<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/hpv_results.php';
require_once __DIR__ . '/scheduled_messages.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/afya_rafiki_content.php';
require_once __DIR__ . '/patient_referral.php';
require_once __DIR__ . '/appointment_utils.php';

const NYERI_REFERRAL_HOSPITAL = 'Nyeri County Referral Hospital';

/** @return bool */
function ensure_patient_screening_schema(): bool
{
    try {
        $pdo = db();
        $cols = [
            'age' => 'SMALLINT UNSIGNED NULL',
            'hiv_status' => "ENUM('unknown','not_known','negative','positive') NOT NULL DEFAULT 'unknown'",
            'hpv_done_before' => "ENUM('unknown','no','yes') NOT NULL DEFAULT 'unknown'",
            'hpv_prior_result' => "ENUM('unknown','negative','positive') NOT NULL DEFAULT 'unknown'",
            'place_of_residence' => 'VARCHAR(255) NULL',
            'via_result' => "ENUM('unknown','not_done','negative','positive') NOT NULL DEFAULT 'unknown'",
            'via_date' => 'DATE NULL',
            'has_cancer' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'treatment_date' => 'DATE NULL',
            'next_checkup_at' => 'DATE NULL',
            'nyeri_referral_at' => 'DATETIME(3) NULL',
            'nyeri_referral_appointment_date' => 'DATE NULL',
        ];
        foreach ($cols as $column => $definition) {
            if (!db_table_has_column('patients', $column)) {
                $pdo->exec("ALTER TABLE patients ADD COLUMN {$column} {$definition}");
            }
        }
        if (!db_table_has_column('patients', 'via_result_notified_at')) {
            $pdo->exec('ALTER TABLE patients ADD COLUMN via_result_notified_at DATETIME(3) NULL');
            $pdo->exec(
                "UPDATE patients SET via_result_notified_at = CONCAT(via_date, ' 12:00:00')
                 WHERE via_result IN ('positive','negative')
                   AND via_date IS NOT NULL
                   AND via_result_notified_at IS NULL"
            );
        }
        if (db_table_has_column('patients', 'hiv_status')) {
            $pdo->exec(
                "ALTER TABLE patients MODIFY COLUMN hiv_status
                 ENUM('unknown','not_known','negative','positive') NOT NULL DEFAULT 'unknown'"
            );
        }
        return true;
    } catch (Throwable $e) {
        error_log('ensure_patient_screening_schema: ' . $e->getMessage());
        return false;
    }
}

function patient_screening_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = db_table_has_column('patients', 'hiv_status')
        && db_table_has_column('patients', 'via_result');
    return $ready;
}

/** @return string[] */
function patient_screening_select_columns(): array
{
    if (!patient_screening_ready()) {
        return [];
    }
    return [
        'hiv_status',
        'hpv_done_before',
        'hpv_prior_result',
        'place_of_residence',
        'via_result',
        'via_date',
        'via_result_notified_at',
        'has_cancer',
        'treatment_date',
        'next_checkup_at',
    ];
}

/**
 * @param array<string, mixed> $body
 * @return array<string, mixed>
 */
function parse_screening_from_body(array $body): array
{
    $hiv = strtolower(trim((string) ($body['hiv_status'] ?? 'unknown')));
    if (!in_array($hiv, ['negative', 'positive', 'not_known'], true)) {
        $hiv = 'unknown';
    }

    $hpvDone = strtolower(trim((string) ($body['hpv_done_before'] ?? 'unknown')));
    if (!in_array($hpvDone, ['yes', 'no'], true)) {
        $hpvDone = 'unknown';
    }

    $hpvPrior = strtolower(trim((string) ($body['hpv_prior_result'] ?? 'unknown')));
    if (!in_array($hpvPrior, ['negative', 'positive'], true)) {
        $hpvPrior = 'unknown';
    }
    if ($hpvDone !== 'yes') {
        $hpvPrior = 'unknown';
    }

    $via = strtolower(trim((string) ($body['via_result'] ?? 'not_done')));
    if (!in_array($via, ['not_done', 'negative', 'positive', 'unknown'], true)) {
        $via = 'not_done';
    }

    $viaDate = trim((string) ($body['via_date'] ?? ''));
    $viaDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $viaDate) ? $viaDate : null;

    $treatmentDate = trim((string) ($body['treatment_date'] ?? ''));
    $treatmentDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $treatmentDate) ? $treatmentDate : null;

    $hasCancer = !empty($body['has_cancer']) && ($via === 'positive');

    return [
        'hiv_status' => $hiv,
        'hpv_done_before' => $hpvDone,
        'hpv_prior_result' => $hpvPrior,
        'place_of_residence' => trim((string) ($body['place_of_residence'] ?? '')),
        'via_result' => $via,
        'via_date' => $viaDate,
        'has_cancer' => $hasCancer ? 1 : 0,
        'treatment_date' => $treatmentDate,
    ];
}

