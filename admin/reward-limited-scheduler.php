<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage899-limited-scheduled-processing.php';

$result = null;
$error = '';
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        $input = tl_security_request_data(false);
        $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['training_action'] ?? ''));
        if ($action !== 'stage899_acknowledge_suspension') {
            throw new TlHttpException('Unsupported Stage 899 action.', 422, 'stage899_action_unsupported');
        }
        $user = tl_security_guard_write($action, $input);
        $input = tl_security_apply_actor($input, $user);
        $result = tl_stage899_acknowledge_suspension($input);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} else {
    $user = tl_auth_current_user();
    if (!tl_security_developer_key_valid() && !tl_auth_role_allowed($user, 'manager')) {
        throw new RuntimeException('A trusted manager or administrator account is required.');
    }
}

labs_page_start(['title'=>'Limited Scheduler | Training Lab','section'=>'admin','active'=>'admin-reward-limited-scheduler']);
if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-reward-limited-scheduler');
tl_stage899_render_admin_page($result, $error);
labs_page_end(['section'=>'admin']);
