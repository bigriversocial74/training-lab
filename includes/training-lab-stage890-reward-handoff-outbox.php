<?php
/**
 * Stage 890 — durable, idempotent Training Lab reward handoff outbox.
 *
 * Training Lab owns only the delivery record and audit state. A real Microgifter
 * mutation is allowed only when every explicit production gate is open and a
 * direct adapter function is installed. No developer key is stored or exposed.
 */
require_once __DIR__ . '/training-lab-microgifter-rewards.php';
require_once __DIR__ . '/training-lab-stage886-account-integration.php';

if (!function_exists('tl_stage890_bool')) {
    function tl_stage890_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) return $value;
        if ($value === null || $value === '') return $default;
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }
}

if (!function_exists('tl_stage890_config')) {
    function tl_stage890_config(): array
    {
        $root = function_exists('tl_security_config') ? tl_security_config() : [];
        $enabled = getenv('TL_REWARD_HANDOFF_PROCESSING_ENABLED');
        $batch = getenv('TL_REWARD_HANDOFF_BATCH_SIZE');
        $attempts = getenv('TL_REWARD_HANDOFF_MAX_ATTEMPTS');
        $retry = getenv('TL_REWARD_HANDOFF_RETRY_BASE_SECONDS');
        return [
            'processing_enabled' => tl_stage890_bool(
                $enabled !== false ? $enabled : ($root['reward_handoff_processing_enabled'] ?? false),
                false
            ),
            'batch_size' => max(1, min(50, (int)($batch !== false && $batch !== '' ? $batch : ($root['reward_handoff_batch_size'] ?? 10)))),
            'max_attempts' => max(1, min(20, (int)($attempts !== false && $attempts !== '' ? $attempts : ($root['reward_handoff_max_attempts'] ?? 5)))),
            'retry_base_seconds' => max(60, min(86400, (int)($retry !== false && $retry !== '' ? $retry : ($root['reward_handoff_retry_base_seconds'] ?? 300)))),
        ];
    }
}

if (!function_exists('tl_stage890_table_ready')) {
    function tl_stage890_table_ready(): bool
    {
        return function_exists('tl_table_exists') && tl_table_exists('training_reward_handoffs');
    }
}

