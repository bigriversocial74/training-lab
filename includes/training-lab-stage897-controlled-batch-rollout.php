<?php
declare(strict_types=1);

/**
 * Stage 897 — Controlled Batch Rollout v1.
 *
 * Orchestrates two to five Stage 896 single-item pilots sequentially. Every item
 * still uses the same durable handoff, signed issue adapter, idempotency key and
 * immediate read-back. The batch pauses on the first result that is not a
 * verified external delivery. Scheduled processing remains disabled.
 */
require_once __DIR__ . '/training-lab-stage896-pilot-bootstrap.php';

if (!function_exists('tl_stage897_bool')) {
    function tl_stage897_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) return $value;
        if ($value === null || $value === '') return $default;
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }
}

if (!function_exists('tl_stage897_config')) {
    function tl_stage897_config(): array
    {
        $root = function_exists('tl_security_config') ? tl_security_config() : [];
        $enabled = getenv('TL_STAGE897_CONTROLLED_BATCH_ENABLED');
        $batchSize = getenv('TL_STAGE897_MAX_BATCH_SIZE');
        $totalValue = getenv('TL_STAGE897_MAX_TOTAL_VALUE_CENTS');
        $pilotAge = getenv('TL_STAGE897_VERIFIED_PILOT_MAX_AGE_SECONDS');
        $runtime = getenv('TL_STAGE897_MAX_RUNTIME_SECONDS');
        return [
            'enabled'=>tl_stage897_bool($enabled !== false ? $enabled : ($root['stage897_controlled_batch_enabled'] ?? false), false),
            'min_batch_size'=>2,
            'max_batch_size'=>max(2, min(5, (int)($batchSize !== false && $batchSize !== '' ? $batchSize : ($root['stage897_max_batch_size'] ?? 3)))),
            'max_total_value_cents'=>max(0, min(25000, (int)($totalValue !== false && $totalValue !== '' ? $totalValue : ($root['stage897_max_total_value_cents'] ?? 7500)))),
            'verified_pilot_max_age_seconds'=>max(300, min(1209600, (int)($pilotAge !== false && $pilotAge !== '' ? $pilotAge : ($root['stage897_verified_pilot_max_age_seconds'] ?? 604800)))),
            'max_runtime_seconds'=>max(20, min(120, (int)($runtime !== false && $runtime !== '' ? $runtime : ($root['stage897_max_runtime_seconds'] ?? 60)))),
            'confirmation_phrase'=>'ISSUE CONTROLLED BATCH',
            'batch_lock_name'=>'training-lab-stage897-controlled-batch-v1',
        ];
    }
}

