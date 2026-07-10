<?php
declare(strict_types=1);

/**
 * Stage 896 isolated bootstrap.
 *
 * The signed pilot client uses its own HMAC authentication instead of a generic
 * developer API key. These Stage 890 overrides exist only in requests that load
 * this bootstrap before the durable outbox service.
 */
if (!function_exists('tl_stage890_adapter_state')) {
    function tl_stage890_adapter_state(): array
    {
        $bridge = function_exists('tl_mg_rewards_config') ? tl_mg_rewards_config() : [];
        $direct = array_values(array_filter((array)($bridge['direct_adapter_functions']['issue_or_claim'] ?? [])));
        $productionEnabled = function_exists('tl_stage880_production_issuing_enabled')
            ? tl_stage880_production_issuing_enabled()
            : (function_exists('tl_stage890_bool')
                ? tl_stage890_bool(getenv('TL_MICROGIFTER_PRODUCTION_ISSUING_ENABLED'), false)
                : filter_var(getenv('TL_MICROGIFTER_PRODUCTION_ISSUING_ENABLED'), FILTER_VALIDATE_BOOLEAN));
        $processingEnabled = function_exists('tl_stage890_config')
            ? !empty(tl_stage890_config()['processing_enabled'])
            : filter_var(getenv('TL_REWARD_HANDOFF_PROCESSING_ENABLED'), FILTER_VALIDATE_BOOLEAN);
        $keyPresent = !empty($bridge['developer_api_key_present']);
        $signedPilotReady = function_exists('tl_stage896_issue_summary')
            && !empty(tl_stage896_issue_summary()['ready'])
            && in_array('microgifter_training_issue_reward', $direct, true);
        $authenticationPresent = $keyPresent || $signedPilotReady;
        return [
            'processing_enabled'=>$processingEnabled,
            'production_issuing_enabled'=>$productionEnabled,
            'developer_key_present'=>$keyPresent,
            'signed_pilot_authentication_ready'=>$signedPilotReady,
            'authentication_present'=>$authenticationPresent,
            'direct_adapter_functions'=>$direct,
            'adapter_mode'=>$signedPilotReady ? 'signed_stage896_pilot_adapter' : (string)($bridge['mode'] ?? 'adapter_pending'),
            'can_process'=>$processingEnabled && $productionEnabled && $authenticationPresent && count($direct) > 0,
        ];
    }
}

if (!function_exists('tl_stage890_blockers')) {
    function tl_stage890_blockers(array $reward, ?array $link, array $adapter): array
    {
        $blocked = [];
        $status = (string)($reward['status'] ?? 'eligible');
        if ($status === 'cancelled') $blocked[] = 'reward_cancelled';
        if (in_array($status, ['issued','linked'], true)) $blocked[] = 'reward_already_delivered';
        if (!$link) $blocked[] = 'active_account_link_required';
        if (empty($adapter['processing_enabled'])) $blocked[] = 'outbox_processing_disabled';
        if (empty($adapter['production_issuing_enabled'])) $blocked[] = 'production_issuing_disabled';
        if (empty($adapter['authentication_present'])) $blocked[] = 'production_adapter_authentication_missing';
        if (empty($adapter['direct_adapter_functions'])) $blocked[] = 'direct_adapter_missing';
        return array_values(array_unique($blocked));
    }
}

require_once __DIR__ . '/training-lab-stage896-signed-pilot-issue-client.php';
require_once __DIR__ . '/training-lab-stage896-limited-reward-pilot.php';
