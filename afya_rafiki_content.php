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

/** Initial welcome — greets patient by first name. */
function build_welcome_message(string $patientName, string $lang = 'en'): string
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

function build_hpv_result_notification(string $patientName, string $result, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $name = afya_first_name($patientName);
    $hello = $lang === 'sw'
        ? ($name !== '' ? "Habari {$name}." : 'Habari.')
        : ($name !== '' ? "Hello {$name}." : 'Hello.');

    if ($result === 'positive') {
        return $lang === 'sw'
            ? "{$hello} Matokeo yako yamethibitishwa: HPV chanya. Hii ni jambo la kawaida — watu wengi hupata nafuu kwa ufuatiliaji sahihi. "
                . 'Tutakutumia ujumbe mfupi wa mwongozo polepole (si mara moja). Tupo pamoja nawe.'
            : "{$hello} Your result is confirmed: HPV positive. This is common — many people stay well with the right follow-up. "
                . 'We will send you short, friendly guidance messages over the next days (not all at once). We are with you.';
    }

    return $lang === 'sw'
        ? "{$hello} Matokeo yako yamethibitishwa: HPV hasi. Hii ni habari nzuri — kwa sasa HPV haikugunduliwa. "
            . 'Tutakutumia ujumbe mfupi wa kusisimua na ukumbusho wa uchunguzi. Tupo pamoja nawe.'
        : "{$hello} Your result is confirmed: HPV negative. That is good news — HPV was not detected this time. "
            . 'We will send a few gentle reminders about routine care over the coming days. We are with you.';
}

function build_consent_message(string $lang = 'en'): string
{
    // Consent is captured on paper before registration.
    // Keep this function for backward compatibility but never send consent prompts.
    return '';
}

