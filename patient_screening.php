<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/hpv_results.php';
require_once __DIR__ . '/scheduled_messages.php';
require_once __DIR__ . '/messaging.php';

const NYERI_REFERRAL_HOSPITAL = 'Nyeri County Referral Hospital';

/** @return bool */
function ensure_patient_screening_schema(): bool
{
    try {
        $pdo = db();
        $cols = [
            'hiv_status' => "ENUM('unknown','negative','positive') NOT NULL DEFAULT 'unknown'",
            'hpv_done_before' => "ENUM('unknown','no','yes') NOT NULL DEFAULT 'unknown'",
            'hpv_prior_result' => "ENUM('unknown','negative','positive') NOT NULL DEFAULT 'unknown'",
            'place_of_residence' => 'VARCHAR(255) NULL',
            'via_result' => "ENUM('unknown','not_done','negative','positive') NOT NULL DEFAULT 'unknown'",
            'via_date' => 'DATE NULL',
            'has_cancer' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'treatment_date' => 'DATE NULL',
            'next_checkup_at' => 'DATE NULL',
        ];
        foreach ($cols as $column => $definition) {
            if (!db_table_has_column('patients', $column)) {
                $pdo->exec("ALTER TABLE patients ADD COLUMN {$column} {$definition}");
            }
        }
        return true;
    } catch (Throwable $e) {
        error_log('ensure_patient_screening_schema: ' . $e->getMessage());
        return false;
    }
}

function patient_screening_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = db_table_has_column('patients', 'hiv_status')
        && db_table_has_column('patients', 'via_result');
    return $ready;
}

/** @return string[] */
function patient_screening_select_columns(): array
{
    if (!patient_screening_ready()) {
        return [];
    }
    return [
        'hiv_status',
        'hpv_done_before',
        'hpv_prior_result',
        'place_of_residence',
        'via_result',
        'via_date',
        'has_cancer',
        'treatment_date',
        'next_checkup_at',
    ];
}

/**
 * @param array<string, mixed> $body
 * @return array<string, mixed>
 */
function parse_screening_from_body(array $body): array
{
    $hiv = strtolower(trim((string) ($body['hiv_status'] ?? 'unknown')));
    if (!in_array($hiv, ['negative', 'positive'], true)) {
        $hiv = 'unknown';
    }

    $hpvDone = strtolower(trim((string) ($body['hpv_done_before'] ?? 'unknown')));
    if (!in_array($hpvDone, ['yes', 'no'], true)) {
        $hpvDone = 'unknown';
    }

    $hpvPrior = strtolower(trim((string) ($body['hpv_prior_result'] ?? 'unknown')));
    if (!in_array($hpvPrior, ['negative', 'positive'], true)) {
        $hpvPrior = 'unknown';
    }
    if ($hpvDone !== 'yes') {
        $hpvPrior = 'unknown';
    }

    $via = strtolower(trim((string) ($body['via_result'] ?? 'unknown')));
    if (!in_array($via, ['not_done', 'negative', 'positive'], true)) {
        $via = 'unknown';
    }

    $viaDate = trim((string) ($body['via_date'] ?? ''));
    $viaDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $viaDate) ? $viaDate : null;

    $treatmentDate = trim((string) ($body['treatment_date'] ?? ''));
    $treatmentDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $treatmentDate) ? $treatmentDate : null;

    $hasCancer = !empty($body['has_cancer']) && ($via === 'positive');

    return [
        'hiv_status' => $hiv,
        'hpv_done_before' => $hpvDone,
        'hpv_prior_result' => $hpvPrior,
        'place_of_residence' => trim((string) ($body['place_of_residence'] ?? '')),
        'via_result' => $via,
        'via_date' => $viaDate,
        'has_cancer' => $hasCancer ? 1 : 0,
        'treatment_date' => $treatmentDate,
    ];
}

/**
 * @param array<string, mixed> $screening
 * @return string|null
 */
function validate_screening_registration(array $screening): ?string
{
    if ($screening['hiv_status'] === 'unknown') {
        return 'HIV status is required (positive or negative).';
    }
    if ($screening['hpv_done_before'] === 'unknown') {
        return 'Please indicate if HPV screening was ever done before.';
    }
    if ($screening['hpv_done_before'] === 'yes' && $screening['hpv_prior_result'] === 'unknown') {
        return 'Please record the prior HPV result (positive or negative).';
    }
    if ($screening['place_of_residence'] === '') {
        return 'Place of residence is required.';
    }
    if ($screening['via_result'] === 'unknown') {
        return 'VIA result is required.';
    }
    if (in_array($screening['via_result'], ['positive', 'negative'], true) && $screening['via_date'] === null) {
        return 'Date of VIA is required when a VIA result is recorded.';
    }
    return null;
}

/**
 * Follow-up rules:
 * - VIA negative → clinic check-up in 1 year
 * - VIA positive + cancer → immediate referral SMS (Nyeri County Referral Hospital)
 * - HIV positive + HPV negative → check-up in 5 years
 * - HIV positive + HPV positive → check-up in 3 years
 *
 * @param array<string, mixed> $screening
 * @return array{next_checkup_at: ?string, schedules: list<array{years: float, reason: string, send_at: string}>}
 */
