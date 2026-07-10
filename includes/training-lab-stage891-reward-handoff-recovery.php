<?php
/**
 * Stage 891 — Reward Handoff Recovery + Operational Acceptance v1.
 *
 * Adds lease recovery, audited operator requeue, resilient batch processing,
 * and read-only consistency acceptance on top of the Stage 890 outbox.
 * No new database table is required.
 */
require_once __DIR__ . '/training-lab-stage890-reward-handoff-outbox.php';

if (!function_exists('tl_stage891_config')) {
    function tl_stage891_config(): array
    {
        $root = function_exists('tl_security_config') ? tl_security_config() : [];
        $lease = getenv('TL_REWARD_HANDOFF_LEASE_SECONDS');
        $batch = getenv('TL_REWARD_HANDOFF_RECOVERY_BATCH_SIZE');
        return [
            'lease_seconds' => max(60, min(3600, (int)($lease !== false && $lease !== '' ? $lease : ($root['reward_handoff_lease_seconds'] ?? 300)))),
            'recovery_batch_size' => max(1, min(100, (int)($batch !== false && $batch !== '' ? $batch : ($root['reward_handoff_recovery_batch_size'] ?? 25)))),
        ];
    }
}

if (!function_exists('tl_stage891_is_stale_lock')) {
    function tl_stage891_is_stale_lock(?string $lockedAt, int $leaseSeconds, ?int $now = null): bool
    {
        $lockedAt = trim((string)$lockedAt);
        if ($lockedAt === '') return false;
        $timestamp = strtotime($lockedAt . (str_contains($lockedAt, 'T') || str_ends_with($lockedAt, 'Z') ? '' : ' UTC'));
        if ($timestamp === false) return false;
        $now = $now ?? time();
        return $timestamp <= ($now - max(60, $leaseSeconds));
    }
}

if (!function_exists('tl_stage891_is_terminal_failure')) {
    function tl_stage891_is_terminal_failure(array $handoff, ?int $maxAttempts = null): bool
    {
        $maxAttempts = $maxAttempts ?? (int)tl_stage890_config()['max_attempts'];
        return (string)($handoff['handoff_status'] ?? '') === 'failed'
            && (int)($handoff['attempt_count'] ?? 0) >= max(1, $maxAttempts)
            && empty($handoff['next_attempt_at']);
    }
}

if (!function_exists('tl_stage891_merge_metadata')) {
    function tl_stage891_merge_metadata($value, array $patch): string
    {
        $metadata = function_exists('tl_stage890_json_decode') ? tl_stage890_json_decode($value) : [];
        $history = is_array($metadata['stage891_recovery_history'] ?? null) ? $metadata['stage891_recovery_history'] : [];
        if (isset($patch['history_event']) && is_array($patch['history_event'])) {
            $history[] = $patch['history_event'];
            $history = array_slice($history, -20);
            unset($patch['history_event']);
        }
        $metadata['stage891'] = array_merge((array)($metadata['stage891'] ?? []), $patch);
        $metadata['stage891_recovery_history'] = $history;
        return tl_stage890_json($metadata);
    }
}

