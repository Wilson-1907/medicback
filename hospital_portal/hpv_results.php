<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/afya_rafiki_content.php';
require_once __DIR__ . '/scheduled_messages.php';

/**
 * Add HPV workflow columns/tables if missing (safe to call repeatedly).
 */
function ensure_hpv_workflow_schema(): bool
{
    try {
        $pdo = db();

        if (!db_table_has_column('patients', 'hpv_screening_result')) {
            $pdo->exec(
                "ALTER TABLE patients
                 ADD COLUMN hpv_screening_result ENUM('unknown','pending','positive','negative','failed') NOT NULL DEFAULT 'pending'"
            );
        } else {
            ensure_hpv_failed_result_enum($pdo);
        }
        if (!db_table_has_column('patients', 'hpv_result_recorded_at')) {
            $pdo->exec('ALTER TABLE patients ADD COLUMN hpv_result_recorded_at DATETIME(3) NULL');
        }
        if (!db_table_has_column('patients', 'hpv_result_confirmed_at')) {
            $pdo->exec('ALTER TABLE patients ADD COLUMN hpv_result_confirmed_at DATETIME(3) NULL');
        }
        if (!db_table_has_column('patients', 'hpv_counseling_index')) {
            $pdo->exec('ALTER TABLE patients ADD COLUMN hpv_counseling_index INT UNSIGNED NOT NULL DEFAULT 0');
        }

        if (!db_table_exists('scheduled_messages')) {
            $pdo->exec(
                "CREATE TABLE scheduled_messages (
                  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  patient_id BIGINT UNSIGNED NOT NULL,
                  message_type VARCHAR(32) NOT NULL DEFAULT 'system',
                  body TEXT NOT NULL,
                  send_at DATETIME(3) NOT NULL,
                  sent_at DATETIME(3) NULL,
                  status ENUM('queued','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
                  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                  KEY idx_sched_patient (patient_id),
                  KEY idx_sched_due (status, send_at),
                  CONSTRAINT fk_sched_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (db_table_exists('scheduled_messages')
            && !db_table_has_column('scheduled_messages', 'triggers_counseling_chain')) {
            $pdo->exec(
                'ALTER TABLE scheduled_messages
                 ADD COLUMN triggers_counseling_chain TINYINT(1) NOT NULL DEFAULT 0'
            );
        }

        if (!db_table_exists('diagnosis_results')) {
            $pdo->exec(
                "CREATE TABLE diagnosis_results (
                  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  patient_id BIGINT UNSIGNED NOT NULL,
                  appointment_id BIGINT UNSIGNED NULL,
                  coded_diagnosis VARCHAR(64) NULL,
                  diagnosis_label VARCHAR(512) NOT NULL,
                  severity ENUM('unknown','mild','moderate','severe') NOT NULL DEFAULT 'unknown',
                  result_summary TEXT NULL,
                  recorded_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                  recorded_by VARCHAR(128) NULL,
                  KEY idx_dx_patient (patient_id),
                  KEY idx_dx_appt (appointment_id),
                  CONSTRAINT fk_dx_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        return true;
    } catch (Throwable $e) {
        error_log('ensure_hpv_workflow_schema: ' . $e->getMessage());
        return false;
    }
}

function hpv_workflow_ready(): bool
{
    try {
        db()->query('SELECT hpv_screening_result FROM patients LIMIT 1');
        return true;
    } catch (Throwable $e) {
        if (!ensure_hpv_workflow_schema()) {
            return false;
        }
        try {
            db()->query('SELECT hpv_screening_result FROM patients LIMIT 1');
            return true;
        } catch (Throwable $e2) {
            error_log('hpv_workflow_ready: ' . $e2->getMessage());
            return false;
        }
    }
}

function hpv_workflow_unavailable_message(): string
{
    return 'HPV result recording is not available right now. Please contact your system administrator.';
}

/** Add `failed` to hpv_screening_result enum on existing databases (safe to call repeatedly). */
function ensure_hpv_failed_result_enum(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $pdo = $pdo ?? db();
        $row = $pdo->query("SHOW COLUMNS FROM patients LIKE 'hpv_screening_result'")->fetch(PDO::FETCH_ASSOC);
        $type = (string) ($row['Type'] ?? '');
        if ($type !== '' && !str_contains($type, 'failed')) {
            $pdo->exec(
                "ALTER TABLE patients
                 MODIFY hpv_screening_result ENUM('unknown','pending','positive','negative','failed') NOT NULL DEFAULT 'pending'"
            );
        }
        $done = true;
    } catch (Throwable $e) {
        error_log('ensure_hpv_failed_result_enum: ' . $e->getMessage());
    }
}

function patient_hpv_result_is_recorded(array $row): bool
{
    $result = strtolower((string) ($row['hpv_screening_result'] ?? ''));
    return in_array($result, ['positive', 'negative', 'failed'], true)
        && !empty($row['hpv_result_recorded_at']);
}

/** HPV failed (insufficient sample) confirmed — proceed to VIA at clinic visit. */
function patient_hpv_pathway_complete(array $row): bool
{
    $result = strtolower((string) ($row['hpv_screening_result'] ?? ''));
    return !empty($row['hpv_result_confirmed_at'])
        && in_array($result, ['positive', 'failed'], true);
}

function get_patient_hpv_row(int $patientId): ?array
{
    if (!hpv_workflow_ready()) {
        return null;
    }
    $st = db()->prepare(
        'SELECT id, full_name, preferred_language, hpv_screening_result,
                hpv_result_recorded_at, hpv_result_confirmed_at, hpv_counseling_index
         FROM patients WHERE id = ? LIMIT 1'
    );
    $st->execute([$patientId]);
    $row = $st->fetch();
    return $row ?: null;
}

function patient_hpv_results_confirmed(int $patientId): bool
{
    $row = get_patient_hpv_row($patientId);
    return $row && !empty($row['hpv_result_confirmed_at']);
}

/** After consent YES: thank you, promise results, schedule generic tip in 3 minutes. */
function handle_consent_accepted(int $patientId, string $patientFullName, string $lang): void
{
    send_patient_message(
        $patientId,
        'system',
        build_consent_thank_you_message($patientFullName, $lang)
    );

    $tip = build_random_generic_encouragement($lang);
    schedule_patient_message($patientId, 'engagement_boost', $tip, '+3 minutes');
}

/**
 * @param array<string, mixed> $intake
 */
function validate_hpv_positive_intake(array $intake): ?string
{
    $lang = trim((string) ($intake['preferred_language'] ?? ''));
    if (!in_array($lang, ['en', 'sw'], true)) {
        return 'Select preferred language: English or Kiswahili.';
    }

    $channel = strtolower(trim((string) ($intake['contact_channel'] ?? '')));
    if (!in_array($channel, ['sms', 'whatsapp'], true)) {
        return 'Select contact channel: SMS or WhatsApp.';
    }

    $hpvDone = strtolower(trim((string) ($intake['hpv_done_before'] ?? '')));
    if (!in_array($hpvDone, ['yes', 'no'], true)) {
        return 'Indicate whether the patient was ever tested for HPV before.';
    }

    if ($hpvDone === 'yes') {
        $hpvPrior = strtolower(trim((string) ($intake['hpv_prior_result'] ?? 'unknown')));
        if (!in_array($hpvPrior, ['positive', 'negative'], true)) {
            return 'Prior HPV result is required when the patient was tested before.';
        }
    }

    return null;
}

/**
 * Optional intake from API/POST — null when caller did not send registration fields
 * (e.g. Record POSITIVE only records lab result; use Edit registration for channel/language).
 *
 * @param array<string, mixed> $body
 * @return array<string, string>|null
 */
function parse_optional_hpv_positive_intake(array $body): ?array
{
    $lang = trim((string) ($body['preferred_language'] ?? ''));
    $channel = trim((string) ($body['contact_channel'] ?? ''));
    $hpvDone = trim((string) ($body['hpv_done_before'] ?? ''));

    if ($lang === '' && $channel === '' && $hpvDone === '') {
        return null;
    }

    return [
        'preferred_language' => $lang,
        'contact_channel' => $channel,
        'hpv_done_before' => $hpvDone,
        'hpv_prior_result' => (string) ($body['hpv_prior_result'] ?? 'unknown'),
    ];
}

/**
 * Update channel, language, and HPV history when recording HPV positive.
 *
 * @param array<string, mixed> $intake
 */
function apply_hpv_positive_intake(int $patientId, array $intake): ?string
{
    $err = validate_hpv_positive_intake($intake);
    if ($err !== null) {
        return $err;
    }

    require_once __DIR__ . '/patient_screening.php';
    ensure_patient_screening_schema();

    $lang = (string) $intake['preferred_language'];
    $hpvDone = strtolower(trim((string) $intake['hpv_done_before']));
    $hpvPrior = $hpvDone === 'yes'
        ? strtolower(trim((string) ($intake['hpv_prior_result'] ?? 'unknown')))
        : 'unknown';

    db()->prepare(
        'UPDATE patients SET preferred_language = ?, hpv_done_before = ?, hpv_prior_result = ? WHERE id = ?'
    )->execute([$lang, $hpvDone, $hpvPrior, $patientId]);

    $channel = strtolower(trim((string) $intake['contact_channel'])) === 'whatsapp' ? 'whatsapp' : 'sms';
    update_patient_primary_channel($patientId, $channel);

    return null;
}

function set_patient_hpv_result(int $patientId, string $result, string $recordedBy = 'staff', ?array $intake = null): array
{
    if (!hpv_workflow_ready()) {
        return ['ok' => false, 'error' => hpv_workflow_unavailable_message()];
    }
    $result = strtolower(trim($result));
    if (!in_array($result, ['positive', 'negative', 'failed'], true)) {
        return ['ok' => false, 'error' => 'Result must be positive, negative, or failed'];
    }

    if ($result === 'positive' && $intake !== null && $intake !== []) {
        $intakeErr = apply_hpv_positive_intake($patientId, $intake);
        if ($intakeErr !== null) {
            return ['ok' => false, 'error' => $intakeErr];
        }
    }

    $st = db()->prepare(
        'UPDATE patients
         SET hpv_screening_result = ?,
             hpv_result_recorded_at = NOW(3),
             hpv_result_confirmed_at = NULL,
             hpv_counseling_index = 0
         WHERE id = ?'
    );
    $st->execute([$result, $patientId]);
    if ($st->rowCount() < 1) {
        return ['ok' => false, 'error' => 'Patient not found'];
    }

    try {
        cancel_queued_counseling_schedule($patientId);
    } catch (Throwable $e) {
        error_log('cancel_queued_counseling_schedule: ' . $e->getMessage());
    }

    if ($result === 'positive') {
        require_once __DIR__ . '/encouragement_drip.php';
        arm_hpv_positive_counseling_drip($patientId);
    } elseif ($result === 'negative') {
        require_once __DIR__ . '/encouragement_drip.php';
        cancel_queued_health_tips_for_patient($patientId);
    }

    try {
        ensure_hpv_workflow_schema();
        $dx = db()->prepare(
            'INSERT INTO diagnosis_results (patient_id, diagnosis_label, severity, result_summary, recorded_by)
             VALUES (?,?,?,?,?)'
        );
        $label = match ($result) {
            'positive' => 'HPV positive',
            'negative' => 'HPV negative',
            default => 'HPV failed (insufficient sample)',
        };
        $dx->execute([
            $patientId,
            $label,
            'unknown',
            'HPV screening result recorded (' . $result . '). Awaiting staff confirmation to notify patient.',
            $recordedBy,
        ]);
    } catch (Throwable $e) {
        error_log('diagnosis_results insert: ' . $e->getMessage());
    }

    $recordedAt = null;
    $recSt = db()->prepare('SELECT hpv_result_recorded_at FROM patients WHERE id = ? LIMIT 1');
    $recSt->execute([$patientId]);
    $recordedAt = $recSt->fetchColumn() ?: null;

    $message = match ($result) {
        'positive' => 'Recorded HPV positive. Book a follow-up appointment, then confirm to notify the patient.',
        'failed' => 'Recorded HPV failed (insufficient sample). Book VIA screening appointment, then confirm to notify the patient.',
        'negative' => 'Recorded HPV negative. Confirm to notify — one result message (3 or 5 years by HIV status), no appointment.',
        default => 'Recorded HPV ' . $result . '. You can now confirm to notify the patient.',
    };

    return [
        'ok' => true,
        'message' => $message,
        'hpv_screening_result' => $result,
        'hpv_result_recorded_at' => $recordedAt ? (string) $recordedAt : null,
        'book_appointment' => in_array($result, ['positive', 'failed'], true),
    ];
}

function confirm_patient_hpv_result(int $patientId, string $confirmedBy = 'staff'): array
{
    if (!hpv_workflow_ready()) {
        return ['ok' => false, 'error' => hpv_workflow_unavailable_message()];
    }

    $row = get_patient_hpv_row($patientId);
    if (!$row) {
        return ['ok' => false, 'error' => 'Patient not found'];
    }

    $result = (string) ($row['hpv_screening_result'] ?? 'pending');
    if (!in_array($result, ['positive', 'negative', 'failed'], true)) {
        return ['ok' => false, 'error' => 'Set HPV result to positive, negative, or failed before confirming'];
    }

    if (!empty($row['hpv_result_confirmed_at'])) {
        return ['ok' => false, 'error' => 'Results were already confirmed and sent to the patient'];
    }

    $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    $name = (string) $row['full_name'];

    if ($result === 'positive' || $result === 'failed') {
        $apptDate = afya_next_appointment_display($patientId);
        if ($apptDate === '__________') {
            return [
                'ok' => false,
                'error' => $result === 'failed'
                    ? 'Book a VIA screening appointment first — the insufficient-sample message needs the appointment date.'
                    : 'Book a follow-up appointment first — the HPV positive message needs the appointment date.',
            ];
        }
    }

    if ($result === 'negative') {
        db()->prepare('UPDATE patients SET hpv_result_confirmed_at = NOW(3) WHERE id = ?')->execute([$patientId]);
    } else {
        db()->prepare(
            'UPDATE patients SET hpv_result_confirmed_at = NOW(3), hpv_counseling_index = 0 WHERE id = ?'
        )->execute([$patientId]);
    }

    $scheduled = false;
    if ($result === 'negative') {
        send_patient_message(
            $patientId,
            'hpv_negative',
            build_hpv_negative_result_notification($name, afya_patient_hiv_status($patientId), $lang)
        );
        require_once __DIR__ . '/encouragement_drip.php';
        complete_encouragement_drip_after_hpv_negative($patientId);
    } elseif ($result === 'failed') {
        $apptDate = afya_next_appointment_display($patientId);
        send_patient_message(
            $patientId,
            'hpv_failed',
            build_hpv_failed_result_notification($name, $apptDate, $lang)
        );
    } else {
        $apptDate = afya_next_appointment_display($patientId);
        if (!patient_has_confirmed_consent($patientId)) {
            send_patient_message(
                $patientId,
                'welcome',
                build_language_introduction_message($lang)
            );
        }
        send_patient_message(
            $patientId,
            'system',
            build_hpv_positive_result_notification($name, $apptDate, $lang)
        );
        require_once __DIR__ . '/encouragement_drip.php';
        $scheduled = start_hpv_positive_counseling_drip_on_confirm($patientId);
    }

    $dx = db()->prepare(
        'INSERT INTO diagnosis_results (patient_id, diagnosis_label, severity, result_summary, recorded_by)
         VALUES (?,?,?,?,?)'
    );
    $dx->execute([
        $patientId,
        match ($result) {
            'positive' => 'HPV positive (confirmed)',
            'failed' => 'HPV failed (confirmed, VIA appointment sent)',
            default => 'HPV negative (confirmed)',
        },
        'unknown',
        'Result confirmed and patient notified via Afya Rafiki.',
        $confirmedBy,
    ]);

    return [
        'ok' => true,
        'hpv_screening_result' => $result,
        'counseling_started' => $scheduled,
        'first_counseling' => 'immediate',
        'next_counseling_in' => '+2 minutes',
    ];
}

/**
 * After a clinic appointment is booked, auto-notify HPV positive patients:
 * sends the lab result SMS (with appointment date), then caller sends appointment confirmation.
 *
 * @return array{confirmed: bool, counseling_started?: bool, error?: string}
 */
function try_auto_confirm_hpv_after_appointment_booked(int $patientId, string $confirmedBy = 'appointment_booked'): array
{
    if (!hpv_workflow_ready()) {
        return ['confirmed' => false];
    }

    $row = get_patient_hpv_row($patientId);
    if (!$row || !empty($row['hpv_result_confirmed_at'])) {
        return ['confirmed' => false];
    }

    $result = strtolower((string) ($row['hpv_screening_result'] ?? ''));
    if ($result !== 'positive' || empty($row['hpv_result_recorded_at'])) {
        return ['confirmed' => false];
    }

    $out = confirm_patient_hpv_result($patientId, $confirmedBy);
    if (empty($out['ok'])) {
        return ['confirmed' => false, 'error' => (string) ($out['error'] ?? 'HPV confirm failed')];
    }

    return [
        'confirmed' => true,
        'counseling_started' => !empty($out['counseling_started']),
    ];
}

/** Delay before each short encouragement tip (gentle pace, not rapid long messages). */
function hpv_delay_before_counseling_index(int $index): string
{
    return match ($index) {
        0 => '+1 day',
        default => '+2 days',
    };
}

function hpv_counseling_message_count(int $patientId): int
{
    $row = get_patient_hpv_row($patientId);
    if (!$row) {
        return 0;
    }
    $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    $result = (string) ($row['hpv_screening_result'] ?? '');
    if ($result === 'positive') {
        return count(afya_counseling_messages_positive($lang));
    }
    if ($result === 'negative') {
        return count(afya_counseling_messages_negative($lang));
    }
    return 0;
}

function hpv_counseling_pathway_complete(int $patientId): bool
{
    if (!patient_hpv_results_confirmed($patientId)) {
        return false;
    }
    return get_hpv_counseling_index($patientId) >= hpv_counseling_message_count($patientId);
}

function get_counseling_message_at_index(int $patientId, int $index, ?string $lang = null): ?string
{
    if (!patient_hpv_results_confirmed($patientId)) {
        return null;
    }
    $row = get_patient_hpv_row($patientId);
    if (!$row) {
        return null;
    }
    if ($lang === null) {
        $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    }
    $result = (string) ($row['hpv_screening_result'] ?? '');
    $messages = $result === 'positive'
        ? afya_counseling_messages_positive($lang)
        : ($result === 'negative' ? afya_counseling_messages_negative($lang) : []);
    return $messages[$index] ?? null;
}

function cancel_queued_counseling_schedule(int $patientId): void
{
    if (!scheduled_messages_has_counseling_chain_column()) {
        return;
    }
    db()->prepare(
        "UPDATE scheduled_messages SET status = 'cancelled'
         WHERE patient_id = ? AND status = 'queued' AND triggers_counseling_chain = 1"
    )->execute([$patientId]);
}

/** Schedule the counseling message at the patient's current index. */
function schedule_hpv_counseling_step(int $patientId, string $delayExpression): bool
{
    if (!patient_hpv_results_confirmed($patientId)) {
        return false;
    }
    if (hpv_counseling_pathway_complete($patientId)) {
        return false;
    }
    $row = get_patient_hpv_row($patientId);
    if (!$row) {
        return false;
    }
    $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    $index = get_hpv_counseling_index($patientId);
    $msg = get_counseling_message_at_index($patientId, $index, $lang);
    if ($msg === null) {
        return false;
    }
    schedule_patient_message($patientId, 'engagement_boost', $msg, $delayExpression, true);
    return true;
}

/** Called by cron after each scheduled counseling SMS is sent. */
function hpv_on_counseling_step_sent(int $patientId): void
{
    advance_hpv_counseling_index($patientId);
    if (hpv_counseling_pathway_complete($patientId)) {
        return;
    }
    $nextIndex = get_hpv_counseling_index($patientId);
    schedule_hpv_counseling_step($patientId, hpv_delay_before_counseling_index($nextIndex));
}

function get_hpv_counseling_index(int $patientId): int
{
    if (!hpv_workflow_ready()) {
        return 0;
    }
    $st = db()->prepare('SELECT hpv_counseling_index FROM patients WHERE id = ?');
    $st->execute([$patientId]);
    return (int) ($st->fetchColumn() ?: 0);
}

function advance_hpv_counseling_index(int $patientId): void
{
    if (!hpv_workflow_ready()) {
        return;
    }
    db()->prepare(
        'UPDATE patients SET hpv_counseling_index = hpv_counseling_index + 1 WHERE id = ?'
    )->execute([$patientId]);
}

/**
 * Reset HPV lab workflow so staff can record the correct result and rebook.
 *
 * @return array<string, mixed>
 */
function clear_patient_hpv_result(int $patientId, bool $cancelUpcomingAppointments = true): array
{
    if (!hpv_workflow_ready()) {
        return ['ok' => false, 'error' => hpv_workflow_unavailable_message()];
    }

    $st = db()->prepare('SELECT id, full_name, hpv_screening_result FROM patients WHERE id = ? LIMIT 1');
    $st->execute([$patientId]);
    $row = $st->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Patient not found'];
    }

    $previous = strtolower((string) ($row['hpv_screening_result'] ?? 'pending'));

    db()->prepare(
        "UPDATE patients
         SET hpv_screening_result = 'pending',
             hpv_result_recorded_at = NULL,
             hpv_result_confirmed_at = NULL,
             hpv_counseling_index = 0
         WHERE id = ?"
    )->execute([$patientId]);

    $cancelledMessages = 0;
    $cancelledAppointments = 0;

    try {
        cancel_queued_counseling_schedule($patientId);
        require_once __DIR__ . '/encouragement_drip.php';
        cancel_queued_health_tips_for_patient($patientId);
        $msgSt = db()->prepare(
            "UPDATE scheduled_messages SET status = 'cancelled'
             WHERE patient_id = ? AND status = 'queued'
               AND message_type IN ('hpv_counseling', 'engagement_boost')"
        );
        $msgSt->execute([$patientId]);
        $cancelledMessages = $msgSt->rowCount();

        db()->prepare(
            "DELETE FROM diagnosis_results
             WHERE patient_id = ? AND diagnosis_label LIKE 'HPV%'"
        )->execute([$patientId]);
    } catch (Throwable $e) {
        error_log('clear_patient_hpv_result messages: ' . $e->getMessage());
    }

    if ($cancelUpcomingAppointments) {
        try {
            $apptSt = db()->prepare(
                "UPDATE appointments SET status = 'cancelled'
                 WHERE patient_id = ?
                   AND status IN ('proposed', 'confirmed')
                   AND scheduled_start >= NOW(3)"
            );
            $apptSt->execute([$patientId]);
            $cancelledAppointments = $apptSt->rowCount();
        } catch (Throwable $e) {
            error_log('clear_patient_hpv_result appointments: ' . $e->getMessage());
        }
    }

    return [
        'ok' => true,
        'patient_id' => $patientId,
        'full_name' => (string) $row['full_name'],
        'previous_hpv_result' => $previous !== '' ? $previous : 'pending',
        'hpv_screening_result' => 'pending',
        'cancelled_scheduled_messages' => $cancelledMessages,
        'cancelled_upcoming_appointments' => $cancelledAppointments,
        'message' => 'HPV result cleared. Record the correct result, book an appointment, then confirm to notify the patient.',
    ];
}

/**
 * Restore a prior HPV workflow snapshot (e.g. after mistaken clear).
 *
 * @param array<string, mixed> $snapshot
 * @return array<string, mixed>
 */
function restore_patient_hpv_result(int $patientId, array $snapshot): array
{
    if (!hpv_workflow_ready()) {
        return ['ok' => false, 'error' => hpv_workflow_unavailable_message()];
    }

    $result = strtolower(trim((string) ($snapshot['result'] ?? '')));
    if (!in_array($result, ['positive', 'negative', 'failed'], true)) {
        return ['ok' => false, 'error' => 'result must be positive, negative, or failed'];
    }

    $st = db()->prepare('SELECT id, full_name FROM patients WHERE id = ? LIMIT 1');
    $st->execute([$patientId]);
    $row = $st->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Patient not found'];
    }

    $recordedAt = trim((string) ($snapshot['hpv_result_recorded_at'] ?? ''));
    $confirmedAt = trim((string) ($snapshot['hpv_result_confirmed_at'] ?? ''));
    $counselingIndex = max(0, (int) ($snapshot['hpv_counseling_index'] ?? 0));

    db()->prepare(
        'UPDATE patients
         SET hpv_screening_result = ?,
             hpv_result_recorded_at = ?,
             hpv_result_confirmed_at = ?,
             hpv_counseling_index = ?
         WHERE id = ?'
    )->execute([
        $result,
        $recordedAt !== '' ? $recordedAt : null,
        $confirmedAt !== '' ? $confirmedAt : null,
        $counselingIndex,
        $patientId,
    ]);

    $restoredAppointments = 0;
    $appointmentIds = $snapshot['restore_appointment_ids'] ?? [];
    if (!is_array($appointmentIds)) {
        $appointmentIds = [];
    }
    if (isset($snapshot['restore_appointment_id']) && (int) $snapshot['restore_appointment_id'] > 0) {
        $appointmentIds[] = (int) $snapshot['restore_appointment_id'];
    }
    $appointmentIds = array_values(array_unique(array_filter(array_map('intval', $appointmentIds))));

    foreach ($appointmentIds as $apptId) {
        $apptSt = db()->prepare(
            "UPDATE appointments SET status = 'confirmed'
             WHERE id = ? AND patient_id = ? AND status = 'cancelled'"
        );
        $apptSt->execute([$apptId, $patientId]);
        $restoredAppointments += $apptSt->rowCount();
    }

    try {
        $label = match ($result) {
            'positive' => 'HPV positive',
            'negative' => 'HPV negative',
            default => 'HPV failed (insufficient sample)',
        };
        $chk = db()->prepare(
            'SELECT 1 FROM diagnosis_results WHERE patient_id = ? AND diagnosis_label = ? LIMIT 1'
        );
        $chk->execute([$patientId, $label]);
        if (!$chk->fetchColumn()) {
            $summary = $confirmedAt !== ''
                ? 'HPV screening result recorded (' . $result . '). Confirmed and patient notified.'
                : 'HPV screening result recorded (' . $result . '). Awaiting staff confirmation to notify patient.';
            db()->prepare(
                'INSERT INTO diagnosis_results (patient_id, diagnosis_label, severity, result_summary, recorded_by)
                 VALUES (?,?,?,?,?)'
            )->execute([$patientId, $label, 'unknown', $summary, 'restore_patient_hpv_result']);
        }
    } catch (Throwable $e) {
        error_log('restore diagnosis_results: ' . $e->getMessage());
    }

    return [
        'ok' => true,
        'patient_id' => $patientId,
        'full_name' => (string) $row['full_name'],
        'hpv_screening_result' => $result,
        'hpv_result_recorded_at' => $recordedAt !== '' ? $recordedAt : null,
        'hpv_result_confirmed_at' => $confirmedAt !== '' ? $confirmedAt : null,
        'restored_appointments' => $restoredAppointments,
        'message' => 'HPV workflow restored.',
    ];
}
