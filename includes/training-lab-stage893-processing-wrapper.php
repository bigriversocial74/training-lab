<?php
/** Stage 893 guarded delivery entrypoints for admin/API callers. */
require_once __DIR__ . '/training-lab-stage893-external-delivery-reconciliation.php';

if (!function_exists('tl_stage893_due_handoff_ids')) {
    function tl_stage893_due_handoff_ids(PDO $pdo, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $pdo->prepare("SELECT id FROM training_reward_handoffs WHERE handoff_status IN ('queued','failed') AND failure_code<>'external_delivery_confirmation_required' AND metadata_json NOT LIKE '%\"reconciliation_required\":true%' AND (next_attempt_at IS NULL OR next_attempt_at <= UTC_TIMESTAMP()) AND attempt_count < ? ORDER BY COALESCE(next_attempt_at, created_at) ASC, id ASC LIMIT " . $limit);
        $stmt->execute([(int)tl_stage890_config()['max_attempts']]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
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
        $quarantined = tl_stage891_scalar($pdo, "SELECT COUNT(*) FROM training_reward_handoffs WHERE failure_code='external_delivery_confirmation_required' OR metadata_json LIKE '%\"reconciliation_required\":true%'");
        $results = [];
        foreach ($rows as $reward) {
            if (!tl_stage890_reward_bridge_enabled($reward)) continue;
            try {
                $results[] = tl_stage890_enqueue_reward_event([
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
        $reconciliation = tl_stage893_reconcile_batch($input + ['actor_user_id'=>$actor,'automatic'=>true,'limit'=>$limit]);
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