/** @return list<string> Positive HPV pathway (after result confirmed). */
function afya_counseling_messages_positive(string $lang = 'en'): array
{
    $lang = afya_lang($lang);
    if ($lang === 'sw') {
        return [
            'Majibu yako ya HPV yalikuwa chanya. Hii inamannisha uko na Virus ya HPV. HPV ni maambukizi ya kawaida na wanawake wengi hupata nafuu bila matatizo. Hata hivyo, huduma ya ufuatiliaji ni muhimu kusaidia kuzuia saratani ya mlango wa kizazi.',
            'Huduma ya ufuatiliaji husaidia wahudumu wa afya kugundua na kutibu mabadiliko mapema kabla hayajawa makubwa. Tafadhali hudhuria kliniki yako kama ulivyoelekezwa.',
            'Majibu chanya ya HPV hayamaanishi kuwa una saratani ya mlango wa kizazi. Inamaanisha kuwa ufuatiliaji zaidi unahitajika ili kulinda afya yako.',
            'Kwa kuwa majibu yako ya HPV ni chanya, hatua inayofuata ni uchunguzi unaoitwa Visual Assessment (VIA). Wakati wa VIA, mhudumu wa afya hupaka dawa maalum ya siki kwenye mlango wa kizazi na kuangalia kama kuna sehemu zisizo za kawaida zinazohitaji matibabu. Uchunguzi huu ni salama na huchukua dakika chache tu.',
            'Baada ya VIA, matokeo yako yanaweza kuwa: VIA Hasi (Negative): Hakuna mabadiliko yasiyo ya kawaida. VIA Chanya (Positive): Mabadiliko yalionekana ambayo yanaweza kuhitaji matibabu ili kuzuia saratani ya mlango wa kizazi.',
            'Ikiwa matokeo yako ya VIA ni hasi, huhitaji matibabu kwa sasa. Wanawake wanaoishi na HIV: rudia kipimo cha HPV baada ya miaka 3. Wanawake wasio na HIV: rudia kipimo cha HPV baada ya miaka 5.',
            'Ikiwa matokeo yako ya VIA ni chanya na unafaa kupata matibabu, mhudumu wa afya anaweza kupendekeza Thermal Ablation. Matibabu haya huondoa seli zisizo za kawaida kwenye mlango wa kizazi kabla hazijageuka kuwa saratani.',
            'Thermal Ablation ni matibabu rahisi yanayotumia joto kuharibu seli zisizo za kawaida kwenye mlango wa kizazi. Matibabu haya huchukua dakika chache na kwa kawaida hayahitaji kulazwa hospitalini.',
            'Baada ya Thermal Ablation, ni kawaida kupata majimaji kutoka ukeni (tumia pad au panty liner) na maumivu madogo chini ya tumbo. Dalili hizi kwa kawaida hupungua ndani ya siku au wiki chache.',
            'Tafadhali rudi hospitalini mara moja ikiwa utapata: kutokwa na damu nyingi ukeni, majimaji yenye harufu mbaya, maumivu makali chini ya tumbo, homa, au dalili nyingine zinazokusumbua.',
            'Ili kuruhusu mlango wa kizazi kupona: epuka kufanya ngono kwa wiki 4 au kama ulivyoelekezwa; epuka kuingiza kitu chochote ukeni; hudhuria miadi yote ya ufuatiliaji.',
            'Baada ya Thermal Ablation, unapaswa kurudi kwa Test of Cure (ToC) kwa kutumia kipimo cha HPV baada ya mwaka 1 ili kuthibitisha matibabu yalifanikiwa.',
        ];
    }

    return [
        'Your HPV test was positive. This means you have HPV virus. HPV is a common infection and many women recover without problems. However, follow-up care is important to help prevent cervical cancer.',
        'Follow-up care helps health providers detect and treat changes early before they become serious. Please attend your recommended clinic visit.',
        'A positive HPV result does not mean you have cervical cancer. It means more follow-up is needed to keep you healthy.',
        'Because your HPV test is positive, the next step is an examination called Visual Assessment (VIA). During VIA, a trained healthcare provider applies a special vinegar solution to the cervix and looks for any abnormal areas that may need treatment. The procedure is simple, safe, and usually takes only a few minutes.',
        'After VIA, your results may be: VIA Negative: No visible abnormal changes were found on the cervix. VIA Positive: Changes were seen on the cervix that may require treatment to prevent cervical cancer.',
        'If your VIA result is negative, no treatment is needed at this time. Women living with HIV: Repeat HPV test after 3 years. Women without HIV: Repeat HPV test after 5 years.',
        'If your VIA result is positive and you are eligible for treatment, your healthcare provider may recommend Thermal Ablation. This treatment removes abnormal cervical cells before they can develop into cancer.',
        'Thermal Ablation is a simple outpatient procedure that uses heat to destroy abnormal cells on the cervix. The procedure usually takes a few minutes and does not require admission to hospital.',
        'After Thermal Ablation, it is normal to experience mild watery discharge (use a pad or panty liner) and mild lower abdominal discomfort. These symptoms usually improve within a few days to weeks.',
        'Please return to the health facility immediately if you experience: heavy vaginal bleeding, foul-smelling vaginal discharge, severe lower abdominal pain, fever, or any symptoms that concern you.',
        'To allow your cervix to heal: avoid sexual intercourse for 4 weeks or as advised; avoid inserting anything into the vagina during the healing period; attend all scheduled follow-up appointments.',
        'After Thermal Ablation, you should return for a Test of Cure (ToC) using HPV testing after 1 year to confirm treatment was successful.',
    ];
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

/** @return list<string> Negative HPV pathway (after result confirmed). */
function afya_counseling_messages_negative(string $lang = 'en'): array
{
    $lang = afya_lang($lang);
    if ($lang === 'sw') {
        return [
            'Matokeo yako ni hasi — habari nzuri. HPV haikugunduliwa kwa sasa. Endelea kujitunza.',
            'Hata hivyo, miadi ya kawaida za kliniki bado ni muhimu. Tutakukumbusha kwa upole.',
            'Wenye HIV: rudia HPV baada ya miaka 3. Wengine: mara nyingi baada ya miaka 5.',
            'Lishe bora, usingizi, na kuacha sigara husaidia afya yako kwa ujumla.',
            'Ukihitaji msaada, jibu HELP au DOCTOR — tuko hapa.',
            'Dalili zisizo za kawaida (damu, maumivu makali)? Wasiliana na kliniki — ni bora kuangalia.',
        ];
    }
    return [
        'Your result is negative — good news. HPV was not found this time. Keep taking care of yourself.',
        'Routine clinic visits still matter. We will remind you gently.',
        'With HIV: HPV screening again in about 3 years. Others: often about 5 years.',
        'Healthy food, rest, and avoiding smoking all support your wellbeing.',
        'Reply HELP or DOCTOR anytime — we are here for you.',
        'Unusual bleeding or strong pain? Contact your clinic — it is always okay to check.',
    ];
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
    $name = afya_first_name($patientName);
    if ($lang === 'sw') {
        $hello = $name !== '' ? "Habari {$name}. " : '';
        return "{$hello}Kikumbusho kutoka Afya Rafiki: Una miadi ya ufuatiliaji wiki ijayo ({$date}) katika {$site}. "
            . 'Kuhudhuria huduma ya ufuatiliaji ni muhimu kwa afya yako.';
    }
    $hello = $name !== '' ? "Hello {$name}. " : '';
    return "{$hello}Reminder from Afya Rafiki: You have a follow-up appointment scheduled next week ({$date}) at {$site}. "
        . 'Attending follow-up care is important for your health.';
}

function build_reminder_3d_message(string $patientName, array $appointment, string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $site = afya_clinic_site();
    $date = afya_format_appointment_date($appointment['scheduled_start'] ?? null);
    $name = afya_first_name($patientName);
    if ($lang === 'sw') {
        $hello = $name !== '' ? "Habari {$name}. " : '';
        return "{$hello}Kikumbusho kutoka Afya Rafiki: Una miadi ya ufuatiliaji baada ya siku 3 ({$date}) katika {$site}. "
            . 'Huduma ya ufuatiliaji husaidia kulinda afya yako — tafadhali jiandae kuhudhuria.';
    }
    $hello = $name !== '' ? "Hello {$name}. " : '';
    return "{$hello}Reminder from Afya Rafiki: Your follow-up appointment is in 3 days ({$date}) at {$site}. "
        . 'Follow-up care helps protect your health — please plan to attend.';
}

function build_reminder_1d_message(string $patientName = '', string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    $site = afya_clinic_site();
    $name = afya_first_name($patientName);
    if ($lang === 'sw') {
        $hello = $name !== '' ? "Habari {$name}. " : '';
        return "{$hello}Kikumbusho kutoka Afya Rafiki: Ziara yako ya ufuatiliaji kliniki {$site} ni kesho. "
            . 'Tafadhali hudhuria kama ulivyopangiwa au wasiliana na kliniki ikiwa unahitaji msaada.';
    }
    $hello = $name !== '' ? "Hello {$name}. " : '';
    return "{$hello}Reminder from Afya Rafiki: Your clinic follow-up visit at {$site} is tomorrow. "
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
            . "2) Je, nina saratani?\n"
            . "3) HPV inatibika?\n"
            . "4) Miadi / kupanga upya\n"
            . "5) Ongea na mhudumu wa afya (DOCTOR)\n"
            . 'Andika swali lako au namba ya chaguo.';
    }
    return "Afya Rafiki — options:\n"
        . "1) What is HPV?\n"
        . "2) Do I have cervical cancer?\n"
        . "3) Can HPV be treated?\n"
        . "4) Appointments / reschedule help\n"
        . "5) Speak to a provider (reply DOCTOR)\n"
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

function build_missed_appointment_message(string $lang = 'en'): string
{
    $lang = afya_lang($lang);
    if ($lang === 'sw') {
        return "Tumeona huenda hukuhudhuria miadi yako ya ufuatiliaji. Je, ungependa kusaidiwa kupanga upya miadi yako?\n"
            . "Jibu:\n1. NDIO\n2. HAPANA";
    }
    return "We noticed you may have missed your follow-up appointment. Would you like help rescheduling your clinic visit?\n"
        . "Reply:\n1. YES\n2. NO";
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
            ? 'Majibu chanya ya HPV hayamaanishi kuwa una saratani. Inamaanisha uko na virusi vya HPV. Virusi hivi vinaweza kusababisha saratani ya mlango wa kizazi vikibaki bila ufuatiliaji wa muda mrefu. Huduma zaidi ya ufuatiliaji inahitajika. Tafadhali hudhuria miadi yako ya kliniki.'
            : 'A positive HPV result does not mean you have cervical cancer. It means you have HPV virus. HPV can lead to cervical cancer if left untreated for a long time. Additional follow-up care is needed. Please attend your clinic appointment.';
    }

    if ($text === '3' || preg_match('/\b(can hpv be treated|hpv inatibika|hpv treated)\b/u', $text)) {
        return $lang === 'sw'
            ? 'Maambukizi ya HPV mara nyingi hupotea yenyewe. Huduma ya ufuatiliaji husaidia wahudumu wa afya kufuatilia na kutibu mabadiliko yoyote mapema.'
            : 'HPV infections often clear naturally. Follow-up care helps health providers monitor and manage any cervical changes early.';
    }

    if ($text === '4' || preg_match('/\b(appointment|miadi|reschedule|panga upya)\b/u', $text)) {
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
  return 'You are Afya Rafiki, a warm and supportive HPV follow-up digital navigator for ' . HOSPITAL_NAME . '. '
        . 'Personality: warm, respectful, non-judgmental, simple, encouraging, confidential, professional — like a kind nurse friend. '
        . 'NEVER use frightening language, stigmatizing terms, or heavy medical jargon. Never shame the patient. Avoid sounding robotic. '
        . 'Use SHORT messages suitable for SMS/WhatsApp. '
        . 'Normalize HPV as very common; many people do well with follow-up. Promote hope and calm, not fear. '
        . 'Encourage clinic attendance and routine screening (VIA, Thermal Ablation, Test of Cure) when relevant. '
        . 'Do NOT diagnose, prescribe, or replace a clinician. '
        . 'For complex, urgent, or emotionally distressed messages, encourage contacting the clinic and reply DOCTOR. '
        . 'Escalate urgent symptoms (heavy bleeding, severe pain, high fever) to seek care immediately.';
}
