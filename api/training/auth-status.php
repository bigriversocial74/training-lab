<?php
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/training-lab-account-bridge.php';
$config = tl_db_config_diagnostics();
tl_stage34_json([
    'ok' => true,
    'account_bridge' => tl_account_bridge_current_context(),
    'backend' => function_exists('tl_stage130_backend_summary') ? tl_stage130_backend_summary() : [],
    'db_connected' => tl_db_ready(),
    'config_ready' => tl_db_config_ready(),
    'config_expected_path' => $config['expected_path'] ?? null,
    'safe_boundaries' => [
        'microgifter_account_creation_requires_adapter' => true,
        'no_unknown_microgifter_auth_table_writes' => true,
        'proof_records_only_no_real_uploads' => true,
        'reward_events_only_no_wallet_balance_changes' => true,
        'no_payments' => true,
        'no_claim_redeem_logic' => true,
    ],
]);
