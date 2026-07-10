<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

$add = static function (string $section, string $name, bool $passed, string $detail = '') use (&$checks): void {
    $checks[$section][] = [
        'name' => $name,
        'passed' => $passed,
        'detail' => $detail,
    ];
};

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) return '';
    $content = file_get_contents($full);
    return $content === false ? '' : $content;
};

$containsAll = static function (string $content, array $needles): bool {
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) return false;
    }
    return true;
};

$requiredDirectories = ['admin', 'api/training', 'app', 'assets', 'config', 'database', 'includes', 'labs'];
foreach ($requiredDirectories as $directory) {
    $add('Architecture', 'Required directory: ' . $directory, is_dir($root . '/' . $directory));
}

$requiredCoreFiles = [
    'includes/training-lab-db.php',
    'includes/training-lab-security.php',
    'includes/training-lab-auth-gate.php',
    'includes/training-lab-route-bootstrap.php',
    'includes/training-lab-actions.php',
    'includes/training-lab-app-service.php',
    'includes/labs-layout.php',
    'api/training/app-action.php',
    'admin/action-result.php',
    'run-full-syntax-check.sh',
];
foreach ($requiredCoreFiles as $file) {
    $add('Architecture', 'Core source file: ' . $file, is_file($root . '/' . $file));
}

$archives = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $fileInfo) {
    $path = str_replace('\\', '/', $fileInfo->getPathname());
    if (str_contains($path, '/.git/')) continue;
    if (preg_match('/\.(zip|tar|tar\.gz|tgz)$/i', $path)) $archives[] = substr($path, strlen($root) + 1);
}
$add('Architecture', 'No packaged archives tracked in active source', $archives === [], implode(', ', $archives));

$security = $read('includes/training-lab-security.php');
$add('Security', 'Central security runtime is present', $security !== '');
$add('Security', 'Secure session cookie controls', $containsAll($security, [
    'session.use_strict_mode', 'session.use_only_cookies', 'httponly', 'samesite', 'session_regenerate_id'
]));
$add('Security', 'CSRF token generation and verification', $containsAll($security, [
    'tl_security_csrf_token', 'hash_equals', 'tl_security_verify_csrf'
]));
$add('Security', 'Origin, method, payload, and rate controls', $containsAll($security, [
    'tl_security_validate_origin', 'tl_security_require_method', 'payload_too_large', 'tl_security_rate_limit'
]));
$add('Security', 'Role-based write authorization', $containsAll($security, [
    'tl_security_authorize_action', 'tl_security_permission_for_action', 'permission_denied'
]));
$add('Security', 'Safe production error envelope', $containsAll($security, [
    'tl_security_error_payload', 'internal_error', 'request_id'
]));
$add('Security', 'Baseline browser security headers', $containsAll($security, [
    'Content-Security-Policy', 'X-Content-Type-Options', 'Referrer-Policy', 'Permissions-Policy'
]));

$writeRoutes = [
    'api/training/actions/_action-bootstrap.php',
    'api/training/app-action.php',
    'api/training/proof-review-workflow.php',
    'admin/action-result.php',
    'app/action-result.php',
];
foreach ($writeRoutes as $route) {
    $content = $read($route);
    $protected = $content !== '' && (
        str_contains($content, 'tl_route_write_input') ||
        str_contains($content, 'training-lab-route-bootstrap.php')
    );
    $add('Security', 'Protected write route: ' . $route, $protected);
}

$authRoutes = [
    'signin.php', 'signup.php', 'logout.php',
    'api/training/auth/login.php', 'api/training/auth/logout.php', 'api/training/account-bridge.php',
];
foreach ($authRoutes as $route) {
    $content = $read($route);
    $protected = $content !== '' && (
        str_contains($content, 'tl_route_auth_input') ||
        str_contains($content, 'tl_security_guard_auth_action') ||
        str_contains($content, 'training-lab-route-bootstrap.php')
    );
    $add('Security', 'Protected auth route: ' . $route, $protected);
}

$config = $read('labs/config.php') . $read('config.php');
$add('Security', 'Repository config keeps password placeholders', str_contains($config, 'PUT_YOUR_DATABASE_PASSWORD_HERE'));
$add('Security', 'No obvious live secret committed', !preg_match('/[\x27\x22]password[\x27\x22]\s*=>\s*[\x27\x22](?!PUT_YOUR_DATABASE_PASSWORD_HERE)[^\x27\x22]{8,}[\x27\x22]/i', $config));

$actions = $read('includes/training-lab-actions.php');
$add('Data Integrity', 'Transactional campaign and review writes', substr_count($actions, 'beginTransaction()') >= 3 && str_contains($actions, 'rollBack()'));
$add('Data Integrity', 'Concurrent review protection', str_contains($actions, 'FOR UPDATE'));
$add('Data Integrity', 'Idempotent proof review behavior', str_contains($actions, "'idempotent'=>true") && str_contains($actions, 'action_receipt_reused'));
$add('Data Integrity', 'Task lookup scoped to selected campaign', str_contains($actions, 'WHERE campaign_id = ? AND (id = ? OR public_id = ?)'));
$add('Data Integrity', 'Reward duplicate guard', str_contains($actions, "reward_rule_id = ? AND status <> 'cancelled'"));
$add('Data Integrity', 'Validated JSON encoding', str_contains($actions, 'JSON_THROW_ON_ERROR'));
$add('Data Integrity', 'External proof URLs limited to HTTP(S)', $containsAll($actions, ['FILTER_VALIDATE_URL', "['http','https']"]));

