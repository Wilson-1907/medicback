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
 * Re-queue failed scheduled messages and resend failed outbound SMS/WhatsApp.
 *
 * @return array<string, mixed>
 */
function resend_stuck_messages(int $lookbackHours = 168, int $maxOutboundResends = 200): array
{
    $pdo = db();
    $lookbackHours = max(1, min(720, $lookbackHours));
    $maxOutboundResends = max(1, min(500, $maxOutboundResends));

    $before = scheduled_messages_queue_stats();

    $requeue = $pdo->prepare(
        "UPDATE scheduled_messages
         SET status = 'queued', send_at = NOW(3), sent_at = NULL
         WHERE status = 'failed'
           AND send_at >= DATE_SUB(NOW(3), INTERVAL ? HOUR)"
    );
    $requeue->execute([$lookbackHours]);
    $scheduledFailedRequeued = $requeue->rowCount();

    $scheduledProcessed = flush_all_due_scheduled_messages();

    $st = $pdo->prepare(
        "SELECT o.id, o.patient_id, o.message_type, o.body, o.channel, o.created_at, p.full_name
         FROM outbound_messages o
         INNER JOIN patients p ON p.id = o.patient_id
         WHERE o.status = 'failed'
           AND o.created_at >= DATE_SUB(NOW(3), INTERVAL ? HOUR)
           AND NOT EXISTS (
               SELECT 1 FROM outbound_messages o2
               WHERE o2.patient_id = o.patient_id
                 AND o2.message_type = o.message_type
                 AND o2.body = o.body
                 AND o2.status IN ('sent', 'delivered')
                 AND o2.id > o.id
           )
         ORDER BY o.created_at ASC
         LIMIT {$maxOutboundResends}"
    );
    $st->execute([$lookbackHours]);
    $failedRows = $st->fetchAll();

    $outboundResent = 0;
    $outboundResendFailed = 0;
    $outboundSkipped = 0;
    $outboundDetails = [];

    foreach ($failedRows as $row) {
        $patientId = (int) ($row['patient_id'] ?? 0);
        $type = (string) ($row['message_type'] ?? 'system');
        $body = (string) ($row['body'] ?? '');
        if ($patientId < 1 || $body === '') {
            $outboundSkipped++;
            continue;
        }

        $ok = send_patient_message($patientId, $type, $body);
        if ($ok) {
            $outboundResent++;
            $outboundDetails[] = [
                'patient_id' => $patientId,
                'patient' => (string) ($row['full_name'] ?? ''),
                'message_type' => $type,
                'channel' => (string) ($row['channel'] ?? ''),
                'result' => 'resent',
            ];
        } else {
            $outboundResendFailed++;
            $outboundDetails[] = [
                'patient_id' => $patientId,
                'patient' => (string) ($row['full_name'] ?? ''),
                'message_type' => $type,
                'channel' => (string) ($row['channel'] ?? ''),
                'result' => 'still_failed',
            ];
        }
    }

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
        'scheduled_failed_requeued' => $scheduledFailedRequeued,
        'scheduled_processed' => $scheduledProcessed,
        'outbound_failed_candidates' => count($failedRows),
        'outbound_resent' => $outboundResent,
        'outbound_resend_failed' => $outboundResendFailed,
        'outbound_skipped' => $outboundSkipped,
        'hpv_drip_repaired' => $dripRepaired,
        'outbound_details' => $outboundDetails,
    ];
}
