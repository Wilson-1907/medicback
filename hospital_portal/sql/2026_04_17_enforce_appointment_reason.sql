USE phv_pilot;

-- Ensure historical rows have a value before NOT NULL change.
UPDATE appointment_reschedule_events
SET reason = 'No reason recorded'
WHERE reason IS NULL OR TRIM(reason) = '';

ALTER TABLE appointment_reschedule_events
MODIFY COLUMN reason VARCHAR(512) NOT NULL COMMENT 'Required for initial booking and every schedule change';
