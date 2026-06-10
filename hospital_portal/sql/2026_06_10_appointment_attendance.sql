-- Appointment visit attendance (nurse marks attended / missed on appointment day)
-- Run on Aiven: select database phv_pilot, then execute this file.

ALTER TABLE appointments
  ADD COLUMN IF NOT EXISTS attendance_recorded_at DATETIME(3) NULL AFTER reminder_night_sent_at;

ALTER TABLE appointments
  MODIFY COLUMN status
    ENUM('proposed','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'proposed';
