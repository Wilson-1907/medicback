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
    $site = defined('CLINIC_SITE_NAME') ? CLINIC_SITE_NAME : HOSPITAL_NAME;
    return str_replace('Health Center', 'Health Centre', $site);
}

function afya_referral_hospital(): string
{
    return defined('NYERI_REFERRAL_HOSPITAL') ? NYERI_REFERRAL_HOSPITAL : 'Nyeri County Referral Hospital';
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
    return date('l, j F Y', $ts) . ', ' . date('g:i A', $ts);
}

/** Official registration welcome — sent after consent thank-you (paper consent already signed). */
function build_registration_welcome_message(string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    if ($lang === 'sw') {
        return 'Karibu kwenye Afya rafiki. Tuko hapa kukusaidia baada ya majibu yako ya uchunguzi wa HPV. '
            . 'Huduma hii itakutumia taarifa za afya, vikumbusho, na mwongozo wa huduma ya ufuatiliaji. '
            . 'Taarifa zako zitahifadhiwa kwa siri.';
    }
    return 'Hello. Welcome to Afya rafiki. We are here to support you after your HPV screening results. '
        . 'This service will provide health information, reminders, and guidance for your follow-up care. '
        . 'Your information will remain confidential.';
}

/** Language selection when Afya Rafiki is activated for HPV-positive follow-up. */
function build_language_introduction_message(string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    if ($lang === 'sw') {
        return "Karibu kwenye Afya Rafiki, Mshirika wako wa safari ya afya ya mlango wa kizazi. Ungependa kupokea ujumbe kwa:\n"
            . "1. Kiingereza\n2. Kiswahili\n"
            . 'Ikiwa ungependa kuacha kupokea ujumbe kutoka Afya Rafiki — bonyeza 3';
    }
    return "Welcome to Afya Rafiki, Your Cervical health journey partner. Do you wish to receive messages in\n"
        . "1. English\n2. Kiswahili\n"
        . 'If you wish to stop receiving Messages from Afya Rafiki- Press 3';
}

/** @deprecated Registration uses paper consent; welcome sent once at enrollment. */
function build_welcome_message(string $patientName, string $lang = 'en'): string
{
    return build_language_introduction_message($lang);
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

/** Official HPV negative result — one SMS; HIV status sets 3-year vs 5-year return. */
function build_hpv_negative_result_notification(string $patientName, string $hivStatus = 'negative', string $lang = 'en'): string
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
            return "{$hello}\nKaribu kwenye Afya Rafiki. Majibu yako ya HPV ni hasi (negative). "
                . 'Hii inamaanisha kuwa hakuna maambukizi ya HPV yaliyopatikana kwa sasa. '
                . "Ili kuendelea kulinda afya yako, tafadhali rudi {$site} kwa uchunguzi mwingine wa virusi vya HPV "
                . 'baada ya miaka 3 au mapema zaidi ikiwa utaelekezwa na mhudumu wa afya. '
                . 'Asante kwa kutumia Afya Rafiki.';
        }

        return "{$hello}\nKaribu kwenye Afya Rafiki. Majibu yako ya kipimo cha HPV ni hasi (negative). "
            . 'Hii inamaanisha kuwa hakuna maambukizi ya HPV yaliyopatikana kwa sasa. '
            . "Ili kuendelea kudumisha afya ya mlango wa kizazi na kuzuia saratani ya mlango wa kizazi, tafadhali rudi {$site} "
            . 'kwa uchunguzi mwingine wa virusi vya HPV baada ya miaka 5, au mapema zaidi ikiwa utaelekezwa na mhudumu wako wa afya. '
            . 'Asante kwa kutumia Afya Rafiki.';
    }

    if ($hivPositive) {
        return "{$hello}\nWelcome to Afya Rafiki. Your HPV test result is negative. "
            . 'This means no HPV infection was detected at this time. '
            . "To continue protecting your health, please return to {$site} for repeat HPV self-sampling test "
            . 'after 3 years, or earlier if advised by your healthcare provider. '
            . 'Thank you for choosing Afya Rafiki.';
    }

    return "{$hello}\nWelcome to Afya Rafiki. Your HPV test result is negative. "
        . 'This means no HPV infection was detected at this time. '
        . "To maintain good cervical health, please return to {$site} for repeat HPV self-sampling test "
        . 'after 5 years, or earlier if advised by your healthcare provider. '
        . 'Thank you for choosing Afya Rafiki.';
}

