-- Patient screening fields for registration (run once on production DB)

ALTER TABLE patients
  ADD COLUMN hiv_status ENUM('unknown','negative','positive') NOT NULL DEFAULT 'unknown',
  ADD COLUMN hpv_done_before ENUM('unknown','no','yes') NOT NULL DEFAULT 'unknown',
  ADD COLUMN hpv_prior_result ENUM('unknown','negative','positive') NOT NULL DEFAULT 'unknown',
  ADD COLUMN place_of_residence VARCHAR(255) NULL,
  ADD COLUMN via_result ENUM('unknown','not_done','negative','positive') NOT NULL DEFAULT 'unknown',
  ADD COLUMN via_date DATE NULL,
  ADD COLUMN has_cancer TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN treatment_date DATE NULL,
  ADD COLUMN next_checkup_at DATE NULL;
