<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../data_wipe.php';
require_once __DIR__ . '/../hpv_results.php';
require_once __DIR__ . '/../patient_client_id.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        api_json(['ok' => false, 'error' => 'POST required'], 405);
    }

    $body = api_body();
    $password = trim((string) ($body['password'] ?? ''));
    if (!wipe_data_password_configured()) {
        api_json([
            'ok' => false,
            'error' => 'Admin password is not configured on the server.',
            'code' => 'auth_not_configured',
        ], 503);
    }
    if (!wipe_data_password_valid($password)) {
        api_json(['ok' => false, 'error' => 'Invalid password', 'code' => 'invalid_password'], 401);
    }

    if (!ensure_hpv_workflow_schema()) {
        api_json(['ok' => false, 'error' => hpv_workflow_unavailable_message()], 503);
    }

    $patientId = (int) ($body['patient_id'] ?? 0);
    $clientRef = trim((string) ($body['client_id'] ?? ''));
    if ($patientId < 1 && $clientRef !== '') {
        $resolved = resolve_patient_id_by_client_id($clientRef);
        if ($resolved === null || $resolved < 1) {
            api_json([
                'ok' => false,
                'error' => 'Patient not found for client number: ' . normalize_client_id_full($clientRef),
            ], 404);
        }
        $patientId = $resolved;
    }
    if ($patientId < 1) {
        api_json(['ok' => false, 'error' => 'patient_id or client_id is required'], 422);
    }

    $cancelAppointments = !array_key_exists('cancel_upcoming_appointments', $body)
        || !empty($body['cancel_upcoming_appointments']);

    $out = clear_patient_hpv_result($patientId, $cancelAppointments);
    api_json($out, !empty($out['ok']) ? 200 : 422);
} catch (Throwable $e) {
    error_log('clear_patient_hpv API: ' . $e->getMessage());
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
