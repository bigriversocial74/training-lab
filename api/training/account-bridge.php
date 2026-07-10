<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-account-bridge.php';

try {
    $result = null;
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        $raw = tl_security_request_data(false);
        $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($raw['auth_action'] ?? $raw['action'] ?? ''));
        if ($action === '') throw new TlHttpException('Account action is required.', 422, 'action_required');
        tl_security_guard_auth_action($action, $raw);
        $result = tl_account_bridge_handle_auth_action(tl_security_normalize_auth_input($raw));
    }
    tl_security_json_response([
        'ok'=>true,
        'action_result'=>$result,
        'account_bridge'=>tl_account_bridge_current_context(),
        'csrf_token'=>tl_security_csrf_token(),
    ]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
