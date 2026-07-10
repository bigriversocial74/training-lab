<?php
/**
 * Stage 892 — Scheduled Reward Handoff Worker v1.
 *
 * Provides a CLI-safe orchestration layer over the Stage 890/891 outbox.
 * The default mode is observe-only. Adapter delivery requires an explicit
 * process request plus every existing production gate.
 */
require_once __DIR__ . '/training-lab-stage891-owned-processor.php';

if (!function_exists('tl_stage892_root_config')) {
    function tl_stage892_root_config(): array
    {
        if (function_exists('tl_security_config')) {
            $config = tl_security_config();
            return is_array($config) ? $config : [];
        }
        if (function_exists('tl_db_config_load')) {
            $loaded = tl_db_config_load();
            $config = $loaded['config']['training_lab'] ?? [];
            return is_array($config) ? $config : [];
        }
        return [];
    }
}

if (!function_exists('tl_stage892_config')) {
    function tl_stage892_config(): array
    {
        $root = tl_stage892_root_config();
        $enabled = getenv('TL_REWARD_HANDOFF_WORKER_ENABLED');
        $batch = getenv('TL_REWARD_HANDOFF_WORKER_BATCH_SIZE');
        $runtime = getenv('TL_REWARD_HANDOFF_WORKER_MAX_RUNTIME_SECONDS');
        $lockFile = getenv('TL_REWARD_HANDOFF_WORKER_LOCK_FILE');
        $actor = getenv('TL_REWARD_HANDOFF_WORKER_ACTOR_USER_ID');

        $defaultLock = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'training-lab-stage892-reward-worker.lock';
        $resolvedLock = trim((string)($lockFile !== false && $lockFile !== ''
            ? $lockFile
            : ($root['reward_handoff_worker_lock_file'] ?? $defaultLock)));
        if ($resolvedLock === '') $resolvedLock = $defaultLock;

        return [
            'worker_enabled' => function_exists('tl_stage890_bool')
                ? tl_stage890_bool($enabled !== false ? $enabled : ($root['reward_handoff_worker_enabled'] ?? false), false)
                : filter_var($enabled !== false ? $enabled : ($root['reward_handoff_worker_enabled'] ?? false), FILTER_VALIDATE_BOOLEAN),
            'batch_size' => max(1, min(50, (int)($batch !== false && $batch !== ''
                ? $batch
                : ($root['reward_handoff_worker_batch_size'] ?? 10)))),
            'max_runtime_seconds' => max(5, min(300, (int)($runtime !== false && $runtime !== ''
                ? $runtime
                : ($root['reward_handoff_worker_max_runtime_seconds'] ?? 45)))),
            'lock_file' => $resolvedLock,
            'actor_user_id' => max(1, (int)($actor !== false && $actor !== ''
                ? $actor
                : ($root['reward_handoff_worker_actor_user_id'] ?? 1))),
        ];
    }
}

if (!function_exists('tl_stage892_mode')) {
    function tl_stage892_mode($value): string
    {
        $value = strtolower(trim((string)$value));
        return in_array($value, ['observe', 'recover', 'process'], true) ? $value : 'observe';
    }
}

if (!function_exists('tl_stage892_lock_path_status')) {
    function tl_stage892_lock_path_status(string $path): array
    {
        $path = trim($path);
        $directory = dirname($path);
        $root = realpath(dirname(__DIR__));
        $realDirectory = realpath($directory);
        $insidePublicTree = false;
        if ($root !== false && $realDirectory !== false) {
            $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $insidePublicTree = $realDirectory === $root || str_starts_with($realDirectory . DIRECTORY_SEPARATOR, $rootPrefix);
        }
        return [
            'path_present' => $path !== '',
            'directory_exists' => is_dir($directory),
            'directory_writable' => is_dir($directory) && is_writable($directory),
            'outside_repository_tree' => !$insidePublicTree,
            'ready' => $path !== '' && is_dir($directory) && is_writable($directory) && !$insidePublicTree,
            'display_name' => basename($path),
        ];
    }
}

