<?php
declare(strict_types=1);

/** Stage 899 readiness, candidate selection, limited execution, and suspension. */
require_once __DIR__ . '/training-lab-stage899-config.php';

if (!function_exists('tl_stage899_candidates')) {
    function tl_stage899_candidates(PDO $pdo): array
    {
        $config = tl_stage899_config();
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
                LIMIT 50";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$maxAttempts,(int)$config['max_item_value_cents']]);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable $e) {
            return ['items'=>[],'selected_count'=>0,'total_value_cents'=>0,'currency'=>'USD'];
        }

        $items = [];
        $total = 0;
        foreach ($ids as $id) {
            if (count($items) >= (int)$config['max_batch_size']) break;
            try {
                $context = tl_stage896_load_context($pdo, (string)$id, false);
                $handoff = (array)$context['handoff'];
                $reward = (array)$context['reward'];
                $link = is_array($context['link'] ?? null) ? (array)$context['link'] : [];
                $linkedUserId = trim((string)($link['microgifter_user_id'] ?? ''));
                $handoffUserId = trim((string)($handoff['microgifter_user_id'] ?? ''));
                $value = (int)($reward['value_cents'] ?? 0);
                if ($linkedUserId === '' || !ctype_digit($linkedUserId) || (int)$linkedUserId < 1) continue;
                if ($handoffUserId !== '' && !hash_equals($linkedUserId, $handoffUserId)) continue;
                if ($value < 0 || $value > (int)$config['max_item_value_cents']) continue;
                if (($total + $value) > (int)$config['max_total_value_cents']) continue;
                $total += $value;
                $items[] = [
                    'sequence'=>count($items) + 1,
                    'handoff_id'=>(int)$handoff['id'],
                    'handoff_public_id'=>(string)$handoff['public_id'],
                    'reward_event_id'=>(int)$reward['id'],
                    'reward_public_id'=>(string)$reward['public_id'],
                    'value_cents'=>$value,
                    'currency'=>(string)$reward['currency'],
                    'microgifter_user_id'=>$linkedUserId,
                    'microgifter_user_fingerprint'=>tl_stage899_fingerprint($linkedUserId),
                    'handoff_reference_fingerprint'=>tl_stage899_fingerprint((string)$handoff['public_id']),
                ];
            } catch (Throwable $e) {
                continue;
            }
        }
        return ['items'=>$items,'selected_count'=>count($items),'total_value_cents'=>$total,'currency'=>'USD'];
    }
}

if (!function_exists('tl_stage899_run_metrics')) {
    function tl_stage899_run_metrics(array $runs): array
    {
        $metrics = ['window'=>count($runs),'completed'=>0,'idle'=>0,'suspended'=>0,'skipped'=>0,'attempted'=>0,'success_rate'=>null,'verified_items'=>0,'processed_items'=>0];
        foreach ($runs as $run) {
            $type = (string)($run['event_type'] ?? '');
            $data = is_array($run['run'] ?? null) ? (array)$run['run'] : [];
            if ($type === 'stage899_limited_processing_completed') {
                $metrics['completed']++;
                $metrics['attempted']++;
                $metrics['verified_items'] += (int)($data['verified_count'] ?? 0);
                $metrics['processed_items'] += (int)($data['processed_count'] ?? 0);
            } elseif ($type === 'stage899_limited_processing_suspended') {
                $metrics['suspended']++;
                $metrics['attempted']++;
                $metrics['verified_items'] += (int)($data['verified_count'] ?? 0);
                $metrics['processed_items'] += (int)($data['processed_count'] ?? 0);
            } elseif ($type === 'stage899_limited_processing_idle') {
                $metrics['idle']++;
            } elseif ($type === 'stage899_limited_processing_skipped') {
                $metrics['skipped']++;
            }
        }
        if ($metrics['attempted'] > 0) {
            $metrics['success_rate'] = (int)round(($metrics['completed'] / $metrics['attempted']) * 100);
        }
        return $metrics;
    }
}

