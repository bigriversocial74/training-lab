<?php
require_once __DIR__ . '/../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../includes/training-lab-stage886-account-integration.php';
require_once __DIR__ . '/../includes/labs-layout.php';

$user = tl_auth_current_user();
if (!$user || !tl_auth_role_allowed($user, 'manager')) {
    http_response_code(403);
    labs_page_start(['title'=>'Account Integration | Training Lab','section'=>'admin','active'=>'admin-account-integration']);
    echo '<section class="labs-card labs-error-card"><h1>Manager access required</h1><p class="labs-copy">Sign in through a trusted Microgifter manager or administrator account.</p></section>';
    labs_page_end(['section'=>'admin']);
    exit;
}

$actionResult = null;
$error = null;
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        $input = tl_security_request_data(false);
        $actor = tl_security_guard_write('manage_account_link', $input);
        $actionResult = tl_stage886_update_link_status($input, $actor);
    } catch (Throwable $e) {
        [$payload] = tl_security_error_payload($e);
        $error = (string)($payload['error'] ?? 'The account link could not be updated.');
    }
}

$summary = tl_stage886_admin_summary();
labs_page_start(['title'=>'Shared Account Integration | Training Lab','section'=>'admin','active'=>'admin-account-integration']);
if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-account-integration');
tl_stage886_render_admin($summary, $actionResult, $error);
labs_page_end(['section'=>'admin']);
