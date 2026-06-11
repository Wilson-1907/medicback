<?php
declare(strict_types=1);

/**
 * Short encouragement drip — works without HPV confirm (registration, VIA, HPV+).
 * Uses patients.hpv_counseling_index as drip position and scheduled_messages chain flag.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/scheduled_messages.php';
require_once __DIR__ . '/afya_pre_via_counseling.php';

function encouragement_drip_message_count(string $lang = 'en'): int
{
    return afya_pre_via_counseling_count($lang);
}

function get_encouragement_drip_message_at_index(int $patientId, int $index, ?string $lang = null): ?string
{
    if ($lang === null) {
        $st = db()->prepare('SELECT preferred_language FROM patients WHERE id = ? LIMIT 1');
        $st->execute([$patientId]);
        $lang = (string) ($st->fetchColumn() ?: 'en');
        $lang = in_array($lang, ['en', 'sw'], true) ? $lang : 'en';
    }
    $messages = afya_pre_via_counseling_messages($lang);
    $count = count($messages);
    if ($count < 1) {
        return null;
    }
    if ($index >= $count) {
        return $messages[$count - 1];
    }
    return $messages[$index] ?? null;
}

function patient_via_result_recorded(int $patientId): bool
{
    if (!db_table_has_column('patients', 'via_result')) {
        return false;
    }
    $st = db()->prepare('SELECT via_result FROM patients WHERE id = ? LIMIT 1');
    $st->execute([$patientId]);
    $via = strtolower((string) ($st->fetchColumn() ?: ''));
    return in_array($via, ['positive', 'negative'], true);
}

function patient_hpv_positive_confirmed(int $patientId): bool
{
    if (!db_table_has_column('patients', 'hpv_screening_result')) {
        return false;
    }
    $st = db()->prepare(
        'SELECT hpv_screening_result, hpv_result_confirmed_at FROM patients WHERE id = ? LIMIT 1'
    );
    $st->execute([$patientId]);
    $row = $st->fetch();
    if (!$row) {
        return false;
    }
    return strtolower((string) ($row['hpv_screening_result'] ?? '')) === 'positive'
        && !empty($row['hpv_result_confirmed_at']);
}

function patient_hpv_positive_recorded(int $patientId): bool
{
    if (!db_table_has_column('patients', 'hpv_screening_result')) {
        return false;
    }
    $st = db()->prepare(
        'SELECT hpv_screening_result, hpv_result_recorded_at FROM patients WHERE id = ? LIMIT 1'
    );
    $st->execute([$patientId]);
    $row = $st->fetch();
    if (!$row) {
        return false;
    }

    return strtolower((string) ($row['hpv_screening_result'] ?? '')) === 'positive'
        && !empty($row['hpv_result_recorded_at']);
}

/** HPV+ pathway active — recorded or confirmed, until VIA or HPV negative confirm. */
function patient_hpv_positive_for_drip(int $patientId): bool
{
    if (patient_via_result_recorded($patientId) || patient_hpv_negative_confirmed($patientId)) {
        return false;
    }

    return patient_hpv_positive_recorded($patientId);
}

function patient_hpv_negative_confirmed(int $patientId): bool
{
    if (!db_table_has_column('patients', 'hpv_screening_result')) {
        return false;
    }
    $st = db()->prepare(
        'SELECT hpv_screening_result, hpv_result_confirmed_at FROM patients WHERE id = ? LIMIT 1'
    );
    $st->execute([$patientId]);
    $row = $st->fetch();
    if (!$row) {
        return false;
    }
    return strtolower((string) ($row['hpv_screening_result'] ?? '')) === 'negative'
        && !empty($row['hpv_result_confirmed_at']);
}

/** Drip stops when VIA is recorded, HPV negative is confirmed, or the FAQ sequence ended (non HPV+). */
function encouragement_drip_pathway_complete(int $patientId): bool
{
    if (patient_via_result_recorded($patientId)) {
        return true;
    }
    if (patient_hpv_negative_confirmed($patientId)) {
        return true;
    }

    $st = db()->prepare('SELECT preferred_language, hpv_counseling_index FROM patients WHERE id = ? LIMIT 1');
    $st->execute([$patientId]);
    $row = $st->fetch();
    if (!$row) {
        return true;
    }

    if (patient_hpv_positive_confirmed($patientId)) {
        return false;
    }

    $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    $index = (int) ($row['hpv_counseling_index'] ?? 0);
    return $index >= encouragement_drip_message_count($lang);
}

function encouragement_drip_delay_before_index(int $index): string
{
    return afya_pre_via_counseling_delay_before_index($index);
}

function patient_has_queued_encouragement_drip(int $patientId): bool
{
    if (!scheduled_messages_has_counseling_chain_column()) {
        return false;
    }
    $st = db()->prepare(
        "SELECT 1 FROM scheduled_messages
         WHERE patient_id = ? AND status = 'queued' AND triggers_counseling_chain = 1
         LIMIT 1"
    );
    $st->execute([$patientId]);
    return (bool) $st->fetchColumn();
}

