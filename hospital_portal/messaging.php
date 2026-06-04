<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/afya_rafiki_content.php';

/** Expand outbound message types — converts legacy ENUM to VARCHAR so any label is accepted. */
function ensure_outbound_message_types(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $pdo = db();
        $st = $pdo->prepare(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'outbound_messages'
               AND COLUMN_NAME = 'message_type' LIMIT 1"
        );
        $st->execute();
        $dataType = strtolower((string) ($st->fetchColumn() ?: ''));
        if ($dataType === 'enum') {
            $pdo->exec(
                "ALTER TABLE outbound_messages
                 MODIFY COLUMN message_type VARCHAR(32) NOT NULL DEFAULT 'system'"
            );
        } elseif ($dataType === '') {
            // Table may not exist yet on fresh installs; schema.sql defines VARCHAR.
        }
        $done = true;
    } catch (Throwable $e) {
        error_log('ensure_outbound_message_types: ' . $e->getMessage());
    }
}

function force_outbound_message_types_varchar(): void
{
    try {
        db()->exec(
            "ALTER TABLE outbound_messages
             MODIFY COLUMN message_type VARCHAR(32) NOT NULL DEFAULT 'system'"
        );
    } catch (Throwable $e) {
        error_log('force_outbound_message_types_varchar: ' . $e->getMessage());
    }
}

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
    ensure_outbound_message_types();
    $st = db()->prepare(
        'INSERT INTO outbound_messages (patient_id, channel, message_type, body, status)
         VALUES (?,?,?,?,?)'
    );
    try {
        $st->execute([$patientId, $channel, $type, $body, 'queued']);
    } catch (PDOException $e) {
        if (stripos($e->getMessage(), 'message_type') !== false) {
            force_outbound_message_types_varchar();
            $st->execute([$patientId, $channel, $type, $body, 'queued']);
        } else {
            throw $e;
        }
    }
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

function send_patient_message(int $patientId, string $messageType, string $body): bool
{
    $contact = patient_primary_contact($patientId);
    if (!$contact) {
        error_log("SEND_PATIENT_MESSAGE FAILED: No contact channel found for patient $patientId (message: '$messageType')");
        return false;
    }

    $channel = (string) $contact['channel'];
    $address = (string) $contact['address'];

    if ($channel === 'whatsapp' && AFRICASTALKING_WHATSAPP_FROM === '') {
        error_log("SEND_PATIENT_MESSAGE FAILED: WhatsApp sender (AFRICASTALKING_*_WHATSAPP_FROM) not configured for patient $patientId");
        $outboundId = log_outbound_message($patientId, $channel, $messageType, $body);
        update_outbound_status($outboundId, 'failed', null, 'WhatsApp sender not configured on server');
        return false;
    }

    error_log("SEND_PATIENT_MESSAGE: Patient=$patientId, Channel=$channel, Address=$address, Type=$messageType");

    $outboundId = log_outbound_message($patientId, $channel, $messageType, $body);
    $result = africastalking_send($channel, $address, $body);

    error_log("AFRICASTALKING_RESULT: outboundId=$outboundId, ok=" . ($result['ok'] ? 'true' : 'false') . ", error=" . ($result['error'] ?? 'none'));

    if ($result['ok']) {
        update_outbound_status($outboundId, 'sent', $result['message_id'], null);
        return true;
    }
    update_outbound_status($outboundId, 'failed', $result['message_id'], $result['error']);
    return false;
}

/**
 * Get patient's preferred language
 */
function get_patient_language(int $patientId): string
{
    $st = db()->prepare('SELECT preferred_language FROM patients WHERE id = ? LIMIT 1');
    $st->execute([$patientId]);
    $row = $st->fetch();
    $lang = $row ? ((string) $row['preferred_language'] ?: 'en') : 'en';
    return in_array($lang, ['en', 'sw']) ? $lang : 'en';
}

/** Sent immediately after successful registration when patient opted in to messages. */
function send_afya_enrollment_messages(int $patientId, string $patientName, string $lang = 'en'): void
{
    send_patient_message($patientId, 'welcome', build_welcome_message($patientName, $lang));
}

