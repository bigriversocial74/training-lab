<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-stage893-processing-wrapper.php';

try {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'POST') {
        $raw = tl_security_request_data(false);
        $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($raw['training_action'] ?? $raw['action'] ?? ''));
        $allowed = [
            'enqueue_reward_handoff' => 'tl_stage890_enqueue_reward_event',
            'sync_reward_handoff_outbox' => 'tl_stage893_sync_outbox_guarded',
            'process_reward_handoff' => 'tl_stage893_process_handoff_guarded',
            'process_reward_handoff_batch' => 'tl_stage893_process_guarded_batch',
            'cancel_reward_handoff' => 'tl_stage890_cancel_handoff',
        ];
        if (!isset($allowed[$action])) throw new TlHttpException('Unsupported reward handoff action.', 422, 'reward_handoff_action_invalid');
        $user = tl_security_guard_write($action, $raw);
        $data = tl_security_apply_actor($raw, $user);
        $fn = $allowed[$action];
        $result = $fn($data);
        tl_security_json_response(['ok'=>true,'action'=>$action,'result'=>$result,'outbox'=>tl_stage890_summary(),'reconciliation'=>tl_stage893_summary()]);
        exit;
    }
    if ($method !== 'GET') {
        if (!headers_sent()) header('Allow: GET, POST');
        throw new TlHttpException('This endpoint requires GET or POST.', 405, 'method_not_allowed');
    }
    $user = tl_auth_current_user();
    if (!tl_security_developer_key_valid() && !tl_auth_role_allowed($user, 'manager')) {
        throw new TlHttpException('A trusted manager or administrator account is required.', 403, 'reward_handoff_outbox_forbidden');
    }
    tl_security_json_response(['ok'=>true,'outbox'=>tl_stage890_summary(),'acceptance'=>tl_stage891_acceptance_summary(),'reconciliation'=>tl_stage893_summary()]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