/**
 * @param array<string, mixed> $screening
 * @return string|null
 */
function validate_screening_registration(array $screening): ?string
{
    if (!in_array($screening['hiv_status'], ['negative', 'positive', 'not_known'], true)) {
        return 'HIV status is required (positive, negative, or not known).';
    }
    if ($screening['hpv_done_before'] === 'unknown') {
        return 'Please indicate if HPV screening was ever done before.';
    }
    if ($screening['hpv_done_before'] === 'yes' && $screening['hpv_prior_result'] === 'unknown') {
        return 'Please record the prior HPV result (positive or negative).';
    }
    if ($screening['place_of_residence'] === '') {
        return 'Place of residence is required.';
    }
    return null;
}

/**
 * @param array<string, mixed> $data
 */
function validate_via_record(array $data): ?string
{
    $via = strtolower(trim((string) ($data['via_result'] ?? '')));
    if (!in_array($via, ['negative', 'positive'], true)) {
        return 'VIA result must be positive or negative.';
    }
    $viaDate = trim((string) ($data['via_date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $viaDate)) {
        return 'Date of VIA test is required.';
    }
    return null;
}

/**
 * Record VIA after the patient has been tested (not at registration).
 *
 * @return array{ok: bool, error?: string, referral_sent?: bool, next_checkup_at?: ?string}
 */
function record_patient_via_result(
    int $patientId,
    string $viaResult,
    string $viaDate,
    bool $hasCancer,
    ?string $treatmentDate,
    string $recordedBy = 'staff'
): array {
    if (!patient_screening_ready()) {
        return ['ok' => false, 'error' => 'VIA recording is not available on this server.'];
    }
    if (!patient_has_confirmed_appointment($patientId)) {
        return ['ok' => false, 'error' => 'Confirm the patient appointment before recording VIA.'];
    }

    $err = validate_via_record([
        'via_result' => $viaResult,
        'via_date' => $viaDate,
    ]);
    if ($err !== null) {
        return ['ok' => false, 'error' => $err];
    }

    $viaResult = strtolower(trim($viaResult));
    $hpvLabCol = db_table_has_column('patients', 'hpv_screening_result') ? 'hpv_screening_result,' : '';
    $st = db()->prepare(
        "SELECT id, full_name, preferred_language, hiv_status, hpv_done_before, hpv_prior_result,
                {$hpvLabCol} place_of_residence, via_result
         FROM patients WHERE id = ? LIMIT 1"
    );
    $st->execute([$patientId]);
    $row = $st->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Patient not found'];
    }

    $treatmentVal = ($treatmentDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $treatmentDate))
        ? $treatmentDate
        : null;
    $hasCancerVal = $viaResult === 'positive' && $hasCancer ? 1 : 0;

    $screening = [
        'hiv_status' => (string) ($row['hiv_status'] ?? 'not_known'),
        'hpv_done_before' => (string) ($row['hpv_done_before'] ?? 'unknown'),
        'hpv_prior_result' => (string) ($row['hpv_prior_result'] ?? 'unknown'),
        'hpv_screening_result' => (string) ($row['hpv_screening_result'] ?? 'unknown'),
        'place_of_residence' => (string) ($row['place_of_residence'] ?? ''),
        'via_result' => $viaResult,
        'via_date' => $viaDate,
        'has_cancer' => $hasCancerVal,
        'treatment_date' => $treatmentVal,
    ];
    $followups = compute_screening_followups($screening);

    $up = db()->prepare(
        'UPDATE patients
         SET via_result = ?, via_date = ?, has_cancer = ?, treatment_date = ?, next_checkup_at = ?
         WHERE id = ?'
    );
    $up->execute([
        $viaResult,
        $viaDate,
        $hasCancerVal,
        $treatmentVal,
        $followups['next_checkup_at'],
        $patientId,
    ]);

    auto_complete_attendance_on_via_record($patientId);

    require_once __DIR__ . '/encouragement_drip.php';
    complete_encouragement_drip_after_via($patientId);

    return [
        'ok' => true,
        'via_result' => $viaResult,
        'via_date' => $viaDate,
        'next_checkup_at' => $followups['next_checkup_at'],
        'referral_sent' => false,
        'via_message_sent' => false,
        'book_followup_next' => true,
        'recorded_by' => $recordedBy,
    ];
}

