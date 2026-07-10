<?php
/**
 * Stage 893 — External Delivery Reconciliation v1.
 *
 * Quarantines ambiguous lost-lease adapter successes and reconciles them through
 * read-only Microgifter lookup adapters before local delivery state is finalized.
 * This service never calls a reward issue/claim adapter.
 */
require_once __DIR__ . '/training-lab-stage891-owned-processor.php';

if (!function_exists('tl_stage893_root_config')) {
    function tl_stage893_root_config(): array
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

if (!function_exists('tl_stage893_config')) {
    function tl_stage893_config(): array
    {
        $root = tl_stage893_root_config();
        $enabled = getenv('TL_REWARD_RECONCILIATION_ENABLED');
        $batch = getenv('TL_REWARD_RECONCILIATION_BATCH_SIZE');
        $minimumAge = getenv('TL_REWARD_RECONCILIATION_MIN_AGE_SECONDS');
        return [
            'enabled' => function_exists('tl_stage890_bool')
                ? tl_stage890_bool($enabled !== false ? $enabled : ($root['reward_delivery_reconciliation_enabled'] ?? false), false)
                : filter_var($enabled !== false ? $enabled : ($root['reward_delivery_reconciliation_enabled'] ?? false), FILTER_VALIDATE_BOOLEAN),
            'batch_size' => max(1, min(100, (int)($batch !== false && $batch !== ''
                ? $batch
                : ($root['reward_delivery_reconciliation_batch_size'] ?? 25)))),
            'minimum_age_seconds' => max(0, min(86400, (int)($minimumAge !== false && $minimumAge !== ''
                ? $minimumAge
                : ($root['reward_delivery_reconciliation_min_age_seconds'] ?? 300)))),
        ];
    }
}

if (!function_exists('tl_stage893_read_adapter_functions')) {
    function tl_stage893_read_adapter_functions(): array
    {
        return array_values(array_filter([
            'microgifter_training_reward_lookup',
            'microgifter_lookup_training_reward',
            'microgifter_find_reward_by_idempotency_key',
            'microgifter_reward_delivery_status',
        ], 'function_exists'));
    }
}

if (!function_exists('tl_stage893_normalize_lookup')) {
    function tl_stage893_normalize_lookup($value): array
    {
        $raw = is_array($value) ? $value : [];
        if (isset($raw['result']) && is_array($raw['result'])) $raw = $raw['result'];
        $status = strtolower(trim((string)($raw['delivery_status'] ?? $raw['status'] ?? $raw['state'] ?? 'unknown')));
        $deliveredStates = ['delivered','issued','linked','complete','completed','success','succeeded','claimed'];
        $pendingStates = ['pending','queued','processing','accepted','created'];
        $missingStates = ['not_found','missing','absent','none'];
        $failedStates = ['failed','error','cancelled','canceled','rejected'];
        $normalized = 'unknown';
        if (in_array($status, $deliveredStates, true)) $normalized = 'delivered';
        elseif (in_array($status, $pendingStates, true)) $normalized = 'pending';
        elseif (in_array($status, $missingStates, true)) $normalized = 'not_found';
        elseif (in_array($status, $failedStates, true)) $normalized = 'failed';

        $references = [
            'gift_id' => $raw['gift_id'] ?? $raw['linked_gift_id'] ?? null,
            'microgift_instance_id' => $raw['microgift_instance_id'] ?? $raw['linked_microgift_instance_id'] ?? null,
            'digital_entitlement_id' => $raw['digital_entitlement_id'] ?? $raw['linked_digital_entitlement_id'] ?? null,
            'wallet_event_id' => $raw['wallet_event_id'] ?? $raw['linked_wallet_event_id'] ?? null,
        ];
        $external = (string)($raw['external_reference'] ?? $raw['reference'] ?? $raw['delivery_id'] ?? '');
        $found = array_key_exists('found', $raw)
            ? !empty($raw['found'])
            : ($normalized !== 'not_found' && ($normalized !== 'unknown' || $external !== '' || count(array_filter($references)) > 0));
        return [
            'found' => $found,
            'delivery_status' => $normalized,
            'confirmed_delivered' => $found && $normalized === 'delivered',
            'confirmed_absent' => !$found || $normalized === 'not_found',
            'external_reference' => mb_substr($external, 0, 190),
            'references' => $references,
            'message' => mb_substr((string)($raw['message'] ?? ''), 0, 500),
        ];
    }
}

if (!function_exists('tl_stage893_lookup_external')) {
    function tl_stage893_lookup_external(array $handoff, array $reward): array
    {
        $functions = tl_stage893_read_adapter_functions();
        if (!$functions) {
            return [
                'lookup_available' => false,
                'adapter' => null,
                'found' => false,
                'delivery_status' => 'unknown',
                'confirmed_delivered' => false,
                'confirmed_absent' => false,
                'external_reference' => (string)($handoff['external_reference'] ?? ''),
                'references' => [],
                'message' => 'No read-only Microgifter reward lookup adapter is installed.',
            ];
        }
        $payload = [
            'contract' => 'training_lab_reward_reconciliation_v1',
            'source' => 'training_lab',
            'idempotency_key' => (string)($handoff['idempotency_key'] ?? ''),
            'external_reference' => (string)($handoff['external_reference'] ?? ''),
            'training_handoff_id' => (int)($handoff['id'] ?? 0),
            'training_handoff_public_id' => (string)($handoff['public_id'] ?? ''),
            'training_reward_event_id' => (int)($reward['id'] ?? 0),
            'training_reward_public_id' => (string)($reward['public_id'] ?? ''),
            'training_user_id' => (int)($reward['user_id'] ?? 0),
            'microgifter_user_id' => (string)($handoff['microgifter_user_id'] ?? ''),
            'read_only' => true,
        ];
        foreach ($functions as $fn) {
            try {
                $normalized = tl_stage893_normalize_lookup($fn($payload));
                return ['lookup_available'=>true, 'adapter'=>$fn] + $normalized;
            } catch (Throwable $e) {
                return [
                    'lookup_available' => true,
                    'adapter' => $fn,
                    'found' => false,
                    'delivery_status' => 'error',
                    'confirmed_delivered' => false,
                    'confirmed_absent' => false,
                    'external_reference' => (string)($handoff['external_reference'] ?? ''),
                    'references' => [],
                    'message' => mb_substr($e->getMessage(), 0, 500),
                ];
            }
        }
        return ['lookup_available'=>false,'adapter'=>null,'confirmed_delivered'=>false,'confirmed_absent'=>false,'delivery_status'=>'unknown','references'=>[]];
    }
}

if (!function_exists('tl_stage893_metadata')) {
    function tl_stage893_metadata($value, array $patch): string
    {
        $metadata = function_exists('tl_stage890_json_decode') ? tl_stage890_json_decode($value) : [];
        $history = is_array($metadata['stage893_reconciliation_history'] ?? null) ? $metadata['stage893_reconciliation_history'] : [];
        if (isset($patch['history_event']) && is_array($patch['history_event'])) {
            $history[] = $patch['history_event'];
            $history = array_slice($history, -30);
            unset($patch['history_event']);
        }
        $metadata['stage893_reconciliation'] = array_merge((array)($metadata['stage893_reconciliation'] ?? []), $patch);
        $metadata['stage893_reconciliation_history'] = $history;
        return tl_stage890_json($metadata);
    }
}

if (!function_exists('tl_stage893_quarantine_lost_outcome')) {
    function tl_stage893_quarantine_lost_outcome(array $outcome, int $actorUserId = 1): array
    {
        if (empty($outcome['ownership_lost']) || empty($outcome['adapter_result_unapplied'])) return ['quarantined'=>false,'reason'=>'not_a_lost_delivery_outcome'];
        if (!tl_stage890_table_ready()) return ['quarantined'=>false,'reason'=>'stage890_schema_missing'];
        $handoffId = (int)($outcome['handoff_id'] ?? 0);
        if ($handoffId < 1) return ['quarantined'=>false,'reason'=>'handoff_missing'];
        $pdo = tl_require_db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM training_reward_handoffs WHERE id=? LIMIT 1 FOR UPDATE');
            $stmt->execute([$handoffId]);
            $handoff = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$handoff) throw new RuntimeException('Reward handoff was not found during quarantine.');
            $status = (string)$handoff['handoff_status'];
            $external = mb_substr((string)($outcome['external_reference'] ?? ''), 0, 190);
            $metadata = tl_stage893_metadata($handoff['metadata_json'] ?? null, [
                'reconciliation_required' => true,
                'quarantined_at' => gmdate('c'),
                'quarantined_by_user_id' => $actorUserId,
                'lost_worker_external_reference' => $external,
                'history_event' => [
                    'event' => 'lost_lease_success_quarantined',
                    'at' => gmdate('c'),
                    'previous_status' => $status,
                    'external_reference' => $external,
                ],
            ]);
            $quarantined = false;
            if (in_array($status, ['queued','failed','blocked'], true)) {
                $update = $pdo->prepare("UPDATE training_reward_handoffs SET handoff_status='blocked', failure_code='external_delivery_confirmation_required', failure_message='A prior worker reported adapter success after losing its lease. External delivery must be confirmed before retry.', external_reference=COALESCE(NULLIF(?,''), external_reference), next_attempt_at=NULL, locked_at=NULL, locked_by=NULL, metadata_json=? WHERE id=? AND handoff_status IN ('queued','failed','blocked')");
                $update->execute([$external, $metadata, $handoffId]);
                $quarantined = $update->rowCount() === 1;
            } else {
                $update = $pdo->prepare('UPDATE training_reward_handoffs SET metadata_json=? WHERE id=?');
                $update->execute([$metadata, $handoffId]);
            }
            tl_log_event($pdo, $actorUserId, 'reward_event', (int)$handoff['reward_event_id'], 'stage893_external_delivery_quarantined', [
                'handoff_id'=>$handoffId,
                'previous_status'=>$status,
                'quarantined'=>$quarantined,
                'external_reference'=>$external,
                'active_replacement_worker'=>$status === 'processing',
            ]);
            $pdo->commit();
            return [
                'quarantined'=>$quarantined,
                'handoff_id'=>$handoffId,
                'handoff_status'=>$quarantined ? 'blocked' : $status,
                'external_reference'=>$external,
                'active_replacement_worker'=>$status === 'processing',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_stage893_process_handoff_guarded')) {
    function tl_stage893_process_handoff_guarded(array $input): array
    {
        $result = tl_stage891_process_handoff_owned($input);
        if (!empty($result['ownership_lost']) && !empty($result['adapter_result_unapplied'])) {
            $result['stage893_reconciliation'] = tl_stage893_quarantine_lost_outcome(
                $result,
                max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1))
            );
        }
        return $result;
    }
}

