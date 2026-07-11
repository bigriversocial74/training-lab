-- Training Lab Pilot Operations + Communications v1
-- Import once into the existing Training Lab database.
-- This migration creates a Training Lab notification outbox only.
-- It does not alter Microgifter users, passwords, wallets, gifts, payments, claims, or redemptions.

CREATE TABLE IF NOT EXISTS training_notification_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    owner_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    template_key VARCHAR(96) NOT NULL,
    channel ENUM('email') NOT NULL DEFAULT 'email',
    message_class ENUM('transactional','reminder') NOT NULL DEFAULT 'transactional',
    template_name VARCHAR(191) NOT NULL,
    subject_template VARCHAR(255) NOT NULL,
    body_template TEXT NOT NULL,
    status ENUM('active','paused','archived') NOT NULL DEFAULT 'active',
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    updated_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_notification_templates_public_id (public_id),
    UNIQUE KEY uq_training_notification_templates_owner_key_channel (owner_user_id, template_key, channel),
    KEY idx_training_notification_templates_status (status),
    KEY idx_training_notification_templates_owner (owner_user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_notification_preferences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    account_link_id BIGINT UNSIGNED NOT NULL,
    transactional_enabled TINYINT(1) NOT NULL DEFAULT 1,
    reminder_enabled TINYINT(1) NOT NULL DEFAULT 1,
    changed_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    unsubscribed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_notification_preferences_public_id (public_id),
    UNIQUE KEY uq_training_notification_preferences_account_link (account_link_id),
    KEY idx_training_notification_preferences_reminders (reminder_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_notification_suppressions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    account_link_id BIGINT UNSIGNED DEFAULT NULL,
    email_hash CHAR(64) NOT NULL,
    suppression_type ENUM('manual','hard_bounce','complaint','invalid_recipient','policy') NOT NULL DEFAULT 'manual',
    reason VARCHAR(255) DEFAULT NULL,
    status ENUM('active','released') NOT NULL DEFAULT 'active',
    created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    released_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    released_at DATETIME DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_notification_suppressions_public_id (public_id),
    UNIQUE KEY uq_training_notification_suppressions_email_hash (email_hash),
    KEY idx_training_notification_suppressions_account_link (account_link_id, status),
    KEY idx_training_notification_suppressions_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_pilot_controls (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    pilot_status ENUM('draft','active','paused','completed') NOT NULL DEFAULT 'draft',
    email_enabled TINYINT(1) NOT NULL DEFAULT 0,
    max_participants INT UNSIGNED NOT NULL DEFAULT 25,
    daily_notification_limit INT UNSIGNED NOT NULL DEFAULT 100,
    paused_reason VARCHAR(255) DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    updated_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_pilot_controls_public_id (public_id),
    UNIQUE KEY uq_training_pilot_controls_campaign (campaign_id),
    KEY idx_training_pilot_controls_owner_status (owner_user_id, pilot_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_notification_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    participant_id BIGINT UNSIGNED DEFAULT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    account_link_id BIGINT UNSIGNED DEFAULT NULL,
    template_id BIGINT UNSIGNED DEFAULT NULL,
    event_type VARCHAR(96) NOT NULL,
    source_type VARCHAR(64) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    message_class ENUM('transactional','reminder') NOT NULL DEFAULT 'transactional',
    channel ENUM('email') NOT NULL DEFAULT 'email',
    recipient_hash CHAR(64) DEFAULT NULL,
    idempotency_key CHAR(64) NOT NULL,
    outbox_status ENUM('queued','blocked','processing','delivered','failed','cancelled','suppressed') NOT NULL DEFAULT 'queued',
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
    scheduled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    next_attempt_at DATETIME DEFAULT NULL,
    leased_at DATETIME DEFAULT NULL,
    lease_token_hash CHAR(64) DEFAULT NULL,
    delivered_at DATETIME DEFAULT NULL,
    last_error_code VARCHAR(96) DEFAULT NULL,
    last_error_detail VARCHAR(255) DEFAULT NULL,
    provider_message_hash CHAR(64) DEFAULT NULL,
    context_json JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_notification_outbox_public_id (public_id),
    UNIQUE KEY uq_training_notification_outbox_idempotency (idempotency_key),
    KEY idx_training_notification_outbox_status_schedule (outbox_status, scheduled_at, next_attempt_at),
    KEY idx_training_notification_outbox_campaign (campaign_id, created_at),
    KEY idx_training_notification_outbox_account (account_link_id, outbox_status),
    KEY idx_training_notification_outbox_source (source_type, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_notification_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    outbox_id BIGINT UNSIGNED NOT NULL,
    attempt_no INT UNSIGNED NOT NULL,
    attempt_status ENUM('processing','delivered','failed','blocked','suppressed') NOT NULL,
    provider_name VARCHAR(96) DEFAULT NULL,
    provider_message_hash CHAR(64) DEFAULT NULL,
    response_code VARCHAR(64) DEFAULT NULL,
    error_code VARCHAR(96) DEFAULT NULL,
    error_detail VARCHAR(255) DEFAULT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    metadata_json JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_notification_attempts_public_id (public_id),
    UNIQUE KEY uq_training_notification_attempts_number (outbox_id, attempt_no),
    KEY idx_training_notification_attempts_status (attempt_status, created_at),
    KEY idx_training_notification_attempts_outbox (outbox_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO training_notification_templates
(public_id, owner_user_id, template_key, channel, message_class, template_name, subject_template, body_template, status, is_system)
VALUES
('15000000-0000-4000-8000-000000000001',0,'participant_invited','email','transactional','Participant invitation','You are invited to {{campaign_title}}','Hello {{participant_name}},\n\nYou have been invited to {{campaign_title}}. Open your Training Lab account to review the campaign and begin.\n\n{{action_url}}','active',1),
('15000000-0000-4000-8000-000000000002',0,'task_reminder','email','reminder','Task reminder','Your next Training Lab task is ready','Hello {{participant_name}},\n\nYour next task in {{campaign_title}} is ready: {{task_title}}.\n\n{{action_url}}\n\nManage reminders: {{unsubscribe_url}}','active',1),
('15000000-0000-4000-8000-000000000003',0,'proof_submitted','email','transactional','Proof submitted','We received your proof for {{task_title}}','Hello {{participant_name}},\n\nYour proof for {{task_title}} in {{campaign_title}} has been received and is waiting for review.\n\n{{action_url}}','active',1),
('15000000-0000-4000-8000-000000000004',0,'review_approved','email','transactional','Proof approved','Your proof was approved','Hello {{participant_name}},\n\nYour proof for {{task_title}} in {{campaign_title}} was approved.\n\n{{action_url}}','active',1),
('15000000-0000-4000-8000-000000000005',0,'review_revision_required','email','transactional','Proof update requested','An update is needed for {{task_title}}','Hello {{participant_name}},\n\nA reviewer requested an update for {{task_title}} in {{campaign_title}}. Review the feedback and submit a revised proof.\n\n{{action_url}}','active',1),
('15000000-0000-4000-8000-000000000006',0,'review_rejected','email','transactional','Proof not approved','Your proof needs attention','Hello {{participant_name}},\n\nYour proof for {{task_title}} in {{campaign_title}} was not approved. Open Training Lab to review the status and next action.\n\n{{action_url}}','active',1),
('15000000-0000-4000-8000-000000000007',0,'reward_earned','email','transactional','Reward earned','You earned {{reward_label}}','Hello {{participant_name}},\n\nYou earned {{reward_label}} through {{campaign_title}}. Open your reward page for the current fulfillment status.\n\n{{action_url}}','active',1),
('15000000-0000-4000-8000-000000000008',0,'reward_delivery_succeeded','email','transactional','Reward delivered','Your reward was delivered','Hello {{participant_name}},\n\n{{reward_label}} from {{campaign_title}} has been delivered.\n\n{{action_url}}','active',1),
('15000000-0000-4000-8000-000000000009',0,'reward_delivery_failed','email','transactional','Reward delivery update','Your reward delivery needs attention','Hello {{participant_name}},\n\nThe delivery of {{reward_label}} from {{campaign_title}} needs attention. Your reward remains recorded in Training Lab while the administrator reviews the issue.\n\n{{action_url}}','active',1);