function compute_screening_followups(array $screening): array
{
    $anchor = $screening['via_date'] ?? date('Y-m-d');
    $schedules = [];

    if ($screening['via_result'] === 'negative') {
        $schedules[] = [
            'years' => 1.0,
            'reason' => 'via_negative_1y',
            'send_at' => date('Y-m-d H:i:s', strtotime($anchor . ' +1 year')),
        ];
    }

    $hiv = $screening['hiv_status'];
    $hpv = $screening['hpv_prior_result'];
    $hpvDone = $screening['hpv_done_before'];
    if ($hiv === 'positive' && $hpvDone === 'yes' && $hpv === 'negative') {
        $schedules[] = [
            'years' => 5.0,
            'reason' => 'hiv_pos_hpv_neg_5y',
            'send_at' => date('Y-m-d H:i:s', strtotime($anchor . ' +5 years')),
        ];
    }
    if ($hiv === 'positive' && $hpvDone === 'yes' && $hpv === 'positive') {
        $schedules[] = [
            'years' => 3.0,
            'reason' => 'hiv_pos_hpv_pos_3y',
            'send_at' => date('Y-m-d H:i:s', strtotime($anchor . ' +3 years')),
        ];
    }

    $next = null;
    foreach ($schedules as $s) {
        $d = substr($s['send_at'], 0, 10);
        if ($next === null || $d < $next) {
            $next = $d;
        }
    }

    return ['next_checkup_at' => $next, 'schedules' => $schedules];
}

function build_referral_message(string $patientName, string $lang): string
{
    $hospital = NYERI_REFERRAL_HOSPITAL;
    if ($lang === 'sw') {
        return "Habari {$patientName}, matokeo yako ya VIA yanaonyesha hali inayohitaji uangalizi zaidi. "
            . "Tunakuelekeza kwenda {$hospital} kwa uchunguzi na matibabu zaidi. "
            . 'Wasiliana na kliniki yetu ikiwa unahitaji usafiri au msaada.';
    }
    return "Hello {$patientName}, your VIA results indicate a condition that needs further care. "
        . "You are referred to {$hospital} for further assessment and treatment. "
        . 'Contact our clinic if you need travel support or assistance.';
}

function build_checkup_reminder_message(
    string $patientName,
    string $checkupDate,
    string $lang,
    string $reasonKey
): string {
    $dateStr = $checkupDate;
    $hospital = defined('HOSPITAL_NAME') ? HOSPITAL_NAME : 'Nyeri Level 4 Hospital';

    if ($lang === 'sw') {
        if ($reasonKey === 'via_negative_1y') {
            return "Habari {$patientName}, matokeo yako ya VIA yalikuwa hasi. Tafadhali rudi {$hospital} kwa uchunguzi wa mwaka tarehe {$dateStr}.";
        }
        if ($reasonKey === 'hiv_pos_hpv_neg_5y') {
            return "Habari {$patientName}, kwa ufuatiliaji (VVU chanya, HPV hasi), tafadhali rudi kliniki tarehe {$dateStr} kwa uchunguzi.";
        }
        if ($reasonKey === 'hiv_pos_hpv_pos_3y') {
            return "Habari {$patientName}, kwa ufuatiliaji (VVU chanya, HPV chanya), tafadhali rudi kliniki tarehe {$dateStr} kwa uchunguzi.";
        }
        return "Habari {$patientName}, tafadhali rudi {$hospital} kwa uchunguzi tarehe {$dateStr}.";
    }

    if ($reasonKey === 'via_negative_1y') {
        return "Hello {$patientName}, your VIA result was negative. Please return to {$hospital} for your annual check-up on {$dateStr}.";
    }
    if ($reasonKey === 'hiv_pos_hpv_neg_5y') {
        return "Hello {$patientName}, for follow-up (HIV positive, HPV negative), please visit the clinic on {$dateStr} for a check-up.";
    }
    if ($reasonKey === 'hiv_pos_hpv_pos_3y') {
        return "Hello {$patientName}, for follow-up (HIV positive, HPV positive), please visit the clinic on {$dateStr} for a check-up.";
    }
    return "Hello {$patientName}, please return to {$hospital} for a check-up on {$dateStr}.";
}

/**
 * @param array<string, mixed> $screening
 */
function process_registration_screening_messages(
    int $patientId,
    string $patientName,
    string $lang,
    array $screening,
    bool $optedIn
): void {
    if (!$optedIn || !patient_screening_ready()) {
        return;
    }

    ensure_patient_screening_schema();

    if ($screening['via_result'] === 'positive' && !empty($screening['has_cancer'])) {
        send_patient_message(
            $patientId,
            'referral',
            build_referral_message($patientName, $lang)
        );
    }

    $followups = compute_screening_followups($screening);
    foreach ($followups['schedules'] as $item) {
        $checkupDate = substr($item['send_at'], 0, 10);
        $body = build_checkup_reminder_message(
            $patientName,
            $checkupDate,
            $lang,
            (string) $item['reason']
        );
        schedule_patient_message_at(
            $patientId,
            'checkup_reminder',
            $body,
            (string) $item['send_at']
        );
    }
}
