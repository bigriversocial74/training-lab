<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';

$ref = isset($_GET['proof']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$_GET['proof']) : null;
tl_stage34_json([
    'stage' => 'Review inspector',
    'review_inspector' => tl_training_review_inspector_summary($ref),
    'queue' => tl_training_review_queue_snapshots(50),
    'safe_boundaries' => [
        'read_only_review_inspector' => true,
        'no_review_decision_writes' => true,
        'no_receipt_writes' => true,
        'no_reward_issuing' => true,
        'no_wallet_balance_changes' => true,
        'no_upload_processing' => true,
        'no_claim_redeem_logic' => true,
        'no_auth_gate_added' => true,
        'no_new_sql_required' => true,
    ],
]);