function patient_via_result_recorded_row(int $patientId): ?array
{
    if (!patient_screening_ready()) {
        return null;
    }
    $st = db()->prepare(
        'SELECT id, full_name, preferred_language, hiv_status, hpv_done_before, hpv_prior_result,
                via_result, via_date, via_result_notified_at, has_cancer, treatment_date, place_of_residence
         FROM patients WHERE id = ? LIMIT 1'
    );
    $st->execute([$patientId]);
    $row = $st->fetch();
    return $row ?: null;
}

function patient_via_awaiting_followup_notify(int $patientId): bool
{
    $row = patient_via_result_recorded_row($patientId);
    if (!$row) {
        return false;
    }
    $via = strtolower((string) ($row['via_result'] ?? ''));
    if (!in_array($via, ['positive', 'negative'], true)) {
        return false;
    }

    return empty($row['via_result_notified_at']);
}

/**
 * Send VIA result SMS (and stop pre-VIA drip) after follow-up appointment is booked.
 *
 * @return array{notified: bool, referral_sent?: bool, error?: string}
 */
function notify_patient_via_result(int $patientId, string $notifiedBy = 'staff'): array
{
    if (!patient_screening_ready()) {
        return ['notified' => false, 'error' => 'VIA workflow not available'];
    }

    $row = patient_via_result_recorded_row($patientId);
    if (!$row) {
        return ['notified' => false, 'error' => 'Patient not found'];
    }

    $via = strtolower((string) ($row['via_result'] ?? ''));
    if (!in_array($via, ['positive', 'negative'], true)) {
        return ['notified' => false, 'error' => 'Record a VIA result before notifying'];
    }

    if (!empty($row['via_result_notified_at'])) {
        return ['notified' => false, 'error' => 'VIA result was already sent to the patient'];
    }

    if (afya_next_appointment_display($patientId) === '__________') {
        return [
            'notified' => false,
            'error' => 'Book a follow-up appointment first — the patient message needs the appointment date.',
        ];
    }

    $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    $name = (string) $row['full_name'];
    $screening = [
        'hiv_status' => (string) ($row['hiv_status'] ?? 'not_known'),
        'hpv_done_before' => (string) ($row['hpv_done_before'] ?? 'unknown'),
        'hpv_prior_result' => (string) ($row['hpv_prior_result'] ?? 'unknown'),
        'place_of_residence' => (string) ($row['place_of_residence'] ?? ''),
        'via_result' => $via,
        'via_date' => (string) ($row['via_date'] ?? ''),
        'has_cancer' => (int) ($row['has_cancer'] ?? 0),
        'treatment_date' => $row['treatment_date'] ?? null,
    ];

    $optSt = db()->prepare(
        'SELECT 1 FROM contact_channels WHERE patient_id = ? AND opted_in = 1 LIMIT 1'
    );
    $optSt->execute([$patientId]);
    $messageSent = false;
    $referralSent = false;
    if ($optSt->fetchColumn()) {
        process_via_recorded_messages($patientId, $name, $lang, $screening, true);
        $messageSent = true;
        $referralSent = $via === 'positive' && (int) ($row['has_cancer'] ?? 0) === 1;
    }

    db()->prepare('UPDATE patients SET via_result_notified_at = NOW(3) WHERE id = ?')->execute([$patientId]);

    return [
        'notified' => true,
        'via_message_sent' => $messageSent,
        'referral_sent' => $referralSent,
        'notified_by' => $notifiedBy,
    ];
}

/**
 * After follow-up appointment booking, auto-send VIA result then caller sends appointment SMS.
 *
 * @return array{notified: bool, referral_sent?: bool}
 */
function try_auto_notify_via_after_appointment_booked(int $patientId, string $notifiedBy = 'appointment_booked'): array
{
    if (!patient_via_awaiting_followup_notify($patientId)) {
        return ['notified' => false];
    }

    $out = notify_patient_via_result($patientId, $notifiedBy);
    if (empty($out['notified'])) {
        return ['notified' => false, 'error' => (string) ($out['error'] ?? 'VIA notify failed')];
    }

    return [
        'notified' => true,
        'referral_sent' => !empty($out['referral_sent']),
    ];
}

