<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/scheduled_messages.php';

/**
 * Process all due scheduled rows (cron normally stops at 100 per run).
 *
 * @return array{processed: int, sent: int, failed: int, batches: int}
 */
function flush_all_due_scheduled_messages(int $maxBatches = 20): array
{
    $totals = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'batches' => 0];
    for ($i = 0; $i < $maxBatches; $i++) {
        $batch = process_due_scheduled_messages();
        $totals['batches']++;
        $totals['processed'] += (int) ($batch['processed'] ?? 0);
        $totals['sent'] += (int) ($batch['sent'] ?? 0);
        $totals['failed'] += (int) ($batch['failed'] ?? 0);
        if ((int) ($batch['processed'] ?? 0) === 0) {
            break;
        }
    }

    return $totals;
}

/**
 * Outbound rows that never reached the patient (failed, or SMS still "sent" with no delivery report).
 *
 * @param list<int>|null $outboundIds Resend specific rows only when set.
 * @return array<string, mixed>
 */
function resend_undelivered_outbound(?array $outboundIds = null, int $lookbackHours = 168, int $maxResends = 200): array
{
    $pdo = db();
    $lookbackHours = max(1, min(720, $lookbackHours));
    $maxResends = max(1, min(500, $maxResends));

    $idFilter = '';
    $args = [$lookbackHours];
    if (is_array($outboundIds) && $outboundIds !== []) {
        $cleanIds = array_values(array_filter(array_map('intval', $outboundIds), static fn (int $id): bool => $id > 0));
        if ($cleanIds === []) {
            return [
                'candidates' => 0,
                'resent' => 0,
                'resend_failed' => 0,
                'skipped' => 0,
                'details' => [],
            ];
        }
        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $idFilter = " AND o.id IN ({$placeholders})";
        $args = array_merge($args, $cleanIds);
    }

    $sql = "SELECT o.id, o.patient_id, o.message_type, o.body, o.channel, o.status, o.error_detail, o.created_at,
                   p.full_name, p.external_mrn AS client_id
            FROM outbound_messages o
            INNER JOIN patients p ON p.id = o.patient_id
            WHERE o.created_at >= DATE_SUB(NOW(3), INTERVAL ? HOUR)
              AND (
                  o.status = 'failed'
                  OR (
                      o.status = 'sent'
                      AND o.channel = 'sms'
                      AND o.created_at <= DATE_SUB(NOW(3), INTERVAL 2 HOUR)
                  )
              )
              AND NOT EXISTS (
                  SELECT 1 FROM outbound_messages o2
                  WHERE o2.patient_id = o.patient_id
                    AND o2.message_type = o.message_type
                    AND o2.status IN ('sent', 'delivered')
                    AND o2.id > o.id
                    AND (
                        o2.body = o.body
                        OR o2.status = 'delivered'
                    )
              )
              {$idFilter}
            ORDER BY o.created_at ASC
            LIMIT {$maxResends}";

    $st = $pdo->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll();

    $resent = 0;
    $resendFailed = 0;
    $skipped = 0;
    $details = [];

    foreach ($rows as $row) {
        $outboundId = (int) ($row['id'] ?? 0);
        $patientId = (int) ($row['patient_id'] ?? 0);
        $type = (string) ($row['message_type'] ?? 'system');
        $body = (string) ($row['body'] ?? '');
        if ($patientId < 1 || $body === '') {
            $skipped++;
            continue;
        }

        $ok = send_patient_message($patientId, $type, $body);
        $detail = [
            'outbound_id' => $outboundId,
            'patient_id' => $patientId,
            'patient' => (string) ($row['full_name'] ?? ''),
            'message_type' => $type,
            'channel' => (string) ($row['channel'] ?? ''),
            'previous_status' => (string) ($row['status'] ?? ''),
            'result' => $ok ? 'resent' : 'still_failed',
        ];
        if ($ok) {
            $resent++;
        } else {
            $resendFailed++;
        }
        $details[] = $detail;
    }

    return [
        'lookback_hours' => $lookbackHours,
        'candidates' => count($rows),
        'resent' => $resent,
        'resend_failed' => $resendFailed,
        'skipped' => $skipped,
        'details' => $details,
    ];
}

/**
 * Re-queue failed scheduled messages and resend failed outbound SMS/WhatsApp.
 *
 * @return array<string, mixed>
 */
function resend_stuck_messages(int $lookbackHours = 168, int $maxOutboundResends = 200, bool $forceQueuedNow = true): array
{
    $pdo = db();
    $lookbackHours = max(1, min(720, $lookbackHours));
    $maxOutboundResends = max(1, min(500, $maxOutboundResends));

    $before = scheduled_messages_queue_stats();

    $scheduledQueuedForced = 0;
    if ($forceQueuedNow) {
        $force = $pdo->exec(
            "UPDATE scheduled_messages
             SET send_at = NOW(3)
             WHERE status = 'queued' AND send_at > NOW(3)"
        );
        $scheduledQueuedForced = $force === false ? 0 : (int) $force;
    }

    $requeue = $pdo->prepare(
        "UPDATE scheduled_messages
         SET status = 'queued', send_at = NOW(3), sent_at = NULL
         WHERE status = 'failed'
           AND send_at >= DATE_SUB(NOW(3), INTERVAL ? HOUR)"
    );
    $requeue->execute([$lookbackHours]);
    $scheduledFailedRequeued = $requeue->rowCount();

    $scheduledProcessed = flush_all_due_scheduled_messages();

    $outboundResend = resend_undelivered_outbound(null, $lookbackHours, $maxOutboundResends);

    $dripRepaired = 0;
    if (function_exists('repair_stalled_hpv_positive_drips')) {
        require_once __DIR__ . '/encouragement_drip.php';
        $dripRepaired = repair_stalled_hpv_positive_drips();
    }

    $after = scheduled_messages_queue_stats();

    return [
        'lookback_hours' => $lookbackHours,
        'queue_before' => $before,
        'queue_after' => $after,
        'scheduled_queued_forced_now' => $scheduledQueuedForced,
        'scheduled_failed_requeued' => $scheduledFailedRequeued,
        'scheduled_processed' => $scheduledProcessed,
        'outbound_failed_candidates' => (int) ($outboundResend['candidates'] ?? 0),
        'outbound_resent' => (int) ($outboundResend['resent'] ?? 0),
        'outbound_resend_failed' => (int) ($outboundResend['resend_failed'] ?? 0),
        'outbound_skipped' => (int) ($outboundResend['skipped'] ?? 0),
        'hpv_drip_repaired' => $dripRepaired,
        'outbound_details' => $outboundResend['details'] ?? [],
    ];
}
