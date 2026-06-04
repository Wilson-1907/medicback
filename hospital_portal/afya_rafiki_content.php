<?php
declare(strict_types=1);

/**
 * Approved Afya Rafiki message content and conversational helpers.
 */
require_once __DIR__ . '/db.php';

function afya_rafiki_name(): string
{
    return defined('AFYA_RAFIKI_NAME') ? AFYA_RAFIKI_NAME : 'Afya Rafiki';
}

function afya_clinic_site(): string
{
    return defined('CLINIC_SITE_NAME') ? CLINIC_SITE_NAME : HOSPITAL_NAME;
}

function afya_lang(string $lang): string
{
    return $lang === 'sw' ? 'sw' : 'en';
}

/** First word of full name for friendly SMS greetings. */
function afya_first_name(string $fullName): string
{
    $fullName = trim($fullName);
    if ($fullName === '') {
        return '';
    }
    $parts = preg_split('/\s+/u', $fullName) ?: [];
    $first = trim((string) ($parts[0] ?? ''));
    return $first !== '' ? $first : $fullName;
}

function afya_format_appointment_date(?string $scheduledStart): string
{
    if ($scheduledStart === null || trim($scheduledStart) === '') {
        return afya_lang('en') === 'sw' ? 'Tarehe iliyopangwa' : 'your scheduled date';
    }
    $ts = strtotime($scheduledStart);
    if ($ts === false) {
        return $scheduledStart;
    }
    return date('l, j M Y', $ts) . ' ' . date('g:i A', $ts);
}

/** Initial welcome when HPV positive pathway is activated (nurse confirms result). */
function build_welcome_message(string $patientName, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    if ($lang === 'sw') {
        return 'Karibu kwenye Afya Rafiki. Tuko hapa kukusaidia baada ya majibu yako ya uchunguzi wa HPV. '
            . 'Huduma hii itakutumia taarifa za afya, vikumbusho, na mwongozo wa huduma ya ufuatiliaji. '
            . 'Taarifa zako zitahifadhiwa kwa siri.';
    }
    return 'Hello. Welcome to Afya Rafiki. We are here to support you after your HPV screening results. '
        . 'This service will provide health information, reminders, and guidance for your follow-up care. '
        . 'Your information will remain confidential.';
}

/** HIV status from registration (for HPV-negative result SMS: 3 vs 5 year return). */
function afya_patient_hiv_status(int $patientId): string
{
    try {
        $st = db()->prepare('SELECT hiv_status FROM patients WHERE id = ? LIMIT 1');
        $st->execute([$patientId]);
        $status = (string) ($st->fetchColumn() ?: 'negative');
        return $status === 'positive' ? 'positive' : 'negative';
    } catch (Throwable $e) {
        return 'negative';
    }
}

/** Next booked follow-up date for SMS templates, or blank line for staff to fill. */
function afya_next_appointment_display(int $patientId): string
{
    try {
        $st = db()->prepare(
            "SELECT scheduled_start FROM appointments
             WHERE patient_id = ? AND status IN ('proposed','confirmed')
             ORDER BY scheduled_start ASC LIMIT 1"
        );
        $st->execute([$patientId]);
        $row = $st->fetch();
        if ($row && !empty($row['scheduled_start'])) {
            return afya_format_appointment_date((string) $row['scheduled_start']);
        }
    } catch (Throwable $e) {
        error_log('afya_next_appointment_display: ' . $e->getMessage());
    }
    return '__________';
}

