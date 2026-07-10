<?php
declare(strict_types=1);

/** Stage 898 canary selection, readiness, execution, and pause controls. */
require_once __DIR__ . '/training-lab-stage898-config.php';

if (!function_exists('tl_stage898_candidate')) {
    function tl_stage898_candidate(PDO $pdo): ?array
    {
        $config = tl_stage898_config();
        $maxAttempts = (int)tl_stage890_config()['max_attempts'];
        $sql = "SELECT h.id
                FROM training_reward_handoffs h
                INNER JOIN training_reward_events re ON re.id=h.reward_event_id
                WHERE h.handoff_status IN ('queued','failed')
                  AND (h.next_attempt_at IS NULL OR h.next_attempt_at<=UTC_TIMESTAMP())
                  AND h.attempt_count<?
                  AND COALESCE(h.failure_code,'')<>'external_delivery_confirmation_required'
                  AND COALESCE(h.metadata_json,'') NOT LIKE '%\"reconciliation_required\":true%'
                  AND COALESCE(h.metadata_json,'') NOT LIKE '%\"stage896_pilot\"%'
                  AND re.status NOT IN ('issued','linked','cancelled')
                  AND re.value_cents BETWEEN 0 AND ?
                  AND re.currency='USD'
                ORDER BY re.value_cents ASC,COALESCE(h.next_attempt_at,h.created_at) ASC,h.id ASC
                LIMIT 25";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$maxAttempts,(int)$config['max_value_cents']]);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable $e) {
            return null;
        }
        foreach ($ids as $id) {
            try {
                $context = tl_stage896_load_context($pdo, (string)$id, false);
                $handoff = (array)$context['handoff'];
                $reward = (array)$context['reward'];
                $link = is_array($context['link'] ?? null) ? (array)$context['link'] : [];
                $linkedUserId = trim((string)($link['microgifter_user_id'] ?? ''));
                $handoffUserId = trim((string)($handoff['microgifter_user_id'] ?? ''));
                if ($linkedUserId === '' || !ctype_digit($linkedUserId) || (int)$linkedUserId < 1) continue;
                if ($handoffUserId !== '' && !hash_equals($linkedUserId, $handoffUserId)) continue;
                return [
                    'handoff_id'=>(int)$handoff['id'],
                    'handoff_public_id'=>(string)$handoff['public_id'],
                    'reward_event_id'=>(int)$reward['id'],
                    'reward_public_id'=>(string)$reward['public_id'],
                    'value_cents'=>(int)$reward['value_cents'],
                    'currency'=>(string)$reward['currency'],
                    'microgifter_user_id'=>$linkedUserId,
                    'microgifter_user_fingerprint'=>tl_stage898_fingerprint($linkedUserId),
                    'handoff_reference_fingerprint'=>tl_stage898_fingerprint((string)$handoff['public_id']),
                ];
            } catch (Throwable $e) {
                continue;
            }
        }
        return null;
    }
}

