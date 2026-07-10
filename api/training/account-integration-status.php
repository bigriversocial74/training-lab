<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-stage886-account-integration.php';

try {
    tl_security_require_method('GET');
    $user = tl_auth_current_user();
    if (!tl_security_developer_key_valid() && (!$user || !tl_auth_role_allowed($user, 'manager'))) {
        throw new TlHttpException('Manager or administrator access is required.', 403, 'account_integration_forbidden');
    }
    tl_security_json_response(['ok'=>true,'account_integration'=>tl_stage886_admin_summary()]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
