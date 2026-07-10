<?php
declare(strict_types=1);

$root = dirname(__DIR__);
putenv('TL_STAGE895_LIVE_ACCEPTANCE_ENABLED=false');
putenv('TL_MICROGIFTER_REWARD_LOOKUP_ENABLED=false');
putenv('TL_REWARD_RECONCILIATION_ENABLED=false');
putenv('TL_REWARD_HANDOFF_PROCESSING_ENABLED=false');
putenv('TL_REWARD_HANDOFF_WORKER_ENABLED=false');
require_once $root . '/includes/training-lab-stage895-integration-acceptance.php';

$failures = [];
$check = static function (bool $condition, string $label) use (&$failures): void {
    if (!$condition) $failures[] = $label;
};
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';

$service = $read('includes/training-lab-stage895-integration-acceptance.php');
$admin = $read('admin/integration-acceptance.php');
$api = $read('api/training/integration-acceptance.php');
$bridge = $read('admin/reward-bridge.php');
$config = $read('config-example.php');
$labsConfig = $read('labs/config-example.php');
$gate = $read('run-quality-gate.sh');
$workflow = $read('.github/workflows/quality-gate.yml');

$readiness = tl_stage895_readiness();
$check(empty($readiness['ready_to_run']), 'live acceptance is disabled by default');
$check(!empty($readiness['processing_gates']['all_closed']), 'production gates are closed in the contract environment');
$check(($readiness['safe_boundaries']['read_only_microgifter_lookup_only'] ?? false) === true, 'read-only boundary is declared');

$probe = tl_stage895_probe('contract', 'Contract probe', 'passed', [
    'http_status'=>200,
    'error_code'=>'',
    'request_id'=>'request-123',
    'duration_ms'=>12,
    'data'=>['found'=>false,'delivery_status'=>'not_found'],
], 'Safe detail');
$check($probe['status'] === 'passed' && $probe['http_status'] === 200, 'probe evidence is normalized');
$check(!array_key_exists('secret', $probe) && !array_key_exists('signature', $probe) && !array_key_exists('nonce', $probe), 'probe evidence excludes signing material');

$check(str_contains($service, 'TL_STAGE895_LIVE_ACCEPTANCE_ENABLED'), 'dedicated acceptance feature flag exists');
$check(str_contains($service, "'all_closed'=>") && str_contains($service, 'stage895_not_ready'), 'suite requires all production gates closed');
$check(str_contains($service, 'CURLOPT_SSL_VERIFYPEER=>true') && str_contains($service, 'CURLOPT_SSL_VERIFYHOST=>2'), 'acceptance transport verifies TLS');
$check(str_contains($service, 'CURLOPT_FOLLOWLOCATION=>false') && str_contains($service, 'CURLOPT_MAXREDIRS=>0'), 'acceptance transport rejects redirects');
$check(str_contains($service, "['tamper_signature'=>true]") && str_contains($service, "'signature_invalid'"), 'tampered-signature probe is enforced');
$check(str_contains($service, 'time() - 1200') && str_contains($service, "'timestamp_expired'"), 'expired-timestamp probe is enforced');
$check(substr_count($service, "'nonce'=>\$replayNonce") >= 2 && str_contains($service, "'request_replayed'"), 'identical nonce replay is tested');
$check(str_contains($service, "'known_reward_found'") && str_contains($service, "'wrong_user'"), 'found and wrong-user probes are required');
$check(str_contains($service, "'valid_not_found'") && str_contains($service, 'stage895-not-found-'), 'valid signed not-found probe uses a synthetic reference');
$check(str_contains($service, 'stage895_signed_integration_acceptance') && str_contains($service, 'secrets_signatures_nonces_and_raw_payloads_excluded'), 'sanitized acceptance evidence is logged');
$check(!str_contains($service, 'tl_stage890_call_adapter(') && !str_contains($service, 'tl_mg_stage160_retry_microgifter_issue('), 'acceptance service has no reward mutation adapter call');

$check(str_contains($admin, 'tl_security_guard_write') && str_contains($admin, 'stage895_run_signed_acceptance'), 'admin acceptance POST is protected');
$check(str_contains($service, 'tl_security_csrf_field'), 'admin form includes CSRF protection');
$check(str_contains($api, 'tl_auth_role_allowed') && str_contains($api, 'tl_security_guard_write'), 'acceptance API GET and POST are protected');
$check(str_contains($bridge, 'tl_stage895_render_reward_bridge_panel'), 'Reward Bridge links the acceptance center');

foreach ([$config, $labsConfig] as $index => $example) {
    $label = $index === 0 ? 'root config' : 'labs config';
    $check(str_contains($example, "'stage895_live_acceptance_enabled' => false"), $label . ' keeps live acceptance disabled');
}
$check(str_contains($gate, 'stage895-signed-integration-acceptance-contract-test.php'), 'local quality gate runs Stage 895 contract');
$check(str_contains($workflow, 'Stage 895 signed integration acceptance contract'), 'PHP matrix runs Stage 895 contract');
$check(!is_file($root . '/database/stage895_signed_integration_acceptance.sql'), 'Stage 895 requires no SQL migration');

if ($failures) {
    fwrite(STDERR, "Stage 895 signed integration acceptance contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Stage 895 signed integration acceptance contract passed.\n";