if (!function_exists('tl_stage893_candidate_rows')) {
    function tl_stage893_candidate_rows(int $limit = 25): array
    {
        if (!tl_stage890_table_ready()) return [];
        $pdo = tl_db();
        if (!$pdo) return [];
        $limit = max(1, min(100, $limit));
        $sql = "SELECT h.*, re.public_id AS reward_public_id, re.status AS reward_status, re.user_id AS reward_user_id, re.linked_gift_id, re.linked_microgift_instance_id, re.linked_digital_entitlement_id, re.linked_wallet_event_id
                FROM training_reward_handoffs h
                JOIN training_reward_events re ON re.id=h.reward_event_id
                WHERE h.handoff_status<>'cancelled' AND (
                    h.failure_code='external_delivery_confirmation_required'
                    OR (h.handoff_status='delivered' AND re.status NOT IN ('issued','linked'))
                    OR h.metadata_json LIKE '%\"reconciliation_required\":true%'
                )
                ORDER BY CASE WHEN h.failure_code='external_delivery_confirmation_required' THEN 0 ELSE 1 END, h.updated_at ASC, h.id ASC
                LIMIT " . $limit;
        try {
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_stage893_reference_values')) {
    function tl_stage893_reference_values(array $lookup, array $handoff, array $reward): array
    {
        $stored = tl_stage890_json_decode($handoff['response_json'] ?? null);
        $storedResult = is_array($stored['result'] ?? null) ? $stored['result'] : [];
        $lookupRefs = is_array($lookup['references'] ?? null) ? $lookup['references'] : [];
        return [
            'gift_id' => $lookupRefs['gift_id'] ?? $storedResult['gift_id'] ?? $storedResult['linked_gift_id'] ?? ($reward['linked_gift_id'] ?? null),
            'microgift_instance_id' => $lookupRefs['microgift_instance_id'] ?? $storedResult['microgift_instance_id'] ?? $storedResult['linked_microgift_instance_id'] ?? ($reward['linked_microgift_instance_id'] ?? null),
            'digital_entitlement_id' => $lookupRefs['digital_entitlement_id'] ?? $storedResult['digital_entitlement_id'] ?? $storedResult['linked_digital_entitlement_id'] ?? ($reward['linked_digital_entitlement_id'] ?? null),
            'wallet_event_id' => $lookupRefs['wallet_event_id'] ?? $storedResult['wallet_event_id'] ?? $storedResult['linked_wallet_event_id'] ?? ($reward['linked_wallet_event_id'] ?? null),
        ];
    }
}

if (!function_exists('tl_stage893_reconcile_handoff')) {
    function tl_stage893_reconcile_handoff(array $input): array
    {
        $config = tl_stage893_config();
        if (empty($config['enabled'])) throw new TlHttpException('External delivery reconciliation is disabled.', 409, 'reconciliation_disabled');
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $ref = trim((string)($input['handoff_id'] ?? $input['public_id'] ?? ''));
        if ($ref === '') throw new TlHttpException('Handoff reference is required.', 422, 'handoff_required');
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        $automatic = !empty($input['automatic']);
        $pdo = tl_require_db();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM training_reward_handoffs WHERE public_id=? OR id=? LIMIT 1 FOR UPDATE');
            $stmt->execute([$ref, ctype_digit($ref) ? (int)$ref : 0]);
            $handoff = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$handoff) throw new TlHttpException('Reward handoff was not found.', 404, 'handoff_not_found');
            $reward = tl_stage890_load_reward($pdo, (string)$handoff['reward_event_id']);
            $rewardLock = $pdo->prepare('SELECT id FROM training_reward_events WHERE id=? FOR UPDATE');
            $rewardLock->execute([(int)$reward['id']]);
            if ((string)$handoff['handoff_status'] === 'cancelled' || (string)$reward['status'] === 'cancelled') {
                throw new TlHttpException('Cancelled rewards cannot be reconciled.', 409, 'reconciliation_cancelled');
            }
            if ((string)$handoff['handoff_status'] === 'delivered' && in_array((string)$reward['status'], ['issued','linked'], true)) {
                $pdo->commit();
                return ['handoff_id'=>(int)$handoff['id'],'status'=>'already_reconciled','idempotent'=>true];
            }
            if ($automatic && (int)$config['minimum_age_seconds'] > 0) {
                $updated = strtotime((string)($handoff['updated_at'] ?? $handoff['created_at'] ?? ''));
                if ($updated !== false && $updated > time() - (int)$config['minimum_age_seconds']) {
                    $pdo->commit();
                    return ['handoff_id'=>(int)$handoff['id'],'status'=>'deferred_minimum_age','idempotent'=>true];
                }
            }
            $snapshotStatus = (string)$handoff['handoff_status'];
            $snapshotFailure = (string)($handoff['failure_code'] ?? '');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $deliveredMismatch = $snapshotStatus === 'delivered' && !in_array((string)$reward['status'], ['issued','linked'], true);
        $lookup = $deliveredMismatch
            ? ['lookup_available'=>false,'adapter'=>'local_outbox_delivery','confirmed_delivered'=>true,'confirmed_absent'=>false,'delivery_status'=>'delivered','external_reference'=>(string)($handoff['external_reference'] ?? ''),'references'=>[],'message'=>'The durable outbox already records delivery.']
            : tl_stage893_lookup_external($handoff, $reward);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM training_reward_handoffs WHERE id=? LIMIT 1 FOR UPDATE');
            $stmt->execute([(int)$handoff['id']]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) throw new RuntimeException('Reward handoff disappeared during reconciliation.');
            $reward = tl_stage890_load_reward($pdo, (string)$current['reward_event_id']);
            $rewardLock = $pdo->prepare('SELECT id FROM training_reward_events WHERE id=? FOR UPDATE');
            $rewardLock->execute([(int)$reward['id']]);
            if ((string)$current['handoff_status'] === 'cancelled' || (string)$reward['status'] === 'cancelled') {
                throw new TlHttpException('Cancelled rewards cannot be reconciled.', 409, 'reconciliation_cancelled');
            }
            if ((string)$current['handoff_status'] !== $snapshotStatus || (string)($current['failure_code'] ?? '') !== $snapshotFailure) {
                $pdo->commit();
                return ['handoff_id'=>(int)$current['id'],'status'=>'state_changed_retry_later','idempotent'=>true];
            }

            $confirmed = !empty($lookup['confirmed_delivered']);
            $external = mb_substr((string)($lookup['external_reference'] ?? $current['external_reference'] ?? ''), 0, 190);
            $metadata = tl_stage893_metadata($current['metadata_json'] ?? null, [
                'reconciliation_required' => !$confirmed,
                'last_checked_at' => gmdate('c'),
                'last_checked_by_user_id' => $actor,
                'lookup_adapter' => (string)($lookup['adapter'] ?? ''),
                'lookup_available' => !empty($lookup['lookup_available']),
                'lookup_status' => (string)($lookup['delivery_status'] ?? 'unknown'),
                'confirmed_delivered' => $confirmed,
                'external_reference' => $external,
                'history_event' => [
                    'event' => $confirmed ? 'external_delivery_confirmed' : 'external_delivery_unconfirmed',
                    'at' => gmdate('c'),
                    'adapter' => (string)($lookup['adapter'] ?? ''),
                    'status' => (string)($lookup['delivery_status'] ?? 'unknown'),
                    'external_reference' => $external,
                ],
            ]);

            if ($confirmed) {
                $references = tl_stage893_reference_values($lookup, $current, $reward);
                $handoffUpdate = $pdo->prepare("UPDATE training_reward_handoffs SET handoff_status='delivered', adapter_name=COALESCE(NULLIF(?,''),adapter_name), delivered_at=COALESCE(delivered_at,UTC_TIMESTAMP()), external_reference=COALESCE(NULLIF(?,''),external_reference), failure_code=NULL, failure_message=NULL, next_attempt_at=NULL, locked_at=NULL, locked_by=NULL, metadata_json=? WHERE id=?");
                $handoffUpdate->execute([(string)($lookup['adapter'] ?? ''), $external, $metadata, (int)$current['id']]);
                $linked = count(array_filter($references)) > 0 || $external !== '';
                $rewardStatus = $linked ? 'linked' : 'issued';
                $rewardMetadata = tl_stage890_json_decode($reward['metadata_json'] ?? null);
                $rewardMetadata['claim_status'] = $linked ? 'linked_to_microgifter' : 'issued_by_microgifter_adapter';
                $rewardMetadata['stage893_reconciliation'] = [
                    'handoff_id'=>(int)$current['id'],
                    'status'=>'confirmed_delivered',
                    'adapter'=>(string)($lookup['adapter'] ?? ''),
                    'external_reference'=>$external,
                    'reconciled_at'=>gmdate('c'),
                    'reconciled_by_user_id'=>$actor,
                ];
                $rewardUpdate = $pdo->prepare('UPDATE training_reward_events SET status=?, linked_gift_id=?, linked_microgift_instance_id=?, linked_digital_entitlement_id=?, linked_wallet_event_id=?, issued_at=COALESCE(issued_at,CURRENT_TIMESTAMP), failure_message=NULL, metadata_json=? WHERE id=?');
                $rewardUpdate->execute([$rewardStatus, $references['gift_id'] ?: null, $references['microgift_instance_id'] ?: null, $references['digital_entitlement_id'] ?: null, $references['wallet_event_id'] ?: null, tl_stage890_json($rewardMetadata), (int)$reward['id']]);
                tl_log_event($pdo, $actor, 'reward_event', (int)$reward['id'], 'stage893_external_delivery_reconciled', [
                    'handoff_id'=>(int)$current['id'],
                    'reward_status'=>$rewardStatus,
                    'lookup_adapter'=>$lookup['adapter'] ?? null,
                    'external_reference'=>$external,
                    'idempotency_key'=>(string)$current['idempotency_key'],
                ]);
                $pdo->commit();
                return ['handoff_id'=>(int)$current['id'],'status'=>'reconciled','handoff_status'=>'delivered','reward_status'=>$rewardStatus,'external_reference'=>$external,'lookup'=>$lookup];
            }

            $message = !empty($lookup['lookup_available'])
                ? 'External delivery was not confirmed. The handoff remains quarantined and must not be retried.'
                : 'A read-only Microgifter lookup adapter is required before this handoff can be retried.';
            $handoffUpdate = $pdo->prepare("UPDATE training_reward_handoffs SET handoff_status='blocked', failure_code='external_delivery_confirmation_required', failure_message=?, next_attempt_at=NULL, locked_at=NULL, locked_by=NULL, metadata_json=? WHERE id=? AND handoff_status<>'delivered'");
            $handoffUpdate->execute([$message, $metadata, (int)$current['id']]);
            tl_log_event($pdo, $actor, 'reward_event', (int)$reward['id'], 'stage893_external_delivery_unconfirmed', [
                'handoff_id'=>(int)$current['id'],
                'lookup_available'=>!empty($lookup['lookup_available']),
                'lookup_adapter'=>$lookup['adapter'] ?? null,
                'lookup_status'=>$lookup['delivery_status'] ?? 'unknown',
                'external_reference'=>$external,
                'retry_blocked'=>true,
            ]);
            $pdo->commit();
            return ['handoff_id'=>(int)$current['id'],'status'=>'unconfirmed','handoff_status'=>'blocked','retry_blocked'=>true,'lookup'=>$lookup];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_stage893_reconcile_batch')) {
    function tl_stage893_reconcile_batch(array $input = []): array
    {
        $config = tl_stage893_config();
        if (empty($config['enabled'])) return ['status'=>'skipped','reason'=>'reconciliation_disabled','selected'=>0,'results'=>[]];
        $limit = max(1, min((int)$config['batch_size'], (int)($input['limit'] ?? $config['batch_size'])));
        $rows = tl_stage893_candidate_rows($limit);
        $results = [];
        foreach ($rows as $row) {
            try {
                $results[] = tl_stage893_reconcile_handoff($input + ['handoff_id'=>(string)$row['id']]);
            } catch (Throwable $e) {
                $results[] = ['handoff_id'=>(int)$row['id'],'status'=>'error','error'=>mb_substr($e->getMessage(), 0, 500)];
            }
        }
        $counts = ['reconciled'=>0,'unconfirmed'=>0,'deferred'=>0,'errors'=>0,'other'=>0];
        foreach ($results as $result) {
            $status = (string)($result['status'] ?? 'other');
            if ($status === 'reconciled' || $status === 'already_reconciled') $counts['reconciled']++;
            elseif ($status === 'unconfirmed') $counts['unconfirmed']++;
            elseif (str_starts_with($status, 'deferred_') || $status === 'state_changed_retry_later') $counts['deferred']++;
            elseif ($status === 'error') $counts['errors']++;
            else $counts['other']++;
        }
        return ['status'=>'completed','selected'=>count($rows),'counts'=>$counts,'results'=>$results];
    }
}

if (!function_exists('tl_stage893_summary')) {
    function tl_stage893_summary(): array
    {
        $config = tl_stage893_config();
        $adapters = tl_stage893_read_adapter_functions();
        $rows = tl_stage893_candidate_rows(100);
        $counts = ['candidates'=>count($rows),'quarantined'=>0,'delivered_mismatch'=>0];
        foreach ($rows as $row) {
            if ((string)($row['failure_code'] ?? '') === 'external_delivery_confirmation_required') $counts['quarantined']++;
            if ((string)($row['handoff_status'] ?? '') === 'delivered' && !in_array((string)($row['reward_status'] ?? ''), ['issued','linked'], true)) $counts['delivered_mismatch']++;
        }
        return [
            'stage'=>'Stage 893 External Delivery Reconciliation v1',
            'enabled'=>!empty($config['enabled']),
            'config'=>$config,
            'read_adapter_available'=>count($adapters) > 0,
            'read_adapter_functions'=>$adapters,
            'counts'=>$counts,
            'candidates'=>array_slice(array_map(static function (array $row): array {
                return [
                    'handoff_id'=>(int)$row['id'],
                    'public_id'=>(string)$row['public_id'],
                    'handoff_status'=>(string)$row['handoff_status'],
                    'reward_status'=>(string)$row['reward_status'],
                    'failure_code'=>(string)($row['failure_code'] ?? ''),
                    'external_reference'=>(string)($row['external_reference'] ?? ''),
                    'updated_at'=>(string)($row['updated_at'] ?? ''),
                ];
            }, $rows), 0, 25),
            'safe_boundaries'=>[
                'read_only_microgifter_lookup_only'=>true,
                'issue_and_claim_adapters_are_never_called'=>true,
                'uncertain_deliveries_remain_blocked'=>true,
                'local_state_changes_require_confirmation'=>true,
                'no_new_sql_required'=>true,
            ],
        ];
    }
}

if (!function_exists('tl_stage893_render_admin_panel')) {
    function tl_stage893_render_admin_panel(): void
    {
        $data = tl_stage893_summary();
        $counts = (array)$data['counts'];
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 893</span><h2>External Delivery Reconciliation</h2><p class="labs-copy">Quarantine ambiguous adapter successes, verify delivery by idempotency key or external reference, and prevent duplicate issuing.</p></div><a class="labs-btn" href="' . labs_e(labs_url('/api/training/reward-delivery-reconciliation.php')) . '">Reconciliation API</a></div>';
        echo '<div class="labs-kpis"><div class="labs-kpi"><span>Mode</span><strong>' . (!empty($data['enabled']) ? 'Enabled' : 'Disabled') . '</strong><small>local repair gate</small></div><div class="labs-kpi"><span>Candidates</span><strong>' . (int)($counts['candidates'] ?? 0) . '</strong><small>need review</small></div><div class="labs-kpi"><span>Quarantined</span><strong>' . (int)($counts['quarantined'] ?? 0) . '</strong><small>retry blocked</small></div><div class="labs-kpi"><span>Lookup</span><strong>' . (!empty($data['read_adapter_available']) ? 'Ready' : 'Missing') . '</strong><small>read-only adapter</small></div></div>';
        echo '<div class="labs-actions"><form action="' . labs_e(labs_url('/admin/action-result.php')) . '" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="stage893_reconcile_delivery_batch"><button class="labs-btn labs-btn-primary" type="submit">Reconcile Verified Deliveries</button></form></div>';
        echo '<div class="labs-safe-note">No reward is issued here. Unconfirmed deliveries stay blocked until a read-only Microgifter lookup confirms the external result.</div></section>';
    }
}
