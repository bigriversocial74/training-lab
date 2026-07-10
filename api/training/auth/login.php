<?php
require_once __DIR__ . '/../../../includes/training-lab-route-bootstrap.php';

try {
    $input = tl_route_auth_input('training_login');
    $user = tl_auth_login_session($input);
    tl_auth_json([
        'ok'=>true,
        'authenticated'=>true,
        'user'=>$user,
        'message'=>'Training Lab participant session created.',
        'csrf_token'=>tl_security_csrf_token(),
    ]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
