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

/** Clinic calendar day in APP_TIMEZONE (YYYY-MM-DD). */
function clinic_today_date(): string
{
    return date('Y-m-d');
}

/** All booked visits on the clinic day (any attendance outcome). */
function count_clinic_day_appointments(?PDO $pdo = null): int
{
    $pdo = $pdo ?? db();
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM appointments
         WHERE DATE(scheduled_start) = ?
           AND status IN ('proposed','confirmed','completed','no_show')"
    );
    $st->execute([clinic_today_date()]);

    return (int) $st->fetchColumn();
}

/** True on the appointment calendar day or any day after. */
function appointment_on_or_past_day(array $appointment): bool
{
    $start = strtotime((string) ($appointment['scheduled_start'] ?? ''));
    if ($start === false) {
        return false;
    }
    return date('Y-m-d', $start) <= date('Y-m-d');
}

/** True when the scheduled start time has passed (uses MySQL clock). */
function appointment_start_time_passed(array $appointment): bool
{
    $startRaw = (string) ($appointment['scheduled_start'] ?? '');
    if ($startRaw === '') {
        return false;
    }
    $row = db()->query('SELECT NOW(3) AS db_now')->fetch();
    $dbNow = is_array($row) ? (string) ($row['db_now'] ?? '') : '';
    if ($dbNow === '') {
        return strtotime($startRaw) <= time();
    }

    return strtotime($startRaw) <= strtotime($dbNow);
}

/** Chronologically first booked appointment for a patient (VIA is only done after this visit). */
function patient_first_appointment_id(int $patientId): ?int
{
    $st = db()->prepare(
        'SELECT id FROM appointments WHERE patient_id = ? ORDER BY scheduled_start ASC, id ASC LIMIT 1'
    );
    $st->execute([$patientId]);
    $id = $st->fetchColumn();

    return $id !== false ? (int) $id : null;
}

/** Patient has a proposed or confirmed appointment (for HPV positive confirm gate). */
function patient_has_upcoming_appointment(int $patientId, ?array $appointments = null): bool
{
    if ($appointments !== null) {
        foreach ($appointments as $a) {
            $status = strtolower((string) ($a['status'] ?? ''));
            if (in_array($status, ['proposed', 'confirmed'], true)) {
                return true;
            }
        }

        return false;
    }

    $st = db()->prepare(
        "SELECT 1 FROM appointments
         WHERE patient_id = ? AND status IN ('proposed','confirmed')
         LIMIT 1"
    );
    $st->execute([$patientId]);

    return (bool) $st->fetchColumn();
}

/** Patient has at least one active booked visit (staff booking counts as confirmed). */
function patient_has_confirmed_appointment(int $patientId, ?array $appointments = null): bool
{
    if ($appointments !== null) {
        foreach ($appointments as $a) {
            $status = strtolower((string) ($a['status'] ?? ''));
            if (in_array($status, ['proposed', 'confirmed', 'completed', 'no_show'], true)) {
                return true;
            }
        }

        return false;
    }

    $st = db()->prepare(
        "SELECT 1 FROM appointments
         WHERE patient_id = ? AND status IN ('proposed','confirmed','completed','no_show')
         LIMIT 1"
    );
    $st->execute([$patientId]);

    return (bool) $st->fetchColumn();
}

/** Patient has at least one visit marked attended (completed). */
function patient_has_completed_appointment(int $patientId, ?array $appointments = null): bool
{
    if ($appointments !== null) {
        foreach ($appointments as $a) {
            if (strtolower((string) ($a['status'] ?? '')) === 'completed') {
                return true;
            }
        }

        return false;
    }

    $st = db()->prepare(
        "SELECT 1 FROM appointments WHERE patient_id = ? AND status = 'completed' LIMIT 1"
    );
    $st->execute([$patientId]);

    return (bool) $st->fetchColumn();
}

/** True when this row is the patient's first booked appointment. */
function appointment_is_patients_first(int $appointmentId, int $patientId): bool
{
    $firstId = patient_first_appointment_id($patientId);

    return $firstId !== null && $firstId === $appointmentId;
}

/** True when attendance can be recorded (any active booked visit). */
function appointment_needs_attendance_check(array $appointment): bool
{
    $status = strtolower((string) ($appointment['status'] ?? ''));
    return in_array($status, ['proposed', 'confirmed'], true);
}

