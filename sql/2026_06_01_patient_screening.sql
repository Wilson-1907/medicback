-- Patient screening fields for registration (HIV, HPV history, VIA, residence, follow-up)
-- Safe to run once; API also auto-adds columns via ensure_patient_screening_schema()

ALTER TABLE patients
  ADD COLUMN IF NOT EXISTS hiv_status ENUM('unknown','negative','positive') NOT NULL DEFAULT 'unknown',
  ADD COLUMN IF NOT EXISTS hpv_done_before ENUM('unknown','no','yes') NOT NULL DEFAULT 'unknown',
  ADD COLUMN IF NOT EXISTS hpv_prior_result ENUM('unknown','negative','positive') NOT NULL DEFAULT 'unknown',
  ADD COLUMN IF NOT EXISTS place_of_residence VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS via_result ENUM('unknown','not_done','negative','positive') NOT NULL DEFAULT 'unknown',
  ADD COLUMN IF NOT EXISTS via_date DATE NULL,
  ADD COLUMN IF NOT EXISTS has_cancer TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS treatment_date DATE NULL,
  ADD COLUMN IF NOT EXISTS next_checkup_at DATE NULL;