/** Official HPV negative result SMS (HIV status sets 3-year vs 5-year return). */
function build_hpv_negative_result_notification(string $patientName, string $hivStatus, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    $site = afya_clinic_site();
    $hello = $lang === 'sw'
        ? ($name !== '' ? "Habari {$name}," : 'Habari,')
        : ($name !== '' ? "Hello {$name}," : 'Hello,');
    $hivPositive = $hivStatus === 'positive';

    if ($lang === 'sw') {
        if ($hivPositive) {
            return "{$hello}\nMajibu yako ya HPV ni hasi (negative). Hii inamaanisha kuwa hakuna maambukizi ya HPV yaliyopatikana kwa sasa. "
                . "Ili kuendelea kulinda afya yako, tafadhali rudi {$site} kwa uchunguzi mwingine wa virusi vya HPV baada ya miaka 3 "
                . 'au mapema zaidi ikiwa utaelekezwa na mhudumu wa afya.'
                . "\nAsante kwa kutumia Afya Rafiki.";
        }
        return "{$hello}\nMajibu yako ya HPV ni hasi (negative). Hii inamaanisha kuwa hakuna maambukizi ya HPV yaliyopatikana kwa sasa. "
            . "Ili kudumisha afya nzuri ya mlango wa kizazi, tafadhali rudi {$site} kwa uchunguzi mwingine wa saratani ya mlango wa kizazi baada ya miaka 5 "
            . 'au mapema zaidi ikiwa utaelekezwa na mhudumu wa afya.'
            . "\nAsante kwa kutumia Afya Rafiki.";
    }

    if ($hivPositive) {
        return "{$hello}\nYour HPV test result is negative. This means no HPV infection was detected at this time. "
            . "To continue protecting your health, please return to {$site} for repeat cervical cancer screening after 3 years, "
            . 'or earlier if advised by your healthcare provider.'
            . "\nThank you for choosing Afya Rafiki.";
    }

    return "{$hello}\nYour HPV test result is negative. This means no HPV infection was detected at this time. "
        . "To maintain good cervical health, please return to {$site} for repeat cervical cancer screening after 5 years, "
        . 'or earlier if advised by your healthcare provider.'
        . "\nThank you for choosing Afya Rafiki.";
}

/** Official HPV positive result SMS with follow-up appointment date. */
function build_hpv_positive_result_notification(string $patientName, string $appointmentDate, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    $site = afya_clinic_site();
    $hello = $lang === 'sw'
        ? ($name !== '' ? "Habari {$name}," : 'Habari,')
        : ($name !== '' ? "Hello {$name}," : 'Hello,');

    if ($lang === 'sw') {
        return "{$hello}\nMajibu yako ya kipimo cha HPV ni chanya (positive). Hii haimaanishi kuwa una saratani ya mlango wa kizazi. "
            . 'Inamaanisha kuwa virusi vya HPV vimepatikana na unahitaji huduma zaidi ya ufuatiliaji ili kulinda afya yako na kusaidia kuzuia saratani ya mlango wa kizazi.'
            . "\nUmepangiwa miadi ya ufuatiliaji katika {$site} tarehe:\nTarehe: {$appointmentDate}"
            . "\nTafadhali hudhuria miadi yako kama ulivyopangiwa. Ikiwa una maswali yoyote, Afya Rafiki iko hapa kukusaidia."
            . "\nAsante kwa kutumia Afya Rafiki.";
    }

    return "{$hello}\nYour HPV test result is positive. This does not mean that you have cervical cancer. "
        . 'It means that the HPV virus was detected and further follow-up is needed to help protect your health and prevent cervical cancer.'
        . "\nYou have been scheduled for a follow-up appointment at {$site} on:\nDate: {$appointmentDate}"
        . "\nPlease attend your appointment as scheduled. If you have any questions, Afya Rafiki is here to support you."
        . "\nThank you for choosing Afya Rafiki.";
}

/** Written consent signed at registration — no SMS opt-in question. */
function record_registration_consent(int $patientId, string $channel): void
{
    if (patient_has_confirmed_consent($patientId)) {
        return;
    }
    $pdo = db();
    $ev = $pdo->prepare(
        'INSERT INTO contact_preference_events (patient_id, channel, action, source)
         VALUES (?,?,?,?)'
    );
    $ev->execute([$patientId, $channel, 'confirm_double_opt_in', 'registration_signed']);
}

