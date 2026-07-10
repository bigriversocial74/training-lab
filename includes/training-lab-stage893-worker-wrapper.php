<?php
/** Stage 893 wrapper for the Stage 892 CLI worker. */
require_once __DIR__ . '/training-lab-stage892-scheduled-worker.php';
require_once __DIR__ . '/training-lab-stage893-processing-wrapper.php';

if (!function_exists('tl_stage893_compact_outcome')) {
    function tl_stage893_compact_outcome(array $result): array
    {
        $compact = tl_stage892_compact_outcome($result);
        if (array_key_exists('external_reference', $result)) $compact['external_reference'] = (string)$result['external_reference'];
        if (isset($result['stage893_reconciliation']) && is_array($result['stage893_reconciliation'])) {
            $compact['stage893_reconciliation'] = $result['stage893_reconciliation'];
        }
        return $compact;
    }
}

if (!function_exists('tl_stage893_run_process_worker')) {
    function tl_stage893_run_process_worker(array $input = []): array
    {
        $workerConfig = tl_stage892_config();
        $reconciliationConfig = tl_stage893_config();
        $runId = function_exists('tl_uuid') ? tl_uuid() : bin2hex(random_bytes(16));
        $startedAt = microtime(true);
        $startedIso = gmdate('c');
        $actor = max(1, (int)($input['actor_user_id'] ?? $workerConfig['actor_user_id'] ?? 1));
        $limit = max(1, min((int)$workerConfig['batch_size'], (int)($input['limit'] ?? $workerConfig['batch_size'])));
        $lock = null;

        try {
            $lock = tl_stage892_acquire_lock((string)$workerConfig['lock_file'], $runId, 'process');
            if (empty($lock['acquired'])) {
                return tl_stage892_skip_result($runId, 'process', 'worker_overlap_detected', [
                    'started_at'=>$startedIso,
                    'lock_file_name'=>(string)($lock['status']['display_name'] ?? ''),
                ]);
            }
            if (!tl_stage890_table_ready()) {
                $result = tl_stage892_skip_result($runId, 'process', 'stage890_schema_missing', ['started_at'=>$startedIso]);
                tl_stage892_event('stage892_worker_skipped', $result, $actor);
                return $result;
            }
            if (!tl_db_ready()) {
                $result = tl_stage892_skip_result($runId, 'process', 'database_unavailable', ['started_at'=>$startedIso]);
                tl_stage892_event('stage892_worker_skipped', $result, $actor);
                return $result;
            }
            if (empty($workerConfig['worker_enabled'])) {
                $result = tl_stage892_skip_result($runId, 'process', 'scheduled_worker_disabled', ['started_at'=>$startedIso]);
                tl_stage892_event('stage892_worker_skipped', $result, $actor);
                return $result;
            }
            if (empty($input['explicit_process'])) {
                $result = tl_stage892_skip_result($runId, 'process', 'explicit_process_flag_required', ['started_at'=>$startedIso]);
                tl_stage892_event('stage892_worker_skipped', $result, $actor);
                return $result;
            }
            if (empty($reconciliationConfig['enabled'])) {
                $result = tl_stage892_skip_result($runId, 'process', 'external_delivery_reconciliation_disabled', ['started_at'=>$startedIso]);
                tl_stage892_event('stage892_worker_skipped', $result, $actor);
                return $result;
            }
            $adapter = tl_stage890_adapter_state();
            if (empty($adapter['can_process'])) {
                $result = tl_stage892_skip_result($runId, 'process', 'production_processing_gates_closed', [
                    'started_at'=>$startedIso,
                    'adapter'=>[
                        'processing_enabled'=>!empty($adapter['processing_enabled']),
                        'production_issuing_enabled'=>!empty($adapter['production_issuing_enabled']),
                        'developer_key_present'=>!empty($adapter['developer_key_present']),
                        'direct_adapter_present'=>!empty($adapter['direct_adapter_functions']),
                    ],
                ]);
                tl_stage892_event('stage892_worker_skipped', $result, $actor);
                return $result;
            }

            tl_stage892_event('stage892_worker_started', [
                'run_id'=>$runId,
                'mode'=>'process',
                'batch_limit'=>$limit,
                'max_runtime_seconds'=>(int)$workerConfig['max_runtime_seconds'],
                'worker_enabled'=>true,
                'stage893_reconciliation_enabled'=>true,
            ], $actor);

            $recovery = tl_stage891_recover_stale_processing([
                'actor_user_id'=>$actor,
                'limit'=>(int)tl_stage891_config()['recovery_batch_size'],
            ]);
            $reconciliation = tl_stage893_reconcile_batch([
                'actor_user_id'=>$actor,
                'automatic'=>true,
                'limit'=>max($limit, min(100, (int)$reconciliationConfig['batch_size'])),
            ]);
            $sync = tl_stage893_sync_outbox_guarded([
                'actor_user_id'=>$actor,
                'limit'=>max($limit, min(200, $limit * 5)),
            ]);
            $pdo = tl_require_db();
            $ids = tl_stage893_due_handoff_ids($pdo, $limit);
            $processed = [];
            $runtimeLimitReached = false;
            $deadline = $startedAt + (int)$workerConfig['max_runtime_seconds'];
            foreach ($ids as $id) {
                if (microtime(true) >= $deadline) {
                    $runtimeLimitReached = true;
                    break;
                }
                try {
                    $processed[] = tl_stage893_compact_outcome(tl_stage893_process_handoff_guarded([
                        'handoff_id'=>(string)$id,
                        'actor_user_id'=>$actor,
                    ]));
                } catch (Throwable $e) {
                    $processed[] = ['handoff_id'=>$id,'handoff_status'=>'error','error'=>mb_substr($e->getMessage(), 0, 500)];
                }
            }

            $acceptance = tl_stage891_acceptance_summary();
            $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
            $result = [
                'stage'=>'Stage 892/893 Scheduled Reward Handoff Worker',
                'run_id'=>$runId,
                'mode'=>'process',
                'status'=>$runtimeLimitReached ? 'partial_runtime_limit' : 'completed',
                'exit_code'=>0,
                'started_at'=>$startedIso,
                'completed_at'=>gmdate('c'),
                'duration_ms'=>$durationMs,
                'batch_limit'=>$limit,
                'selected'=>count($ids),
                'processed_counts'=>tl_stage892_counts($processed),
                'processed'=>$processed,
                'recovery'=>$recovery,
                'reconciliation'=>$reconciliation,
                'sync'=>$sync,
                'acceptance'=>[
                    'score'=>(int)($acceptance['score'] ?? 0),
                    'safe_to_observe'=>!empty($acceptance['safe_to_observe']),
                    'ready_for_production_processing'=>!empty($acceptance['ready_for_production_processing']),
                ],
                'safe_boundaries'=>[
                    'quarantined_deliveries_excluded_from_sync'=>true,
                    'quarantined_deliveries_excluded_from_processing'=>true,
                    'lost_lease_success_is_quarantined'=>true,
                    'process_mode_requires_explicit_cli_flag'=>true,
                    'process_mode_requires_all_stage890_gates'=>true,
                    'overlapping_workers_are_rejected'=>true,
                    'runtime_budget_checked_between_handoffs'=>true,
                    'credentials_are_not_logged'=>true,
                ],
            ];
            tl_stage892_event('stage892_worker_completed', [
                'run_id'=>$runId,
                'mode'=>'process',
                'status'=>$result['status'],
                'duration_ms'=>$durationMs,
                'selected'=>count($ids),
                'processed_counts'=>$result['processed_counts'],
                'reconciliation_counts'=>(array)($reconciliation['counts'] ?? []),
                'quarantined_excluded'=>(int)($sync['quarantined_excluded'] ?? 0),
                'acceptance'=>$result['acceptance'],
            ], $actor);
            return $result;
        } catch (Throwable $e) {
            $result = [
                'stage'=>'Stage 892/893 Scheduled Reward Handoff Worker',
                'run_id'=>$runId,
                'mode'=>'process',
                'status'=>'failed',
                'exit_code'=>1,
                'started_at'=>$startedIso,
                'completed_at'=>gmdate('c'),
                'duration_ms'=>(int)round((microtime(true) - $startedAt) * 1000),
                'error'=>mb_substr($e->getMessage(), 0, 500),
            ];
            tl_stage892_event('stage892_worker_failed', $result, $actor);
            return $result;
        } finally {
            if (is_array($lock) && !empty($lock['acquired'])) tl_stage892_release_lock($lock['handle'] ?? null);
        }
    }
}

if (!function_exists('tl_stage893_run_scheduled_worker')) {
    function tl_stage893_run_scheduled_worker(array $input = []): array
    {
        $mode = tl_stage892_mode($input['mode'] ?? 'observe');
        if ($mode !== 'process') return tl_stage892_run($input);
        return tl_stage893_run_process_worker($input);
    }
}
