<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

try {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'add';
    
    if ($action === 'add') {
        // Validate required fields
        if (empty($data['patient_id']) || empty($data['scheduled_start']) || empty($data['reason'])) {
            throw new Exception('Patient ID, scheduled start time, and reason are required');
        }
        
        // Get patient language preference
        $stmt = $pdo->prepare('SELECT full_name, phone, preferred_language, primary_channel FROM patients WHERE id = ?');
        $stmt->execute([$data['patient_id']]);
        $patient = $stmt->fetch();
        
        if (!$patient) {
            throw new Exception('Patient not found');
        }
        
        // Insert appointment
        $stmt = $pdo->prepare(
            'INSERT INTO appointments (patient_id, scheduled_start, scheduled_end, department, 
             provider_name, location, reason, status, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        
        $stmt->execute([
            $data['patient_id'],
            $data['scheduled_start'],
            $data['scheduled_end'] ?? null,
            $data['department'] ?? null,
            $data['provider_name'] ?? null,
            $data['location'] ?? null,
            $data['reason'],
            'proposed'
        ]);
        
        $appointmentId = $pdo->lastInsertId();
        
        // Send notification in patient's preferred language
        sendAppointmentNotification($patient, $data['scheduled_start'], $appointmentId);
        
        api_json(['ok' => true, 'id' => $appointmentId, 'message' => 'Appointment scheduled successfully']);
    }
    
    elseif ($action === 'reschedule') {
        if (empty($data['appointment_id']) || empty($data['new_scheduled_start']) || empty($data['reason'])) {
            throw new Exception('Appointment ID, new time, and reason are required');
        }
        
        // Get appointment and patient details
        $stmt = $pdo->prepare(
            'SELECT a.*, p.full_name, p.phone, p.preferred_language, p.primary_channel 
             FROM appointments a 
             JOIN patients p ON a.patient_id = p.id 
             WHERE a.id = ?'
        );
        $stmt->execute([$data['appointment_id']]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) {
            throw new Exception('Appointment not found');
        }
        
        // Update appointment
        $stmt = $pdo->prepare(
            'UPDATE appointments 
             SET scheduled_start = ?, scheduled_end = ?, status = "proposed", updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$data['new_scheduled_start'], $data['new_scheduled_end'] ?? null, $data['appointment_id']]);
        
        // Send reschedule notification in patient's language
        sendRescheduleNotification($appointment, $data['new_scheduled_start'], $data['reason']);
        
        api_json(['ok' => true, 'message' => 'Appointment rescheduled successfully']);
    }
    
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}

function sendAppointmentNotification($patient, $appointmentTime, $appointmentId) {
    $date = date('l, F j, Y', strtotime($appointmentTime));
    $time = date('g:i A', strtotime($appointmentTime));
    
    $messages = [
        'en' => "📅 Appointment Confirmation\n\nDear {$patient['full_name']},\n\nYour appointment has been scheduled for:\n📆 Date: $date\n⏰ Time: $time\n\nPlease arrive 15 minutes early.\n\nReply CONFIRM to confirm or RESCHEDULE to change.\n\nPHV Hospital",
        
        'sw' => "📅 Uthibitisho wa Miadi\n\nMpendwa {$patient['full_name']},\n\nMiadi yako imepangwa kwa:\n📆 Tarehe: $date\n⏰ Saa: $time\n\nTafadhali fika dakika 15 kabla ya wakati.\n\nJibu CONFIRM ili kuthibitisha au RESCHEDULE kubadilisha.\n\nHospitali ya PHV"
    ];
    
    $message = $messages[$patient['preferred_language']] ?? $messages['en'];
    
    // Log the message (in production, send via SMS/WhatsApp)
    error_log("Sending to {$patient['phone']} ({$patient['preferred_language']}): $message");
    
    // Here you would integrate with your messaging service
    // sendMessage($patient['phone'], $message, $patient['primary_channel']);
}

function sendRescheduleNotification($appointment, $newTime, $reason) {
    $oldDate = date('l, F j, Y', strtotime($appointment['scheduled_start']));
    $oldTime = date('g:i A', strtotime($appointment['scheduled_start']));
    $newDate = date('l, F j, Y', strtotime($newTime));
    $newTimeDate = date('g:i A', strtotime($newTime));
    
    $messages = [
        'en' => "🔄 Appointment Rescheduled\n\nDear {$appointment['full_name']},\n\nYour appointment has been rescheduled.\n\n❌ Old: $oldDate at $oldTime\n✅ New: $newDate at $newTime\n\nReason: $reason\n\nPlease confirm your availability by replying CONFIRM.\n\nPHV Hospital",
        
        'sw' => "🔄 Miadi Imebadilishwa\n\nMpendwa {$appointment['full_name']},\n\nMiadi yako imebadilishwa.\n\n❌ Ya zamani: $oldDate saa $oldTime\n✅ Mpya: $newDate saa $newTimeDate\n\nSababu: $reason\n\nTafadhali thibitisha upatikanaji wako kwa kujibu CONFIRM.\n\nHospitali ya PHV"
    ];
    
    $message = $messages[$appointment['preferred_language']] ?? $messages['en'];
    
    error_log("Reschedule notification to {$appointment['phone']}: $message");
}
?>