if (!function_exists('tl_stage897_json')) {
    function tl_stage897_json($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('tl_stage897_fingerprint')) {
    function tl_stage897_fingerprint($value): string
    {
        $value = trim((string)$value);
        return $value === '' ? '' : substr(hash('sha256', $value), 0, 16);
    }
}

if (!function_exists('tl_stage897_latest_verified_pilot')) {
    function tl_stage897_latest_verified_pilot(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT public_id,actor_user_id,metadata_json,created_at FROM training_events WHERE event_type='stage896_pilot_delivery_verified' ORDER BY id DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (Throwable $e) {
            $row = false;
        }
        if (!$row) {
            return ['found'=>false,'fresh'=>false,'age_seconds'=>null,'event_id'=>'','recorded_at'=>''];
        }
        $created = strtotime((string)($row['created_at'] ?? '')) ?: 0;
        $age = $created > 0 ? max(0, time() - $created) : null;
        $fresh = $age !== null && $age <= (int)tl_stage897_config()['verified_pilot_max_age_seconds'];
        $metadata = tl_stage897_json($row['metadata_json'] ?? null);
        return [
            'found'=>true,
            'fresh'=>$fresh,
            'age_seconds'=>$age,
            'event_id'=>(string)($row['public_id'] ?? ''),
            'recorded_at'=>(string)($row['created_at'] ?? ''),
            'handoff_id'=>(int)($metadata['handoff_id'] ?? 0),
            'external_reference_fingerprint'=>(string)($metadata['external_reference_fingerprint'] ?? ''),
        ];
    }
}

if (!function_exists('tl_stage897_latest_batch_state')) {
    function tl_stage897_latest_batch_state(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT public_id,event_type,metadata_json,created_at FROM training_events WHERE event_type IN ('stage897_controlled_batch_completed','stage897_controlled_batch_paused','stage897_controlled_batch_pause_acknowledged') ORDER BY id DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (Throwable $e) {
            $row = false;
        }
        if (!$row) return ['found'=>false,'status'=>'clear','requires_acknowledgement'=>false,'batch_id'=>'','recorded_at'=>''];
        $metadata = tl_stage897_json($row['metadata_json'] ?? null);
        $eventType = (string)($row['event_type'] ?? '');
        $status = $eventType === 'stage897_controlled_batch_paused'
            ? 'paused'
            : ($eventType === 'stage897_controlled_batch_completed' ? 'completed' : 'acknowledged');
        return [
            'found'=>true,
            'status'=>$status,
            'requires_acknowledgement'=>$status === 'paused',
            'batch_id'=>(string)($metadata['batch_id'] ?? ''),
            'pause_reason'=>(string)($metadata['pause_reason'] ?? ''),
            'selected_count'=>(int)($metadata['selected_count'] ?? 0),
            'verified_count'=>(int)($metadata['verified_count'] ?? 0),
            'recorded_at'=>(string)($row['created_at'] ?? ''),
        ];
    }
}

if (!function_exists('tl_stage897_acquire_lock')) {
    function tl_stage897_acquire_lock(PDO $pdo): bool
    {
        $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute([(string)tl_stage897_config()['batch_lock_name']]);
        return (int)$stmt->fetchColumn() === 1;
    }
}

if (!function_exists('tl_stage897_release_lock')) {
    function tl_stage897_release_lock(PDO $pdo): void
    {
        try {
            $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([(string)tl_stage897_config()['batch_lock_name']]);
        } catch (Throwable $e) {
            // Connection close also releases MySQL advisory locks.
        }
    }
}

if (!function_exists('tl_stage897_readiness')) {
    function tl_stage897_readiness(): array
    {
        $config = tl_stage897_config();
        $pdo = function_exists('tl_db') ? tl_db() : null;
        $stage896 = function_exists('tl_stage896_summary') ? tl_stage896_summary() : ['ready_to_issue'=>false,'active_pilot_count'=>0];
        $verifiedPilot = $pdo instanceof PDO ? tl_stage897_latest_verified_pilot($pdo) : ['found'=>false,'fresh'=>false];
        $batchState = $pdo instanceof PDO ? tl_stage897_latest_batch_state($pdo) : ['requires_acknowledgement'=>false,'status'=>'unknown'];
        $checks = [
            'batch_enabled'=>!empty($config['enabled']),
            'stage896_ready'=>!empty($stage896['ready_to_issue']),
            'verified_stage896_pilot_found'=>!empty($verifiedPilot['found']),
            'verified_stage896_pilot_fresh'=>!empty($verifiedPilot['fresh']),
            'no_active_stage896_pilot'=>(int)($stage896['active_pilot_count'] ?? 0) === 0,
            'previous_batch_cleared'=>empty($batchState['requires_acknowledgement']),
            'scheduled_worker_disabled'=>!empty($stage896['scheduled_worker_disabled']),
        ];
        return [
            'stage'=>'Stage 897 Controlled Batch Rollout v1',
            'ready_to_run'=>count(array_filter($checks)) === count($checks),
            'score'=>(int)round((count(array_filter($checks)) / max(1, count($checks))) * 100),
            'checks'=>$checks,
            'verified_pilot'=>$verifiedPilot,
            'latest_batch'=>$batchState,
            'max_batch_size'=>(int)$config['max_batch_size'],
            'min_batch_size'=>(int)$config['min_batch_size'],
            'max_total_value_cents'=>(int)$config['max_total_value_cents'],
            'max_runtime_seconds'=>(int)$config['max_runtime_seconds'],
            'scheduled_worker_disabled'=>!empty($stage896['scheduled_worker_disabled']),
        ];
    }
}

if (!function_exists('tl_stage897_selection')) {
    function tl_stage897_selection(array $input): array
    {
        $ids = $input['handoff_ids'] ?? [];
        if (is_string($ids)) $ids = preg_split('/\s*,\s*/', trim($ids), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!is_array($ids)) $ids = [];
        $normalized = [];
        foreach ($ids as $id) {
            $id = trim((string)$id);
            if ($id === '' || !ctype_digit($id) || (int)$id < 1) continue;
            $normalized[] = (string)(int)$id;
        }
        $normalized = array_values(array_unique($normalized));
        $confirmations = is_array($input['confirm_microgifter_user_ids'] ?? null)
            ? $input['confirm_microgifter_user_ids']
            : [];
        return ['handoff_ids'=>$normalized,'confirmations'=>$confirmations];
    }
}

if (!function_exists('tl_stage897_preflight')) {
    function tl_stage897_preflight(PDO $pdo, array $selection): array
    {
        $config = tl_stage897_config();
        $ids = (array)($selection['handoff_ids'] ?? []);
        $count = count($ids);
        if ($count < (int)$config['min_batch_size'] || $count > (int)$config['max_batch_size']) {
            throw new TlHttpException('Select between ' . (int)$config['min_batch_size'] . ' and ' . (int)$config['max_batch_size'] . ' unique reward handoffs.', 422, 'stage897_batch_size_invalid');
        }
        $items = [];
        $totalValue = 0;
        foreach ($ids as $sequence => $handoffId) {
            $context = tl_stage896_load_context($pdo, (string)$handoffId, false);
            $handoff = $context['handoff'];
            $reward = $context['reward'];
            $link = $context['link'];
            $confirmed = trim((string)($selection['confirmations'][(string)$handoffId] ?? $selection['confirmations'][(int)$handoffId] ?? ''));
            if ($confirmed === '' || !ctype_digit($confirmed) || (int)$confirmed < 1) {
                throw new TlHttpException('Re-enter the linked Microgifter user ID for every selected reward.', 422, 'stage897_recipient_confirmation_required');
            }
            if (!in_array((string)$handoff['handoff_status'], ['queued','failed','blocked'], true)) {
                throw new TlHttpException('Every selected handoff must be queued, failed, or requirements-blocked.', 409, 'stage897_handoff_not_eligible');
            }
            if ((string)($handoff['failure_code'] ?? '') === 'external_delivery_confirmation_required') {
                throw new TlHttpException('A quarantined handoff cannot enter the controlled batch.', 409, 'stage897_handoff_quarantined');
            }
            if (in_array((string)$reward['status'], ['issued','linked','cancelled'], true)) {
                throw new TlHttpException('A selected reward is already complete or cancelled.', 409, 'stage897_reward_not_eligible');
            }
            if (!$link || empty($link['microgifter_user_id'])) {
                throw new TlHttpException('Every selected reward requires an active linked Microgifter recipient.', 409, 'stage897_account_link_required');
            }
            $linkedUserId = (string)$link['microgifter_user_id'];
            $handoffUserId = trim((string)($handoff['microgifter_user_id'] ?? ''));
            if (!hash_equals($linkedUserId, $confirmed) || ($handoffUserId !== '' && !hash_equals($handoffUserId, $confirmed))) {
                throw new TlHttpException('A confirmed Microgifter user ID does not match the selected reward.', 409, 'stage897_recipient_mismatch');
            }
            $value = (int)($reward['value_cents'] ?? 0);
            if ($value > (int)tl_stage896_config()['max_value_cents'] || (string)($reward['currency'] ?? '') !== 'USD') {
                throw new TlHttpException('Every selected reward must satisfy the Stage 896 value and USD currency limits.', 409, 'stage897_value_or_currency_invalid');
            }
            $totalValue += $value;
            $items[] = [
                'sequence'=>$sequence + 1,
                'handoff_id'=>(int)$handoff['id'],
                'handoff_public_id'=>(string)$handoff['public_id'],
                'reward_event_id'=>(int)$reward['id'],
                'reward_public_id'=>(string)$reward['public_id'],
                'value_cents'=>$value,
                'currency'=>(string)$reward['currency'],
                'confirmed_microgifter_user_id'=>$confirmed,
                'microgifter_user_fingerprint'=>tl_stage897_fingerprint($confirmed),
            ];
        }
        if ($totalValue > (int)$config['max_total_value_cents']) {
            throw new TlHttpException('The selected rewards exceed the Stage 897 cumulative value ceiling.', 409, 'stage897_total_value_exceeded');
        }
        return ['items'=>$items,'selected_count'=>$count,'total_value_cents'=>$totalValue,'currency'=>'USD'];
    }
}

if (!function_exists('tl_stage897_log')) {
    function tl_stage897_log(PDO $pdo, int $actor, string $eventType, array $metadata, ?int $subjectId = null): void
    {
        $safe = [
            'batch_id'=>(string)($metadata['batch_id'] ?? ''),
            'status'=>(string)($metadata['status'] ?? ''),
            'pause_reason'=>(string)($metadata['pause_reason'] ?? ''),
            'selected_count'=>(int)($metadata['selected_count'] ?? 0),
            'processed_count'=>(int)($metadata['processed_count'] ?? 0),
            'verified_count'=>(int)($metadata['verified_count'] ?? 0),
            'total_value_cents'=>(int)($metadata['total_value_cents'] ?? 0),
            'sequence'=>(int)($metadata['sequence'] ?? 0),
            'handoff_id'=>(int)($metadata['handoff_id'] ?? 0),
            'handoff_reference_fingerprint'=>(string)($metadata['handoff_reference_fingerprint'] ?? ''),
            'microgifter_user_fingerprint'=>(string)($metadata['microgifter_user_fingerprint'] ?? ''),
            'delivery_status'=>(string)($metadata['delivery_status'] ?? ''),
            'duration_ms'=>(int)($metadata['duration_ms'] ?? 0),
            'raw_recipient_signature_nonce_payload_and_response_excluded'=>true,
        ];
        tl_log_event($pdo, $actor, $subjectId ? 'reward_event' : 'system', $subjectId, $eventType, $safe);
    }
}

if (!function_exists('tl_stage897_release_pause')) {
    function tl_stage897_release_pause(array $input = []): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        $phrase = trim((string)($input['confirmation_phrase'] ?? ''));
        if (!hash_equals('ACKNOWLEDGE BATCH PAUSE', $phrase)) {
            throw new TlHttpException('Enter the exact Stage 897 acknowledgement phrase.', 422, 'stage897_acknowledgement_invalid');
        }
        $pdo = tl_require_db();
        if (!tl_stage897_acquire_lock($pdo)) throw new TlHttpException('Another Stage 897 operation is running.', 409, 'stage897_operation_locked');
        try {
            $latest = tl_stage897_latest_batch_state($pdo);
            if (empty($latest['requires_acknowledgement'])) {
                throw new TlHttpException('There is no paused Stage 897 batch awaiting acknowledgement.', 404, 'stage897_pause_missing');
            }
            if (function_exists('tl_stage896_active_pilots') && tl_stage896_active_pilots($pdo)) {
                throw new TlHttpException('Resolve the active Stage 896 pilot before acknowledging the batch pause.', 409, 'stage897_active_pilot_unresolved');
            }
            tl_stage897_log($pdo, $actor, 'stage897_controlled_batch_pause_acknowledged', [
                'batch_id'=>(string)$latest['batch_id'],
                'status'=>'acknowledged',
                'selected_count'=>(int)$latest['selected_count'],
                'verified_count'=>(int)$latest['verified_count'],
            ]);
            return ['acknowledged'=>true,'batch_id'=>(string)$latest['batch_id'],'ready_for_new_batch'=>true];
        } finally {
            tl_stage897_release_lock($pdo);
        }
    }
}

