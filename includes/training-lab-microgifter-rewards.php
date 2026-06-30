<?php
/**
 * Training Lab Microgifter rewards bridge.
 *
 * This layer keeps reward tracking inside Training Lab tables while exposing a
 * clean adapter boundary for the production Microgifter reward/wallet system.
 * It can use direct in-app adapter functions when they exist, or a configured
 * developer API key as a readiness signal. It never guesses production table
 * names and never stores the API key in Training Lab tables.
 */
require_once __DIR__ . '/training-lab-actions.php';
require_once __DIR__ . '/training-lab-account-bridge.php';

if (!function_exists('tl_mg_rewards_clean')) {
    function tl_mg_rewards_clean(string $value, int $max = 500): string
    {
        $value = trim(strip_tags($value));
        if (function_exists('mb_substr')) return mb_substr($value, 0, $max);
        return substr($value, 0, $max);
    }
}

if (!function_exists('tl_mg_rewards_actor_id')) {
    function tl_mg_rewards_actor_id(?array $input = null): int
    {
        $input = $input ?: [];
        if (!empty($input['user_id'])) return max(1, (int)$input['user_id']);
        if (!empty($input['actor_user_id'])) return max(1, (int)$input['actor_user_id']);
        return function_exists('tl_account_bridge_numeric_user_id') ? tl_account_bridge_numeric_user_id() : 1;
    }
}

if (!function_exists('tl_mg_rewards_config')) {
    function tl_mg_rewards_config(): array
    {
        $key = '';
        foreach (['TL_MICROGIFTER_DEVELOPER_API_KEY','MICROGIFTER_DEVELOPER_API_KEY','MG_DEVELOPER_API_KEY'] as $constant) {
            if (defined($constant) && (string)constant($constant) !== '') {
                $key = (string)constant($constant);
                break;
            }
        }
        if ($key === '') {
            foreach (['TL_MICROGIFTER_DEVELOPER_API_KEY','MICROGIFTER_DEVELOPER_API_KEY','MG_DEVELOPER_API_KEY'] as $env) {
                $value = getenv($env);
                if (is_string($value) && trim($value) !== '') {
                    $key = trim($value);
                    break;
                }
            }
        }
        $mode = 'adapter_pending';
        if (function_exists('microgifter_training_reward_catalog') || function_exists('microgifter_reward_catalog') || function_exists('microgifter_training_issue_reward') || function_exists('microgifter_issue_training_reward') || function_exists('microgifter_create_reward_claim')) {
            $mode = 'direct_adapter_available';
        } elseif ($key !== '') {
            $mode = 'developer_key_configured';
        }
        return [
            'configured' => $mode !== 'adapter_pending',
            'mode' => $mode,
            'developer_api_key_present' => $key !== '',
            'developer_api_key_fingerprint' => $key !== '' ? substr(hash('sha256', $key), 0, 12) : null,
            'direct_adapter_functions' => [
                'catalog' => array_values(array_filter(['microgifter_training_reward_catalog','microgifter_reward_catalog'], 'function_exists')),
                'issue_or_claim' => array_values(array_filter(['microgifter_training_issue_reward','microgifter_issue_training_reward','microgifter_create_reward_claim'], 'function_exists')),
            ],
            'safe_boundary' => 'API key is read from constants/env only; it is not written to Training Lab tables or exposed in JSON.',
        ];
    }
}

if (!function_exists('tl_mg_reward_catalog')) {
    function tl_mg_reward_catalog(): array
    {
        $config = tl_mg_rewards_config();
        $adapterRows = [];
        try {
            if (function_exists('microgifter_training_reward_catalog')) {
                $adapterRows = microgifter_training_reward_catalog(['source' => 'training_lab', 'developer_api_key_present' => $config['developer_api_key_present']]);
            } elseif (function_exists('microgifter_reward_catalog')) {
                $adapterRows = microgifter_reward_catalog(['source' => 'training_lab', 'developer_api_key_present' => $config['developer_api_key_present']]);
            }
        } catch (Throwable $e) {
            $adapterRows = [];
        }
        if (is_array($adapterRows) && $adapterRows) {
            return array_map(function ($row) {
                return [
                    'source' => 'microgifter_adapter',
                    'template_id' => (string)($row['template_id'] ?? $row['id'] ?? ''),
                    'catalog_product_id' => (string)($row['catalog_product_id'] ?? $row['product_id'] ?? ''),
                    'label' => (string)($row['label'] ?? $row['title'] ?? 'Microgifter Reward'),
                    'description' => (string)($row['description'] ?? 'Available from Microgifter reward adapter.'),
                    'value_cents' => (int)($row['value_cents'] ?? $row['price_cents'] ?? 0),
                    'currency' => (string)($row['currency'] ?? 'USD'),
                    'reward_type' => (string)($row['reward_type'] ?? 'microgift'),
                    'adapter_ready' => true,
                ];
            }, $adapterRows);
        }
        return [
            ['source' => 'training_default', 'template_id' => 'tl-badge-readiness', 'catalog_product_id' => '', 'label' => 'Readiness Badge', 'description' => 'Default Training Lab completion badge.', 'value_cents' => 0, 'currency' => 'USD', 'reward_type' => 'badge', 'adapter_ready' => false],
            ['source' => 'training_default', 'template_id' => 'tl-microgift-5', 'catalog_product_id' => '', 'label' => '$5 Microgift Preview', 'description' => 'Training Lab microgift offer placeholder. Connect the Microgifter reward adapter to issue for real.', 'value_cents' => 500, 'currency' => 'USD', 'reward_type' => 'microgift', 'adapter_ready' => false],
            ['source' => 'training_default', 'template_id' => 'tl-entitlement', 'catalog_product_id' => '', 'label' => 'Training Entitlement', 'description' => 'Internal entitlement placeholder for access or recognition.', 'value_cents' => 0, 'currency' => 'USD', 'reward_type' => 'entitlement', 'adapter_ready' => false],
        ];
    }
}

