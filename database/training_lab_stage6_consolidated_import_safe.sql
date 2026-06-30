-- Microgifter Training Lab Stage 6 Consolidated SQL
-- IMPORT-SAFE REVISION: no CHECK constraints, no external foreign keys, no role-table writes
--
-- PURPOSE:
--   Adds the Training Lab campaign/proof/review/action-receipt/reward-event schema.
--
-- SOURCE CONTEXT:
--   Built after reviewing the Microgifter Stage 1 -> Stage 9 consolidated schema reference.
--   The existing Microgifter install preserves users/accounts/login and already includes
--   gift, merchant location, PPPM, entitlement, microgift, ledger, and operation tables.
--
-- DEPLOYMENT CONTRACT:
--   1. Export a full database backup before import.
--   2. Import this file once into the existing Microgifter database.
--   3. This file is additive and uses CREATE TABLE IF NOT EXISTS / INSERT IGNORE.
--   4. This file does not drop, truncate, delete, recreate, or modify existing tables.
--   5. This file does not issue rewards, change wallet balances, process payments,
--      create claim/redeem behavior, or process real uploads.
--
-- FOREIGN KEY POLICY:
--   Import-safe version: no foreign keys and no CHECK constraints are used in this file.
--   Existing Microgifter account/reward/wallet IDs are stored as nullable/indexed IDs first.
--   Foreign keys can be added in a later FK-hardening migration after exact table names are confirmed.

