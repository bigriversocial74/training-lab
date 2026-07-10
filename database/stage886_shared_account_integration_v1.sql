-- Stage 886 Shared Microgifter Account Integration v1
-- Import into the existing Training Lab database before enabling the account bridge secret.
-- This migration does not alter Microgifter user/password tables.

SET NAMES utf8mb4;
START TRANSACTION;

CREATE TABLE IF NOT EXISTS training_account_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    identity_key CHAR(64) NOT NULL,
    issuer VARCHAR(191) NOT NULL,
    microgifter_user_id VARCHAR(191) NOT NULL,
    training_numeric_user_id BIGINT UNSIGNED NULL,
    email VARCHAR(254) NULL,
    email_hash CHAR(64) NULL,
    display_name VARCHAR(190) NOT NULL,
    normalized_role VARCHAR(32) NOT NULL DEFAULT 'participant',
    merchant_id VARCHAR(191) NULL,
    organization_id VARCHAR(191) NULL,
    link_status ENUM('active','pending','revoked','suspended') NOT NULL DEFAULT 'active',
    last_authenticated_at DATETIME NULL,
    last_role_sync_at DATETIME NULL,
    trust_expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    revoked_by_user_id BIGINT UNSIGNED NULL,
    revoke_reason VARCHAR(255) NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_account_links_public_id (public_id),
    UNIQUE KEY uq_training_account_links_identity_key (identity_key),
    UNIQUE KEY uq_training_account_links_numeric_user (training_numeric_user_id),
    KEY idx_training_account_links_status (link_status),
    KEY idx_training_account_links_email_hash (email_hash),
    KEY idx_training_account_links_merchant (merchant_id),
    KEY idx_training_account_links_last_auth (last_authenticated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_auth_nonces (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    account_link_id BIGINT UNSIGNED NULL,
    nonce_hash CHAR(64) NOT NULL,
    token_id_hash CHAR(64) NOT NULL,
    issuer VARCHAR(191) NOT NULL,
    subject_hash CHAR(64) NOT NULL,
    nonce_status ENUM('consumed','rejected','expired') NOT NULL DEFAULT 'consumed',
    issued_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    request_id VARCHAR(80) NULL,
    client_ip_hash CHAR(64) NULL,
    user_agent_hash CHAR(64) NULL,
    failure_code VARCHAR(64) NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_auth_nonces_public_id (public_id),
    UNIQUE KEY uq_training_auth_nonces_nonce_hash (nonce_hash),
    UNIQUE KEY uq_training_auth_nonces_token_id_hash (token_id_hash),
    KEY idx_training_auth_nonces_link (account_link_id),
    KEY idx_training_auth_nonces_status (nonce_status),
    KEY idx_training_auth_nonces_expires (expires_at),
    CONSTRAINT fk_training_auth_nonces_account_link
        FOREIGN KEY (account_link_id) REFERENCES training_account_links(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