/**
 * Follow-up rules (official study §12b after VIA negative):
 * Repeat HPV screening in 1 year from VIA date (all patients).
 *
 * @param array<string, mixed> $screening
 * @return array{next_checkup_at: ?string, schedules: list<array{years: float, reason: string, send_at: string}>}
 */
function compute_screening_followups(array $screening): array
{
    $anchor = $screening['via_date'] ?? date('Y-m-d');
    $schedules = [];

    if ($screening['via_result'] === 'negative') {
        $schedules[] = [
            'years' => 1.0,
            'reason' => 'via_neg_1y',
            'send_at' => date('Y-m-d H:i:s', strtotime($anchor . ' +1 year')),
        ];
    }

    $next = null;
    foreach ($schedules as $s) {
        $d = substr($s['send_at'], 0, 10);
        if ($next === null || $d < $next) {
            $next = $d;
        }
    }

    return ['next_checkup_at' => $next, 'schedules' => $schedules];
}

function build_referral_message(string $patientName, string $lang, string $appointmentDate = '__________'): string
{
    return build_referral_initial_message($patientName, $appointmentDate, $lang);
}

function build_checkup_reminder_message(
    string $patientName,
    string $checkupDate,
    string $lang,
    string $reasonKey
): string {
    $dateStr = $checkupDate;
    $hospital = defined('HOSPITAL_NAME') ? HOSPITAL_NAME : 'Nyeri Town Health Center';

    if ($lang === 'sw') {
        if ($reasonKey === 'via_neg_1y') {
            return "Habari {$patientName}, ni muhimu urudi {$hospital} kwa kipimo kingine cha HPV baada ya mwaka 1. Tarehe ya kukumbushwa: {$dateStr}.";
        }
        return "Habari {$patientName}, tafadhali rudi {$hospital} kwa uchunguzi tarehe {$dateStr}.";
    }

    if ($reasonKey === 'via_neg_1y') {
        return "Hello {$patientName}, please return to {$hospital} for a repeat HPV test after 1 year. Reminder date: {$dateStr}.";
    }
    return "Hello {$patientName}, please return to {$hospital} for a check-up on {$dateStr}.";
}

/**
 * Messages after nurse records VIA on the patient page (post-test).
 *
 * @param array<string, mixed> $screening
 */
function process_via_recorded_messages(
    int $patientId,
    string $patientName,
    string $lang,
    array $screening,
    bool $optedIn
): void {
    if (!$optedIn || !patient_screening_ready()) {
        return;
    }

    ensure_patient_screening_schema();

    if (!in_array($screening['via_result'], ['negative', 'positive'], true)) {
        return;
    }

    if ($screening['via_result'] === 'negative') {
        $apptDate = afya_next_appointment_display($patientId);
        if ($apptDate === '__________') {
            $apptDate = afya_format_appointment_date((string) ($screening['via_date'] ?? date('Y-m-d')) . ' 09:00:00');
        }
        send_patient_message(
            $patientId,
            'via_negative',
            build_post_visit_via_negative($patientName, $apptDate, $lang)
        );
    } elseif (!empty($screening['has_cancer'])) {
        $refDate = (string) ($screening['via_date'] ?? date('Y-m-d'));
        send_patient_message(
            $patientId,
            'referral',
            build_referral_message($patientName, $lang, afya_format_appointment_date($refDate . ' 09:00:00'))
        );
        mark_nyeri_referral_recorded($patientId, $refDate);
        schedule_referral_followup_messages($patientId, $patientName, $lang, $refDate);
    } else {
        [$viaType, $viaBody] = resolve_via_positive_patient_message(
            $patientId,
            $patientName,
            $lang,
            isset($screening['treatment_date']) ? (string) $screening['treatment_date'] : null
        );
        send_patient_message($patientId, $viaType, $viaBody);
    }

    $followups = compute_screening_followups($screening);
    foreach ($followups['schedules'] as $item) {
        $checkupDate = substr($item['send_at'], 0, 10);
        $body = build_checkup_reminder_message(
            $patientName,
            $checkupDate,
            $lang,
            (string) $item['reason']
        );
        schedule_patient_message_at(
            $patientId,
            'checkup_reminder',
            $body,
            (string) $item['send_at']
        );
    }
}

/** @deprecated VIA is no longer recorded at registration; use process_via_recorded_messages(). */
function process_registration_screening_messages(
    int $patientId,
    string $patientName,
    string $lang,
    array $screening,
    bool $optedIn
): void {
    process_via_recorded_messages($patientId, $patientName, $lang, $screening, $optedIn);
}
