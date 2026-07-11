<?php
declare(strict_types=1);

$root = dirname(__DIR__);
putenv('TL_STAGE896_LIMITED_PILOT_ENABLED=false');
putenv('TL_MICROGIFTER_PILOT_ISSUE_ENABLED=false');
putenv('TL_REWARD_HANDOFF_WORKER_ENABLED=false');
require_once $root . '/includes/training-lab-stage896-pilot-bootstrap.php';

$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$exists = static fn(string $path): bool => is_file($root . '/' . $path);
$failures = [];
$check = static function (bool $condition, string $label) use (&$failures): void {
    if (!$condition) $failures[] = $label;
};

$core = $read('includes/training-lab-stage896-limited-reward-pilot.php');
$client = $read('includes/training-lab-stage896-signed-pilot-issue-client.php');
$bootstrap = $read('includes/training-lab-stage896-pilot-bootstrap.php');
$admin = $read('admin/reward-pilot.php');
$api = $read('api/training/reward-pilot.php');
$advancedOperations = $read('admin/reward-operations.php');
$config = $read('config-example.php');
$labsConfig = $read('labs/config-example.php');
$gate = $read('run-quality-gate.sh');
$workflow = $read('.github/workflows/quality-gate.yml');

$issueConfig = tl_stage896_issue_config();
$check(empty($issueConfig['enabled']) && empty($issueConfig['ready']), 'signed issue client is disabled by default');
$check(!$exists('database/stage896_limited_reward_pilot_v1.sql'), 'Stage 896 requires no SQL migration');

$check($exists('includes/training-lab-stage896-limited-reward-pilot.php') && $exists('includes/training-lab-stage896-signed-pilot-issue-client.php') && $exists('includes/training-lab-stage896-pilot-bootstrap.php'), 'isolated pilot services exist');
$check(str_contains($core, 'GET_LOCK(?, 0)') && str_contains($core, 'RELEASE_LOCK(?)'), 'global advisory lock prevents overlap');
$check(str_contains($core, "event_type='stage895_signed_integration_acceptance'") && str_contains($core, "'stage895_passed'") && str_contains($core, "'stage895_fresh'"), 'fresh successful Stage 895 evidence is required');
$check(str_contains($core, "'scheduled_worker_disabled'") && str_contains($core, 'tl_stage896_worker_disabled'), 'scheduled worker must remain disabled');
$check(str_contains($core, "'ISSUE ONE PILOT'") && str_contains($core, 'confirm_microgifter_user_id') && str_contains($core, 'stage896_recipient_mismatch'), 'operator phrase and recipient re-entry are enforced');
$check(str_contains($core, 'stage896_max_value_cents') && str_contains($core, 'stage896_value_limit_exceeded') && str_contains($core, 'stage896_currency_not_allowed'), 'value ceiling and USD boundary are enforced');
$check(str_contains($core, 'tl_stage896_active_pilots') && str_contains($core, 'stage896_active_pilot_exists') && str_contains($core, 'multiple_active_pilots'), 'one globally active pilot is enforced');
$check(str_contains($core, 'tl_stage893_process_handoff_production_guarded') && !str_contains($core, 'tl_stage893_process_guarded_batch('), 'pilot uses the existing single-handoff processor and no batch processor');
$check(str_contains($core, 'tl_stage896_finalize_verification') && str_contains($core, 'tl_stage893_lookup_external') && str_contains($core, 'tl_stage893_reconcile_handoff_guarded'), 'immediate signed read-back and repair are required');
$check(str_contains($core, "return ['verified','closed_absent','cancelled_before_processing']") && str_contains($core, "'verification_pending'"), 'only evidence-backed terminal states release the next pilot');
$check(str_contains($core, 'raw_identity_reference_and_adapter_payload_excluded') && str_contains($core, 'microgifter_user_fingerprint'), 'pilot event evidence is sanitized');

$check(str_contains($client, 'training-lab-reward-issue-v1\n') && str_contains($client, "hash_hmac('sha256'") && str_contains($client, 'random_bytes(24)'), 'signed issue client uses versioned HMAC and nonce');
$check(str_contains($client, 'CURLOPT_SSL_VERIFYPEER=>true') && str_contains($client, 'CURLOPT_SSL_VERIFYHOST=>2') && str_contains($client, 'CURLOPT_FOLLOWLOCATION=>false'), 'signed issue transport verifies TLS and blocks redirects');
$check(str_contains($client, "'training_lab_reward_issue_pilot_v1'") && str_contains($client, "'pilot_only'=>true") && str_contains($client, "'readback_required'=>true"), 'pilot-only remote contract is enforced');
$check(str_contains($client, "handoff_status'] !== 'processing'") && str_contains($client, "pilot['status'] ?? '') !== 'processing'") && str_contains($client, 'count($active) !== 1'), 'adapter can run only for the single processing pilot');
$check(str_contains($client, 'function microgifter_training_issue_reward') && str_contains($client, "delivery_status'=>'delivered'"), 'existing Stage 890 adapter hook is registered conditionally');
$check(str_contains($bootstrap, "'signed_pilot_authentication_ready'") && str_contains($bootstrap, "'authentication_present'") && str_contains($bootstrap, 'training-lab-stage896-signed-pilot-issue-client.php'), 'pilot bootstrap isolates signed authentication from global routes');

$check(str_contains($admin, 'training-lab-stage896-pilot-bootstrap.php') && str_contains($admin, 'tl_security_guard_write'), 'admin pilot page is protected and uses isolated bootstrap');
$check(str_contains($api, 'training-lab-stage896-pilot-bootstrap.php') && str_contains($api, 'tl_auth_role_allowed') && str_contains($api, 'tl_security_guard_write'), 'pilot API GET and POST are protected');
$check(str_contains($advancedOperations, 'tl_stage896_render_reward_bridge_panel'), 'Advanced Reward Operations exposes pilot status');
foreach ([$config, $labsConfig] as $index => $example) {
    $label = $index === 0 ? 'root config' : 'labs config';
    $check(str_contains($example, "'stage896_limited_pilot_enabled' => false") && str_contains($example, "'microgifter_pilot_issue_enabled' => false"), $label . ' keeps pilot and issue client disabled');
    $check(str_contains($example, 'microgifter_pilot_issue_secret') && str_contains($example, 'DO_NOT_COMMIT_A_REAL_SECRET'), $label . ' documents a private issue secret');
}
$check(str_contains($gate, 'stage896-limited-reward-pilot-contract-test.php'), 'local quality gate runs Stage 896 contract');
$check(str_contains($workflow, 'Stage 896 limited reward pilot contract'), 'PHP matrix runs Stage 896 contract');

if ($failures) {
    fwrite(STDERR, "Stage 896 limited reward pilot contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Stage 896 limited reward pilot contract passed.\n";
