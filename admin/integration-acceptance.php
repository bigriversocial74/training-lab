<?php
require_once __DIR__ . '/../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage895-integration-acceptance.php';

$result = null;
$error = '';
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        $raw = tl_security_request_data(false);
        $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($raw['training_action'] ?? 'stage895_run_signed_acceptance'));
        if ($action !== 'stage895_run_signed_acceptance') {
            throw new TlHttpException('Unsupported Stage 895 action.', 422, 'unsupported_stage895_action');
        }
        $user = tl_security_guard_write($action, $raw);
        $input = tl_security_apply_actor($raw, $user);
        $result = tl_stage895_run_suite($input);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

labs_page_start(['title'=>'Signed Integration Acceptance | Training Lab','section'=>'admin','active'=>'admin-integration-acceptance']);
if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-integration-acceptance');
tl_stage895_render_admin_page($result, $error);
labs_page_end(['section'=>'admin']);