if (!function_exists('tl_stage897_run_batch')) {
    function tl_stage897_run_batch(array $input): array
    {
        $readiness = tl_stage897_readiness();
        if (empty($readiness['ready_to_run'])) {
            throw new TlHttpException('Stage 897 is not ready. Verify one Stage 896 pilot, clear any prior pause, and keep the scheduled worker disabled.', 409, 'stage897_not_ready');
        }
        $config = tl_stage897_config();
        $phrase = trim((string)($input['confirmation_phrase'] ?? ''));
        if (!hash_equals((string)$config['confirmation_phrase'], $phrase)) {
            throw new TlHttpException('Enter the exact Stage 897 confirmation phrase.', 422, 'stage897_confirmation_phrase_invalid');
        }
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        $selection = tl_stage897_selection($input);
        $pdo = tl_require_db();
        if (!tl_stage897_acquire_lock($pdo)) throw new TlHttpException('Another Stage 897 batch is already running.', 409, 'stage897_operation_locked');
        $stage896LockHeld = false;
        $batchId = function_exists('tl_uuid') ? tl_uuid() : bin2hex(random_bytes(16));
        $started = microtime(true);
        try {
            // Hold the Stage 896 lock for the entire batch. Individual Stage 896
            // calls acquire/release one recursive reference while this outer lock
            // prevents a concurrent manual pilot from entering between items.
            if (!tl_stage896_acquire_lock($pdo)) {
                throw new TlHttpException('A Stage 896 pilot operation is already running.', 409, 'stage897_stage896_operation_locked');
            }
            $stage896LockHeld = true;
            if (tl_stage896_active_pilots($pdo)) {
                throw new TlHttpException('Resolve the active Stage 896 pilot before starting a controlled batch.', 409, 'stage897_active_pilot_exists');
            }
            $plan = tl_stage897_preflight($pdo, $selection);
            tl_stage897_log($pdo, $actor, 'stage897_controlled_batch_started', [
                'batch_id'=>$batchId,
                'status'=>'running',
                'selected_count'=>(int)$plan['selected_count'],
                'total_value_cents'=>(int)$plan['total_value_cents'],
            ]);

            $results = [];
            $verifiedCount = 0;
            $pauseReason = '';
            foreach ($plan['items'] as $item) {
                if ((microtime(true) - $started) >= (int)$config['max_runtime_seconds']) {
                    $pauseReason = 'runtime_limit_reached_before_next_item';
                    break;
                }
                $itemStarted = microtime(true);
                try {
                    $result = tl_stage896_run_pilot([
                        'handoff_id'=>(string)$item['handoff_id'],
                        'confirm_microgifter_user_id'=>(string)$item['confirmed_microgifter_user_id'],
                        'confirmation_phrase'=>'ISSUE ONE PILOT',
                        'actor_user_id'=>$actor,
                        'stage897_batch_id'=>$batchId,
                    ]);
                    $verified = (string)($result['pilot']['pilot_status'] ?? '') === 'verified'
                        && !empty($result['verification']['confirmed_delivered']);
                    $deliveryStatus = (string)($result['verification']['delivery_status'] ?? 'unknown');
                    $results[] = [
                        'sequence'=>(int)$item['sequence'],
                        'handoff_id'=>(int)$item['handoff_id'],
                        'handoff_public_id'=>(string)$item['handoff_public_id'],
                        'pilot_status'=>(string)($result['pilot']['pilot_status'] ?? 'unknown'),
                        'delivery_status'=>$deliveryStatus,
                        'verified'=>$verified,
                        'duration_ms'=>(int)round((microtime(true) - $itemStarted) * 1000),
                    ];
                    tl_stage897_log($pdo, $actor, $verified ? 'stage897_batch_item_verified' : 'stage897_batch_item_paused', [
                        'batch_id'=>$batchId,
                        'status'=>$verified ? 'verified' : 'paused',
                        'sequence'=>(int)$item['sequence'],
                        'handoff_id'=>(int)$item['handoff_id'],
                        'handoff_reference_fingerprint'=>tl_stage897_fingerprint((string)$item['handoff_public_id']),
                        'microgifter_user_fingerprint'=>(string)$item['microgifter_user_fingerprint'],
                        'delivery_status'=>$deliveryStatus,
                        'duration_ms'=>(int)round((microtime(true) - $itemStarted) * 1000),
                    ], (int)$item['reward_event_id']);
                    if (!$verified) {
                        $pauseReason = 'item_not_verified';
                        break;
                    }
                    $verifiedCount++;
                } catch (Throwable $e) {
                    $pauseReason = 'item_processing_exception';
                    $results[] = [
                        'sequence'=>(int)$item['sequence'],
                        'handoff_id'=>(int)$item['handoff_id'],
                        'handoff_public_id'=>(string)$item['handoff_public_id'],
                        'pilot_status'=>'error',
                        'delivery_status'=>'unknown',
                        'verified'=>false,
                        'error_code'=>'stage897_item_exception',
                        'duration_ms'=>(int)round((microtime(true) - $itemStarted) * 1000),
                    ];
                    tl_stage897_log($pdo, $actor, 'stage897_batch_item_paused', [
                        'batch_id'=>$batchId,
                        'status'=>'paused',
                        'pause_reason'=>$pauseReason,
                        'sequence'=>(int)$item['sequence'],
                        'handoff_id'=>(int)$item['handoff_id'],
                        'handoff_reference_fingerprint'=>tl_stage897_fingerprint((string)$item['handoff_public_id']),
                        'microgifter_user_fingerprint'=>(string)$item['microgifter_user_fingerprint'],
                        'delivery_status'=>'unknown',
                        'duration_ms'=>(int)round((microtime(true) - $itemStarted) * 1000),
                    ], (int)$item['reward_event_id']);
                    break;
                }
            }

            $processedCount = count($results);
            $completed = $verifiedCount === (int)$plan['selected_count'];
            $status = $completed ? 'completed' : 'paused';
            if (!$completed && $pauseReason === '') $pauseReason = 'batch_incomplete';
            tl_stage897_log($pdo, $actor, $completed ? 'stage897_controlled_batch_completed' : 'stage897_controlled_batch_paused', [
                'batch_id'=>$batchId,
                'status'=>$status,
                'pause_reason'=>$pauseReason,
                'selected_count'=>(int)$plan['selected_count'],
                'processed_count'=>$processedCount,
                'verified_count'=>$verifiedCount,
                'total_value_cents'=>(int)$plan['total_value_cents'],
                'duration_ms'=>(int)round((microtime(true) - $started) * 1000),
            ]);
            return [
                'batch_id'=>$batchId,
                'status'=>$status,
                'pause_reason'=>$pauseReason,
                'selected_count'=>(int)$plan['selected_count'],
                'processed_count'=>$processedCount,
                'verified_count'=>$verifiedCount,
                'total_value_cents'=>(int)$plan['total_value_cents'],
                'currency'=>'USD',
                'duration_ms'=>(int)round((microtime(true) - $started) * 1000),
                'results'=>$results,
                'scheduled_worker_remained_disabled'=>true,
                'raw_recipient_signature_nonce_payload_and_response_excluded'=>true,
            ];
        } finally {
            if ($stage896LockHeld) tl_stage896_release_lock($pdo);
            tl_stage897_release_lock($pdo);
        }
    }
}

