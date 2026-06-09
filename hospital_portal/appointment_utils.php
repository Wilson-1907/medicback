<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

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
