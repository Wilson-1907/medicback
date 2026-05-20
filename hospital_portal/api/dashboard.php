<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $pdo = db();
    
    // Check if tables exist and get counts
    $stats = [
        'patients' => 0,
        'appointments_today' => 0,
        'upcoming' => 0
    ];
    
    // Get total patients
    $result = $pdo->query("SELECT COUNT(*) as c FROM patients");
    if ($result) {
        $stats['patients'] = (int) $result->fetch()['c'];
    }
    
    // Get today's appointments
    $result = $pdo->query("SELECT COUNT(*) as c FROM appointments WHERE DATE(scheduled_start) = CURDATE() AND status IN ('proposed','confirmed')");
    if ($result) {
        $stats['appointments_today'] = (int) $result->fetch()['c'];
    }
    
    // Get upcoming appointments
    $result = $pdo->query("SELECT COUNT(*) as c FROM appointments WHERE scheduled_start >= NOW() AND status IN ('proposed','confirmed')");
    if ($result) {
        $stats['upcoming'] = (int) $result->fetch()['c'];
    }
    
    // Get appointments with patient details
    $appointments = [];
    $result = $pdo->query(
        "SELECT a.*, p.full_name, p.phone, p.preferred_language, p.primary_channel 
         FROM appointments a 
         LEFT JOIN patients p ON a.patient_id = p.id 
         WHERE a.scheduled_start >= CURDATE() AND a.status IN ('proposed','confirmed')
         ORDER BY a.scheduled_start ASC 
         LIMIT 20"
    );
    
    if ($result) {
        $appointments = $result->fetchAll();
    }
    
    // Get recent patients
    $recent = [];
    $result = $pdo->query(
        "SELECT id, full_name, status, registration_at, preferred_language 
         FROM patients 
         ORDER BY registration_at DESC 
         LIMIT 10"
    );
    
    if ($result) {
        $recent = $result->fetchAll();
    }
    
    api_json([
        'ok' => true,
        'stats' => $stats,
        'appointments' => $appointments,
        'recent' => $recent
    ]);
    
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
?>
