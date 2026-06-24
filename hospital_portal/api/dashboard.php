<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../appointment_utils.php';

try {
    $pdo = db();
    cancel_duplicate_appointments($pdo);

    $clinicDate = clinic_today_date();

    $stats = [
        'patients' => (int) $pdo->query('SELECT COUNT(*) c FROM patients')->fetch()['c'],
        'registered_today' => (int) $pdo->query(
            'SELECT COUNT(*) c FROM patients WHERE DATE(registration_at)=CURDATE()'
        )->fetch()['c'],
        'appointments_today' => count_clinic_day_appointments($pdo),
        'upcoming' => (int) $pdo->query(
            "SELECT COUNT(*) c FROM appointments WHERE scheduled_start >= NOW() AND status IN ('proposed','confirmed')"
        )->fetch()['c'],
        'open_escalations' => (int) $pdo->query(
            "SELECT COUNT(*) c FROM escalations e
             INNER JOIN patients p ON p.id = e.patient_id
             WHERE e.status IN ('open','triaged')"
        )->fetch()['c'],
    ];

    $recent = $pdo->query(
        'SELECT id, full_name, status, preferred_language, registration_at, external_mrn AS client_id
         FROM patients
         ORDER BY registration_at DESC
         LIMIT 10'
    )->fetchAll();

    $apptSt = $pdo->prepare(
        "SELECT a.id, a.patient_id, p.full_name, p.external_mrn AS client_id, a.department, a.provider_name,
                a.scheduled_start, a.scheduled_end, a.location, a.status,
                (SELECT e.reason FROM appointment_reschedule_events e
                 WHERE e.appointment_id = a.id ORDER BY e.created_at DESC, e.id DESC LIMIT 1) AS reason
         FROM appointments a
         INNER JOIN patients p ON p.id = a.patient_id
         WHERE DATE(a.scheduled_start) = ?
           AND a.status IN ('proposed','confirmed','completed','no_show')
         ORDER BY a.scheduled_start ASC
         LIMIT 50"
    );
    $apptSt->execute([$clinicDate]);
    $appointments = $apptSt->fetchAll();

    api_json([
        'ok' => true,
        'stats' => $stats,
        'clinic_date' => $clinicDate,
        'recent' => $recent,
        'appointments' => $appointments,
    ]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
