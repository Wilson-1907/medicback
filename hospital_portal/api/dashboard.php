<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $pdo = db();
    $stats = [
        'patients' => (int) $pdo->query('SELECT COUNT(*) c FROM patients')->fetch()['c'],
        'appointments_today' => (int) $pdo->query(
            "SELECT COUNT(*) c FROM appointments WHERE DATE(scheduled_start)=CURDATE() AND status IN ('proposed','confirmed')"
        )->fetch()['c'],
        'upcoming' => (int) $pdo->query(
            "SELECT COUNT(*) c FROM appointments WHERE scheduled_start >= NOW() AND status IN ('proposed','confirmed')"
        )->fetch()['c'],
    ];

    $recent = $pdo->query(
        'SELECT id, full_name, status, registration_at
         FROM patients
         ORDER BY registration_at DESC
         LIMIT 10'
    )->fetchAll();

    // NEW: Fetch appointments with patient names and formatted dates
    $appointments = $pdo->query(
        'SELECT 
            a.id,
            DATE(a.scheduled_start) as appointment_date,
            TIME(a.scheduled_start) as start_time,
            TIME(a.scheduled_end) as end_time,
            a.status,
            p.full_name,
            p.id as patient_id
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         WHERE a.scheduled_start >= CURDATE()
         ORDER BY a.scheduled_start ASC
         LIMIT 30'
    )->fetchAll();

    api_json([
        'ok' => true, 
        'stats' => $stats, 
        'recent' => $recent,
        'appointments' => $appointments
    ]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
