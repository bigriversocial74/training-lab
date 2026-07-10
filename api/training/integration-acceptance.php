<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-stage895-integration-acceptance.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
try {
    if ($method === 'GET') {
        $user = tl_auth_current_user();
        if (!tl_security_developer_key_valid() && !tl_auth_role_allowed($user, 'manager')) {
            throw new TlHttpException('A trusted manager or administrator account is required.', 403, 'stage895_acceptance_forbidden');
        }
        tl_security_json_response(['ok'=>true,'data'=>tl_stage895_readiness()]);
        exit;
    }
    if ($method !== 'POST') {
        if (!headers_sent()) header('Allow: GET, POST');
        throw new TlHttpException('This endpoint supports GET and POST only.', 405, 'method_not_allowed');
    }
    $raw = tl_security_request_data(false);
    $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($raw['training_action'] ?? $raw['action'] ?? 'stage895_run_signed_acceptance'));
    if ($action !== 'stage895_run_signed_acceptance') {
        throw new TlHttpException('Unsupported Stage 895 action.', 422, 'unsupported_stage895_action');
    }
    $user = tl_security_guard_write($action, $raw);
    $input = tl_security_apply_actor($raw, $user);
    $result = tl_stage895_run_suite($input);
    tl_security_json_response(['ok'=>true,'action'=>$action,'data'=>$result,'readiness'=>tl_stage895_readiness()]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
