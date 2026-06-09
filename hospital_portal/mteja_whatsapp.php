<?php
declare(strict_types=1);

/**
 * Mteja WhatsApp template broadcast API.
 * @see https://api.sentry.mteja.io/api/whatsapp-template
 */

function mteja_whatsapp_enabled(): bool
{
    return defined('WHATSAPP_PROVIDER')
        && WHATSAPP_PROVIDER === 'mteja'
        && defined('MTEJA_API_KEY')
        && MTEJA_API_KEY !== ''
        && defined('MTEJA_APP_ID')
        && MTEJA_APP_ID !== ''
        && defined('MTEJA_VIRTUAL_NUMBER')
        && MTEJA_VIRTUAL_NUMBER !== '';
}

function mteja_lang_code(string $lang): string
{
    $lang = $lang === 'sw' ? 'sw' : 'en';
    if ($lang === 'sw' && defined('MTEJA_LANG_CODE_SW') && MTEJA_LANG_CODE_SW !== '') {
        return MTEJA_LANG_CODE_SW;
    }
    if ($lang === 'en' && defined('MTEJA_LANG_CODE_EN') && MTEJA_LANG_CODE_EN !== '') {
        return MTEJA_LANG_CODE_EN;
    }
    return $lang;
}

function mteja_template_suffix(string $lang): string
{
    return $lang === 'sw' ? 'sw' : 'en';
}

function mteja_body_component(array $textParams = []): array
{
    $parameters = [];
    foreach ($textParams as $text) {
        $parameters[] = ['type' => 'text', 'text' => (string) $text];
    }
    return ['type' => 'body', 'parameters' => $parameters];
}

/** Static templates (no {{1}} vars) should send empty components per Mteja/Meta. */
function mteja_build_components(array $textParams = []): array
{
    if ($textParams === []) {
        return [];
    }
    return [mteja_body_component($textParams)];
}

function mteja_template_name(string $base, string $suffix): string
{
    $overrideKey = 'MTEJA_TEMPLATE_' . strtoupper(str_replace('-', '_', $base)) . '_' . strtoupper($suffix);
    if (defined($overrideKey)) {
        $override = constant($overrideKey);
        if (is_string($override) && $override !== '') {
            return $override;
        }
    }
    return $base . '_' . $suffix;
}

function mteja_lang_alternates(string $langCode): array
{
    $codes = [$langCode];
    if ($langCode === 'en') {
        $codes[] = 'en_US';
    } elseif ($langCode === 'en_US') {
        $codes[] = 'en';
    } elseif ($langCode === 'sw') {
        $codes[] = 'sw_KE';
    }
    return array_values(array_unique($codes));
}

/**
 * @return array{templateName: string, languageCode: string, components: list<array<string, mixed>>}|null
 */
