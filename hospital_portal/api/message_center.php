<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $pdo = db();
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
        "SELECT e.id, e.created_at, e.status, e.urgency, e.reason, p.full_name
         FROM escalations e
         INNER JOIN patients p ON p.id = e.patient_id
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
