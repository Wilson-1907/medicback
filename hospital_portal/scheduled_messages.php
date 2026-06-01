<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/messaging.php';

function schedule_patient_message(
    int $patientId,
    string $messageType,
    string $body,
    string $delayExpression = '+3 minutes'
): int
{
    $sendAt = date('Y-m-d H:i:s', strtotime($delayExpression));
    $st = db()->prepare(
        'INSERT INTO scheduled_messages (patient_id, message_type, body, send_at, status)
         VALUES (?,?,?,?,?)'
    );
    $st->execute([$patientId, $messageType, $body, $sendAt, 'queued']);
    return (int) db()->lastInsertId();
}

/** @return array{processed: int, sent: int, failed: int} */
function process_due_scheduled_messages(): array
{
    $pdo = db();
    $rows = $pdo->query(
        "SELECT id, patient_id, message_type, body
         FROM scheduled_messages
         WHERE status = 'queued' AND send_at <= NOW(3)
         ORDER BY send_at ASC
         LIMIT 100"
    )->fetchAll();

    $sent = 0;
    $failed = 0;
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $patientId = (int) $row['patient_id'];
        $type = (string) $row['message_type'];
        $body = (string) $row['body'];
        $ok = send_patient_message($patientId, $type, $body);
        $upd = $pdo->prepare(
            'UPDATE scheduled_messages SET status = ?, sent_at = NOW(3) WHERE id = ?'
        );
        if ($ok) {
            $upd->execute(['sent', $id]);
            $sent++;
        } else {
            $upd->execute(['failed', $id]);
            $failed++;
        }
    }

    return ['processed' => count($rows), 'sent' => $sent, 'failed' => $failed];
}
