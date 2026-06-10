<?php
declare(strict_types=1);

/**
 * Afya Rafiki alignment & flow self-test — compares live code to WHATSAPP_MESSAGE_TEMPLATES.md.
 * GET /api/afya_self_test.php
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../afya_counseling_positive.php';
require_once __DIR__ . '/../hpv_results.php';

header('Content-Type: application/json; charset=utf-8');

$token = getenv('AFYA_SELF_TEST_TOKEN') ?: '';
if ($token !== '' && ($_GET['token'] ?? '') !== $token) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_PRETTY_PRINT);
    exit;
}

/** @return list<array{name: string, pass: bool, detail: string}> */
function afya_test_results(): array
{
    $results = [];
    $pass = static function (string $name, bool $ok, string $detail = '') use (&$results): void {
        $results[] = ['name' => $name, 'pass' => $ok, 'detail' => $detail];
    };

    $norm = static function (string $s): string {
        $s = preg_replace('/\s+/u', ' ', trim($s)) ?? '';
        return mb_strtolower($s);
    };

    $contains = static function (string $haystack, string $needle) use ($norm): bool {
        return str_contains($norm($haystack), $norm($needle));
    };

    // --- Section 1: Language introduction ---
    $welcomeEn = build_language_introduction_message('en');
    $pass('§1 welcome EN — opening line', $contains($welcomeEn, 'Welcome to Afya Rafiki, Your Cervical health journey partner'));
    $pass('§1 welcome EN — language options', $contains($welcomeEn, '1. English') && $contains($welcomeEn, '2. Kiswahili'));
    $pass('§1 welcome EN — stop option', $contains($welcomeEn, 'Press 3'));

    $welcomeSw = build_language_introduction_message('sw');
    $pass('§1 welcome SW — opening', $contains($welcomeSw, 'Karibu kwenye Afya Rafiki'));
    $pass('§1 welcome SW — stop option', $contains($welcomeSw, 'bonyeza 3'));

    // --- Section 2–3: HPV negative ---
    $negHivPos = build_hpv_negative_result_notification('Jane Doe', 'positive', 'en');
    $pass('§2 HPV neg HIV+ EN — negative result', $contains($negHivPos, 'HPV test result is negative'));
    $pass('§2 HPV neg HIV+ EN — 3 years', $contains($negHivPos, 'after 3 years'));

    $negHivNeg = build_hpv_negative_result_notification('Mary', 'negative', 'en');
    $pass('§3 HPV neg HIV− EN — 5 years', $contains($negHivNeg, 'after 5 years'));
    $pass('§3 HPV neg HIV− EN — cervical health', $contains($negHivNeg, 'maintain good cervical health'));

    // --- Section 4: HPV positive ---
    $posEn = build_hpv_positive_result_notification('Jane', 'Saturday, 14 June 2026, 10:30 AM', 'en');
    $pass('§4 HPV positive EN — not cancer', $contains($posEn, 'does not mean that you have cervical cancer'));
    $pass('§4 HPV positive EN — appointment date', $contains($posEn, 'Saturday, 14 June 2026, 10:30 AM'));

    // --- Section 25: Consent thank-you (registration, message 1) ---
    $consentEn = build_consent_thank_you_message('Jane', 'en');
    $pass('§25 consent thanks EN', $contains($consentEn, 'appreciate you agreeing to receive messages from Afya Rafiki'));

    $regWelcomeEn = build_registration_welcome_message('en');
    $pass('Registration welcome EN — confidential', $contains($regWelcomeEn, 'Your information will remain confidential'));
    $pass('Registration welcome SW', $contains(build_registration_welcome_message('sw'), 'itahifadhiwa kwa siri'));

    // --- Reminders (§5–7) ---
    $appt = ['scheduled_start' => '2026-06-14 10:30:00'];
    $pass('§5 reminder 7d EN', $contains(build_reminder_7d_message('Jane', $appt, 'en'), 'next week'));
    $pass('§6 reminder 3d EN', $contains(build_reminder_3d_message('Jane', $appt, 'en'), 'Reminder from Afya Rafiki'));
    $pass('§7 reminder 1d EN', $contains(build_reminder_1d_message('Jane', 'en'), 'tomorrow'));

    // --- HELP menu ---
    $pass('§10 HELP menu EN', $contains(build_help_menu_message('en'), 'What is HPV?'));
    $pass('§10 HELP menu SW', $contains(build_help_menu_message('sw'), 'HPV ni nini?'));

    // --- Counseling pathway (Phase 3) ---
    require_once __DIR__ . '/../afya_simple_drip.php';
    $dripEn = afya_simple_encouragement_drip_en();
    $dripSw = afya_simple_encouragement_drip_sw();
    $pass('Simple drip EN count = 10', count($dripEn) === 10, 'got ' . count($dripEn));
    $pass('Simple drip SW count = 10', count($dripSw) === 10, 'got ' . count($dripSw));
    $pass('Simple drip[0] EN — What is HPV', $contains($dripEn[0], 'What is HPV'));
    $pass('Simple drip[0] EN — short (no 8 out of 10)', !str_contains($dripEn[0], '8 out of every 10'));
    require_once __DIR__ . '/../afya_rafiki_content.php';
    $counselEn = afya_counseling_messages_positive('en');
    $pass('HPV+ drip uses simple tips', $contains($counselEn[0], 'What is HPV'));
    $pass('§42 VIA neg counseling EN — HIV 3y / 5y', $contains(afya_counseling_messages_positive_en()[8], 'Repeat HPV screening after 3 years')
        && $contains(afya_counseling_messages_positive_en()[8], 'Repeat HPV screening after 5 years'));

    require_once __DIR__ . '/../patient_screening.php';
    $viaNegHivPos = build_via_negative_result_notification('Jane', 'positive', 'en');
    $viaNegHivNeg = build_via_negative_result_notification('Mary', 'negative', 'en');
    $pass('VIA neg HIV+ EN — 3 years only', $contains($viaNegHivPos, 'after 3 years')
        && !str_contains($viaNegHivPos, 'after 5 years'));
    $pass('VIA neg HIV− EN — 5 years only', $contains($viaNegHivNeg, 'after 5 years')
        && !str_contains($viaNegHivNeg, 'after 3 years'));

    $fuPos = compute_screening_followups([
        'via_result' => 'negative',
        'via_date' => '2026-06-10',
        'hiv_status' => 'positive',
    ]);
    $pass('VIA neg follow-up HIV+ — 3 years', ($fuPos['schedules'][0]['reason'] ?? '') === 'via_neg_hiv_pos_3y');
    $fuNeg = compute_screening_followups([
        'via_result' => 'negative',
        'via_date' => '2026-06-10',
        'hiv_status' => 'negative',
    ]);
    $pass('VIA neg follow-up HIV− — 5 years', ($fuNeg['schedules'][0]['reason'] ?? '') === 'via_neg_hiv_neg_5y');

    // --- HPV confirm delays (study: 3h, 5h, then 1 day) ---
    $pass('HPV tip delay index 0 = +1 day', hpv_delay_before_counseling_index(0) === '+1 day');
    $pass('HPV tip delay index 1 = +2 days', hpv_delay_before_counseling_index(1) === '+2 days');
    $pass('HPV tip delay index 2 = +2 days', hpv_delay_before_counseling_index(2) === '+2 days');

    // --- Mteja language codes ---
    $pass('Mteja lang en_US → en', mteja_lang_code('en_US') === 'en');
    $pass('Mteja lang en → en', mteja_lang_code('en') === 'en');
    $pass('Mteja lang sw → sw', mteja_lang_code('sw') === 'sw');

    // --- Mteja template resolution (Phase 1 go-live) ---
    $fakePatientId = 0;
    $tplWelcome = mteja_resolve_template($fakePatientId, 'welcome', $welcomeEn);
    $pass('Mteja template welcome', ($tplWelcome['templateName'] ?? '') === 'afya_welcome_en');

    $tplConsent = mteja_resolve_template($fakePatientId, 'system', $consentEn);
    $pass('Mteja template consent_thanks', ($tplConsent['templateName'] ?? '') === 'afya_consent_thanks_en');
    $pass('Mteja consent lang code', ($tplConsent['languageCode'] ?? '') === 'en');

    $tplNeg = mteja_resolve_template($fakePatientId, 'system', $negHivNeg);
    $pass('Mteja template hpv_neg_hivneg', ($tplNeg['templateName'] ?? '') === 'afya_hpv_neg_hivneg_en');

    $tplPos = mteja_resolve_template($fakePatientId, 'system', $posEn);
    $pass('Mteja template hpv_positive', ($tplPos['templateName'] ?? '') === 'afya_hpv_positive_en');

    $rem7 = build_reminder_7d_message('Jane', $appt, 'en');
    $tpl7 = mteja_resolve_template($fakePatientId, 'appointment_reminder', $rem7);
    $pass('Mteja template appt_reminder_7d', str_starts_with((string) ($tpl7['templateName'] ?? ''), 'afya_appt_reminder_7d'));

    // --- Registration enrollment sends message (source check) ---
    $enrollSrc = (string) file_get_contents(__DIR__ . '/../messaging.php');
    $pass(
        'Registration sends thank-you then welcome',
        str_contains($enrollSrc, 'build_consent_thank_you_message')
            && str_contains($enrollSrc, 'build_registration_welcome_message')
    );
    $pass(
        'Registration schedules first simple HPV tip',
        str_contains($enrollSrc, 'afya_simple_encouragement_drip')
    );
    $pass(
        'Registration does not send language intro (1/2/3)',
        !str_contains($enrollSrc, 'build_language_introduction_message($lang)')
    );

    // --- Reminder cron SQL guards (exact-day only) ---
    $remSrc = (string) file_get_contents(__DIR__ . '/../reminders.php');
    $pass('Reminder 7d exact day', str_contains($remSrc, 'INTERVAL 7 DAY'));
    $pass('Reminder night at 20:00', str_contains($remSrc, "20:00:00"));

    // --- HPV positive requires appointment BEFORE confirm is recorded ---
    $hpvSrc = (string) file_get_contents(__DIR__ . '/../hpv_results.php');
    $pass('HPV positive confirm gates on appointment', str_contains($hpvSrc, 'Book a follow-up appointment first'));
    $confirmBlock = preg_match(
        '/if \(\$result === \'positive\'\).*?Book a follow-up appointment first.*?hpv_result_confirmed_at = NOW/s',
        $hpvSrc
    );
    $pass(
        'HPV positive validates appointment before confirmed_at',
        (bool) $confirmBlock,
        'appointment check must run before hpv_result_confirmed_at update'
    );

    // --- Doc template index: templates we must map for go-live ---
    $requiredBases = [
        'afya_welcome', 'afya_hpv_neg_hivpos', 'afya_hpv_neg_hivneg', 'afya_hpv_positive',
        'afya_appt_reminder_7d', 'afya_appt_reminder_3d', 'afya_appt_reminder_1d',
        'afya_via_referral', 'afya_appt_booked', 'afya_help_menu', 'afya_consent_thanks',
        'afya_staff_message', 'afya_ai_reply', 'afya_fallback',
    ];
    $mtejaSrc = (string) file_get_contents(__DIR__ . '/../mteja_whatsapp.php');
    foreach ($requiredBases as $base) {
        $pass("Mteja maps {$base}", str_contains($mtejaSrc, "'{$base}'"));
    }

    // --- Optional templates not yet mapped (informational warnings) ---
    $optionalMissing = [];
    foreach (['afya_counsel_pos', 'afya_post_visit', 'afya_missed_reschedule', 'afya_unlinked', 'afya_checkup_via_neg'] as $prefix) {
        if (!str_contains($mtejaSrc, $prefix)) {
            $optionalMissing[] = $prefix;
        }
    }
    $pass(
        'Optional templates pending Mteja mapping',
        true,
        $optionalMissing === [] ? 'all optional prefixes present' : 'missing: ' . implode(', ', $optionalMissing)
    );

    // --- Messaging health snapshot ---
    try {
        $pdo = db();
        $lastOut = $pdo->query(
            "SELECT status, message_type, LEFT(body, 60) AS preview FROM outbound_messages ORDER BY id DESC LIMIT 1"
        )->fetch();
        $pass('DB reachable', true, $lastOut ? "last outbound: {$lastOut['status']} {$lastOut['message_type']}" : 'no outbound yet');
    } catch (Throwable $e) {
        $pass('DB reachable', false, $e->getMessage());
    }

    return $results;
}

$results = afya_test_results();
$failed = array_values(array_filter($results, static fn (array $r): bool => !$r['pass']));
$passed = count($results) - count($failed);

echo json_encode([
    'ok' => $failed === [],
    'summary' => [
        'passed' => $passed,
        'failed' => count($failed),
        'total' => count($results),
    ],
    'failures' => array_map(static fn (array $r): array => [
        'name' => $r['name'],
        'detail' => $r['detail'],
    ], $failed),
    'results' => $results,
    'doc' => 'hospital_portal/docs/WHATSAPP_MESSAGE_TEMPLATES.md',
    'ran_at' => date('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
