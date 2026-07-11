<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-production-integration-closeout.php';

try {
    tl_security_require_method('GET');
    $user = tl_security_current_user();
    if (!$user && tl_security_developer_key_valid()) $user = ['id'=>'developer-key','numeric_user_id'=>1,'role'=>'admin','source'=>'developer_key'];
    if (!$user) throw new TlHttpException('Authentication is required.', 401, 'authentication_required');
    tl_closeout_admin($user);
    $report = tl_closeout_report((string)($_GET['campaign'] ?? ''));
    tl_security_json_response(['ok'=>true,'data'=>$report]);
} catch (Throwable $error) {
    tl_security_json_exception($error);
}
