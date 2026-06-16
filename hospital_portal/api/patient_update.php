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

    if (!ensure_hpv_workflow_schema()) {
        api_json(['ok' => false, 'error' => hpv_workflow_unavailable_message()], 503);
    }

    $body = api_body();
    $patientId = (int) ($body['patient_id'] ?? 0);
    if ($patientId < 1) {
        api_json(['ok' => false, 'error' => 'patient_id is required'], 422);
    }

    $intake = [
        'preferred_language' => (string) ($body['preferred_language'] ?? ''),
        'contact_channel' => (string) ($body['contact_channel'] ?? ''),
        'hpv_done_before' => (string) ($body['hpv_done_before'] ?? ''),
        'hpv_prior_result' => (string) ($body['hpv_prior_result'] ?? 'unknown'),
    ];

    $err = apply_hpv_positive_intake($patientId, $intake);
    if ($err !== null) {
        api_json(['ok' => false, 'error' => $err], 422);
    }

    api_json(['ok' => true]);
} catch (Throwable $e) {
    error_log('patient_update API: ' . $e->getMessage());
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}

