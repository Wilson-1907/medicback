-- Flags scheduled rows that advance the HPV counseling drip chain when sent.

ALTER TABLE scheduled_messages
  ADD COLUMN triggers_counseling_chain TINYINT(1) NOT NULL DEFAULT 0;