function mteja_resolve_template(int $patientId, string $messageType, string $body): ?array
{
    require_once __DIR__ . '/afya_rafiki_content.php';

    $lang = function_exists('get_patient_language') ? get_patient_language($patientId) : 'en';
    $suffix = mteja_template_suffix($lang);
    $langCode = mteja_lang_code($lang);
    $name = '';
    if (function_exists('afya_first_name')) {
        $st = db()->prepare('SELECT full_name FROM patients WHERE id = ? LIMIT 1');
        $st->execute([$patientId]);
        $name = afya_first_name((string) ($st->fetchColumn() ?: ''));
    }

    $mk = static function (string $base, array $params = []) use ($suffix, $langCode): array {
        return [
            'templateName' => mteja_template_name($base, $suffix),
            'languageCode' => $langCode,
            'components' => mteja_build_components($params),
        ];
    };

    $bodyLower = mb_strtolower($body);

    if ($messageType === 'welcome') {
        return $mk('afya_welcome');
    }

    if ($messageType === 'appointment_reminder') {
        if (str_contains($bodyLower, 'next week') || str_contains($bodyLower, 'wiki ijayo')) {
            if (preg_match('/\(([^)]+)\)/u', $body, $m)) {
                return $mk('afya_appt_reminder_7d', [$m[1]]);
            }
            return $mk('afya_appt_reminder_7d', ['your scheduled date']);
        }
        if (str_contains($bodyLower, 'tomorrow') || str_contains($bodyLower, 'kesho')) {
            return $mk('afya_appt_reminder_1d');
        }
        if (preg_match('/\(([^)]+)\)/u', $body, $m)) {
            return $mk('afya_appt_reminder_3d', [$m[1]]);
        }
        return $mk('afya_appt_reminder_3d', ['your scheduled date']);
    }

    if ($messageType === 'education_menu' || ($messageType === 'system' && str_contains($bodyLower, 'afya rafiki —'))) {
        return $mk('afya_help_menu');
    }

    if ($messageType === 'escalation_notice') {
        if (str_contains($bodyLower, 'missed') || str_contains($bodyLower, 'nilikosa') || str_contains($bodyLower, 'sikuhudhuria')) {
            return $mk('afya_missed_appt', $name !== '' ? [$name] : ['']);
        }
        if (str_contains($bodyLower, 'received your message') || str_contains($bodyLower, 'tumepokea ujumbe')) {
            return $mk('afya_doctor_reason_ack');
        }
        return $mk('afya_escalation');
    }

    if ($messageType === 'engagement_boost') {
        return $mk('afya_engagement_tip');
    }

    if ($messageType === 'referral') {
        $date = '__________';
        if (preg_match('/Date:\s*(.+)$/mi', $body, $m) || preg_match('/Tarehe:\s*(.+)$/mi', $body, $m)) {
            $date = trim($m[1]);
        }
        return $mk('afya_via_referral', $name !== '' ? [$name, $date] : ['', $date]);
    }

    if ($messageType === 'system' || $messageType === 'ai_reply') {
        if (str_contains($bodyLower, 'hpv test result is negative') || str_contains($bodyLower, 'majibu yako ya hpv ni hasi')) {
            $hiv = function_exists('afya_patient_hiv_status') ? afya_patient_hiv_status($patientId) : 'negative';
            $base = $hiv === 'positive' ? 'afya_hpv_neg_hivpos' : 'afya_hpv_neg_hivneg';
            return $mk($base, $name !== '' ? [$name] : ['']);
        }
        if (str_contains($bodyLower, 'hpv test result is positive') || str_contains($bodyLower, 'majibu yako ya kipimo cha hpv ni chanya')) {
            $date = function_exists('afya_next_appointment_display') ? afya_next_appointment_display($patientId) : '__________';
            if (preg_match('/Date:\s*(.+)$/mi', $body, $m) || preg_match('/Tarehe:\s*(.+)$/mi', $body, $m)) {
                $date = trim($m[1]);
            }
            return $mk('afya_hpv_positive', $name !== '' ? [$name, $date] : ['', $date]);
        }
        if (str_contains($bodyLower, 'would like to speak with a health specialist')
            || str_contains($bodyLower, 'ungependa kuongea na mhudumu')) {
            return $mk('afya_doctor_reason_ask');
        }
        if (str_contains($bodyLower, 'still waiting for a short message') || str_contains($bodyLower, 'tunasubiri ujumbe mfupi')) {
            return $mk('afya_doctor_reason_remind');
        }
        if (str_contains($bodyLower, 'already have your request') || str_contains($bodyLower, 'tayari tumepokea ombi')) {
            return $mk('afya_doctor_already');
        }
        if (str_contains($bodyLower, 'appointment at') && str_contains($bodyLower, 'booked')) {
            $date = 'TBD';
            if (preg_match('/Date\/Time:\s*(.+)$/mi', $body, $m) || preg_match('/Tarehe\/Saa:\s*(.+)$/mi', $body, $m)) {
                $date = trim($m[1]);
            }
            return $mk('afya_appt_booked', $name !== '' ? [$name, $date] : ['', $date]);
        }
        if (str_contains($bodyLower, 'has been updated') || str_contains($bodyLower, 'imebadilishwa')) {
            $date = 'TBD';
            if (preg_match('/Date\/Time:\s*(.+)$/mi', $body, $m) || preg_match('/Tarehe\/Saa:\s*(.+)$/mi', $body, $m)) {
                $date = trim($m[1]);
            }
            return $mk('afya_appt_updated', $name !== '' ? [$name, $date] : ['', $date]);
        }

        $faq = afya_faq_reply($body, $lang);
        if ($faq !== null) {
            if (preg_match('/\bwhat is hpv\b/ui', $body) || trim($body) === '1') {
                return $mk('afya_faq_hpv');
            }
            if (preg_match('/cervical cancer|saratani/ui', $body) || trim($body) === '2') {
                return $mk('afya_faq_cancer');
            }
            if (preg_match('/treated|inatibika/ui', $body) || trim($body) === '3') {
                return $mk('afya_faq_treat');
            }
            if (preg_match('/appointment|miadi/ui', $body) || trim($body) === '4') {
                return $mk('afya_faq_appt');
            }
            if (preg_match('/symptoms of hpv|dalili za hpv/ui', $body) || trim($body) === '5') {
                return $mk('afya_faq_symptoms_hpv');
            }
            if (preg_match('/cervical cancer symptoms|dalili za saratani/ui', $body) || trim($body) === '6') {
                return $mk('afya_faq_symptoms_cc');
            }
            return $mk('afya_help_menu');
        }
    }

    return $mk('afya_fallback');
}

/**
 * @param list<array<string, mixed>> $components
 * @return array{ok: bool, message_id: ?string, error: ?string}
 */
