<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-stage893-processing-wrapper.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
try {
    if ($method === 'GET') {
        $user = tl_auth_current_user();
        if (!tl_security_developer_key_valid() && !tl_auth_role_allowed($user, 'manager')) {
            throw new TlHttpException('A trusted manager or administrator account is required.', 403, 'reward_reconciliation_forbidden');
        }
        tl_security_json_response(['ok'=>true,'data'=>tl_stage893_summary_guarded()]);
        exit;
    }
    if ($method !== 'POST') {
        if (!headers_sent()) header('Allow: GET, POST');
        throw new TlHttpException('This endpoint supports GET and POST only.', 405, 'method_not_allowed');
    }
    $raw = tl_security_request_data(false);
    $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($raw['training_action'] ?? $raw['action'] ?? 'stage893_reconcile_delivery_batch'));
    $user = tl_security_guard_write($action, $raw);
    $input = tl_security_apply_actor($raw, $user);
    if ($action === 'stage893_reconcile_delivery') {
        $result = tl_stage893_reconcile_handoff_guarded($input);
    } elseif ($action === 'stage893_reconcile_delivery_batch') {
        $result = tl_stage893_reconcile_batch_guarded($input);
    } else {
        throw new TlHttpException('Unsupported Stage 893 action.', 422, 'unsupported_stage893_action');
    }
    tl_security_json_response(['ok'=>true,'action'=>$action,'data'=>$result,'summary'=>tl_stage893_summary_guarded()]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