if (!function_exists('tl_mg_rewards_json_decode')) {
    function tl_mg_rewards_json_decode($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('tl_mg_rewards_for_user')) {
    function tl_mg_rewards_for_user(?int $userId = null, int $limit = 100): array
    {
        $userId = $userId && $userId > 0 ? $userId : tl_mg_rewards_actor_id();
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_reward_events')) return [];
        try {
            $sql = "SELECT re.*, c.title AS campaign_title, c.slug AS campaign_slug, rr.reward_label, rr.reward_type, rr.rule_name, rr.linked_microgift_template_id, rr.linked_catalog_product_id,
                           COALESCE(tp.participant_label, CONCAT('User #', re.user_id)) AS participant_label
                    FROM training_reward_events re
                    LEFT JOIN training_campaigns c ON c.id = re.campaign_id
                    LEFT JOIN training_reward_rules rr ON rr.id = re.reward_rule_id
                    LEFT JOIN training_participants tp ON tp.id = re.participant_id
                    WHERE re.user_id = ?
                    ORDER BY FIELD(re.status, 'eligible','queued','issued','failed','linked','cancelled'), re.updated_at DESC, re.created_at DESC
                    LIMIT " . max(1, min(200, $limit));
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll();
            return array_map(function ($row) {
                $metadata = tl_mg_rewards_json_decode($row['metadata_json'] ?? null);
                $claimStatus = (string)($metadata['claim_status'] ?? 'not_claimed');
                $status = (string)$row['status'];
                $claimable = in_array($status, ['eligible','queued','issued','failed'], true) && !in_array($claimStatus, ['claimed_in_training','linked_to_microgifter','cancelled'], true);
                return $row + [
                    'metadata' => $metadata,
                    'claim_status' => $claimStatus,
                    'claimable' => $claimable,
                    'display_label' => (string)($row['reward_label'] ?: 'Training Reward'),
                    'display_value' => ((int)$row['value_cents'] > 0 ? '$' . number_format(((int)$row['value_cents']) / 100, 2) : 'Recognition'),
                ];
            }, $rows);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_mg_rewards_summary')) {
    function tl_mg_rewards_summary(?int $userId = null): array
    {
        $rewards = tl_mg_rewards_for_user($userId, 100);
        $counts = ['total' => count($rewards), 'eligible' => 0, 'queued' => 0, 'issued' => 0, 'linked' => 0, 'failed' => 0, 'cancelled' => 0, 'claimable' => 0, 'claimed_in_training' => 0];
        foreach ($rewards as $reward) {
            $status = (string)($reward['status'] ?? '');
            if (isset($counts[$status])) $counts[$status]++;
            if (!empty($reward['claimable'])) $counts['claimable']++;
            if (($reward['claim_status'] ?? '') === 'claimed_in_training' || ($reward['claim_status'] ?? '') === 'linked_to_microgifter') $counts['claimed_in_training']++;
        }
        return [
            'user_id' => $userId ?: tl_mg_rewards_actor_id(),
            'counts' => $counts,
            'rewards' => $rewards,
            'bridge' => tl_mg_rewards_config(),
            'catalog' => tl_mg_reward_catalog(),
        ];
    }
}

if (!function_exists('tl_mg_rewards_claim_code')) {
    function tl_mg_rewards_claim_code(array $reward): string
    {
        return 'TL-' . strtoupper(substr(hash('sha256', (string)($reward['public_id'] ?? $reward['id'] ?? '') . '|' . gmdate('YmdHis')), 0, 10));
    }
}

if (!function_exists('tl_mg_rewards_load_event')) {
    function tl_mg_rewards_load_event(PDO $pdo, string $rewardRef): array
    {
        if ($rewardRef === '') throw new RuntimeException('Reward reference is required.');
        $stmt = $pdo->prepare("SELECT re.*, rr.reward_label, rr.reward_type, rr.rule_name, rr.linked_microgift_template_id, rr.linked_catalog_product_id, c.title AS campaign_title
                               FROM training_reward_events re
                               LEFT JOIN training_reward_rules rr ON rr.id = re.reward_rule_id
                               LEFT JOIN training_campaigns c ON c.id = re.campaign_id
                               WHERE re.public_id = ? OR re.id = ? LIMIT 1");
        $stmt->execute([$rewardRef, ctype_digit($rewardRef) ? (int)$rewardRef : 0]);
        $reward = $stmt->fetch();
        if (!$reward) throw new RuntimeException('Reward event was not found.');
        return $reward;
    }
}

if (!function_exists('tl_mg_rewards_call_claim_adapter')) {
    function tl_mg_rewards_call_claim_adapter(array $reward, array $input, string $claimCode): array
    {
        $config = tl_mg_rewards_config();
        $payload = [
            'source' => 'training_lab',
            'training_reward_event_id' => (int)$reward['id'],
            'training_reward_public_id' => (string)$reward['public_id'],
            'training_campaign_id' => (int)$reward['campaign_id'],
            'training_participant_id' => (int)$reward['participant_id'],
            'user_id' => (int)$reward['user_id'],
            'reward_label' => (string)($reward['reward_label'] ?? 'Training Reward'),
            'reward_type' => (string)($reward['reward_type'] ?? 'microgift'),
            'value_cents' => (int)$reward['value_cents'],
            'currency' => (string)$reward['currency'],
            'linked_microgift_template_id' => $reward['linked_microgift_template_id'] ?? null,
            'linked_catalog_product_id' => $reward['linked_catalog_product_id'] ?? null,
            'claim_code' => $claimCode,
            'claim_note' => tl_mg_rewards_clean((string)($input['claim_note'] ?? ''), 300),
            'developer_api_key_present' => $config['developer_api_key_present'],
        ];
        foreach (['microgifter_training_issue_reward','microgifter_issue_training_reward','microgifter_create_reward_claim'] as $fn) {
            if (function_exists($fn)) {
                try {
                    $result = $fn($payload);
                    if (is_array($result)) {
                        return ['adapter' => $fn, 'status' => 'adapter_success', 'result' => $result];
                    }
                    return ['adapter' => $fn, 'status' => 'adapter_called', 'result' => ['raw' => $result]];
                } catch (Throwable $e) {
                    return ['adapter' => $fn, 'status' => 'adapter_error', 'message' => $e->getMessage()];
                }
            }
        }
        if ($config['developer_api_key_present']) {
            return ['adapter' => 'developer_api_key', 'status' => 'queued_for_microgifter_api', 'message' => 'Developer API key is present; connect the HTTP/direct API adapter to issue this reward.'];
        }
        return ['adapter' => 'adapter_pending', 'status' => 'pending_developer_key', 'message' => 'No Microgifter reward adapter or developer API key is configured yet.'];
    }
}

if (!function_exists('tl_mg_claim_training_reward')) {
    function tl_mg_claim_training_reward(array $input): array
    {
        $pdo = tl_require_db();
        $rewardRef = tl_mg_rewards_clean((string)($input['reward_event_id'] ?? $input['reward_id'] ?? $input['public_id'] ?? ''), 80);
        $actor = tl_mg_rewards_actor_id($input);
        $reward = tl_mg_rewards_load_event($pdo, $rewardRef);
        $currentUser = function_exists('tl_auth_current_user') ? tl_auth_current_user() : null;
        $role = (string)($currentUser['role'] ?? 'participant');
        $isAdminish = in_array($role, ['coach','reviewer','manager','admin'], true);
        if ((int)$reward['user_id'] !== $actor && !$isAdminish) {
            throw new RuntimeException('Reward can only be claimed by the assigned participant or an authorized training operator.');
        }
        if (in_array((string)$reward['status'], ['cancelled'], true)) throw new RuntimeException('Cancelled rewards cannot be claimed.');
        $metadata = tl_mg_rewards_json_decode($reward['metadata_json'] ?? null);
        $claimCode = (string)($metadata['claim_code'] ?? tl_mg_rewards_claim_code($reward));
        $bridge = tl_mg_rewards_call_claim_adapter($reward, $input, $claimCode);
        $now = gmdate('c');
        $newStatus = 'queued';
        $claimStatus = 'claimed_in_training';
        $linkedGiftId = $reward['linked_gift_id'] ?? null;
        $linkedMicrogiftId = $reward['linked_microgift_instance_id'] ?? null;
        $linkedEntitlementId = $reward['linked_digital_entitlement_id'] ?? null;
        $linkedWalletEventId = $reward['linked_wallet_event_id'] ?? null;
        $issuedAt = null;
        $failureMessage = null;
        if (($bridge['status'] ?? '') === 'adapter_success') {
            $result = is_array($bridge['result'] ?? null) ? $bridge['result'] : [];
            $linkedGiftId = $result['gift_id'] ?? $result['linked_gift_id'] ?? $linkedGiftId;
            $linkedMicrogiftId = $result['microgift_instance_id'] ?? $result['linked_microgift_instance_id'] ?? $linkedMicrogiftId;
            $linkedEntitlementId = $result['digital_entitlement_id'] ?? $result['linked_digital_entitlement_id'] ?? $linkedEntitlementId;
            $linkedWalletEventId = $result['wallet_event_id'] ?? $result['linked_wallet_event_id'] ?? $linkedWalletEventId;
            $newStatus = ($linkedGiftId || $linkedMicrogiftId || $linkedEntitlementId || $linkedWalletEventId) ? 'linked' : 'issued';
            $claimStatus = $newStatus === 'linked' ? 'linked_to_microgifter' : 'issued_by_microgifter_adapter';
            $issuedAt = date('Y-m-d H:i:s');
        } elseif (($bridge['status'] ?? '') === 'adapter_error') {
            $newStatus = 'failed';
            $claimStatus = 'claim_failed_adapter_error';
            $failureMessage = substr((string)($bridge['message'] ?? 'Adapter error'), 0, 500);
        } elseif (($bridge['status'] ?? '') === 'queued_for_microgifter_api') {
            $newStatus = 'queued';
            $claimStatus = 'queued_for_microgifter_issue';
        }
        $metadata['claim_status'] = $claimStatus;
        $metadata['claim_code'] = $claimCode;
        $metadata['claimed_at'] = $now;
        $metadata['claimed_by_user_id'] = $actor;
        $metadata['claim_channel'] = 'training_lab_in_app_claim';
        $metadata['microgifter_bridge'] = $bridge;
        $metadata['no_wallet_balance_mutation_by_training_lab'] = true;
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $stmt = $pdo->prepare('UPDATE training_reward_events SET status = ?, linked_gift_id = ?, linked_microgift_instance_id = ?, linked_digital_entitlement_id = ?, linked_wallet_event_id = ?, issued_at = COALESCE(issued_at, ?), failure_message = ?, metadata_json = ? WHERE id = ?');
        $stmt->execute([$newStatus, $linkedGiftId ?: null, $linkedMicrogiftId ?: null, $linkedEntitlementId ?: null, $linkedWalletEventId ?: null, $issuedAt, $failureMessage, $metadataJson, (int)$reward['id']]);
        tl_log_event($pdo, $actor, 'reward_event', (int)$reward['id'], 'training_reward_claimed_in_app', ['status' => $newStatus, 'claim_status' => $claimStatus, 'claim_code' => $claimCode, 'bridge' => $bridge]);
        return [
            'reward_event_id' => (int)$reward['id'],
            'reward_public_id' => (string)$reward['public_id'],
            'status' => $newStatus,
            'claim_status' => $claimStatus,
            'claim_code' => $claimCode,
            'bridge' => $bridge,
        ];
    }
}

if (!function_exists('tl_mg_offer_reward_for_campaign')) {
    function tl_mg_offer_reward_for_campaign(array $input): array
    {
        $pdo = tl_require_db();
        $campaignRef = tl_mg_rewards_clean((string)($input['campaign'] ?? $input['campaign_id'] ?? $input['slug'] ?? ''), 160);
        if ($campaignRef === '') throw new RuntimeException('Campaign is required.');
        $campaign = tl_find_campaign_row($pdo, $campaignRef);
        if (!$campaign) throw new RuntimeException('Campaign not found.');
        $catalogId = tl_mg_rewards_clean((string)($input['template_id'] ?? $input['catalog_template_id'] ?? ''), 120);
        $catalog = tl_mg_reward_catalog();
        $selected = null;
        foreach ($catalog as $item) {
            if ($catalogId !== '' && ((string)$item['template_id'] === $catalogId || (string)$item['catalog_product_id'] === $catalogId)) {
                $selected = $item;
                break;
            }
        }
        if (!$selected) {
            $selected = [
                'template_id' => $catalogId ?: 'custom-training-reward',
                'catalog_product_id' => tl_mg_rewards_clean((string)($input['catalog_product_id'] ?? ''), 120),
                'label' => tl_mg_rewards_clean((string)($input['reward_label'] ?? 'Training Reward'), 160),
                'value_cents' => max(0, (int)($input['reward_value_cents'] ?? 0)),
                'currency' => 'USD',
                'reward_type' => tl_mg_rewards_clean((string)($input['reward_type'] ?? 'microgift'), 40),
                'description' => 'Custom Training Lab reward offer.',
            ];
        }
        $actor = tl_mg_rewards_actor_id($input);
        $selected['reward_type'] = (string)($selected['reward_type'] ?? 'microgift');
        if (!in_array($selected['reward_type'], ['badge','microgift','entitlement','wallet_credit_preview','custom'], true)) $selected['reward_type'] = 'microgift';
        $selected['currency'] = preg_match('/^[A-Z]{3}$/', (string)($selected['currency'] ?? 'USD')) ? (string)$selected['currency'] : 'USD';
        $ruleName = 'Microgifter Reward Offer - ' . (string)$selected['label'];
        $stmt = $pdo->prepare("INSERT INTO training_reward_rules (public_id, campaign_id, rule_name, trigger_type, threshold_count, reward_type, reward_label, reward_value_cents, currency, linked_microgift_template_id, linked_catalog_product_id, status, settings_json) VALUES (?, ?, ?, 'sequence_completed', ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
        $settings = json_encode(['source' => 'microgifter_reward_bridge', 'catalog_item' => $selected, 'configured_by_user_id' => $actor, 'configured_at' => gmdate('c')], JSON_UNESCAPED_SLASHES);
        $stmt->execute([tl_uuid(), (int)$campaign['id'], $ruleName, max(1, (int)($input['threshold_count'] ?? $campaign['target_action_count'] ?? 1)), (string)($selected['reward_type'] ?? 'microgift'), (string)$selected['label'], max(0, (int)($selected['value_cents'] ?? 0)), (string)($selected['currency'] ?? 'USD'), ctype_digit((string)($selected['template_id'] ?? '')) ? (int)$selected['template_id'] : null, ctype_digit((string)($selected['catalog_product_id'] ?? '')) ? (int)$selected['catalog_product_id'] : null, $settings]);
        $ruleId = (int)$pdo->lastInsertId();
        tl_log_event($pdo, $actor, 'reward_rule', $ruleId, 'microgifter_reward_offer_configured', ['campaign_id' => (int)$campaign['id'], 'catalog_item' => $selected]);
        return ['campaign_id' => (int)$campaign['id'], 'reward_rule_id' => $ruleId, 'catalog_item' => $selected];
    }
}

if (!function_exists('tl_mg_reward_bridge_summary')) {
    function tl_mg_reward_bridge_summary(): array
    {
        $config = tl_mg_rewards_config();
        $catalog = tl_mg_reward_catalog();
        $pdo = tl_db();
        $counts = ['rules' => 0, 'events' => 0, 'claimable' => 0, 'claimed_or_linked' => 0, 'failed' => 0];
        if ($pdo && tl_table_exists('training_reward_rules')) $counts['rules'] = tl_app_count('training_reward_rules');
        if ($pdo && tl_table_exists('training_reward_events')) {
            $counts['events'] = tl_app_count('training_reward_events');
            $counts['claimable'] = tl_app_count('training_reward_events', "status IN ('eligible','queued','issued')");
            $counts['claimed_or_linked'] = tl_app_count('training_reward_events', "status IN ('issued','linked')");
            $counts['failed'] = tl_app_count('training_reward_events', "status = 'failed'");
        }
        return [
            'stage' => 'Stage 131–140 Microgifter rewards bridge and in-app claim flow',
            'config' => $config,
            'catalog_count' => count($catalog),
            'catalog' => $catalog,
            'counts' => $counts,
            'supported_actions' => ['offer_microgifter_reward','claim_training_reward'],
            'safe_boundaries' => [
                'developer_key_not_stored_or_exposed' => true,
                'uses_training_reward_events_for_tracking' => true,
                'direct_microgifter_issue_requires_adapter_or_key' => true,
                'no_payment_processing' => true,
                'no_wallet_balance_mutation_by_training_lab' => true,
            ],
        ];
    }
}


/**
 * Stage 141-160 reward lifecycle hardening.
 * These helpers keep all lifecycle tracking in training_reward_events.metadata_json
 * and only call Microgifter when an explicit adapter/developer-key path is present.
 */
if (!function_exists('tl_mg_stage160_lifecycle_state')) {
    function tl_mg_stage160_lifecycle_state(array $row): string
    {
        $metadata = tl_mg_rewards_json_decode($row['metadata_json'] ?? null);
        $claimStatus = (string)($metadata['claim_status'] ?? '');
        $status = (string)($row['status'] ?? 'eligible');
        if ($status === 'cancelled') return 'cancelled';
        if ($status === 'failed' || str_contains($claimStatus, 'failed')) return 'failed_retry_available';
        if ($status === 'linked' || $claimStatus === 'linked_to_microgifter') return 'linked_to_microgifter';
        if ($status === 'issued' || in_array($claimStatus, ['issued_by_microgifter_adapter','manual_issued'], true)) return 'issued';
        if ($claimStatus === 'queued_for_microgifter_issue' || $status === 'queued') return 'pending_microgifter_sync';
        if ($claimStatus === 'claimed_in_training') return 'claimed_in_app';
        return 'available_to_claim';
    }
}

if (!function_exists('tl_mg_stage160_enrich_reward')) {
    function tl_mg_stage160_enrich_reward(array $row): array
    {
        $metadata = tl_mg_rewards_json_decode($row['metadata_json'] ?? null);
        $lifecycle = tl_mg_stage160_lifecycle_state($row);
        $claimStatus = (string)($metadata['claim_status'] ?? 'not_claimed');
        $claimable = in_array($lifecycle, ['available_to_claim','failed_retry_available'], true) && (string)($row['status'] ?? '') !== 'cancelled';
        $retryable = in_array($lifecycle, ['pending_microgifter_sync','failed_retry_available','claimed_in_app'], true) && (string)($row['status'] ?? '') !== 'cancelled';
        return $row + [
            'metadata' => $metadata,
            'claim_status' => $claimStatus,
            'lifecycle_status' => $lifecycle,
            'claimable' => $claimable,
            'retryable' => $retryable,
            'display_label' => (string)($row['reward_label'] ?? $row['rule_name'] ?? 'Training Reward'),
            'display_value' => ((int)($row['value_cents'] ?? 0) > 0 ? '$' . number_format(((int)$row['value_cents']) / 100, 2) : 'Recognition'),
            'microgifter_linked' => !empty($row['linked_gift_id']) || !empty($row['linked_microgift_instance_id']) || !empty($row['linked_digital_entitlement_id']) || !empty($row['linked_wallet_event_id']),
        ];
    }
}

if (!function_exists('tl_mg_stage160_select_rewards_sql')) {
    function tl_mg_stage160_select_rewards_sql(string $where = ''): string
    {
        return "SELECT re.*, c.title AS campaign_title, c.slug AS campaign_slug, rr.reward_label, rr.reward_type, rr.rule_name, rr.linked_microgift_template_id, rr.linked_catalog_product_id,
                       COALESCE(tp.participant_label, CONCAT('User #', re.user_id)) AS participant_label
                FROM training_reward_events re
                LEFT JOIN training_campaigns c ON c.id = re.campaign_id
                LEFT JOIN training_reward_rules rr ON rr.id = re.reward_rule_id
                LEFT JOIN training_participants tp ON tp.id = re.participant_id
                " . $where . "
                ORDER BY FIELD(re.status, 'failed','eligible','queued','issued','linked','cancelled'), re.updated_at DESC, re.created_at DESC";
    }
}

if (!function_exists('tl_mg_stage160_rewards_for_user')) {
    function tl_mg_stage160_rewards_for_user(?int $userId = null, int $limit = 100): array
    {
        $userId = $userId && $userId > 0 ? $userId : tl_mg_rewards_actor_id();
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_reward_events')) return [];
        try {
            $sql = tl_mg_stage160_select_rewards_sql('WHERE re.user_id = ?') . ' LIMIT ' . max(1, min(300, $limit));
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            return array_map('tl_mg_stage160_enrich_reward', $stmt->fetchAll());
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_mg_stage160_admin_rewards')) {
    function tl_mg_stage160_admin_rewards(int $limit = 200, string $status = ''): array
    {
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_reward_events')) return [];
        try {
            $where = '';
            $params = [];
            if ($status !== '' && in_array($status, ['eligible','queued','issued','linked','cancelled','failed'], true)) {
                $where = 'WHERE re.status = ?';
                $params[] = $status;
            }
            $sql = tl_mg_stage160_select_rewards_sql($where) . ' LIMIT ' . max(1, min(500, $limit));
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return array_map('tl_mg_stage160_enrich_reward', $stmt->fetchAll());
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_mg_stage160_counts')) {
    function tl_mg_stage160_counts(array $rewards): array
    {
        $counts = [
            'total' => count($rewards),
            'available_to_claim' => 0,
            'claimed_in_app' => 0,
            'pending_microgifter_sync' => 0,
            'issued' => 0,
            'linked_to_microgifter' => 0,
            'failed_retry_available' => 0,
            'cancelled' => 0,
            'claimable' => 0,
            'retryable' => 0,
        ];
        foreach ($rewards as $reward) {
            $state = (string)($reward['lifecycle_status'] ?? 'available_to_claim');
            if (isset($counts[$state])) $counts[$state]++;
            if (!empty($reward['claimable'])) $counts['claimable']++;
            if (!empty($reward['retryable'])) $counts['retryable']++;
        }
        return $counts;
    }
}

if (!function_exists('tl_mg_stage160_write_reward_update')) {
    function tl_mg_stage160_write_reward_update(PDO $pdo, array $reward, string $status, array $metadata, array $extra = []): void
    {
        $metadata['lifecycle_state'] = tl_mg_stage160_lifecycle_state(['status' => $status, 'metadata_json' => json_encode($metadata, JSON_UNESCAPED_SLASHES)] + $reward);
        $metadata['stage160_lifecycle_version'] = 1;
        $metadata['updated_by_training_lab_lifecycle'] = gmdate('c');
        $sql = 'UPDATE training_reward_events SET status = ?, metadata_json = ?';
        $params = [$status, json_encode($metadata, JSON_UNESCAPED_SLASHES)];
        foreach (['linked_gift_id','linked_microgift_instance_id','linked_digital_entitlement_id','linked_wallet_event_id','issued_at','cancelled_at','failure_message'] as $field) {
            if (array_key_exists($field, $extra)) {
                $sql .= ', ' . $field . ' = ?';
                $params[] = $extra[$field];
            }
        }
        $sql .= ' WHERE id = ?';
        $params[] = (int)$reward['id'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}

if (!function_exists('tl_mg_stage160_retry_microgifter_issue')) {
    function tl_mg_stage160_retry_microgifter_issue(array $input): array
    {
        $pdo = tl_require_db();
        $rewardRef = tl_mg_rewards_clean((string)($input['reward_event_id'] ?? $input['reward_id'] ?? $input['public_id'] ?? ''), 80);
        $reward = tl_mg_rewards_load_event($pdo, $rewardRef);
        if ((string)$reward['status'] === 'cancelled') throw new RuntimeException('Cancelled rewards cannot be retried.');
        $metadata = tl_mg_rewards_json_decode($reward['metadata_json'] ?? null);
        $claimCode = (string)($metadata['claim_code'] ?? tl_mg_rewards_claim_code($reward));
        $metadata['claim_code'] = $claimCode;
        $metadata['retry_count'] = (int)($metadata['retry_count'] ?? 0) + 1;
        $metadata['last_retry_at'] = gmdate('c');
        $metadata['claim_note'] = tl_mg_rewards_clean((string)($input['claim_note'] ?? 'Admin retry from Reward Bridge'), 300);
        $bridge = tl_mg_rewards_call_claim_adapter($reward, $input + ['claim_note' => $metadata['claim_note']], $claimCode);
        $newStatus = 'queued';
        $claimStatus = 'queued_for_microgifter_issue';
        $extra = ['failure_message' => null];
        if (($bridge['status'] ?? '') === 'adapter_success') {
            $result = is_array($bridge['result'] ?? null) ? $bridge['result'] : [];
            $extra['linked_gift_id'] = $result['gift_id'] ?? $result['linked_gift_id'] ?? ($reward['linked_gift_id'] ?: null);
            $extra['linked_microgift_instance_id'] = $result['microgift_instance_id'] ?? $result['linked_microgift_instance_id'] ?? ($reward['linked_microgift_instance_id'] ?: null);
            $extra['linked_digital_entitlement_id'] = $result['digital_entitlement_id'] ?? $result['linked_digital_entitlement_id'] ?? ($reward['linked_digital_entitlement_id'] ?: null);
            $extra['linked_wallet_event_id'] = $result['wallet_event_id'] ?? $result['linked_wallet_event_id'] ?? ($reward['linked_wallet_event_id'] ?: null);
            $newStatus = ($extra['linked_gift_id'] || $extra['linked_microgift_instance_id'] || $extra['linked_digital_entitlement_id'] || $extra['linked_wallet_event_id']) ? 'linked' : 'issued';
            $claimStatus = $newStatus === 'linked' ? 'linked_to_microgifter' : 'issued_by_microgifter_adapter';
            $extra['issued_at'] = date('Y-m-d H:i:s');
        } elseif (($bridge['status'] ?? '') === 'adapter_error') {
            $newStatus = 'failed';
            $claimStatus = 'claim_failed_adapter_error';
            $extra['failure_message'] = substr((string)($bridge['message'] ?? 'Adapter error'), 0, 500);
        }
        $metadata['claim_status'] = $claimStatus;
        $metadata['microgifter_bridge'] = $bridge;
        $metadata['no_wallet_balance_mutation_by_training_lab'] = true;
        tl_mg_stage160_write_reward_update($pdo, $reward, $newStatus, $metadata, $extra);
        tl_log_event($pdo, tl_mg_rewards_actor_id($input), 'reward_event', (int)$reward['id'], 'reward_microgifter_issue_retry', ['status' => $newStatus, 'claim_status' => $claimStatus, 'bridge_status' => $bridge['status'] ?? null]);
        return ['reward_event_id' => (int)$reward['id'], 'public_id' => (string)$reward['public_id'], 'status' => $newStatus, 'claim_status' => $claimStatus, 'bridge' => $bridge];
    }
}

if (!function_exists('tl_mg_stage160_mark_manual_issued')) {
    function tl_mg_stage160_mark_manual_issued(array $input): array
    {
        $pdo = tl_require_db();
        $rewardRef = tl_mg_rewards_clean((string)($input['reward_event_id'] ?? $input['reward_id'] ?? $input['public_id'] ?? ''), 80);
        $reward = tl_mg_rewards_load_event($pdo, $rewardRef);
        if ((string)$reward['status'] === 'cancelled') throw new RuntimeException('Cancelled rewards cannot be marked issued.');
        $metadata = tl_mg_rewards_json_decode($reward['metadata_json'] ?? null);
        $metadata['claim_status'] = 'manual_issued';
        $metadata['manual_issued_at'] = gmdate('c');
        $metadata['manual_issued_by_user_id'] = tl_mg_rewards_actor_id($input);
        $metadata['manual_issue_note'] = tl_mg_rewards_clean((string)($input['manual_issue_note'] ?? $input['claim_note'] ?? 'Manual issue recorded in Training Lab.'), 500);
        $metadata['manual_issue_no_wallet_mutation'] = true;
        tl_mg_stage160_write_reward_update($pdo, $reward, 'issued', $metadata, ['issued_at' => date('Y-m-d H:i:s'), 'failure_message' => null]);
        tl_log_event($pdo, tl_mg_rewards_actor_id($input), 'reward_event', (int)$reward['id'], 'reward_marked_manual_issued', ['manual_issue_no_wallet_mutation' => true]);
        return ['reward_event_id' => (int)$reward['id'], 'public_id' => (string)$reward['public_id'], 'status' => 'issued', 'claim_status' => 'manual_issued'];
    }
}

if (!function_exists('tl_mg_stage160_cancel_reward')) {
    function tl_mg_stage160_cancel_reward(array $input): array
    {
        $pdo = tl_require_db();
        $rewardRef = tl_mg_rewards_clean((string)($input['reward_event_id'] ?? $input['reward_id'] ?? $input['public_id'] ?? ''), 80);
        $reward = tl_mg_rewards_load_event($pdo, $rewardRef);
        $metadata = tl_mg_rewards_json_decode($reward['metadata_json'] ?? null);
        $metadata['claim_status'] = 'cancelled';
        $metadata['cancelled_at'] = gmdate('c');
        $metadata['cancelled_by_user_id'] = tl_mg_rewards_actor_id($input);
        $metadata['cancel_reason'] = tl_mg_rewards_clean((string)($input['cancel_reason'] ?? 'Cancelled from Reward Bridge.'), 500);
        tl_mg_stage160_write_reward_update($pdo, $reward, 'cancelled', $metadata, ['cancelled_at' => date('Y-m-d H:i:s')]);
        tl_log_event($pdo, tl_mg_rewards_actor_id($input), 'reward_event', (int)$reward['id'], 'reward_cancelled_training_lab', ['reason' => $metadata['cancel_reason']]);
        return ['reward_event_id' => (int)$reward['id'], 'public_id' => (string)$reward['public_id'], 'status' => 'cancelled'];
    }
}

if (!function_exists('tl_mg_stage160_reconcile_lifecycle')) {
    function tl_mg_stage160_reconcile_lifecycle(array $input = []): array
    {
        $pdo = tl_require_db();
        $rows = tl_mg_stage160_admin_rewards(500);
        $changed = 0;
        foreach ($rows as $row) {
            $metadata = $row['metadata'] ?? [];
            $target = (string)($row['lifecycle_status'] ?? tl_mg_stage160_lifecycle_state($row));
            if (($metadata['lifecycle_state'] ?? null) !== $target || (int)($metadata['stage160_lifecycle_version'] ?? 0) < 1) {
                $metadata['lifecycle_state'] = $target;
                $metadata['stage160_lifecycle_version'] = 1;
                $metadata['last_lifecycle_reconciled_at'] = gmdate('c');
                $stmt = $pdo->prepare('UPDATE training_reward_events SET metadata_json = ? WHERE id = ?');
                $stmt->execute([json_encode($metadata, JSON_UNESCAPED_SLASHES), (int)$row['id']]);
                $changed++;
            }
        }
        tl_log_event($pdo, tl_mg_rewards_actor_id($input), 'system', null, 'reward_lifecycle_reconciled', ['scanned' => count($rows), 'changed' => $changed, 'wallet_write' => false]);
        return ['scanned' => count($rows), 'changed' => $changed, 'wallet_write' => false];
    }
}

if (!function_exists('tl_mg_stage160_user_summary')) {
    function tl_mg_stage160_user_summary(?int $userId = null): array
    {
        $userId = $userId && $userId > 0 ? $userId : tl_mg_rewards_actor_id();
        $rewards = tl_mg_stage160_rewards_for_user($userId, 200);
        return [
            'stage' => 'Stage 141-160 reward lifecycle and claim tracking',
            'user_id' => $userId,
            'counts' => tl_mg_stage160_counts($rewards),
            'rewards' => $rewards,
            'bridge' => tl_mg_rewards_config(),
            'catalog' => tl_mg_reward_catalog(),
            'claim_flow' => ['available_to_claim','claimed_in_app','pending_microgifter_sync','issued','linked_to_microgifter','failed_retry_available','cancelled'],
        ];
    }
}

if (!function_exists('tl_mg_stage160_bridge_summary')) {
    function tl_mg_stage160_bridge_summary(): array
    {
        $rewards = tl_mg_stage160_admin_rewards(300);
        return [
            'stage' => 'Stage 141-160 reward lifecycle and Microgifter issuing hardening',
            'config' => tl_mg_rewards_config(),
            'catalog' => tl_mg_reward_catalog(),
            'counts' => tl_mg_stage160_counts($rewards),
            'admin_rewards' => $rewards,
            'supported_actions' => ['offer_microgifter_reward','claim_training_reward','retry_microgifter_reward_issue','mark_reward_manual_issued','cancel_training_reward','reconcile_reward_lifecycle'],
            'safe_boundaries' => [
                'developer_key_not_stored_or_exposed' => true,
                'claim_tracking_uses_training_reward_events_metadata' => true,
                'direct_microgifter_issue_requires_adapter_or_key' => true,
                'manual_issue_is_training_record_only' => true,
                'no_payment_processing' => true,
                'no_wallet_balance_mutation_by_training_lab' => true,
            ],
        ];
    }
}
