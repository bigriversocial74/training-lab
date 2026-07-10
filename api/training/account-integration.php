<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-stage886-account-integration.php';

try {
    $input = tl_security_request_data(false);
    $actor = tl_security_guard_write('manage_account_link', $input);
    $result = tl_stage886_update_link_status($input, $actor);
    tl_security_json_response(['ok'=>true,'account_link'=>$result]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
