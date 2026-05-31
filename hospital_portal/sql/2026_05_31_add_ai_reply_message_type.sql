USE phv_pilot;

ALTER TABLE outbound_messages
  MODIFY COLUMN message_type ENUM(
    'welcome',
    'appointment_reminder',
    'education_menu',
    'system',
    'ai_reply',
    'escalation_notice'
  ) NOT NULL;
