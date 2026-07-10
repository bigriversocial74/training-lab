<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-stage899-limited-scheduled-processing.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
try {
    if ($method === 'GET') {
        $user = tl_auth_current_user();
        if (!tl_security_developer_key_valid() && !tl_auth_role_allowed($user, 'manager')) {
            throw new TlHttpException('A trusted manager or administrator account is required.', 403, 'stage899_forbidden');
        }
        tl_security_json_response(['ok'=>true,'data'=>tl_stage899_summary()]);
        exit;
    }
    if ($method !== 'POST') {
        if (!headers_sent()) header('Allow: GET, POST');
        throw new TlHttpException('This endpoint supports GET and POST only.', 405, 'method_not_allowed');
    }
    $input = tl_security_request_data(false);
    $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['training_action'] ?? $input['action'] ?? ''));
    if ($action !== 'stage899_acknowledge_suspension') {
        throw new TlHttpException('Unsupported Stage 899 action.', 422, 'stage899_action_unsupported');
    }
    $user = tl_security_guard_write($action, $input);
    $input = tl_security_apply_actor($input, $user);
    $result = tl_stage899_acknowledge_suspension($input);
    tl_security_json_response(['ok'=>true,'action'=>$action,'data'=>$result,'summary'=>tl_stage899_summary()]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
