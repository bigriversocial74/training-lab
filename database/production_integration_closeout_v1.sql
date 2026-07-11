-- Training Lab Production Integration Closeout v1
-- Import after all established Training Lab, Stage 886/890, and Sections 15/17/18 migrations.
-- Additive evidence and administrator-decision schema only.
-- No Microgifter user, password, wallet, payment, gift, claim, redemption, or reward table is altered.

CREATE TABLE IF NOT EXISTS training_integration_closeout_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    campaign_id BIGINT UNSIGNED DEFAULT NULL,
    account_link_id BIGINT UNSIGNED DEFAULT NULL,
    email_pilot_run_id BIGINT UNSIGNED DEFAULT NULL,
    reward_handoff_id BIGINT UNSIGNED DEFAULT NULL,
    run_status ENUM('recorded','blocked','approved','rejected') NOT NULL DEFAULT 'recorded',
    report_hash CHAR(64) NOT NULL,
    score_percent INT UNSIGNED NOT NULL DEFAULT 0,
    passed_count INT UNSIGNED NOT NULL DEFAULT 0,
    required_count INT UNSIGNED NOT NULL DEFAULT 0,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    decision_notes VARCHAR(1000) DEFAULT NULL,
    recorded_by_user_id BIGINT UNSIGNED NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    rejected_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    rejected_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_integration_closeout_runs_public (public_id),
    UNIQUE KEY uq_training_integration_closeout_runs_report_hash (report_hash),
    KEY idx_training_integration_closeout_runs_status (run_status, recorded_at),
    KEY idx_training_integration_closeout_runs_campaign (campaign_id, recorded_at),
    KEY idx_training_integration_closeout_runs_account (account_link_id, recorded_at),
    KEY idx_training_integration_closeout_runs_reward (reward_handoff_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_integration_closeout_checks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    check_group_id CHAR(36) NOT NULL,
    closeout_run_id BIGINT UNSIGNED NOT NULL,
    category_key VARCHAR(64) NOT NULL,
    check_key VARCHAR(96) NOT NULL,
    check_label VARCHAR(191) NOT NULL,
    check_status ENUM('passed','failed','pending') NOT NULL,
    observed_value VARCHAR(191) DEFAULT NULL,
    required_value VARCHAR(191) DEFAULT NULL,
    detail VARCHAR(700) DEFAULT NULL,
    evidence_hash CHAR(64) DEFAULT NULL,
    evaluated_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    evaluated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_integration_closeout_checks_public (public_id),
    UNIQUE KEY uq_training_integration_closeout_checks_group_key (check_group_id, check_key),
    KEY idx_training_integration_closeout_checks_run (closeout_run_id, evaluated_at),
    KEY idx_training_integration_closeout_checks_category (category_key, check_status, evaluated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_integration_closeout_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    closeout_run_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(96) NOT NULL,
    severity ENUM('info','warning','critical','success') NOT NULL DEFAULT 'info',
    event_summary VARCHAR(255) NOT NULL,
    metadata_json JSON DEFAULT NULL,
    actor_user_id BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_integration_closeout_events_public (public_id),
    KEY idx_training_integration_closeout_events_run (closeout_run_id, created_at),
    KEY idx_training_integration_closeout_events_type (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
