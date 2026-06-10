-- Optional date of birth; store age when DOB is not known.
-- Run on Aiven database phv_pilot.

ALTER TABLE patients
  ADD COLUMN IF NOT EXISTS age SMALLINT UNSIGNED NULL AFTER date_of_birth;
