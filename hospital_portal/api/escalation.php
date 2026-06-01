<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../doctor_call_requests.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        api_json(['ok' => false, 'error' => 'GET required'], 405);
    }

    $id = (int) ($_GET['id'] ?? 0);
    if ($id < 1) {
        api_json(['ok' => false, 'error' => 'id is required'], 422);
    }

    $pdo = db();
    $st = $pdo->prepare(
        "SELECT e.id, e.patient_id, e.created_at, e.status, e.urgency, e.reason, p.full_name,
                (SELECT cc.address FROM contact_channels cc
                 WHERE cc.patient_id = p.id AND cc.is_primary = 1 LIMIT 1) AS phone,
                (SELECT cc.channel FROM contact_channels cc
                 WHERE cc.patient_id = p.id AND cc.is_primary = 1 LIMIT 1) AS channel,
                dcr.status AS doctor_call_status,
                dcr.requested_at AS doctor_call_requested_at,
                dcr.reason AS doctor_call_reason,
                (SELECT i.body FROM inbound_messages i
                 WHERE i.patient_id = p.id ORDER BY i.received_at DESC, i.id DESC LIMIT 1) AS last_inbound_body,
                (SELECT i.received_at FROM inbound_messages i
                 WHERE i.patient_id = p.id ORDER BY i.received_at DESC, i.id DESC LIMIT 1) AS last_inbound_at
         FROM escalations e
         INNER JOIN patients p ON p.id = e.patient_id
         LEFT JOIN doctor_call_requests dcr ON dcr.patient_id = e.patient_id
         WHERE e.id = ?
         LIMIT 1"
    );
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        api_json(['ok' => false, 'error' => 'Escalation not found'], 404);
    }

    $row['awaiting_doctor_reason'] = is_awaiting_doctor_reason_row([
        'status' => $row['doctor_call_status'] ?? '',
        'reason' => $row['doctor_call_reason'] ?? '',
    ]);
    $row['patient_stated_reason'] = patient_stated_call_reason(
        ['reason' => $row['doctor_call_reason'] ?? ''],
        $row['reason'] ?? ''
    );

    api_json(['ok' => true, 'escalation' => $row]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