if (!function_exists('tl_stage897_summary')) {
    function tl_stage897_summary(): array
    {
        $readiness = tl_stage897_readiness();
        $candidates = function_exists('tl_stage896_candidate_rows') ? tl_stage896_candidate_rows(50) : [];
        return $readiness + [
            'candidate_count'=>count($candidates),
            'candidates'=>array_map(static function (array $row): array {
                return [
                    'handoff_id'=>(int)$row['id'],
                    'handoff_public_id'=>(string)$row['public_id'],
                    'reward_public_id'=>(string)$row['reward_public_id'],
                    'reward_label'=>(string)$row['reward_label'],
                    'participant_label'=>(string)$row['participant_label'],
                    'value_cents'=>(int)$row['value_cents'],
                    'currency'=>(string)$row['currency'],
                    'microgifter_user_id'=>(string)$row['microgifter_user_id'],
                ];
            }, array_slice($candidates, 0, 25)),
            'safe_boundaries'=>[
                'stage896_verified_pilot_required'=>true,
                'two_to_five_selected_items'=>true,
                'sequential_single_item_processing'=>true,
                'stage896_lock_held_for_entire_batch'=>true,
                'stop_on_first_non_verified_result'=>true,
                'immediate_readback_per_item'=>true,
                'cumulative_value_ceiling'=>true,
                'scheduled_worker_must_remain_disabled'=>true,
                'pause_requires_operator_acknowledgement'=>true,
                'no_new_microgifter_endpoint'=>true,
                'no_new_sql'=>true,
            ],
        ];
    }
}

