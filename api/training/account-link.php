<?php
require_once __DIR__ . '/../../includes/training-lab-stage886-account-integration.php';

try {
    tl_security_headers(true);
    tl_security_require_method('POST');
    tl_security_rate_limit('stage886_account_link', 20, 300);
    $input = tl_security_request_data(false);
    $token = trim((string)($input['identity_assertion'] ?? $input['token'] ?? ''));
    if ($token === '') throw new TlHttpException('Signed identity assertion is required.', 422, 'identity_token_required');
    $result = tl_stage886_accept_handoff($token);
    tl_security_json_response([
        'ok'=>true,
        'authenticated'=>true,
        'user'=>$result['user'],
        'account_link'=>$result['link'],
        'redirect'=>function_exists('labs_url') ? labs_url('/app/index.php') : '/app/index.php',
        'safe_boundaries'=>[
            'signed_assertion_only'=>true,
            'passwords_not_copied'=>true,
            'no_microgifter_auth_table_writes'=>true,
        ],
    ]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
