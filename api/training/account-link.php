<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-stage886-account-integration.php';

try {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'POST') {
        tl_security_headers(true);
        tl_security_rate_limit('stage886_account_link_api', 20, 300);
        $input = tl_security_request_data(false);
        $assertion = (string)($input['assertion'] ?? $input['token'] ?? '');
        $result = tl_stage886_consume_assertion($assertion);
        tl_security_json_response(['ok'=>true,'account_link'=>$result], 201);
        exit;
    }
    if ($method === 'GET') {
        $user = tl_stage886_current_principal();
        tl_security_json_response([
            'ok'=>true,
            'authenticated'=>$user !== null,
            'user'=>$user,
            'integration'=>[
                'configured'=>tl_stage886_enabled(),
                'schema_ready'=>tl_stage886_schema_ready(),
                'assertion_version'=>'v1',
            ],
        ]);
        exit;
    }
    throw new TlHttpException('This endpoint accepts GET or POST.', 405, 'method_not_allowed');
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
