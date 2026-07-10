-- Stage 890 Reward Handoff Outbox v1
-- Import once into the existing Training Lab database.
-- This migration does not alter Microgifter authentication, wallet, payment,
-- claim, redemption, gift, or reward tables.

CREATE TABLE IF NOT EXISTS training_reward_handoffs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    reward_event_id BIGINT UNSIGNED NOT NULL,
    account_link_id BIGINT UNSIGNED DEFAULT NULL,
    microgifter_user_id VARCHAR(191) DEFAULT NULL,
    idempotency_key CHAR(64) NOT NULL,
    handoff_status ENUM('queued','blocked','processing','delivered','failed','cancelled') NOT NULL DEFAULT 'queued',
    adapter_mode VARCHAR(64) DEFAULT NULL,
    adapter_name VARCHAR(191) DEFAULT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME DEFAULT NULL,
    last_attempt_at DATETIME DEFAULT NULL,
    locked_at DATETIME DEFAULT NULL,
    locked_by VARCHAR(191) DEFAULT NULL,
    delivered_at DATETIME DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    external_reference VARCHAR(191) DEFAULT NULL,
    failure_code VARCHAR(120) DEFAULT NULL,
    failure_message VARCHAR(500) DEFAULT NULL,
    payload_json JSON NOT NULL,
    response_json JSON DEFAULT NULL,
    metadata_json JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_reward_handoffs_public_id (public_id),
    UNIQUE KEY uq_training_reward_handoffs_reward_event (reward_event_id),
    UNIQUE KEY uq_training_reward_handoffs_idempotency (idempotency_key),
    KEY idx_training_reward_handoffs_status_due (handoff_status, next_attempt_at),
    KEY idx_training_reward_handoffs_account_link (account_link_id),
    KEY idx_training_reward_handoffs_microgifter_user (microgifter_user_id),
    KEY idx_training_reward_handoffs_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