/** Official HPV failed sample — insufficient sample; book VIA screening appointment. */
function build_hpv_failed_result_notification(string $patientName, string $appointmentDate, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    $hello = $lang === 'sw'
        ? ($name !== '' ? "Habari {$name}," : 'Habari,')
        : ($name !== '' ? "Hello {$name}," : 'Hello,');

    if ($lang === 'sw') {
        return "{$hello}\nMajibu yako ya kipimo cha HPV hayakuweza kupatikana kwa sababu sampuli iliyokusanywa haikutosha kufanyiwa uchunguzi. "
            . "Hii haimaanishi kuwa una tatizo la kiafya.\n"
            . "Tafadhali rudi katika kituo cha afya kwa uchunguzi wa VIA ili kukamilisha uchunguzi wako wa kuona kama kuna mabadiliko yeyote ya mlango wa kizazi.\n"
            . "Tarehe ya miadi (Clinic): {$appointmentDate}\n"
            . 'Asante kwa kutumia Afya Rafiki.';
    }

    return "{$hello}\nYour HPV test result could not be completed because the sample collected was not sufficient for testing. "
        . "This does not mean that there is a problem with your health.\n"
        . "We kindly request that you return to the health facility for VIA screening to complete your cervical screening.\n"
        . "Appointment Date: {$appointmentDate}\n"
        . 'Thank you for choosing Afya Rafiki.';
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
        return "{$hello}\nKaribu kwenye Afya Rafiki. Majibu yako ya kipimo cha HPV ni chanya (positive). Hii haimaanishi kuwa una saratani ya mlango wa kizazi. "
            . 'Inamaanisha kuwa virusi vya HPV vimepatikana na unahitaji huduma zaidi ya ufuatiliaji ili kulinda afya yako na kusaidia kuzuia saratani ya mlango wa kizazi.'
            . "\nUmepangiwa miadi(follow up clinic) ya ufuatiliaji katika {$site} tarehe:\nTarehe: {$appointmentDate}"
            . "\nTafadhali hudhuria miadi yako kama ulivyopangiwa. Ikiwa una maswali yoyote, Afya Rafiki iko hapa kukusaidia."
            . "\nAsante kwa kutumia Afya Rafiki.";
    }

    return "{$hello}\nWelcome to Afya Rafiki. Your HPV test result is positive. This does not mean that you have cervical cancer. "
        . 'It means that the HPV virus was detected and further follow-up is needed to help protect your health and prevent cervical cancer.'
        . "\nYou have been scheduled for a follow-up appointment at {$site} on:\nDate: {$appointmentDate}"
        . "\nPlease attend your appointment as scheduled. If you have any questions, Afya Rafiki is here to support you."
        . "\nThank you for choosing Afya Rafiki.";
}

/** VIA negative result — study §12b (sent when follow-up appointment is booked). */
function build_via_negative_result_notification(
    string $patientName,
    string $hivStatus = 'negative',
    string $lang = 'en',
    string $appointmentDate = '__________'
): string {
    unset($hivStatus);
    return build_post_visit_via_negative($patientName, $appointmentDate, $lang);
}

