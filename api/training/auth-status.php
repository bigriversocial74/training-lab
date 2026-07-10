<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/training-lab-account-bridge.php';

try {
    tl_security_require_method('GET');
    $config = tl_db_config_diagnostics();
    tl_security_json_response([
        'ok'=>true,
        'account_bridge'=>tl_account_bridge_current_context(),
        'backend'=>function_exists('tl_stage130_backend_summary') ? tl_stage130_backend_summary() : [],
        'db_connected'=>tl_db_ready(),
        'config_ready'=>tl_db_config_ready(),
        'config_expected_path'=>$config['expected_path'] ?? null,
        'csrf_token'=>tl_security_csrf_token(),
        'safe_boundaries'=>[
            'microgifter_account_creation_requires_adapter'=>true,
            'no_unknown_microgifter_auth_table_writes'=>true,
            'proof_records_only_no_real_uploads'=>true,
            'reward_events_only_no_wallet_balance_changes'=>true,
            'no_payments'=>true,
            'no_claim_redeem_logic'=>true,
            'write_actions_require_auth_and_csrf'=>true,
        ],
    ]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