function cancel_queued_encouragement_drip(int $patientId): void
{
    if (!scheduled_messages_has_counseling_chain_column()) {
        return;
    }
    db()->prepare(
        "UPDATE scheduled_messages SET status = 'cancelled'
         WHERE patient_id = ? AND status = 'queued' AND triggers_counseling_chain = 1"
    )->execute([$patientId]);
}

function reset_encouragement_drip_index(int $patientId): void
{
    if (!db_table_has_column('patients', 'hpv_counseling_index')) {
        return;
    }
    db()->prepare('UPDATE patients SET hpv_counseling_index = 0 WHERE id = ?')->execute([$patientId]);
}

/** Schedule the next pre-VIA counseling tip (HPV+ confirmed only). */
function schedule_encouragement_drip_step(int $patientId, ?string $delayExpression = null): bool
{
    if (patient_via_result_recorded($patientId)) {
        return false;
    }
    if (!patient_hpv_positive_for_drip($patientId)) {
        return false;
    }
    if (encouragement_drip_pathway_complete($patientId)) {
        return false;
    }
    if (patient_has_queued_encouragement_drip($patientId)) {
        return false;
    }

    $st = db()->prepare('SELECT preferred_language, hpv_counseling_index FROM patients WHERE id = ? LIMIT 1');
    $st->execute([$patientId]);
    $row = $st->fetch();
    if (!$row) {
        return false;
    }

    $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    $index = (int) ($row['hpv_counseling_index'] ?? 0);
    $count = encouragement_drip_message_count($lang);
    $msg = get_encouragement_drip_message_at_index($patientId, $index, $lang);
    if ($msg === null || trim($msg) === '') {
        return false;
    }

    if ($delayExpression !== null) {
        $delay = $delayExpression;
    } elseif ($index >= $count) {
        $delay = '+2 days';
    } else {
        $delay = encouragement_drip_delay_before_index($index);
    }
    schedule_patient_message($patientId, 'hpv_counseling', $msg, $delay, true);
    if (function_exists('maybe_flush_due_scheduled_messages')) {
        maybe_flush_due_scheduled_messages(30);
    }
    return true;
}

/** Called by cron after each chained encouragement message is sent. */
function encouragement_drip_step_sent(int $patientId): void
{
    if (!db_table_has_column('patients', 'hpv_counseling_index')) {
        return;
    }
    if (patient_via_result_recorded($patientId) || patient_hpv_negative_confirmed($patientId)) {
        return;
    }

    $st = db()->prepare('SELECT preferred_language, hpv_counseling_index FROM patients WHERE id = ? LIMIT 1');
    $st->execute([$patientId]);
    $row = $st->fetch();
    if (!$row) {
        return;
    }

    $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    $count = encouragement_drip_message_count($lang);
    $index = (int) ($row['hpv_counseling_index'] ?? 0);

    if ($index < $count - 1) {
        db()->prepare(
            'UPDATE patients SET hpv_counseling_index = hpv_counseling_index + 1 WHERE id = ?'
        )->execute([$patientId]);
        $nextIndex = $index + 1;
        schedule_encouragement_drip_step($patientId, encouragement_drip_delay_before_index($nextIndex));
        return;
    }

    if (patient_hpv_positive_confirmed($patientId)) {
        db()->prepare('UPDATE patients SET hpv_counseling_index = ? WHERE id = ?')->execute([$count, $patientId]);
        schedule_encouragement_drip_step($patientId, '+2 days');
        return;
    }

    db()->prepare(
        'UPDATE patients SET hpv_counseling_index = hpv_counseling_index + 1 WHERE id = ?'
    )->execute([$patientId]);
    if (!encouragement_drip_pathway_complete($patientId)) {
        schedule_encouragement_drip_step($patientId, encouragement_drip_delay_before_index($index + 1));
    }
}

/** Arm drip pathway when HPV+ is recorded (no messages until confirm). */
function arm_hpv_positive_counseling_drip(int $patientId): void
{
    cancel_queued_encouragement_drip($patientId);
    reset_encouragement_drip_index($patientId);
}

/**
 * After HPV+ confirm: send counseling msg 1 immediately, queue msg 2 (+2 min) via cron.
 */
