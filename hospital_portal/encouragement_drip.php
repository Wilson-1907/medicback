<?php
declare(strict_types=1);

/**
 * Short encouragement drip — works without HPV confirm (registration, VIA, HPV+).
 * Uses patients.hpv_counseling_index as drip position and scheduled_messages chain flag.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/scheduled_messages.php';
require_once __DIR__ . '/afya_simple_drip.php';

function encouragement_drip_message_count(string $lang = 'en'): int
{
    return count(afya_simple_encouragement_drip($lang));
}

function get_encouragement_drip_message_at_index(int $patientId, int $index, ?string $lang = null): ?string
{
    if ($lang === null) {
        $st = db()->prepare('SELECT preferred_language FROM patients WHERE id = ? LIMIT 1');
        $st->execute([$patientId]);
        $lang = (string) ($st->fetchColumn() ?: 'en');
        $lang = in_array($lang, ['en', 'sw'], true) ? $lang : 'en';
    }
    $messages = afya_simple_encouragement_drip($lang);
    return $messages[$index] ?? null;
}

function encouragement_drip_pathway_complete(int $patientId): bool
{
    $st = db()->prepare('SELECT preferred_language, hpv_counseling_index FROM patients WHERE id = ? LIMIT 1');
    $st->execute([$patientId]);
    $row = $st->fetch();
    if (!$row) {
        return true;
    }
    $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    $index = (int) ($row['hpv_counseling_index'] ?? 0);
    return $index >= encouragement_drip_message_count($lang);
}

function encouragement_drip_delay_before_index(int $index): string
{
    return match ($index) {
        0 => '+3 hours',
        1 => '+1 day',
        default => '+2 days',
    };
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

/** Schedule the next short tip at the patient's current hpv_counseling_index. */
function schedule_encouragement_drip_step(int $patientId, ?string $delayExpression = null): bool
{
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
    $msg = get_encouragement_drip_message_at_index($patientId, $index, $lang);
    if ($msg === null || trim($msg) === '') {
        return false;
    }

    $delay = $delayExpression ?? encouragement_drip_delay_before_index($index);
    schedule_patient_message($patientId, 'engagement_boost', $msg, $delay, true);
    return true;
}

/** Called by cron after each chained encouragement message is sent. */
function encouragement_drip_step_sent(int $patientId): void
{
    if (!db_table_has_column('patients', 'hpv_counseling_index')) {
        return;
    }
    db()->prepare(
        'UPDATE patients SET hpv_counseling_index = hpv_counseling_index + 1 WHERE id = ?'
    )->execute([$patientId]);

    if (encouragement_drip_pathway_complete($patientId)) {
        return;
    }

    $st = db()->prepare('SELECT hpv_counseling_index FROM patients WHERE id = ?');
    $st->execute([$patientId]);
    $nextIndex = (int) ($st->fetchColumn() ?: 0);
    schedule_encouragement_drip_step($patientId, encouragement_drip_delay_before_index($nextIndex));
}

/** Restart drip from tip 1 (e.g. after HPV confirm). */
function restart_encouragement_drip(int $patientId, string $firstDelay = '+3 hours'): bool
{
    cancel_queued_encouragement_drip($patientId);
    reset_encouragement_drip_index($patientId);
    return schedule_encouragement_drip_step($patientId, $firstDelay);
}

/** Stop pre-VIA FAQ drip once VIA result is recorded (result SMS is sent separately). */
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