if (!function_exists('tl_stage899_rolling_health')) {
    function tl_stage899_rolling_health(PDO $pdo): array
    {
        $config = tl_stage899_config();
        $ackId = 0;
        try {
            $ack = $pdo->query("SELECT id FROM training_events WHERE event_type='stage899_limited_processing_suspension_acknowledged' ORDER BY id DESC LIMIT 1");
            $ackId = $ack ? (int)$ack->fetchColumn() : 0;
            $stmt = $pdo->prepare("SELECT public_id,event_type,metadata_json,created_at FROM training_events WHERE id>? AND event_type IN ('stage899_limited_processing_completed','stage899_limited_processing_suspended') ORDER BY id DESC LIMIT " . (int)$config['health_window']);
            $stmt->execute([$ackId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return ['healthy'=>false,'query_ready'=>false,'success_rate'=>0,'attempted'=>0,'completed'=>0,'suspended'=>0,'window'=>0,'reset_event_id'=>$ackId];
        }
        $runs = array_map(static function (array $row): array {
            return [
                'event_id'=>(string)($row['public_id'] ?? ''),
                'event_type'=>(string)($row['event_type'] ?? ''),
                'created_at'=>(string)($row['created_at'] ?? ''),
                'run'=>tl_stage899_json($row['metadata_json'] ?? null),
            ];
        }, $rows);
        $metrics = tl_stage899_run_metrics($runs);
        $rateReady = $metrics['success_rate'] === null
            || (int)$metrics['success_rate'] >= (int)$config['min_success_rate_percent'];
        return $metrics + [
            'healthy'=>$rateReady && (int)$metrics['suspended'] === 0,
            'query_ready'=>true,
            'minimum_success_rate'=>(int)$config['min_success_rate_percent'],
            'reset_event_id'=>$ackId,
        ];
    }
}

if (!function_exists('tl_stage899_readiness')) {
    function tl_stage899_readiness(): array
    {
        $config = tl_stage899_config();
        $pdo = function_exists('tl_db') ? tl_db() : null;
        $stage896 = function_exists('tl_stage896_summary') ? tl_stage896_summary() : ['ready_to_issue'=>false,'active_pilot_count'=>0,'scheduled_worker_disabled'=>false];
        $graduation = $pdo instanceof PDO ? tl_stage899_canary_graduation($pdo) : ['graduated'=>false];
        $stage898Pause = $pdo instanceof PDO ? tl_stage898_pause_state($pdo) : ['paused'=>false];
        $suspension = $pdo instanceof PDO ? tl_stage899_suspension_state($pdo) : ['suspended'=>false];
        $last = $pdo instanceof PDO ? tl_stage899_last_attempt($pdo) : ['found'=>false,'age_seconds'=>null];
        $queue = $pdo instanceof PDO ? tl_stage898_queue_metrics($pdo) : [];
        $rolling = $pdo instanceof PDO ? tl_stage899_rolling_health($pdo) : ['healthy'=>false,'query_ready'=>false];
        $lock = tl_stage899_lock_status((string)$config['lock_file']);
        $intervalReady = empty($last['found']) || $last['age_seconds'] === null || (int)$last['age_seconds'] >= (int)$config['min_interval_seconds'];
        $stage897Disabled = function_exists('tl_stage897_config') ? empty(tl_stage897_config()['enabled']) : false;
        $stage898Disabled = function_exists('tl_stage898_config') ? empty(tl_stage898_config()['enabled']) : false;
        $checks = [
            'limited_scheduler_enabled'=>!empty($config['enabled']),
            'database_ready'=>$pdo instanceof PDO,
            'stage896_ready'=>!empty($stage896['ready_to_issue']),
            'stage898_canaries_graduated'=>!empty($graduation['graduated']),
            'stage898_canary_disabled'=>$stage898Disabled,
            'stage898_pause_cleared'=>empty($stage898Pause['paused']),
            'stage897_manual_batch_disabled'=>$stage897Disabled,
            'normal_stage892_worker_disabled'=>!empty($stage896['scheduled_worker_disabled']),
            'no_active_stage896_pilot'=>(int)($stage896['active_pilot_count'] ?? 0) === 0,
            'limited_scheduler_not_suspended'=>empty($suspension['suspended']),
            'rolling_success_threshold_met'=>!empty($rolling['healthy']),
            'no_quarantined_handoffs'=>(int)($queue['quarantined'] ?? 0) === 0,
            'lock_path_ready'=>!empty($lock['ready']),
            'minimum_interval_elapsed'=>$intervalReady,
        ];
        return [
            'stage'=>'Stage 899 Canary Graduation & Limited Scheduled Processing v1',
            'ready_to_run'=>count(array_filter($checks)) === count($checks),
            'score'=>(int)round((count(array_filter($checks)) / max(1, count($checks))) * 100),
            'checks'=>$checks,
            'graduation'=>$graduation,
            'rolling_health'=>$rolling,
            'stage898_pause'=>$stage898Pause,
            'suspension'=>$suspension,
            'last_attempt'=>$last,
            'queue'=>$queue,
            'lock'=>[
                'ready'=>!empty($lock['ready']),
                'display_name'=>(string)($lock['display_name'] ?? ''),
                'directory_writable'=>!empty($lock['directory_writable']),
                'outside_repository_tree'=>!empty($lock['outside_repository_tree']),
            ],
            'max_batch_size'=>(int)$config['max_batch_size'],
            'max_item_value_cents'=>(int)$config['max_item_value_cents'],
            'max_total_value_cents'=>(int)$config['max_total_value_cents'],
            'min_interval_seconds'=>(int)$config['min_interval_seconds'],
            'max_runtime_seconds'=>(int)$config['max_runtime_seconds'],
            'normal_worker_disabled'=>!empty($stage896['scheduled_worker_disabled']),
            'stage897_disabled'=>$stage897Disabled,
            'stage898_disabled'=>$stage898Disabled,
        ];
    }
}

if (!function_exists('tl_stage899_suspend')) {
    function tl_stage899_suspend(PDO $pdo, int $actor, array $metadata, ?int $subjectId = null): array
    {
        $metadata['status'] = 'suspended';
        $metadata['severity'] = (string)($metadata['severity'] ?? 'critical');
        $metadata['exit_code'] = (int)($metadata['exit_code'] ?? 3);
        tl_stage899_log($pdo, $actor, 'stage899_limited_processing_suspended', $metadata, $subjectId);
        tl_stage899_log($pdo, $actor, 'stage899_limited_processing_escalated', $metadata, $subjectId);
        return $metadata;
    }
}

if (!function_exists('tl_stage899_run')) {
    function tl_stage899_run(array $input = []): array
    {
        $mode = strtolower(trim((string)($input['mode'] ?? 'observe')));
        if (!in_array($mode, ['observe','run'], true)) $mode = 'observe';
        if ($mode === 'observe') return tl_stage899_summary();

        $config = tl_stage899_config();
        $actor = max(1, (int)($input['actor_user_id'] ?? $config['actor_user_id']));
        $runId = function_exists('tl_uuid') ? tl_uuid() : bin2hex(random_bytes(16));
        $started = microtime(true);
        $lock = null;
        $pdo = tl_require_db();

        try {
            if (empty($input['explicit_run'])) {
                $result = ['stage'=>'Stage 899 Canary Graduation & Limited Scheduled Processing v1','run_id'=>$runId,'status'=>'skipped','reason'=>'explicit_run_flag_required','exit_code'=>2,'completed_at'=>gmdate('c')];
                tl_stage899_log($pdo, $actor, 'stage899_limited_processing_skipped', $result);
                return $result;
            }
            $readiness = tl_stage899_readiness();
            if (empty($readiness['ready_to_run'])) {
                $result = ['stage'=>'Stage 899 Canary Graduation & Limited Scheduled Processing v1','run_id'=>$runId,'status'=>'skipped','reason'=>'limited_scheduler_not_ready','exit_code'=>2,'readiness_score'=>(int)($readiness['score'] ?? 0),'completed_at'=>gmdate('c')];
                tl_stage899_log($pdo, $actor, 'stage899_limited_processing_skipped', $result);
                return $result;
            }

            $lock = tl_stage899_acquire_lock((string)$config['lock_file'], $runId);
            if (empty($lock['acquired'])) {
                $result = ['stage'=>'Stage 899 Canary Graduation & Limited Scheduled Processing v1','run_id'=>$runId,'status'=>'skipped','reason'=>'limited_scheduler_overlap_detected','exit_code'=>2,'completed_at'=>gmdate('c')];
                tl_stage899_log($pdo, $actor, 'stage899_limited_processing_skipped', $result);
                return $result;
            }

            $readiness = tl_stage899_readiness();
            if (empty($readiness['ready_to_run'])) {
                $result = ['stage'=>'Stage 899 Canary Graduation & Limited Scheduled Processing v1','run_id'=>$runId,'status'=>'skipped','reason'=>'limited_scheduler_readiness_changed','exit_code'=>2,'completed_at'=>gmdate('c')];
                tl_stage899_log($pdo, $actor, 'stage899_limited_processing_skipped', $result);
                return $result;
            }

            $plan = tl_stage899_candidates($pdo);
            $queue = tl_stage898_queue_metrics($pdo);
            if (empty($plan['items'])) {
                $result = [
                    'stage'=>'Stage 899 Canary Graduation & Limited Scheduled Processing v1',
                    'run_id'=>$runId,
                    'status'=>'idle',
                    'reason'=>'no_eligible_limited_processing_handoffs',
                    'exit_code'=>0,
                    'selected_count'=>0,
                    'processed_count'=>0,
                    'verified_count'=>0,
                    'duration_ms'=>(int)round((microtime(true) - $started) * 1000),
                    'completed_at'=>gmdate('c'),
                    'queue'=>$queue,
                ];
                tl_stage899_log($pdo, $actor, 'stage899_limited_processing_idle', [
                    'run_id'=>$runId,'status'=>'idle','reason'=>$result['reason'],'exit_code'=>0,'duration_ms'=>$result['duration_ms'],
                    'queue_due'=>(int)($queue['due'] ?? 0),'queue_failed'=>(int)($queue['failed'] ?? 0),'queue_quarantined'=>(int)($queue['quarantined'] ?? 0),
                    'graduated_canary_count'=>(int)($readiness['graduation']['verified_count'] ?? 0),
                ]);
                return $result;
            }

            tl_stage899_log($pdo, $actor, 'stage899_limited_processing_started', [
                'run_id'=>$runId,'status'=>'running','selected_count'=>(int)$plan['selected_count'],'total_value_cents'=>(int)$plan['total_value_cents'],
                'graduated_canary_count'=>(int)($readiness['graduation']['verified_count'] ?? 0),
                'queue_due'=>(int)($queue['due'] ?? 0),'queue_failed'=>(int)($queue['failed'] ?? 0),'queue_quarantined'=>(int)($queue['quarantined'] ?? 0),
            ]);

            $results = [];
            $verifiedCount = 0;
            $deadline = $started + (int)$config['max_runtime_seconds'];
            foreach ((array)$plan['items'] as $item) {
                if (microtime(true) >= $deadline) {
                    $metadata = tl_stage899_suspend($pdo, $actor, [
                        'run_id'=>$runId,'suspension_reason'=>'runtime_limit_reached_before_next_item','severity'=>'high','exit_code'=>3,
                        'selected_count'=>(int)$plan['selected_count'],'processed_count'=>count($results),'verified_count'=>$verifiedCount,
                        'total_value_cents'=>(int)$plan['total_value_cents'],'duration_ms'=>(int)round((microtime(true) - $started) * 1000),
                    ]);
                    return $metadata + ['stage'=>'Stage 899 Canary Graduation & Limited Scheduled Processing v1','results'=>$results,'completed_at'=>gmdate('c')];
                }

                $itemStarted = microtime(true);
                try {
                    $pilot = tl_stage896_run_pilot([
                        'handoff_id'=>(string)$item['handoff_id'],
                        'confirm_microgifter_user_id'=>(string)$item['microgifter_user_id'],
                        'confirmation_phrase'=>'ISSUE ONE PILOT',
                        'actor_user_id'=>$actor,
                        'stage899_run_id'=>$runId,
                    ]);
                    $verified = (string)($pilot['pilot']['pilot_status'] ?? '') === 'verified'
                        && !empty($pilot['verification']['confirmed_delivered']);
                    $deliveryStatus = (string)($pilot['verification']['delivery_status'] ?? 'unknown');
                    $pilotStatus = (string)($pilot['pilot']['pilot_status'] ?? 'unknown');
                    $itemResult = [
                        'sequence'=>(int)$item['sequence'],
                        'handoff_id'=>(int)$item['handoff_id'],
                        'status'=>$verified ? 'verified' : 'not_verified',
                        'delivery_status'=>$deliveryStatus,
                        'pilot_status'=>$pilotStatus,
                        'verified'=>$verified,
                        'duration_ms'=>(int)round((microtime(true) - $itemStarted) * 1000),
                    ];
                    $results[] = $itemResult;
                    tl_stage899_log($pdo, $actor, $verified ? 'stage899_limited_processing_item_verified' : 'stage899_limited_processing_item_unverified', [
                        'run_id'=>$runId,'status'=>$itemResult['status'],'sequence'=>(int)$item['sequence'],'handoff_id'=>(int)$item['handoff_id'],
                        'reward_event_id'=>(int)$item['reward_event_id'],'value_cents'=>(int)$item['value_cents'],'delivery_status'=>$deliveryStatus,
                        'pilot_status'=>$pilotStatus,'duration_ms'=>(int)$itemResult['duration_ms'],
                        'handoff_reference_fingerprint'=>(string)$item['handoff_reference_fingerprint'],
                        'microgifter_user_fingerprint'=>(string)$item['microgifter_user_fingerprint'],
                    ], (int)$item['reward_event_id']);
                    if (!$verified) {
                        $metadata = tl_stage899_suspend($pdo, $actor, [
                            'run_id'=>$runId,'suspension_reason'=>'scheduled_item_not_verified','severity'=>'critical','exit_code'=>3,
                            'selected_count'=>(int)$plan['selected_count'],'processed_count'=>count($results),'verified_count'=>$verifiedCount,
                            'sequence'=>(int)$item['sequence'],'handoff_id'=>(int)$item['handoff_id'],'reward_event_id'=>(int)$item['reward_event_id'],
                            'value_cents'=>(int)$item['value_cents'],'total_value_cents'=>(int)$plan['total_value_cents'],
                            'delivery_status'=>$deliveryStatus,'pilot_status'=>$pilotStatus,
                            'handoff_reference_fingerprint'=>(string)$item['handoff_reference_fingerprint'],
                            'microgifter_user_fingerprint'=>(string)$item['microgifter_user_fingerprint'],
                            'duration_ms'=>(int)round((microtime(true) - $started) * 1000),
                        ], (int)$item['reward_event_id']);
                        return $metadata + ['stage'=>'Stage 899 Canary Graduation & Limited Scheduled Processing v1','results'=>$results,'completed_at'=>gmdate('c')];
                    }
                    $verifiedCount++;
                } catch (Throwable $e) {
                    $results[] = ['sequence'=>(int)$item['sequence'],'handoff_id'=>(int)$item['handoff_id'],'status'=>'error','delivery_status'=>'unknown','pilot_status'=>'error','verified'=>false];
                    $metadata = tl_stage899_suspend($pdo, $actor, [
                        'run_id'=>$runId,'suspension_reason'=>'scheduled_item_exception','severity'=>'critical','exit_code'=>1,
                        'selected_count'=>(int)$plan['selected_count'],'processed_count'=>count($results),'verified_count'=>$verifiedCount,
                        'sequence'=>(int)$item['sequence'],'handoff_id'=>(int)$item['handoff_id'],'reward_event_id'=>(int)$item['reward_event_id'],
                        'value_cents'=>(int)$item['value_cents'],'total_value_cents'=>(int)$plan['total_value_cents'],
                        'delivery_status'=>'unknown','pilot_status'=>'error',
                        'handoff_reference_fingerprint'=>(string)$item['handoff_reference_fingerprint'],
                        'microgifter_user_fingerprint'=>(string)$item['microgifter_user_fingerprint'],
                        'duration_ms'=>(int)round((microtime(true) - $started) * 1000),
                    ], (int)$item['reward_event_id']);
                    return $metadata + ['stage'=>'Stage 899 Canary Graduation & Limited Scheduled Processing v1','results'=>$results,'completed_at'=>gmdate('c')];
                }
            }

            $durationMs = (int)round((microtime(true) - $started) * 1000);
            $result = [
                'stage'=>'Stage 899 Canary Graduation & Limited Scheduled Processing v1',
                'run_id'=>$runId,
                'status'=>'completed',
                'exit_code'=>0,
                'selected_count'=>(int)$plan['selected_count'],
                'processed_count'=>count($results),
                'verified_count'=>$verifiedCount,
                'total_value_cents'=>(int)$plan['total_value_cents'],
                'currency'=>'USD',
                'duration_ms'=>$durationMs,
                'completed_at'=>gmdate('c'),
                'results'=>$results,
                'normal_worker_remained_disabled'=>true,
                'stage898_canary_remained_disabled'=>true,
            ];
            $rolling = tl_stage899_rolling_health($pdo);
            tl_stage899_log($pdo, $actor, 'stage899_limited_processing_completed', [
                'run_id'=>$runId,'status'=>'completed','exit_code'=>0,'selected_count'=>(int)$plan['selected_count'],
                'processed_count'=>count($results),'verified_count'=>$verifiedCount,'total_value_cents'=>(int)$plan['total_value_cents'],
                'duration_ms'=>$durationMs,'rolling_success_rate'=>(int)($rolling['success_rate'] ?? 100),
                'graduated_canary_count'=>(int)($readiness['graduation']['verified_count'] ?? 0),
                'queue_due'=>(int)($queue['due'] ?? 0),'queue_failed'=>(int)($queue['failed'] ?? 0),'queue_quarantined'=>(int)($queue['quarantined'] ?? 0),
            ]);
            return $result;
        } catch (Throwable $e) {
            $metadata = [
                'run_id'=>$runId,'suspension_reason'=>'limited_scheduler_exception','severity'=>'critical','exit_code'=>1,
                'duration_ms'=>(int)round((microtime(true) - $started) * 1000),
            ];
            try {
                $metadata = tl_stage899_suspend($pdo, $actor, $metadata);
            } catch (Throwable $ignored) {
                $metadata['status'] = 'failed';
            }
            return $metadata + ['stage'=>'Stage 899 Canary Graduation & Limited Scheduled Processing v1','error_code'=>'stage899_scheduler_exception','completed_at'=>gmdate('c')];
        } finally {
            if (is_array($lock) && !empty($lock['acquired'])) tl_stage899_release_lock($lock['handle'] ?? null);
        }
    }
}

if (!function_exists('tl_stage899_acknowledge_suspension')) {
    function tl_stage899_acknowledge_suspension(array $input = []): array
    {
        $phrase = trim((string)($input['confirmation_phrase'] ?? ''));
        if (!hash_equals((string)tl_stage899_config()['suspension_ack_phrase'], $phrase)) {
            throw new TlHttpException('Enter the exact Stage 899 suspension acknowledgement phrase.', 422, 'stage899_acknowledgement_invalid');
        }
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? tl_stage899_config()['actor_user_id']));
        $pdo = tl_require_db();
        $suspension = tl_stage899_suspension_state($pdo);
        if (empty($suspension['suspended'])) {
            throw new TlHttpException('There is no Stage 899 suspension awaiting acknowledgement.', 404, 'stage899_suspension_missing');
        }
        if (function_exists('tl_stage896_active_pilots') && tl_stage896_active_pilots($pdo)) {
            throw new TlHttpException('Resolve the active Stage 896 pilot before acknowledging the Stage 899 suspension.', 409, 'stage899_active_pilot_unresolved');
        }
        $queue = tl_stage898_queue_metrics($pdo);
        if ((int)($queue['quarantined'] ?? 0) > 0) {
            throw new TlHttpException('Resolve quarantined handoffs before acknowledging the Stage 899 suspension.', 409, 'stage899_quarantine_unresolved');
        }
        $stage898Pause = tl_stage898_pause_state($pdo);
        if (!empty($stage898Pause['paused'])) {
            throw new TlHttpException('Clear the Stage 898 canary pause before acknowledging Stage 899.', 409, 'stage899_canary_pause_unresolved');
        }
        tl_stage899_log($pdo, $actor, 'stage899_limited_processing_suspension_acknowledged', [
            'run_id'=>(string)$suspension['run_id'],
            'status'=>'acknowledged',
            'reason'=>(string)$suspension['reason'],
            'severity'=>(string)$suspension['severity'],
            'exit_code'=>0,
            'queue_quarantined'=>0,
        ]);
        return ['acknowledged'=>true,'run_id'=>(string)$suspension['run_id'],'rolling_health_reset'=>true,'ready_for_reassessment'=>true];
    }
}
