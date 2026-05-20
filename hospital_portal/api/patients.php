<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

header('Content-Type: application/json');

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $q = $_GET['q'] ?? '';
        $sql = 'SELECT id, full_name, external_mrn, phone, preferred_language, primary_channel, status, registration_at 
                FROM patients';
        $params = [];
        
        if (!empty($q)) {
            $sql .= ' WHERE full_name LIKE ? OR external_mrn LIKE ? OR id = ?';
            $params = ["%$q%", "%$q%", is_numeric($q) ? $q : 0];
        }
        
        $sql .= ' ORDER BY registration_at DESC LIMIT 100';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        
        api_json(['ok' => true, 'items' => $items]);
    }
    
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['full_name']) || empty($data['phone'])) {
            throw new Exception('Full name and phone are required');
        }
        
        // Insert patient
        $stmt = $pdo->prepare(
            'INSERT INTO patients (full_name, date_of_birth, external_mrn, phone, preferred_language, 
             primary_channel, notes, opt_in, status, registration_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        
        $stmt->execute([
            $data['full_name'],
            $data['date_of_birth'] ?? null,
            $data['external_mrn'] ?? null,
            $data['phone'],
            $data['preferred_language'] ?? 'en',
            $data['contact_channel'] ?? 'sms',
            $data['notes'] ?? null,
            $data['opt_in'] ?? 1,
            'active'
        ]);
        
        $patientId = $pdo->lastInsertId();
        
        // Send welcome message based on language preference
        if ($data['opt_in'] ?? 1) {
            sendLanguageSpecificMessage($patientId, $data['preferred_language'] ?? 'en');
        }
        
        api_json(['ok' => true, 'id' => $patientId, 'message' => 'Patient registered successfully']);
    }
    
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}

function sendLanguageSpecificMessage($patientId, $language) {
    // This function would integrate with your SMS/WhatsApp service
    // For now, we'll log it
    $messages = [
        'en' => "Welcome to PHV Hospital! Your health is our priority. We'll send you appointment reminders and health tips.",
        'sw' => "Karibu Hospitali ya PHV! Afya yako ni kipaumbele chetu. Tutakutumia vikumbusho vya miadi na vidokezo vya afya."
    ];
    
    $message = $messages[$language] ?? $messages['en'];
    
    // Log the message that would be sent
    error_log("Sending to patient $patientId ($language): $message");
    
    // Here you would call your actual messaging service
    // sendSMS($phone, $message);
}
?>
