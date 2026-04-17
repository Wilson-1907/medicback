-- PHV Patient Engagement Pilot — MySQL/MariaDB (XAMPP)
-- Run in phpMyAdmin or: mysql -u root -p phv_pilot < phv_pilot_schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS phv_pilot;
CREATE DATABASE phv_pilot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE phv_pilot;

-- ---------------------------------------------------------------------------
-- Core: patients and how we may reach them
-- ---------------------------------------------------------------------------
CREATE TABLE patients (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  hospital_id     VARCHAR(64) NOT NULL DEFAULT 'single-hospital-pilot',
  external_mrn    VARCHAR(128) NULL COMMENT 'Hospital MRN if integrated',
  full_name       VARCHAR(255) NOT NULL,
  date_of_birth   DATE NULL,
  preferred_language VARCHAR(16) NOT NULL DEFAULT 'en',
  registration_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  status          ENUM('registered','active','completed_pilot','withdrawn') NOT NULL DEFAULT 'registered',
  notes           TEXT NULL,
  created_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  KEY idx_patients_hospital (hospital_id),
  KEY idx_patients_status (status)
) ENGINE=InnoDB;

CREATE TABLE contact_channels (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id   BIGINT UNSIGNED NOT NULL,
  channel      ENUM('sms','whatsapp') NOT NULL,
  address      VARCHAR(64) NOT NULL COMMENT 'E.164 phone for SMS; WhatsApp ID/phone',
  is_primary   TINYINT(1) NOT NULL DEFAULT 0,
  opted_in     TINYINT(1) NOT NULL DEFAULT 0,
  opted_in_at  DATETIME(3) NULL,
  created_at   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY uq_channel_address (channel, address),
  KEY idx_contact_patient (patient_id),
  CONSTRAINT fk_contact_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Explicit preference log (audit + marketing compliance)
CREATE TABLE contact_preference_events (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id  BIGINT UNSIGNED NOT NULL,
  channel     ENUM('sms','whatsapp') NOT NULL,
  action      ENUM('opt_in','opt_out','confirm_double_opt_in') NOT NULL,
  source      VARCHAR(64) NOT NULL DEFAULT 'registration',
  recorded_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  meta_json   JSON NULL,
  KEY idx_pref_patient_time (patient_id, recorded_at),
  CONSTRAINT fk_pref_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Appointments (linked to patient); rescheduling as history
-- ---------------------------------------------------------------------------
CREATE TABLE appointments (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id      BIGINT UNSIGNED NOT NULL,
  department      VARCHAR(128) NULL,
  provider_name   VARCHAR(255) NULL,
  scheduled_start DATETIME(3) NOT NULL,
  scheduled_end   DATETIME(3) NULL,
  location        VARCHAR(512) NULL,
  status          ENUM('proposed','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'proposed',
  confirmation_at DATETIME(3) NULL,
  created_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  KEY idx_appt_patient (patient_id),
  KEY idx_appt_start (scheduled_start),
  CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE appointment_reschedule_events (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  appointment_id BIGINT UNSIGNED NOT NULL,
  old_start       DATETIME(3) NOT NULL,
  old_end         DATETIME(3) NULL,
  new_start       DATETIME(3) NOT NULL,
  new_end         DATETIME(3) NULL,
  reason          VARCHAR(512) NOT NULL COMMENT 'Required for initial booking and every schedule change',
  initiated_by    ENUM('patient','staff','system') NOT NULL DEFAULT 'patient',
  created_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  KEY idx_resched_appt (appointment_id),
  CONSTRAINT fk_resched_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Diagnosis / results tracking (post-diagnosis engagement)
-- ---------------------------------------------------------------------------
CREATE TABLE diagnosis_results (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id      BIGINT UNSIGNED NOT NULL,
  appointment_id BIGINT UNSIGNED NULL,
  coded_diagnosis VARCHAR(64) NULL COMMENT 'ICD-10 or local code',
  diagnosis_label VARCHAR(512) NOT NULL,
  severity        ENUM('unknown','mild','moderate','severe') NOT NULL DEFAULT 'unknown',
  result_summary  TEXT NULL,
  recorded_at     DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  recorded_by     VARCHAR(128) NULL COMMENT 'clinician or import batch id',
  KEY idx_dx_patient (patient_id),
  KEY idx_dx_appt (appointment_id),
  CONSTRAINT fk_dx_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_dx_appt FOREIGN KEY (appointment_id) REFERENCES appointments(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Messaging (Africa's Talking delivery log)
-- ---------------------------------------------------------------------------
CREATE TABLE outbound_messages (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id      BIGINT UNSIGNED NOT NULL,
  channel         ENUM('sms','whatsapp') NOT NULL,
  message_type    ENUM(
                      'welcome',
                      'appointment_reminder',
                      'education_menu',
                      'system',
                      'escalation_notice'
                    ) NOT NULL,
  body            TEXT NOT NULL,
  at_message_id   VARCHAR(128) NULL COMMENT 'Africa\'s Talking message id',
  status          ENUM('queued','sent','delivered','failed') NOT NULL DEFAULT 'queued',
  error_detail    VARCHAR(512) NULL,
  created_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  KEY idx_msg_patient (patient_id),
  KEY idx_msg_type (message_type),
  CONSTRAINT fk_msg_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE inbound_messages (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id      BIGINT UNSIGNED NULL,
  channel         ENUM('sms','whatsapp') NOT NULL,
  from_address    VARCHAR(64) NOT NULL,
  body            TEXT NOT NULL,
  raw_payload     JSON NULL,
  received_at     DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  KEY idx_in_from_time (from_address, received_at),
  KEY idx_in_patient (patient_id),
  CONSTRAINT fk_in_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Conversational AI (OpenAI) — session + turns for audit and safety
-- ---------------------------------------------------------------------------
CREATE TABLE ai_conversations (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id      BIGINT UNSIGNED NOT NULL,
  channel         ENUM('sms','whatsapp','web') NOT NULL,
  opened_at       DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  closed_at       DATETIME(3) NULL,
  context_json    JSON NULL COMMENT 'non-PHI pointers: appointment_id, menu state',
  KEY idx_conv_patient (patient_id),
  CONSTRAINT fk_conv_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ai_turns (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  conversation_id  BIGINT UNSIGNED NOT NULL,
  role             ENUM('user','assistant','system') NOT NULL,
  content          MEDIUMTEXT NOT NULL,
  model            VARCHAR(64) NULL,
  token_usage_json JSON NULL,
  created_at       DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  KEY idx_turn_conv (conversation_id),
  CONSTRAINT fk_turn_conv FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Escalation to hospital / doctor contact
-- ---------------------------------------------------------------------------
CREATE TABLE escalations (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id      BIGINT UNSIGNED NOT NULL,
  conversation_id BIGINT UNSIGNED NULL,
  reason          TEXT NOT NULL,
  urgency         ENUM('routine','same_day','urgent') NOT NULL DEFAULT 'routine',
  status          ENUM('open','triaged','contacted','resolved','cancelled') NOT NULL DEFAULT 'open',
  assigned_to     VARCHAR(128) NULL,
  created_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  KEY idx_esc_patient (patient_id),
  CONSTRAINT fk_esc_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_esc_conv FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