SET NAMES utf8mb4;
SET @TL_OLD_FOREIGN_KEY_CHECKS := @@FOREIGN_KEY_CHECKS;
SET @TL_OLD_UNIQUE_CHECKS := @@UNIQUE_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;
SET UNIQUE_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Training campaigns
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS training_campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  merchant_location_id BIGINT UNSIGNED NULL,
  slug VARCHAR(160) NOT NULL,
  title VARCHAR(180) NOT NULL,
  summary VARCHAR(500) NULL,
  description TEXT NULL,
  campaign_type ENUM('movement','safety','onboarding','wellness','skills','custom') NOT NULL DEFAULT 'custom',
  visibility ENUM('draft','private','published','archived') NOT NULL DEFAULT 'draft',
  status ENUM('draft','scheduled','active','paused','completed','archived') NOT NULL DEFAULT 'draft',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  timezone VARCHAR(80) NOT NULL DEFAULT 'America/Phoenix',
  target_action_count INT UNSIGNED NOT NULL DEFAULT 5,
  reward_summary VARCHAR(255) NULL,
  settings_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_campaigns_public_id (public_id),
  UNIQUE KEY uq_training_campaigns_owner_slug (owner_user_id, slug),
  KEY idx_training_campaigns_owner_status (owner_user_id, status, updated_at),
  KEY idx_training_campaigns_location_status (merchant_location_id, status),
  KEY idx_training_campaigns_visibility_status (visibility, status, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Ordered campaign tasks
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS training_campaign_tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  position_no INT UNSIGNED NOT NULL,
  day_no INT UNSIGNED NULL,
  task_type ENUM('checklist','movement','photo_proof','video_proof','text_reflection','quiz','custom') NOT NULL DEFAULT 'checklist',
  title VARCHAR(180) NOT NULL,
  instructions TEXT NULL,
  proof_required TINYINT(1) NOT NULL DEFAULT 0,
  expected_duration_minutes INT UNSIGNED NULL,
  status ENUM('active','hidden','archived') NOT NULL DEFAULT 'active',
  settings_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_tasks_public_id (public_id),
  UNIQUE KEY uq_training_tasks_campaign_position (campaign_id, position_no),
  KEY idx_training_tasks_campaign_status (campaign_id, status, position_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Campaign participants
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS training_participants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  invited_by_user_id BIGINT UNSIGNED NULL,
  participant_label VARCHAR(160) NULL,
  status ENUM('invited','active','paused','completed','removed') NOT NULL DEFAULT 'active',
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  removed_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_participants_public_id (public_id),
  UNIQUE KEY uq_training_participants_campaign_user (campaign_id, user_id),
  KEY idx_training_participants_user_status (user_id, status, updated_at),
  KEY idx_training_participants_campaign_status (campaign_id, status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Proof submissions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS training_proof_submissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  submitted_by_user_id BIGINT UNSIGNED NOT NULL,
  proof_type ENUM('none','text','image','video','audio','file','external_link') NOT NULL DEFAULT 'text',
  proof_text TEXT NULL,
  storage_reference VARCHAR(255) NULL,
  external_url VARCHAR(500) NULL,
  status ENUM('draft','submitted','in_review','approved','rejected','cancelled') NOT NULL DEFAULT 'submitted',
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_proof_public_id (public_id),
  KEY idx_training_proof_participant_status (participant_id, status, submitted_at),
  KEY idx_training_proof_campaign_status (campaign_id, status, submitted_at),
  KEY idx_training_proof_task_status (task_id, status, submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Manual review decisions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS training_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  proof_submission_id BIGINT UNSIGNED NOT NULL,
  reviewer_user_id BIGINT UNSIGNED NOT NULL,
  decision ENUM('approved','rejected','needs_more_info') NOT NULL,
  review_notes TEXT NULL,
  reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_reviews_public_id (public_id),
  KEY idx_training_reviews_proof_created (proof_submission_id, created_at),
  KEY idx_training_reviews_reviewer_created (reviewer_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Verified action receipts
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS training_action_receipts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  proof_submission_id BIGINT UNSIGNED NULL,
  review_id BIGINT UNSIGNED NULL,
  receipt_type ENUM('task_completed','sequence_completed','manual_adjustment') NOT NULL DEFAULT 'task_completed',
  verification_hash CHAR(64) NULL,
  receipt_status ENUM('active','voided') NOT NULL DEFAULT 'active',
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  voided_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_receipts_public_id (public_id),
  KEY idx_training_receipts_campaign_user (campaign_id, user_id, issued_at),
  KEY idx_training_receipts_participant (participant_id, issued_at),
  KEY idx_training_receipts_status (receipt_status, issued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Reward eligibility rules
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS training_reward_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  rule_name VARCHAR(160) NOT NULL,
  trigger_type ENUM('action_count','sequence_completed','streak_days','manual') NOT NULL DEFAULT 'sequence_completed',
  threshold_count INT UNSIGNED NOT NULL DEFAULT 1,
  reward_type ENUM('badge','microgift','entitlement','wallet_credit_preview','custom') NOT NULL DEFAULT 'badge',
  reward_label VARCHAR(160) NOT NULL,
  reward_value_cents INT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  linked_microgift_template_id BIGINT UNSIGNED NULL,
  linked_catalog_product_id BIGINT UNSIGNED NULL,
  status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'draft',
  settings_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_reward_rules_public_id (public_id),
  KEY idx_training_reward_rules_campaign_status (campaign_id, status),
  KEY idx_training_reward_rules_trigger (trigger_type, threshold_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Reward events bridge into Microgifter wallet/reward layer later
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS training_reward_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  action_receipt_id BIGINT UNSIGNED NULL,
  reward_rule_id BIGINT UNSIGNED NULL,
  status ENUM('eligible','queued','issued','linked','cancelled','failed') NOT NULL DEFAULT 'eligible',
  linked_gift_id BIGINT UNSIGNED NULL,
  linked_microgift_instance_id BIGINT UNSIGNED NULL,
  linked_digital_entitlement_id BIGINT UNSIGNED NULL,
  linked_wallet_event_id BIGINT UNSIGNED NULL,
  value_cents INT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  eligibility_reason VARCHAR(255) NULL,
  issued_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  failure_message VARCHAR(500) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_reward_events_public_id (public_id),
  KEY idx_training_reward_events_campaign_status (campaign_id, status, created_at),
  KEY idx_training_reward_events_user_status (user_id, status, created_at),
  KEY idx_training_reward_events_participant (participant_id, created_at),
  KEY idx_training_reward_events_links (linked_gift_id, linked_microgift_instance_id, linked_digital_entitlement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Streak/progress summary
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS training_streaks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  current_streak_days INT UNSIGNED NOT NULL DEFAULT 0,
  longest_streak_days INT UNSIGNED NOT NULL DEFAULT 0,
  completed_action_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_action_date DATE NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_streaks_participant (participant_id),
  KEY idx_training_streaks_campaign_user (campaign_id, user_id),
  KEY idx_training_streaks_last_action (last_action_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Internal audit/event log for Training Lab actions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS training_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  subject_type ENUM('campaign','task','participant','proof','review','receipt','reward_rule','reward_event','streak','system') NOT NULL,
  subject_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_events_public_id (public_id),
  KEY idx_training_events_subject (subject_type, subject_id, created_at),
  KEY idx_training_events_actor_created (actor_user_id, created_at),
  KEY idx_training_events_type_created (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Training Lab permission catalog
-- -----------------------------------------------------------------------------
-- Import-safe note:
-- This creates a Training Lab-local permission catalog only. It does not write into
-- existing permissions/roles tables because those table names vary by install and can
-- trigger import failures. Map these slugs to your existing role system after the
-- real account tables are confirmed.

CREATE TABLE IF NOT EXISTS training_permission_catalog (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(120) NOT NULL,
  name VARCHAR(180) NOT NULL,
  description VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_training_permission_catalog_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO training_permission_catalog (slug, name, description) VALUES
('training.campaign.view', 'View training campaigns', 'View authorized Training Lab campaigns.'),
('training.campaign.manage', 'Manage training campaigns', 'Create and manage Training Lab campaigns.'),
('training.participate', 'Participate in training campaigns', 'Join campaigns, view tasks, and submit proof when enabled.'),
('training.proof.review', 'Review training proof', 'Review and decide submitted Training Lab proof.'),
('training.receipt.view', 'View training action receipts', 'View verified Training Lab action receipts.'),
('training.reward.manage', 'Manage training reward rules', 'Create and manage Training Lab reward eligibility rules.');

-- -----------------------------------------------------------------------------
-- Completion marker
-- -----------------------------------------------------------------------------
SET UNIQUE_CHECKS = @TL_OLD_UNIQUE_CHECKS;
SET FOREIGN_KEY_CHECKS = @TL_OLD_FOREIGN_KEY_CHECKS;

-- END Microgifter Training Lab Stage 6 Consolidated SQL
