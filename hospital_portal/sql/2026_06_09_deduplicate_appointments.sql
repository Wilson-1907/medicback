-- Cancel duplicate appointments (same patient + same start time).
-- Keeps the earliest id; marks extras as cancelled.

UPDATE appointments a
INNER JOIN (
    SELECT patient_id, scheduled_start, MIN(id) AS keep_id
    FROM appointments
    WHERE status IN ('proposed','confirmed')
    GROUP BY patient_id, scheduled_start
    HAVING COUNT(*) > 1
) d ON a.patient_id = d.patient_id AND a.scheduled_start = d.scheduled_start
SET a.status = 'cancelled', a.updated_at = NOW(3)
WHERE a.id <> d.keep_id AND a.status IN ('proposed','confirmed');
