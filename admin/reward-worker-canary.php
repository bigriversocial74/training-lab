<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage898-worker-canary-monitoring.php';

$result = null;
$error = '';
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        $input = tl_security_request_data(false);
        $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['training_action'] ?? ''));
        if ($action !== 'stage898_acknowledge_canary_pause') {
            throw new TlHttpException('Unsupported Stage 898 action.', 422, 'stage898_action_unsupported');
        }
        $user = tl_security_guard_write($action, $input);
        $input = tl_security_apply_actor($input, $user);
        $result = tl_stage898_acknowledge_pause($input);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} else {
    $user = tl_auth_current_user();
    if (!tl_security_developer_key_valid() && !tl_auth_role_allowed($user, 'manager')) {
        throw new RuntimeException('A trusted manager or administrator account is required.');
    }
}

labs_page_start(['title'=>'Worker Canary | Training Lab','section'=>'admin','active'=>'admin-reward-worker-canary']);
if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-reward-worker-canary');
tl_stage898_render_admin_page($result, $error);
labs_page_end(['section'=>'admin']);
