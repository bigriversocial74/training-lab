<?php
declare(strict_types=1);

$root = dirname(__DIR__);
putenv('TL_MICROGIFTER_REWARD_LOOKUP_ENABLED=false');
putenv('TL_MICROGIFTER_REWARD_LOOKUP_SECRET');
require_once $root . '/includes/training-lab-stage894-signed-reward-lookup-client.php';

$failures = [];
$check = static function (bool $condition, string $label) use (&$failures): void {
    if (!$condition) $failures[] = $label;
};
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';

$client = $read('includes/training-lab-stage894-signed-reward-lookup-client.php');
$bootstrap = $read('includes/training-lab-stage894-reconciliation-bootstrap.php');
$reconciliationApi = $read('api/training/reward-delivery-reconciliation.php');
$operationsApi = $read('api/training/reward-handoff-operations.php');
$outboxApi = $read('api/training/reward-handoff-outbox.php');
$appAction = $read('api/training/app-action.php');
$proofReview = $read('api/training/proof-review-workflow.php');
$adminAction = $read('admin/action-result.php');
$rewardBridge = $read('admin/reward-bridge.php');
$worker = $read('bin/reward-handoff-worker.php');
$config = $read('config-example.php');
$labsConfig = $read('labs/config-example.php');
$gate = $read('run-quality-gate.sh');

$defaultConfig = tl_stage894_config();
$check(empty($defaultConfig['enabled']), 'client is disabled by default');
$check(empty($defaultConfig['ready']), 'disabled client is not ready');
$check(!function_exists('microgifter_training_reward_lookup'), 'disabled client does not register Stage 893 adapter');

$body = '{"contract":"training_lab_reward_reconciliation_v1","read_only":true}';
$canonical = tl_stage894_canonical_request('1700000000', 'nonce-1234567890', $body);
$expectedCanonical = "training-lab-reward-lookup-v1\n1700000000\nnonce-1234567890\n" . hash('sha256', $body);
$check(hash_equals($expectedCanonical, $canonical), 'canonical request format is deterministic');
$signature = tl_stage894_signature('stage894-contract-secret-0123456789', '1700000000', 'nonce-1234567890', $body);
$expectedSignature = hash_hmac('sha256', $expectedCanonical, 'stage894-contract-secret-0123456789');
$check(hash_equals($expectedSignature, $signature), 'signature matches Microgifter HMAC contract');

$validEndpoint = tl_stage894_endpoint_status('https://microgifter.com/api/integrations/training-lab-reward-lookup.php', ['microgifter.com']);
$check(!empty($validEndpoint['valid']), 'canonical HTTPS endpoint is allowed');
$check(empty(tl_stage894_endpoint_status('http://microgifter.com/api/integrations/training-lab-reward-lookup.php', ['microgifter.com'])['valid']), 'HTTP endpoint is rejected');
$check(empty(tl_stage894_endpoint_status('https://example.com/api/integrations/training-lab-reward-lookup.php', ['microgifter.com'])['valid']), 'unapproved host is rejected');
$check(empty(tl_stage894_endpoint_status('https://user:pass@microgifter.com/api/integrations/training-lab-reward-lookup.php', ['microgifter.com'])['valid']), 'URL credentials are rejected');

$check(str_contains($client, 'CURLOPT_SSL_VERIFYPEER=>true') && str_contains($client, 'CURLOPT_SSL_VERIFYHOST=>2'), 'TLS peer and hostname verification are enabled');
$check(str_contains($client, 'CURLOPT_FOLLOWLOCATION=>false') && str_contains($client, 'CURLOPT_MAXREDIRS=>0'), 'redirects are disabled');
$check(str_contains($client, 'CURLOPT_CONNECTTIMEOUT') && str_contains($client, 'CURLOPT_TIMEOUT'), 'transport uses bounded timeouts');
$check(str_contains($client, 'max_response_bytes') && str_contains($client, 'response was too large'), 'response size is bounded');
$check(str_contains($client, 'random_bytes(24)') && str_contains($client, 'X-Microgifter-Training-Lab-Nonce'), 'every lookup uses a cryptographic nonce');
$check(str_contains($client, 'X-Microgifter-Training-Lab-Signature') && str_contains($client, 'tl_stage894_signature'), 'signed request header is present');
$check(str_contains($client, "if (!empty(\$stage894RegistrationConfig['ready']))") && str_contains($client, 'function microgifter_training_reward_lookup'), 'adapter registration requires full readiness');
$check(!str_contains($client, 'HTTP_COOKIE') && !str_contains($client, 'Authorization:') && !str_contains($client, 'developer_api_key'), 'client sends no browser session or developer key');
$check(str_contains($client, "'shared_secret_not_returned'=>true") && !str_contains($client, "'secret'=>(string)"), 'summary never returns the shared secret');

$check(strpos($bootstrap, 'training-lab-stage894-signed-reward-lookup-client.php') < strpos($bootstrap, 'training-lab-stage893-processing-wrapper.php'), 'Stage 894 loads before Stage 893 reconciliation');
foreach ([$reconciliationApi,$operationsApi,$outboxApi,$appAction,$proofReview,$adminAction,$rewardBridge] as $route) {
    $check(str_contains($route, 'training-lab-stage894-reconciliation-bootstrap.php'), 'active route loads Stage 894 bootstrap');
}
$check(str_contains($worker, 'training-lab-stage894-signed-reward-lookup-client.php') && strpos($worker, 'training-lab-stage894-signed-reward-lookup-client.php') < strpos($worker, 'training-lab-stage893-worker-wrapper.php'), 'CLI loads Stage 894 before worker reconciliation');
$check(str_contains($rewardBridge, 'tl_stage894_render_admin_panel'), 'Reward Bridge renders Stage 894 readiness panel');
$check(str_contains($reconciliationApi, 'tl_stage894_summary') && str_contains($operationsApi, 'tl_stage894_summary') && str_contains($outboxApi, 'tl_stage894_summary'), 'protected APIs expose sanitized client readiness');

foreach ([$config,$labsConfig] as $index => $example) {
    $label = $index === 0 ? 'root config example' : 'labs config example';
    $check(str_contains($example, "'microgifter_reward_lookup_enabled' => false"), $label . ' keeps client disabled');
    $check(str_contains($example, "'microgifter_reward_lookup_url' => 'https://microgifter.com/"), $label . ' documents HTTPS endpoint');
    $check(str_contains($example, "'microgifter_reward_lookup_allowed_hosts'"), $label . ' documents host allowlist');
    $check(str_contains($example, "'microgifter_reward_lookup_timeout_seconds' => 8"), $label . ' documents timeout');
    $check(str_contains($example, "'microgifter_reward_lookup_secret' => 'DO_NOT_COMMIT_A_REAL_SECRET'"), $label . ' documents private secret without a live value');
}
$check(str_contains($gate, 'stage894-signed-reward-lookup-client-contract-test.php'), 'full quality gate runs Stage 894 contract');
$check(!is_file($root . '/database/stage894_signed_reward_lookup_client.sql'), 'Stage 894 requires no SQL migration');

if ($failures) {
    fwrite(STDERR, "Stage 894 signed reward lookup client contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Stage 894 signed reward lookup client contract passed.\n";
