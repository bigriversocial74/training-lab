<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
$stage885Path = __DIR__ . '/../../includes/training-lab-stage885-proof-review-handoff.php';
if (!is_file($stage885Path)) {
    tl_security_json_response(['ok'=>false,'stage'=>'Stage 885 Proof Review + Award Handoff Preview','error'=>'Stage 885 service is unavailable.','error_code'=>'stage885_missing'], 500);
    exit;
}
require_once $stage885Path;
$stage894Path = __DIR__ . '/../../includes/training-lab-stage894-reconciliation-bootstrap.php';
if (is_file($stage894Path)) require_once $stage894Path;

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$proofRef = isset($_GET['proof']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$_GET['proof']) : null;

try {
    if ($method === 'POST') {
        $raw = tl_security_request_data(false);
        $user = tl_security_guard_write('stage885_review_proof', $raw);
        $input = tl_security_apply_actor($raw, $user);
        $data = tl_stage885_submit_review_decision($input);
        if (function_exists('tl_stage893_sync_outbox_guarded') && tl_stage890_table_ready()) {
            try { $data['stage890_outbox_sync'] = tl_stage893_sync_outbox_guarded($input + ['limit'=>25]); }
            catch (Throwable $syncError) { $data['stage890_outbox_sync'] = ['ok'=>false,'error'=>$syncError->getMessage()]; }
        }
        if (function_exists('tl_stage894_summary')) $data['stage894_reward_lookup_client'] = tl_stage894_summary();
        tl_security_json_response(['ok'=>true,'data'=>$data]);
        exit;
    }
    if ($method !== 'GET') throw new TlHttpException('This endpoint supports GET and POST only.', 405, 'method_not_allowed');
    tl_security_headers(true);
    tl_security_json_response(['ok'=>true,'data'=>tl_stage885_summary($proofRef),'reward_lookup_client'=>function_exists('tl_stage894_summary') ? tl_stage894_summary() : null]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
