<?php
/**
 * Stage 893 compatibility guards for older claim/retry and manual processing actions.
 * Exposed routes must use these helpers instead of the legacy direct adapter bridge.
 */
require_once __DIR__ . '/training-lab-stage893-processing-wrapper.php';

if (!function_exists('tl_stage893_require_reconciliation_processing_gate')) {
    function tl_stage893_require_reconciliation_processing_gate(): void
    {
        if (empty(tl_stage893_config()['enabled'])) {
            throw new TlHttpException('External delivery reconciliation must be enabled before reward handoff processing can run.', 409, 'external_delivery_reconciliation_disabled');
        }
    }
}

if (!function_exists('tl_stage893_process_handoff_production_guarded')) {
    function tl_stage893_process_handoff_production_guarded(array $input): array
    {
        tl_stage893_require_reconciliation_processing_gate();
        return tl_stage893_process_handoff_guarded($input);
    }
}

if (!function_exists('tl_stage893_process_batch_production_guarded')) {
    function tl_stage893_process_batch_production_guarded(array $input = []): array
    {
        tl_stage893_require_reconciliation_processing_gate();
        return tl_stage893_process_guarded_batch($input);
    }
}

if (!function_exists('tl_stage893_claim_or_retry_reward_guarded')) {
    function tl_stage893_claim_or_retry_reward_guarded(array $input): array
    {
        $enqueue = tl_stage893_enqueue_reward_event_guarded($input);
        $handoffId = (int)($enqueue['handoff_id'] ?? 0);
        if ($handoffId < 1) throw new TlHttpException('The reward handoff could not be resolved.', 500, 'handoff_resolution_failed');
        if ((string)($enqueue['handoff_status'] ?? '') === 'delivered') {
            return [
                'handoff'=>$enqueue,
                'processing'=>['handoff_id'=>$handoffId,'handoff_status'=>'delivered','idempotent'=>true],
                'legacy_direct_adapter_bypassed'=>true,
            ];
        }
        tl_stage893_require_reconciliation_processing_gate();
        $processing = tl_stage893_process_handoff_guarded($input + ['handoff_id'=>(string)$handoffId]);
        return [
            'handoff'=>$enqueue,
            'processing'=>$processing,
            'legacy_direct_adapter_bypassed'=>true,
        ];
    }
}
