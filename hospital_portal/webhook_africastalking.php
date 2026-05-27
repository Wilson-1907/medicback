<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/openai_assistant.php';

/**
 * Africa's Talking inbound webhook handler.
 * Configure this URL in AT dashboard for both SMS and WhatsApp callbacks.
 */
header('Content-Type: text/plain; charset=UTF-8');

function request_payload(): array
{
    $payload = [];

    foreach ([$_GET, $_POST] as $source) {
        foreach ($source as $k => $v) {
            $payload[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }
    }

    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            foreach ($json as $k => $v) {
                $payload[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
            }
        } else {
            parse_str($raw, $formPairs);
            if (is_array($formPairs)) {
                foreach ($formPairs as $k => $v) {
                    $payload[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
                }
            }
        }
    }

    return $payload;
}

function payload_value(array $payload, array $keys): string
{
    $lower = [];
    foreach ($payload as $k => $v) {
        $lower[strtolower((string) $k)] = trim((string) $v);
    }
    foreach ($keys as $k) {
        $v = $lower[strtolower($k)] ?? '';
        if ($v !== '') {
            return $v;
        }
    }
    return '';
}

function channel_from_payload(array $payload): string
{
    $channel = strtolower(payload_value($payload, ['channel']));
    if ($channel === 'whatsapp') {
        return 'whatsapp';
    }
    $to = strtolower(payload_value($payload, ['to', 'toNumber', 'recipient']));
    if (str_contains($to, 'whatsapp')) {
        return 'whatsapp';
    }
    return 'sms';
}

function normalize_inbound_phone(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if ($raw[0] === '+') {
        return '+' . preg_replace('/\D+/', '', substr($raw, 1));
    }
    return '+' . preg_replace('/\D+/', '', $raw);
}

function find_patient_by_phone(string $phone): ?array
{
    if ($phone === '') {
        return null;
    }
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    $st = db()->prepare(
        'SELECT p.id, p.full_name, p.preferred_language
         FROM contact_channels c
         INNER JOIN patients p ON p.id = c.patient_id
         WHERE c.opted_in = 1
            AND (c.address = ?
             OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(c.address, \'+\', \'\'), \' \', \'\'), \'-\', \'\'), \'(\', \'\'), \')\', \'\') = ?)
         ORDER BY c.is_primary DESC, c.id ASC
         LIMIT 1'
    );
    $st->execute([$phone, $digits]);
    $row = $st->fetch();
    return $row ?: null;
}

function save_inbound(?int $patientId, string $channel, string $from, string $body, array $payload): void
{
    $st = db()->prepare(
        'INSERT INTO inbound_messages (patient_id, channel, from_address, body, raw_payload)
         VALUES (?,?,?,?,?)'
    );
    $st->execute([$patientId, $channel, $from, $body, json_encode($payload)]);
}

function upsert_escalation(int $patientId, string $reason): void
{
    $st = db()->prepare(
        'INSERT INTO escalations (patient_id, reason, urgency, status)
         VALUES (?,?,?,?)'
    );
    $st->execute([$patientId, $reason, 'same_day', 'open']);
}

function send_unlinked_reply(string $channel, string $to, string $body): void
{
    if ($to === '') {
        return;
    }
    africastalking_send($channel, $to, $body);
}

$payload = request_payload();
$from = normalize_inbound_phone(payload_value($payload, ['from', 'fromNumber', 'source', 'sender']));
$body = payload_value($payload, ['text', 'message', 'body', 'content']);
$channel = channel_from_payload($payload);
$patient = find_patient_by_phone($from);
$patientId = $patient ? (int) $patient['id'] : null;
$lang = $patient ? ((string) $patient['preferred_language'] ?: 'en') : 'en';

save_inbound($patientId, $channel, $from, $body, $payload);

if ($body === '') {
    echo 'OK';
    exit;
}

if (!$patientId) {
    send_unlinked_reply(
        $channel,
        $from,
        'Hi. To help you with PHV updates, please register your number with the hospital first. If this is urgent, contact the hospital directly.'
    );
    echo 'OK';
    exit;
}

$msg = strtoupper($body);
if (in_array($msg, ['HI', 'HELLO', 'HEY', 'MAMBO', 'SAWA'], true)) {
    if ($lang === 'sw') {
        send_patient_message(
            $patientId,
            'system',
            'Habari. Nini unataka kujua juu ya PHV leo? Unaweza kuuliza juu ya dalili, kuzuia, miadi, au jibu DOCTOR kwa msaada wa hospitali.'
        );
    } else {
        send_patient_message(
            $patientId,
            'system',
            'Hi. What do you want to know about PHV today? You can ask about signs, prevention, appointments, or reply DOCTOR for direct hospital support.'
        );
    }
    echo 'OK';
    exit;
}

if ($msg === 'HELP' || $msg === 'MENU' || $msg === '0') {
    send_patient_message($patientId, 'education_menu', build_engagement_menu_message($lang));
    echo 'OK';
    exit;
}

if ($msg === '1') {
    if ($lang === 'sw') {
        send_patient_message(
            $patientId,
            'system',
            'Dalili za PHV za kumangalia: dalili kali kwa ghafla, joto la juu, maumivu yanayodumu, kupumzika kupiga kelele, au kutoka kwa kawaida. '
            . 'Ikiwa dalili ni kali, tafuta huduma ya dharura mara moja.'
        );
    } else {
        send_patient_message(
            $patientId,
            'system',
            'PHV signs to watch: sudden severe symptoms, high fever, persistent pain, worsening breathing, or unusual bleeding. '
            . 'If symptoms are severe, seek emergency care immediately.'
        );
    }
    echo 'OK';
    exit;
}

if ($msg === '2') {
    if ($lang === 'sw') {
        send_patient_message(
            $patientId,
            'system',
            'Vidokezo vya kuzuia PHV: chukua dawa iliyoagizwa, kuendelea na kutembelea, eneza kwa maji, pumzika, na kuripoti dalili yoyote inayoongezwa mapema. '
            . 'Jibu HELP kwa chaguo zaidi.'
        );
    } else {
        send_patient_message(
            $patientId,
            'system',
            'PHV prevention tips: take prescribed medication, keep follow-up visits, stay hydrated, rest, and report any worsening signs early. '
            . 'Reply HELP for more options.'
        );
    }
    echo 'OK';
    exit;
}

if ($msg === 'DOCTOR' || $msg === '4') {
    upsert_escalation($patientId, 'Patient requested direct doctor contact via messaging channel.');
    if ($lang === 'sw') {
        send_patient_message(
            $patientId,
            'escalation_notice',
            'Asante. Ombi lako limetumwa kwa ' . HOSPITAL_NAME . '. Mwanachama wa timu ya huduma atakupigia simu hivi karibuni.'
        );
    } else {
        send_patient_message(
            $patientId,
            'escalation_notice',
            'Thank you. Your request has been sent to ' . HOSPITAL_NAME . '. A care team member will contact you shortly.'
        );
    }
    echo 'OK';
    exit;
}

if (str_contains($msg, 'PHV')) {
    if ($lang === 'sw') {
        send_patient_message(
            $patientId,
            'system',
            'PHV ni hali ya afya inayohitaji ufuatiliaji wenye karibu, kuripoti dalili mapema, na msaada wa kuzuia. '
            . 'Katika ' . HOSPITAL_NAME . ', tunakusaidia na ukumbusho wa miadi, dalili za onyo, na mwongozo wa vitendo vya kuzuia. '
            . 'Ikiwa unajisikia mbaya au una dalili kali, tafuta huduma ya dharura mara moja. Jibu DOCTOR kwa mawasiliano ya hospitali moja kwa moja.'
        );
    } else {
        send_patient_message(
            $patientId,
            'system',
            'PHV is a health condition that needs close follow-up, early symptom reporting, and prevention support. '
            . 'At ' . HOSPITAL_NAME . ', we help you with appointment reminders, warning signs, and practical prevention guidance. '
            . 'If you feel worse or have severe symptoms, seek urgent care immediately. Reply DOCTOR for direct hospital contact.'
        );
    }
    echo 'OK';
    exit;
}

$ai = ai_generate_reply($patientId, $channel, $body, $lang);
if ($ai['ok']) {
    send_patient_message($patientId, 'system', $ai['reply']);
    echo 'OK';
    exit;
}

if ($lang === 'sw') {
    send_patient_message(
        $patientId,
        'system',
        'Asante kwa ujumbe wako. Tupo hapa kwako. Jibu HELP kwa mwongozo wa PHV au DOCTOR kwa msaada wa hospitali.'
    );
} else {
    send_patient_message(
        $patientId,
        'system',
        'Thank you for your message. We are here for you. Reply HELP for PHV guidance or DOCTOR for direct hospital support.'
    );
}
echo 'OK';
