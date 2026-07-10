<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage896-pilot-bootstrap.php';

$result = null;
$error = '';
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        $input = tl_security_request_data(false);
        $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['training_action'] ?? ''));
        if (!in_array($action, ['stage896_run_pilot','stage896_verify_active_pilot'], true)) {
            throw new TlHttpException('Unsupported Stage 896 action.', 422, 'stage896_action_unsupported');
        }
        $user = tl_security_guard_write($action, $input);
        $input = tl_security_apply_actor($input, $user);
        $result = $action === 'stage896_run_pilot'
            ? tl_stage896_run_pilot($input)
            : tl_stage896_verify_active_pilot($input);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} else {
    $user = tl_auth_current_user();
    if (!tl_security_developer_key_valid() && !tl_auth_role_allowed($user, 'manager')) {
        throw new RuntimeException('A trusted manager or administrator account is required.');
    }
}

labs_page_start(['title'=>'Limited Reward Pilot | Training Lab','section'=>'admin','active'=>'admin-reward-pilot']);
if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-reward-pilot');
tl_stage896_render_admin_page($result, $error);
labs_page_end(['section'=>'admin']);