function build_consent_thank_you_message(string $patientName, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    if ($lang === 'sw') {
        $greet = $name !== '' ? "Asante sana {$name}." : 'Asante sana.';
        return "{$greet} Tunashukuru kwa kukubali kupokea ujumbe kutoka Afya Rafiki. "
            . 'Matokeo yako ya uchunguzi wa HPV yatatumwa kwako hapa mara tu yatakapothibitishwa na kliniki. '
            . 'Tuko hapa kukusaidia.';
    }
    $greet = $name !== '' ? "Thank you {$name}." : 'Thank you.';
    return "{$greet} We appreciate you agreeing to receive messages from Afya Rafiki. "
        . 'Your HPV screening results will be sent to you here as soon as they are confirmed by the clinic. '
        . 'We are here to support you.';
}

/** Neutral encouragement before result is known (positive or negative). */
function build_random_generic_encouragement(string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $pool = $lang === 'sw' ? [
        'Afya yako ni muhimu. Endelea kujali mwili wako — lishe bora, usingizi, na maji ya kutosha husaidia.',
        'Umechukua hatua nzuri kwa kufuatilia afya yako. Tupo hapa ukihitaji msaada.',
        'Kumbuka: uchunguzi wa mara kwa mara husaidia kulinda afya yako. Jibu HELP ikiwa una swali.',
        'Pole na safari yako ya afya — kila hatua ina maana. Tunakutakia nguvu.',
        'Epuka wasiwasi kupita kiasi. Subiri matokeo kutoka kliniki; tutakujulisha hapa.',
        'Tembea kidogo, kula vizuri, na pumzika — mambo madogo yanaimarisha afya.',
        'Wewe si peke yako. Afya Rafiki iko pamoja nawe katika safari hii.',
        'Ikiwa una wasiwasi, wasiliana na kliniki yako au jibu DOCTOR.',
    ] : [
        'Your health matters. Keep caring for yourself — good food, rest, and water all help.',
        'You have taken a good step by following up on your health. We are here if you need support.',
        'Remember: regular screening helps protect your health. Reply HELP if you have a question.',
        'Be gentle with yourself on this journey — every step counts. We wish you strength.',
        'Try not to worry too much. Wait for your results from the clinic; we will update you here.',
        'A short walk, healthy meals, and rest can support your wellbeing.',
        'You are not alone. Afya Rafiki is with you on this path.',
        'If you are worried, contact your clinic or reply DOCTOR.',
    ];
    return $pool[array_rand($pool)];
}

/** @deprecated Use build_hpv_negative_result_notification or build_hpv_positive_result_notification */
function build_hpv_result_notification(string $patientName, string $result, string $lang = 'en'): string
{
    if ($result === 'positive') {
        return build_hpv_positive_result_notification($patientName, '__________', $lang);
    }
    return build_hpv_negative_result_notification($patientName, 'negative', $lang);
}

function build_consent_message(string $lang = 'en'): string
{
    // Consent is captured on paper before registration.
    // Keep this function for backward compatibility but never send consent prompts.
    return '';
}

/** @return list<string> Positive HPV pathway — 16 official counseling messages. */
function afya_counseling_messages_positive(string $lang = 'en'): array
{
    require_once __DIR__ . '/afya_counseling_positive.php';
    return afya_lang($lang) === 'sw'
        ? afya_counseling_messages_positive_sw()
        : afya_counseling_messages_positive_en();
}

function patient_has_confirmed_consent(int $patientId): bool
{
    $st = db()->prepare(
        "SELECT 1 FROM contact_preference_events
         WHERE patient_id = ? AND action = 'confirm_double_opt_in'
         LIMIT 1"
    );
    $st->execute([$patientId]);
    return (bool) $st->fetchColumn();
}

function patient_awaiting_consent(int $patientId): bool
{
    $st = db()->prepare(
        'SELECT opted_in FROM contact_channels WHERE patient_id = ? AND is_primary = 1 LIMIT 1'
    );
    $st->execute([$patientId]);
    $opted = (int) ($st->fetchColumn() ?: 0);
    if ($opted !== 1) {
        return false;
    }
    return !patient_has_confirmed_consent($patientId);
}