function start_hpv_positive_counseling_drip_on_confirm(int $patientId): bool
{
    if (!patient_hpv_positive_for_drip($patientId)) {
        return false;
    }

    $optSt = db()->prepare(
        'SELECT 1 FROM contact_channels WHERE patient_id = ? AND opted_in = 1 LIMIT 1'
    );
    $optSt->execute([$patientId]);
    if (!$optSt->fetchColumn()) {
        return false;
    }

    cancel_queued_encouragement_drip($patientId);
    reset_encouragement_drip_index($patientId);

    $lang = function_exists('get_patient_language') ? get_patient_language($patientId) : 'en';
    $msg = get_encouragement_drip_message_at_index($patientId, 0, $lang);
    if ($msg === null || trim($msg) === '') {
        return false;
    }

    $sent = send_patient_message($patientId, 'hpv_counseling', $msg);
    if (!$sent) {
        return restart_encouragement_drip($patientId, '+2 minutes');
    }

    if (db_table_has_column('patients', 'hpv_counseling_index')) {
        db()->prepare('UPDATE patients SET hpv_counseling_index = 1 WHERE id = ?')->execute([$patientId]);
    }

    $nextDelay = encouragement_drip_delay_before_index(1);
    $scheduled = schedule_encouragement_drip_step($patientId, $nextDelay);
    if (!$scheduled) {
        $msg2 = get_encouragement_drip_message_at_index($patientId, 1, $lang);
        if ($msg2 !== null && trim($msg2) !== '') {
            schedule_patient_message($patientId, 'hpv_counseling', $msg2, $nextDelay, true);
            $scheduled = true;
            error_log('HPV_DRIP: forced queue of counseling msg 2 for patient ' . $patientId);
        } else {
            error_log('HPV_DRIP: counseling msg 2 not queued for patient ' . $patientId);
        }
    }
    if (function_exists('maybe_flush_due_scheduled_messages')) {
        maybe_flush_due_scheduled_messages(30);
    }

    return $sent && $scheduled;
}

/** Restart drip from tip 1 (fallback / repair). */
function restart_encouragement_drip(int $patientId, string $firstDelay = '+2 minutes'): bool
{
    cancel_queued_encouragement_drip($patientId);
    reset_encouragement_drip_index($patientId);
    return schedule_encouragement_drip_step($patientId, $firstDelay);
}

/** Stop HPV FAQ drip once VIA result is recorded (official VIA script SMS is sent separately). */
function complete_encouragement_drip_after_via(int $patientId): void
{
    cancel_queued_encouragement_drip($patientId);
    if (!db_table_has_column('patients', 'hpv_counseling_index')) {
        return;
    }
    $st = db()->prepare('SELECT preferred_language FROM patients WHERE id = ? LIMIT 1');
    $st->execute([$patientId]);
    $langRaw = (string) ($st->fetchColumn() ?: 'en');
    $lang = in_array($langRaw, ['en', 'sw'], true) ? $langRaw : 'en';
    $done = encouragement_drip_message_count($lang);
    db()->prepare('UPDATE patients SET hpv_counseling_index = ? WHERE id = ?')->execute([$done, $patientId]);
}

/** Stop registration drip once HPV negative is confirmed (one result SMS only; no VIA path). */
function complete_encouragement_drip_after_hpv_negative(int $patientId): void
{
    cancel_queued_encouragement_drip($patientId);
    if (!db_table_has_column('patients', 'hpv_counseling_index')) {
        return;
    }
    $st = db()->prepare('SELECT preferred_language FROM patients WHERE id = ? LIMIT 1');
    $st->execute([$patientId]);
    $langRaw = (string) ($st->fetchColumn() ?: 'en');
    $lang = in_array($langRaw, ['en', 'sw'], true) ? $langRaw : 'en';
    $done = encouragement_drip_message_count($lang);
    db()->prepare('UPDATE patients SET hpv_counseling_index = ? WHERE id = ?')->execute([$done, $patientId]);
}

/** Re-queue drip for HPV+ patients with no queued counseling step (cron safety net). */
function repair_stalled_hpv_positive_drips(): int
{
    if (!db_table_has_column('patients', 'hpv_screening_result') || !db_table_has_column('patients', 'via_result')) {
        return 0;
    }

    $repaired = 0;

    $mid = db()->query(
        "SELECT id, hpv_counseling_index FROM patients
         WHERE hpv_screening_result = 'positive'
           AND hpv_result_confirmed_at IS NOT NULL
           AND hpv_counseling_index BETWEEN 1 AND 9
           AND (via_result IS NULL OR via_result = '' OR via_result NOT IN ('positive', 'negative'))"
    )->fetchAll();

    foreach ($mid as $row) {
        $patientId = (int) ($row['id'] ?? 0);
        $index = (int) ($row['hpv_counseling_index'] ?? 0);
        if ($patientId < 1 || patient_has_queued_encouragement_drip($patientId)) {
            continue;
        }
        $delay = encouragement_drip_delay_before_index($index);
        if (schedule_encouragement_drip_step($patientId, $delay)) {
            $repaired++;
            continue;
        }
        $lang = get_patient_language($patientId);
        $msg = get_encouragement_drip_message_at_index($patientId, $index, $lang);
        if ($msg !== null && trim($msg) !== '') {
            schedule_patient_message($patientId, 'hpv_counseling', $msg, $delay, true);
            $repaired++;
        }
    }

    $rows = db()->query(
        "SELECT id FROM patients
         WHERE hpv_screening_result = 'positive'
           AND hpv_result_confirmed_at IS NOT NULL
           AND via_result NOT IN ('positive', 'negative')"
    )->fetchAll();

    foreach ($rows as $row) {
        $patientId = (int) ($row['id'] ?? 0);
        if ($patientId < 1 || patient_has_queued_encouragement_drip($patientId)) {
            continue;
        }
        if (schedule_encouragement_drip_step($patientId, '+2 days')) {
            $repaired++;
        }
    }

    return $repaired;
}
