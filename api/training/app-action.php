<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';

try {
    $raw = tl_security_request_data(false);
    $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($raw['training_action'] ?? $raw['action'] ?? ''));
    if ($action === '') throw new TlHttpException('Training action is required.', 422, 'action_required');
    $user = tl_security_guard_write($action, $raw);
    $data = tl_security_apply_actor($raw, $user);
    $result = tl_training_handle_app_action($data);
    tl_security_json_response(['ok'=>true,'data'=>$result,'flow'=>tl_app_flow_summary()]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
