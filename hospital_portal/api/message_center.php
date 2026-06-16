<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../doctor_call_requests.php';
require_once __DIR__ . '/../stuck_messages.php';

try {
    $pdo = db();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $body = api_body();
        $action = (string) ($body['action'] ?? 'send_custom');

        if ($action === 'resend_failed') {
            @set_time_limit(300);
            $outboundIds = null;
            if (isset($body['outbound_ids']) && is_array($body['outbound_ids'])) {
                $outboundIds = $body['outbound_ids'];
            } elseif (isset($body['outbound_id'])) {
                $outboundIds = [(int) $body['outbound_id']];
            }
            $hours = (int) ($body['hours'] ?? 168);
            $limit = (int) ($body['limit'] ?? 200);
            $result = resend_undelivered_outbound($outboundIds, $hours, $limit);
            api_json(['ok' => true, 'resend' => $result]);
        }

        if ($action !== 'send_custom') {
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
            $failed = 0;
            foreach ($recipients as $r) {
                if (send_patient_message((int) $r['id'], 'staff_custom', $messageText)) {
                    $count++;
                } else {
                    $failed++;
                }
            }
            if ($count === 0 && $failed > 0) {
                api_json(['ok' => false, 'error' => 'WhatsApp send failed for all patients — submit afya_staff_message template in Mteja'], 502);
            }
            api_json(['ok' => true, 'sent' => $count, 'failed' => $failed]);
        }
        $patientId = (int) ($body['patient_id'] ?? 0);
        if ($patientId < 1) {
            api_json(['ok' => false, 'error' => 'patient_id is required'], 422);
        }
        if (!send_patient_message($patientId, 'staff_custom', $messageText)) {
            api_json([
                'ok' => false,
                'error' => 'WhatsApp send failed — create template afya_staff_message_en (lang en) in Mteja with body variable {{1}}',
            ], 502);
        }
        api_json(['ok' => true, 'sent' => 1]);
    }

    $undeliveredSql = "FROM outbound_messages o
         INNER JOIN patients p ON p.id = o.patient_id
         WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
           AND (
               o.status = 'failed'
               OR (
                   o.status = 'sent'
                   AND o.channel = 'sms'
                   AND o.created_at <= DATE_SUB(NOW(), INTERVAL 2 HOUR)
               )
           )
           AND NOT EXISTS (
               SELECT 1 FROM outbound_messages o2
               WHERE o2.patient_id = o.patient_id
                 AND o2.message_type = o.message_type
                 AND o2.status IN ('sent', 'delivered')
                 AND o2.id > o.id
                 AND (o2.body = o.body OR o2.status = 'delivered')
           )";

    $stats = [
        'outbound_24h' => (int) $pdo->query(
            "SELECT COUNT(*) c FROM outbound_messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
        )->fetch()['c'],
        'failed_24h' => (int) $pdo->query("SELECT COUNT(*) c {$undeliveredSql}")->fetch()['c'],
        'inbound_24h' => (int) $pdo->query(
            "SELECT COUNT(*) c FROM inbound_messages WHERE received_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
        )->fetch()['c'],
        'open_escalations' => (int) $pdo->query(
            "SELECT COUNT(*) c FROM escalations e
             INNER JOIN patients p ON p.id = e.patient_id
             WHERE e.status IN ('open','triaged')"
        )->fetch()['c'],
    ];

    $outbound = $pdo->query(
        "SELECT o.id, o.created_at, o.channel, o.message_type, o.status, o.body, o.error_detail, p.full_name
         FROM outbound_messages o
         INNER JOIN patients p ON p.id = o.patient_id
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 80"
    )->fetchAll();
    $failedOutbound24h = $pdo->query(
        "SELECT o.id, o.created_at, o.channel, o.message_type, o.status, o.body, o.error_detail,
                p.full_name, p.external_mrn AS client_id
         {$undeliveredSql}
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 50"
    )->fetchAll();
    $inbound = $pdo->query(
        "SELECT i.id, i.received_at, i.channel, i.from_address, i.body, p.full_name
         FROM inbound_messages i
         LEFT JOIN patients p ON p.id = i.patient_id
         ORDER BY i.received_at DESC, i.id DESC
         LIMIT 80"
    )->fetchAll();
    $escalations = $pdo->query(
        "SELECT e.id, e.patient_id, p.external_mrn AS client_id, e.created_at, e.status, e.urgency, e.reason, p.full_name,
                (SELECT cc.address FROM contact_channels cc WHERE cc.patient_id = p.id AND cc.is_primary = 1 LIMIT 1) AS phone,
                (SELECT cc.channel FROM contact_channels cc WHERE cc.patient_id = p.id AND cc.is_primary = 1 LIMIT 1) AS channel,
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
         WHERE e.status IN ('open','triaged')
         ORDER BY FIELD(e.urgency, 'same_day', 'urgent', 'routine'), e.created_at DESC, e.id DESC
         LIMIT 60"
    )->fetchAll();

    foreach ($escalations as &$esc) {
        $esc['awaiting_doctor_reason'] = is_awaiting_doctor_reason_row([
            'status' => $esc['doctor_call_status'] ?? '',
            'reason' => $esc['doctor_call_reason'] ?? '',
        ]);
        $esc['patient_stated_reason'] = patient_stated_call_reason(
            ['reason' => $esc['doctor_call_reason'] ?? ''],
            $esc['reason'] ?? ''
        );
    }
    unset($esc);

    api_json([
        'ok' => true,
        'stats' => $stats,
        'outbound' => $outbound,
        'failed_outbound_24h' => $failedOutbound24h,
        'inbound' => $inbound,
        'escalations' => $escalations,
    ]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
