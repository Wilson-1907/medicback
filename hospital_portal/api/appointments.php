<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../appointment_utils.php';

try {
    $pdo = db();
    ensure_appointment_attendance_schema();

    // List booked appointments (for the hospital console appointments viewer).
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        cancel_duplicate_appointments($pdo);
        $rows = $pdo->query(
            "SELECT a.id, a.patient_id, p.full_name, p.external_mrn AS client_id, a.department, a.provider_name,
                    a.scheduled_start, a.scheduled_end, a.location, a.status,
                    a.reminder_7d_sent_at, a.reminder_3d_sent_at, a.reminder_night_sent_at,
                    (SELECT cc.channel FROM contact_channels cc
                     WHERE cc.patient_id = p.id AND cc.is_primary = 1 LIMIT 1) AS contact_channel,
                    (SELECT e.reason FROM appointment_reschedule_events e
                     WHERE e.appointment_id = a.id
                     ORDER BY e.created_at DESC, e.id DESC LIMIT 1) AS reason
             FROM appointments a
             INNER JOIN patients p ON p.id = a.patient_id
             WHERE a.status IN ('proposed','confirmed','completed','no_show')
             ORDER BY a.scheduled_start DESC
             LIMIT 300"
        )->fetchAll();
        api_json(['ok' => true, 'items' => $rows]);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        api_json(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = api_body();
    $action = (string) ($body['action'] ?? 'add');

    if ($action === 'add') {
        $patientId = (int) ($body['patient_id'] ?? 0);
        $start = trim((string) ($body['scheduled_start'] ?? ''));
        $reason = trim((string) ($body['reason'] ?? ''));
        if ($patientId < 1 || $start === '' || $reason === '') {
            api_json(['ok' => false, 'error' => 'patient_id, scheduled_start and reason are required'], 422);
        }
        $end = trim((string) ($body['scheduled_end'] ?? ''));
        $department = trim((string) ($body['department'] ?? ''));
        $provider = trim((string) ($body['provider_name'] ?? ''));
        $location = trim((string) ($body['location'] ?? ''));

        $nameSt = $pdo->prepare('SELECT full_name, preferred_language FROM patients WHERE id = ? LIMIT 1');
        $nameSt->execute([$patientId]);
        $nameRow = $nameSt->fetch();
        if (!$nameRow) {
            api_json(['ok' => false, 'error' => 'Patient not found'], 404);
        }
        $patientName = (string) $nameRow['full_name'];
        $lang = in_array($nameRow['preferred_language'], ['en', 'sw']) ? $nameRow['preferred_language'] : 'en';

        $startSql = api_dt($start);
        $endSql = $end === '' ? null : api_dt($end);

        if (appointment_slot_taken($patientId, $startSql)) {
            api_json([
                'ok' => false,
                'error' => 'This patient already has an appointment at that date and time.',
            ], 409);
        }

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'INSERT INTO appointments (patient_id, department, provider_name, scheduled_start, scheduled_end, location, status)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $st->execute([
                $patientId,
                $department === '' ? null : $department,
                $provider === '' ? null : $provider,
                $startSql,
                $endSql,
                $location === '' ? null : $location,
                'proposed',
            ]);
            $appointmentId = (int) $pdo->lastInsertId();
            $h = $pdo->prepare(
                'INSERT INTO appointment_reschedule_events
                 (appointment_id, old_start, old_end, new_start, new_end, reason, initiated_by)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $h->execute([$appointmentId, $startSql, $endSql, $startSql, $endSql, $reason, 'staff']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        send_patient_message(
            $patientId,
            'appointment_booked',
            build_appointment_change_message($patientName, [
                'scheduled_start' => $startSql,
                'scheduled_end' => $endSql,
                'department' => $department === '' ? null : $department,
                'provider_name' => $provider === '' ? null : $provider,
                'location' => $location === '' ? null : $location,
            ], $reason, false, $lang)
        );
        api_json(['ok' => true, 'appointment_id' => $appointmentId], 201);
    }

    if ($action === 'reschedule') {
        $appointmentId = (int) ($body['appointment_id'] ?? 0);
        $newStart = trim((string) ($body['new_scheduled_start'] ?? ''));
        $reason = trim((string) ($body['reason'] ?? ''));
        if ($appointmentId < 1 || $newStart === '' || $reason === '') {
            api_json(['ok' => false, 'error' => 'appointment_id, new_scheduled_start and reason are required'], 422);
        }
        $newEnd = trim((string) ($body['new_scheduled_end'] ?? ''));
        $newStartSql = api_dt($newStart);
        $newEndSql = $newEnd === '' ? null : api_dt($newEnd);

        $st = $pdo->prepare(
            'SELECT a.id, a.patient_id, a.scheduled_start, a.scheduled_end, a.department, a.provider_name, a.location, p.full_name, p.preferred_language
             FROM appointments a
             INNER JOIN patients p ON p.id = a.patient_id
             WHERE a.id = ?
             LIMIT 1'
        );
        $st->execute([$appointmentId]);
        $row = $st->fetch();
        if (!$row) {
            api_json(['ok' => false, 'error' => 'Appointment not found'], 404);
        }

        $lang = in_array($row['preferred_language'], ['en', 'sw']) ? $row['preferred_language'] : 'en';

        if (appointment_slot_taken((int) $row['patient_id'], $newStartSql, $appointmentId)) {
            api_json([
                'ok' => false,
                'error' => 'This patient already has another appointment at that date and time.',
            ], 409);
        }

        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare(
                'UPDATE appointments
                 SET scheduled_start = ?, scheduled_end = ?, reminder_7d_sent_at = NULL, reminder_3d_sent_at = NULL, reminder_night_sent_at = NULL, updated_at = NOW(3)
                 WHERE id = ?'
            );
            $up->execute([$newStartSql, $newEndSql, $appointmentId]);
            $h = $pdo->prepare(
                'INSERT INTO appointment_reschedule_events
                 (appointment_id, old_start, old_end, new_start, new_end, reason, initiated_by)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $h->execute([
                $appointmentId,
                $row['scheduled_start'],
                $row['scheduled_end'],
                $newStartSql,
                $newEndSql,
                $reason,
                'staff',
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        send_patient_message(
            (int) $row['patient_id'],
            'appointment_rescheduled',
            build_appointment_change_message((string) $row['full_name'], [
                'scheduled_start' => $newStartSql,
                'scheduled_end' => $newEndSql,
                'department' => $row['department'],
                'provider_name' => $row['provider_name'],
                'location' => $row['location'],
            ], $reason, true, $lang)
        );
        api_json(['ok' => true, 'appointment_id' => $appointmentId]);
    }

    if ($action === 'mark_attended') {
        $appointmentId = (int) ($body['appointment_id'] ?? 0);
        if ($appointmentId < 1) {
            api_json(['ok' => false, 'error' => 'appointment_id is required'], 422);
        }
        $out = mark_appointment_attended($appointmentId, 'hospital_console');
        api_json($out, !empty($out['ok']) ? 200 : 422);
    }

    if ($action === 'mark_missed') {
        $appointmentId = (int) ($body['appointment_id'] ?? 0);
        if ($appointmentId < 1) {
            api_json(['ok' => false, 'error' => 'appointment_id is required'], 422);
        }
        $out = mark_appointment_missed($appointmentId, 'hospital_console');
        api_json($out, !empty($out['ok']) ? 200 : 422);
    }

    api_json(['ok' => false, 'error' => 'Unknown action'], 422);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
