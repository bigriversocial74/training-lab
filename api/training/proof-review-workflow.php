<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
$stage885Path = __DIR__ . '/../../includes/training-lab-stage885-proof-review-handoff.php';
if (!is_file($stage885Path)) {
    tl_security_json_response(['ok'=>false,'stage'=>'Stage 885 Proof Review + Award Handoff Preview','error'=>'Stage 885 service is unavailable.','error_code'=>'stage885_missing'], 500);
    exit;
}
require_once $stage885Path;
$stage890Path = __DIR__ . '/../../includes/training-lab-stage890-reward-handoff-outbox.php';
if (is_file($stage890Path)) require_once $stage890Path;

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$proofRef = isset($_GET['proof']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$_GET['proof']) : null;

try {
    if ($method === 'POST') {
        $raw = tl_security_request_data(false);
        $user = tl_security_guard_write('stage885_review_proof', $raw);
        $input = tl_security_apply_actor($raw, $user);
        $data = tl_stage885_submit_review_decision($input);
        if (function_exists('tl_stage890_sync_outbox') && tl_stage890_table_ready()) {
            try { $data['stage890_outbox_sync'] = tl_stage890_sync_outbox($input + ['limit'=>25]); }
            catch (Throwable $syncError) { $data['stage890_outbox_sync'] = ['ok'=>false,'error'=>$syncError->getMessage()]; }
        }
        tl_security_json_response(['ok'=>true,'data'=>$data]);
        exit;
    }
    if ($method !== 'GET') throw new TlHttpException('This endpoint supports GET and POST only.', 405, 'method_not_allowed');
    tl_security_headers(true);
    tl_security_json_response(['ok'=>true,'data'=>tl_stage885_summary($proofRef)]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
