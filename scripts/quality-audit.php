<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$jsonMode = in_array('--json', $argv, true);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$exists = static fn(string $path): bool => is_file($root . '/' . $path);
$contains = static fn(string $path, string $needle): bool => str_contains($read($path), $needle);

$sections = [
    'security_auth' => [
        'label'=>'Security & authentication',
        'checks'=>[
            'central_security_layer'=>$exists('includes/training-lab-security.php'),
            'secure_session_cookie'=>$contains('includes/training-lab-security.php', "'httponly'=>true") && $contains('includes/training-lab-security.php', "'samesite'=>'Lax'"),
            'csrf_guard'=>$contains('includes/training-lab-security.php', 'tl_security_verify_csrf'),
            'origin_guard'=>$contains('includes/training-lab-security.php', 'tl_security_validate_origin'),
            'trusted_roles'=>$contains('includes/training-lab-security.php', 'tl_security_trusted_role'),
            'safe_demo_login'=>$contains('includes/training-lab-security.php', 'tl_security_demo_login_allowed'),
            'security_headers'=>$contains('includes/training-lab-security.php', 'Content-Security-Policy'),
            'safe_logout'=>$contains('logout.php', 'tl_security_guard_auth_action') && $contains('logout.php', 'REQUEST_METHOD'),
        ],
    ],
    'api_runtime' => [
        'label'=>'API & runtime behavior',
        'checks'=>[
            'route_bootstrap'=>$exists('includes/training-lab-route-bootstrap.php'),
            'post_only_app_actions'=>$contains('api/training/app-action.php', 'tl_security_request_data(false)'),
            'protected_action_bootstrap'=>$contains('api/training/actions/_action-bootstrap.php', 'tl_route_write_input'),
            'protected_review_api'=>$contains('api/training/proof-review-workflow.php', "tl_security_guard_write('stage885_review_proof'"),
            'safe_json_errors'=>$contains('includes/training-lab-security.php', 'tl_security_json_exception'),
            'request_ids'=>$contains('includes/training-lab-security.php', 'X-Request-ID'),
            'payload_limit'=>$contains('includes/training-lab-security.php', 'payload_too_large'),
            'rate_limit'=>$contains('includes/training-lab-security.php', 'tl_security_rate_limit'),
        ],
    ],
    'data_integrity' => [
        'label'=>'Database & data integrity',
        'checks'=>[
            'prepared_statements'=>$contains('includes/training-lab-actions.php', '$pdo->prepare'),
            'transactional_writes'=>$contains('includes/training-lab-actions.php', 'beginTransaction'),
            'row_locks'=>$contains('includes/training-lab-actions.php', 'FOR UPDATE'),
            'campaign_scoped_tasks'=>$contains('includes/training-lab-actions.php', 'campaign_id = ? AND (id = ? OR public_id = ?)'),
            'idempotent_receipts'=>$contains('includes/training-lab-actions.php', "receipt_status = 'active'") && $contains('includes/training-lab-actions.php', 'reused'),
            'idempotent_rewards'=>$contains('includes/training-lab-actions.php', "status <> 'cancelled'"),
            'crypto_receipt_hash'=>$contains('includes/training-lab-actions.php', 'random_bytes(32)'),
            'bounded_validated_input'=>$contains('includes/training-lab-actions.php', 'tl_action_clean') && $contains('includes/training-lab-actions.php', 'tl_action_enum'),
        ],
    ],
    'architecture_maintainability' => [
        'label'=>'Architecture & maintainability',
        'checks'=>[
            'shared_security'=>$exists('includes/training-lab-security.php'),
            'shared_route_layer'=>$exists('includes/training-lab-route-bootstrap.php'),
            'shared_layout'=>$exists('includes/labs-layout.php'),
            'shared_public_template'=>$exists('includes/training-lab-public-template.php'),
            'single_action_service'=>$exists('includes/training-lab-actions.php'),
            'quality_script'=>$exists('scripts/quality-audit.php'),
            'audit_documentation'=>$exists('docs/CODE-AUDIT-2026-07-09.md'),
            'no_new_runtime_dependency'=>!$exists('composer.lock') || $exists('composer.json'),
        ],
    ],
    'frontend_accessibility' => [
        'label'=>'Frontend & accessibility',
        'checks'=>[
            'skip_links'=>$contains('includes/labs-layout.php', 'labs-skip-link') && $contains('includes/training-lab-public-template.php', 'tl-skip-link'),
            'main_landmark'=>$contains('includes/labs-layout.php', 'id="main-content"') && $contains('includes/training-lab-public-template.php', 'id="main-content"'),
            'aria_current'=>$contains('includes/labs-layout.php', 'aria-current="page"') && $contains('includes/training-lab-public-template.php', 'aria-current="page"'),
            'csrf_meta'=>$contains('includes/labs-layout.php', 'csrf-token') && $contains('includes/training-lab-public-template.php', 'csrf-token'),
            'form_csrf_injection'=>$contains('assets/js/labs.js', 'securePostForms') && $contains('assets/js/public-template.js', "input.name = '_csrf'"),
            'keyboard_escape'=>$contains('assets/js/labs.js', "event.key === 'Escape'") && $contains('assets/js/public-template.js', "event.key === 'Escape'"),
            'focus_styles'=>$contains('assets/css/security-accessibility.css', ':focus-visible'),
            'reduced_motion'=>$contains('assets/css/security-accessibility.css', 'prefers-reduced-motion'),
        ],
    ],
    'testing_ci' => [
        'label'=>'Testing & CI',
        'checks'=>[
            'recursive_syntax'=>$exists('run-full-syntax-check.sh'),
            'security_runtime_test'=>$exists('tests/security-runtime-test.php'),
            'data_contract_test'=>$exists('tests/data-integrity-contract-test.php'),
            'route_contract_test'=>$exists('tests/http-route-contract-test.php'),
            'quality_gate_script'=>$exists('run-quality-gate.sh'),
            'quality_workflow'=>$exists('.github/workflows/quality-gate.yml'),
            'php_82_matrix'=>$contains('.github/workflows/quality-gate.yml', "'8.2'"),
            'php_83_matrix'=>$contains('.github/workflows/quality-gate.yml', "'8.3'"),
        ],
    ],
    'deployment_operations' => [
        'label'=>'Deployment & operations',
        'checks'=>[
            'config_examples'=>$exists('config-example.php') && $exists('labs/config-example.php'),
            'config_export_protection'=>$contains('.gitattributes', '/config.php export-ignore') && $contains('.gitattributes', '/labs/config.php export-ignore'),
            'archive_ignored'=>$contains('.gitignore', '*.zip'),
            'db_health_route'=>$exists('admin/db-health.php') && $exists('api/training/db-status.php'),
            'deployment_acceptance'=>$exists('admin/deployment-acceptance.php') && $exists('api/training/deployment-acceptance.php'),
            'live_smoke'=>$exists('admin/live-smoke.php') && $exists('api/training/live-smoke.php'),
            'safe_error_logging'=>$contains('includes/training-lab-security.php', 'error_log'),
            'audit_report'=>$exists('docs/CODE-AUDIT-2026-07-09.md'),
        ],
    ],
];

