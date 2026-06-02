-- Fix: Data truncated for column 'message_type' when sending referral or check-up SMS.
-- Use VARCHAR so all message labels (welcome, referral, checkup_reminder, etc.) are accepted.

ALTER TABLE outbound_messages
  MODIFY COLUMN message_type VARCHAR(32) NOT NULL DEFAULT 'system';