/** VIA positive result — sent when nurse records VIA after the test (counseling step 10 script). */
function build_via_positive_result_notification(string $patientName, string $lang = 'en'): string
{
    require_once __DIR__ . '/afya_counseling_positive.php';
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    $hello = $lang === 'sw'
        ? ($name !== '' ? "Habari {$name}," : 'Habari,')
        : ($name !== '' ? "Hello {$name}," : 'Hello,');
    $messages = $lang === 'sw'
        ? afya_counseling_messages_positive_sw()
        : afya_counseling_messages_positive_en();
    $body = trim((string) ($messages[9] ?? ''));
    return $body === '' ? $hello : "{$hello}\n{$body}";
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

/** @return list<string> HPV-positive drip — short encouraging tips (not long clinical script). */
function afya_counseling_messages_positive(string $lang = 'en'): array
{
    require_once __DIR__ . '/afya_simple_drip.php';
    return afya_simple_encouragement_drip($lang);
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
    return in_array($msg, ['2', '3', 'NO', 'HAPANA', 'STOP', 'STOPALL'], true);
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
        . "Reply:\n1. YES -Reschedule my appointment\n2. NO - I will contact the clinic myself\n3. I need to speak with a healthcare provider";
}

function build_missed_reschedule_confirmation(string $patientName, string $appointmentDate, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    $hello = $lang === 'sw'
        ? ($name !== '' ? "Habari {$name}," : 'Habari,')
        : ($name !== '' ? "Hello {$name}," : 'Hello,');
    if ($lang === 'sw') {
        return "{$hello}\nAsante kwa kuchagua kuendelea na huduma yako ya ufuatiliaji. Kupanga upya miadi yako ni hatua muhimu katika kulinda afya yako na kuzuia saratani ya mlango wa kizazi.\n"
            . "Miadi yako mpya imepangwa tarehe:\nTarehe: {$appointmentDate}\nTunatarajia kukuona.";
    }
    return "{$hello}\nThank you for choosing to continue your follow-up care. Rescheduling your appointment is an important step in protecting your health and preventing cervical cancer.\n"
        . "Your new appointment is scheduled for:\nDate: {$appointmentDate}\nWe look forward to seeing you.";
}

function build_referral_initial_message(string $patientName, string $appointmentDate, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    $site = afya_referral_hospital();
    $hello = $lang === 'sw'
        ? ($name !== '' ? "Habari {$name}," : 'Habari,')
        : ($name !== '' ? "Hello {$name}," : 'Hello,');
    if ($lang === 'sw') {
        return "{$hello}\nUchunguzi wako wa hivi karibuni wa mlango wa kizazi ulionyesha matokeo yanayohitaji tathmini zaidi na daktari bingwa. Hii haimaanishi moja kwa moja kuwa una saratani ya mlango wa kizazi. Hata hivyo, uchunguzi zaidi unapendekezwa ili kuelewa vizuri mabadiliko yaliyoonekana kwenye mlango wa kizazi na kubaini huduma inayofaa zaidi.\n"
            . "Umepewa rufaa kwenda {$site} kwa uchunguzi wa daktari bingwa.\nTarehe ya miadi: {$appointmentDate}\n"
            . 'Tafadhali hudhuria miadi yako kama ulivyopangiwa. Uchunguzi wa mapema husaidia kuhakikisha kuwa matatizo yoyote ya afya yanagunduliwa na kushughulikiwa ipasavyo.'
            . "\nAsante kwa kutumia Afya Rafiki.";
    }
    return "{$hello}\nYour recent cervical screening showed findings that would benefit from further assessment by a specialist. This does not necessarily mean that you have cervical cancer. However, additional evaluation is recommended to better understand the changes seen on your cervix and determine the most appropriate care.\n"
        . "You have been referred to {$site} for specialist review.\nAppointment Date: {$appointmentDate}\n"
        . 'Please attend your appointment as scheduled. Early assessment helps ensure that any health concerns are identified and managed appropriately.'
        . "\nThank you for choosing Afya Rafiki.";
}

function build_referral_reassurance_message(string $patientName, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    $hello = $lang === 'sw'
        ? ($name !== '' ? "Habari {$name}," : 'Habari,')
        : ($name !== '' ? "Hello {$name}," : 'Hello,');
    if ($lang === 'sw') {
        return "{$hello}\nTunaelewa kuwa kupokea rufaa kunaweza kukusababishia wasiwasi. Tafadhali kumbuka kuwa wanawake wengi wanaopewa rufaa kwa uchunguzi wa daktari bingwa hawapatikani na saratani ya mlango wa kizazi. Lengo la rufaa ni kusaidia daktari kuchunguza mlango wa kizazi kwa karibu zaidi na kuhakikisha unapata huduma inayofaa.\n"
            . "Kuhudhuria miadi yako ni hatua muhimu katika kulinda afya yako.\nAfya Rafiki iko hapa kukusaidia.";
    }
    return "{$hello}\nWe understand that receiving a referral may cause concern. Please remember that many women referred for specialist assessment do not have cervical cancer. The purpose of the referral is to allow a closer examination of the cervix and ensure that you receive the most appropriate care.\n"
        . "Attending your appointment is an important step in protecting your health.\nAfya Rafiki is here to support you.";
}

function build_referral_appointment_reminder(string $appointmentDate, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $site = afya_referral_hospital();
    if ($lang === 'sw') {
        return "Kikumbusho kutoka Afya Rafiki\nUna miadi ya uchunguzi wa daktari bingwa katika {$site} tarehe:\nTarehe: {$appointmentDate}\n"
            . 'Tafadhali hudhuria kama ulivyopangiwa. Ziara hii itasaidia kubaini hatua zinazofuata zinazofaa kwa huduma yako.'
            . "\nIkiwa hutaweza kuhudhuria, tafadhali wasiliana na mhudumu wako wa afya ili kupanga miadi nyingine.";
    }
    return "Reminder from Afya Rafiki\nYou have a specialist review appointment at {$site} on:\nDate: {$appointmentDate}\n"
        . 'Please attend as scheduled. This visit will help determine the most appropriate next steps for your care.'
        . "\nIf you are unable to attend, please contact your healthcare provider to arrange another appointment.";
}

function build_post_visit_via_negative(string $patientName, string $appointmentDate, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    $hello = $lang === 'sw'
        ? ($name !== '' ? "Habari {$name}," : 'Habari,')
        : ($name !== '' ? "Hello {$name}," : 'Hello,');
    if ($lang === 'sw') {
        return "{$hello}\nMajibu yako ya HPV yalikuwa chanya (positive), lakini uchunguzi wa VIA haukuonyesha mabadiliko kwenye mlango wa kizazi yanayohitaji matibabu kwa sasa.\n"
            . 'Haya ni matokeo mazuri. Ingawa HPV vilipatikana, hakuna mabadiliko yaliyoonekana kwenye mlango wa kizazi kwa sasa. Maambukizi mengi ya HPV huisha yenyewe bila kusababisha matatizo ya kiafya. Hata hivyo, ufuatiliaji wa mara kwa mara ni muhimu kwa sababu baadhi ya maambukizi yanaweza kuendelea kwa muda mrefu na kusababisha mabadiliko kwenye mlango wa kizazi.'
            . "\nHuhitaji matibabu sasa.\nNi muhimu urudi kwa kipimo kingine cha HPV baada ya mwaka 1 ili kufuatilia afya ya mlango wa kizazi na kuhakikisha mabadiliko yoyote yatagunduliwa mapema ikiwa yatatokea."
            . "\nMiadi yako ya ufuatiliaji ni:\nTarehe: {$appointmentDate}"
            . "\nIkiwa utapata dalili kama kutokwa na damu isiyo ya kawaida ukeni, majimaji yenye harufu mbaya kutoka ukeni, au maumivu ya muda mrefu chini ya tumbo, tafadhali tembelea kituo cha afya kwa uchunguzi zaidi."
            . "\nAsante kwa kutumia Afya Rafiki. Tuko hapa kukusaidia.";
    }
    return "{$hello}\nYour HPV test was positive, but your VIA examination did not show any changes on the cervix that require treatment at this time.\n"
        . 'This is good news. Although HPV was detected, no visible changes were seen on your cervix. Most HPV infections clear naturally without causing health problems. However, regular follow-up is important because a small number of HPV infections may persist and cause changes over time.'
        . "\nYou do not need treatment at this time.\nIt is important that you return for a repeat HPV test after 1 year to monitor your cervical health and ensure that any future changes are detected early."
        . "\nYour follow-up appointment is scheduled for:\nDate: {$appointmentDate}"
        . "\nIf you experience unusual symptoms such as abnormal vaginal bleeding, foul-smelling discharge, or persistent lower abdominal pain, please visit a health facility for assessment."
        . "\nThank you for choosing Afya Rafiki. We are here to support you.";
}

function build_post_visit_via_positive_ablation(string $patientName, string $appointmentDate, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    $hello = $lang === 'sw'
        ? ($name !== '' ? "Habari {$name}," : 'Habari,')
        : ($name !== '' ? "Hello {$name}," : 'Hello,');
    if ($lang === 'sw') {
        return "{$hello}\nMajibu yako ya HPV yalikuwa chanya (positive), na uchunguzi wa VIA ulionyesha mabadiliko kwenye mlango wa kizazi yaliyohitaji matibabu. Thermal Ablation imefanyika kwa mafanikio ili kuondoa seli zisizo za kawaida na kusaidia kuzuia saratani ya mlango wa kizazi.\n"
            . 'Baada ya matibabu, ni kawaida kupata majimaji kutoka ukeni na maumivu madogo chini ya tumbo kwa siku chache. Unaweza kutumia pedi ikiwa itahitajika.'
            . "\nTafadhali rudi hospitalini mara moja ikiwa utapata kutokwa na damu nyingi, majimaji yenye harufu mbaya kutoka ukeni, maumivu makali chini ya tumbo, homa, au dalili nyingine zinazokusumbua."
            . "\nNi muhimu urudi kwa kipimo kingine cha HPV baada ya mwaka 1 ili kuthibitisha kuwa matibabu yalifanikiwa."
            . "\nTarehe ya miadi yako ya ufuatiliaji ni:\nTarehe: {$appointmentDate}"
            . "\nAsante kwa kutumia Afya Rafiki. Tuko hapa kukusaidia.";
    }
    return "{$hello}\nYour HPV test was positive, and your VIA examination showed changes on the cervix that required treatment. Thermal Ablation was successfully performed to remove the abnormal cells and help prevent cervical cancer.\n"
        . 'After treatment, it is normal to experience mild watery discharge and mild lower abdominal discomfort for a few days, mild blood spots. Please use a sanitary pad if needed.'
        . "\nPlease return to the health facility immediately if you experience heavy bleeding, foul-smelling discharge, severe lower abdominal pain, fever, or any other concerning symptoms."
        . "\nIt is important that you return for a repeat HPV test after 1 year to confirm if treatment was successful."
        . "\nYour follow-up appointment is scheduled for:\nDate: {$appointmentDate}"
        . "\nThank you for choosing Afya Rafiki. We are here to support you.";
}

function build_post_visit_treatment_postponed(string $patientName, string $appointmentDate, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    $site = afya_clinic_site();
    $hello = $lang === 'sw'
        ? ($name !== '' ? "Habari {$name}," : 'Habari,')
        : ($name !== '' ? "Hello {$name}," : 'Hello,');
    if ($lang === 'sw') {
        return "{$hello}\nMajibu yako ya HPV yalikuwa chanya (positive), na uchunguzi wa VIA ulionyesha mabadiliko kwenye mlango wa kizazi yanayohitaji matibabu. Hii haimaanishi kuwa una saratani ya mlango wa kizazi. Matibabu ya mapema husaidia kuzuia seli zisizo za kawaida zisigeuke kuwa saratani.\n"
            . "Matibabu yako yameahirishwa na umepangiwa tarehe nyingine ya matibabu:\nTarehe: {$appointmentDate}\n"
            . "Ni muhimu uhudhurie miadi hii ili matibabu yaliyopendekezwa yakamilike. Ikiwa hutaweza kuhudhuria, tafadhali wasiliana na mhudumu wako wa afya au tembelea {$site} kupanga tarehe nyingine."
            . "\nIkiwa una maswali yoyote, Afya Rafiki iko hapa kukusaidia.\nAsante kwa kutumia Afya Rafiki.";
    }
    return "{$hello}\nYour HPV test was positive, and your VIA examination showed changes on the cervix that require treatment. This does not mean that you have cervical cancer. Early treatment helps prevent abnormal cells from developing into cervical cancer.\n"
        . "Your treatment was postponed and has been rescheduled for:\nDate: {$appointmentDate}\n"
        . "It is important that you attend this appointment so that the recommended treatment can be completed. If you are unable to attend, please contact your healthcare provider or visit {$site} to arrange another appointment."
        . "\nIf you have any questions, Afya Rafiki is here to support you.\nThank you for choosing Afya Rafiki.";
}

function build_language_set_ack_message(string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    return $lang === 'sw'
        ? 'Asante. Afya Rafiki itatumia Kiswahili. Jibu HELP wakati wowote.'
        : 'Thank you. Afya Rafiki will send messages in English. Reply HELP anytime.';
}

function build_unsubscribe_ack_message(string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    return $lang === 'sw'
        ? 'Umejiondoa kupokea ujumbe kutoka Afya Rafiki. Wasiliana na Nyeri Town Health Centre ikiwa unahitaji msaada.'
        : 'You have been unsubscribed from Afya Rafiki messages. Contact Nyeri Town Health Centre if you need help.';
}

/**
 * @return array{0: string, 1: string} message type, body
 */
function resolve_via_positive_patient_message(int $patientId, string $patientName, string $lang, ?string $treatmentDate): array
{
    $lang = afya_lang($lang);
    if ($treatmentDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $treatmentDate)) {
        $formattedTreatment = afya_format_appointment_date($treatmentDate . ' 09:00:00');
        if ($treatmentDate > date('Y-m-d')) {
            return [
                'via_tx_postponed',
                build_post_visit_treatment_postponed($patientName, $formattedTreatment, $lang),
            ];
        }
        $followUp = afya_next_appointment_display($patientId);
        if ($followUp === '__________') {
            $followUp = afya_format_appointment_date(date('Y-m-d', strtotime($treatmentDate . ' +1 year')) . ' 09:00:00');
        }

        return [
            'via_ablation',
            build_post_visit_via_positive_ablation($patientName, $followUp, $lang),
        ];
    }

    return [
        'via_positive',
        build_via_positive_result_notification($patientName, $lang),
    ];
}

