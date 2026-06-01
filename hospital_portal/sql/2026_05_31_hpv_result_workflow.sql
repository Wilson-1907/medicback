-- HPV result workflow + delayed message queue (run once on phv_pilot)

ALTER TABLE patients
  ADD COLUMN hpv_screening_result ENUM('unknown','pending','positive','negative') NOT NULL DEFAULT 'pending',
  ADD COLUMN hpv_result_recorded_at DATETIME(3) NULL,
  ADD COLUMN hpv_result_confirmed_at DATETIME(3) NULL,
  ADD COLUMN hpv_counseling_index INT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS scheduled_messages (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id   BIGINT UNSIGNED NOT NULL,
  message_type VARCHAR(32) NOT NULL DEFAULT 'system',
  body         TEXT NOT NULL,
  send_at      DATETIME(3) NOT NULL,
  sent_at      DATETIME(3) NULL,
  status       ENUM('queued','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
  created_at   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  KEY idx_sched_patient (patient_id),
  KEY idx_sched_due (status, send_at),
  CONSTRAINT fk_sched_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