if (!function_exists('tl_stage897_render_admin_page')) {
    function tl_stage897_render_admin_page(?array $result = null, string $error = ''): void
    {
        if (!function_exists('labs_e') || !function_exists('labs_url')) return;
        $summary = tl_stage897_summary();
        $latest = (array)($summary['latest_batch'] ?? []);
        echo '<section class="labs-page-title"><div><span class="labs-eyebrow">Stage 897</span><h1>Controlled Batch Rollout</h1><p class="labs-copy">Process a small operator-selected set sequentially. Every reward remains a complete Stage 896 pilot and the batch stops on the first result that is not externally verified.</p></div><a class="labs-btn" href="' . labs_e(labs_url('/api/training/reward-batch.php')) . '">Batch JSON</a></section>';
        echo '<section class="labs-kpis">';
        echo '<div class="labs-kpi"><span>Readiness</span><strong>' . (int)$summary['score'] . '%</strong><small>controlled rollout gates</small></div>';
        echo '<div class="labs-kpi"><span>Batch limit</span><strong>' . (int)$summary['max_batch_size'] . '</strong><small>sequential rewards</small></div>';
        echo '<div class="labs-kpi"><span>Total ceiling</span><strong>$' . number_format(((int)$summary['max_total_value_cents']) / 100, 2) . '</strong><small>USD per batch</small></div>';
        echo '<div class="labs-kpi"><span>Worker</span><strong>' . (!empty($summary['scheduled_worker_disabled']) ? 'Disabled' : 'STOP') . '</strong><small>must remain disabled</small></div>';
        echo '</section>';
        if ($error !== '') echo '<section class="labs-card labs-error-card"><h2>Batch needs attention</h2><p class="labs-copy">' . labs_e($error) . '</p></section>';
        if ($result) {
            echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Batch result</span><h2>' . labs_e((string)($result['status'] ?? 'complete')) . '</h2><p class="labs-copy">Verified ' . (int)($result['verified_count'] ?? 0) . ' of ' . (int)($result['selected_count'] ?? 0) . ' selected rewards. Pause reason: ' . labs_e((string)($result['pause_reason'] ?? 'none')) . '.</p></div></div></section>';
        }
        if (!empty($latest['requires_acknowledgement'])) {
            echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Paused rollout</span><h2>Operator review required</h2><p class="labs-copy">Batch ' . labs_e((string)$latest['batch_id']) . ' paused after ' . (int)$latest['verified_count'] . ' verified item(s). Resolve any active Stage 896 pilot, then acknowledge the pause before another batch.</p></div></div>';
            echo '<form method="post" class="labs-stage30-form">' . (function_exists('tl_security_csrf_field') ? tl_security_csrf_field() : '') . '<input type="hidden" name="training_action" value="stage897_acknowledge_batch_pause"><label>Type ACKNOWLEDGE BATCH PAUSE<input type="text" name="confirmation_phrase" autocomplete="off" required></label><button class="labs-btn labs-btn-primary" type="submit">Acknowledge Pause</button></form></section>';
        }
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Sequential rollout</span><h2>Select ' . (int)$summary['min_batch_size'] . '–' . (int)$summary['max_batch_size'] . ' rewards</h2><p class="labs-copy">Re-enter each linked Microgifter user ID. The system validates the whole plan before the first issue request.</p></div></div>';
        $candidates = (array)$summary['candidates'];
        echo '<form method="post" class="labs-stage30-form">' . (function_exists('tl_security_csrf_field') ? tl_security_csrf_field() : '') . '<input type="hidden" name="training_action" value="stage897_run_controlled_batch">';
        foreach ($candidates as $candidate) {
            $id = (int)$candidate['handoff_id'];
            $label = (string)$candidate['reward_label'] . ' · ' . (string)$candidate['participant_label'] . ' · $' . number_format(((int)$candidate['value_cents']) / 100, 2);
            echo '<div class="labs-card"><label><input type="checkbox" name="handoff_ids[]" value="' . $id . '"> ' . labs_e($label) . '</label><label>Confirm linked Microgifter user ID for this reward<input type="number" min="1" name="confirm_microgifter_user_ids[' . $id . ']" value=""></label></div>';
        }
        echo '<label>Type ISSUE CONTROLLED BATCH<input type="text" name="confirmation_phrase" autocomplete="off" required></label>';
        echo '<button class="labs-btn labs-btn-primary" type="submit"' . (empty($summary['ready_to_run']) || count($candidates) < (int)$summary['min_batch_size'] ? ' disabled' : '') . '>Run Controlled Batch</button></form>';
        if (!$candidates) echo '<div class="labs-empty-state"><strong>No eligible rollout handoffs</strong><p>Create or enqueue low-value rewards and verify active account links.</p></div>';
        echo '</section>';
        echo '<section class="labs-safe-note">Stage 897 does not enable cron. One verified Stage 896 pilot is required first. Every item still uses the pilot endpoint, durable idempotency key, lease-owned processor, and immediate signed read-back.</section>';
    }
}

if (!function_exists('tl_stage897_render_reward_bridge_panel')) {
    function tl_stage897_render_reward_bridge_panel(): void
    {
        if (!function_exists('labs_e') || !function_exists('labs_url')) return;
        $summary = tl_stage897_summary();
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 897</span><h2>Controlled Batch Rollout</h2><p class="labs-copy">Two to five sequential Stage 896 pilots with immediate read-back and a hard stop on the first unverified result.</p></div><a class="labs-btn labs-btn-primary" href="' . labs_e(labs_url('/admin/reward-batch.php')) . '">Open Batch Control</a></div>';
        echo '<div class="labs-kpis"><div class="labs-kpi"><span>Readiness</span><strong>' . (int)$summary['score'] . '%</strong></div><div class="labs-kpi"><span>Limit</span><strong>' . (int)$summary['max_batch_size'] . '</strong></div><div class="labs-kpi"><span>Candidates</span><strong>' . (int)$summary['candidate_count'] . '</strong></div><div class="labs-kpi"><span>Worker</span><strong>' . (!empty($summary['scheduled_worker_disabled']) ? 'Off' : 'STOP') . '</strong></div></div></section>';
    }
}
