<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$exists = static fn(string $path): bool => is_file($root . '/' . $path);
$core = $read('includes/training-lab-stage896-limited-reward-pilot.php');
$client = $read('includes/training-lab-stage896-signed-pilot-issue-client.php');
$bootstrap = $read('includes/training-lab-stage896-pilot-bootstrap.php');

$checks = [
    'single_global_pilot'=>$contains = str_contains($core, 'GET_LOCK(?, 0)') && str_contains($core, 'stage896_active_pilot_exists'),
    'fresh_acceptance_required'=>str_contains($core, "event_type='stage895_signed_integration_acceptance'") && str_contains($core, "'stage895_fresh'"),
    'operator_and_recipient_confirmation'=>str_contains($core, 'ISSUE ONE PILOT') && str_contains($core, 'confirm_microgifter_user_id'),
    'bounded_value_and_currency'=>str_contains($core, 'stage896_value_limit_exceeded') && str_contains($core, 'stage896_currency_not_allowed'),
    'canonical_single_handoff_processor'=>str_contains($core, 'tl_stage893_process_handoff_production_guarded') && !str_contains($core, 'tl_stage893_process_guarded_batch('),
    'immediate_readback_and_repair'=>str_contains($core, 'tl_stage893_lookup_external') && str_contains($core, 'tl_stage893_reconcile_handoff_guarded'),
    'signed_tls_issue_client'=>str_contains($client, "hash_hmac('sha256'") && str_contains($client, 'CURLOPT_SSL_VERIFYPEER=>true') && str_contains($client, 'CURLOPT_FOLLOWLOCATION=>false'),
    'isolated_pilot_adapter'=>str_contains($bootstrap, 'signed_stage896_pilot_adapter') && str_contains($client, 'function microgifter_training_issue_reward'),
    'protected_operator_surfaces'=>$exists('admin/reward-pilot.php') && $exists('api/training/reward-pilot.php') && str_contains($read('api/training/reward-pilot.php'), 'tl_security_guard_write'),
    'configuration_contract_and_no_sql'=>str_contains($read('config-example.php'), "'stage896_limited_pilot_enabled' => false") && str_contains($read('labs/config-example.php'), "'microgifter_pilot_issue_enabled' => false") && !$exists('database/stage896_limited_reward_pilot_v1.sql'),
];

$passed = count(array_filter($checks));
$total = count($checks);
$score = round(($passed / max(1, $total)) * 10, 1);
echo "Stage 896 quality audit\n";
echo str_repeat('=', 64) . "\n";
foreach ($checks as $name => $ok) echo '  ' . ($ok ? '[PASS] ' : '[FAIL] ') . str_replace('_', ' ', $name) . "\n";
echo str_repeat('-', 64) . "\n";
printf("Score: %.1f/10 (%d/%d)\n", $score, $passed, $total);
exit($passed === $total ? 0 : 1);