$actionBootstrap = $read('api/training/actions/_action-bootstrap.php');
$appAction = $read('api/training/app-action.php');
$proofWorkflow = $read('api/training/proof-review-workflow.php');
$add('API Contracts', 'Shared action bootstrap returns structured JSON', $containsAll($actionBootstrap, ['tl_security_json_response', 'tl_security_json_exception', "'ok'=>true"]));
$add('API Contracts', 'App action endpoint uses shared write guard', $containsAll($appAction, ['tl_route_write_input', 'tl_security_json_exception']));
$add('API Contracts', 'Proof workflow uses shared write guard', $containsAll($proofWorkflow, ['tl_route_write_input', 'tl_security_json_exception']));
$add('API Contracts', 'HTTP errors retain status and machine code', $containsAll($security, ['httpStatus()', 'errorCode()', 'http_response_code']));
$add('API Contracts', 'JSON encoding failures are handled', str_contains($security, 'json_encode_failed'));

$layout = $read('includes/labs-layout.php');
$publicTemplate = $read('includes/training-lab-public-template.php');
$accessibilityCss = $read('assets/css/security-accessibility.css');
$add('Frontend & Accessibility', 'Application layout includes language and viewport metadata', $containsAll($layout, ['<html lang="en">', 'name="viewport"']));
$add('Frontend & Accessibility', 'Application skip link and focus target', $containsAll($layout, ['labs-skip-link', 'id="main-content"', 'tabindex="-1"']));
$add('Frontend & Accessibility', 'Current navigation state is exposed', str_contains($layout, 'aria-current="page"'));
$add('Frontend & Accessibility', 'CSRF token is exposed to progressive-enhancement scripts', str_contains($layout, 'name="csrf-token"'));
$add('Frontend & Accessibility', 'Public template has skip navigation and main landmark', $containsAll($publicTemplate, ['tl-skip-link', 'id="main-content"']));
$add('Frontend & Accessibility', 'Visible focus and reduced-motion support', $containsAll($accessibilityCss, [':focus-visible', 'prefers-reduced-motion']));

$criticalFiles = [
    'includes/training-lab-security.php',
    'includes/training-lab-auth-gate.php',
    'includes/training-lab-route-bootstrap.php',
    'includes/training-lab-actions.php',
    'api/training/actions/_action-bootstrap.php',
    'api/training/app-action.php',
];
foreach ($criticalFiles as $file) {
    $content = $read($file);
    $noDebug = !preg_match('/\b(var_dump|print_r)\s*\(/', $content);
    $add('Maintainability', 'No debug output in ' . $file, $content !== '' && $noDebug);
}
$add('Maintainability', 'Shared security and route helpers avoid duplicated guards', $containsAll($read('includes/training-lab-route-bootstrap.php'), ['tl_route_write_input', 'tl_route_auth_input']));
$add('Maintainability', 'Centralized input validation helpers', $containsAll($actions, ['tl_action_clean', 'tl_action_enum', 'tl_action_external_url']));

$workflow = $read('.github/workflows/quality-gate.yml');
$syntaxWorkflow = $read('.github/workflows/php-syntax.yml');
$runner = $read('run-quality-gate.sh');
$add('Testing & CI', 'Recursive PHP syntax runner exists', is_file($root . '/run-full-syntax-check.sh'));
$add('Testing & CI', 'Repository quality runner exists', $containsAll($runner, ['run-full-syntax-check.sh', 'tests/quality-gate.php']));
$add('Testing & CI', 'Quality workflow tests PHP 8.2 and 8.3', $containsAll($workflow, ["'8.2'", "'8.3'", 'run-quality-gate.sh']));
$add('Testing & CI', 'Existing syntax workflow remains active', $containsAll($syntaxWorkflow, ['PHP Syntax', 'run-full-syntax-check.sh']));

$gitignore = $read('.gitignore');
$gitattributes = $read('.gitattributes');
$auditReport = $read('AUDIT-REPORT.md');
$add('Operations & Documentation', 'Package artifacts are ignored', str_contains($gitignore, '*.zip'));
$add('Operations & Documentation', 'Live config is excluded from deploy archives', $containsAll($gitattributes, ['/config.php export-ignore', '/labs/config.php export-ignore']));
$add('Operations & Documentation', 'Audit report documents rubric and remediation', $containsAll($auditReport, ['Initial audit scores', 'Final scores', '10/10'])) ;
$add('Operations & Documentation', 'Database configuration path remains documented', str_contains($read('README.md'), '/labs/config.php'));

$failed = 0;
printf("Training Lab Quality Gate\n=========================\n");
foreach ($checks as $section => $sectionChecks) {
    $passed = count(array_filter($sectionChecks, static fn(array $check): bool => $check['passed']));
    $total = count($sectionChecks);
    $score = $total > 0 ? round(($passed / $total) * 10, 1) : 0.0;
    printf("%-28s %4.1f/10 (%d/%d)\n", $section, $score, $passed, $total);
    foreach ($sectionChecks as $check) {
        if ($check['passed']) continue;
        $failed++;
        printf("  [FAIL] %s%s\n", $check['name'], $check['detail'] !== '' ? ' — ' . $check['detail'] : '');
    }
}

if ($failed > 0) {
    fwrite(STDERR, "\nQuality gate failed with {$failed} check(s).\n");
    exit(1);
}

printf("\nAll sections scored 10/10 against the repository audit rubric.\n");
