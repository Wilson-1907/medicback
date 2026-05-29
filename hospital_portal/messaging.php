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
        error_log("SEND_PATIENT_MESSAGE FAILED: No contact channel found for patient $patientId (message: '$messageType')");
        return;
    }

    $channel = (string) $contact['channel'];
    $address = (string) $contact['address'];
    
    error_log("SEND_PATIENT_MESSAGE: Patient=$patientId, Channel=$channel, Address=$address, Type=$messageType");

    $outboundId = log_outbound_message($patientId, $channel, $messageType, $body);
    $result = africastalking_send($channel, $address, $body);

    error_log("AFRICASTALKING_RESULT: outboundId=$outboundId, ok=" . ($result['ok'] ? 'true' : 'false') . ", error=" . ($result['error'] ?? 'none'));

    if ($result['ok']) {
        update_outbound_status($outboundId, 'sent', $result['message_id'], null);
        return;
    }
    update_outbound_status($outboundId, 'failed', $result['message_id'], $result['error']);
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

/**
 * AFYA RAFIKI - Welcome message with full intro
 */
function build_welcome_message(string $patientName, string $lang = 'en'): string
{
    if ($lang === 'sw') {
        return "Karibu kwenye Afya Rafiki, {$patientName}! 🏥\n"
            . "Tuko hapa kukusaidia baada ya majibu yako ya uchaguzi wa saratani ya Kila mwezi.\n\n"
            . "Huduma hii itakutumia:\n"
            . "✓ Taarifa za afya kamili\n"
            . "✓ Vikumbusho vya miadi\n"
            . "✓ Mwongozo wa huduma ya ufuatiliaji\n\n"
            . "Taarifa zako zitahifadhiwa Kwa siri. Tupo hapa kwako!\n"
            . "Jibu HELP kwa huduma zaidi.";
    }
    
    return "Welcome to Afya Rafiki, {$patientName}! 🏥\n"
        . "We are here to support you after your screening results.\n\n"
        . "This service will provide:\n"
        . "✓ Health information\n"
        . "✓ Appointment reminders\n"
        . "✓ Follow-up care guidance\n\n"
        . "Your information will remain confidential. We are here for you!\n"
        . "Reply HELP for more support.";
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
    if ($lang === 'sw') {
        return "Kuendelea na huduma yako katika " . HOSPITAL_NAME . ":\n"
            . "1) Dalili za onyo za afya\n"
            . "2) Vidokezo vya kujaga afya\n"
            . "3) Msaada wa miadi\n"
            . "4) Sema na timu ya hospitali";
    }
    
    return "Stay active with your care at " . HOSPITAL_NAME . ":\n"
        . "1) Health warning signs\n"
        . "2) Prevention tips\n"
        . "3) Appointment help\n"
        . "4) Talk to hospital team";
}

function build_appointment_reminder_message(
    string $patientName,
    array $appointment,
    string $reason,
    int $reminderNumber,
    int $totalReminders = 3,
    string $lang = 'en'
): string {
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
            "Habari! 👋 Tunakamatiana na wewe. Je, unajisikia vizuri? Tupo hapa ikiwa una maswali au haja ya msaada. Jibu HELP.",
            "💪 Dakika 5 ya stretching kila asubuhi inaweza kuboresha afya yako. Je, unajaribu? Tusifu za kujaza! 🌟",
            "🥗 Kula vyakula vya kumata kuna chuma na vitamini. Hii husaidia katika kujaga macho na afya. Unakula vizuri?",
            "😴 Usingizi wa saa 7-8 kila usiku ni muhimu. Je, unajipata usingizi wa kutosha? Jibu naye.",
            "🚶 Tengeneza wakati wa kutembea kila siku. Hii inaboresha moyo na akili. Karibu kusambaza mafanikio!",
            "💧 Kunua maji mengi (lita 8-10) kila siku inaboresha ndoto. Je, unakumbuka kunua?",
            "🌞 Jua la asubuhi linaboresha vitamin D. Njia nzuri na salama. Kusimama majumbani kwa dakika 15-20?",
            "😊 Kujaza akili kwa ujinga ni vyema. Je, una filamu, kitabu au muziki unayopenda? Fanya hivi leo!",
            "❤️ Afya yako ni muhimu sana kwetu. Tupo hapa wakati wowote. Lolote ulilowajua, karibu sana kuuliza.",
            "🎯 Kuwa na madhumuni mazuri kila siku kunaboresha moyo. Nini madhumuni yako kwa kila siku?",
        ];
    }
    
    return [
        "Hi there! 👋 We're checking in on you. How are you feeling? We're here to help. Reply HELP if you need anything.",
        "💪 Just 5 minutes of stretching every morning can boost your health. How about trying today? You've got this! 🌟",
        "🥗 Eating nutrient-rich foods strengthens your immunity. What's your favorite healthy meal?",
        "😴 Getting 7-8 hours of sleep is key to wellness. Are you getting enough rest? Let us know!",
        "🚶 Take a 20-minute walk daily—it's great for your heart and mind. Share your progress with us!",
        "💧 Drinking 8-10 glasses of water daily keeps you hydrated and healthy. Remember to drink up today!",
        "🌞 Morning sunlight boosts vitamin D naturally. Spend 15-20 minutes outside safely. Feel the difference!",
        "😊 It's okay to take mental breaks. Watch something you love, read a book, or listen to music today!",
        "❤️ Your health matters to us. We're here anytime you need support. Ask us anything—no question is too small.",
        "🎯 Set one small health goal for today and celebrate it! What will it be? Share with us!",
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
 * Send random engagement message to patient
 */
function send_random_engagement_message(int $patientId): void
{
    if (!should_send_engagement_message($patientId)) {
        return;
    }
    
    $lang = get_patient_language($patientId);
    $messages = get_random_engagement_messages($lang);
    $randomMessage = $messages[array_rand($messages)];
    
    send_patient_message($patientId, 'engagement_boost', $randomMessage);
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
    
    send_patient_message(
        $patientId,
        'appointment_reminder',
        build_appointment_change_message($patientName, $appointment, $reason, $isUpdate, $lang)
    );
    send_patient_message(
        $patientId,
        'appointment_reminder',
        build_appointment_reminder_message($patientName, $appointment, $reason, 2, 3, $lang)
    );
    send_patient_message(
        $patientId,
        'appointment_reminder',
        build_appointment_reminder_message($patientName, $appointment, $reason, 3, 3, $lang)
    );
    send_patient_message($patientId, 'education_menu', build_engagement_menu_message($lang));
}
