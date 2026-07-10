<?php
require_once __DIR__ . '/../../includes/training-lab-stage886-admin.php';

try {
    $input = tl_security_request_data(false);
    $user = tl_security_guard_write('backend_health_snapshot', $input);
    if (!tl_security_developer_key_valid() && !tl_auth_role_allowed($user, 'manager')) {
        throw new TlHttpException('A trusted manager or administrator account is required.', 403, 'account_integration_forbidden');
    }
    $publicId = trim((string)($input['account_link_public_id'] ?? $input['public_id'] ?? ''));
    $result = tl_stage886_revoke_link($publicId, tl_security_numeric_user_id($user));
    tl_security_json_response(['ok'=>true,'account_link'=>$result]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
