<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/training-lab-account-bridge.php';
require_once __DIR__ . '/../../includes/training-lab-stage886-account-integration.php';
require_once __DIR__ . '/../../includes/training-lab-stage886-session-policy.php';

try {
    tl_security_require_method('GET');
    $config = tl_db_config_diagnostics();
    $principal = tl_stage886_current_principal();
    tl_security_json_response([
        'ok'=>true,
        'account_bridge'=>tl_account_bridge_current_context(),
        'stage886'=>[
            'configured'=>tl_stage886_enabled(),
            'schema_ready'=>tl_stage886_schema_ready(),
            'authenticated'=>$principal !== null,
            'principal'=>$principal,
            'assertion_version'=>'v1',
            'session_ttl_seconds'=>tl_stage886_session_ttl_seconds(),
        ],
        'backend'=>function_exists('tl_stage130_backend_summary') ? tl_stage130_backend_summary() : [],
        'db_connected'=>tl_db_ready(),
        'config_ready'=>tl_db_config_ready(),
        'config_expected_path'=>$config['expected_path'] ?? null,
        'csrf_token'=>tl_security_csrf_token(),
        'safe_boundaries'=>[
            'signed_microgifter_identity_required_when_configured'=>true,
            'no_password_copy'=>true,
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
