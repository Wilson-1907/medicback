<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function delivery_report_payload_value(array $payload, array $keys): string
{
    $lower = [];
    foreach ($payload as $k => $v) {
        if (is_scalar($v)) {
            $lower[strtolower((string) $k)] = trim((string) $v);
        }
    }
    foreach ($keys as $key) {
        $v = $lower[strtolower($key)] ?? '';
        if ($v !== '') {
            return $v;
        }
    }
    return '';
}

function map_delivery_report_status(string $rawStatus): string
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

/** True when payload looks like AT delivery status (not a patient reply). */
function is_africastalking_delivery_report_payload(array $payload): bool
{
    $messageId = delivery_report_payload_value($payload, ['id', 'messageId', 'messageid']);
    $statusRaw = delivery_report_payload_value($payload, ['status']);
    if ($messageId === '' || $statusRaw === '') {
        return false;
    }

    $text = delivery_report_payload_value($payload, ['text', 'message', 'body', 'content']);
    $from = delivery_report_payload_value($payload, ['from', 'fromNumber', 'source', 'sender', 'phoneNumber', 'phone', 'msisdn']);
    return $text === '' || $from === '';
}

/** Update outbound_messages from an AT-style delivery report payload. */
function apply_africastalking_delivery_report(array $payload): bool
{
    if (!is_africastalking_delivery_report_payload($payload)) {
        return false;
    }

    $messageId = delivery_report_payload_value($payload, ['id', 'messageId', 'messageid']);
    $statusRaw = delivery_report_payload_value($payload, ['status']);
    $error = delivery_report_payload_value($payload, ['failureReason', 'reason']);

    $status = map_delivery_report_status($statusRaw);
    $st = db()->prepare(
        'UPDATE outbound_messages
         SET status = ?, error_detail = CASE WHEN ? = "" THEN error_detail ELSE ? END
         WHERE at_message_id = ?'
    );
    $st->execute([$status, $error, $error, $messageId]);

    return true;
}
