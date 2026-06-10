<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../patient_referral.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $patientId = (int) ($_GET['patient_id'] ?? 0);
        if ($patientId < 1) {
            api_json(['ok' => false, 'error' => 'patient_id is required'], 422);
        }
        $st = db()->prepare(
            'SELECT id, hpv_screening_result, hpv_result_confirmed_at, hpv_prior_result,
                    via_result, nyeri_referral_at, nyeri_referral_appointment_date
             FROM patients WHERE id = ? LIMIT 1'
        );
        $st->execute([$patientId]);
        $row = $st->fetch();
        if (!$row) {
            api_json(['ok' => false, 'error' => 'Patient not found'], 404);
        }
        api_json(['ok' => true, 'status' => patient_nyeri_referral_status($row)]);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        api_json(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = api_body();
    $patientId = (int) ($body['patient_id'] ?? 0);
    $apptDate = trim((string) ($body['referral_appointment_date'] ?? ''));

    if ($patientId < 1) {
        api_json(['ok' => false, 'error' => 'patient_id is required'], 422);
    }

    $manualOverride = !empty($body['manual_override']);
    $out = refer_patient_to_nyeri_hospital($patientId, $apptDate, 'hospital_console', $manualOverride);
    api_json($out, !empty($out['ok']) ? 200 : 422);
} catch (Throwable $e) {
    error_log('referral API: ' . $e->getMessage());
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
