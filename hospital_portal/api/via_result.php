<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../patient_screening.php';
require_once __DIR__ . '/../data_wipe.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        api_json(['ok' => false, 'error' => 'POST required'], 405);
    }

    if (!patient_screening_ready()) {
        api_json(['ok' => false, 'error' => 'VIA recording is not available on this server.'], 503);
    }

    $body = api_body();
    $action = strtolower(trim((string) ($body['action'] ?? 'record')));

    if ($action === 'clear_by_date') {
        $password = trim((string) ($body['password'] ?? ''));
        if (!wipe_data_password_configured()) {
            api_json(['ok' => false, 'error' => 'Admin password is not configured on the server.'], 503);
        }
        if (!wipe_data_password_valid($password)) {
            api_json(['ok' => false, 'error' => 'Invalid password'], 401);
        }
        $date = trim((string) ($body['via_date'] ?? $body['date'] ?? ''));
        $out = clear_via_results_on_date($date);
        api_json($out, !empty($out['ok']) ? 200 : 422);
    }

    $patientId = (int) ($body['patient_id'] ?? 0);
    if ($patientId < 1) {
        api_json(['ok' => false, 'error' => 'patient_id is required'], 422);
    }

    if ($action === 'clear') {
        $password = trim((string) ($body['password'] ?? ''));
        if (!wipe_data_password_configured()) {
            api_json(['ok' => false, 'error' => 'Admin password is not configured on the server.'], 503);
        }
        if (!wipe_data_password_valid($password)) {
            api_json(['ok' => false, 'error' => 'Invalid password'], 401);
        }
        $out = clear_patient_via_result($patientId);
        api_json($out, !empty($out['ok']) ? 200 : 422);
    }

    $viaResult = (string) ($body['via_result'] ?? '');
    $viaDate = trim((string) ($body['via_date'] ?? ''));
    $hasCancer = !empty($body['has_cancer']);
    $treatmentDate = trim((string) ($body['treatment_date'] ?? ''));
    $treatmentDate = $treatmentDate === '' ? null : $treatmentDate;
    $notifyPatient = array_key_exists('notify_patient', $body)
        ? !empty($body['notify_patient'])
        : true;

    $out = record_patient_via_result(
        $patientId,
        $viaResult,
        $viaDate,
        $hasCancer,
        $treatmentDate,
        'hospital_console',
        $notifyPatient
    );
    api_json($out, !empty($out['ok']) ? 200 : 422);
} catch (Throwable $e) {
    error_log('via_result API: ' . $e->getMessage());
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