function build_appointment_message(string $patientName, array $appointment, string $lang = 'en'): string
{
    $parts = [];
    
    if ($lang === 'sw') {
        $parts[] = "Habari {$patientName}, miadi yako katika " . HOSPITAL_NAME . " imepangwa.";
        $parts[] = 'Tarehe/Saa: ' . ($appointment['scheduled_start'] ?? 'TBD');
        if (!empty($appointment['department'])) {
            $parts[] = 'Idara: ' . $appointment['department'];
        }
        if (!empty($appointment['provider_name'])) {
            $parts[] = 'Mtoa huduma: ' . $appointment['provider_name'];
        }
        if (!empty($appointment['location'])) {
            $parts[] = 'Mahali: ' . $appointment['location'];
        }
        $parts[] = 'Tupo haka kwako. Jibu HELP kwa mwongozo wa afya au DOCTOR kwa mawasiliano ya moja kwa moja na hospitali.';
    } else {
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
        $parts[] = 'We are here for you. Reply HELP for health guidance or DOCTOR for direct hospital contact.';
    }
    
    return implode("\n", $parts);
}

function build_appointment_change_message(string $patientName, array $appointment, string $reason, bool $isUpdate, string $lang = 'en'): string
{
    $parts = [];
    
    if ($lang === 'sw') {
        if ($isUpdate) {
            $parts[] = "Habari {$patientName}, miadi yako katika " . HOSPITAL_NAME . " imebadilishwa.";
        } else {
            $parts[] = "Habari {$patientName}, miadi yako katika " . HOSPITAL_NAME . " imepangwa.";
        }
        $parts[] = 'Tarehe/Saa: ' . ($appointment['scheduled_start'] ?? 'TBD');
        if (!empty($appointment['scheduled_end'])) {
            $parts[] = 'Wakati wa mwisho: ' . $appointment['scheduled_end'];
        }
        if (!empty($appointment['department'])) {
            $parts[] = 'Idara: ' . $appointment['department'];
        }
        if (!empty($appointment['provider_name'])) {
            $parts[] = 'Mtoa huduma: ' . $appointment['provider_name'];
        }
        if (!empty($appointment['location'])) {
            $parts[] = 'Mahali: ' . $appointment['location'];
        }
        $parts[] = 'Sababu: ' . $reason;
        $parts[] = 'Tupo hapa kwako. Jibu HELP kwa mwongozo wa afya au DOCTOR kwa msaada wa hospitali.';
    } else {
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
        $parts[] = 'We are here for you. Reply HELP for health guidance or DOCTOR for direct hospital contact.';
    }
    
    return implode("\n", $parts);
}

function build_engagement_menu_message(string $lang = 'en'): string
{
    return build_help_menu_message($lang);
}

function build_appointment_reminder_message(
    string $patientName,
    array $appointment,
    string $reason,
    int $reminderNumber,
    int $totalReminders = 3,
    string $lang = 'en',
    string $reminderKind = ''
): string {
    if (in_array($reminderKind, ['7d', '3d', 'night', '1d'], true)) {
        return build_afya_appointment_reminder($reminderKind, $patientName, $appointment, $lang);
    }

    if ($lang === 'sw') {
        $prefix = $reminderNumber === 1 ? 'Maelezo ya miadi' : ('Ukumbusho wa miadi ' . $reminderNumber . '/' . $totalReminders);
        $parts = [];
        $parts[] = "Habari {$patientName}, {$prefix} kutoka " . HOSPITAL_NAME . ".";
        $parts[] = 'Tarehe/Saa: ' . ($appointment['scheduled_start'] ?? 'TBD');
        if (!empty($appointment['department'])) {
            $parts[] = 'Idara: ' . $appointment['department'];
        }
        if (!empty($appointment['provider_name'])) {
            $parts[] = 'Mtoa huduma: ' . $appointment['provider_name'];
        }
        if (!empty($appointment['location'])) {
            $parts[] = 'Mahali: ' . $appointment['location'];
        }
        if ($reason !== '') {
            $parts[] = 'Sababu: ' . $reason;
        }
        $parts[] = 'Jibu HELP kwa mwongozo wa afya au DOCTOR kwa msaada wa hospitali.';
        return implode("\n", $parts);
    }
    
    $prefix = $reminderNumber === 1 ? 'Appointment details' : ('Appointment reminder ' . $reminderNumber . '/' . $totalReminders);
    $parts = [];
    $parts[] = "Hello {$patientName}, {$prefix} from " . HOSPITAL_NAME . ".";
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
    if ($reason !== '') {
        $parts[] = 'Reason: ' . $reason;
    }
    $parts[] = 'Reply HELP for health guidance or DOCTOR for direct hospital support.';
    return implode("\n", $parts);
}

