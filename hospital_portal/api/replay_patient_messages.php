<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../data_wipe.php';
require_once __DIR__ . '/../patient_message_replay.php';

@set_time_limit(300);

try {
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

    $patientId = (int) ($body['patient_id'] ?? 0);
    $clientRef = trim((string) ($body['client_id'] ?? ''));
    if ($patientId < 1 && $clientRef === '') {
        api_json(['ok' => false, 'error' => 'patient_id or client_id is required'], 422);
    }

    $result = $patientId > 0
        ? replay_patient_messages($patientId)
        : replay_patient_messages_by_client_id($clientRef);

    api_json(['ok' => !empty($result['ok']), 'replay' => $result], !empty($result['ok']) ? 200 : 502);
} catch (Throwable $e) {
    error_log('replay_patient_messages API: ' . $e->getMessage());
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
