<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/hpv_results.php';
require_once __DIR__ . '/scheduled_messages.php';
require_once __DIR__ . '/afya_rafiki_content.php';
require_once __DIR__ . '/encouragement_drip.php';
require_once __DIR__ . '/patient_client_id.php';

/**
 * True when an outbound row reached the patient (delivered, or sent without a hard failure).
 */
function patient_message_type_succeeded(int $patientId, string $messageType): bool
{
    $st = db()->prepare(
        "SELECT status, error_detail FROM outbound_messages
         WHERE patient_id = ? AND message_type = ?
         ORDER BY id DESC
         LIMIT 20"
    );
    $st->execute([$patientId, $messageType]);
    while ($row = $st->fetch()) {
        $status = strtolower((string) ($row['status'] ?? ''));
        $error = strtolower((string) ($row['error_detail'] ?? ''));
        if ($status === 'delivered') {
            return true;
        }
        if ($status === 'sent') {
            return true;
        }
        if ($status === 'failed' && is_send_error_retryable((string) ($row['error_detail'] ?? ''))) {
            continue;
        }
        if ($status === 'failed' && (str_contains($error, 'insufficientbalance') || str_contains($error, 'insufficient balance'))) {
            continue;
        }
    }

    return false;
}

/**
 * Send all applicable Afya Rafiki messages for one patient only.
 *
 * @return array<string, mixed>
 */
function replay_patient_messages(int $patientId): array
{
    $pdo = db();
    $st = $pdo->prepare(
        'SELECT id, full_name, preferred_language, status, hpv_screening_result,
                hpv_result_recorded_at, hpv_result_confirmed_at, hpv_counseling_index
         FROM patients WHERE id = ? LIMIT 1'
    );
    $st->execute([$patientId]);
    $patient = $st->fetch();
    if (!$patient) {
        return ['ok' => false, 'error' => 'Patient not found'];
    }

    $lang = in_array($patient['preferred_language'], ['en', 'sw'], true)
        ? $patient['preferred_language']
        : 'en';
    $name = (string) $patient['full_name'];
    $sent = [];
    $failed = [];
    $skipped = [];
    $smsBalanceLow = sms_insufficient_balance_recently();

    $optSt = $pdo->prepare('SELECT 1 FROM contact_channels WHERE patient_id = ? AND opted_in = 1 LIMIT 1');
    $optSt->execute([$patientId]);
    if (!$optSt->fetchColumn()) {
        return ['ok' => false, 'error' => 'Patient is not opted in to messages'];
    }

    $trySend = static function (string $label, string $type, string $body) use ($patientId, &$sent, &$failed): void {
        $ok = send_patient_message($patientId, $type, $body);
        if ($ok) {
            $sent[] = ['label' => $label, 'message_type' => $type];
        } else {
            $last = db()->prepare(
                'SELECT error_detail FROM outbound_messages
                 WHERE patient_id = ? AND message_type = ?
                 ORDER BY id DESC LIMIT 1'
            );
            $last->execute([$patientId, $type]);
            $failed[] = [
                'label' => $label,
                'message_type' => $type,
                'error' => (string) ($last->fetchColumn() ?: 'Send failed'),
            ];
        }
    };

    if (!patient_message_type_succeeded($patientId, 'system')) {
        $trySend('consent_thank_you', 'system', build_consent_thank_you_message($name, $lang));
    } else {
        $skipped[] = 'consent_thank_you';
    }

    if (!patient_message_type_succeeded($patientId, 'registration_welcome')) {
        $trySend('registration_welcome', 'registration_welcome', build_registration_welcome_message($lang));
    } else {
        $skipped[] = 'registration_welcome';
    }

    $apptSt = $pdo->prepare(
        "SELECT id, department, provider_name, scheduled_start, scheduled_end, location, status
         FROM appointments
         WHERE patient_id = ? AND status IN ('proposed', 'confirmed')
         ORDER BY scheduled_start ASC"
    );
    $apptSt->execute([$patientId]);
    $appointments = $apptSt->fetchAll();
    foreach ($appointments as $appt) {
        $startSql = (string) ($appt['scheduled_start'] ?? '');
        if ($startSql === '') {
            continue;
        }
        $reasonSt = $pdo->prepare(
            'SELECT reason FROM appointment_reschedule_events
             WHERE appointment_id = ? ORDER BY created_at ASC, id ASC LIMIT 1'
        );
        $reasonSt->execute([(int) $appt['id']]);
        $reason = (string) ($reasonSt->fetchColumn() ?: 'Appointment');

        if (!patient_message_type_succeeded($patientId, 'appointment_booked')) {
            $trySend(
                'appointment_booked_' . $appt['id'],
                'appointment_booked',
                build_appointment_change_message($name, [
                    'scheduled_start' => $startSql,
                    'scheduled_end' => $appt['scheduled_end'],
                    'department' => $appt['department'],
                    'provider_name' => $appt['provider_name'],
                    'location' => $appt['location'],
                ], $reason, false, $lang)
            );
        } else {
            $skipped[] = 'appointment_booked';
        }
    }

    $hpvResult = strtolower((string) ($patient['hpv_screening_result'] ?? ''));
    if (in_array($hpvResult, ['positive', 'negative', 'failed'], true) && empty($patient['hpv_result_confirmed_at'])) {
        $confirm = confirm_patient_hpv_result($patientId, 'replay_patient_messages');
        if (!empty($confirm['ok'])) {
            $sent[] = ['label' => 'hpv_confirm_' . $hpvResult, 'message_type' => 'confirm_result'];
        } else {
            $failed[] = [
                'label' => 'hpv_confirm_' . $hpvResult,
                'message_type' => 'confirm_result',
                'error' => (string) ($confirm['error'] ?? 'HPV confirm failed'),
            ];
        }
    } elseif (!empty($patient['hpv_result_confirmed_at'])) {
        if ($hpvResult === 'failed' && !patient_message_type_succeeded($patientId, 'hpv_failed')) {
            $apptDate = afya_next_appointment_display($patientId);
            $trySend('hpv_failed', 'hpv_failed', build_hpv_failed_result_notification($name, $apptDate, $lang));
        } elseif ($hpvResult === 'negative' && !patient_message_type_succeeded($patientId, 'hpv_negative')) {
            $trySend(
                'hpv_negative',
                'hpv_negative',
                build_hpv_negative_result_notification($name, afya_patient_hiv_status($patientId), $lang)
            );
        } elseif ($hpvResult === 'positive') {
            if (!patient_message_type_succeeded($patientId, 'system') || !patient_has_queued_encouragement_drip($patientId)) {
                $dripStarted = start_hpv_positive_counseling_drip_on_confirm($patientId);
                if ($dripStarted) {
                    $sent[] = ['label' => 'hpv_counseling_drip_started', 'message_type' => 'hpv_counseling'];
                }
            }
        }
        $skipped[] = 'hpv_already_confirmed';
    }

    $forceSt = $pdo->prepare(
        'UPDATE scheduled_messages
         SET send_at = NOW(3)
         WHERE patient_id = ? AND status = "queued" AND send_at > NOW(3)'
    );
    $forceSt->execute([$patientId]);
    $scheduledForced = $forceSt->rowCount();
    $scheduledProcessed = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'batches' => 0];
    for ($i = 0; $i < 20; $i++) {
        $batch = process_patient_due_scheduled_messages($patientId);
        $scheduledProcessed['batches']++;
        $scheduledProcessed['processed'] += (int) ($batch['processed'] ?? 0);
        $scheduledProcessed['sent'] += (int) ($batch['sent'] ?? 0);
        $scheduledProcessed['failed'] += (int) ($batch['failed'] ?? 0);
        if ((int) ($batch['processed'] ?? 0) === 0) {
            break;
        }
    }

    return [
        'ok' => count($failed) === 0,
        'patient_id' => $patientId,
        'full_name' => $name,
        'sms_balance_low' => $smsBalanceLow,
        'sent' => $sent,
        'failed' => $failed,
        'skipped' => $skipped,
        'scheduled_queued_forced_now' => $scheduledForced === false ? 0 : (int) $scheduledForced,
        'scheduled_processed' => $scheduledProcessed,
    ];
}

