<?php
/** Stage 893 wrapper for the Stage 892 CLI worker. */
require_once __DIR__ . '/training-lab-stage892-scheduled-worker.php';
require_once __DIR__ . '/training-lab-stage893-processing-wrapper.php';

if (!function_exists('tl_stage893_run_scheduled_worker')) {
    function tl_stage893_run_scheduled_worker(array $input = []): array
    {
        $mode = function_exists('tl_stage892_mode') ? tl_stage892_mode($input['mode'] ?? 'observe') : 'observe';
        $actor = max(1, (int)($input['actor_user_id'] ?? tl_stage892_config()['actor_user_id'] ?? 1));
        $stage893 = tl_stage893_config();
        if ($mode === 'process' && empty($stage893['enabled'])) {
            return [
                'stage'=>'Stage 893 External Delivery Reconciliation v1',
                'mode'=>'process',
                'status'=>'skipped',
                'reason'=>'external_delivery_reconciliation_disabled',
                'exit_code'=>2,
                'completed_at'=>gmdate('c'),
                'safe_boundaries'=>[
                    'stage892_process_not_started'=>true,
                    'uncertain_deliveries_cannot_be_retried'=>true,
                ],
            ];
        }

        $preflight = null;
        if ($mode === 'process') {
            $preflight = tl_stage893_reconcile_batch([
                'actor_user_id'=>$actor,
                'automatic'=>true,
                'limit'=>max(1, min(50, (int)($input['limit'] ?? $stage893['batch_size']))),
            ]);
        }

        $result = tl_stage892_run($input + ['actor_user_id'=>$actor]);
        $quarantines = [];
        foreach ((array)($result['processed'] ?? []) as $outcome) {
            if (!is_array($outcome) || empty($outcome['ownership_lost']) || empty($outcome['adapter_result_unapplied'])) continue;
            try {
                $quarantines[] = tl_stage893_quarantine_lost_outcome($outcome, $actor);
            } catch (Throwable $e) {
                $quarantines[] = [
                    'handoff_id'=>(int)($outcome['handoff_id'] ?? 0),
                    'quarantined'=>false,
                    'error'=>mb_substr($e->getMessage(), 0, 500),
                ];
            }
        }
        $result['stage893_reconciliation'] = [
            'preflight'=>$preflight,
            'post_run_quarantines'=>$quarantines,
            'enabled'=>!empty($stage893['enabled']),
        ];
        return $result;
    }
}