/**
 * Random Engagement Messages - AI Generated to encourage interaction
 * Sends every 3 days to keep patients engaged and informed
 */
function get_random_engagement_messages(string $lang = 'en'): array
{
    if ($lang === 'sw') {
        return [
            'Afya yako ni muhimu. Endelea kujali mwili wako — lishe bora, usingizi, na maji ya kutosha husaidia.',
            'Umechukua hatua nzuri kwa kufuatilia afya yako. Tupo hapa ukihitaji msaada. Jibu HELP kwa maswali.',
            'Kumbuka: uchunguzi wa mara kwa mara husaidia kulinda afya yako. HPV ni jambo la kawaida — ufuatiliaji husaidia.',
            'Tembea kidogo, kula vizuri, na pumzika — mambo madogo yanaimarisha afya.',
            'Wewe si peke yako. Afya Rafiki iko pamoja nawe katika safari hii.',
        ];
    }

    return [
        'Your health matters. Keep caring for yourself — good food, rest, and water all help.',
        'You have taken a good step by following up on your health. Reply HELP if you have a question.',
        'Regular screening helps protect your health. HPV is very common — follow-up care makes a difference.',
        'A short walk, healthy meals, and rest can support your wellbeing.',
        'You are not alone. Afya Rafiki is with you on this path.',
    ];
}

/**
 * Check if patient needs engagement message today
 * Returns true if 3+ days have passed since last engagement message
 */
function should_send_engagement_message(int $patientId): bool
{
    $st = db()->prepare(
        'SELECT MAX(created_at) as last_sent
         FROM outbound_messages
         WHERE patient_id = ? AND message_type = ?'
    );
    $st->execute([$patientId, 'engagement_boost']);
    $row = $st->fetch();
    
    if (!$row || !$row['last_sent']) {
        return true;
    }
    
    $lastSent = strtotime($row['last_sent']);
    $now = time();
    $daysSince = ($now - $lastSent) / (24 * 3600);
    
    return $daysSince >= 3;
}

/**
 * Send random engagement message to patient (every 3+ days). Returns true if sent.
 */
function send_random_engagement_message(int $patientId): bool
{
    require_once __DIR__ . '/hpv_results.php';
    if (!hpv_counseling_pathway_complete($patientId)) {
        return false;
    }
    if (!should_send_engagement_message($patientId)) {
        return false;
    }

    $body = build_engagement_boost_message($patientId);
    return send_patient_message($patientId, 'engagement_boost', $body);
}

/**
 * Build a warm HPV-focused engagement message (Groq when available, else curated tips).
 */
function build_engagement_boost_message(int $patientId): string
{
    $lang = get_patient_language($patientId);

    $st = db()->prepare('SELECT full_name FROM patients WHERE id = ? LIMIT 1');
    $st->execute([$patientId]);
    $name = (string) ($st->fetch()['full_name'] ?? '');

    if (file_exists(__DIR__ . '/openai_assistant.php')) {
        require_once __DIR__ . '/openai_assistant.php';
        if (function_exists('ai_engagement_reply')) {
            $ai = ai_engagement_reply($name, $lang);
            if ($ai['ok'] && strlen(trim($ai['reply'])) > 25) {
                return trim($ai['reply']);
            }
        }
    }

    $messages = get_random_engagement_messages($lang);
    return $messages[array_rand($messages)];
}

function send_appointment_bundle_messages(
    int $patientId,
    string $patientName,
    array $appointment,
    string $reason,
    bool $isUpdate
): void
{
    $lang = get_patient_language($patientId);
    
    // Booking confirmation only; 7d / 3d / night-before reminders are sent by cron_run_reminders.php.
    send_patient_message(
        $patientId,
        'appointment_reminder',
        build_appointment_change_message($patientName, $appointment, $reason, $isUpdate, $lang)
    );
}
