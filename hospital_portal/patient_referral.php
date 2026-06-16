<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/hpv_results.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/scheduled_messages.php';
require_once __DIR__ . '/afya_rafiki_content.php';

/** @return bool */
function ensure_nyeri_referral_schema(): bool
{
    try {
        $pdo = db();
        if (!db_table_has_column('patients', 'nyeri_referral_at')) {
            $pdo->exec(
                'ALTER TABLE patients ADD COLUMN nyeri_referral_at DATETIME(3) NULL AFTER next_checkup_at'
            );
        }
        if (!db_table_has_column('patients', 'nyeri_referral_appointment_date')) {
            $pdo->exec(
                'ALTER TABLE patients ADD COLUMN nyeri_referral_appointment_date DATE NULL AFTER nyeri_referral_at'
            );
        }

        return true;
    } catch (Throwable $e) {
        error_log('ensure_nyeri_referral_schema: ' . $e->getMessage());

        return false;
    }
}

/** HPV pathway complete — lab result confirmed and patient notified. */
function patient_hpv_test_complete(array $patient): bool
{
    $result = strtolower((string) ($patient['hpv_screening_result'] ?? ''));
    if ($result === 'failed') {
        return false;
    }
    if (!empty($patient['hpv_result_confirmed_at'])) {
        return in_array($result, ['positive', 'negative'], true);
    }
    if (!hpv_workflow_ready()) {
        $prior = strtolower((string) ($patient['hpv_prior_result'] ?? ''));
        if (in_array($prior, ['positive', 'negative'], true)) {
            return true;
        }
        $result = strtolower((string) ($patient['hpv_screening_result'] ?? ''));

        return in_array($result, ['positive', 'negative'], true);
    }

    return false;
}

/** VIA recorded after clinic visit. */
function patient_via_test_complete(array $patient): bool
{
    return in_array(strtolower((string) ($patient['via_result'] ?? '')), ['negative', 'positive'], true);
}

/** Both HPV and VIA have been completed once. */
function patient_all_screening_tests_complete(array $patient): bool
{
    return patient_hpv_test_complete($patient) && patient_via_test_complete($patient);
}

/**
 * @return array{
 *   hpv_complete: bool,
 *   via_complete: bool,
 *   all_complete: bool,
 *   already_referred: bool,
 *   referral_at: ?string,
 *   referral_appointment_date: ?string,
 *   hospital: string
 * }
 */
function patient_nyeri_referral_status(array $patient): array
{
    ensure_nyeri_referral_schema();

    return [
        'hpv_complete' => patient_hpv_test_complete($patient),
        'via_complete' => patient_via_test_complete($patient),
        'all_complete' => patient_all_screening_tests_complete($patient),
        'already_referred' => !empty($patient['nyeri_referral_at']),
        'referral_at' => isset($patient['nyeri_referral_at']) ? (string) $patient['nyeri_referral_at'] : null,
        'referral_appointment_date' => isset($patient['nyeri_referral_appointment_date'])
            ? (string) $patient['nyeri_referral_appointment_date']
            : null,
        'hospital' => afya_referral_hospital(),
    ];
}

/**
 * Schedule reassurance (+2 min) and specialist reminder (7 days before appt).
 */
function schedule_referral_followup_messages(
    int $patientId,
    string $patientName,
    string $lang,
    string $referralAppointmentDateYmd
): void {
    $displayDate = afya_format_appointment_date($referralAppointmentDateYmd . ' 09:00:00');
    $reassurance = build_referral_reassurance_message($patientName, $lang);
    if ($reassurance !== '') {
        schedule_patient_message($patientId, 'referral_reassurance', $reassurance, '+2 minutes');
    }
    $reminderTs = strtotime($referralAppointmentDateYmd . ' -7 days 09:00:00');
    if ($reminderTs !== false && $reminderTs > time()) {
        schedule_patient_message_at(
            $patientId,
            'referral_appt_reminder',
            build_referral_appointment_reminder($displayDate, $lang),
            date('Y-m-d H:i:s', $reminderTs)
        );
    }
}

/**
 * Refer patient to Nyeri County Referral Hospital after all screening tests are done.
 *
 * @return array{ok: bool, error?: string, referral_sent?: bool, referral_at?: string}
 */
function refer_patient_to_nyeri_hospital(
    int $patientId,
    string $referralAppointmentDate,
    string $recordedBy = 'staff',
    bool $manualOverride = false
): array {
    ensure_nyeri_referral_schema();

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $referralAppointmentDate)) {
        return ['ok' => false, 'error' => 'Referral appointment date is required (YYYY-MM-DD).'];
    }

    $st = db()->prepare(
        'SELECT id, full_name, preferred_language, hpv_screening_result, hpv_result_confirmed_at,
                hpv_prior_result, via_result, nyeri_referral_at
         FROM patients WHERE id = ? LIMIT 1'
    );
    $st->execute([$patientId]);
    $row = $st->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Patient not found'];
    }

    if (!$manualOverride && !patient_all_screening_tests_complete($row)) {
        return [
            'ok' => false,
            'error' => 'Complete and confirm HPV, then record VIA, before sending a Nyeri referral.',
        ];
    }

    if (!empty($row['nyeri_referral_at'])) {
        return [
            'ok' => false,
            'error' => 'This patient was already referred to ' . afya_referral_hospital() . '.',
        ];
    }

    $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    $name = (string) $row['full_name'];
    $displayDate = afya_format_appointment_date($referralAppointmentDate . ' 09:00:00');

    $optSt = db()->prepare(
        'SELECT 1 FROM contact_channels WHERE patient_id = ? AND opted_in = 1 LIMIT 1'
    );
    $optSt->execute([$patientId]);
    $referralSent = false;
    if ($optSt->fetchColumn()) {
        send_patient_message(
            $patientId,
            'referral',
            build_referral_initial_message($name, $displayDate, $lang)
        );
        schedule_referral_followup_messages($patientId, $name, $lang, $referralAppointmentDate);
        $referralSent = true;
    }

    $up = db()->prepare(
        'UPDATE patients
         SET nyeri_referral_at = NOW(3), nyeri_referral_appointment_date = ?
         WHERE id = ?'
    );
    $up->execute([$referralAppointmentDate, $patientId]);

    return [
        'ok' => true,
        'referral_sent' => $referralSent,
        'referral_at' => date('Y-m-d H:i:s'),
        'referral_appointment_date' => $referralAppointmentDate,
        'hospital' => afya_referral_hospital(),
        'recorded_by' => $recordedBy,
    ];
}

/** Mark referral timestamp when auto-sent from VIA + cancer pathway. */
function mark_nyeri_referral_recorded(int $patientId, ?string $appointmentDate = null): void
{
    ensure_nyeri_referral_schema();
    $dateVal = ($appointmentDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentDate))
        ? $appointmentDate
        : null;
    $st = db()->prepare(
        'UPDATE patients
         SET nyeri_referral_at = NOW(3),
             nyeri_referral_appointment_date = COALESCE(?, nyeri_referral_appointment_date)
         WHERE id = ? AND nyeri_referral_at IS NULL'
    );
    $st->execute([$dateVal, $patientId]);
}
