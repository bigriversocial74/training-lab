<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-stage892-scheduled-worker.php';

try {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'GET') {
        if (!headers_sent()) header('Allow: GET');
        throw new TlHttpException('This endpoint requires GET.', 405, 'method_not_allowed');
    }
    $user = tl_auth_current_user();
    if (!tl_security_developer_key_valid() && !tl_auth_role_allowed($user, 'manager')) {
        throw new TlHttpException('A trusted manager or administrator account is required.', 403, 'reward_handoff_worker_status_forbidden');
    }
    tl_security_json_response(['ok'=>true,'worker'=>tl_stage892_status_summary()]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
