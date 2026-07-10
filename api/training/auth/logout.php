<?php
require_once __DIR__ . '/../../../includes/training-lab-route-bootstrap.php';

try {
    $data = tl_security_request_data(false);
    tl_security_guard_auth_action('logout_training', $data);
    tl_auth_logout_session();
    tl_security_json_response(['ok'=>true,'authenticated'=>false,'message'=>'Training Lab session cleared.']);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
