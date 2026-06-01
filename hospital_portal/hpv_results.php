<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/afya_rafiki_content.php';
require_once __DIR__ . '/scheduled_messages.php';

function hpv_workflow_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        db()->query('SELECT hpv_screening_result FROM patients LIMIT 1');
        $ready = true;
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
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

function set_patient_hpv_result(int $patientId, string $result, string $recordedBy = 'staff'): array
{
    if (!hpv_workflow_ready()) {
        return ['ok' => false, 'error' => 'Run sql/2026_05_31_hpv_result_workflow.sql on the database first'];
    }
    $result = strtolower(trim($result));
    if (!in_array($result, ['positive', 'negative'], true)) {
        return ['ok' => false, 'error' => 'Result must be positive or negative'];
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

    $dx = db()->prepare(
        'INSERT INTO diagnosis_results (patient_id, diagnosis_label, severity, result_summary, recorded_by)
         VALUES (?,?,?,?,?)'
    );
    $label = $result === 'positive' ? 'HPV positive' : 'HPV negative';
    $dx->execute([
        $patientId,
        $label,
        'unknown',
        'HPV screening result recorded (' . $result . '). Awaiting staff confirmation to notify patient.',
        $recordedBy,
    ]);

    return ['ok' => true, 'hpv_screening_result' => $result];
}

function confirm_patient_hpv_result(int $patientId, string $confirmedBy = 'staff'): array
{
    if (!hpv_workflow_ready()) {
        return ['ok' => false, 'error' => 'Run sql/2026_05_31_hpv_result_workflow.sql on the database first'];
    }

    $row = get_patient_hpv_row($patientId);
    if (!$row) {
        return ['ok' => false, 'error' => 'Patient not found'];
    }

    $result = (string) ($row['hpv_screening_result'] ?? 'pending');
    if (!in_array($result, ['positive', 'negative'], true)) {
        return ['ok' => false, 'error' => 'Set HPV result to positive or negative before confirming'];
    }

    if (!empty($row['hpv_result_confirmed_at'])) {
        return ['ok' => false, 'error' => 'Results were already confirmed and sent to the patient'];
    }

    if (!patient_has_confirmed_consent($patientId)) {
        return ['ok' => false, 'error' => 'Patient has not accepted messages (consent) yet'];
    }

    $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    $name = (string) $row['full_name'];

    db()->prepare(
        'UPDATE patients SET hpv_result_confirmed_at = NOW(3), hpv_counseling_index = 0 WHERE id = ?'
    )->execute([$patientId]);

    send_patient_message(
        $patientId,
        'system',
        build_hpv_result_notification($name, $result, $lang)
    );

    $first = get_next_counseling_message($patientId, $lang);
    if ($first !== null) {
        send_patient_message($patientId, 'education_menu', $first);
        advance_hpv_counseling_index($patientId);
    }

    $dx = db()->prepare(
        'INSERT INTO diagnosis_results (patient_id, diagnosis_label, severity, result_summary, recorded_by)
         VALUES (?,?,?,?,?)'
    );
    $dx->execute([
        $patientId,
        $result === 'positive' ? 'HPV positive (confirmed)' : 'HPV negative (confirmed)',
        'unknown',
        'Result confirmed and patient notified via Afya Rafiki.',
        $confirmedBy,
    ]);

    return [
        'ok' => true,
        'hpv_screening_result' => $result,
        'counseling_started' => $first !== null,
    ];
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
