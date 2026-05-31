USE phv_pilot;

ALTER TABLE outbound_messages
  MODIFY COLUMN message_type ENUM(
    'welcome',
    'appointment_reminder',
    'education_menu',
    'engagement_boost',
    'system',
    'ai_reply',
    'escalation_notice'
  ) NOT NULL;