if (!function_exists('tl_stage898_readiness')) {
    function tl_stage898_readiness(): array
    {
        $config = tl_stage898_config();
        $pdo = function_exists('tl_db') ? tl_db() : null;
        $stage896 = function_exists('tl_stage896_summary') ? tl_stage896_summary() : ['ready_to_issue'=>false,'active_pilot_count'=>0,'scheduled_worker_disabled'=>false];
        $evidence = $pdo instanceof PDO ? tl_stage898_stage897_evidence($pdo) : ['found'=>false,'clean'=>false,'fresh'=>false];
        $pause = $pdo instanceof PDO ? tl_stage898_pause_state($pdo) : ['paused'=>false];
        $last = $pdo instanceof PDO ? tl_stage898_last_attempt($pdo) : ['found'=>false,'age_seconds'=>null];
        $lock = tl_stage898_lock_status((string)$config['lock_file']);
        $intervalReady = empty($last['found']) || $last['age_seconds'] === null || (int)$last['age_seconds'] >= (int)$config['min_interval_seconds'];
        $checks = [
            'canary_enabled'=>!empty($config['enabled']),
            'database_ready'=>$pdo instanceof PDO,
            'stage896_ready'=>!empty($stage896['ready_to_issue']),
            'clean_stage897_batch_found'=>!empty($evidence['clean']),
            'clean_stage897_batch_fresh'=>!empty($evidence['fresh']),
            'normal_scheduled_worker_disabled'=>!empty($stage896['scheduled_worker_disabled']),
            'no_active_stage896_pilot'=>(int)($stage896['active_pilot_count'] ?? 0) === 0,
            'pause_not_latched'=>empty($pause['paused']),
            'lock_path_ready'=>!empty($lock['ready']),
            'minimum_interval_elapsed'=>$intervalReady,
        ];
        return [
            'stage'=>'Stage 898 Scheduled Worker Canary & Monitoring v1',
            'ready_to_run'=>count(array_filter($checks)) === count($checks),
            'score'=>(int)round((count(array_filter($checks)) / max(1, count($checks))) * 100),
            'checks'=>$checks,
            'stage897_evidence'=>$evidence,
            'pause'=>$pause,
            'last_attempt'=>$last,
            'lock'=>[
                'ready'=>!empty($lock['ready']),
                'display_name'=>(string)$lock['display_name'],
                'directory_writable'=>!empty($lock['directory_writable']),
                'outside_repository_tree'=>!empty($lock['outside_repository_tree']),
            ],
            'max_value_cents'=>(int)$config['max_value_cents'],
            'min_interval_seconds'=>(int)$config['min_interval_seconds'],
            'stale_after_seconds'=>(int)$config['stale_after_seconds'],
            'normal_worker_disabled'=>!empty($stage896['scheduled_worker_disabled']),
        ];
    }
}

