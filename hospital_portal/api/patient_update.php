<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
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

    if (!ensure_hpv_workflow_schema()) {
        api_json(['ok' => false, 'error' => hpv_workflow_unavailable_message()], 503);
    }

    $body = api_body();
    $patientId = (int) ($body['patient_id'] ?? 0);
    if ($patientId < 1) {
        api_json(['ok' => false, 'error' => 'patient_id is required'], 422);
    }

    $updated = false;
    $phoneOut = null;
    $channelOut = null;

    $phoneRaw = trim((string) ($body['phone'] ?? ''));
    if ($phoneRaw === '' && isset($body['phone_local'])) {
        $phoneRaw = api_phone((string) $body['phone_local']);
    } elseif ($phoneRaw !== '') {
        $phoneRaw = api_phone($phoneRaw);
    }

    if ($phoneRaw !== '') {
        $channelForPhone = isset($body['contact_channel']) ? (string) $body['contact_channel'] : null;
        $phoneResult = update_patient_primary_contact($patientId, $phoneRaw, $channelForPhone);
        if (empty($phoneResult['ok'])) {
            api_json(['ok' => false, 'error' => (string) ($phoneResult['error'] ?? 'Phone update failed')], 422);
        }
        $updated = true;
        $phoneOut = (string) ($phoneResult['phone'] ?? $phoneRaw);
        $channelOut = (string) ($phoneResult['channel'] ?? 'sms');
    }

    $lang = trim((string) ($body['preferred_language'] ?? ''));
    $channel = trim((string) ($body['contact_channel'] ?? ''));
    $hpvDone = trim((string) ($body['hpv_done_before'] ?? ''));
    $hasIntake = array_key_exists('preferred_language', $body)
        || array_key_exists('hpv_done_before', $body)
        || (array_key_exists('contact_channel', $body) && $phoneRaw === '');
    if ($hasIntake && ($lang !== '' || $channel !== '' || $hpvDone !== '')) {
        $intake = [
            'preferred_language' => $lang !== '' ? $lang : 'en',
            'contact_channel' => $channel !== '' ? $channel : 'sms',
            'hpv_done_before' => $hpvDone !== '' ? $hpvDone : 'unknown',
            'hpv_prior_result' => (string) ($body['hpv_prior_result'] ?? 'unknown'),
        ];
        $err = apply_hpv_positive_intake($patientId, $intake);
        if ($err !== null) {
            api_json(['ok' => false, 'error' => $err], 422);
        }
        $updated = true;
        if ($channelOut === null && $channel !== '') {
            $channelOut = strtolower($channel) === 'whatsapp' ? 'whatsapp' : 'sms';
        }
    }

    if (!$updated) {
        api_json(['ok' => false, 'error' => 'Nothing to update'], 422);
    }

    api_json([
        'ok' => true,
        'phone' => $phoneOut,
        'channel' => $channelOut,
    ]);
} catch (Throwable $e) {
    error_log('patient_update API: ' . $e->getMessage());
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