/** @return array{processed: int, sent: int, failed: int} */
function process_patient_due_scheduled_messages(int $patientId): array
{
    $pdo = db();
    $chainCol = scheduled_messages_has_counseling_chain_column()
        ? ', triggers_counseling_chain'
        : '';
    $st = $pdo->prepare(
        "SELECT id, patient_id, message_type, body{$chainCol}
         FROM scheduled_messages
         WHERE patient_id = ? AND status = 'queued' AND send_at <= NOW(3)
         ORDER BY send_at ASC
         LIMIT 50"
    );
    $st->execute([$patientId]);
    $rows = $st->fetchAll();

    $sent = 0;
    $failed = 0;
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $type = (string) $row['message_type'];
        $body = (string) $row['body'];
        $chain = !empty($row['triggers_counseling_chain']);
        $ok = send_patient_message($patientId, $type, $body);
        $upd = $pdo->prepare(
            'UPDATE scheduled_messages SET status = ?, sent_at = NOW(3) WHERE id = ?'
        );
        if ($ok) {
            $upd->execute(['sent', $id]);
            $sent++;
            if ($type === 'hpv_counseling' && function_exists('encouragement_drip_step_sent')) {
                encouragement_drip_step_sent($patientId);
            } elseif ($chain && function_exists('encouragement_drip_step_sent')) {
                encouragement_drip_step_sent($patientId);
            }
        } else {
            $upd->execute(['failed', $id]);
            $failed++;
        }
    }

    return ['processed' => count($rows), 'sent' => $sent, 'failed' => $failed];
}

/**
 * @return array<string, mixed>
 */
function replay_patient_messages_by_client_id(string $clientRef): array
{
    $patientId = resolve_patient_id_by_client_id($clientRef);
    if ($patientId === null || $patientId < 1) {
        return ['ok' => false, 'error' => 'Patient not found for client number: ' . normalize_client_id_full($clientRef)];
    }

    return replay_patient_messages($patientId);
}
