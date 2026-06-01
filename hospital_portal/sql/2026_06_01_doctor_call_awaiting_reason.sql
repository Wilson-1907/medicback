-- Allow "waiting for patient to describe why they need a call" status (run once on phv_pilot)
USE phv_pilot;

ALTER TABLE doctor_call_requests
  MODIFY COLUMN status ENUM('awaiting_reason','pending','contacted','closed') NOT NULL DEFAULT 'pending';
