<?php
declare(strict_types=1);

/**
 * Missed appointment conversation — study §13 (survey → reschedule offer → confirm).
 */

require_once __DIR__ . '/db.php';

function ensure_missed_appointment_flow_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS missed_appointment_flows (
            patient_id INT UNSIGNED NOT NULL PRIMARY KEY,
            appointment_id INT UNSIGNED NULL,
            status ENUM('awaiting_reason','awaiting_reschedule','reschedule_requested','closed') NOT NULL DEFAULT 'awaiting_reason',
            reason_code TINYINT UNSIGNED NULL,
            updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function missed_flow_get(int $patientId): ?array
{
    ensure_missed_appointment_flow_schema();
    $st = db()->prepare(
        'SELECT patient_id, appointment_id, status, reason_code, updated_at
         FROM missed_appointment_flows WHERE patient_id = ? LIMIT 1'
    );
    $st->execute([$patientId]);
    $row = $st->fetch();
    return $row ?: null;
}

function missed_flow_start(int $patientId, int $appointmentId): void
{
    ensure_missed_appointment_flow_schema();
    $st = db()->prepare(
        'INSERT INTO missed_appointment_flows (patient_id, appointment_id, status, reason_code)
         VALUES (?, ?, \'awaiting_reason\', NULL)
         ON DUPLICATE KEY UPDATE appointment_id = VALUES(appointment_id), status = \'awaiting_reason\',
             reason_code = NULL, updated_at = NOW(3)'
    );
    $st->execute([$patientId, $appointmentId]);
}

function missed_flow_close(int $patientId): void
{
    ensure_missed_appointment_flow_schema();
    db()->prepare(
        'UPDATE missed_appointment_flows SET status = \'closed\', updated_at = NOW(3) WHERE patient_id = ?'
    )->execute([$patientId]);
}

function patient_awaiting_missed_reason(int $patientId): bool
{
    $row = missed_flow_get($patientId);
    return ($row['status'] ?? '') === 'awaiting_reason';
}

function patient_awaiting_missed_reschedule(int $patientId): bool
{
    $row = missed_flow_get($patientId);
    return ($row['status'] ?? '') === 'awaiting_reschedule';
}

function patient_missed_reschedule_requested(int $patientId): bool
{
    $row = missed_flow_get($patientId);
    return ($row['status'] ?? '') === 'reschedule_requested';
}

function is_missed_reason_reply(string $body): ?int
{
    $trim = trim($body);
    if (preg_match('/^[1-7]$/', $trim)) {
        return (int) $trim;
    }
    return null;
}

function is_missed_reschedule_yes(string $body, string $lang): bool
{
    $trim = strtoupper(trim($body));
    if ($trim === '1' || $trim === 'YES' || $trim === 'NDIO') {
        return true;
    }
    if ($lang === 'sw' && str_contains(mb_strtolower($body), 'ndio')) {
        return true;
    }
    return (bool) preg_match('/\b(yes|ndio)\b/ui', $body);
}

function is_missed_reschedule_no(string $body, string $lang): bool
{
    $trim = strtoupper(trim($body));
    if ($trim === '2' || $trim === 'NO' || $trim === 'HAPANA') {
        return true;
    }
    unset($lang);
    return (bool) preg_match('/\b(no|hapana)\b/ui', $body);
}

function is_missed_reschedule_doctor(string $body): bool
{
    $trim = strtoupper(trim($body));
    return $trim === '3'
        || str_contains($trim, 'DOCTOR')
        || str_contains($trim, 'DAKTARI')
        || str_contains(mb_strtolower($body), 'mhudumu');
}

/** Start §13 flow when nurse marks appointment missed. */
function missed_flow_on_appointment_missed(int $patientId, int $appointmentId): void
{
    missed_flow_start($patientId, $appointmentId);
}

/**
 * Handle inbound replies for §13. Returns true if consumed (skip FAQ/AI).
 *
 * @return array{handled: bool, action?: string}
 */
function try_handle_missed_appointment_inbound(int $patientId, string $body, string $channel, string $lang): array
{
    require_once __DIR__ . '/afya_rafiki_content.php';
    require_once __DIR__ . '/messaging.php';
    require_once __DIR__ . '/doctor_call_requests.php';

    $row = missed_flow_get($patientId);
    if (!$row) {
        return ['handled' => false];
    }

    $status = (string) ($row['status'] ?? '');
    $replyLang = $lang === 'sw' ? 'sw' : 'en';

    if ($status === 'awaiting_reason') {
        $code = is_missed_reason_reply($body);
        if ($code === null) {
            return ['handled' => false];
        }
        db()->prepare(
            'UPDATE missed_appointment_flows SET status = \'awaiting_reschedule\', reason_code = ?, updated_at = NOW(3) WHERE patient_id = ?'
        )->execute([$code, $patientId]);
        send_patient_message(
            $patientId,
            'missed_reschedule_offer',
            build_missed_appointment_reschedule_offer($replyLang)
        );
        return ['handled' => true, 'action' => 'missed_reschedule_offer'];
    }

    if ($status === 'awaiting_reschedule') {
        if (is_missed_reschedule_doctor($body)) {
            missed_flow_close($patientId);
            handle_doctor_request_keyword($patientId, $channel, $replyLang);
            return ['handled' => true, 'action' => 'missed_doctor'];
        }
        if (is_missed_reschedule_no($body, $replyLang)) {
            missed_flow_close($patientId);
            send_patient_message(
                $patientId,
                'system',
                $replyLang === 'sw'
                    ? 'Asante. Unaweza kuwasiliana na Nyeri Town Health Centre wakati wowote ukiwa tayari kupanga miadi.'
                    : 'Thank you. You may contact Nyeri Town Health Centre whenever you are ready to schedule.'
            );
            return ['handled' => true, 'action' => 'missed_self_contact'];
        }
        if (is_missed_reschedule_yes($body, $replyLang)) {
            db()->prepare(
                'UPDATE missed_appointment_flows SET status = \'reschedule_requested\', updated_at = NOW(3) WHERE patient_id = ?'
            )->execute([$patientId]);
            create_escalation(
                $patientId,
                'Patient requested appointment reschedule after missed visit (survey reply).',
                'routine'
            );
            send_patient_message(
                $patientId,
                'system',
                $replyLang === 'sw'
                    ? 'Asante. Mhudumu wa afya atawasiliana nawe au panga miadi yako upya hospitalini.'
                    : 'Thank you. A healthcare provider will contact you, or please book a new appointment at the clinic.'
            );
            return ['handled' => true, 'action' => 'missed_reschedule_requested'];
        }
        return ['handled' => false];
    }

    return ['handled' => false];
}

/** Send §13c when staff books a new appointment after missed-flow reschedule request. */
function try_send_missed_reschedule_confirmation(int $patientId, string $appointmentDateDisplay): bool
{
    if (!patient_missed_reschedule_requested($patientId)) {
        return false;
    }

    require_once __DIR__ . '/messaging.php';
    require_once __DIR__ . '/afya_rafiki_content.php';

    $st = db()->prepare('SELECT full_name, preferred_language FROM patients WHERE id = ? LIMIT 1');
    $st->execute([$patientId]);
    $row = $st->fetch();
    if (!$row) {
        return false;
    }

    $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    $sent = send_patient_message(
        $patientId,
        'missed_reschedule_confirm',
        build_missed_reschedule_confirmation((string) $row['full_name'], $appointmentDateDisplay, $lang)
    );
    if ($sent) {
        missed_flow_close($patientId);
    }
    return $sent;
}
