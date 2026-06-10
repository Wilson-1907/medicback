<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/afya_rafiki_content.php';

/** @return bool */
function ensure_appointment_attendance_schema(): bool
{
    try {
        $pdo = db();
        if (!db_table_has_column('appointments', 'attendance_recorded_at')) {
            $pdo->exec('ALTER TABLE appointments ADD COLUMN attendance_recorded_at DATETIME(3) NULL');
        }
        if (db_table_has_column('appointments', 'status')) {
            $pdo->exec(
                "ALTER TABLE appointments MODIFY COLUMN status
                 ENUM('proposed','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'proposed'"
            );
        }
        return true;
    } catch (Throwable $e) {
        error_log('ensure_appointment_attendance_schema: ' . $e->getMessage());
        return false;
    }
}

/** Appointment date/time has passed and nurse has not recorded attendance yet. */
function appointment_needs_attendance_check(array $appointment): bool
{
    $status = strtolower((string) ($appointment['status'] ?? ''));
    if (!in_array($status, ['proposed', 'confirmed'], true)) {
        return false;
    }
    $start = strtotime((string) ($appointment['scheduled_start'] ?? ''));
    return $start !== false && $start <= time();
}

/**
 * Nurse confirms patient attended — mark completed; next step is VIA recording.
 *
 * @return array{ok: bool, error?: string, record_via_next?: bool}
 */
function mark_appointment_attended(int $appointmentId, string $recordedBy = 'staff'): array
{
    ensure_appointment_attendance_schema();

    $st = db()->prepare(
        'SELECT a.id, a.patient_id, a.scheduled_start, a.status, p.via_result
         FROM appointments a
         INNER JOIN patients p ON p.id = a.patient_id
         WHERE a.id = ?
         LIMIT 1'
    );
    $st->execute([$appointmentId]);
    $row = $st->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Appointment not found'];
    }
    if (!in_array($row['status'], ['proposed', 'confirmed'], true)) {
        return ['ok' => false, 'error' => 'Attendance was already recorded for this appointment'];
    }
    if (strtotime((string) $row['scheduled_start']) > time()) {
        return ['ok' => false, 'error' => 'This appointment has not happened yet'];
    }

    $up = db()->prepare(
        "UPDATE appointments
         SET status = 'completed', attendance_recorded_at = NOW(3), updated_at = NOW(3)
         WHERE id = ?"
    );
    $up->execute([$appointmentId]);

    $via = strtolower((string) ($row['via_result'] ?? 'not_done'));
    $recordViaNext = !in_array($via, ['negative', 'positive'], true);

    return [
        'ok' => true,
        'appointment_id' => $appointmentId,
        'status' => 'completed',
        'record_via_next' => $recordViaNext,
        'recorded_by' => $recordedBy,
    ];
}

/**
 * Nurse confirms patient did not attend — mark no_show and send missed-appointment message.
 *
 * @return array{ok: bool, error?: string, missed_message_sent?: bool}
 */
function mark_appointment_missed(int $appointmentId, string $recordedBy = 'staff'): array
{
    ensure_appointment_attendance_schema();

    $st = db()->prepare(
        'SELECT a.id, a.patient_id, a.scheduled_start, a.status, p.full_name, p.preferred_language
         FROM appointments a
         INNER JOIN patients p ON p.id = a.patient_id
         WHERE a.id = ?
         LIMIT 1'
    );
    $st->execute([$appointmentId]);
    $row = $st->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Appointment not found'];
    }
    if (!in_array($row['status'], ['proposed', 'confirmed'], true)) {
        return ['ok' => false, 'error' => 'Attendance was already recorded for this appointment'];
    }
    if (strtotime((string) $row['scheduled_start']) > time()) {
        return ['ok' => false, 'error' => 'Cannot mark missed before the appointment time'];
    }

    $up = db()->prepare(
        "UPDATE appointments
         SET status = 'no_show', attendance_recorded_at = NOW(3), updated_at = NOW(3)
         WHERE id = ?"
    );
    $up->execute([$appointmentId]);

    $patientId = (int) $row['patient_id'];
    $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    $missedSent = false;

    $optSt = db()->prepare(
        'SELECT 1 FROM contact_channels WHERE patient_id = ? AND opted_in = 1 LIMIT 1'
    );
    $optSt->execute([$patientId]);
    if ($optSt->fetchColumn()) {
        $missedSent = send_patient_message(
            $patientId,
            'escalation_notice',
            build_missed_appointment_message((string) $row['full_name'], $lang)
        );
    }

    return [
        'ok' => true,
        'appointment_id' => $appointmentId,
        'status' => 'no_show',
        'missed_message_sent' => $missedSent,
        'recorded_by' => $recordedBy,
    ];
}

/** True if patient already has a proposed/confirmed appointment at this start time. */
function appointment_slot_taken(int $patientId, string $startSql, ?int $excludeAppointmentId = null): bool
{
    $sql = "SELECT id FROM appointments
            WHERE patient_id = ? AND scheduled_start = ? AND status IN ('proposed','confirmed')";
    $args = [$patientId, $startSql];
    if ($excludeAppointmentId !== null && $excludeAppointmentId > 0) {
        $sql .= ' AND id <> ?';
        $args[] = $excludeAppointmentId;
    }
    $sql .= ' LIMIT 1';
    $st = db()->prepare($sql);
    $st->execute($args);
    return (bool) $st->fetchColumn();
}

/** Cancel duplicate rows (same patient + start); keep the earliest id. Returns rows updated. */
function cancel_duplicate_appointments(?PDO $pdo = null): int
{
    $pdo = $pdo ?? db();
    $st = $pdo->prepare(
        "UPDATE appointments a
         INNER JOIN (
             SELECT patient_id, scheduled_start, MIN(id) AS keep_id
             FROM appointments
             WHERE status IN ('proposed','confirmed')
             GROUP BY patient_id, scheduled_start
             HAVING COUNT(*) > 1
         ) d ON a.patient_id = d.patient_id AND a.scheduled_start = d.scheduled_start
         SET a.status = 'cancelled', a.updated_at = NOW(3)
         WHERE a.id <> d.keep_id AND a.status IN ('proposed','confirmed')"
    );
    $st->execute();
    return $st->rowCount();
}