function mteja_whatsapp_send_template(
    string $customerE164,
    string $templateName,
    string $languageCode,
    array $components = []
): array {
    if (!mteja_whatsapp_enabled()) {
        return ['ok' => false, 'message_id' => null, 'error' => 'Mteja WhatsApp API not configured'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message_id' => null, 'error' => 'PHP cURL extension is not enabled'];
    }

    $customer = normalize_outbound_address($customerE164);
    if ($customer === '') {
        return ['ok' => false, 'message_id' => null, 'error' => 'Invalid recipient phone number'];
    }

    $virtual = normalize_outbound_address(MTEJA_VIRTUAL_NUMBER);
    $requestId = bin2hex(random_bytes(16));

    $url = defined('MTEJA_API_URL') && MTEJA_API_URL !== ''
        ? MTEJA_API_URL
        : 'https://api.sentry.mteja.io/api/whatsapp-template';

    $virtualVariants = array_values(array_unique(array_filter([
        $virtual,
        ltrim($virtual, '+'),
        preg_replace('/^\+254/', '0', $virtual) ?: null,
    ])));

    $attempt = static function (array $payload) use ($url, $requestId): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-App-ID: ' . MTEJA_APP_ID,
                'X-API-Key: ' . MTEJA_API_KEY,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false) {
            return [
                'ok' => false,
                'message_id' => null,
                'error' => $err !== '' ? $err : 'cURL error',
                'response' => null,
            ];
        }

        $json = json_decode($raw, true);
        $messageId = is_array($json) ? (string) ($json['requestId'] ?? $requestId) : $requestId;

        if ($code >= 200 && $code < 300 && is_array($json) && !empty($json['success'])) {
            return ['ok' => true, 'message_id' => $messageId, 'error' => null, 'response' => $json];
        }

        $reason = is_array($json) ? (string) ($json['reason'] ?? json_encode($json)) : $raw;
        return [
            'ok' => false,
            'message_id' => $messageId,
            'error' => 'HTTP ' . $code . ': ' . $reason
                . ' [template=' . ($payload['templateName'] ?? '?')
                . ' lang=' . ($payload['languageCode'] ?? '?')
                . ' to=' . ($payload['customerNumber'] ?? '?') . ']',
            'response' => is_array($json) ? $json : null,
        ];
    };

    $basePayload = [
        'appId' => (int) MTEJA_APP_ID,
        'userId' => 0,
        'virtualNumber' => $virtual,
        'customerNumber' => $customer,
        'templateName' => $templateName,
        'languageCode' => $languageCode,
        'requestId' => $requestId,
    ];

    // Try empty components first (static templates), then explicit body if needed.
    $componentVariants = [$components];
    if ($components === []) {
        $componentVariants[] = [mteja_body_component([])];
    }

    $langVariants = mteja_lang_alternates($languageCode);
    $last = ['ok' => false, 'message_id' => null, 'error' => 'Mteja send failed'];

    foreach ($virtualVariants as $virtualTry) {
        foreach ($langVariants as $langTry) {
            foreach ($componentVariants as $compTry) {
                $payload = $basePayload;
                $payload['virtualNumber'] = $virtualTry;
                $payload['languageCode'] = $langTry;
                $payload['components'] = $compTry;
                $payload['requestId'] = bin2hex(random_bytes(16));

                error_log('MTEJA_SEND: ' . json_encode([
                    'template' => $templateName,
                    'lang' => $langTry,
                    'virtual' => $virtualTry,
                    'to' => $customer,
                    'components' => count($compTry),
                ]));

                $last = $attempt($payload);
                if ($last['ok']) {
                    return [
                        'ok' => true,
                        'message_id' => $last['message_id'],
                        'error' => null,
                    ];
                }
            }
        }
    }

    return [
        'ok' => false,
        'message_id' => $last['message_id'] ?? null,
        'error' => ($last['error'] ?? 'Mteja send failed')
            . ' — verify in Mteja: template name afya_welcome_en, language en, number +254142830423',
    ];
}

/**
 * @return array{ok: bool, message_id: ?string, error: ?string}
 */
function mteja_whatsapp_send_patient(int $patientId, string $customerE164, string $messageType, string $body): array
{
    $resolved = mteja_resolve_template($patientId, $messageType, $body);
    if ($resolved === null) {
        return ['ok' => false, 'message_id' => null, 'error' => 'No Mteja template mapping for message type: ' . $messageType];
    }

    error_log('MTEJA_TEMPLATE: ' . $resolved['templateName'] . ' lang=' . $resolved['languageCode']);

    return mteja_whatsapp_send_template(
        $customerE164,
        $resolved['templateName'],
        $resolved['languageCode'],
        $resolved['components']
    );
}
