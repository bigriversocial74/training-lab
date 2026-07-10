<?php
require_once __DIR__ . '/../../includes/labs-layout.php';
require_once __DIR__ . '/../../includes/training-lab-production-runtime-acceptance.php';

try {
    tl_security_require_method('GET');
    $user = function_exists('tl_auth_current_user') ? tl_auth_current_user() : null;
    $authorized = tl_security_developer_key_valid() || (function_exists('tl_auth_role_allowed') && tl_auth_role_allowed($user, 'manager'));
    if (!$authorized) {
        throw new TlHttpException('A trusted manager or administrator session is required.', 403, 'runtime_acceptance_forbidden');
    }

    $runProbes = (string)($_GET['probe'] ?? '') === '1';
    if ($runProbes) tl_security_rate_limit('runtime_acceptance_probe', 5, 60);
    tl_security_json_response(['ok' => true, 'acceptance' => tl_runtime_acceptance_summary($runProbes)]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
exit;