function record_consent_yes(int $patientId, string $channel): void
{
    $pdo = db();
    $ev = $pdo->prepare(
        'INSERT INTO contact_preference_events (patient_id, channel, action, source)
         VALUES (?,?,?,?)'
    );
    $ev->execute([$patientId, $channel, 'confirm_double_opt_in', 'sms_whatsapp_reply']);
}

function record_consent_no(int $patientId, string $channel): void
{
    $pdo = db();
    $pdo->prepare(
        'UPDATE contact_channels SET opted_in = 0 WHERE patient_id = ?'
    )->execute([$patientId]);
    $ev = $pdo->prepare(
        'INSERT INTO contact_preference_events (patient_id, channel, action, source)
         VALUES (?,?,?,?)'
    );
    $ev->execute([$patientId, $channel, 'opt_out', 'sms_whatsapp_reply']);
}

function is_consent_yes_reply(string $body): bool
{
    $msg = strtoupper(trim($body));
    return in_array($msg, ['1', 'YES', 'NDIO', 'NDIYO', 'OK', 'SAWA'], true);
}

function is_consent_no_reply(string $body): bool
{
    $msg = strtoupper(trim($body));
    return in_array($msg, ['2', 'NO', 'HAPANA'], true);
}

/** Negative HPV: one official result SMS only (no drip counseling). */
function afya_counseling_messages_negative(string $lang = 'en'): array
{
    return [];
}

function get_next_counseling_message(int $patientId, string $lang = 'en'): ?string
{
    if (!function_exists('get_counseling_message_at_index')) {
        require_once __DIR__ . '/hpv_results.php';
    }
    if (function_exists('hpv_counseling_pathway_complete') && hpv_counseling_pathway_complete($patientId)) {
        return null;
    }
    $index = function_exists('get_hpv_counseling_index') ? get_hpv_counseling_index($patientId) : 0;
    return get_counseling_message_at_index($patientId, $index, $lang);
}

function build_reminder_7d_message(string $patientName, array $appointment, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $site = afya_clinic_site();
    $date = afya_format_appointment_date($appointment['scheduled_start'] ?? null);
    if ($lang === 'sw') {
        return "Kikumbusho kutoka Afya Rafiki: Una miadi (Clinic follow up) ya ufuatiliaji wiki ijayo ({$date}) katika {$site}. "
            . 'Kuhudhuria huduma ya ufuatiliaji ni muhimu kwa afya yako.';
    }
    return "Reminder from Afya Rafiki: You have a follow-up appointment scheduled next week ({$date}) at {$site}. "
        . 'Attending follow-up care is important for your health.';
}

function build_reminder_3d_message(string $patientName, array $appointment, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $site = afya_clinic_site();
    $date = afya_format_appointment_date($appointment['scheduled_start'] ?? null);
    if ($lang === 'sw') {
        return "Kikumbusho kutoka Afya Rafiki: Una miadi (Clinic follow up) ya ufuatiliaji ({$date}) katika {$site}. "
            . 'Kuhudhuria huduma ya ufuatiliaji ni muhimu kwa afya yako.';
    }
    return "Reminder from Afya Rafiki: You have a follow-up appointment scheduled on ({$date}) at {$site}. "
        . 'Attending follow-up care is important for your health.';
}

function build_reminder_1d_message(string $patientName = '', string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $site = afya_clinic_site();
    if ($lang === 'sw') {
        return "Kikumbusho: Ziara yako ya ufuatiliaji kliniki {$site} ni kesho. "
            . 'Tafadhali hudhuria kama ulivyopangiwa au wasiliana na kliniki ikiwa unahitaji msaada.';
    }
    return "Reminder: Your clinic follow-up visit at {$site} is tomorrow. "
        . 'Please attend as scheduled or contact the facility if you need assistance.';
}

/** Cron-scheduled reminder: 7d, 3d, or night (night-before / 1-day). */
function build_afya_appointment_reminder(
    string $kind,
    string $patientName,
    array $appointment,
    string $lang = 'en'
): string {
    return match ($kind) {
        '7d' => build_reminder_7d_message($patientName, $appointment, $lang),
        '3d' => build_reminder_3d_message($patientName, $appointment, $lang),
        'night', '1d' => build_reminder_1d_message($patientName, $lang),
        default => build_reminder_3d_message($patientName, $appointment, $lang),
    };
}

