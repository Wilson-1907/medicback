<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Africa's Talking delivery report webhook.
 * Expected payload keys may include:
 * - id or messageId (provider message id)
 * - status (Success/Failed/etc)
 * - failureReason / reason (optional)
 */
header('Content-Type: text/plain; charset=UTF-8');

function dr_value(string $key): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? '';
    return trim((string) $value);
}

function map_delivery_status(string $rawStatus): string
{
    $s = strtoupper($rawStatus);
    if (in_array($s, ['SUCCESS', 'SENT', 'DELIVERED'], true)) {
        return 'delivered';
    }
    if (in_array($s, ['FAILED', 'REJECTED', 'EXPIRED', 'UNDELIVERABLE'], true)) {
        return 'failed';
    }
    return 'sent';
}

$messageId = dr_value('messageId');
if ($messageId === '') {
    $messageId = dr_value('id');
}

$statusRaw = dr_value('status');
$error = dr_value('failureReason');
if ($error === '') {
    $error = dr_value('reason');
}

if ($messageId !== '') {
    $status = map_delivery_status($statusRaw);
    $st = db()->prepare(
        'UPDATE outbound_messages
         SET status = ?, error_detail = CASE WHEN ? = "" THEN error_detail ELSE ? END
         WHERE at_message_id = ?'
    );
    $st->execute([$status, $error, $error, $messageId]);
}

echo 'OK';
