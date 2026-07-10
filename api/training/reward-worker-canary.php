<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-stage898-worker-canary-monitoring.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
try {
    if ($method === 'GET') {
        $user = tl_auth_current_user();
        if (!tl_security_developer_key_valid() && !tl_auth_role_allowed($user, 'manager')) {
            throw new TlHttpException('A trusted manager or administrator account is required.', 403, 'stage898_forbidden');
        }
        tl_security_json_response(['ok'=>true,'data'=>tl_stage898_summary()]);
        exit;
    }
    if ($method !== 'POST') {
        if (!headers_sent()) header('Allow: GET, POST');
        throw new TlHttpException('This endpoint supports GET and POST only.', 405, 'method_not_allowed');
    }
    $input = tl_security_request_data(false);
    $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['training_action'] ?? $input['action'] ?? ''));
    if ($action !== 'stage898_acknowledge_canary_pause') {
        throw new TlHttpException('Unsupported Stage 898 action.', 422, 'stage898_action_unsupported');
    }
    $user = tl_security_guard_write($action, $input);
    $input = tl_security_apply_actor($input, $user);
    $result = tl_stage898_acknowledge_pause($input);
    tl_security_json_response(['ok'=>true,'action'=>$action,'data'=>$result,'summary'=>tl_stage898_summary()]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
