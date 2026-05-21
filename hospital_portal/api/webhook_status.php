<?php
/**
 * Professional Webhook Status Checker
 * Run this to diagnose webhook issues
 */

header('Content-Type: application/json');

require_once __DIR__ . '/bootstrap.php';

$response = [
    'status' => 'checking',
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'server_name' => $_SERVER['SERVER_NAME'],
        'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'],
        'https' => isset($_SERVER['HTTPS']) ? 'enabled' : 'disabled'
    ],
    'webhook_urls' => [],
    'recent_inbound' => [],
    'configuration' => []
];

// Get the base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $host;

$response['webhook_urls'] = [
    'sms_callback' => $baseUrl . '/api/webhook_africastalking.php',
    'whatsapp_callback' => $baseUrl . '/api/webhook_africastalking.php',
    'delivery_report' => $baseUrl . '/api/webhook_delivery_report.php',
    'test_endpoint' => $baseUrl . '/api/test_webhook.php'
];

// Check if webhook file exists
$webhookFile = __DIR__ . '/webhook_africastalking.php';
$response['configuration']['webhook_file_exists'] = file_exists($webhookFile);
$response['configuration']['webhook_readable'] = is_readable($webhookFile);

// Check recent inbound messages (last 24 hours)
try {
    $db = db();
    $stmt = $db->prepare("
        SELECT id, from_address, body, received_at, channel 
        FROM inbound_messages 
        WHERE received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY id DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['recent_inbound'] = [
        'count' => count($recent),
        'messages' => $recent
    ];
} catch (Exception $e) {
    $response['recent_inbound'] = ['error' => $e->getMessage()];
}

// Check if .env has AT webhook settings
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    $response['configuration']['env_exists'] = true;
    $response['configuration']['has_webhook_secret'] = preg_match('/WEBHOOK_SECRET=/', $envContent) ? true : false;
} else {
    $response['configuration']['env_exists'] = false;
}

// Test if webhook is accessible externally (simulate AT request)
$testPayload = [
    'from' => 'TEST',
    'text' => 'Webhook connectivity test',
    'timestamp' => date('Y-m-d H:i:s')
];

$ch = curl_init($response['webhook_urls']['sms_callback']);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($testPayload),
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
]);
$webhookResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$response['webhook_test'] = [
    'http_code' => $httpCode,
    'response' => trim($webhookResponse),
    'curl_error' => $curlError ?: null,
    'success' => ($httpCode === 200 && trim($webhookResponse) === 'OK')
];

$response['status'] = $response['webhook_test']['success'] ? 'working' : 'not_working';

// Add troubleshooting steps
if (!$response['webhook_test']['success']) {
    $response['troubleshooting'] = [
        '1' => 'Check if your webhook URL is correctly configured in Africa\'s Talking Dashboard',
        '2' => 'Ensure your server is publicly accessible (not localhost)',
        '3' => 'Verify SSL certificate is valid (AT requires HTTPS)',
        '4' => 'Check if your firewall allows POST requests from AT IP addresses',
        '5' => 'Review error logs: ' . __DIR__ . '/../logs/error.log',
        '6' => 'Test manually: curl -X POST ' . $response['webhook_urls']['sms_callback'] . ' -d "from=TEST&text=Hello"'
    ];
}

// Return as JSON (for debugging)
echo json_encode($response, JSON_PRETTY_PRINT);
?>
