<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = [
    'api/training/auth/login.php' => ['tl_route_auth_input', 'tl_auth_json'],
    'api/training/auth/logout.php' => ['tl_security_guard_auth_action', 'tl_auth_logout_session'],
    'api/training/account-bridge.php' => ['tl_security_guard_auth_action', 'tl_security_json_exception'],
    'api/training/app-action.php' => ['tl_security_request_data(false)', 'tl_security_json_exception'],
    'api/training/proof-review-workflow.php' => ['method_not_allowed', 'tl_security_json_exception'],
    'logout.php' => ['REQUEST_METHOD', 'tl_security_csrf_field'],
];
foreach ($checks as $file => $needles) {
    $content = file_get_contents($root . '/' . $file) ?: '';
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) $failures[] = $file . ' is missing route contract: ' . $needle;
    }
}

$legacyPatterns = [
    'api/training/auth/login.php' => ['$_GET'],
    'api/training/auth/logout.php' => ["tl_auth_logout_session();\n\ntl_auth_json"],
    'api/training/app-action.php' => ["throw new RuntimeException('Training app actions require POST.')"],
];
foreach ($legacyPatterns as $file => $patterns) {
    $content = file_get_contents($root . '/' . $file) ?: '';
    foreach ($patterns as $pattern) {
        if (str_contains($content, $pattern)) $failures[] = $file . ' still contains an insecure legacy route pattern.';
    }
}

if ($failures) {
    fwrite(STDERR, "HTTP route contract test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "HTTP route contract test passed.\n";
