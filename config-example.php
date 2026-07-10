<?php
/**
 * Microgifter Training Lab database config example.
 *
 * IMPORTANT FOR DAVID'S CPANEL WORKFLOW
 * ------------------------------------
 * This file is packaged at /labs/labs/config.php. After extracting the zip and
 * moving the CONTENTS of the first /labs/ folder into web root, it lands at
 * /labs/config.php. Edit only that deployed private file.
 *
 * Do not commit live credentials, developer keys, identity shared secrets, or
 * signed integration shared secrets.
 */
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'ywzyeite_microlabs',
        'username' => 'ywzyeite_microlabs',
        'password' => 'PUT_YOUR_DATABASE_PASSWORD_HERE',
        'charset' => 'utf8mb4',
    ],
    'training_lab' => [
        'mode' => 'database',
        'debug' => false,
        'allow_demo_session_login' => false,
        'proof_records_only_no_real_uploads' => true,
        'reward_events_only_no_wallet_balance_changes' => true,
        'payments_enabled' => false,
        'claim_redeem_enabled' => false,
        'use_existing_microgifter_auth' => true,
        'identity_issuer' => 'microgifter.com',
        'identity_audience' => 'training-lab',
        'identity_max_ttl_seconds' => 180,
        'identity_clock_skew_seconds' => 30,
        'identity_session_ttl_seconds' => 28800,
        'identity_session_idle_ttl_seconds' => 3600,
        'reward_handoff_processing_enabled' => false,
        'reward_handoff_batch_size' => 10,
        'reward_handoff_max_attempts' => 5,
        'reward_handoff_retry_base_seconds' => 300,
        'reward_handoff_lease_seconds' => 300,
        'reward_handoff_recovery_batch_size' => 25,
        'reward_handoff_worker_enabled' => false,
        'reward_handoff_worker_batch_size' => 10,
        'reward_handoff_worker_max_runtime_seconds' => 45,
        'reward_handoff_worker_actor_user_id' => 1,
        'reward_handoff_worker_lock_file' => sys_get_temp_dir() . '/training-lab-stage892-reward-worker.lock',
        'reward_delivery_reconciliation_enabled' => false,
        'reward_delivery_reconciliation_batch_size' => 25,
        'reward_delivery_reconciliation_min_age_seconds' => 300,
        'microgifter_reward_lookup_enabled' => false,
        'microgifter_reward_lookup_url' => 'https://microgifter.com/api/integrations/training-lab-reward-lookup.php',
        'microgifter_reward_lookup_allowed_hosts' => ['microgifter.com', 'www.microgifter.com'],
        'microgifter_reward_lookup_timeout_seconds' => 8,
        'microgifter_reward_lookup_connect_timeout_seconds' => 3,
        'microgifter_reward_lookup_max_response_bytes' => 131072,
        'stage895_live_acceptance_enabled' => false,
        'stage896_limited_pilot_enabled' => false,
        'stage896_max_value_cents' => 2500,
        'stage896_acceptance_max_age_seconds' => 86400,
        'microgifter_pilot_issue_enabled' => false,
        'microgifter_pilot_issue_url' => 'https://microgifter.com/api/integrations/training-lab-reward-pilot-issue.php',
        'microgifter_pilot_issue_allowed_hosts' => ['microgifter.com', 'www.microgifter.com'],
        'microgifter_pilot_issue_timeout_seconds' => 10,
        'microgifter_pilot_issue_connect_timeout_seconds' => 3,
        'microgifter_pilot_issue_max_response_bytes' => 131072,
        // Prefer server environment variables for all secrets and production gates.
        // 'identity_shared_secret' => 'DO_NOT_COMMIT_A_REAL_SECRET',
        // 'microgifter_reward_lookup_secret' => 'DO_NOT_COMMIT_A_REAL_SECRET',
        // 'microgifter_pilot_issue_secret' => 'DO_NOT_COMMIT_A_REAL_SECRET',
    ],
];