function build_post_visit_acknowledgement(string $patientName, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    $hello = $lang === 'sw'
        ? ($name !== '' ? "Habari {$name}," : 'Habari,')
        : ($name !== '' ? "Hello {$name}," : 'Hello,');
    if ($lang === 'sw') {
        return "{$hello}\nAsante kwa kuhudhuria miadi (Clinic appointment) yako ya ufuatiliaji kama ulivyopangiwa. "
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
            ? 'Majibu chanya ya HPV hayamaanishi kuwa una saratani. Inamaanisha uko na virusi za HPV. HPV virus huleta saratani ya cervix ikikaa muda mrefu kwa cervix bila matibabu.Inamaanisha kuwa huduma zaidi ya ufuatiliaji inahitajika. Tafadhali hudhuria miadi yako ya kliniki.'
            : 'A positive HPV result does not mean you have cervical cancer. It means you have HPV virus. HPV virus causes Cervical cancer if left untreated after a long period of time. It means additional follow-up care is needed. Please attend your clinic appointment.';
    }

    if ($text === '3' || preg_match('/\b(can hpv be treated|hpv inatibika|hpv treated)\b/u', $text)) {
        return $lang === 'sw'
            ? 'Maambukizi ya HPV mara nyingi hupotea yenyewe. Huduma ya ufuatiliaji husaidia wahudumu wa afya kufuatilia na kutibu mabadiliko yoyote mapema.'
            : 'HPV infections often clear naturally. Follow-up care helps health providers monitor and manage any cervical changes early.';
    }

    if ($text === '5' || preg_match('/\b(symptoms of hpv|hpv symptoms|dalili za hpv|dalili za virusi)\b/u', $text)) {
        return $lang === 'sw'
            ? 'Watu wengi wenye virusi vya HPV hawana dalili zozote na huenda wasijue kuwa wana maambukizi hayo. HPV kwa kawaida haisababishi maumivu, muwasho, au ugonjwa unaoonekana. Mara nyingi mwili huondoa maambukizi haya wenyewe bila matibabu. Baadhi ya aina za HPV zinaweza kusababisha mabadiliko kwenye mlango wa kizazi ambayo yanaweza kugunduliwa tu kupitia vipimo vya uchunguzi kama vile kipimo cha HPV na VIA. Ndiyo maana uchunguzi wa mara kwa mara ni muhimu hata kama unajisikia mzima na huna dalili zozote. Ikiwa una dalili kama kutokwa na damu isiyo ya kawaida ukeni, maumivu ya muda mrefu chini ya tumbo au nyonga, au majimaji yasiyo ya kawaida kutoka ukeni, tafadhali tembelea mhudumu wa afya kwa uchunguzi zaidi.'
            : 'Most people with HPV do not have any symptoms and may not know they have the virus. HPV usually does not cause pain, itching, or illness. In many cases, the body clears the infection naturally without treatment. Some types of HPV can cause changes on the cervix that can only be detected through screening tests such as HPV testing and VIA. This is why regular screening is important, even when you feel healthy and have no symptoms. If you have any unusual symptoms such as abnormal vaginal bleeding, persistent pelvic pain, or unusual vaginal discharge, please visit a healthcare provider for assessment.';
    }

    if ($text === '6' || preg_match('/\b(symptoms of cervical|dalili za saratani|cervical cancer symptoms)\b/u', $text)) {
        return $lang === 'sw'
            ? 'Wanawake wengi walio na mabadiliko ya awali kwenye mlango wa kizazi au saratani ya awali ya mlango wa kizazi wanaweza wasiwe na dalili yoyote. Ndiyo maana uchunguzi wa mara kwa mara ni muhimu. Dalili zinazoweza kuonekana ni pamoja na: Kutokwa na damu baada ya kufanya ngono. Kutokwa na damu kati ya hedhi. Kutokwa na damu baada ya kukoma hedhi. Majimaji yasiyo ya kawaida kutoka ukeni, hasa yenye harufu mbaya. Maumivu ya kudumu chini ya tumbo au nyonga. Maumivu wakati wa kufanya ngono. Kuwa na dalili moja au zaidi kati ya hizi haimaanishi moja kwa moja kuwa una saratani ya mlango wa kizazi, kwani zinaweza kusababishwa na matatizo mengine ya kiafya. Ukiona dalili hizi, tafadhali tembelea kituo cha afya kwa uchunguzi zaidi.'
            : 'Most women with early cervical changes or early cervical cancer may not have any symptoms. This is why regular screening is important. Possible symptoms of cervical cancer may include: Bleeding after sexual intercourse. Bleeding between menstrual periods. Bleeding after menopause. Unusual vaginal discharge, especially if it has a bad smell. Persistent lower abdominal or pelvic pain or lower back pain. Pain during sexual intercourse. Having one or more of these symptoms does not necessarily mean you have cervical cancer, as they can be caused by other health conditions. If you experience any of these symptoms, please visit a health facility for assessment by a healthcare provider.';
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
