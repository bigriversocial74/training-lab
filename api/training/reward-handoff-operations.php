<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-stage891-reward-handoff-recovery.php';
require_once __DIR__ . '/../../includes/training-lab-stage891-owned-processor.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
try {
    if ($method === 'GET') {
        $user = tl_auth_current_user();
        if (!tl_security_developer_key_valid() && !tl_auth_role_allowed($user, 'manager')) {
            throw new TlHttpException('A trusted manager or administrator account is required.', 403, 'reward_handoff_operations_forbidden');
        }
        tl_security_headers(true);
        tl_security_json_response(['ok'=>true,'data'=>tl_stage891_acceptance_summary()]);
        exit;
    }
    if ($method !== 'POST') {
        if (!headers_sent()) header('Allow: GET, POST');
        throw new TlHttpException('This endpoint supports GET and POST only.', 405, 'method_not_allowed');
    }
    $raw = tl_security_request_data(false);
    $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($raw['training_action'] ?? $raw['action'] ?? 'stage891_run_handoff_acceptance'));
    $user = tl_security_guard_write($action, $raw);
    $input = tl_security_apply_actor($raw, $user);
    if ($action === 'stage891_recover_stale_handoffs') {
        $result = tl_stage891_recover_stale_processing($input);
    } elseif ($action === 'stage891_requeue_handoff') {
        $result = tl_stage891_requeue_handoff($input);
    } elseif ($action === 'stage891_process_resilient_batch') {
        $result = tl_stage891_process_owned_batch($input);
    } elseif ($action === 'stage891_run_handoff_acceptance') {
        $result = tl_stage891_run_acceptance($input);
    } else {
        throw new TlHttpException('Unsupported Stage 891 action.', 422, 'unsupported_stage891_action');
    }
    tl_security_json_response(['ok'=>true,'action'=>$action,'data'=>$result]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
