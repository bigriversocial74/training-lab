<?php
/**
 * Stage 891 lease-owned processor.
 *
 * This is the production-safe processing entry point for Stage 890 handoffs.
 * A worker may finalize a result only while it still owns the processing lease.
 */
require_once __DIR__ . '/training-lab-stage891-reward-handoff-recovery.php';

if (!function_exists('tl_stage891_process_handoff_owned')) {
    function tl_stage891_process_handoff_owned(array $input): array
    {
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $pdo = tl_require_db();
        $ref = trim((string)($input['handoff_id'] ?? $input['public_id'] ?? ''));
        if ($ref === '') throw new TlHttpException('Handoff reference is required.', 422, 'handoff_required');
        $actor = (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1);
        $worker = 'training-lab-stage891:' . (function_exists('tl_security_request_id') ? tl_security_request_id() : bin2hex(random_bytes(8)));
        $handoff = [];
        $payload = [];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM training_reward_handoffs WHERE public_id = ? OR id = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$ref, ctype_digit($ref) ? (int)$ref : 0]);
            $handoff = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$handoff) throw new TlHttpException('Reward handoff was not found.', 404, 'handoff_not_found');
            if ((string)$handoff['handoff_status'] === 'delivered') {
                $pdo->commit();
                return ['handoff_id'=>(int)$handoff['id'],'handoff_status'=>'delivered','idempotent'=>true];
            }
            if ((string)$handoff['handoff_status'] === 'cancelled') throw new TlHttpException('Cancelled handoffs cannot be processed.', 409, 'handoff_cancelled');

            $reward = tl_stage890_load_reward($pdo, (string)$handoff['reward_event_id']);
            $link = tl_stage890_find_account_link($pdo, $reward);
            $adapter = tl_stage890_adapter_state();
            $blockers = tl_stage890_blockers($reward, $link, $adapter);
            if ($blockers) {
                $metadata = tl_stage891_merge_metadata($handoff['metadata_json'] ?? null, [
                    'last_gate_check_at'=>gmdate('c'),
                    'last_gate_blockers'=>$blockers,
                ]);
                $update = $pdo->prepare("UPDATE training_reward_handoffs SET handoff_status='blocked', failure_code='requirements_blocked', failure_message=?, locked_at=NULL, locked_by=NULL, next_attempt_at=NULL, metadata_json=? WHERE id=?");
                $update->execute([implode(', ', $blockers), $metadata, (int)$handoff['id']]);
                $pdo->commit();
                return ['handoff_id'=>(int)$handoff['id'],'handoff_status'=>'blocked','blockers'=>$blockers];
            }

            $attempt = (int)$handoff['attempt_count'] + 1;
            $metadata = tl_stage891_merge_metadata($handoff['metadata_json'] ?? null, [
                'active_worker'=>$worker,
                'active_worker_started_at'=>gmdate('c'),
                'history_event'=>[
                    'event'=>'worker_lease_acquired',
                    'at'=>gmdate('c'),
                    'worker'=>$worker,
                    'attempt_count'=>$attempt,
                ],
            ]);
            $update = $pdo->prepare("UPDATE training_reward_handoffs SET handoff_status='processing', attempt_count=?, last_attempt_at=UTC_TIMESTAMP(), locked_at=UTC_TIMESTAMP(), locked_by=?, adapter_mode=?, failure_code=NULL, failure_message=NULL, metadata_json=? WHERE id=?");
            $update->execute([$attempt, $worker, (string)$adapter['adapter_mode'], $metadata, (int)$handoff['id']]);
            $payload = tl_stage890_payload($reward, $link, (string)$handoff['idempotency_key']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $adapterResult = tl_stage890_call_adapter($payload);
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT * FROM training_reward_handoffs WHERE id = ? LIMIT 1 FOR UPDATE');
            $lock->execute([(int)$handoff['id']]);
            $current = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$current) throw new RuntimeException('Handoff disappeared during processing.');
            if ((string)$current['handoff_status'] === 'delivered') {
                $pdo->commit();
                return ['handoff_id'=>(int)$current['id'],'handoff_status'=>'delivered','idempotent'=>true,'late_worker_result_ignored'=>true];
            }

            $ownsLease = (string)$current['handoff_status'] === 'processing'
                && hash_equals((string)($current['locked_by'] ?? ''), $worker);
            if (!$ownsLease) {
                $external = !empty($adapterResult['ok'])
                    ? tl_stage890_external_reference(is_array($adapterResult['result'] ?? null) ? $adapterResult['result'] : [])
                    : '';
                tl_log_event($pdo, $actor, 'reward_event', (int)$current['reward_event_id'], 'stage891_worker_lease_lost', [
                    'handoff_id'=>(int)$current['id'],
                    'worker'=>$worker,
                    'current_status'=>(string)$current['handoff_status'],
                    'current_locked_by'=>(string)($current['locked_by'] ?? ''),
                    'adapter_result_ok'=>!empty($adapterResult['ok']),
                    'external_reference'=>$external,
                    'result_not_applied'=>true,
                ]);
                $pdo->commit();
                return [
                    'handoff_id'=>(int)$current['id'],
                    'handoff_status'=>(string)$current['handoff_status'],
                    'ownership_lost'=>true,
                    'adapter_result_unapplied'=>true,
                    'external_reference'=>$external,
                ];
            }

            $reward = tl_stage890_load_reward($pdo, (string)$current['reward_event_id']);
            if (!empty($adapterResult['ok'])) {
                $result = is_array($adapterResult['result'] ?? null) ? $adapterResult['result'] : [];
                $external = tl_stage890_external_reference($result);
                $metadata = tl_stage891_merge_metadata($current['metadata_json'] ?? null, [
                    'last_completed_worker'=>$worker,
                    'last_completed_at'=>gmdate('c'),
                    'history_event'=>[
                        'event'=>'worker_delivery_confirmed',
                        'at'=>gmdate('c'),
                        'worker'=>$worker,
                        'external_reference'=>$external,
                    ],
                ]);
                $stmt = $pdo->prepare("UPDATE training_reward_handoffs SET handoff_status='delivered', adapter_name=?, delivered_at=UTC_TIMESTAMP(), external_reference=?, response_json=?, failure_code=NULL, failure_message=NULL, next_attempt_at=NULL, locked_at=NULL, locked_by=NULL, metadata_json=? WHERE id=? AND handoff_status='processing' AND locked_by=?");
                $stmt->execute([(string)($adapterResult['adapter'] ?? ''), $external !== '' ? $external : null, tl_stage890_json($adapterResult), $metadata, (int)$current['id'], $worker]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('Handoff lease ownership changed during delivery finalization.');

                $rewardMetadata = tl_stage890_json_decode($reward['metadata_json'] ?? null);
                $rewardMetadata['claim_status'] = $external !== '' ? 'linked_to_microgifter' : 'issued_by_microgifter_adapter';
                $rewardMetadata['stage891_handoff'] = [
                    'handoff_id'=>(int)$current['id'],
                    'status'=>'delivered',
                    'worker'=>$worker,
                    'adapter'=>(string)($adapterResult['adapter'] ?? ''),
                    'external_reference'=>$external,
                    'delivered_at'=>gmdate('c'),
                    'idempotency_key'=>(string)$current['idempotency_key'],
                ];
                $linkedGift = $result['gift_id'] ?? $result['linked_gift_id'] ?? ($reward['linked_gift_id'] ?: null);
                $linkedMicrogift = $result['microgift_instance_id'] ?? $result['linked_microgift_instance_id'] ?? ($reward['linked_microgift_instance_id'] ?: null);
                $linkedEntitlement = $result['digital_entitlement_id'] ?? $result['linked_digital_entitlement_id'] ?? ($reward['linked_digital_entitlement_id'] ?: null);
                $linkedWallet = $result['wallet_event_id'] ?? $result['linked_wallet_event_id'] ?? ($reward['linked_wallet_event_id'] ?: null);
                $rewardStatus = ($linkedGift || $linkedMicrogift || $linkedEntitlement || $linkedWallet || $external !== '') ? 'linked' : 'issued';
                $rewardUpdate = $pdo->prepare('UPDATE training_reward_events SET status=?, linked_gift_id=?, linked_microgift_instance_id=?, linked_digital_entitlement_id=?, linked_wallet_event_id=?, issued_at=COALESCE(issued_at, CURRENT_TIMESTAMP), failure_message=NULL, metadata_json=? WHERE id=?');
                $rewardUpdate->execute([$rewardStatus, $linkedGift, $linkedMicrogift, $linkedEntitlement, $linkedWallet, tl_stage890_json($rewardMetadata), (int)$reward['id']]);
                tl_log_event($pdo, $actor, 'reward_event', (int)$reward['id'], 'stage891_reward_handoff_delivered', [
                    'handoff_id'=>(int)$current['id'],
                    'worker'=>$worker,
                    'adapter'=>$adapterResult['adapter'] ?? null,
                    'external_reference'=>$external,
                ]);
                $status = 'delivered';
                $nextAttempt = null;
            } else {
                $attempt = (int)$current['attempt_count'];
                $config = tl_stage890_config();
                $code = (string)($adapterResult['code'] ?? 'adapter_failed');
                $retryable = $attempt < (int)$config['max_attempts'] && !in_array($code, ['processing_gate_closed','adapter_unavailable'], true);
                $status = $retryable ? 'failed' : (in_array($code, ['processing_gate_closed','adapter_unavailable'], true) ? 'blocked' : 'failed');
                $nextAttempt = $retryable ? gmdate('Y-m-d H:i:s', time() + tl_stage890_retry_delay($attempt)) : null;
                $message = mb_substr((string)($adapterResult['message'] ?? 'Adapter delivery failed.'), 0, 500);
                $metadata = tl_stage891_merge_metadata($current['metadata_json'] ?? null, [
                    'last_completed_worker'=>$worker,
                    'last_completed_at'=>gmdate('c'),
                    'history_event'=>[
                        'event'=>'worker_delivery_failed',
                        'at'=>gmdate('c'),
                        'worker'=>$worker,
                        'attempt_count'=>$attempt,
                        'failure_code'=>$code,
                        'next_attempt_at'=>$nextAttempt,
                    ],
                ]);
                $stmt = $pdo->prepare("UPDATE training_reward_handoffs SET handoff_status=?, adapter_name=?, response_json=?, failure_code=?, failure_message=?, next_attempt_at=?, locked_at=NULL, locked_by=NULL, metadata_json=? WHERE id=? AND handoff_status='processing' AND locked_by=?");
                $stmt->execute([$status, (string)($adapterResult['adapter'] ?? ''), tl_stage890_json($adapterResult), $code, $message, $nextAttempt, $metadata, (int)$current['id'], $worker]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('Handoff lease ownership changed during failure finalization.');

                $rewardMetadata = tl_stage890_json_decode($reward['metadata_json'] ?? null);
                $rewardMetadata['claim_status'] = 'claim_failed_adapter_error';
                $rewardMetadata['stage891_handoff'] = [
                    'handoff_id'=>(int)$current['id'],
                    'status'=>$status,
                    'worker'=>$worker,
                    'attempt_count'=>$attempt,
                    'next_attempt_at'=>$nextAttempt,
                    'failure_code'=>$code,
                    'updated_at'=>gmdate('c'),
                ];
                $rewardUpdate = $pdo->prepare("UPDATE training_reward_events SET status='failed', failure_message=?, metadata_json=? WHERE id=? AND status NOT IN ('issued','linked','cancelled')");
                $rewardUpdate->execute([$message, tl_stage890_json($rewardMetadata), (int)$reward['id']]);
                tl_log_event($pdo, $actor, 'reward_event', (int)$reward['id'], 'stage891_reward_handoff_failed', [
                    'handoff_id'=>(int)$current['id'],
                    'worker'=>$worker,
                    'status'=>$status,
                    'attempt_count'=>$attempt,
                    'next_attempt_at'=>$nextAttempt,
                    'failure_code'=>$code,
                ]);
            }
            $pdo->commit();
            return [
                'handoff_id'=>(int)$current['id'],
                'public_id'=>(string)$current['public_id'],
                'handoff_status'=>$status,
                'worker'=>$worker,
                'adapter_result'=>$adapterResult,
                'next_attempt_at'=>$nextAttempt,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_stage891_process_owned_batch')) {
    function tl_stage891_process_owned_batch(array $input = []): array
    {
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $recovery = tl_stage891_recover_stale_processing($input);
        $sync = tl_stage890_sync_outbox($input);
        $pdo = tl_require_db();
        $limit = max(1, min(50, (int)($input['limit'] ?? tl_stage890_config()['batch_size'])));
        $stmt = $pdo->prepare("SELECT id FROM training_reward_handoffs WHERE handoff_status IN ('queued','failed') AND (next_attempt_at IS NULL OR next_attempt_at <= UTC_TIMESTAMP()) AND attempt_count < ? ORDER BY COALESCE(next_attempt_at, created_at) ASC, id ASC LIMIT " . $limit);
        $stmt->execute([(int)tl_stage890_config()['max_attempts']]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $processed = [];
        foreach ($ids as $id) {
            try {
                $processed[] = tl_stage891_process_handoff_owned($input + ['handoff_id'=>(string)$id]);
            } catch (Throwable $e) {
                $processed[] = ['handoff_id'=>$id,'error'=>$e->getMessage()];
            }
        }
        return [
            'recovery'=>$recovery,
            'sync'=>$sync,
            'selected'=>count($ids),
            'processed'=>$processed,
            'acceptance'=>tl_stage891_acceptance_summary(),
        ];
    }
}
