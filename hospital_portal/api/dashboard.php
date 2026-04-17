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

    api_json(['ok' => true, 'stats' => $stats, 'recent' => $recent]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