if (!function_exists('tl_stage898_run')) {
    function tl_stage898_run(array $input = []): array
    {
        $mode = strtolower(trim((string)($input['mode'] ?? 'observe')));
        if (!in_array($mode, ['observe','run'], true)) $mode = 'observe';
        if ($mode === 'observe') return tl_stage898_summary();

        $config = tl_stage898_config();
        $actor = max(1, (int)($input['actor_user_id'] ?? $config['actor_user_id']));
        $runId = function_exists('tl_uuid') ? tl_uuid() : bin2hex(random_bytes(16));
        $started = microtime(true);
        $lock = null;
        $pdo = tl_require_db();

        try {
            $readiness = tl_stage898_readiness();
            if (empty($readiness['ready_to_run'])) {
                $result = [
                    'stage'=>'Stage 898 Scheduled Worker Canary & Monitoring v1',
                    'run_id'=>$runId,
                    'status'=>'skipped',
                    'reason'=>'canary_not_ready',
                    'exit_code'=>2,
                    'completed_at'=>gmdate('c'),
                    'readiness_score'=>(int)($readiness['score'] ?? 0),
                ];
                tl_stage898_log($pdo, $actor, 'stage898_worker_canary_skipped', $result);
                return $result;
            }

            $lock = tl_stage898_acquire_lock((string)$config['lock_file'], $runId);
            if (empty($lock['acquired'])) {
                $result = [
                    'stage'=>'Stage 898 Scheduled Worker Canary & Monitoring v1',
                    'run_id'=>$runId,
                    'status'=>'skipped',
                    'reason'=>'canary_overlap_detected',
                    'exit_code'=>2,
                    'completed_at'=>gmdate('c'),
                ];
                tl_stage898_log($pdo, $actor, 'stage898_worker_canary_skipped', $result);
                return $result;
            }

            $readiness = tl_stage898_readiness();
            if (empty($readiness['ready_to_run'])) {
                $result = [
                    'stage'=>'Stage 898 Scheduled Worker Canary & Monitoring v1',
                    'run_id'=>$runId,
                    'status'=>'skipped',
                    'reason'=>'canary_readiness_changed',
                    'exit_code'=>2,
                    'completed_at'=>gmdate('c'),
                ];
                tl_stage898_log($pdo, $actor, 'stage898_worker_canary_skipped', $result);
                return $result;
            }

            $queue = tl_stage898_queue_metrics($pdo);
            $candidate = tl_stage898_candidate($pdo);
            if (!$candidate) {
                $result = [
                    'stage'=>'Stage 898 Scheduled Worker Canary & Monitoring v1',
                    'run_id'=>$runId,
                    'status'=>'idle',
                    'reason'=>'no_eligible_canary_handoff',
                    'exit_code'=>0,
                    'duration_ms'=>(int)round((microtime(true) - $started) * 1000),
                    'completed_at'=>gmdate('c'),
                    'queue'=>$queue,
                ];
                tl_stage898_log($pdo, $actor, 'stage898_worker_canary_idle', [
                    'run_id'=>$runId,
                    'status'=>'idle',
                    'reason'=>'no_eligible_canary_handoff',
                    'exit_code'=>0,
                    'duration_ms'=>$result['duration_ms'],
                    'queue_due'=>(int)($queue['due'] ?? 0),
                    'queue_failed'=>(int)($queue['failed'] ?? 0),
                    'queue_quarantined'=>(int)($queue['quarantined'] ?? 0),
                    'stage897_batch_id'=>(string)($readiness['stage897_evidence']['batch_id'] ?? ''),
                ]);
                return $result;
            }

            $pilot = tl_stage896_run_pilot([
                'handoff_id'=>(string)$candidate['handoff_id'],
                'confirm_microgifter_user_id'=>(string)$candidate['microgifter_user_id'],
                'confirmation_phrase'=>'ISSUE ONE PILOT',
                'actor_user_id'=>$actor,
                'stage898_canary_run_id'=>$runId,
            ]);
            $verified = (string)($pilot['pilot']['pilot_status'] ?? '') === 'verified'
                && !empty($pilot['verification']['confirmed_delivered']);
            $deliveryStatus = (string)($pilot['verification']['delivery_status'] ?? 'unknown');
            $pilotStatus = (string)($pilot['pilot']['pilot_status'] ?? 'unknown');
            $durationMs = (int)round((microtime(true) - $started) * 1000);

            if (!$verified) {
                $result = [
                    'stage'=>'Stage 898 Scheduled Worker Canary & Monitoring v1',
                    'run_id'=>$runId,
                    'status'=>'paused',
                    'pause_reason'=>'canary_delivery_not_verified',
                    'exit_code'=>3,
                    'handoff_id'=>(int)$candidate['handoff_id'],
                    'delivery_status'=>$deliveryStatus,
                    'pilot_status'=>$pilotStatus,
                    'duration_ms'=>$durationMs,
                    'completed_at'=>gmdate('c'),
                ];
                tl_stage898_log($pdo, $actor, 'stage898_worker_canary_paused', [
                    'run_id'=>$runId,
                    'status'=>'paused',
                    'pause_reason'=>'canary_delivery_not_verified',
                    'exit_code'=>3,
                    'handoff_id'=>(int)$candidate['handoff_id'],
                    'reward_event_id'=>(int)$candidate['reward_event_id'],
                    'value_cents'=>(int)$candidate['value_cents'],
                    'delivery_status'=>$deliveryStatus,
                    'pilot_status'=>$pilotStatus,
                    'duration_ms'=>$durationMs,
                    'stage897_batch_id'=>(string)($readiness['stage897_evidence']['batch_id'] ?? ''),
                    'handoff_reference_fingerprint'=>(string)$candidate['handoff_reference_fingerprint'],
                    'microgifter_user_fingerprint'=>(string)$candidate['microgifter_user_fingerprint'],
                    'queue_due'=>(int)($queue['due'] ?? 0),
                    'queue_failed'=>(int)($queue['failed'] ?? 0),
                    'queue_quarantined'=>(int)($queue['quarantined'] ?? 0),
                ], (int)$candidate['reward_event_id']);
                return $result;
            }

            $result = [
                'stage'=>'Stage 898 Scheduled Worker Canary & Monitoring v1',
                'run_id'=>$runId,
                'status'=>'verified',
                'exit_code'=>0,
                'handoff_id'=>(int)$candidate['handoff_id'],
                'delivery_status'=>$deliveryStatus,
                'pilot_status'=>$pilotStatus,
                'value_cents'=>(int)$candidate['value_cents'],
                'currency'=>'USD',
                'duration_ms'=>$durationMs,
                'completed_at'=>gmdate('c'),
                'normal_worker_remained_disabled'=>true,
            ];
            tl_stage898_log($pdo, $actor, 'stage898_worker_canary_completed', [
                'run_id'=>$runId,
                'status'=>'verified',
                'exit_code'=>0,
                'handoff_id'=>(int)$candidate['handoff_id'],
                'reward_event_id'=>(int)$candidate['reward_event_id'],
                'value_cents'=>(int)$candidate['value_cents'],
                'delivery_status'=>$deliveryStatus,
                'pilot_status'=>$pilotStatus,
                'duration_ms'=>$durationMs,
                'stage897_batch_id'=>(string)($readiness['stage897_evidence']['batch_id'] ?? ''),
                'handoff_reference_fingerprint'=>(string)$candidate['handoff_reference_fingerprint'],
                'microgifter_user_fingerprint'=>(string)$candidate['microgifter_user_fingerprint'],
                'queue_due'=>(int)($queue['due'] ?? 0),
                'queue_failed'=>(int)($queue['failed'] ?? 0),
                'queue_quarantined'=>(int)($queue['quarantined'] ?? 0),
            ], (int)$candidate['reward_event_id']);
            return $result;
        } catch (Throwable $e) {
            $durationMs = (int)round((microtime(true) - $started) * 1000);
            $result = [
                'stage'=>'Stage 898 Scheduled Worker Canary & Monitoring v1',
                'run_id'=>$runId,
                'status'=>'failed',
                'pause_reason'=>'canary_exception',
                'exit_code'=>1,
                'duration_ms'=>$durationMs,
                'completed_at'=>gmdate('c'),
                'error_code'=>'stage898_canary_exception',
            ];
            try {
                tl_stage898_log($pdo, $actor, 'stage898_worker_canary_paused', [
                    'run_id'=>$runId,
                    'status'=>'paused',
                    'pause_reason'=>'canary_exception',
                    'exit_code'=>1,
                    'duration_ms'=>$durationMs,
                ]);
            } catch (Throwable $ignored) {
            }
            return $result;
        } finally {
            if (is_array($lock) && !empty($lock['acquired'])) tl_stage898_release_lock($lock['handle'] ?? null);
        }
    }
}

