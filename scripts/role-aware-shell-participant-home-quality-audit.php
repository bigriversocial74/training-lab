<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [];
$load = static function (string $path) use ($root, &$files): string {
    if (!array_key_exists($path, $files)) $files[$path] = is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
    return $files[$path];
};
$has = static fn(string $path, string $needle): bool => str_contains($load($path), $needle);
$lacks = static fn(string $path, string $needle): bool => !str_contains($load($path), $needle);
$exists = static fn(string $path): bool => is_file($root . '/' . $path);

$sections = [
    'Page access' => [
        $has('includes/labs-layout.php', 'tl_product_require_page_access($page)'),
        $has('includes/training-lab-product-shell.php', 'tl_product_required_role'),
        $has('includes/training-lab-product-shell.php', "'reviewer' : 'manager'"),
        $has('includes/training-lab-product-shell.php', 'tl_product_redirect'),
    ],
    'Trusted identity' => [
        $has('includes/training-lab-participant-home.php', 'tl_security_numeric_user_id($user)'),
        $has('includes/training-lab-participant-home.php', 'WHERE tp.user_id=?'),
        $has('includes/training-lab-participant-home.php', '$pdo->prepare($sql)'),
        $lacks('app/index.php', "\$_GET['user_id']"),
    ],
    'Participant home' => [
        $has('app/index.php', 'Recommended next step'),
        $has('app/index.php', 'Current campaign'),
        $has('app/index.php', 'Your next actions'),
        $has('app/index.php', 'Recent activity'),
        $lacks('app/index.php', '/api/training/'),
        $lacks('app/index.php', 'Stage '),
    ],
    'Role navigation' => [
        $has('includes/training-lab-product-shell.php', 'tl_product_app_nav'),
        $has('includes/training-lab-product-shell.php', 'tl_product_admin_nav'),
        $has('includes/training-lab-product-shell.php', "'System'"),
        $has('includes/labs-layout.php', 'tl_product_top_nav($labsUser)'),
        $lacks('includes/labs-layout.php', 'Stage 885 Review Workflow'),
    ],
    'Management home' => [
        $has('admin/index.php', "'required_role' => 'reviewer'"),
        $has('admin/index.php', 'Manage campaigns, participants, reviews, and rewards.'),
        $has('admin/index.php', 'Review participant proof and keep training moving.'),
        $has('admin/index.php', 'Proof waiting for a decision'),
        $lacks('admin/index.php', '/api/training/'),
        $lacks('admin/index.php', 'operations readiness'),
    ],
    'Legacy consolidation' => [
        $has('app/launchpad.php', 'tl_product_redirect($destination)'),
        $has('app/participant-portal.php', 'tl_product_redirect($destination)'),
        $lacks('app/launchpad.php', "\$_GET['user_id']"),
        $lacks('app/participant-portal.php', "\$_GET['user_id']"),
    ],
    'Responsive accessibility' => [
        $exists('assets/css/product-shell.css'),
        $has('assets/css/product-shell.css', '@media(max-width:760px)'),
        $has('assets/css/product-shell.css', '@media(max-width:460px)'),
        $has('assets/css/product-shell.css', '@media(prefers-reduced-motion:reduce)'),
        $has('app/index.php', 'aria-label="Training summary"'),
        $has('app/index.php', 'role="status"'),
    ],
    'Maintainability' => [
        $exists('includes/training-lab-product-shell.php'),
        $exists('includes/training-lab-participant-home.php'),
        $has('includes/training-lab-participant-home.php', 'tl_product_task_status'),
        $has('includes/training-lab-participant-home.php', 'tl_product_recent_activity'),
        $has('assets/css/product-shell.css', '.labs-product-card'),
        $has('assets/css/product-shell.css', '.labs-manager-row'),
    ],
    'Automated acceptance' => [
        $exists('tests/role-aware-shell-participant-home-contract-test.php'),
        $has('run-quality-gate.sh', 'role-aware-shell-participant-home-contract-test.php'),
        $has('run-quality-gate.sh', 'role-aware-shell-participant-home-quality-audit.php'),
        $has('.github/workflows/quality-gate.yml', 'Role-aware shell and participant home contract'),
        $has('.github/workflows/quality-gate.yml', 'Role-aware shell and participant home scored audit'),
    ],
    'Boundaries and documentation' => [
        $exists('docs/ROLE-AWARE-SHELL-PARTICIPANT-HOME-V1.md'),
        $has('docs/ROLE-AWARE-SHELL-PARTICIPANT-HOME-V1.md', 'No SQL is required.'),
        $has('docs/ROLE-AWARE-SHELL-PARTICIPANT-HOME-V1.md', 'does not create a second role or permission authority'),
        $has('docs/ROLE-AWARE-SHELL-PARTICIPANT-HOME-V1.md', 'No database rollback'),
        $lacks('includes/training-lab-participant-home.php', 'INSERT INTO'),
        $lacks('includes/training-lab-participant-home.php', 'UPDATE '),
    ],
];

$failed = false;
echo "Role-Aware App Shell + Participant Home quality audit\n";
foreach ($sections as $name => $checks) {
    $passed = count(array_filter($checks));
    $total = count($checks);
    $score = $total > 0 ? round(($passed / $total) * 10, 1) : 0;
    $display = number_format($score, $score === 10.0 ? 0 : 1);
    echo sprintf("%-30s %s/10 (%d/%d checks)\n", $name, $display, $passed, $total);
    if ($passed !== $total) $failed = true;
}

if ($failed) {
    fwrite(STDERR, "Role-aware shell section has not reached 10/10 in every category.\n");
    exit(1);
}

echo "Every Role-Aware App Shell + Participant Home section scored 10/10.\n";
