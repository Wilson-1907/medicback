<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Stored in doctor_call_requests.reason until the patient replies in their own words. */
const DCR_REASON_AWAITING = '__AWAITING_PATIENT_REASON__';

function get_doctor_call_request(int $patientId): ?array
{
    $st = db()->prepare(
        'SELECT patient_id, reason, status, requested_at, updated_at
         FROM doctor_call_requests WHERE patient_id = ? LIMIT 1'
    );
    $st->execute([$patientId]);
    $row = $st->fetch();
    return $row ?: null;
}

function is_awaiting_doctor_reason_row(?array $row): bool
{
    if (!$row) {
        return false;
    }
    if (($row['status'] ?? '') === 'awaiting_reason') {
        return true;
    }
    return trim((string) ($row['reason'] ?? '')) === DCR_REASON_AWAITING;
}

function patient_awaiting_doctor_reason(int $patientId): bool
{
    return is_awaiting_doctor_reason_row(get_doctor_call_request($patientId));
}

function is_auto_doctor_call_reason(string $reason): bool
{
    $reason = trim($reason);
    if ($reason === '' || $reason === DCR_REASON_AWAITING) {
        return true;
    }
    return (bool) preg_match('/^Patient requested direct provider contact via /i', $reason);
}

function is_doctor_request_keyword(string $normalizedUpper): bool
{
    return str_contains($normalizedUpper, 'DOCTOR')
        || str_contains($normalizedUpper, 'DAKTARI')
        || $normalizedUpper === '5';
}

function should_capture_as_doctor_reason(string $body, string $normalizedUpper): bool
{
    if (is_doctor_request_keyword($normalizedUpper)) {
        return false;
    }
    $trimmed = trim($body);
    if (mb_strlen($trimmed) < 8) {
        return false;
    }
    if (preg_match('/^(YES|NO|NDIO|HAPANA|HELP|MENU|HI|HELLO|SAWA)$/ui', $normalizedUpper)) {
        return false;
    }
    if (preg_match('/^[1-5]$/', $normalizedUpper)) {
        return false;
    }
    return true;
}

function patient_stated_call_reason(?array $doctorCall, ?string $escalationReason = null): ?string
{
    $reason = trim((string) ($doctorCall['reason'] ?? $escalationReason ?? ''));
    if ($reason === '' || is_auto_doctor_call_reason($reason)) {
        return null;
    }
    if (str_starts_with($reason, 'Patient wants to speak with a health specialist:')) {
        return trim(substr($reason, strlen('Patient wants to speak with a health specialist:')));
    }
    return $reason;
}

/** Ask the patient why they want to talk to a provider (no escalation until they reply). */
function start_doctor_call_reason_collection(int $patientId, string $channel, string $lang): void
{
    require_once __DIR__ . '/afya_rafiki_content.php';
    require_once __DIR__ . '/messaging.php';

    $pdo = db();
    $status = doctor_call_status_awaiting_reason();
    $st = $pdo->prepare(
        'INSERT INTO doctor_call_requests (patient_id, reason, status, requested_at)
         VALUES (?, ?, ?, NOW(3))
         ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = VALUES(status), requested_at = NOW(3)'
    );
    $st->execute([$patientId, DCR_REASON_AWAITING, $status]);

    upsert_open_escalation_for_doctor_call(
        $patientId,
        'Health specialist requested — waiting for patient to describe why they need a call.'
    );

    send_patient_message($patientId, 'system', build_doctor_reason_request_prompt($lang));
}

function doctor_call_status_awaiting_reason(): string
{
    static $supported = null;
    if ($supported !== null) {
        return $supported ? 'awaiting_reason' : 'pending';
    }
    try {
        $col = db()->query(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'doctor_call_requests'
               AND COLUMN_NAME = 'status'
             LIMIT 1"
        )->fetchColumn();
        $supported = is_string($col) && str_contains($col, 'awaiting_reason');
    } catch (Throwable $e) {
        $supported = false;
    }
    return $supported ? 'awaiting_reason' : 'pending';
}

