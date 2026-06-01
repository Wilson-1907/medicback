<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../hpv_results.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        api_json(['ok' => false, 'error' => 'POST required'], 405);
    }

    $body = api_body();
    $action = (string) ($body['action'] ?? '');
    $patientId = (int) ($body['patient_id'] ?? 0);

    if ($patientId < 1) {
        api_json(['ok' => false, 'error' => 'patient_id is required'], 422);
    }

    if (!ensure_hpv_workflow_schema()) {
        api_json(['ok' => false, 'error' => hpv_workflow_unavailable_message()], 503);
    }

    if ($action === 'set_result') {
        $result = (string) ($body['result'] ?? '');
        $out = set_patient_hpv_result($patientId, $result, 'hospital_console');
        api_json($out, !empty($out['ok']) ? 200 : 422);
    }

    if ($action === 'confirm_result') {
        $out = confirm_patient_hpv_result($patientId, 'hospital_console');
        api_json($out, !empty($out['ok']) ? 200 : 422);
    }

    api_json(['ok' => false, 'error' => 'Unknown action. Use set_result or confirm_result'], 422);
} catch (Throwable $e) {
    error_log('hpv_result API: ' . $e->getMessage());
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}