function build_help_menu_message(string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    if ($lang === 'sw') {
        return "Afya Rafiki — chaguo:\n"
            . "1) HPV ni nini?\n"
            . "2) Je, nina saratani ya mlango wa kizazi?\n"
            . "3) HPV inatibika?\n"
            . "4) Miadi / kupanga upya\n"
            . "5) Dalili za HPV\n"
            . "6) Dalili za saratani ya mlango wa kizazi\n"
            . "7) Ongea na mhudumu wa afya (DOCTOR)\n"
            . 'Andika swali lako au namba ya chaguo.';
    }
    return "Afya Rafiki — options:\n"
        . "1) What is HPV?\n"
        . "2) Do I have cervical cancer?\n"
        . "3) Can HPV be treated?\n"
        . "4) Appointments / reschedule help\n"
        . "5) Symptoms of HPV\n"
        . "6) Symptoms of cervical cancer\n"
        . "7) Speak to a provider (reply DOCTOR)\n"
        . 'Type your question or option number.';
}

function build_escalation_reply(string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    if ($lang === 'sw') {
        return 'Asante kwa swali lako. Mhudumu wa afya ataweza kukusaidia vizuri zaidi. '
            . 'Tafadhali wasiliana na kliniki yako au subiri simu kutoka kwa mhudumu wa afya.';
    }
    return 'Thank you for your question. A healthcare provider will be better able to assist you. '
        . 'Please contact your clinic or wait for a provider follow-up call.';
}

/** After patient replies DOCTOR / DAKTARI — ask why in their own words. */
function build_doctor_reason_request_prompt(string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    if ($lang === 'sw') {
        return 'Asante ' . AFYA_RAFIKI_NAME . '. Tungependa kukusaidia. '
            . 'Tafadhali andika kwa ufupi kwa nini ungependa kuongea na mhudumu wa afya '
            . '(mfano: maumivu, wasiwasi kuhusu matokeo, au kupanga miadi).';
    }
    return 'Thanks for reaching out to ' . AFYA_RAFIKI_NAME . '. We would like to help you. '
        . 'Please reply in a short message with why you would like to speak with a health specialist '
        . '(for example: pain, worry about your results, or booking a visit).';
}

function build_doctor_reason_reminder_prompt(string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    if ($lang === 'sw') {
        return 'Tunasubiri ujumbe mfupi ukielezea kwa nini ungependa kuongea na mhudumu wa afya. '
            . 'Andika kwa maneno yako — mfano: maumivu au wasiwasi.';
    }
    return 'We are still waiting for a short message explaining why you would like to speak with a health specialist. '
        . 'Please reply in your own words — for example pain or a concern about your results.';
}

function build_doctor_reason_received_ack(string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    if ($lang === 'sw') {
        return 'Asante — tumepokea ujumbe wako. Mhudumu wa afya atajaribu kuwasiliana nawe hivi karibuni. '
            . 'Ikiwa hali yako ni dharura, nenda kliniki mara moja.';
    }
    return 'Thank you — we have received your message. A health specialist will try to reach you soon. '
        . 'If this is an emergency, please go to the clinic right away.';
}

function build_doctor_request_already_logged_ack(string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    if ($lang === 'sw') {
        return 'Tayari tumepokea ombi lako la kuongea na mhudumu wa afya. Tafadhali subiri simu au ujumbe kutoka kwao.';
    }
    return 'We already have your request to speak with a health specialist. Please wait for them to contact you.';
}

