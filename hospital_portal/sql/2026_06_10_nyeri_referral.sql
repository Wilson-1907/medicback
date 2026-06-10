-- Track specialist referral to Nyeri County Referral Hospital after screening is complete.
-- Run on Aiven database phv_pilot.

-- Columns are also added by ensure_nyeri_referral_schema() when the API runs.
ALTER TABLE patients
  ADD COLUMN IF NOT EXISTS nyeri_referral_at DATETIME(3) NULL;

ALTER TABLE patients
  ADD COLUMN IF NOT EXISTS nyeri_referral_appointment_date DATE NULL;
