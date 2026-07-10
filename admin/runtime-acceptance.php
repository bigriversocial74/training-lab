<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-production-runtime-acceptance.php';

$user = function_exists('tl_auth_current_user') ? tl_auth_current_user() : null;
$authorized = tl_security_developer_key_valid() || (function_exists('tl_auth_role_allowed') && tl_auth_role_allowed($user, 'manager'));
$runProbes = (string)($_GET['probe'] ?? '') === '1';

labs_page_start(['title' => 'Production Runtime Acceptance | Training Lab', 'section' => 'admin', 'active' => 'admin-runtime-acceptance']);
if (!$authorized) {
    http_response_code(403);
    echo '<section class="labs-page-title"><div><span class="labs-eyebrow">Protected diagnostics</span><h1>Production Runtime Acceptance v1</h1><p class="labs-copy">A trusted Microgifter manager or administrator session is required to view production diagnostics.</p></div></section>';
    echo '<section class="labs-card"><h2>Access required</h2><p class="labs-copy">Sign in through the connected Microgifter account bridge with manager or administrator authority, then reopen this page.</p><a class="labs-btn labs-btn-primary" href="' . tl_runtime_acceptance_e(labs_url('/signin.php?next=/admin/runtime-acceptance.php')) . '">Open Sign In</a></section>';
} else {
    tl_runtime_acceptance_render($runProbes);
}
labs_page_end(['section' => 'admin']);
