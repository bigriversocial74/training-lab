<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $path) use ($root, &$failures): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        $failures[] = $path . ' is missing.';
        return '';
    }
    return file_get_contents($full) ?: '';
};
$requires = static function (string $content, string $needle, string $message) use (&$failures): void {
    if (!str_contains($content, $needle)) $failures[] = $message;
};
$forbids = static function (string $content, string $needle, string $message) use (&$failures): void {
    if (str_contains($content, $needle)) $failures[] = $message;
};

$shell = $read('includes/training-lab-product-shell.php');
$layout = $read('includes/labs-layout.php');
$homeService = $read('includes/training-lab-participant-home.php');
$participantHome = $read('app/index.php');
$adminHome = $read('admin/index.php');
$launchpad = $read('app/launchpad.php');
$portal = $read('app/participant-portal.php');
$css = $read('assets/css/product-shell.css');
$runner = $read('run-quality-gate.sh');
$workflow = $read('.github/workflows/quality-gate.yml');
$documentation = $read('docs/ROLE-AWARE-SHELL-PARTICIPANT-HOME-V1.md');

$requires($layout, 'tl_product_require_page_access($page)', 'The shared layout must enforce page access before rendering.');
$requires($layout, "labs_asset('css/product-shell.css')", 'The shared layout must load the product shell stylesheet.');
$requires($layout, 'tl_product_top_nav($labsUser)', 'The top navigation must be role-aware.');
$forbids($layout, 'Stage 885 Review Workflow', 'Stage labels must not appear in the product navigation.');
$forbids($layout, "'Backend'", 'Participant navigation must not expose the old Backend label.');

$requires($shell, "return 'participant';", 'App pages must default to participant access.');
$requires($shell, 'return in_array($script, $reviewerPages, true) ? \'reviewer\' : \'manager\';', 'Admin pages must separate reviewer and manager access.');
$requires($shell, "'manager' => 3", 'Manager role ranking is required.');
$requires($shell, "'admin' => 4", 'Administrator role ranking is required.');
$requires($shell, 'tl_product_redirect', 'Unauthorized page access must use a safe redirect.');
$requires($shell, "'System'", 'Administrator-only system navigation is required.');

$requires($homeService, 'tl_security_numeric_user_id($user)', 'Participant identity must come from the trusted signed-in user.');
$requires($homeService, 'WHERE tp.user_id=?', 'Participant campaign reads must be scoped to the signed-in user.');
$requires($homeService, '$pdo->prepare($sql)', 'Participant campaign reads must use prepared statements.');
$requires($homeService, 'tl_product_task_status', 'Participant task status normalization is required.');
$requires($homeService, 'tl_product_recent_activity', 'Participant recent activity must be derived from real records.');

$requires($homeService, 'tl_product_manager_home_scope', 'Manager dashboard data must use a dedicated ownership-scoped service.');
$requires($homeService, 'WHERE c.owner_user_id=?', 'Manager campaign reads must be scoped to the trusted owner user ID.');
$requires($homeService, 'WHERE owner_user_id=?', 'Manager campaign list must be scoped by owner_user_id.');
$requires($homeService, '$countStmt = $pdo->prepare($countSql)', 'Manager aggregate counts must use a prepared query.');
$requires($homeService, '$role === \'manager\'', 'Manager-only ownership scoping must be explicit.');
$requires($homeService, "'scope' => 'owned_campaigns'", 'Manager results must declare owned-campaign scope.');
$requires($documentation, 'Merchant managers see only campaigns where `training_campaigns.owner_user_id` matches their trusted account', 'Documentation must define merchant tenant scoping.');

$requires($participantHome, 'tl_product_participant_home($user ?? []', 'Participant home must use the signed-in participant view model.');
$requires($participantHome, 'Recommended next step', 'Participant home must surface one clear next action.');
$requires($participantHome, 'Your next actions', 'Participant home must show the task path.');
$requires($participantHome, 'Recent activity', 'Participant home must show recent activity.');
$forbids($participantHome, "\$_GET['user_id']", 'Participant home must not accept a user_id query parameter.');
$forbids($participantHome, '/api/training/', 'Participant home must not expose API links.');
$forbids($participantHome, 'Stage ', 'Participant home must not expose stage terminology.');
$forbids($participantHome, 'Build ', 'Participant home must not expose build terminology.');

$requires($adminHome, "'required_role' => 'reviewer'", 'Management home must be available to trusted reviewers.');
$requires($adminHome, 'Manage campaigns, participants, reviews, and rewards.', 'Manager home must present product operations.');
$requires($adminHome, 'Review participant proof and keep training moving.', 'Reviewer home must present a focused review experience.');
$forbids($adminHome, '/api/training/', 'Management home must not expose API links.');
$forbids($adminHome, 'operations readiness', 'Management home must not lead with internal readiness scoring.');
$forbids($adminHome, 'Stage ', 'Management home must not expose stage terminology.');

foreach (['app/launchpad.php' => $launchpad, 'app/participant-portal.php' => $portal] as $path => $content) {
    $requires($content, 'tl_product_require_page_access($page)', $path . ' must preserve participant access enforcement.');
    $requires($content, 'tl_product_redirect($destination)', $path . ' must redirect to the consolidated participant home.');
    $forbids($content, "\$_GET['user_id']", $path . ' must ignore legacy user_id switching.');
}

$requires($css, '@media(max-width:760px)', 'The product shell must include a mobile breakpoint.');
$requires($css, '@media(max-width:460px)', 'The product shell must include a narrow-phone breakpoint.');
$requires($css, '@media(prefers-reduced-motion:reduce)', 'The product shell must respect reduced-motion preferences.');
$requires($css, '.labs-product-task', 'The product shell must include reusable task components.');
$requires($css, '.labs-manager-grid', 'The product shell must include reusable management components.');

$requires($runner, 'role-aware-shell-participant-home-contract-test.php', 'The local quality gate must run this contract test.');
$requires($runner, 'role-aware-shell-participant-home-quality-audit.php', 'The local quality gate must run this scored audit.');
$requires($workflow, 'Role-aware shell and participant home contract', 'GitHub Actions must run this contract test.');
$requires($workflow, 'Role-aware shell and participant home scored audit', 'GitHub Actions must run this scored audit.');

require_once $root . '/includes/training-lab-product-shell.php';
if (!tl_product_role_allows('admin', 'manager')) $failures[] = 'Admin must inherit manager access.';
if (!tl_product_role_allows('manager', 'reviewer')) $failures[] = 'Manager must inherit reviewer access.';
if (tl_product_role_allows('participant', 'reviewer')) $failures[] = 'Participant must not inherit reviewer access.';
if (tl_product_role_label('manager') !== 'Merchant Manager') $failures[] = 'Manager role label must be product-facing.';

if ($failures) {
    fwrite(STDERR, "Role-aware shell and participant home contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Role-aware shell and participant home contract passed.\n";
