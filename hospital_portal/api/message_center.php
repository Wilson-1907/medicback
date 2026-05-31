<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $pdo = db();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $body = api_body();
        if ((string) ($body['action'] ?? '') !== 'send_custom') {
            api_json(['ok' => false, 'error' => 'Unknown action'], 422);
        }
        $messageText = trim((string) ($body['message_text'] ?? ''));
        if ($messageText === '') {
            api_json(['ok' => false, 'error' => 'message_text is required'], 422);
        }
        $target = (string) ($body['target'] ?? 'one');
        if ($target === 'broadcast') {
            $recipients = $pdo->query(
                "SELECT DISTINCT p.id
                 FROM patients p
                 INNER JOIN contact_channels c ON c.patient_id = p.id
                 WHERE p.status = 'active' AND c.opted_in = 1"
            )->fetchAll();
            $count = 0;
            foreach ($recipients as $r) {
                send_patient_message((int) $r['id'], 'system', $messageText);
                $count++;
            }
            api_json(['ok' => true, 'sent' => $count]);
        }
        $patientId = (int) ($body['patient_id'] ?? 0);
        if ($patientId < 1) {
            api_json(['ok' => false, 'error' => 'patient_id is required'], 422);
        }
        send_patient_message($patientId, 'system', $messageText);
        api_json(['ok' => true, 'sent' => 1]);
    }

    $stats = [
        'outbound_24h' => (int) $pdo->query(
            "SELECT COUNT(*) c FROM outbound_messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
        )->fetch()['c'],
        'failed_24h' => (int) $pdo->query(
            "SELECT COUNT(*) c FROM outbound_messages WHERE status='failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
        )->fetch()['c'],
        'inbound_24h' => (int) $pdo->query(
            "SELECT COUNT(*) c FROM inbound_messages WHERE received_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
        )->fetch()['c'],
        'open_escalations' => (int) $pdo->query(
            "SELECT COUNT(*) c FROM escalations WHERE status IN ('open','triaged')"
        )->fetch()['c'],
    ];

    $outbound = $pdo->query(
        "SELECT o.id, o.created_at, o.channel, o.message_type, o.status, o.body, o.error_detail, p.full_name
         FROM outbound_messages o
         INNER JOIN patients p ON p.id = o.patient_id
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 80"
    )->fetchAll();
    $inbound = $pdo->query(
        "SELECT i.id, i.received_at, i.channel, i.from_address, i.body, p.full_name
         FROM inbound_messages i
         LEFT JOIN patients p ON p.id = i.patient_id
         ORDER BY i.received_at DESC, i.id DESC
         LIMIT 80"
    )->fetchAll();
    $escalations = $pdo->query(
        "SELECT e.id, e.patient_id, e.created_at, e.status, e.urgency, e.reason, p.full_name,
                (SELECT cc.address FROM contact_channels cc WHERE cc.patient_id = p.id AND cc.is_primary = 1 LIMIT 1) AS phone,
                (SELECT cc.channel FROM contact_channels cc WHERE cc.patient_id = p.id AND cc.is_primary = 1 LIMIT 1) AS channel,
                dcr.status AS doctor_call_status,
                dcr.requested_at AS doctor_call_requested_at,
                dcr.reason AS doctor_call_reason
         FROM escalations e
         INNER JOIN patients p ON p.id = e.patient_id
         LEFT JOIN doctor_call_requests dcr ON dcr.patient_id = e.patient_id
         ORDER BY e.created_at DESC, e.id DESC
         LIMIT 60"
    )->fetchAll();

    api_json([
        'ok' => true,
        'stats' => $stats,
        'outbound' => $outbound,
        'inbound' => $inbound,
        'escalations' => $escalations,
    ]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
