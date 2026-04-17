USE phv_pilot;

ALTER TABLE appointments
  ADD COLUMN reminder_7d_sent_at DATETIME(3) NULL AFTER confirmation_at,
  ADD COLUMN reminder_3d_sent_at DATETIME(3) NULL AFTER reminder_7d_sent_at,
  ADD COLUMN reminder_night_sent_at DATETIME(3) NULL AFTER reminder_3d_sent_at;