if (!function_exists('tl_stage898_acknowledge_pause')) {
    function tl_stage898_acknowledge_pause(array $input = []): array
    {
        $phrase = trim((string)($input['confirmation_phrase'] ?? ''));
        if (!hash_equals((string)tl_stage898_config()['pause_ack_phrase'], $phrase)) {
            throw new TlHttpException('Enter the exact Stage 898 pause acknowledgement phrase.', 422, 'stage898_acknowledgement_invalid');
        }
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? tl_stage898_config()['actor_user_id']));
        $pdo = tl_require_db();
        $pause = tl_stage898_pause_state($pdo);
        if (empty($pause['paused'])) {
            throw new TlHttpException('There is no Stage 898 canary pause awaiting acknowledgement.', 404, 'stage898_pause_missing');
        }
        if (function_exists('tl_stage896_active_pilots') && tl_stage896_active_pilots($pdo)) {
            throw new TlHttpException('Resolve the active Stage 896 pilot before acknowledging the canary pause.', 409, 'stage898_active_pilot_unresolved');
        }
        tl_stage898_log($pdo, $actor, 'stage898_worker_canary_pause_acknowledged', [
            'run_id'=>(string)$pause['run_id'],
            'status'=>'acknowledged',
            'reason'=>(string)$pause['reason'],
            'exit_code'=>0,
        ]);
        return ['acknowledged'=>true,'run_id'=>(string)$pause['run_id'],'ready_for_next_canary'=>true];
    }
}
