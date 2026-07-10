<?php
/** Stage 893 guarded delivery entrypoints for admin/API callers. */
require_once __DIR__ . '/training-lab-stage893-external-delivery-reconciliation.php';

if (!function_exists('tl_stage893_due_handoff_ids')) {
    function tl_stage893_due_handoff_ids(PDO $pdo, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $pdo->prepare("SELECT id FROM training_reward_handoffs WHERE handoff_status IN ('queued','failed') AND failure_code<>'external_delivery_confirmation_required' AND COALESCE(metadata_json,'') NOT LIKE '%\"reconciliation_required\":true%' AND (next_attempt_at IS NULL OR next_attempt_at <= UTC_TIMESTAMP()) AND attempt_count < ? ORDER BY COALESCE(next_attempt_at, created_at) ASC, id ASC LIMIT " . $limit);
        $stmt->execute([(int)tl_stage890_config()['max_attempts']]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}

if (!function_exists('tl_stage893_reconciliation_candidate_rows_guarded')) {
    function tl_stage893_reconciliation_candidate_rows_guarded(int $limit = 25): array
    {
        if (!tl_stage890_table_ready()) return [];
        $pdo = tl_db();
        if (!$pdo) return [];
        $limit = max(1, min(100, $limit));
        $cutoff = gmdate('Y-m-d H:i:s', time() - (int)tl_stage891_config()['lease_seconds']);
        $sql = "SELECT h.*, re.public_id AS reward_public_id, re.status AS reward_status, re.user_id AS reward_user_id, re.linked_gift_id, re.linked_microgift_instance_id, re.linked_digital_entitlement_id, re.linked_wallet_event_id
                FROM training_reward_handoffs h
                JOIN training_reward_events re ON re.id=h.reward_event_id
                WHERE h.handoff_status<>'cancelled'
                  AND NOT (h.handoff_status='delivered' AND re.status IN ('issued','linked'))
                  AND (h.handoff_status<>'processing' OR h.locked_at IS NULL OR h.locked_at<=?)
                  AND (
                      h.failure_code='external_delivery_confirmation_required'
                      OR (h.handoff_status='delivered' AND re.status NOT IN ('issued','linked'))
                      OR COALESCE(h.metadata_json,'') LIKE '%\"reconciliation_required\":true%'
                  )
                ORDER BY CASE WHEN h.failure_code='external_delivery_confirmation_required' THEN 0 ELSE 1 END, h.updated_at ASC, h.id ASC
                LIMIT " . $limit;
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cutoff]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_stage893_reconcile_handoff_guarded')) {
    function tl_stage893_reconcile_handoff_guarded(array $input): array
    {
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $ref = trim((string)($input['handoff_id'] ?? $input['public_id'] ?? ''));
        if ($ref === '') throw new TlHttpException('Handoff reference is required.', 422, 'handoff_required');
        $pdo = tl_require_db();
        $stmt = $pdo->prepare('SELECT handoff_status, locked_at FROM training_reward_handoffs WHERE public_id=? OR id=? LIMIT 1');
        $stmt->execute([$ref, ctype_digit($ref) ? (int)$ref : 0]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new TlHttpException('Reward handoff was not found.', 404, 'handoff_not_found');
        if ((string)$row['handoff_status'] === 'processing' && !tl_stage891_is_stale_lock($row['locked_at'] ?? null, (int)tl_stage891_config()['lease_seconds'])) {
            throw new TlHttpException('This handoff has an active worker lease and cannot be reconciled yet.', 409, 'active_worker_lease');
        }
        return tl_stage893_reconcile_handoff($input);
    }
}

if (!function_exists('tl_stage893_reconcile_batch_guarded')) {
    function tl_stage893_reconcile_batch_guarded(array $input = []): array
    {
        $config = tl_stage893_config();
        if (empty($config['enabled'])) return ['status'=>'skipped','reason'=>'reconciliation_disabled','selected'=>0,'counts'=>['reconciled'=>0,'unconfirmed'=>0,'deferred'=>0,'errors'=>0,'other'=>0],'results'=>[]];
        $limit = max(1, min((int)$config['batch_size'], (int)($input['limit'] ?? $config['batch_size'])));
        $rows = tl_stage893_reconciliation_candidate_rows_guarded($limit);
        $results = [];
        foreach ($rows as $row) {
            try {
                $results[] = tl_stage893_reconcile_handoff_guarded($input + ['handoff_id'=>(string)$row['id']]);
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

if (!function_exists('tl_stage893_summary_guarded')) {
    function tl_stage893_summary_guarded(): array
    {
        $summary = tl_stage893_summary();
        $rows = tl_stage893_reconciliation_candidate_rows_guarded(100);
        $counts = ['candidates'=>count($rows),'quarantined'=>0,'delivered_mismatch'=>0];
        foreach ($rows as $row) {
            if ((string)($row['failure_code'] ?? '') === 'external_delivery_confirmation_required') $counts['quarantined']++;
            if ((string)($row['handoff_status'] ?? '') === 'delivered' && !in_array((string)($row['reward_status'] ?? ''), ['issued','linked'], true)) $counts['delivered_mismatch']++;
        }
        $summary['counts'] = $counts;
        $summary['candidates'] = array_slice(array_map(static function (array $row): array {
            return [
                'handoff_id'=>(int)$row['id'],
                'public_id'=>(string)$row['public_id'],
                'handoff_status'=>(string)$row['handoff_status'],
                'reward_status'=>(string)$row['reward_status'],
                'failure_code'=>(string)($row['failure_code'] ?? ''),
                'external_reference'=>(string)($row['external_reference'] ?? ''),
                'updated_at'=>(string)($row['updated_at'] ?? ''),
            ];
        }, $rows), 0, 25);
        $summary['active_worker_leases_excluded'] = true;
        $summary['completed_deliveries_excluded'] = true;
        return $summary;
    }
}

if (!function_exists('tl_stage893_render_admin_panel_guarded')) {
    function tl_stage893_render_admin_panel_guarded(): void
    {
        $data = tl_stage893_summary_guarded();
        $counts = (array)$data['counts'];
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 893</span><h2>External Delivery Reconciliation</h2><p class="labs-copy">Quarantine ambiguous adapter successes, verify delivery by idempotency key or external reference, and prevent duplicate issuing.</p></div><a class="labs-btn" href="' . labs_e(labs_url('/api/training/reward-delivery-reconciliation.php')) . '">Reconciliation API</a></div>';
        echo '<div class="labs-kpis"><div class="labs-kpi"><span>Mode</span><strong>' . (!empty($data['enabled']) ? 'Enabled' : 'Disabled') . '</strong><small>local repair gate</small></div><div class="labs-kpi"><span>Candidates</span><strong>' . (int)($counts['candidates'] ?? 0) . '</strong><small>need review</small></div><div class="labs-kpi"><span>Quarantined</span><strong>' . (int)($counts['quarantined'] ?? 0) . '</strong><small>retry blocked</small></div><div class="labs-kpi"><span>Lookup</span><strong>' . (!empty($data['read_adapter_available']) ? 'Ready' : 'Missing') . '</strong><small>read-only adapter</small></div></div>';
        echo '<div class="labs-actions"><form action="' . labs_e(labs_url('/admin/action-result.php')) . '" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="stage893_reconcile_delivery_batch"><button class="labs-btn labs-btn-primary" type="submit">Reconcile Verified Deliveries</button></form></div>';
        echo '<div class="labs-safe-note">No reward is issued here. Active worker leases are excluded, and unconfirmed deliveries remain blocked until a read-only Microgifter lookup confirms the external result.</div></section>';
    }
}

if (!function_exists('tl_stage893_enqueue_reward_event_guarded')) {
    function tl_stage893_enqueue_reward_event_guarded(array $input): array
    {
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $rewardRef = trim((string)($input['reward_event_id'] ?? $input['reward_id'] ?? $input['public_id'] ?? ''));
        if ($rewardRef === '') throw new TlHttpException('Reward event reference is required.', 422, 'reward_event_required');
        $pdo = tl_require_db();
        $reward = tl_stage890_load_reward($pdo, $rewardRef);
        $stmt = $pdo->prepare('SELECT failure_code, metadata_json FROM training_reward_handoffs WHERE reward_event_id=? OR idempotency_key=? LIMIT 1');
        $stmt->execute([(int)$reward['id'], tl_stage890_idempotency_key($reward)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $metadata = tl_stage890_json_decode($row['metadata_json'] ?? null);
            $reconciliationRequired = !empty($metadata['stage893_reconciliation']['reconciliation_required']);
            if ((string)($row['failure_code'] ?? '') === 'external_delivery_confirmation_required' || $reconciliationRequired) {
                throw new TlHttpException('This reward handoff is quarantined pending external delivery confirmation and cannot be refreshed or enqueued.', 409, 'external_delivery_reconciliation_required');
            }
        }
        return tl_stage890_enqueue_reward_event($input);
    }
}

if (!function_exists('tl_stage893_sync_outbox_guarded')) {
    function tl_stage893_sync_outbox_guarded(array $input = []): array
    {
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $pdo = tl_require_db();
        $limit = max(1, min(200, (int)($input['limit'] ?? 100)));
        $sql = "SELECT re.*, rr.reward_type, rr.linked_microgift_template_id, rr.linked_catalog_product_id, rr.settings_json AS reward_rule_settings_json,
                       h.id AS existing_handoff_id, h.failure_code AS existing_failure_code, h.metadata_json AS existing_handoff_metadata
                FROM training_reward_events re
                LEFT JOIN training_reward_rules rr ON rr.id = re.reward_rule_id
                LEFT JOIN training_reward_handoffs h ON h.reward_event_id = re.id
                WHERE re.status IN ('eligible','queued','failed')
                  AND (
                      h.id IS NULL
                      OR (
                          COALESCE(h.failure_code,'') <> 'external_delivery_confirmation_required'
                          AND COALESCE(h.metadata_json,'') NOT LIKE '%\"reconciliation_required\":true%'
                      )
                  )
                ORDER BY re.updated_at ASC, re.id ASC
                LIMIT " . $limit;
        $stmt = $pdo->query($sql);
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        $quarantined = tl_stage891_scalar($pdo, "SELECT COUNT(*) FROM training_reward_handoffs WHERE failure_code='external_delivery_confirmation_required' OR COALESCE(metadata_json,'') LIKE '%\"reconciliation_required\":true%'");
        $results = [];
        foreach ($rows as $reward) {
            if (!tl_stage890_reward_bridge_enabled($reward)) continue;
            try {
                $results[] = tl_stage893_enqueue_reward_event_guarded([
                    'reward_event_id'=>(string)$reward['id'],
                    'actor_user_id'=>(int)($input['actor_user_id'] ?? $input['user_id'] ?? 1),
                ]);
            } catch (Throwable $e) {
                $results[] = ['reward_event_id'=>(int)$reward['id'],'error'=>mb_substr($e->getMessage(), 0, 500)];
            }
        }
        return [
            'scanned'=>count($rows),
            'eligible_for_bridge'=>count($results),
            'quarantined_excluded'=>max(0, $quarantined),
            'results'=>$results,
        ];
    }
}

if (!function_exists('tl_stage893_requeue_handoff_guarded')) {
    function tl_stage893_requeue_handoff_guarded(array $input): array
    {
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $ref = trim((string)($input['handoff_id'] ?? $input['public_id'] ?? ''));
        if ($ref === '') throw new TlHttpException('Handoff reference is required.', 422, 'handoff_required');
        $pdo = tl_require_db();
        $stmt = $pdo->prepare('SELECT failure_code, metadata_json FROM training_reward_handoffs WHERE public_id=? OR id=? LIMIT 1');
        $stmt->execute([$ref, ctype_digit($ref) ? (int)$ref : 0]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $failureCode = (string)($row['failure_code'] ?? '');
        $metadata = tl_stage890_json_decode($row['metadata_json'] ?? null);
        $reconciliationRequired = !empty($metadata['stage893_reconciliation']['reconciliation_required']);
        if ($failureCode === 'external_delivery_confirmation_required' || $reconciliationRequired) {
            throw new TlHttpException('This handoff is quarantined pending external delivery confirmation and cannot be manually requeued.', 409, 'external_delivery_reconciliation_required');
        }
        return tl_stage891_requeue_handoff($input);
    }
}

if (!function_exists('tl_stage893_process_guarded_batch')) {
    function tl_stage893_process_guarded_batch(array $input = []): array
    {
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        $limit = max(1, min(50, (int)($input['limit'] ?? tl_stage890_config()['batch_size'])));
        $recovery = tl_stage891_recover_stale_processing($input + ['actor_user_id'=>$actor]);
        $reconciliation = tl_stage893_reconcile_batch_guarded($input + ['actor_user_id'=>$actor,'automatic'=>true,'limit'=>$limit]);
        $sync = tl_stage893_sync_outbox_guarded($input + ['actor_user_id'=>$actor,'limit'=>max($limit, min(200, $limit * 5))]);
        $pdo = tl_require_db();
        $ids = tl_stage893_due_handoff_ids($pdo, $limit);
        $processed = [];
        foreach ($ids as $id) {
            try {
                $processed[] = tl_stage893_process_handoff_guarded($input + ['handoff_id'=>(string)$id,'actor_user_id'=>$actor]);
            } catch (Throwable $e) {
                $processed[] = ['handoff_id'=>$id,'status'=>'error','error'=>mb_substr($e->getMessage(), 0, 500)];
            }
        }
        return [
            'recovery'=>$recovery,
            'reconciliation'=>$reconciliation,
            'sync'=>$sync,
            'selected'=>count($ids),
            'processed'=>$processed,
            'acceptance'=>tl_stage891_acceptance_summary(),
        ];
    }
}
