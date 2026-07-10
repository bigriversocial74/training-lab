-- Stage 886 Shared Microgifter Account Integration v1
-- Import once into the existing Training Lab database.
-- This migration does not alter Microgifter user or password tables.

CREATE TABLE IF NOT EXISTS training_account_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    microgifter_user_id VARCHAR(191) NOT NULL,
    training_user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(254) DEFAULT NULL,
    display_name VARCHAR(191) DEFAULT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'participant',
    merchant_context VARCHAR(191) DEFAULT NULL,
    organization_context VARCHAR(191) DEFAULT NULL,
    issuer VARCHAR(191) NOT NULL,
    audience VARCHAR(191) NOT NULL,
    link_status ENUM('active','pending','revoked','suspended') NOT NULL DEFAULT 'active',
    last_authenticated_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    revoked_at DATETIME DEFAULT NULL,
    metadata_json JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_account_links_public_id (public_id),
    UNIQUE KEY uq_training_account_links_microgifter_user (microgifter_user_id),
    KEY idx_training_account_links_training_user (training_user_id),
    KEY idx_training_account_links_status (link_status),
    KEY idx_training_account_links_last_auth (last_authenticated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_auth_nonces (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nonce_hash CHAR(64) NOT NULL,
    issuer VARCHAR(191) NOT NULL,
    audience VARCHAR(191) NOT NULL,
    microgifter_user_id VARCHAR(191) DEFAULT NULL,
    nonce_status ENUM('issued','consumed','expired','rejected') NOT NULL DEFAULT 'consumed',
    issued_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME DEFAULT NULL,
    rejected_reason VARCHAR(191) DEFAULT NULL,
    request_ip_hash CHAR(64) DEFAULT NULL,
    user_agent_hash CHAR(64) DEFAULT NULL,
    metadata_json JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_auth_nonces_hash (nonce_hash),
    KEY idx_training_auth_nonces_status_expiry (nonce_status, expires_at),
    KEY idx_training_auth_nonces_microgifter_user (microgifter_user_id),
    KEY idx_training_auth_nonces_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
