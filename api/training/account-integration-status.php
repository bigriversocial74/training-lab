<?php
require_once __DIR__ . '/../../includes/training-lab-stage886-admin.php';

try {
    tl_security_require_method('GET');
    $user = tl_auth_current_user();
    if (!tl_security_developer_key_valid() && !tl_auth_role_allowed($user, 'manager')) {
        throw new TlHttpException('A trusted manager or administrator account is required.', 403, 'account_integration_forbidden');
    }
    tl_security_json_response(['ok'=>true,'account_integration'=>tl_stage886_admin_summary()]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
