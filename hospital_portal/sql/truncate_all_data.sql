-- Empty every table in phv_pilot while keeping schema.
-- Run in phpMyAdmin or: mysql -u user -p phv_pilot < truncate_all_data.sql

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE ai_turns;
TRUNCATE TABLE ai_conversations;
TRUNCATE TABLE escalations;
TRUNCATE TABLE doctor_call_requests;
TRUNCATE TABLE inbound_messages;
TRUNCATE TABLE outbound_messages;
TRUNCATE TABLE diagnosis_results;
TRUNCATE TABLE appointment_reschedule_events;
TRUNCATE TABLE appointments;
TRUNCATE TABLE contact_preference_events;
TRUNCATE TABLE contact_channels;
TRUNCATE TABLE patients;
TRUNCATE TABLE staff_users;

SET FOREIGN_KEY_CHECKS = 1;