function build_missed_appointment_message(string $patientName, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    $hello = $lang === 'sw'
        ? ($name !== '' ? "Habari {$name}," : 'Habari,')
        : ($name !== '' ? "Hello {$name}," : 'Hello,');
    if ($lang === 'sw') {
        return "{$hello}\nTumeona huenda hukuhudhuria miadi yako ya ufuatiliaji kama ulivyopangiwa. "
            . 'Huduma ya ufuatiliaji ni muhimu katika kulinda afya yako na kusaidia kuzuia saratani ya mlango wa kizazi.'
            . "\nTafadhali tujulishe ni nini kilikuzuia kuhudhuria miadi yako."
            . "\nJibu kwa nambari inayofaa zaidi:\n"
            . "1. Changamoto za usafiri\n2. Nilisahau miadi\n3. Hofu au wasiwasi kuhusu matokeo au matibabu\n"
            . "4. Majukumu ya kazi au familia\n5. Nilikuwa mgonjwa\n6. Nilifika hospitalini lakini sikuhudumiwa\n7. Sababu nyingine";
    }
    return "{$hello}\nWe noticed that you may have missed your scheduled follow-up appointment. "
        . 'Follow-up care is important to help protect your health and prevent cervical cancer.'
        . "\nCould you tell us what prevented you from attending?"
        . "\nReply with the number that best describes your situation:\n"
        . "1. Transport challenges\n2. Forgot the appointment\n3. Fear or concern about results or treatment\n"
        . "4. Work or family commitments\n5. I was unwell\n6. I attended but was not seen\n7. Other reason";
}

function build_missed_appointment_reschedule_offer(string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $site = afya_clinic_site();
    if ($lang === 'sw') {
        return "Asante kwa majibu yako.\nTungependa kukusaidia kuendelea na huduma yako ya ufuatiliaji. "
            . "Je, ungependa kupanga upya miadi yako katika {$site}?\n"
            . "Jibu:\n1. NDIO - Nipangie miadi nyingine\n2. HAPANA - Nitawasiliana na kliniki mwenyewe\n3. Ningependa kuzungumza na mhudumu wa afya";
    }
    return "Thank you for your response.\nWe would like to help you continue your follow-up care. "
        . "Would you like to reschedule your appointment at {$site}?\n"
        . "Reply:\n1. YES - Reschedule my appointment\n2. NO - I will contact the clinic myself\n3. I need to speak with a healthcare provider";
}

function build_post_visit_acknowledgement(string $patientName, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    $hello = $lang === 'sw'
        ? ($name !== '' ? "Habari {$name}," : 'Habari,')
        : ($name !== '' ? "Hello {$name}," : 'Hello,');
    if ($lang === 'sw') {
        return "{$hello}\nAsante kwa kuhudhuria miadi yako ya ufuatiliaji kama ulivyopangiwa. "
            . 'Umechukua hatua muhimu katika kulinda afya yako na kuzuia saratani ya mlango wa kizazi.'
            . "\nKwa kuhudhuria miadi yako, umechangia kuhakikisha kwamba mabadiliko yoyote kwenye mlango wa kizazi yanagunduliwa na kushughulikiwa mapema ikiwa yatahitajika. "
            . 'Tunakuhimiza kuendelea kufuata ushauri wa mhudumu wako wa afya na kuhudhuria miadi nyingine yoyote utakayopangiwa.'
            . "\nEndelea kuchukua hatua chanya kwa afya yako. Afya Rafiki inajivunia kuwa sehemu ya safari yako ya afya."
            . "\nAsante kwa kutumia Afya Rafiki.";
    }
    return "{$hello}\nThank you for attending your scheduled follow-up appointment. "
        . 'You have taken an important step in protecting your health and preventing cervical cancer.'
        . "\nBy attending your appointment, you have helped ensure that any cervical changes can be identified and managed early if needed. "
        . 'We encourage you to continue following the advice of your healthcare provider and attend any future appointments that may be recommended.'
        . "\nKeep taking positive steps for your health. Afya Rafiki is proud to support you on your journey."
        . "\nThank you for choosing Afya Rafiki.";
}

/**
 * Rule-based FAQ / menu replies. Returns null if no match.
 */
