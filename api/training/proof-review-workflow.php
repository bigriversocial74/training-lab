<?php
require_once __DIR__ . '/../../includes/training-lab-stage885-proof-review-handoff.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$proofRef = isset($_GET['proof']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$_GET['proof']) : null;

if ($method === 'POST') {
    try {
        $input = function_exists('tl_request_data') ? tl_request_data() : ($_POST ?: []);
        tl_stage34_json(tl_stage885_submit_review_decision($input));
    } catch (Throwable $e) {
        tl_json_response(['ok' => false, 'error' => $e->getMessage(), 'stage' => 'Stage 885 Proof Review + Award Handoff Preview'], 400);
    }
    exit;
}

tl_stage34_json(tl_stage885_summary($proofRef));
exit;