/** Save the patient's reason, open escalation for staff, and confirm by SMS. */
function complete_doctor_call_with_patient_reason(
    int $patientId,
    string $patientReason,
    string $channel,
    string $lang
): void {
    require_once __DIR__ . '/afya_rafiki_content.php';
    require_once __DIR__ . '/messaging.php';

    $patientReason = trim($patientReason);
    if (mb_strlen($patientReason) > 480) {
        $patientReason = mb_substr($patientReason, 0, 480);
    }

    $staffSummary = 'Patient wants to speak with a health specialist: ' . $patientReason;

    $st = db()->prepare(
        'INSERT INTO doctor_call_requests (patient_id, reason, status, requested_at)
         VALUES (?, ?, ?, NOW(3))
         ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = VALUES(status), requested_at = NOW(3)'
    );
    $st->execute([$patientId, $staffSummary, 'pending']);

    upsert_open_escalation_for_doctor_call($patientId, $staffSummary);

    send_patient_message($patientId, 'escalation_notice', build_doctor_reason_received_ack($lang));
}

function upsert_open_escalation_for_doctor_call(int $patientId, string $reason): void
{
    $pdo = db();
    $find = $pdo->prepare(
        "SELECT id FROM escalations
         WHERE patient_id = ? AND status IN ('open','triaged')
         ORDER BY created_at DESC, id DESC
         LIMIT 1"
    );
    $find->execute([$patientId]);
    $existingId = $find->fetchColumn();

    if ($existingId) {
        $upd = $pdo->prepare('UPDATE escalations SET reason = ?, urgency = ? WHERE id = ?');
        $upd->execute([$reason, 'same_day', $existingId]);
        return;
    }

    create_escalation_for_doctor_call($patientId, $reason);
}

function create_escalation_for_doctor_call(int $patientId, string $reason): void
{
    require_once __DIR__ . '/afya_rafiki_content.php';

    create_escalation($patientId, $reason, 'same_day');
}

/** Legacy entry: immediate generic request (kept for scripts/tests). Prefer start + complete flow. */
function create_doctor_call_request(int $patientId, string $reason): void
{
    $st = db()->prepare(
        'INSERT INTO doctor_call_requests (patient_id, reason, status, requested_at)
         VALUES (?, ?, ?, NOW(3))
         ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = VALUES(status), requested_at = NOW(3)'
    );
    $st->execute([$patientId, $reason, 'pending']);

    create_escalation_for_doctor_call($patientId, $reason);
}

/**
 * Staff confirmed they called the patient — close open escalations and mark the call request.
 *
 * @return array{ok: bool, patient_id: int, escalations_updated: int, doctor_call_updated: bool}
 */
function mark_specialist_request_contacted(int $patientId, ?int $escalationId = null): array
{
    $pdo = db();

    if ($escalationId !== null && $escalationId > 0) {
        $st = $pdo->prepare('SELECT patient_id FROM escalations WHERE id = ? LIMIT 1');
        $st->execute([$escalationId]);
        $found = $st->fetchColumn();
        if (!$found) {
            return ['ok' => false, 'error' => 'Escalation not found'];
        }
        $patientId = (int) $found;
    }

    if ($patientId < 1) {
        return ['ok' => false, 'error' => 'patient_id is required'];
    }

    $esc = $pdo->prepare(
        "UPDATE escalations SET status = 'contacted', updated_at = NOW(3)
         WHERE patient_id = ? AND status IN ('open', 'triaged')"
    );
    $esc->execute([$patientId]);
    $escalationsUpdated = $esc->rowCount();

    $dcr = $pdo->prepare(
        "UPDATE doctor_call_requests SET status = 'contacted', updated_at = NOW(3)
         WHERE patient_id = ? AND status NOT IN ('contacted', 'closed')"
    );
    $dcr->execute([$patientId]);
    $doctorCallUpdated = $dcr->rowCount() > 0;

    return [
        'ok' => true,
        'patient_id' => $patientId,
        'escalations_updated' => $escalationsUpdated,
        'doctor_call_updated' => $doctorCallUpdated,
    ];
}

function handle_doctor_request_keyword(int $patientId, string $channel, string $lang): void
{
    require_once __DIR__ . '/afya_rafiki_content.php';
    require_once __DIR__ . '/messaging.php';

    if (patient_awaiting_doctor_reason($patientId)) {
        send_patient_message($patientId, 'system', build_doctor_reason_reminder_prompt($lang));
        return;
    }

    $existing = get_doctor_call_request($patientId);
    if ($existing && ($existing['status'] ?? '') === 'pending' && !is_auto_doctor_call_reason((string) $existing['reason'])) {
        send_patient_message($patientId, 'system', build_doctor_request_already_logged_ack($lang));
        return;
    }

    start_doctor_call_reason_collection($patientId, $channel, $lang);
}
