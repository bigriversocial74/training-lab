-- Training Lab Resend Webhooks + Delivery Reconciliation v1
-- Import once after database/pilot_operations_communications_v1.sql.
-- This migration is additive and stores only hashes and sanitized provider metadata.
-- It does not alter Microgifter users, passwords, wallets, gifts, payments, claims, rewards, or redemptions.

CREATE TABLE IF NOT EXISTS training_notification_provider_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    provider_name VARCHAR(32) NOT NULL DEFAULT 'resend',
    svix_id_hash CHAR(64) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    delivery_status VARCHAR(32) NOT NULL,
    event_rank SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    provider_message_hash CHAR(64) DEFAULT NULL,
    outbox_id BIGINT UNSIGNED DEFAULT NULL,
    account_link_id BIGINT UNSIGNED DEFAULT NULL,
    recipient_hash CHAR(64) DEFAULT NULL,
    processing_status ENUM('reconciled','ignored','orphaned','failed') NOT NULL DEFAULT 'ignored',
    signature_timestamp BIGINT UNSIGNED NOT NULL,
    event_occurred_at DATETIME DEFAULT NULL,
    duplicate_count INT UNSIGNED NOT NULL DEFAULT 0,
    suppression_created TINYINT(1) NOT NULL DEFAULT 0,
    error_code VARCHAR(96) DEFAULT NULL,
    error_detail VARCHAR(255) DEFAULT NULL,
    metadata_json JSON DEFAULT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reconciled_at DATETIME DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_notification_provider_events_public_id (public_id),
    UNIQUE KEY uq_training_notification_provider_events_svix_hash (svix_id_hash),
    KEY idx_training_notification_provider_events_message (provider_message_hash, event_occurred_at),
    KEY idx_training_notification_provider_events_status (processing_status, received_at),
    KEY idx_training_notification_provider_events_outbox (outbox_id, event_occurred_at),
    KEY idx_training_notification_provider_events_type (event_type, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_notification_provider_states (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    outbox_id BIGINT UNSIGNED NOT NULL,
    provider_message_hash CHAR(64) NOT NULL,
    current_event_id BIGINT UNSIGNED NOT NULL,
    delivery_status ENUM('sent','delayed','delivered','failed','suppressed','bounced','complained') NOT NULL,
    event_rank SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    event_occurred_at DATETIME NOT NULL,
    first_event_at DATETIME NOT NULL,
    last_event_at DATETIME NOT NULL,
    sent_at DATETIME DEFAULT NULL,
    delayed_at DATETIME DEFAULT NULL,
    delivered_at DATETIME DEFAULT NULL,
    failed_at DATETIME DEFAULT NULL,
    suppressed_at DATETIME DEFAULT NULL,
    bounced_at DATETIME DEFAULT NULL,
    complained_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_notification_provider_states_public_id (public_id),
    UNIQUE KEY uq_training_notification_provider_states_outbox (outbox_id),
    UNIQUE KEY uq_training_notification_provider_states_message (provider_message_hash),
    KEY idx_training_notification_provider_states_status (delivery_status, last_event_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
