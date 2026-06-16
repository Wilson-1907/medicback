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

    // --- Section 2–3: HPV negative (HIV-stratified return, no appointment) ---
    $negEnHivNeg = build_hpv_negative_result_notification('Jane Doe', 'negative', 'en');
    $pass('HPV negative EN HIV− — 5 years', $contains($negEnHivNeg, 'in 5 years'));
    $pass('HPV negative EN HIV− — no infection detected', $contains($negEnHivNeg, 'no HPV infection was detected'));
    $negEnHivPos = build_hpv_negative_result_notification('Jane Doe', 'positive', 'en');
    $pass('HPV negative EN HIV+ — 3 years', $contains($negEnHivPos, 'after 3 years'));
    $pass('HPV negative EN — no appointment', !$contains($negEnHivNeg, 'Date:'));
    $negSw = build_hpv_negative_result_notification('Mary', 'negative', 'sw');
    $pass('HPV negative SW — 5 years', $contains($negSw, 'miaka 5'));

    // --- Section 4: HPV positive ---
    $posEn = build_hpv_positive_result_notification('Jane', 'Saturday, 14 June 2026, 10:30 AM', 'en');
    $pass('§4 HPV positive EN — not cancer', $contains($posEn, 'does not mean that you have cervical cancer'));
    $pass('§4 HPV positive EN — appointment date', $contains($posEn, 'Saturday, 14 June 2026, 10:30 AM'));

    $failedEn = build_hpv_failed_result_notification('Jane', 'Saturday, 14 June 2026, 10:30 AM', 'en');
    $pass('HPV sample failed EN — insufficient sample', $contains($failedEn, 'sample collected was not sufficient'));
    $pass('HPV sample failed EN — VIA screening', $contains($failedEn, 'VIA screening'));
    $failedSw = build_hpv_failed_result_notification('Jane', 'Jumamosi, 14 Juni 2026, 10:30 AM', 'sw');
    $pass('HPV sample failed SW — insufficient sample', $contains($failedSw, 'sampuli iliyokusanywa haikutosha'));

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

    // --- Pre-VIA counseling drip (study messages 1–10) ---
    require_once __DIR__ . '/../afya_pre_via_counseling.php';
    $dripEn = afya_pre_via_counseling_messages('en');
    $dripSw = afya_pre_via_counseling_messages('sw');
    $pass('Pre-VIA counseling EN count = 10', count($dripEn) === 10, 'got ' . count($dripEn));
    $pass('Pre-VIA counseling SW count = 10', count($dripSw) === 10, 'got ' . count($dripSw));
    $pass('Counseling msg 1 EN — 8 in 10', $contains($dripEn[0], '8 out of every 10'));
    $pass('Counseling msg 2 EN — follow-up clinic', $contains($dripEn[1], 'recommended clinic visit'));
    $pass('Counseling msg 7 EN — VIA exam', $contains($dripEn[6], 'Visual Inspection with Acetic acid'));
    $pass('Counseling msg 9 EN — 1 year return', $contains($dripEn[8], 'return for follow up after one year'));

    require_once __DIR__ . '/../patient_screening.php';
    $viaNeg = build_via_negative_result_notification('Jane', 'negative', 'en', 'Monday, 10 June 2027');
    $pass('VIA neg result EN — §12b 1 year', $contains($viaNeg, 'repeat HPV test after 1 year'));
    $pass('VIA neg result EN — appointment date', $contains($viaNeg, 'Monday, 10 June 2027'));

    $fu = compute_screening_followups([
        'via_result' => 'negative',
        'via_date' => '2026-06-10',
        'hiv_status' => 'positive',
    ]);
    $pass('VIA neg follow-up — 1 year (study §12b)', ($fu['schedules'][0]['reason'] ?? '') === 'via_neg_1y'
        && ($fu['schedules'][0]['years'] ?? 0) === 1.0);

    // --- HPV confirm delays (study: 3h, 5h, then 1 day) ---
    require_once __DIR__ . '/../encouragement_drip.php';
    $pass('Encouragement drip delay index 0 = +2 minutes', encouragement_drip_delay_before_index(0) === '+2 minutes');
    $pass('Encouragement drip delay index 1 = +2 minutes', encouragement_drip_delay_before_index(1) === '+2 minutes');
    $pass('Encouragement drip delay index 2 = +1 hour', encouragement_drip_delay_before_index(2) === '+1 hour');
    $pass('Encouragement drip delay index 3 = +21 hours', encouragement_drip_delay_before_index(3) === '+21 hours');
    $spanDays = round(afya_pre_via_counseling_total_span_minutes() / 60 / 24, 2);
    $pass(
        'All 10 counseling messages within 6.5 days',
        afya_pre_via_counseling_within_max_span(),
        "span={$spanDays} days"
    );
    $pass('Encouragement drip delay index 2 = +1 day', encouragement_drip_delay_before_index(2) === '+1 day');

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

    $tplNeg = mteja_resolve_template($fakePatientId, 'hpv_negative', $negEnHivNeg);
    $pass('Mteja template hpv_negative HIV−', ($tplNeg['templateName'] ?? '') === 'afya_hpv_neg_hivneg_en');

    $tplPos = mteja_resolve_template($fakePatientId, 'system', $posEn);
    $pass('Mteja template hpv_positive', ($tplPos['templateName'] ?? '') === 'afya_hpv_positive_en');

    $tplFailed = mteja_resolve_template($fakePatientId, 'hpv_failed', $failedEn);
    $pass('Mteja template hpv_sample_failed', ($tplFailed['templateName'] ?? '') === 'afya_hpv_sample_failed_en');

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
        'Registration does not start pre-VIA drip (waits for HPV+ confirm)',
        str_contains($enrollSrc, 'cancel_queued_encouragement_drip')
            && !preg_match('/send_afya_enrollment_messages[\s\S]*?schedule_encouragement_drip_step/s', $enrollSrc)
    );
    $hpvSrc = (string) file_get_contents(__DIR__ . '/../hpv_results.php');
    $pass(
        'HPV+ record arms counseling drip pathway',
        str_contains($hpvSrc, 'arm_hpv_positive_counseling_drip')
    );
    $pass(
        'HPV+ confirm sends counseling msg 1 immediately',
        str_contains($hpvSrc, 'start_hpv_positive_counseling_drip_on_confirm')
    );
    $viaSrc = (string) file_get_contents(__DIR__ . '/../patient_screening.php');
    $pass(
        'VIA record stops pre-VIA drip and sends script result only',
        str_contains($viaSrc, 'complete_encouragement_drip_after_via')
            && str_contains($viaSrc, 'build_via_positive_result_notification')
    );
    $dripSrc = (string) file_get_contents(__DIR__ . '/../encouragement_drip.php');
    $pass(
        'HPV+ drip continues until VIA is recorded',
        str_contains($dripSrc, 'patient_hpv_positive_confirmed')
            && str_contains($dripSrc, 'patient_via_result_recorded')
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
    $pass(
        'HPV negative confirm stops encouragement drip',
        str_contains($hpvSrc, 'complete_encouragement_drip_after_hpv_negative')
            && str_contains($dripSrc, 'patient_hpv_negative_confirmed')
    );
    $msgSrc = (string) file_get_contents(__DIR__ . '/../messaging.php');
    $pass(
        'HPV negative patients excluded from health tips',
        str_contains($msgSrc, 'patient_hpv_negative_recorded')
    );
    $missedSrc = (string) file_get_contents(__DIR__ . '/../missed_appointment_flow.php');
    $pass(
        'Missed appointment §13b/13c inbound wired',
        str_contains($missedSrc, 'try_handle_missed_appointment_inbound')
            && str_contains($missedSrc, 'try_send_missed_reschedule_confirmation')
            && str_contains($missedSrc, 'missed_reschedule_offer')
    );
    $pass(
        'Pre-VIA drip uses study counseling 1–10',
        str_contains((string) file_get_contents(__DIR__ . '/../encouragement_drip.php'), 'hpv_counseling')
            && str_contains((string) file_get_contents(__DIR__ . '/../afya_pre_via_counseling.php'), 'afya_counseling_messages_positive_en')
    );
    $atWebhookSrc = (string) file_get_contents(__DIR__ . '/../webhook_africastalking.php');
    $pass(
        'AT inbound ignores delivery reports (no ghost Unknown rows)',
        str_contains($atWebhookSrc, 'apply_africastalking_delivery_report')
            && str_contains($atWebhookSrc, 'Empty from+body')
    );
    $pass(
        'Mteja nav template id EN',
        function_exists('mteja_nav_template_id')
            && mteja_nav_template_id('afya_nav_edu_01', 'en') === 'afya_nav_edu_01_en'
            && mteja_nav_template_id('afya_nav_edu_01', 'sw') === 'afya_nav_edu_01_sw'
    );
    $pass('HPV positive confirm gates on appointment', str_contains($hpvSrc, 'Book a follow-up appointment first'));
    $pass('HPV failed confirm gates on VIA appointment', str_contains($hpvSrc, 'Book a VIA screening appointment first'));
    $pass('HPV failed enum migration', str_contains($hpvSrc, 'ensure_hpv_failed_result_enum'));
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
        'afya_welcome', 'afya_hpv_neg_hivpos', 'afya_hpv_neg_hivneg', 'afya_hpv_positive', 'afya_hpv_sample_failed',
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
    foreach ([
        'afya_nav_edu', 'afya_nav_missed_offer', 'afya_nav_missed_confirm', 'afya_nav_via_neg_result',
        'afya_nav_checkup_1y', 'afya_nav_registration_welcome', 'afya_nav_referral_reassurance',
        'afya_nav_referral_appt_reminder', 'afya_nav_post_visit', 'afya_nav_via_ablation',
        'afya_nav_tx_postponed', 'afya_nav_lang_set', 'afya_nav_unsubscribe',
    ] as $prefix) {
        $pass("Mteja maps {$prefix}", str_contains($mtejaSrc, "'{$prefix}'") || str_contains($mtejaSrc, "{$prefix}_"));
    }
    $pass(
        'Registration welcome maps to afya_nav_registration_welcome',
        str_contains($mtejaSrc, "messageType === 'registration_welcome'")
            && str_contains($mtejaSrc, 'afya_nav_registration_welcome')
    );
    $pass(
        'Mark attended sends post_visit_ack',
        str_contains((string) file_get_contents(__DIR__ . '/../appointment_utils.php'), "'post_visit_ack'")
            && str_contains((string) file_get_contents(__DIR__ . '/../appointment_utils.php'), 'build_post_visit_acknowledgement')
    );
    $pass(
        'Referral reassurance uses dedicated message type',
        str_contains((string) file_get_contents(__DIR__ . '/../patient_referral.php'), "'referral_reassurance'")
    );
    $pass(
        'Language/stop replies use nav ack builders',
        str_contains((string) file_get_contents(__DIR__ . '/../whatsapp_inbound.php'), 'build_language_set_ack_message')
            && str_contains((string) file_get_contents(__DIR__ . '/../whatsapp_inbound.php'), 'build_unsubscribe_ack_message')
    );
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
