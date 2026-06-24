<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../patient_screening.php';

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
    $patientId = (int) ($body['patient_id'] ?? 0);
    if ($patientId < 1) {
        api_json(['ok' => false, 'error' => 'patient_id is required'], 422);
    }

    $viaResult = (string) ($body['via_result'] ?? '');
    $viaDate = trim((string) ($body['via_date'] ?? ''));
    $hasCancer = !empty($body['has_cancer']);
    $treatmentDate = trim((string) ($body['treatment_date'] ?? ''));
    $treatmentDate = $treatmentDate === '' ? null : $treatmentDate;
    $notifyPatient = array_key_exists('notify_patient', $body)
        ? !empty($body['notify_patient'])
        : false;

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
