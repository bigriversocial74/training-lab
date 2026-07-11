<?php
declare(strict_types=1);

$root = dirname(__DIR__);
putenv('TL_MICROGIFTER_DEVELOPER_API_KEY=stage890-test-developer-key-0123456789');
putenv('TL_MICROGIFTER_PRODUCTION_ISSUING_ENABLED=true');
putenv('TL_REWARD_HANDOFF_PROCESSING_ENABLED=true');
putenv('TL_REWARD_HANDOFF_BATCH_SIZE=7');
putenv('TL_REWARD_HANDOFF_MAX_ATTEMPTS=4');
putenv('TL_REWARD_HANDOFF_RETRY_BASE_SECONDS=120');

if (!function_exists('microgifter_issue_training_reward')) {
    function microgifter_issue_training_reward(array $payload): array
    {
        return [
            'ok'=>true,
            'gift_id'=>'gift-stage890-test',
            'received_idempotency_key'=>(string)($payload['idempotency_key'] ?? ''),
        ];
    }
}

require_once $root . '/includes/training-lab-stage890-reward-handoff-outbox.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$config = tl_stage890_config();
$assert($config['processing_enabled'] === true, 'Stage 890 processing flag must be configurable.');
$assert((int)$config['batch_size'] === 7, 'Stage 890 batch size must be configurable.');
$assert((int)$config['max_attempts'] === 4, 'Stage 890 maximum attempts must be configurable.');
$assert(tl_stage890_retry_delay(1) === 120, 'First retry must use the base delay.');
$assert(tl_stage890_retry_delay(2) === 240, 'Retry delay must use exponential backoff.');
$assert(tl_stage890_retry_delay(20) <= 86400, 'Retry delay must remain capped.');

$reward = [
    'id'=>11,
    'public_id'=>'11111111-2222-4333-8444-555555555555',
    'campaign_id'=>4,
    'campaign_public_id'=>'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
    'campaign_title'=>'Stage 890 Test Campaign',
    'participant_id'=>8,
    'user_id'=>42,
    'participant_label'=>'Test Participant',
    'reward_label'=>'Test Microgift',
    'reward_type'=>'microgift',
    'value_cents'=>500,
    'currency'=>'USD',
    'linked_microgift_template_id'=>9,
    'linked_catalog_product_id'=>null,
    'eligibility_reason'=>'Approved proof.',
    'status'=>'eligible',
];
$link = [
    'id'=>3,
    'public_id'=>'99999999-8888-4777-8666-555555555555',
    'microgifter_user_id'=>'42',
    'email'=>'participant@example.test',
    'display_name'=>'Test Participant',
    'merchant_context'=>'merchant-1',
    'organization_context'=>'organization-1',
];
$key = tl_stage890_idempotency_key($reward);
$payload = tl_stage890_payload($reward, $link, $key);
$assert(strlen($key) === 64, 'Idempotency key must be a SHA-256 hash.');
$assert((string)$payload['idempotency_key'] === $key, 'Adapter payload must retain the stable idempotency key.');
$assert((string)$payload['microgifter_user_id'] === '42', 'Adapter payload must use the server-derived account link identity.');
$assert(!array_key_exists('password', $payload) && !array_key_exists('password_hash', $payload), 'Adapter payload must never contain credentials.');

$adapter = tl_stage890_adapter_state();
$assert($adapter['can_process'] === true, 'All explicit Stage 890 gates should allow the test adapter.');
$assert(in_array('microgifter_issue_training_reward', $adapter['direct_adapter_functions'], true), 'Direct issue adapter must be detected.');
$adapterResult = tl_stage890_call_adapter($payload);
$assert(!empty($adapterResult['ok']), 'Direct adapter test handoff must succeed.');
$assert((string)($adapterResult['result']['received_idempotency_key'] ?? '') === $key, 'Adapter must receive the idempotency key.');

$blocked = tl_stage890_blockers($reward, null, ['processing_enabled'=>false,'production_issuing_enabled'=>false,'developer_key_present'=>false,'direct_adapter_functions'=>[]]);
$assert(in_array('active_account_link_required', $blocked, true), 'Missing account link must block delivery.');
$assert(in_array('outbox_processing_disabled', $blocked, true), 'Disabled processing must block delivery.');
$assert(in_array('production_issuing_disabled', $blocked, true), 'Disabled production issuing must block delivery.');
$assert(in_array('developer_key_missing', $blocked, true), 'Missing developer key must block delivery.');
$assert(in_array('direct_adapter_missing', $blocked, true), 'Missing direct adapter must block delivery.');

$sql = file_get_contents($root . '/database/stage890_reward_handoff_outbox_v1.sql') ?: '';
$service = file_get_contents($root . '/includes/training-lab-stage890-reward-handoff-outbox.php') ?: '';
$api = file_get_contents($root . '/api/training/reward-handoff-outbox.php') ?: '';
$merchantFulfillment = file_get_contents($root . '/admin/reward-bridge.php') ?: '';
$advancedOperations = file_get_contents($root . '/admin/reward-operations.php') ?: '';
$actions = file_get_contents($root . '/admin/action-result.php') ?: '';
$configExample = file_get_contents($root . '/labs/config-example.php') ?: '';

$assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS training_reward_handoffs'), 'Stage 890 SQL must create the outbox table.');
$assert(str_contains($sql, 'UNIQUE KEY uq_training_reward_handoffs_reward_event'), 'Only one handoff may exist per reward event.');
$assert(str_contains($sql, 'UNIQUE KEY uq_training_reward_handoffs_idempotency'), 'Outbox idempotency keys must be unique.');
$assert(str_contains($sql, "ENUM('queued','blocked','processing','delivered','failed','cancelled')"), 'Outbox lifecycle statuses must be explicit.');
$assert(str_contains($service, 'FOR UPDATE'), 'Outbox processing must use row locks.');
$assert(str_contains($service, 'attempt_count') && str_contains($service, 'next_attempt_at'), 'Outbox must persist retry state.');
$assert(str_contains($service, 'tl_stage890_adapter_state') && str_contains($service, 'can_process'), 'Outbox must enforce explicit adapter gates.');
$assert(str_contains($service, 'training_account_links'), 'Outbox must resolve the recipient through the persistent account link.');
$assert(str_contains($service, 'no_password_claims'), 'Outbox payload must declare credential exclusion.');
$assert(str_contains($api, 'tl_security_guard_write') && str_contains($api, 'tl_auth_role_allowed'), 'Outbox API must protect reads and writes.');
$assert(str_contains($advancedOperations, 'tl_stage890_render_admin_panel'), 'Advanced Reward Operations must render the Stage 890 operating panel.');
$assert(!str_contains($merchantFulfillment, 'tl_stage890_render_admin_panel'), 'Merchant fulfillment must not render the Stage 890 operating panel.');
$assert(str_contains($actions, 'process_reward_handoff_batch') && str_contains($actions, 'sync_reward_handoff_outbox'), 'Protected action page must dispatch Stage 890 operations.');
$assert(str_contains($configExample, 'reward_handoff_processing_enabled') && str_contains($configExample, 'reward_handoff_max_attempts'), 'Deployment config must document Stage 890 controls.');

if ($failures) {
    fwrite(STDERR, "Stage 890 reward handoff outbox contract test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Stage 890 reward handoff outbox contract test passed.\n";