/**
 * @deprecated Attendance is marked only by staff via mark_appointment_attended().
 */
function auto_complete_attendance_on_via_record(int $patientId): void
{
    // Intentionally no-op — nurses mark attendance manually.
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
        'SELECT a.id, a.patient_id, a.scheduled_start, a.status, a.created_at,
                p.full_name, p.preferred_language, p.via_result
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

    $up = db()->prepare(
        "UPDATE appointments
         SET status = 'completed', attendance_recorded_at = NOW(3), updated_at = NOW(3)
         WHERE id = ?"
    );
    $up->execute([$appointmentId]);

    $via = strtolower((string) ($row['via_result'] ?? 'not_done'));
    $patientId = (int) $row['patient_id'];
    $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    $patientName = (string) $row['full_name'];

    $optSt = db()->prepare(
        'SELECT 1 FROM contact_channels WHERE patient_id = ? AND opted_in = 1 LIMIT 1'
    );
    $optSt->execute([$patientId]);
    $createdAt = strtotime((string) ($row['created_at'] ?? ''));
    $dbNowRow = db()->query('SELECT UNIX_TIMESTAMP(NOW(3)) AS ts')->fetch();
    $dbNowTs = is_array($dbNowRow) ? (int) ($dbNowRow['ts'] ?? time()) : time();
    $bookedSecondsAgo = $createdAt !== false ? max(0, $dbNowTs - $createdAt) : 99999;
    if ($optSt->fetchColumn() && $bookedSecondsAgo >= 1800) {
        require_once __DIR__ . '/afya_rafiki_content.php';
        send_patient_message(
            $patientId,
            'post_visit_ack',
            build_post_visit_acknowledgement($patientName, $lang)
        );
    }

    $recordViaNext = !in_array($via, ['negative', 'positive'], true)
        && appointment_is_patients_first($appointmentId, $patientId);

    return [
        'ok' => true,
        'appointment_id' => $appointmentId,
        'status' => 'completed',
        'record_via_next' => $recordViaNext,
        'needs_via_record' => $recordViaNext,
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
    if (!appointment_on_or_past_day($row)) {
        return ['ok' => false, 'error' => 'Missed visits can be recorded on or after the appointment day'];
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

    require_once __DIR__ . '/missed_appointment_flow.php';
    missed_flow_on_appointment_missed($patientId, $appointmentId);

    $optSt = db()->prepare(
        'SELECT 1 FROM contact_channels WHERE patient_id = ? AND opted_in = 1 LIMIT 1'
    );
    $optSt->execute([$patientId]);
    if ($optSt->fetchColumn()) {
        $missedSent = send_patient_message(
            $patientId,
            'missed_survey',
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

/**
 * Resend missed-appointment survey SMS/WhatsApp for an already marked no_show visit.
 *
 * @return array{ok: bool, error?: string, missed_message_sent?: bool}
 */
function resend_missed_appointment_message(int $appointmentId): array
{
    $st = db()->prepare(
        'SELECT a.id, a.patient_id, a.status, p.full_name, p.preferred_language
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
    if (strtolower((string) ($row['status'] ?? '')) !== 'no_show') {
        return ['ok' => false, 'error' => 'Missed message can only be sent for did-not-attend visits'];
    }

    $patientId = (int) $row['patient_id'];
    $lang = in_array($row['preferred_language'], ['en', 'sw'], true) ? $row['preferred_language'] : 'en';
    $missedSent = false;

    require_once __DIR__ . '/missed_appointment_flow.php';
    missed_flow_on_appointment_missed($patientId, $appointmentId);

    $optSt = db()->prepare(
        'SELECT 1 FROM contact_channels WHERE patient_id = ? AND opted_in = 1 LIMIT 1'
    );
    $optSt->execute([$patientId]);
    if ($optSt->fetchColumn()) {
        require_once __DIR__ . '/afya_rafiki_content.php';
        $missedSent = send_patient_message(
            $patientId,
            'missed_survey',
            build_missed_appointment_message((string) $row['full_name'], $lang)
        );
    }

    return [
        'ok' => true,
        'appointment_id' => $appointmentId,
        'missed_message_sent' => $missedSent,
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