function afya_faq_reply(string $body, string $lang = 'en'): ?string
{
    $lang = afya_lang($lang);
    $text = mb_strtolower(trim($body));
    if ($text === '') {
        return null;
    }

    if (in_array($text, ['help', 'menu', '0', 'msaada'], true)) {
        return build_help_menu_message($lang);
    }

    if ($text === '1' || preg_match('/\b(what is hpv|hpv ni nini|hpv nini)\b/u', $text)) {
        return $lang === 'sw'
            ? 'HPV ni virusi vya kawaida vinavyoweza kuathiri mlango wa kizazi. Aina zingine zinaweza kusababisha saratani ya mlango wa kizazi zisipotibiwa mapema. Huduma ya ufuatiliaji husaidia kulinda afya yako.'
            : 'HPV is a common virus that can affect the cervix. Some types may cause cervical cancer if not treated early. Follow-up care helps protect your health.';
    }

    if ($text === '2' || preg_match('/\b(cervical cancer|do i have cancer|nina saratani|saratani ya mlango)\b/u', $text)) {
        return $lang === 'sw'
            ? 'Majibu chanya ya HPV hayamaanishi kuwa una saratani. Inamaanisha uko na virusi za HPV. HPV virus huleta saratani ya cervix ikikaa muda mrefu kwa cervix bila matibabu. Inamaanisha kuwa huduma zaidi ya ufuatiliaji inahitajika. Tafadhali hudhuria miadi yako ya kliniki.'
            : 'A positive HPV result does not mean you have cervical cancer. It means you have HPV virus. HPV virus causes HPV cancer if left untreated after a long period of time. It means additional follow-up care is needed. Please attend your clinic appointment.';
    }

    if ($text === '3' || preg_match('/\b(can hpv be treated|hpv inatibika|hpv treated)\b/u', $text)) {
        return $lang === 'sw'
            ? 'Maambukizi ya HPV mara nyingi hupotea yenyewe. Huduma ya ufuatiliaji husaidia wahudumu wa afya kufuatilia na kutibu mabadiliko yoyote mapema.'
            : 'HPV infections often clear naturally. Follow-up care helps health providers monitor and manage any cervical changes early.';
    }

    if ($text === '5' || preg_match('/\b(symptoms of hpv|hpv symptoms|dalili za hpv|dalili za virusi)\b/u', $text)) {
        return $lang === 'sw'
            ? 'Watu wengi wenye virusi vya HPV hawana dalili zozote. HPV kwa kawaida haisababishi maumivu au muwasho. Mara nyingi mwili huondoa maambukizi wenyewe. Baadhi ya aina zinaweza kusababisha mabadiliko kwenye mlango wa kizazi yanayogundulika kupitia kipimo cha HPV na VIA — ndiyo maana uchunguzi wa mara kwa mara ni muhimu. Ikiwa una damu isiyo ya kawaida, maumivu ya kudumu, au majimaji yasiyo ya kawaida, tembelea mhudumu wa afya.'
            : 'Most people with HPV do not have any symptoms and may not know they have the virus. HPV usually does not cause pain, itching, or illness. In many cases, the body clears the infection naturally. Some types can cause cervical changes detected only through screening such as HPV testing and VIA — regular screening is important even when you feel healthy. If you have unusual bleeding, persistent pelvic pain, or unusual discharge, please visit a healthcare provider.';
    }

    if ($text === '6' || preg_match('/\b(symptoms of cervical|dalili za saratani|cervical cancer symptoms)\b/u', $text)) {
        return $lang === 'sw'
            ? 'Wanawake wengi walio na mabadiliko ya awali au saratani ya awali wanaweza wasiwe na dalili. Dalili zinazoweza kuonekana: kutokwa na damu baada ya ngono, kati ya hedhi, au baada ya kukoma hedhi; majimaji yasiyo ya kawaida; maumivu ya kudumu chini ya tumbo; maumivu wakati wa ngono. Kuwa na dalili hizi haimaanishi moja kwa moja saratani — tembelea kituo cha afya kwa uchunguzi.'
            : 'Most women with early cervical changes or early cervical cancer may not have any symptoms. Possible symptoms may include bleeding after sex, between periods, or after menopause; unusual vaginal discharge; persistent lower abdominal or pelvic pain; pain during sex. Having these symptoms does not necessarily mean cervical cancer — please visit a health facility for assessment.';
    }

    if ($text === '4' || $text === '7' || preg_match('/\b(appointment|miadi|reschedule|panga upya)\b/u', $text)) {
        return $lang === 'sw'
            ? 'Kwa miadi, wasiliana na ' . afya_clinic_site() . ' au subiri ujumbe wa kikumbusho. Jibu DOCTOR ikiwa unahitaji msaada wa haraka.'
            : 'For appointments, contact ' . afya_clinic_site() . ' or wait for your reminder message. Reply DOCTOR if you need urgent help.';
    }

    return null;
}