if (!function_exists('tl_stage892_acquire_lock')) {
    function tl_stage892_acquire_lock(string $path, string $runId, string $mode): array
    {
        $status = tl_stage892_lock_path_status($path);
        if (empty($status['ready'])) {
            throw new RuntimeException('Stage 892 lock file must use a writable directory outside the deployed repository tree.');
        }
        $handle = @fopen($path, 'c+');
        if (!is_resource($handle)) throw new RuntimeException('Stage 892 could not open the worker lock file.');
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return ['acquired'=>false, 'handle'=>null, 'status'=>$status];
        }
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode([
            'run_id'=>$runId,
            'mode'=>$mode,
            'pid'=>function_exists('getmypid') ? getmypid() : null,
            'started_at'=>gmdate('c'),
        ], JSON_UNESCAPED_SLASHES));
        fflush($handle);
        return ['acquired'=>true, 'handle'=>$handle, 'status'=>$status];
    }
}

if (!function_exists('tl_stage892_release_lock')) {
    function tl_stage892_release_lock($handle): void
    {
        if (!is_resource($handle)) return;
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

if (!function_exists('tl_stage892_event')) {
    function tl_stage892_event(string $eventType, array $metadata, ?int $actorUserId = null): void
    {
        $pdo = function_exists('tl_db') ? tl_db() : null;
        if (!$pdo || !function_exists('tl_log_event')) return;
        try {
            tl_log_event($pdo, $actorUserId, 'system', null, $eventType, $metadata);
        } catch (Throwable $e) {
            // Worker logging must never replace the primary run result.
        }
    }
}

if (!function_exists('tl_stage892_due_handoff_ids')) {
    function tl_stage892_due_handoff_ids(PDO $pdo, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $pdo->prepare("SELECT id FROM training_reward_handoffs WHERE handoff_status IN ('queued','failed') AND (next_attempt_at IS NULL OR next_attempt_at <= UTC_TIMESTAMP()) AND attempt_count < ? ORDER BY COALESCE(next_attempt_at, created_at) ASC, id ASC LIMIT " . $limit);
        $stmt->execute([(int)tl_stage890_config()['max_attempts']]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}

if (!function_exists('tl_stage892_compact_outcome')) {
    function tl_stage892_compact_outcome(array $result): array
    {
        $compact = [
            'handoff_id'=>(int)($result['handoff_id'] ?? 0),
            'public_id'=>(string)($result['public_id'] ?? ''),
            'handoff_status'=>(string)($result['handoff_status'] ?? ($result['error'] ?? 'unknown')),
        ];
        foreach (['idempotent','ownership_lost','adapter_result_unapplied','next_attempt_at'] as $key) {
            if (array_key_exists($key, $result)) $compact[$key] = $result[$key];
        }
        if (!empty($result['blockers']) && is_array($result['blockers'])) $compact['blockers'] = array_values($result['blockers']);
        if (!empty($result['error'])) $compact['error'] = mb_substr((string)$result['error'], 0, 500);
        return $compact;
    }
}

if (!function_exists('tl_stage892_counts')) {
    function tl_stage892_counts(array $processed): array
    {
        $counts = ['total'=>count($processed),'delivered'=>0,'failed'=>0,'blocked'=>0,'queued'=>0,'ownership_lost'=>0,'errors'=>0,'other'=>0];
        foreach ($processed as $row) {
            if (!empty($row['error'])) { $counts['errors']++; continue; }
            if (!empty($row['ownership_lost'])) $counts['ownership_lost']++;
            $status = (string)($row['handoff_status'] ?? '');
            if (isset($counts[$status])) $counts[$status]++;
            else $counts['other']++;
        }
        return $counts;
    }
}

if (!function_exists('tl_stage892_skip_result')) {
    function tl_stage892_skip_result(string $runId, string $mode, string $reason, array $extra = []): array
    {
        return array_merge([
            'stage'=>'Stage 892 Scheduled Reward Handoff Worker v1',
            'run_id'=>$runId,
            'mode'=>$mode,
            'status'=>'skipped',
            'reason'=>$reason,
            'exit_code'=>2,
            'completed_at'=>gmdate('c'),
        ], $extra);
    }
}

if (!function_exists('tl_stage892_run')) {
    function tl_stage892_run(array $input = []): array
    {
        $config = tl_stage892_config();
        $mode = tl_stage892_mode($input['mode'] ?? 'observe');
        $runId = function_exists('tl_uuid') ? tl_uuid() : bin2hex(random_bytes(16));
        $startedAt = microtime(true);
        $startedIso = gmdate('c');
        $actor = max(1, (int)($input['actor_user_id'] ?? $config['actor_user_id']));
        $limit = max(1, min((int)$config['batch_size'], (int)($input['limit'] ?? $config['batch_size'])));
        $lock = null;

        try {
            $lock = tl_stage892_acquire_lock((string)$config['lock_file'], $runId, $mode);
            if (empty($lock['acquired'])) {
                return tl_stage892_skip_result($runId, $mode, 'worker_overlap_detected', [
                    'started_at'=>$startedIso,
                    'lock_file_name'=>(string)($lock['status']['display_name'] ?? ''),
                ]);
            }

            if (!function_exists('tl_stage890_table_ready') || !tl_stage890_table_ready()) {
                $result = tl_stage892_skip_result($runId, $mode, 'stage890_schema_missing', ['started_at'=>$startedIso]);
                tl_stage892_event('stage892_worker_skipped', $result, $actor);
                return $result;
            }
            if (!function_exists('tl_db_ready') || !tl_db_ready()) {
                $result = tl_stage892_skip_result($runId, $mode, 'database_unavailable', ['started_at'=>$startedIso]);
                tl_stage892_event('stage892_worker_skipped', $result, $actor);
                return $result;
            }
            if ($mode !== 'observe' && empty($config['worker_enabled'])) {
                $result = tl_stage892_skip_result($runId, $mode, 'scheduled_worker_disabled', ['started_at'=>$startedIso]);
                tl_stage892_event('stage892_worker_skipped', $result, $actor);
                return $result;
            }
            if ($mode === 'process' && empty($input['explicit_process'])) {
                $result = tl_stage892_skip_result($runId, $mode, 'explicit_process_flag_required', ['started_at'=>$startedIso]);
                tl_stage892_event('stage892_worker_skipped', $result, $actor);
                return $result;
            }

            $adapter = tl_stage890_adapter_state();
            if ($mode === 'process' && empty($adapter['can_process'])) {
                $result = tl_stage892_skip_result($runId, $mode, 'production_processing_gates_closed', [
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
                'mode'=>$mode,
                'batch_limit'=>$limit,
                'max_runtime_seconds'=>(int)$config['max_runtime_seconds'],
                'worker_enabled'=>!empty($config['worker_enabled']),
            ], $actor);

            $recovery = null;
            $sync = null;
            $processed = [];
            $selected = 0;
            $runtimeLimitReached = false;

            if ($mode === 'recover' || $mode === 'process') {
                $recovery = tl_stage891_recover_stale_processing([
                    'actor_user_id'=>$actor,
                    'limit'=>(int)tl_stage891_config()['recovery_batch_size'],
                ]);
            }

            if ($mode === 'process') {
                $sync = tl_stage890_sync_outbox([
                    'actor_user_id'=>$actor,
                    'limit'=>max($limit, min(200, $limit * 5)),
                ]);
                $pdo = tl_require_db();
                $ids = tl_stage892_due_handoff_ids($pdo, $limit);
                $selected = count($ids);
                $deadline = $startedAt + (int)$config['max_runtime_seconds'];
                foreach ($ids as $id) {
                    if (microtime(true) >= $deadline) {
                        $runtimeLimitReached = true;
                        break;
                    }
                    try {
                        $processed[] = tl_stage892_compact_outcome(tl_stage891_process_handoff_owned([
                            'handoff_id'=>(string)$id,
                            'actor_user_id'=>$actor,
                        ]));
                    } catch (Throwable $e) {
                        $processed[] = ['handoff_id'=>$id,'handoff_status'=>'error','error'=>mb_substr($e->getMessage(), 0, 500)];
                    }
                }
            }

            $acceptance = tl_stage891_acceptance_summary();
            $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
            $result = [
                'stage'=>'Stage 892 Scheduled Reward Handoff Worker v1',
                'run_id'=>$runId,
                'mode'=>$mode,
                'status'=>$runtimeLimitReached ? 'partial_runtime_limit' : 'completed',
                'exit_code'=>0,
                'started_at'=>$startedIso,
                'completed_at'=>gmdate('c'),
                'duration_ms'=>$durationMs,
                'batch_limit'=>$limit,
                'selected'=>$selected,
                'processed_counts'=>tl_stage892_counts($processed),
                'processed'=>$processed,
                'recovery'=>$recovery,
                'sync'=>$sync,
                'acceptance'=>[
                    'score'=>(int)($acceptance['score'] ?? 0),
                    'safe_to_observe'=>!empty($acceptance['safe_to_observe']),
                    'ready_for_production_processing'=>!empty($acceptance['ready_for_production_processing']),
                ],
                'safe_boundaries'=>[
                    'observe_mode_is_read_only'=>true,
                    'recover_mode_never_calls_microgifter'=>true,
                    'process_mode_requires_explicit_cli_flag'=>true,
                    'process_mode_requires_all_stage890_gates'=>true,
                    'overlapping_workers_are_rejected'=>true,
                    'runtime_budget_checked_between_handoffs'=>true,
                    'credentials_are_not_logged'=>true,
                ],
            ];
            tl_stage892_event('stage892_worker_completed', [
                'run_id'=>$runId,
                'mode'=>$mode,
                'status'=>$result['status'],
                'duration_ms'=>$durationMs,
                'selected'=>$selected,
                'processed_counts'=>$result['processed_counts'],
                'recovery'=>is_array($recovery) ? [
                    'selected'=>(int)($recovery['selected'] ?? 0),
                    'recovered'=>(int)($recovery['recovered'] ?? 0),
                    'terminal_failures'=>(int)($recovery['terminal_failures'] ?? 0),
                ] : null,
                'acceptance'=>$result['acceptance'],
            ], $actor);
            return $result;
        } catch (Throwable $e) {
            $result = [
                'stage'=>'Stage 892 Scheduled Reward Handoff Worker v1',
                'run_id'=>$runId,
                'mode'=>$mode,
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

if (!function_exists('tl_stage892_parse_cli_arguments')) {
    function tl_stage892_parse_cli_arguments(array $argv): array
    {
        $modes = [];
        $limit = null;
        $help = false;
        $errors = [];
        foreach (array_slice($argv, 1) as $argument) {
            if ($argument === '--observe') $modes[] = 'observe';
            elseif ($argument === '--recover') $modes[] = 'recover';
            elseif ($argument === '--process') $modes[] = 'process';
            elseif ($argument === '--help' || $argument === '-h') $help = true;
            elseif (str_starts_with($argument, '--limit=')) {
                $value = substr($argument, 8);
                if ($value === '' || !ctype_digit($value) || (int)$value < 1) $errors[] = 'The --limit value must be a positive integer.';
                else $limit = (int)$value;
            } else $errors[] = 'Unknown argument: ' . $argument;
        }
        $modes = array_values(array_unique($modes));
        if (count($modes) > 1) $errors[] = 'Choose only one of --observe, --recover, or --process.';
        $mode = $modes[0] ?? 'observe';
        return [
            'mode'=>$mode,
            'limit'=>$limit,
            'help'=>$help,
            'errors'=>$errors,
            'explicit_process'=>$mode === 'process' && in_array('--process', $argv, true),
        ];
    }
}

if (!function_exists('tl_stage892_recent_runs')) {
    function tl_stage892_recent_runs(int $limit = 10): array
    {
        $pdo = function_exists('tl_db') ? tl_db() : null;
        if (!$pdo || !function_exists('tl_table_exists') || !tl_table_exists('training_events')) return [];
        $limit = max(1, min(50, $limit));
        try {
            $stmt = $pdo->query("SELECT event_type, metadata_json, created_at FROM training_events WHERE subject_type='system' AND event_type IN ('stage892_worker_completed','stage892_worker_failed','stage892_worker_skipped') ORDER BY id DESC LIMIT " . $limit);
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            return array_map(static function (array $row): array {
                $metadata = json_decode((string)($row['metadata_json'] ?? ''), true);
                return [
                    'event_type'=>(string)($row['event_type'] ?? ''),
                    'created_at'=>(string)($row['created_at'] ?? ''),
                    'run'=>is_array($metadata) ? $metadata : [],
                ];
            }, $rows);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_stage892_status_summary')) {
    function tl_stage892_status_summary(): array
    {
        $config = tl_stage892_config();
        $lockStatus = tl_stage892_lock_path_status((string)$config['lock_file']);
        $runs = tl_stage892_recent_runs(10);
        $acceptance = function_exists('tl_stage891_acceptance_summary') ? tl_stage891_acceptance_summary() : [];
        return [
            'stage'=>'Stage 892 Scheduled Reward Handoff Worker v1',
            'worker_enabled'=>!empty($config['worker_enabled']),
            'configuration'=>[
                'batch_size'=>(int)$config['batch_size'],
                'max_runtime_seconds'=>(int)$config['max_runtime_seconds'],
                'actor_user_id'=>(int)$config['actor_user_id'],
                'lock_file_name'=>(string)$lockStatus['display_name'],
                'lock_path_ready'=>!empty($lockStatus['ready']),
                'lock_directory_writable'=>!empty($lockStatus['directory_writable']),
                'lock_outside_repository_tree'=>!empty($lockStatus['outside_repository_tree']),
            ],
            'processing_gate'=>[
                'adapter_can_process'=>!empty(($acceptance['adapter'] ?? [])['can_process']),
                'ready_for_production_processing'=>!empty($acceptance['ready_for_production_processing']),
            ],
            'last_run'=>$runs[0] ?? null,
            'recent_runs'=>$runs,
            'commands'=>[
                'observe'=>'php /path/to/training-lab/bin/reward-handoff-worker.php --observe',
                'recover'=>'php /path/to/training-lab/bin/reward-handoff-worker.php --recover',
                'process'=>'php /path/to/training-lab/bin/reward-handoff-worker.php --process',
            ],
            'safe_boundaries'=>[
                'cli_only'=>true,
                'observe_is_default'=>true,
                'no_http_execution_route'=>true,
                'processing_requires_explicit_flag_and_all_gates'=>true,
                'no_new_sql_required'=>true,
            ],
        ];
    }
}

if (!function_exists('tl_stage892_render_admin_panel')) {
    function tl_stage892_render_admin_panel(): void
    {
        $data = tl_stage892_status_summary();
        $config = (array)$data['configuration'];
        $last = is_array($data['last_run'] ?? null) ? (array)$data['last_run'] : [];
        $lastRun = is_array($last['run'] ?? null) ? (array)$last['run'] : [];
        $lastStatus = (string)($lastRun['status'] ?? ($last['event_type'] ?? 'Not run'));
        $lastMode = (string)($lastRun['mode'] ?? '—');
        $lastAt = (string)($last['created_at'] ?? 'Not run');
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 892</span><h2>Scheduled Reward Worker</h2><p class="labs-copy">Run observe, stale-recovery, or explicitly gated delivery batches from a cPanel cron command without exposing a web execution route.</p></div><a class="labs-btn" href="' . labs_e(labs_url('/api/training/reward-handoff-worker-status.php')) . '">Worker Status API</a></div>';
        echo '<div class="labs-kpis"><div class="labs-kpi"><span>Worker</span><strong>' . (!empty($data['worker_enabled']) ? 'Enabled' : 'Disabled') . '</strong><small>recover/process switch</small></div><div class="labs-kpi"><span>Lock</span><strong>' . (!empty($config['lock_path_ready']) ? 'Ready' : 'Check') . '</strong><small>' . labs_e((string)$config['lock_file_name']) . '</small></div><div class="labs-kpi"><span>Last Run</span><strong>' . labs_e(ucwords(str_replace('_', ' ', $lastStatus))) . '</strong><small>' . labs_e($lastMode . ' · ' . $lastAt) . '</small></div><div class="labs-kpi"><span>Runtime</span><strong>' . (int)$config['max_runtime_seconds'] . 's</strong><small>batch ' . (int)$config['batch_size'] . '</small></div></div>';
        echo '<div class="labs-stage25-code"><strong>Safe first cron</strong><pre>' . labs_e((string)$data['commands']['observe']) . '</pre><strong>Production cron after acceptance</strong><pre>' . labs_e((string)$data['commands']['process']) . '</pre></div>';
        echo '<div class="labs-safe-note">Observe mode is the default and read-only. Recover/process require the worker flag. Process additionally requires the explicit CLI flag and every Stage 890 production gate.</div></section>';
    }
}
