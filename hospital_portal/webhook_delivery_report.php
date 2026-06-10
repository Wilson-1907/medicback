<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/delivery_report_utils.php';

/**
 * Africa's Talking delivery report webhook.
 * Expected payload keys may include:
 * - id or messageId (provider message id)
 * - status (Success/Failed/etc)
 * - failureReason / reason (optional)
 */
header('Content-Type: text/plain; charset=UTF-8');

$payload = [];
foreach ([$_GET, $_POST] as $source) {
    foreach ($source as $k => $v) {
        if (is_scalar($v)) {
            $payload[(string) $k] = (string) $v;
        }
    }
}

apply_africastalking_delivery_report($payload);

echo 'OK';