if (!function_exists('tl_stage891_recover_stale_processing')) {
    function tl_stage891_recover_stale_processing(array $input = []): array
    {
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $pdo = tl_require_db();
        $config = tl_stage891_config();
        $stage890 = tl_stage890_config();
        $limit = max(1, min(100, (int)($input['limit'] ?? $config['recovery_batch_size'])));
        $lease = (int)$config['lease_seconds'];
        $cutoff = gmdate('Y-m-d H:i:s', time() - $lease);
        $actor = (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM training_reward_handoffs WHERE handoff_status='processing' AND locked_at IS NOT NULL AND locked_at <= ? ORDER BY locked_at ASC, id ASC LIMIT " . $limit . ' FOR UPDATE');
            $stmt->execute([$cutoff]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $recovered = [];
            $terminal = 0;
            foreach ($rows as $row) {
                $attempts = (int)$row['attempt_count'];
                $isTerminal = $attempts >= (int)$stage890['max_attempts'];
                $code = $isTerminal ? 'worker_lease_expired_terminal' : 'worker_lease_expired_recovered';
                $message = $isTerminal
                    ? 'Worker lease expired after the maximum delivery attempts. Operator review is required.'
                    : 'Worker lease expired before delivery confirmation. The handoff was safely returned to the retry queue.';
                $nextAttempt = $isTerminal ? null : gmdate('Y-m-d H:i:s');
                $metadata = tl_stage891_merge_metadata($row['metadata_json'] ?? null, [
                    'last_recovery_at' => gmdate('c'),
                    'last_recovery_code' => $code,
                    'lease_seconds' => $lease,
                    'recovered_by_user_id' => $actor,
                    'history_event' => [
                        'event' => 'stale_processing_recovered',
                        'at' => gmdate('c'),
                        'attempt_count' => $attempts,
                        'terminal' => $isTerminal,
                        'previous_locked_by' => (string)($row['locked_by'] ?? ''),
                    ],
                ]);
                $update = $pdo->prepare("UPDATE training_reward_handoffs SET handoff_status='failed', failure_code=?, failure_message=?, next_attempt_at=?, locked_at=NULL, locked_by=NULL, metadata_json=? WHERE id=? AND handoff_status='processing'");
                $update->execute([$code, $message, $nextAttempt, $metadata, (int)$row['id']]);
                if ($update->rowCount() > 0) {
                    if ($isTerminal) $terminal++;
                    $recovered[] = [
                        'handoff_id' => (int)$row['id'],
                        'public_id' => (string)$row['public_id'],
                        'attempt_count' => $attempts,
                        'terminal' => $isTerminal,
                        'next_attempt_at' => $nextAttempt,
                    ];
                    if (!empty($row['reward_event_id'])) {
                        tl_log_event($pdo, $actor, 'reward_event', (int)$row['reward_event_id'], 'stage891_stale_handoff_recovered', [
                            'handoff_id' => (int)$row['id'],
                            'attempt_count' => $attempts,
                            'terminal' => $isTerminal,
                            'lease_seconds' => $lease,
                        ]);
                    }
                }
            }
            $pdo->commit();
            return [
                'cutoff_utc' => $cutoff,
                'lease_seconds' => $lease,
                'selected' => count($rows),
                'recovered' => count($recovered),
                'terminal_failures' => $terminal,
                'rows' => $recovered,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_stage891_requeue_handoff')) {
    function tl_stage891_requeue_handoff(array $input): array
    {
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $pdo = tl_require_db();
        $ref = trim((string)($input['handoff_id'] ?? $input['public_id'] ?? ''));
        if ($ref === '') throw new TlHttpException('Handoff reference is required.', 422, 'handoff_required');
        $reason = trim((string)($input['requeue_reason'] ?? 'Operator requeued the handoff after reviewing the failure.'));
        $reason = mb_substr($reason !== '' ? $reason : 'Operator requeue.', 0, 500);
        $actor = (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM training_reward_handoffs WHERE public_id=? OR id=? LIMIT 1 FOR UPDATE');
            $stmt->execute([$ref, ctype_digit($ref) ? (int)$ref : 0]);
            $handoff = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$handoff) throw new TlHttpException('Reward handoff was not found.', 404, 'handoff_not_found');
            $status = (string)$handoff['handoff_status'];
            if (in_array($status, ['delivered','cancelled','processing'], true)) {
                throw new TlHttpException('Only failed, blocked, or queued handoffs can be manually requeued.', 409, 'handoff_not_requeueable');
            }
            $reward = tl_stage890_load_reward($pdo, (string)$handoff['reward_event_id']);
            if (in_array((string)$reward['status'], ['issued','linked','cancelled'], true)) {
                throw new TlHttpException('The underlying reward is already delivered or cancelled.', 409, 'reward_not_requeueable');
            }
            $metadata = tl_stage891_merge_metadata($handoff['metadata_json'] ?? null, [
                'last_manual_requeue_at' => gmdate('c'),
                'last_manual_requeue_by_user_id' => $actor,
                'last_manual_requeue_reason' => $reason,
                'manual_requeue_count' => (int)(tl_stage890_json_decode($handoff['metadata_json'] ?? null)['stage891']['manual_requeue_count'] ?? 0) + 1,
                'history_event' => [
                    'event' => 'operator_requeue',
                    'at' => gmdate('c'),
                    'previous_status' => $status,
                    'previous_attempt_count' => (int)$handoff['attempt_count'],
                    'reason' => $reason,
                ],
            ]);
            $update = $pdo->prepare("UPDATE training_reward_handoffs SET handoff_status='queued', attempt_count=0, next_attempt_at=NULL, locked_at=NULL, locked_by=NULL, failure_code='operator_requeued', failure_message=?, metadata_json=? WHERE id=?");
            $update->execute([$reason, $metadata, (int)$handoff['id']]);
            $rewardMetadata = tl_stage890_json_decode($reward['metadata_json'] ?? null);
            $rewardMetadata['claim_status'] = 'queued_for_microgifter_issue';
            $rewardMetadata['stage891_requeue'] = [
                'handoff_id' => (int)$handoff['id'],
                'requeued_at' => gmdate('c'),
                'requeued_by_user_id' => $actor,
                'reason' => $reason,
            ];
            $rewardUpdate = $pdo->prepare("UPDATE training_reward_events SET status='queued', failure_message=NULL, metadata_json=? WHERE id=? AND status NOT IN ('issued','linked','cancelled')");
            $rewardUpdate->execute([tl_stage890_json($rewardMetadata), (int)$reward['id']]);
            tl_log_event($pdo, $actor, 'reward_event', (int)$reward['id'], 'stage891_handoff_requeued', [
                'handoff_id' => (int)$handoff['id'],
                'previous_status' => $status,
                'previous_attempt_count' => (int)$handoff['attempt_count'],
                'reason' => $reason,
            ]);
            $pdo->commit();
            return [
                'handoff_id' => (int)$handoff['id'],
                'public_id' => (string)$handoff['public_id'],
                'handoff_status' => 'queued',
                'attempt_count' => 0,
                'operator_requeued' => true,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_stage891_scalar')) {
    function tl_stage891_scalar(PDO $pdo, string $sql, array $params = []): int
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return -1;
        }
    }
}

if (!function_exists('tl_stage891_consistency_findings')) {
    function tl_stage891_consistency_findings(): array
    {
        if (!tl_stage890_table_ready()) {
            return [
                'query_ok' => false,
                'stale_processing' => -1,
                'terminal_failures' => -1,
                'orphan_handoffs' => -1,
                'delivered_reward_mismatch' => -1,
                'duplicate_idempotency_keys' => -1,
                'duplicate_external_references' => -1,
            ];
        }
        $pdo = tl_db();
        if (!$pdo) return ['query_ok'=>false];
        $config = tl_stage891_config();
        $stage890 = tl_stage890_config();
        $cutoff = gmdate('Y-m-d H:i:s', time() - (int)$config['lease_seconds']);
        $findings = [
            'query_ok' => true,
            'stale_processing' => tl_stage891_scalar($pdo, "SELECT COUNT(*) FROM training_reward_handoffs WHERE handoff_status='processing' AND locked_at IS NOT NULL AND locked_at <= ?", [$cutoff]),
            'terminal_failures' => tl_stage891_scalar($pdo, "SELECT COUNT(*) FROM training_reward_handoffs WHERE handoff_status='failed' AND attempt_count >= ? AND next_attempt_at IS NULL", [(int)$stage890['max_attempts']]),
            'orphan_handoffs' => tl_stage891_scalar($pdo, 'SELECT COUNT(*) FROM training_reward_handoffs h LEFT JOIN training_reward_events re ON re.id=h.reward_event_id WHERE re.id IS NULL'),
            'delivered_reward_mismatch' => tl_stage891_scalar($pdo, "SELECT COUNT(*) FROM training_reward_handoffs h JOIN training_reward_events re ON re.id=h.reward_event_id WHERE h.handoff_status='delivered' AND re.status NOT IN ('issued','linked')"),
            'duplicate_idempotency_keys' => tl_stage891_scalar($pdo, 'SELECT COUNT(*) FROM (SELECT idempotency_key FROM training_reward_handoffs GROUP BY idempotency_key HAVING COUNT(*) > 1) d'),
            'duplicate_external_references' => tl_stage891_scalar($pdo, "SELECT COUNT(*) FROM (SELECT external_reference FROM training_reward_handoffs WHERE handoff_status='delivered' AND external_reference IS NOT NULL AND external_reference<>'' GROUP BY external_reference HAVING COUNT(*) > 1) d"),
            'processing_rows' => tl_stage891_scalar($pdo, "SELECT COUNT(*) FROM training_reward_handoffs WHERE handoff_status='processing'"),
            'blocked_rows' => tl_stage891_scalar($pdo, "SELECT COUNT(*) FROM training_reward_handoffs WHERE handoff_status='blocked'"),
            'delivered_rows' => tl_stage891_scalar($pdo, "SELECT COUNT(*) FROM training_reward_handoffs WHERE handoff_status='delivered'"),
        ];
        foreach ($findings as $key => $value) {
            if ($key !== 'query_ok' && $value < 0) $findings['query_ok'] = false;
        }
        $findings['stale_cutoff_utc'] = $cutoff;
        return $findings;
    }
}

if (!function_exists('tl_stage891_acceptance_summary')) {
    function tl_stage891_acceptance_summary(): array
    {
        $schemaReady = tl_stage890_table_ready();
        $config = tl_stage891_config();
        $adapter = tl_stage890_adapter_state();
        $outbox = tl_stage890_summary();
        $findings = tl_stage891_consistency_findings();
        $checks = [
            'stage890_schema_ready' => $schemaReady,
            'consistency_queries_succeeded' => !empty($findings['query_ok']),
            'no_stale_processing_leases' => (int)($findings['stale_processing'] ?? -1) === 0,
            'no_orphan_handoffs' => (int)($findings['orphan_handoffs'] ?? -1) === 0,
            'no_delivered_reward_mismatch' => (int)($findings['delivered_reward_mismatch'] ?? -1) === 0,
            'no_duplicate_idempotency_keys' => (int)($findings['duplicate_idempotency_keys'] ?? -1) === 0,
            'no_duplicate_external_references' => (int)($findings['duplicate_external_references'] ?? -1) === 0,
            'lease_policy_valid' => (int)$config['lease_seconds'] >= 60,
            'recovery_batch_policy_valid' => (int)$config['recovery_batch_size'] >= 1,
            'processing_disabled_or_all_gates_open' => empty($adapter['processing_enabled']) || !empty($adapter['can_process']),
        ];
        $passed = count(array_filter($checks));
        $score = (int)round(($passed / max(1, count($checks))) * 100);
        $structuralKeys = [
            'stage890_schema_ready',
            'consistency_queries_succeeded',
            'no_stale_processing_leases',
            'no_orphan_handoffs',
            'no_delivered_reward_mismatch',
            'no_duplicate_idempotency_keys',
            'no_duplicate_external_references',
            'lease_policy_valid',
            'recovery_batch_policy_valid',
        ];
        $safeToObserve = true;
        foreach ($structuralKeys as $key) {
            if (empty($checks[$key])) $safeToObserve = false;
        }
        $readyForProduction = $safeToObserve && !empty($adapter['can_process']) && (int)($findings['terminal_failures'] ?? 0) === 0;
        return [
            'stage' => 'Stage 891 Reward Handoff Recovery + Operational Acceptance v1',
            'score' => $score,
            'safe_to_observe' => $safeToObserve,
            'ready_for_production_processing' => $readyForProduction,
            'processing_intentionally_disabled' => empty($adapter['processing_enabled']),
            'config' => $config,
            'adapter' => $adapter,
            'outbox_counts' => (array)($outbox['counts'] ?? []),
            'findings' => $findings,
            'checks' => $checks,
            'safe_boundaries' => [
                'acceptance_is_read_only' => true,
                'recovery_never_calls_microgifter' => true,
                'manual_requeue_never_calls_microgifter' => true,
                'production_delivery_still_requires_stage890_gates' => true,
                'no_new_sql_required' => true,
                'no_wallet_mutation_by_training_lab' => true,
                'no_payment_processing' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage891_run_acceptance')) {
    function tl_stage891_run_acceptance(array $input = []): array
    {
        $recovery = null;
        if (!empty($input['recover_stale'])) $recovery = tl_stage891_recover_stale_processing($input);
        $summary = tl_stage891_acceptance_summary();
        $pdo = tl_db();
        if ($pdo) {
            tl_log_event($pdo, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1), 'system', null, 'stage891_reward_handoff_acceptance_run', [
                'score' => (int)$summary['score'],
                'safe_to_observe' => !empty($summary['safe_to_observe']),
                'ready_for_production_processing' => !empty($summary['ready_for_production_processing']),
                'recovery' => $recovery,
            ]);
        }
        return ['recovery'=>$recovery,'acceptance'=>$summary];
    }
}

if (!function_exists('tl_stage891_process_resilient_batch')) {
    function tl_stage891_process_resilient_batch(array $input = []): array
    {
        $recovery = tl_stage891_recover_stale_processing($input);
        $batch = tl_stage890_process_batch($input);
        return ['recovery'=>$recovery,'batch'=>$batch,'acceptance'=>tl_stage891_acceptance_summary()];
    }
}

if (!function_exists('tl_stage891_render_admin_panel')) {
    function tl_stage891_render_admin_panel(): void
    {
        $data = tl_stage891_acceptance_summary();
        $findings = (array)$data['findings'];
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 891</span><h2>Handoff Recovery + Acceptance</h2><p class="labs-copy">Recover abandoned worker leases, review terminal failures, and verify delivery consistency before production issuing is enabled.</p></div><a class="labs-btn" href="' . labs_e(labs_url('/api/training/reward-handoff-operations.php')) . '">Operations API</a></div>';
        echo '<div class="labs-kpis"><div class="labs-kpi"><span>Acceptance</span><strong>' . (int)$data['score'] . '/100</strong><small>read-only consistency</small></div><div class="labs-kpi"><span>Stale</span><strong>' . max(0, (int)($findings['stale_processing'] ?? 0)) . '</strong><small>worker leases</small></div><div class="labs-kpi"><span>Terminal</span><strong>' . max(0, (int)($findings['terminal_failures'] ?? 0)) . '</strong><small>manual review</small></div><div class="labs-kpi"><span>Production</span><strong>' . (!empty($data['ready_for_production_processing']) ? 'Ready' : 'Gated') . '</strong><small>' . (!empty($data['processing_intentionally_disabled']) ? 'disabled by config' : 'gate review') . '</small></div></div>';
        echo '<div class="labs-actions"><form action="' . labs_e(labs_url('/admin/action-result.php')) . '" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="stage891_run_handoff_acceptance"><button class="labs-btn" type="submit">Run Acceptance</button></form><form action="' . labs_e(labs_url('/admin/action-result.php')) . '" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="stage891_recover_stale_handoffs"><button class="labs-btn" type="submit">Recover Stale Workers</button></form><form action="' . labs_e(labs_url('/admin/action-result.php')) . '" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="stage891_process_resilient_batch"><button class="labs-btn labs-btn-primary" type="submit">Recover + Process Batch</button></form></div>';
        echo '<div class="labs-safe-note">Acceptance and recovery do not call Microgifter. Real delivery remains controlled by all Stage 890 production gates.</div></section>';
    }
}
