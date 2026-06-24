<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/messaging.php';

/** Ensure scheduled_messages supports counseling chain callbacks (safe to call repeatedly). */
function ensure_scheduled_messages_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        if (function_exists('ensure_hpv_workflow_schema')) {
            require_once __DIR__ . '/hpv_results.php';
            ensure_hpv_workflow_schema();
        }
        $done = true;
    } catch (Throwable $e) {
        error_log('ensure_scheduled_messages_schema: ' . $e->getMessage());
    }
}

ensure_scheduled_messages_schema();

/** Compute send_at from MySQL clock so PHP/MySQL skew does not delay drips. */
function schedule_compute_send_at(string $delayExpression): string
{
    $row = db()->query('SELECT NOW(3) AS db_now')->fetch();
    $base = is_array($row) ? (string) ($row['db_now'] ?? '') : '';
    if ($base === '') {
        $base = date('Y-m-d H:i:s');
    }
    $ts = strtotime($delayExpression, strtotime($base));
    if ($ts === false) {
        $ts = time();
    }

    return date('Y-m-d H:i:s', $ts);
}

/**
 * Run due queued messages (debounced). Drips only leave the queue when this or cron runs.
 */
function maybe_flush_due_scheduled_messages(int $minIntervalSeconds = 60): void
{
    static $ranThisRequest = false;
    if ($ranThisRequest) {
        return;
    }
    $ranThisRequest = true;

    $lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phv_scheduled_flush.lock';
    $now = time();
    $last = is_file($lockFile) ? (int) @file_get_contents($lockFile) : 0;
    if ($now - $last < $minIntervalSeconds) {
        return;
    }
    @file_put_contents($lockFile, (string) $now);
    process_due_scheduled_messages();
}

/** @return array{queued: int, due_now: int, next_send_at: ?string, db_now: ?string} */
function scheduled_messages_queue_stats(): array
{
    try {
        $row = db()->query(
            "SELECT
                (SELECT COUNT(*) FROM scheduled_messages WHERE status = 'queued') AS queued,
                (SELECT COUNT(*) FROM scheduled_messages WHERE status = 'queued' AND send_at <= NOW(3)) AS due_now,
                (SELECT MIN(send_at) FROM scheduled_messages WHERE status = 'queued') AS next_send_at,
                NOW(3) AS db_now"
        )->fetch();
        if (!$row) {
            return ['queued' => 0, 'due_now' => 0, 'next_send_at' => null, 'db_now' => null];
        }

        return [
            'queued' => (int) ($row['queued'] ?? 0),
            'due_now' => (int) ($row['due_now'] ?? 0),
            'next_send_at' => isset($row['next_send_at']) ? (string) $row['next_send_at'] : null,
            'db_now' => isset($row['db_now']) ? (string) $row['db_now'] : null,
        ];
    } catch (Throwable $e) {
        return ['queued' => 0, 'due_now' => 0, 'next_send_at' => null, 'db_now' => null];
    }
}

function schedule_patient_message(
    int $patientId,
    string $messageType,
    string $body,
    string $delayExpression = '+3 minutes',
    bool $triggersCounselingChain = false
): int
{
    $sendAt = schedule_compute_send_at($delayExpression);
    $hasChainCol = scheduled_messages_has_counseling_chain_column();
    if ($hasChainCol) {
        $st = db()->prepare(
            'INSERT INTO scheduled_messages (patient_id, message_type, body, send_at, status, triggers_counseling_chain)
             VALUES (?,?,?,?,?,?)'
        );
        $st->execute([$patientId, $messageType, $body, $sendAt, 'queued', $triggersCounselingChain ? 1 : 0]);
    } else {
        $st = db()->prepare(
            'INSERT INTO scheduled_messages (patient_id, message_type, body, send_at, status)
             VALUES (?,?,?,?,?)'
        );
        $st->execute([$patientId, $messageType, $body, $sendAt, 'queued']);
    }
    return (int) db()->lastInsertId();
}

function schedule_patient_message_at(
    int $patientId,
    string $messageType,
    string $body,
    string $sendAtIso
): int {
    $ts = strtotime($sendAtIso);
    $sendAt = $ts !== false ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
    $hasChainCol = scheduled_messages_has_counseling_chain_column();
    if ($hasChainCol) {
        $st = db()->prepare(
            'INSERT INTO scheduled_messages (patient_id, message_type, body, send_at, status, triggers_counseling_chain)
             VALUES (?,?,?,?,?,0)'
        );
        $st->execute([$patientId, $messageType, $body, $sendAt, 'queued']);
    } else {
        $st = db()->prepare(
            'INSERT INTO scheduled_messages (patient_id, message_type, body, send_at, status)
             VALUES (?,?,?,?,?)'
        );
        $st->execute([$patientId, $messageType, $body, $sendAt, 'queued']);
    }
    return (int) db()->lastInsertId();
}

function scheduled_messages_has_counseling_chain_column(): bool
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    try {
        db()->query('SELECT triggers_counseling_chain FROM scheduled_messages LIMIT 1');
        $has = true;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/** @return array{processed: int, sent: int, failed: int} */
function process_due_scheduled_messages(): array
{
    $pdo = db();
    $chainCol = scheduled_messages_has_counseling_chain_column()
        ? ', triggers_counseling_chain'
        : '';
    $rows = $pdo->query(
        "SELECT id, patient_id, message_type, body{$chainCol}
         FROM scheduled_messages
         WHERE status = 'queued' AND send_at <= NOW(3)
         ORDER BY send_at ASC
         LIMIT 100"
    )->fetchAll();

    $sent = 0;
    $failed = 0;
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $patientId = (int) $row['patient_id'];
        $type = (string) $row['message_type'];
        $body = (string) $row['body'];
        $chain = !empty($row['triggers_counseling_chain']);
        $ok = send_patient_message($patientId, $type, $body);
        $upd = $pdo->prepare(
            'UPDATE scheduled_messages SET status = ?, sent_at = NOW(3) WHERE id = ?'
        );
        if ($ok) {
            $upd->execute(['sent', $id]);
            $sent++;
            if ($type === 'hpv_post_via_counseling' && function_exists('post_via_positive_counseling_step_sent')) {
                require_once __DIR__ . '/encouragement_drip.php';
                post_via_positive_counseling_step_sent($patientId);
            } elseif ($type === 'hpv_counseling' && function_exists('encouragement_drip_step_sent')) {
                require_once __DIR__ . '/encouragement_drip.php';
                encouragement_drip_step_sent($patientId);
            } elseif ($chain && function_exists('encouragement_drip_step_sent')) {
                require_once __DIR__ . '/encouragement_drip.php';
                encouragement_drip_step_sent($patientId);
            }
        } else {
            $upd->execute(['failed', $id]);
            $failed++;
        }
    }

    return ['processed' => count($rows), 'sent' => $sent, 'failed' => $failed];
}