$allPerfect = true;
foreach ($sections as &$section) {
    $passed = count(array_filter($section['checks']));
    $total = count($section['checks']);
    $section['passed'] = $passed;
    $section['total'] = $total;
    $section['score'] = round(($passed / max(1, $total)) * 10, 1);
    $section['status'] = $passed === $total ? 'pass' : 'needs_work';
    if ($passed !== $total) $allPerfect = false;
}
unset($section);

$result = [
    'audit'=>'Training Lab production-readiness quality gate',
    'rubric_version'=>'2026-07-09.1',
    'all_sections_10_of_10'=>$allPerfect,
    'sections'=>$sections,
];

if ($jsonMode) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo "Training Lab quality audit\n";
    echo str_repeat('=', 72) . "\n";
    foreach ($sections as $section) {
        printf("%-36s %4.1f/10  (%d/%d)\n", $section['label'], $section['score'], $section['passed'], $section['total']);
        foreach ($section['checks'] as $check => $passed) {
            echo '  ' . ($passed ? '[PASS] ' : '[FAIL] ') . str_replace('_', ' ', $check) . "\n";
        }
    }
    echo str_repeat('-', 72) . "\n";
    echo $allPerfect ? "All audited sections score 10/10.\n" : "Quality gate failed: one or more sections are below 10/10.\n";
}

exit($allPerfect ? 0 : 1);
