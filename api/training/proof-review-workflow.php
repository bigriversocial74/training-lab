<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
$stage885Path = __DIR__ . '/../../includes/training-lab-stage885-proof-review-handoff.php';
if (!is_file($stage885Path)) {
    tl_json_response([
        'ok' => false,
        'stage' => 'Stage 885 Proof Review + Award Handoff Preview',
        'error' => 'Stage 885 service file is missing from the deployed package.',
        'expected_file' => 'includes/training-lab-stage885-proof-review-handoff.php',
    ], 500);
    exit;
}
require_once $stage885Path;

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$proofRef = isset($_GET['proof']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$_GET['proof']) : null;

try {
    if ($method === 'POST') {
        $input = function_exists('tl_request_data') ? tl_request_data() : ($_POST ?: []);
        tl_stage34_json(tl_stage885_submit_review_decision($input));
        exit;
    }

    tl_stage34_json(tl_stage885_summary($proofRef));
    exit;
} catch (Throwable $e) {
    tl_json_response([
        'ok' => false,
        'stage' => 'Stage 885 Proof Review + Award Handoff Preview',
        'error' => $e->getMessage(),
        'safe_boundaries' => [
            'no_payment_processing' => true,
            'no_wallet_balance_mutation' => true,
            'no_claim_redeem_mutation' => true,
            'no_microgifter_reward_issuing' => true,
        ],
    ], 500);
    exit;
}
