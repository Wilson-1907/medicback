<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function messaging_enabled(): bool
{
    return AFRICASTALKING_API_KEY !== '';
}

/**
 * Returns ['channel' => 'sms|whatsapp', 'address' => '+254...'] or null.
 */
function patient_primary_contact(int $patientId): ?array
{
    $st = db()->prepare(
        'SELECT channel, address
         FROM contact_channels
         WHERE patient_id = ? AND opted_in = 1
         ORDER BY is_primary DESC, id ASC
         LIMIT 1'
    );
    $st->execute([$patientId]);
    $row = $st->fetch();
    return $row ?: null;
}

function log_outbound_message(int $patientId, string $channel, string $type, string $body): int
{
    $st = db()->prepare(
        'INSERT INTO outbound_messages (patient_id, channel, message_type, body, status)
         VALUES (?,?,?,?,?)'
    );
    $st->execute([$patientId, $channel, $type, $body, 'queued']);
    return (int) db()->lastInsertId();
}

function update_outbound_status(int $outboundId, string $status, ?string $atId, ?string $error): void
{
    $st = db()->prepare(
        'UPDATE outbound_messages
         SET status = ?, at_message_id = ?, error_detail = ?
         WHERE id = ?'
    );
    $st->execute([$status, $atId, $error, $outboundId]);
}

/**
 * Sends either SMS or WhatsApp via Africa's Talking.
 * Returns ['ok' => bool, 'message_id' => ?string, 'error' => ?string]
 */
function africastalking_send(string $channel, string $to, string $message): array
{
    if (!messaging_enabled()) {
        return ['ok' => false, 'message_id' => null, 'error' => 'AFRICASTALKING_API_KEY is empty'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message_id' => null, 'error' => 'PHP cURL extension is not enabled'];
    }

    $url = $channel === 'whatsapp' ? AFRICASTALKING_WHATSAPP_URL : AFRICASTALKING_SMS_URL;
    $sender = $channel === 'whatsapp' ? AFRICASTALKING_WHATSAPP_FROM : AFRICASTALKING_SMS_FROM;
    $payload = [
        'username' => AFRICASTALKING_USERNAME,
        'to' => $to,
        'message' => $message,
    ];
    if ($sender !== '') {
        $payload['from'] = $sender;
    }

    $request = function (array $extraOptions = []) use ($url, $payload): array {
        $ch = curl_init($url);
        $opts = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apiKey: ' . AFRICASTALKING_API_KEY,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        CURLOPT_NOPROXY => '*',
        ];
        foreach ($extraOptions as $k => $v) {
            $opts[$k] = $v;
        }
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$raw, $err, $code];
    };

    [$raw, $err, $code] = $request();

    if ($raw === false) {
        $retryableSslError = str_contains(strtolower($err), 'wrong version number')
            || str_contains(strtolower($err), 'ssl routines');
        if ($retryableSslError) {
            // Retry once with default SSL negotiation in case host/client TLS settings mismatch.
            [$rawRetry, $errRetry, $codeRetry] = $request([CURLOPT_SSLVERSION => CURL_SSLVERSION_DEFAULT]);
            if ($rawRetry !== false) {
                $raw = $rawRetry;
                $err = '';
                $code = $codeRetry;
            } else {
                $err = $errRetry;
            }
        }
    }

    if ($raw === false) {
        return ['ok' => false, 'message_id' => null, 'error' => $err !== '' ? $err : 'Unknown cURL error'];
    }

    $json = json_decode($raw, true);
    $messageId = null;
    if (is_array($json)) {
        if (isset($json['SMSMessageData']['Recipients'][0]['messageId'])) {
            $messageId = (string) $json['SMSMessageData']['Recipients'][0]['messageId'];
        } elseif (isset($json['data']['id'])) {
            $messageId = (string) $json['data']['id'];
        }
    }

    if ($code >= 200 && $code < 300) {
        return ['ok' => true, 'message_id' => $messageId, 'error' => null];
    }
    $error = is_array($json) ? json_encode($json) : (string) $raw;
    return ['ok' => false, 'message_id' => $messageId, 'error' => 'HTTP ' . $code . ': ' . $error];
}

function send_patient_message(int $patientId, string $messageType, string $body): void
{
    $contact = patient_primary_contact($patientId);
    if (!$contact) {
        return;
    }

    $channel = (string) $contact['channel'];
    $address = (string) $contact['address'];
    $outboundId = log_outbound_message($patientId, $channel, $messageType, $body);
    $result = africastalking_send($channel, $address, $body);

    if ($result['ok']) {
        update_outbound_status($outboundId, 'sent', $result['message_id'], null);
        return;
    }
    update_outbound_status($outboundId, 'failed', $result['message_id'], $result['error']);
}

function build_welcome_message(string $patientName): string
{
    return "Hello {$patientName}, welcome to " . HOSPITAL_NAME . ". "
        . "We are happy to have you on board - enjoy our services. "
        . "We will keep sharing helpful PHV care tips and appointment updates. "
        . "Reply HELP any time for guidance.";
}

function build_appointment_message(string $patientName, array $appointment): string
{
    $parts = [];
    $parts[] = "Hello {$patientName}, your appointment at " . HOSPITAL_NAME . " is scheduled.";
    $parts[] = 'Date/Time: ' . ($appointment['scheduled_start'] ?? 'TBD');
    if (!empty($appointment['department'])) {
        $parts[] = 'Department: ' . $appointment['department'];
    }
    if (!empty($appointment['provider_name'])) {
        $parts[] = 'Provider: ' . $appointment['provider_name'];
    }
    if (!empty($appointment['location'])) {
        $parts[] = 'Location: ' . $appointment['location'];
    }
    $parts[] = 'We are here for you. Reply HELP for PHV signs & prevention tips, or DOCTOR for direct hospital contact.';
    return implode("\n", $parts);
}

function build_appointment_change_message(string $patientName, array $appointment, string $reason, bool $isUpdate): string
{
    $parts = [];
    if ($isUpdate) {
        $parts[] = "Hello {$patientName}, your appointment at " . HOSPITAL_NAME . " has been updated.";
    } else {
        $parts[] = "Hello {$patientName}, your appointment at " . HOSPITAL_NAME . " is booked.";
    }
    $parts[] = 'Date/Time: ' . ($appointment['scheduled_start'] ?? 'TBD');
    if (!empty($appointment['scheduled_end'])) {
        $parts[] = 'End time: ' . $appointment['scheduled_end'];
    }
    if (!empty($appointment['department'])) {
        $parts[] = 'Department: ' . $appointment['department'];
    }
    if (!empty($appointment['provider_name'])) {
        $parts[] = 'Provider: ' . $appointment['provider_name'];
    }
    if (!empty($appointment['location'])) {
        $parts[] = 'Location: ' . $appointment['location'];
    }
    $parts[] = 'Reason: ' . $reason;
    $parts[] = 'We are here for you. Reply HELP for PHV signs & prevention tips, or DOCTOR for direct hospital contact.';
    return implode("\n", $parts);
}

function build_engagement_menu_message(): string
{
    return "Stay active with your care at " . HOSPITAL_NAME . ":\n"
        . "1) PHV warning signs\n"
        . "2) Prevention tips\n"
        . "3) Appointment help\n"
        . "4) Talk to hospital team";
}
