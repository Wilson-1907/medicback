<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../hpv_results.php';
require_once __DIR__ . '/../patient_screening.php';
require_once __DIR__ . '/../patient_client_id.php';
require_once __DIR__ . '/../afya_rafiki_content.php';

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    ensure_patient_screening_schema();
    ensure_hpv_workflow_schema();
    ensure_client_id_unique_index();
    ensure_outbound_message_types();

    if ($method === 'GET') {
        $clientIdParam = trim((string) ($_GET['client_id'] ?? ''));
        if ($clientIdParam !== '') {
            $resolvedId = resolve_patient_id_by_client_id($clientIdParam);
            if ($resolvedId === null) {
                api_json(['ok' => false, 'error' => 'Patient not found for client number: ' . normalize_client_id_full($clientIdParam)], 404);
            }
            $_GET['id'] = (string) $resolvedId;
        }

        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $hpvCols = hpv_workflow_ready()
                ? 'hpv_screening_result, hpv_result_recorded_at, hpv_result_confirmed_at, hpv_counseling_index'
                : 'NULL AS hpv_screening_result, NULL AS hpv_result_recorded_at, NULL AS hpv_result_confirmed_at, 0 AS hpv_counseling_index';
            $screenCols = patient_screening_ready()
                ? implode(', ', patient_screening_select_columns())
                : "NULL AS hiv_status, NULL AS hpv_done_before, NULL AS hpv_prior_result, NULL AS place_of_residence,
                   NULL AS via_result, NULL AS via_date, 0 AS has_cancer, NULL AS treatment_date, NULL AS next_checkup_at";
            $st = $pdo->prepare(
                "SELECT id, full_name, date_of_birth, preferred_language, external_mrn, notes, status, registration_at,
                        {$hpvCols}, {$screenCols}
                 FROM patients WHERE id = ? LIMIT 1"
            );
            $st->execute([$id]);
            $patient = $st->fetch();
            if (!$patient) {
                api_json(['ok' => false, 'error' => 'Patient not found'], 404);
            }

            $contacts = $pdo->prepare(
                'SELECT channel, address, is_primary, opted_in
                 FROM contact_channels WHERE patient_id = ? ORDER BY is_primary DESC, id ASC'
            );
            $contacts->execute([$id]);
            $patient['contacts'] = $contacts->fetchAll();

            $appts = $pdo->prepare(
                "SELECT a.id, a.department, a.provider_name, a.scheduled_start, a.scheduled_end,
                        a.location, a.status,
                        (SELECT e.reason FROM appointment_reschedule_events e
                         WHERE e.appointment_id = a.id ORDER BY e.created_at DESC, e.id DESC LIMIT 1) AS reason
                 FROM appointments a
                 WHERE a.patient_id = ?
                 ORDER BY a.scheduled_start DESC
                 LIMIT 20"
            );
            $appts->execute([$id]);
            $patient['appointments'] = $appts->fetchAll();

            $esc = $pdo->prepare(
                'SELECT id, reason, urgency, status, created_at
                 FROM escalations WHERE patient_id = ? ORDER BY created_at DESC LIMIT 10'
            );
            $esc->execute([$id]);
            $patient['escalations'] = $esc->fetchAll();

            $dcr = $pdo->prepare(
                'SELECT id, reason, status, requested_at, updated_at
                 FROM doctor_call_requests WHERE patient_id = ? LIMIT 1'
            );
            $dcr->execute([$id]);
            $patient['doctor_call_request'] = $dcr->fetch() ?: null;
            $patient['hpv_workflow_enabled'] = hpv_workflow_ready();
            $patient['screening_enabled'] = patient_screening_ready();
            $patient['client_id'] = $patient['external_mrn'] ?? null;

            api_json(['ok' => true, 'patient' => $patient]);
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        $sql = 'SELECT p.id, p.full_name, p.status, p.registration_at, p.preferred_language, p.external_mrn AS client_id,
                (SELECT cc.address FROM contact_channels cc WHERE cc.patient_id = p.id AND cc.is_primary = 1 LIMIT 1) AS phone,
                (SELECT cc.channel FROM contact_channels cc WHERE cc.patient_id = p.id AND cc.is_primary = 1 LIMIT 1) AS primary_channel
                FROM patients p';
        $args = [];
        if ($q !== '') {
            $sql .= ' WHERE p.full_name LIKE ? OR p.external_mrn LIKE ?';
            $like = '%' . $q . '%';
            $suffix = normalize_client_id_suffix($q);
            if ($suffix !== '') {
                $sql .= ' OR p.external_mrn LIKE ?';
                $args = [$like, $like, '%' . client_id_prefix() . $suffix . '%'];
            } else {
                $args = [$like, $like];
            }
        }
        $sql .= ' ORDER BY p.external_mrn ASC, p.full_name ASC LIMIT 300';
        $st = $pdo->prepare($sql);
        $st->execute($args);
        api_json(['ok' => true, 'items' => $st->fetchAll()]);
    }

    if ($method !== 'POST') {
        api_json(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = api_body();
    $name = trim((string) ($body['full_name'] ?? ''));
    $dob = trim((string) ($body['date_of_birth'] ?? ''));
    $lang = trim((string) ($body['preferred_language'] ?? 'en')) ?: 'en';
    $clientId = parse_client_id_from_body($body);
    $notes = trim((string) ($body['notes'] ?? ''));
    $phone = api_phone((string) ($body['phone'] ?? ''));
    $channel = ((string) ($body['contact_channel'] ?? 'sms')) === 'whatsapp' ? 'whatsapp' : 'sms';
    $optIn = !empty($body['opt_in']);

    if ($name === '') {
        api_json(['ok' => false, 'error' => 'Full name is required'], 422);
    }
    if ($phone === '' || !preg_match('/^\+254\d{9}$/', $phone)) {
        api_json(['ok' => false, 'error' => 'Enter 9 digits after +254 (e.g. 712345678)'], 422);
    }
    $clientErr = validate_client_id_registration($clientId);
    if ($clientErr !== null) {
        api_json(['ok' => false, 'error' => $clientErr], 422);
    }

    $screening = parse_screening_from_body($body);
    if (patient_screening_ready()) {
        $screenErr = validate_screening_registration($screening);
        if ($screenErr !== null) {
            api_json(['ok' => false, 'error' => $screenErr], 422);
        }
    }

    $followups = compute_screening_followups($screening);
    $nextCheckup = $followups['next_checkup_at'];

    $pdo->beginTransaction();
    try {
        if (patient_screening_ready()) {
            $st = $pdo->prepare(
                'INSERT INTO patients (
                    full_name, date_of_birth, preferred_language, external_mrn, notes, status,
                    hiv_status, hpv_done_before, hpv_prior_result, place_of_residence,
                    via_result, via_date, has_cancer, treatment_date, next_checkup_at
                 ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $st->execute([
                $name,
                $dob === '' ? null : $dob,
                $lang,
                $clientId,
                $notes === '' ? null : $notes,
                'active',
                $screening['hiv_status'],
                $screening['hpv_done_before'],
                $screening['hpv_prior_result'],
                $screening['place_of_residence'] === '' ? null : $screening['place_of_residence'],
                $screening['via_result'],
                $screening['via_date'],
                (int) $screening['has_cancer'],
                $screening['treatment_date'],
                $nextCheckup,
            ]);
        } else {
            $st = $pdo->prepare(
                'INSERT INTO patients (full_name, date_of_birth, preferred_language, external_mrn, notes, status)
                 VALUES (?,?,?,?,?,?)'
            );
            $st->execute([
                $name,
                $dob === '' ? null : $dob,
                $lang,
                $clientId,
                $notes === '' ? null : $notes,
                'active',
            ]);
        }
        $pid = (int) $pdo->lastInsertId();

        if ($screening['hpv_done_before'] === 'yes'
            && in_array($screening['hpv_prior_result'], ['positive', 'negative'], true)
            && hpv_workflow_ready()
        ) {
            $hpvSt = $pdo->prepare(
                'UPDATE patients SET hpv_screening_result = ?, hpv_result_recorded_at = NOW(3) WHERE id = ?'
            );
            $hpvSt->execute([$screening['hpv_prior_result'], $pid]);
        }

        $ch = $pdo->prepare(
            'INSERT INTO contact_channels (patient_id, channel, address, is_primary, opted_in, opted_in_at)
             VALUES (?,?,?,?,?,?)'
        );
        $ch->execute([$pid, $channel, $phone, 1, $optIn ? 1 : 0, $optIn ? date('Y-m-d H:i:s') : null]);

        $ev = $pdo->prepare(
            'INSERT INTO contact_preference_events (patient_id, channel, action, source)
             VALUES (?,?,?,?)'
        );
        $ev->execute([$pid, $channel, $optIn ? 'opt_in' : 'opt_out', 'frontend_registration']);
        if ($optIn) {
            record_registration_consent($pid, $channel);
        }
        $pdo->commit();

        if ($optIn) {
            send_afya_enrollment_messages($pid, $name, $lang);
            process_registration_screening_messages($pid, $name, $lang, $screening, true);
        }

        api_json([
            'ok' => true,
            'patient_id' => $pid,
            'client_id' => $clientId,
            'next_checkup_at' => $nextCheckup,
            'referral_sent' => $screening['via_result'] === 'positive' && !empty($screening['has_cancer']) && $optIn,
        ], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        api_json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
