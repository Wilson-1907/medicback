USE phv_pilot;

CREATE TABLE IF NOT EXISTS doctor_call_requests (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id      BIGINT UNSIGNED NOT NULL,
  reason          VARCHAR(512) NOT NULL,
  status          ENUM('pending','contacted','closed') NOT NULL DEFAULT 'pending',
  requested_at    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY uq_dcr_patient (patient_id),
  CONSTRAINT fk_dcr_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
