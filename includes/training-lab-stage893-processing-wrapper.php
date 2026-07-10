<?php
/** Stage 893 guarded delivery entrypoints for admin/API callers. */
require_once __DIR__ . '/training-lab-stage893-external-delivery-reconciliation.php';

if (!function_exists('tl_stage893_due_handoff_ids')) {
    function tl_stage893_due_handoff_ids(PDO $pdo, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $pdo->prepare("SELECT id FROM training_reward_handoffs WHERE handoff_status IN ('queued','failed') AND failure_code<>'external_delivery_confirmation_required' AND (next_attempt_at IS NULL OR next_attempt_at <= UTC_TIMESTAMP()) AND attempt_count < ? ORDER BY COALESCE(next_attempt_at, created_at) ASC, id ASC LIMIT " . $limit);
        $stmt->execute([(int)tl_stage890_config()['max_attempts']]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
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
        $sync = tl_stage890_sync_outbox($input + ['actor_user_id'=>$actor,'limit'=>max($limit, min(200, $limit * 5))]);
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