if (!function_exists('tl_stage890_json_decode')) {
    function tl_stage890_json_decode($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('tl_stage890_json')) {
    function tl_stage890_json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}

if (!function_exists('tl_stage890_retry_delay')) {
    function tl_stage890_retry_delay(int $attempt): int
    {
        $attempt = max(1, $attempt);
        $base = (int)tl_stage890_config()['retry_base_seconds'];
        return min(86400, $base * (2 ** min(8, $attempt - 1)));
    }
}

if (!function_exists('tl_stage890_adapter_state')) {
    function tl_stage890_adapter_state(): array
    {
        $bridge = function_exists('tl_mg_rewards_config') ? tl_mg_rewards_config() : [];
        $direct = array_values(array_filter((array)($bridge['direct_adapter_functions']['issue_or_claim'] ?? [])));
        $productionEnabled = function_exists('tl_stage880_production_issuing_enabled')
            ? tl_stage880_production_issuing_enabled()
            : tl_stage890_bool(getenv('TL_MICROGIFTER_PRODUCTION_ISSUING_ENABLED'), false);
        $config = tl_stage890_config();
        $keyPresent = !empty($bridge['developer_api_key_present']);
        return [
            'processing_enabled' => !empty($config['processing_enabled']),
            'production_issuing_enabled' => $productionEnabled,
            'developer_key_present' => $keyPresent,
            'direct_adapter_functions' => $direct,
            'adapter_mode' => (string)($bridge['mode'] ?? 'adapter_pending'),
            'can_process' => !empty($config['processing_enabled']) && $productionEnabled && $keyPresent && count($direct) > 0,
        ];
    }
}

if (!function_exists('tl_stage890_load_reward')) {
    function tl_stage890_load_reward(PDO $pdo, string $rewardRef): array
    {
        $rewardRef = trim($rewardRef);
        if ($rewardRef === '') throw new TlHttpException('Reward event reference is required.', 422, 'reward_event_required');
        $stmt = $pdo->prepare("SELECT re.*, rr.reward_label, rr.reward_type, rr.rule_name, rr.linked_microgift_template_id, rr.linked_catalog_product_id, rr.settings_json AS reward_rule_settings_json, c.title AS campaign_title, c.public_id AS campaign_public_id, COALESCE(tp.participant_label, CONCAT('User #', re.user_id)) AS participant_label FROM training_reward_events re LEFT JOIN training_reward_rules rr ON rr.id = re.reward_rule_id LEFT JOIN training_campaigns c ON c.id = re.campaign_id LEFT JOIN training_participants tp ON tp.id = re.participant_id WHERE re.public_id = ? OR re.id = ? LIMIT 1");
        $stmt->execute([$rewardRef, ctype_digit($rewardRef) ? (int)$rewardRef : 0]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new TlHttpException('Reward event was not found.', 404, 'reward_event_not_found');
        return $row;
    }
}

if (!function_exists('tl_stage890_find_account_link')) {
    function tl_stage890_find_account_link(PDO $pdo, array $reward): ?array
    {
        if (!function_exists('tl_stage886_tables_ready') || !tl_stage886_tables_ready()) return null;
        $userId = (string)($reward['user_id'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM training_account_links WHERE link_status = 'active' AND (training_user_id = ? OR microgifter_user_id = ?) ORDER BY last_authenticated_at DESC, id DESC LIMIT 1");
        $stmt->execute([(int)$userId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('tl_stage890_idempotency_key')) {
    function tl_stage890_idempotency_key(array $reward): string
    {
        return hash('sha256', 'training-reward-handoff-v1|' . (string)($reward['id'] ?? '') . '|' . (string)($reward['public_id'] ?? ''));
    }
}

if (!function_exists('tl_stage890_payload')) {
    function tl_stage890_payload(array $reward, ?array $link, string $idempotencyKey): array
    {
        return [
            'contract' => 'training_lab_reward_handoff_v1',
            'source' => 'training_lab',
            'idempotency_key' => $idempotencyKey,
            'training_reward_event_id' => (int)($reward['id'] ?? 0),
            'training_reward_public_id' => (string)($reward['public_id'] ?? ''),
            'training_campaign_id' => (int)($reward['campaign_id'] ?? 0),
            'training_campaign_public_id' => (string)($reward['campaign_public_id'] ?? ''),
            'training_campaign_title' => (string)($reward['campaign_title'] ?? ''),
            'training_participant_id' => (int)($reward['participant_id'] ?? 0),
            'training_user_id' => (int)($reward['user_id'] ?? 0),
            'account_link_public_id' => (string)($link['public_id'] ?? ''),
            'microgifter_user_id' => (string)($link['microgifter_user_id'] ?? ''),
            'recipient_email' => (string)($link['email'] ?? ''),
            'recipient_name' => (string)($link['display_name'] ?? $reward['participant_label'] ?? ''),
            'merchant_context' => (string)($link['merchant_context'] ?? ''),
            'organization_context' => (string)($link['organization_context'] ?? ''),
            'reward_label' => (string)($reward['reward_label'] ?? $reward['rule_name'] ?? 'Training Reward'),
            'reward_type' => (string)($reward['reward_type'] ?? 'microgift'),
            'value_cents' => max(0, (int)($reward['value_cents'] ?? 0)),
            'currency' => (string)($reward['currency'] ?? 'USD'),
            'linked_microgift_template_id' => $reward['linked_microgift_template_id'] ?? null,
            'linked_catalog_product_id' => $reward['linked_catalog_product_id'] ?? null,
            'eligibility_reason' => (string)($reward['eligibility_reason'] ?? ''),
            'no_password_claims' => true,
            'no_training_lab_wallet_mutation' => true,
        ];
    }
}

if (!function_exists('tl_stage890_blockers')) {
    function tl_stage890_blockers(array $reward, ?array $link, array $adapter): array
    {
        $blocked = [];
        $status = (string)($reward['status'] ?? 'eligible');
        if ($status === 'cancelled') $blocked[] = 'reward_cancelled';
        if (in_array($status, ['issued','linked'], true)) $blocked[] = 'reward_already_delivered';
        if (!$link) $blocked[] = 'active_account_link_required';
        if (empty($adapter['processing_enabled'])) $blocked[] = 'outbox_processing_disabled';
        if (empty($adapter['production_issuing_enabled'])) $blocked[] = 'production_issuing_disabled';
        if (empty($adapter['developer_key_present'])) $blocked[] = 'developer_key_missing';
        if (empty($adapter['direct_adapter_functions'])) $blocked[] = 'direct_adapter_missing';
        return array_values(array_unique($blocked));
    }
}

if (!function_exists('tl_stage890_update_reward_trace')) {
    function tl_stage890_update_reward_trace(PDO $pdo, array $reward, array $trace, ?string $status = null): void
    {
        $metadata = tl_stage890_json_decode($reward['metadata_json'] ?? null);
        $metadata['stage890_handoff'] = array_merge((array)($metadata['stage890_handoff'] ?? []), $trace);
        if ($status !== null) {
            $stmt = $pdo->prepare('UPDATE training_reward_events SET status = ?, metadata_json = ? WHERE id = ?');
            $stmt->execute([$status, tl_stage890_json($metadata), (int)$reward['id']]);
        } else {
            $stmt = $pdo->prepare('UPDATE training_reward_events SET metadata_json = ? WHERE id = ?');
            $stmt->execute([tl_stage890_json($metadata), (int)$reward['id']]);
        }
    }
}

if (!function_exists('tl_stage890_enqueue_reward_event')) {
    function tl_stage890_enqueue_reward_event(array $input): array
    {
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $pdo = tl_require_db();
        $rewardRef = trim((string)($input['reward_event_id'] ?? $input['reward_id'] ?? $input['public_id'] ?? ''));
        $pdo->beginTransaction();
        try {
            $reward = tl_stage890_load_reward($pdo, $rewardRef);
            $lock = $pdo->prepare('SELECT id FROM training_reward_events WHERE id = ? FOR UPDATE');
            $lock->execute([(int)$reward['id']]);
            $link = tl_stage890_find_account_link($pdo, $reward);
            $adapter = tl_stage890_adapter_state();
            $blockers = tl_stage890_blockers($reward, $link, $adapter);
            $idempotencyKey = tl_stage890_idempotency_key($reward);
            $payload = tl_stage890_payload($reward, $link, $idempotencyKey);
            $desiredStatus = $blockers ? 'blocked' : 'queued';
            $existingStmt = $pdo->prepare('SELECT * FROM training_reward_handoffs WHERE reward_event_id = ? OR idempotency_key = ? LIMIT 1 FOR UPDATE');
            $existingStmt->execute([(int)$reward['id'], $idempotencyKey]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $current = (string)$existing['handoff_status'];
                if (!in_array($current, ['delivered','cancelled','processing'], true)) {
                    $stmt = $pdo->prepare('UPDATE training_reward_handoffs SET account_link_id=?, microgifter_user_id=?, handoff_status=?, adapter_mode=?, failure_code=?, failure_message=?, payload_json=?, metadata_json=?, next_attempt_at=NULL, locked_at=NULL, locked_by=NULL WHERE id=?');
                    $stmt->execute([
                        $link['id'] ?? null,
                        $link['microgifter_user_id'] ?? null,
                        $desiredStatus,
                        (string)$adapter['adapter_mode'],
                        $blockers ? 'requirements_blocked' : null,
                        $blockers ? implode(', ', $blockers) : null,
                        tl_stage890_json($payload),
                        tl_stage890_json(['blockers'=>$blockers,'last_refreshed_at'=>gmdate('c')]),
                        (int)$existing['id'],
                    ]);
                    $current = $desiredStatus;
                }
                $handoffId = (int)$existing['id'];
                $publicId = (string)$existing['public_id'];
                $idempotent = true;
            } else {
                $publicId = tl_uuid();
                $stmt = $pdo->prepare('INSERT INTO training_reward_handoffs (public_id, reward_event_id, account_link_id, microgifter_user_id, idempotency_key, handoff_status, adapter_mode, failure_code, failure_message, payload_json, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $publicId,
                    (int)$reward['id'],
                    $link['id'] ?? null,
                    $link['microgifter_user_id'] ?? null,
                    $idempotencyKey,
                    $desiredStatus,
                    (string)$adapter['adapter_mode'],
                    $blockers ? 'requirements_blocked' : null,
                    $blockers ? implode(', ', $blockers) : null,
                    tl_stage890_json($payload),
                    tl_stage890_json(['blockers'=>$blockers,'created_by_user_id'=>(int)($input['actor_user_id'] ?? $input['user_id'] ?? 1)]),
                ]);
                $handoffId = (int)$pdo->lastInsertId();
                $idempotent = false;
            }
            $trace = ['handoff_id'=>$handoffId,'handoff_public_id'=>$publicId,'idempotency_key'=>$idempotencyKey,'status'=>$desiredStatus,'blockers'=>$blockers,'queued_at'=>gmdate('c')];
            tl_stage890_update_reward_trace($pdo, $reward, $trace, $desiredStatus === 'queued' && in_array((string)$reward['status'], ['eligible','failed'], true) ? 'queued' : null);
            tl_log_event($pdo, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1), 'reward_event', (int)$reward['id'], 'stage890_reward_handoff_enqueued', ['handoff_id'=>$handoffId,'status'=>$desiredStatus,'blockers'=>$blockers,'idempotent'=>$idempotent]);
            $pdo->commit();
            return ['handoff_id'=>$handoffId,'public_id'=>$publicId,'reward_event_id'=>(int)$reward['id'],'handoff_status'=>$desiredStatus,'blockers'=>$blockers,'idempotent'=>$idempotent];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_stage890_reward_bridge_enabled')) {
    function tl_stage890_reward_bridge_enabled(array $reward): bool
    {
        $settings = tl_stage890_json_decode($reward['reward_rule_settings_json'] ?? null);
        $type = (string)($reward['reward_type'] ?? '');
        return !empty($settings['microgifter_reward_bridge'])
            || !empty($reward['linked_microgift_template_id'])
            || !empty($reward['linked_catalog_product_id'])
            || in_array($type, ['microgift','wallet_credit_preview'], true);
    }
}

if (!function_exists('tl_stage890_sync_outbox')) {
    function tl_stage890_sync_outbox(array $input = []): array
    {
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $pdo = tl_require_db();
        $limit = max(1, min(200, (int)($input['limit'] ?? 100)));
        $stmt = $pdo->query("SELECT re.*, rr.reward_type, rr.linked_microgift_template_id, rr.linked_catalog_product_id, rr.settings_json AS reward_rule_settings_json FROM training_reward_events re LEFT JOIN training_reward_rules rr ON rr.id = re.reward_rule_id WHERE re.status IN ('eligible','queued','failed') ORDER BY re.updated_at ASC, re.id ASC LIMIT " . $limit);
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        $results = [];
        foreach ($rows as $reward) {
            if (!tl_stage890_reward_bridge_enabled($reward)) continue;
            try {
                $results[] = tl_stage890_enqueue_reward_event([
                    'reward_event_id'=>(string)$reward['id'],
                    'actor_user_id'=>(int)($input['actor_user_id'] ?? $input['user_id'] ?? 1),
                ]);
            } catch (Throwable $e) {
                $results[] = ['reward_event_id'=>(int)$reward['id'],'error'=>$e->getMessage()];
            }
        }
        return ['scanned'=>count($rows),'eligible_for_bridge'=>count($results),'results'=>$results];
    }
}

if (!function_exists('tl_stage890_call_adapter')) {
    function tl_stage890_call_adapter(array $payload): array
    {
        $state = tl_stage890_adapter_state();
        if (empty($state['can_process'])) return ['ok'=>false,'code'=>'processing_gate_closed','message'=>'Production handoff processing gates are not all open.'];
        foreach ((array)$state['direct_adapter_functions'] as $fn) {
            if (!function_exists($fn)) continue;
            try {
                $result = $fn($payload + ['developer_api_key_present'=>true]);
                if (is_array($result) && ((array_key_exists('ok', $result) && !$result['ok']) || (array_key_exists('success', $result) && !$result['success']))) {
                    return ['ok'=>false,'code'=>'adapter_rejected','adapter'=>$fn,'message'=>(string)($result['error'] ?? $result['message'] ?? 'Adapter rejected the handoff.'),'result'=>$result];
                }
                return ['ok'=>true,'adapter'=>$fn,'result'=>is_array($result) ? $result : ['raw'=>$result]];
            } catch (Throwable $e) {
                return ['ok'=>false,'code'=>'adapter_exception','adapter'=>$fn,'message'=>$e->getMessage()];
            }
        }
        return ['ok'=>false,'code'=>'adapter_unavailable','message'=>'No direct reward issue adapter is available.'];
    }
}

if (!function_exists('tl_stage890_external_reference')) {
    function tl_stage890_external_reference(array $result): string
    {
        foreach (['gift_id','linked_gift_id','microgift_instance_id','linked_microgift_instance_id','digital_entitlement_id','linked_digital_entitlement_id','wallet_event_id','linked_wallet_event_id','claim_id','id'] as $key) {
            if (isset($result[$key]) && trim((string)$result[$key]) !== '') return mb_substr(trim((string)$result[$key]), 0, 191);
        }
        return '';
    }
}

if (!function_exists('tl_stage890_process_handoff')) {
    function tl_stage890_process_handoff(array $input): array
    {
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $pdo = tl_require_db();
        $ref = trim((string)($input['handoff_id'] ?? $input['public_id'] ?? ''));
        if ($ref === '') throw new TlHttpException('Handoff reference is required.', 422, 'handoff_required');
        $actor = (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1);
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
                $update = $pdo->prepare("UPDATE training_reward_handoffs SET handoff_status='blocked', failure_code='requirements_blocked', failure_message=?, locked_at=NULL, locked_by=NULL, next_attempt_at=NULL, metadata_json=? WHERE id=?");
                $update->execute([implode(', ', $blockers), tl_stage890_json(['blockers'=>$blockers,'last_checked_at'=>gmdate('c')]), (int)$handoff['id']]);
                $pdo->commit();
                return ['handoff_id'=>(int)$handoff['id'],'handoff_status'=>'blocked','blockers'=>$blockers];
            }
            $attempt = (int)$handoff['attempt_count'] + 1;
            $worker = 'training-lab:' . (function_exists('tl_security_request_id') ? tl_security_request_id() : bin2hex(random_bytes(8)));
            $update = $pdo->prepare("UPDATE training_reward_handoffs SET handoff_status='processing', attempt_count=?, last_attempt_at=UTC_TIMESTAMP(), locked_at=UTC_TIMESTAMP(), locked_by=?, adapter_mode=?, failure_code=NULL, failure_message=NULL WHERE id=?");
            $update->execute([$attempt, $worker, (string)$adapter['adapter_mode'], (int)$handoff['id']]);
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
            $reward = tl_stage890_load_reward($pdo, (string)$current['reward_event_id']);
            if (!empty($adapterResult['ok'])) {
                $result = is_array($adapterResult['result'] ?? null) ? $adapterResult['result'] : [];
                $external = tl_stage890_external_reference($result);
                $stmt = $pdo->prepare("UPDATE training_reward_handoffs SET handoff_status='delivered', adapter_name=?, delivered_at=UTC_TIMESTAMP(), external_reference=?, response_json=?, failure_code=NULL, failure_message=NULL, next_attempt_at=NULL, locked_at=NULL, locked_by=NULL WHERE id=?");
                $stmt->execute([(string)($adapterResult['adapter'] ?? ''), $external !== '' ? $external : null, tl_stage890_json($adapterResult), (int)$current['id']]);
                $metadata = tl_stage890_json_decode($reward['metadata_json'] ?? null);
                $metadata['claim_status'] = $external !== '' ? 'linked_to_microgifter' : 'issued_by_microgifter_adapter';
                $metadata['stage890_handoff'] = ['handoff_id'=>(int)$current['id'],'status'=>'delivered','adapter'=>(string)($adapterResult['adapter'] ?? ''),'external_reference'=>$external,'delivered_at'=>gmdate('c'),'idempotency_key'=>(string)$current['idempotency_key']];
                $linkedGift = $result['gift_id'] ?? $result['linked_gift_id'] ?? ($reward['linked_gift_id'] ?: null);
                $linkedMicrogift = $result['microgift_instance_id'] ?? $result['linked_microgift_instance_id'] ?? ($reward['linked_microgift_instance_id'] ?: null);
                $linkedEntitlement = $result['digital_entitlement_id'] ?? $result['linked_digital_entitlement_id'] ?? ($reward['linked_digital_entitlement_id'] ?: null);
                $linkedWallet = $result['wallet_event_id'] ?? $result['linked_wallet_event_id'] ?? ($reward['linked_wallet_event_id'] ?: null);
                $rewardStatus = ($linkedGift || $linkedMicrogift || $linkedEntitlement || $linkedWallet || $external !== '') ? 'linked' : 'issued';
                $rewardUpdate = $pdo->prepare('UPDATE training_reward_events SET status=?, linked_gift_id=?, linked_microgift_instance_id=?, linked_digital_entitlement_id=?, linked_wallet_event_id=?, issued_at=COALESCE(issued_at, CURRENT_TIMESTAMP), failure_message=NULL, metadata_json=? WHERE id=?');
                $rewardUpdate->execute([$rewardStatus, $linkedGift, $linkedMicrogift, $linkedEntitlement, $linkedWallet, tl_stage890_json($metadata), (int)$reward['id']]);
                tl_log_event($pdo, $actor, 'reward_event', (int)$reward['id'], 'stage890_reward_handoff_delivered', ['handoff_id'=>(int)$current['id'],'adapter'=>$adapterResult['adapter'] ?? null,'external_reference'=>$external]);
                $status = 'delivered';
                $nextAttempt = null;
            } else {
                $attempt = (int)$current['attempt_count'];
                $config = tl_stage890_config();
                $retryable = $attempt < (int)$config['max_attempts'] && !in_array((string)($adapterResult['code'] ?? ''), ['processing_gate_closed','adapter_unavailable'], true);
                $status = $retryable ? 'failed' : ((string)($adapterResult['code'] ?? '') === 'processing_gate_closed' || (string)($adapterResult['code'] ?? '') === 'adapter_unavailable' ? 'blocked' : 'failed');
                $nextAttempt = $retryable ? gmdate('Y-m-d H:i:s', time() + tl_stage890_retry_delay($attempt)) : null;
                $stmt = $pdo->prepare('UPDATE training_reward_handoffs SET handoff_status=?, adapter_name=?, response_json=?, failure_code=?, failure_message=?, next_attempt_at=?, locked_at=NULL, locked_by=NULL WHERE id=?');
                $stmt->execute([$status, (string)($adapterResult['adapter'] ?? ''), tl_stage890_json($adapterResult), (string)($adapterResult['code'] ?? 'adapter_failed'), mb_substr((string)($adapterResult['message'] ?? 'Adapter delivery failed.'), 0, 500), $nextAttempt, (int)$current['id']]);
                $metadata = tl_stage890_json_decode($reward['metadata_json'] ?? null);
                $metadata['claim_status'] = 'claim_failed_adapter_error';
                $metadata['stage890_handoff'] = ['handoff_id'=>(int)$current['id'],'status'=>$status,'attempt_count'=>$attempt,'next_attempt_at'=>$nextAttempt,'failure_code'=>$adapterResult['code'] ?? 'adapter_failed','updated_at'=>gmdate('c')];
                $rewardUpdate = $pdo->prepare("UPDATE training_reward_events SET status='failed', failure_message=?, metadata_json=? WHERE id=? AND status NOT IN ('issued','linked','cancelled')");
                $rewardUpdate->execute([mb_substr((string)($adapterResult['message'] ?? 'Adapter delivery failed.'), 0, 500), tl_stage890_json($metadata), (int)$reward['id']]);
                tl_log_event($pdo, $actor, 'reward_event', (int)$reward['id'], 'stage890_reward_handoff_failed', ['handoff_id'=>(int)$current['id'],'status'=>$status,'attempt_count'=>$attempt,'next_attempt_at'=>$nextAttempt,'failure_code'=>$adapterResult['code'] ?? null]);
            }
            $pdo->commit();
            return ['handoff_id'=>(int)$current['id'],'public_id'=>(string)$current['public_id'],'handoff_status'=>$status,'adapter_result'=>$adapterResult,'next_attempt_at'=>$nextAttempt];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_stage890_process_batch')) {
    function tl_stage890_process_batch(array $input = []): array
    {
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $sync = tl_stage890_sync_outbox($input);
        $pdo = tl_require_db();
        $limit = max(1, min(50, (int)($input['limit'] ?? tl_stage890_config()['batch_size'])));
        $stmt = $pdo->prepare("SELECT id FROM training_reward_handoffs WHERE handoff_status IN ('queued','failed') AND (next_attempt_at IS NULL OR next_attempt_at <= UTC_TIMESTAMP()) AND attempt_count < ? ORDER BY COALESCE(next_attempt_at, created_at) ASC, id ASC LIMIT " . $limit);
        $stmt->execute([(int)tl_stage890_config()['max_attempts']]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $processed = [];
        foreach ($ids as $id) {
            try {
                $processed[] = tl_stage890_process_handoff($input + ['handoff_id'=>(string)$id]);
            } catch (Throwable $e) {
                $processed[] = ['handoff_id'=>$id,'error'=>$e->getMessage()];
            }
        }
        return ['sync'=>$sync,'selected'=>count($ids),'processed'=>$processed];
    }
}

if (!function_exists('tl_stage890_cancel_handoff')) {
    function tl_stage890_cancel_handoff(array $input): array
    {
        if (!tl_stage890_table_ready()) throw new TlHttpException('Stage 890 database migration is required.', 503, 'stage890_schema_missing');
        $pdo = tl_require_db();
        $ref = trim((string)($input['handoff_id'] ?? $input['public_id'] ?? ''));
        if ($ref === '') throw new TlHttpException('Handoff reference is required.', 422, 'handoff_required');
        $stmt = $pdo->prepare("UPDATE training_reward_handoffs SET handoff_status='cancelled', cancelled_at=UTC_TIMESTAMP(), next_attempt_at=NULL, locked_at=NULL, locked_by=NULL, failure_code='cancelled_by_operator', failure_message=? WHERE (public_id=? OR id=?) AND handoff_status <> 'delivered'");
        $reason = mb_substr(trim((string)($input['cancel_reason'] ?? 'Cancelled by Training Lab operator.')), 0, 500);
        $stmt->execute([$reason, $ref, ctype_digit($ref) ? (int)$ref : 0]);
        if ($stmt->rowCount() < 1) throw new TlHttpException('Handoff was not found, already delivered, or already cancelled.', 409, 'handoff_not_cancelled');
        return ['handoff_reference'=>$ref,'handoff_status'=>'cancelled','reward_event_cancelled'=>false];
    }
}

if (!function_exists('tl_stage890_rows')) {
    function tl_stage890_rows(int $limit = 100): array
    {
        if (!tl_stage890_table_ready()) return [];
        $pdo = tl_db();
        if (!$pdo) return [];
        $sql = "SELECT h.*, re.public_id AS reward_public_id, re.status AS reward_status, re.value_cents, re.currency, rr.reward_label, rr.reward_type, c.title AS campaign_title, COALESCE(tp.participant_label, CONCAT('User #', re.user_id)) AS participant_label FROM training_reward_handoffs h LEFT JOIN training_reward_events re ON re.id=h.reward_event_id LEFT JOIN training_reward_rules rr ON rr.id=re.reward_rule_id LEFT JOIN training_campaigns c ON c.id=re.campaign_id LEFT JOIN training_participants tp ON tp.id=re.participant_id ORDER BY FIELD(h.handoff_status,'processing','failed','blocked','queued','delivered','cancelled'), COALESCE(h.next_attempt_at,h.updated_at) ASC, h.id DESC LIMIT " . max(1, min(500, $limit));
        try { return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_stage890_summary')) {
    function tl_stage890_summary(): array
    {
        $rows = tl_stage890_rows(300);
        $counts = ['total'=>count($rows),'queued'=>0,'blocked'=>0,'processing'=>0,'delivered'=>0,'failed'=>0,'cancelled'=>0,'due'=>0];
        foreach ($rows as $row) {
            $status = (string)($row['handoff_status'] ?? '');
            if (isset($counts[$status])) $counts[$status]++;
            if (in_array($status, ['queued','failed'], true) && (empty($row['next_attempt_at']) || strtotime((string)$row['next_attempt_at']) <= time())) $counts['due']++;
        }
        return [
            'stage'=>'Stage 890 Reward Handoff Outbox v1',
            'schema_ready'=>tl_stage890_table_ready(),
            'config'=>tl_stage890_config(),
            'adapter'=>tl_stage890_adapter_state(),
            'counts'=>$counts,
            'rows'=>$rows,
            'safe_boundaries'=>[
                'durable_idempotency'=>true,
                'single_handoff_per_reward_event'=>true,
                'row_locked_processing'=>true,
                'production_mutation_requires_all_gates'=>true,
                'developer_key_not_stored_or_exposed'=>true,
                'no_training_lab_wallet_mutation'=>true,
                'no_payment_processing'=>true,
                'no_claim_redeem_mutation_by_training_lab'=>true,
            ],
        ];
    }
}

if (!function_exists('tl_stage890_render_admin_panel')) {
    function tl_stage890_render_admin_panel(): void
    {
        $data = tl_stage890_summary();
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 890</span><h2>Reward Handoff Outbox</h2><p class="labs-copy">Durable, idempotent delivery records between approved Training Lab rewards and the gated Microgifter adapter.</p></div><a class="labs-btn" href="' . labs_e(labs_url('/api/training/reward-handoff-outbox.php')) . '">Outbox API</a></div>';
        if (!$data['schema_ready']) {
            echo '<div class="labs-error-card"><strong>Stage 890 SQL required</strong><p>Import <code>database/stage890_reward_handoff_outbox_v1.sql</code> into the Training Lab database.</p></div></section>';
            return;
        }
        $c = $data['counts'];
        echo '<div class="labs-kpis"><div class="labs-kpi"><span>Queued</span><strong>' . (int)$c['queued'] . '</strong><small>ready for gated delivery</small></div><div class="labs-kpi"><span>Blocked</span><strong>' . (int)$c['blocked'] . '</strong><small>requirements missing</small></div><div class="labs-kpi"><span>Failed</span><strong>' . (int)$c['failed'] . '</strong><small>retry controlled</small></div><div class="labs-kpi"><span>Delivered</span><strong>' . (int)$c['delivered'] . '</strong><small>adapter confirmed</small></div></div>';
        echo '<div class="labs-actions"><form action="' . labs_e(labs_url('/admin/action-result.php')) . '" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="sync_reward_handoff_outbox"><button class="labs-btn" type="submit">Sync Eligible Rewards</button></form><form action="' . labs_e(labs_url('/admin/action-result.php')) . '" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="process_reward_handoff_batch"><button class="labs-btn labs-btn-primary" type="submit">Process Due Batch</button></form></div>';
        echo '<div class="labs-stage160-claim-table">';
        foreach (array_slice((array)$data['rows'], 0, 50) as $row) {
            $value = (int)$row['value_cents'] > 0 ? '$' . number_format(((int)$row['value_cents']) / 100, 2) . ' ' . labs_e((string)$row['currency']) : 'Recognition';
            echo '<div class="labs-stage160-claim-row"><div><span class="labs-pill">' . labs_e((string)$row['handoff_status']) . '</span><strong>' . labs_e((string)($row['reward_label'] ?: 'Training Reward')) . '</strong><p>' . labs_e((string)($row['campaign_title'] ?: 'Campaign')) . ' · ' . labs_e((string)($row['participant_label'] ?: 'Participant')) . ' · ' . $value . '</p><small>Attempts: ' . (int)$row['attempt_count'] . ($row['failure_message'] ? ' · ' . labs_e((string)$row['failure_message']) : '') . '</small></div><div class="labs-stage160-claim-actions">';
            if (!in_array((string)$row['handoff_status'], ['delivered','cancelled'], true)) {
                echo '<form action="' . labs_e(labs_url('/admin/action-result.php')) . '" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="process_reward_handoff"><input type="hidden" name="handoff_id" value="' . (int)$row['id'] . '"><button class="labs-btn" type="submit">Process</button></form><form action="' . labs_e(labs_url('/admin/action-result.php')) . '" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="cancel_reward_handoff"><input type="hidden" name="handoff_id" value="' . (int)$row['id'] . '"><button class="labs-btn labs-btn-danger" type="submit">Cancel Delivery</button></form>';
            }
            echo '</div></div>';
        }
        if (!$data['rows']) echo '<div class="labs-empty-state"><strong>No handoffs yet</strong><p>Sync eligible rewards after an approved proof creates a Microgifter-enabled reward event.</p></div>';
        echo '</div><div class="labs-safe-note">Outbox processing is disabled by default. Delivery requires the processing flag, production issuing flag, developer key, active account link, and a direct adapter function.</div></section>';
    }
}
