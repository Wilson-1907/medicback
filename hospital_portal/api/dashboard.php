<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

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

    // Fetch appointments with patient details and language preference
    $appointments = $pdo->query(
        'SELECT 
            a.id,
            a.scheduled_start,
            a.scheduled_end,
            a.status,
            a.department,
            a.provider_name,
            a.location,
            a.reason,
            p.id as patient_id,
            p.full_name,
            p.phone,
            p.preferred_language,
            p.primary_channel
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         WHERE a.scheduled_start >= CURDATE() 
            AND a.status IN ("proposed", "confirmed")
         ORDER BY a.scheduled_start ASC
         LIMIT 20'
    )->fetchAll();

    $recent = $pdo->query(
        'SELECT id, full_name, status, registration_at, preferred_language
         FROM patients
         ORDER BY registration_at DESC
         LIMIT 10'
    )->fetchAll();

    api_json(['ok' => true, 'stats' => $stats, 'appointments' => $appointments, 'recent' => $recent]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
?>