/**
 * @return array{escalate: bool, reason: string, urgency: string}
 */
function afya_escalation_check(string $body): array
{
    $text = mb_strtolower(trim($body));
    $none = ['escalate' => false, 'reason' => '', 'urgency' => 'routine'];

    if ($text === '') {
        return $none;
    }

    $urgentPatterns = [
        '/\b(heavy bleeding|severe bleeding|damu nyingi|bleeding heavily)\b/u',
        '/\b(chest pain|can\'?t breathe|cannot breathe|ugumu wa kupumua|nimepata homa kali)\b/u',
        '/\b(severe pain|maumivu makali|unconscious|nimezimia)\b/u',
        '/\b(foul.?smell|harufu mbaya|high fever|homa kali)\b/u',
    ];
    foreach ($urgentPatterns as $pattern) {
        if (preg_match($pattern, $text)) {
            return [
                'escalate' => true,
                'reason' => 'Patient reported urgent symptoms via Afya Rafiki: ' . mb_substr($body, 0, 200),
                'urgency' => 'urgent',
            ];
        }
    }

    $distressPatterns = [
        '/\b(scared|afraid|terrified|worried sick|very worried|ninahofu|nina hofu|nina wasiwasi)\b/u',
        '/\b(i am scared|ninaogopa|nimeogopa)\b/u',
    ];
    foreach ($distressPatterns as $pattern) {
        if (preg_match($pattern, $text)) {
            return [
                'escalate' => true,
                'reason' => 'Patient expressed distress or fear via Afya Rafiki: ' . mb_substr($body, 0, 200),
                'urgency' => 'same_day',
            ];
        }
    }

    if (preg_match('/\b(missed my appointment|missed appointment|sikuhudhuria|nilikosa miadi|couldn\'?t come)\b/u', $text)) {
        return [
            'escalate' => true,
            'reason' => 'Patient may have missed follow-up appointment: ' . mb_substr($body, 0, 200),
            'urgency' => 'routine',
        ];
    }

    if (preg_match('/\b(side effect|drug interaction|chemotherapy|surgery|biopsy|prescription)\b/u', $text)
        && preg_match('/\?/u', $body)) {
        return [
            'escalate' => true,
            'reason' => 'Complex clinical question via Afya Rafiki: ' . mb_substr($body, 0, 200),
            'urgency' => 'routine',
        ];
    }

    return $none;
}

function create_escalation(int $patientId, string $reason, string $urgency = 'routine'): void
{
    $st = db()->prepare(
        'INSERT INTO escalations (patient_id, reason, urgency, status) VALUES (?,?,?,?)'
    );
    $st->execute([$patientId, $reason, $urgency, 'open']);
}

function afya_ai_personality_block(): string
{
    return 'You are Afya Rafiki, the HPV follow-up digital navigator for ' . afya_clinic_site() . '. '
        . 'Communicate in a warm, supportive, respectful, non-judgmental, simple, encouraging, confidential, and professional way. '
        . 'NEVER use frightening language, stigmatizing terms, heavy medical jargon, or robotic phrasing. '
        . 'Use SHORT SMS/WhatsApp-friendly messages. Encourage follow-up care. Normalize HPV infection. Promote hope and prevention. '
        . 'Do NOT diagnose, prescribe, or replace a visit with a health worker. '
        . 'Escalate complex issues to healthcare providers — suggest the patient reply DOCTOR or contact the clinic. '
        . 'Urgent symptoms (heavy bleeding, severe pain, fever): advise immediate facility visit.';
}
