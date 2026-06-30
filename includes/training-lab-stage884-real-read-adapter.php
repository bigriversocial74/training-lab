<?php
/**
 * Stage 884 Real Microgifter Read Adapter Connection.
 *
 * Uses the live Training Lab database as the first real read adapter source for
 * Microgifter-style campaign, award, account, status, and inventory reads. This
 * layer is intentionally read-only. It does not issue rewards, mutate wallets,
 * redeem claims, process payments, or destructively sync back to Microgifter.
 */

if (!function_exists('tl_stage884_adapter_source')) {
    function tl_stage884_adapter_source(): string
    {
        $pdo = function_exists('tl_db') ? tl_db() : null;
        return $pdo instanceof PDO ? 'training_lab_database' : 'fixture_unavailable';
    }
}

if (!function_exists('tl_stage884_table_ready')) {
    function tl_stage884_table_ready(string $table): bool
    {
        return function_exists('tl_table_exists') && tl_table_exists($table);
    }
}

if (!function_exists('tl_stage884_fetch_all')) {
    function tl_stage884_fetch_all(string $sql, array $params = []): array
    {
        $pdo = function_exists('tl_db') ? tl_db() : null;
        if (!$pdo instanceof PDO) return [];
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_stage884_count_table')) {
    function tl_stage884_count_table(string $table): int
    {
        if (!tl_stage884_table_ready($table)) return 0;
        $rows = tl_stage884_fetch_all('SELECT COUNT(*) AS row_count FROM `' . str_replace('`', '', $table) . '`');
        return (int)($rows[0]['row_count'] ?? 0);
    }
}

if (!function_exists('tl_stage884_normalize_campaign_row')) {
    function tl_stage884_normalize_campaign_row(array $row, int $idx = 0): array
    {
        $target = max(1, (int)($row['target_action_count'] ?? 1));
        $participants = max(0, (int)($row['participant_count'] ?? 0));
        $tasks = max(0, (int)($row['task_count'] ?? 0));
        $rules = max(0, (int)($row['reward_rule_count'] ?? 0));
        $total = max(25, $target * max(1, $rules ?: 1) * 10);
        $reserved = min($total, $participants);
        $issued = max(0, (int)($row['reward_event_count'] ?? 0));
        $available = max(0, $total - $reserved - $issued);
        $title = (string)($row['title'] ?? ('Training Campaign ' . ($idx + 1)));
        $publicId = (string)($row['public_id'] ?? '');
        $slug = (string)($row['slug'] ?? '');
        return [
            'merchant_id' => 'training-lab-db',
            'merchant_name' => 'Training Lab Live Database',
            'campaign_id' => $publicId !== '' ? $publicId : ($slug !== '' ? $slug : 'training-campaign-' . ($idx + 1)),
            'campaign_name' => $title,
            'campaign_status' => (string)($row['status'] ?? 'active'),
            'reward_type' => $rules > 0 ? 'training_reward_rule' : 'training_completion_reward',
            'reward_title' => $rules > 0 ? ($rules . ' reward rule(s) available') : 'Training completion reward',
            'reward_value' => $rules > 0 ? 'rule-based' : 'preview',
            'quantity_total' => $total,
            'quantity_available' => $available,
            'quantity_reserved' => $reserved,
            'quantity_issued' => $issued,
            'starts_at' => 'database campaign window',
            'expires_at' => 'configured by campaign workflow',
            'claim_rules' => ['approved proof required','training review required','read-only adapter preview'],
            'source_url' => '',
            'visibility' => (string)($row['visibility'] ?? 'private'),
            'target_action_count' => $target,
            'task_count' => $tasks,
            'participant_count' => $participants,
            'reward_rule_count' => $rules,
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'source' => 'training_lab_database',
        ];
    }
}

if (!function_exists('microgifter_training_campaign_catalog')) {
    function microgifter_training_campaign_catalog(): array
    {
        if (!tl_stage884_table_ready('training_campaigns')) return [];
        $rows = tl_stage884_fetch_all(
            "SELECT c.id, c.public_id, c.slug, c.title, c.status, c.visibility, c.target_action_count, c.updated_at,
                    COUNT(DISTINCT t.id) AS task_count,
                    COUNT(DISTINCT p.id) AS participant_count,
                    COUNT(DISTINCT rr.id) AS reward_rule_count,
                    COUNT(DISTINCT re.id) AS reward_event_count
             FROM training_campaigns c
             LEFT JOIN training_campaign_tasks t ON t.campaign_id = c.id
             LEFT JOIN training_participants p ON p.campaign_id = c.id
             LEFT JOIN training_reward_rules rr ON rr.campaign_id = c.id
             LEFT JOIN training_reward_events re ON re.campaign_id = c.id
             GROUP BY c.id, c.public_id, c.slug, c.title, c.status, c.visibility, c.target_action_count, c.updated_at
             ORDER BY c.updated_at DESC, c.id DESC
             LIMIT 100"
        );
        $campaigns = [];
        foreach ($rows as $idx => $row) $campaigns[] = tl_stage884_normalize_campaign_row($row, $idx);
        return $campaigns;
    }
}

if (!function_exists('microgifter_merchant_reward_campaigns')) {
    function microgifter_merchant_reward_campaigns(): array
    {
        return microgifter_training_campaign_catalog();
    }
}

if (!function_exists('microgifter_reward_campaign_catalog')) {
    function microgifter_reward_campaign_catalog(): array
    {
        return microgifter_training_campaign_catalog();
    }
}

if (!function_exists('microgifter_reward_catalog')) {
    function microgifter_reward_catalog(): array
    {
        if (!tl_stage884_table_ready('training_reward_rules')) return [];
        $rows = tl_stage884_fetch_all(
            "SELECT rr.*, c.public_id AS campaign_public_id, c.slug AS campaign_slug, c.title AS campaign_title
             FROM training_reward_rules rr
             LEFT JOIN training_campaigns c ON c.id = rr.campaign_id
             ORDER BY rr.id DESC
             LIMIT 100"
        );
        $out = [];
        foreach ($rows as $idx => $row) {
            $label = (string)($row['reward_label'] ?? $row['label'] ?? $row['title'] ?? ('Reward Rule ' . ($idx + 1)));
            $out[] = [
                'reward_id' => (string)($row['public_id'] ?? $row['id'] ?? ('reward-rule-' . ($idx + 1))),
                'campaign_id' => (string)($row['campaign_public_id'] ?? $row['campaign_slug'] ?? $row['campaign_id'] ?? ''),
                'campaign_name' => (string)($row['campaign_title'] ?? 'Training Campaign'),
                'reward_title' => $label,
                'reward_type' => (string)($row['reward_type'] ?? $row['type'] ?? 'training_reward_rule'),
                'reward_value' => (string)($row['reward_value'] ?? $row['value'] ?? 'rule-based'),
                'status' => (string)($row['status'] ?? 'active'),
                'source' => 'training_lab_database',
            ];
        }
        return $out;
    }
}

if (!function_exists('tl_stage884_normalize_award_row')) {
    function tl_stage884_normalize_award_row(array $row, int $idx = 0): array
    {
        $decision = strtolower((string)($row['latest_decision'] ?? ''));
        $eventType = strtolower((string)($row['event_type'] ?? ''));
        $proofStatus = strtolower((string)($row['proof_status'] ?? ''));
        $status = 'pending_review';
        if ($eventType !== '') $status = 'issued';
        if (in_array($decision, ['approved','accepted','pass'], true) || $proofStatus === 'approved') $status = 'claimable';
        if (in_array($decision, ['rejected','denied','failed'], true)) $status = 'blocked';
        $claimStatus = $status === 'claimable' ? 'ready' : ($status === 'issued' ? 'issued' : 'blocked');
        return [
            'user_id' => (string)($row['submitted_by_user_id'] ?? $row['user_id'] ?? 'training-user'),
            'microgifter_user_id' => 'training-lab-user-' . (string)($row['submitted_by_user_id'] ?? $row['user_id'] ?? '0'),
            'award_id' => (string)($row['reward_event_public_id'] ?? $row['proof_public_id'] ?? $row['proof_id'] ?? ('db-award-' . ($idx + 1))),
            'campaign_id' => (string)($row['campaign_public_id'] ?? $row['campaign_slug'] ?? $row['campaign_id'] ?? ''),
            'merchant_id' => 'training-lab-db',
            'merchant_name' => 'Training Lab Live Database',
            'award_title' => (string)($row['reward_label'] ?? $row['task_title'] ?? 'Training Award'),
            'award_value' => (string)($row['reward_value'] ?? 'rule-based'),
            'award_status' => $status,
            'claim_status' => $claimStatus,
            'reward_type' => (string)($row['reward_type'] ?? 'training_reward'),
            'quantity_available' => 1,
            'earned_from_task_id' => (string)($row['task_public_id'] ?? $row['task_id'] ?? ''),
            'earned_from_task_title' => (string)($row['task_title'] ?? 'Training task'),
            'proof_id' => (string)($row['proof_public_id'] ?? $row['proof_id'] ?? ''),
            'review_status' => $decision !== '' ? $decision : ($proofStatus ?: 'pending'),
            'claim_code' => '',
            'claim_url' => '',
            'expires_at' => 'configured by campaign workflow',
            'created_at' => (string)($row['created_at'] ?? $row['submitted_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'blocked_reason' => $status === 'blocked' ? 'Review or proof requirement is not approved.' : '',
            'source' => 'training_lab_database',
        ];
    }
}

if (!function_exists('microgifter_training_user_awards')) {
    function microgifter_training_user_awards(int $userId = 0): array
    {
        $awards = [];
        if (tl_stage884_table_ready('training_reward_events')) {
            $params = [];
            $where = '';
            if ($userId > 0) { $where = 'WHERE re.user_id = ?'; $params[] = $userId; }
            $rows = tl_stage884_fetch_all(
                "SELECT re.public_id AS reward_event_public_id, re.user_id, re.event_type, re.created_at, re.updated_at,
                        c.public_id AS campaign_public_id, c.slug AS campaign_slug, c.title AS campaign_title,
                        rr.reward_label, tp.participant_label
                 FROM training_reward_events re
                 LEFT JOIN training_campaigns c ON c.id = re.campaign_id
                 LEFT JOIN training_reward_rules rr ON rr.id = re.reward_rule_id
                 LEFT JOIN training_participants tp ON tp.id = re.participant_id
                 $where
                 ORDER BY re.created_at DESC
                 LIMIT 100",
                $params
            );
            foreach ($rows as $idx => $row) $awards[] = tl_stage884_normalize_award_row($row, $idx);
        }
        if (!$awards && tl_stage884_table_ready('training_proof_submissions')) {
            $params = [];
            $where = '';
            if ($userId > 0) { $where = 'WHERE p.submitted_by_user_id = ?'; $params[] = $userId; }
            $rows = tl_stage884_fetch_all(
                "SELECT p.id AS proof_id, p.public_id AS proof_public_id, p.status AS proof_status, p.submitted_by_user_id, p.submitted_at, p.updated_at,
                        c.public_id AS campaign_public_id, c.slug AS campaign_slug, c.title AS campaign_title,
                        t.public_id AS task_public_id, t.title AS task_title,
                        (SELECT r.decision FROM training_reviews r WHERE r.proof_submission_id = p.id ORDER BY r.created_at DESC LIMIT 1) AS latest_decision
                 FROM training_proof_submissions p
                 LEFT JOIN training_campaigns c ON c.id = p.campaign_id
                 LEFT JOIN training_campaign_tasks t ON t.id = p.task_id
                 $where
                 ORDER BY p.updated_at DESC, p.submitted_at DESC
                 LIMIT 100",
                $params
            );
            foreach ($rows as $idx => $row) $awards[] = tl_stage884_normalize_award_row($row, $idx);
        }
        return $awards;
    }
}

if (!function_exists('microgifter_customer_awards')) {
    function microgifter_customer_awards(int $userId = 0): array
    {
        return microgifter_training_user_awards($userId);
    }
}

if (!function_exists('microgifter_user_awards')) {
    function microgifter_user_awards(int $userId = 0): array
    {
        return microgifter_training_user_awards($userId);
    }
}

if (!function_exists('microgifter_user_account_status')) {
    function microgifter_user_account_status(int $userId = 0): array
    {
        $participant = [];
        if (tl_stage884_table_ready('training_participants')) {
            $params = [];
            $where = '';
            if ($userId > 0) { $where = 'WHERE tp.user_id = ?'; $params[] = $userId; }
            $rows = tl_stage884_fetch_all(
                "SELECT tp.*, c.public_id AS campaign_public_id, c.slug AS campaign_slug, c.title AS campaign_title
                 FROM training_participants tp
                 LEFT JOIN training_campaigns c ON c.id = tp.campaign_id
                 $where
                 ORDER BY tp.updated_at DESC, tp.id DESC
                 LIMIT 1",
                $params
            );
            $participant = $rows[0] ?? [];
        }
        return [
            'status' => $participant ? 'connected' : 'database_ready',
            'connected' => true,
            'source' => 'training_lab_database',
            'user_id' => $userId,
            'microgifter_user_id' => 'training-lab-user-' . ($userId ?: 'preview'),
            'display_name' => (string)($participant['participant_label'] ?? 'Training Lab User'),
            'email' => (string)($participant['participant_email'] ?? ''),
            'campaign_id' => (string)($participant['campaign_public_id'] ?? $participant['campaign_slug'] ?? ''),
            'campaign_name' => (string)($participant['campaign_title'] ?? ''),
            'detail' => 'Read-only account status derived from Training Lab database.',
        ];
    }
}

if (!function_exists('microgifter_customer_account_status')) {
    function microgifter_customer_account_status(int $userId = 0): array
    {
        return microgifter_user_account_status($userId);
    }
}

if (!function_exists('microgifter_training_user_account_status')) {
    function microgifter_training_user_account_status(int $userId = 0): array
    {
        return microgifter_user_account_status($userId);
    }
}

if (!function_exists('microgifter_adapter_status')) {
    function microgifter_adapter_status(): array
    {
        return [
            'status' => tl_stage884_adapter_source() === 'training_lab_database' ? 'connected' : 'fixture',
            'mode' => 'training_lab_database_read_adapter',
            'connected' => tl_stage884_adapter_source() === 'training_lab_database',
            'developer_key_present' => false,
            'source' => tl_stage884_adapter_source(),
            'tables' => [
                'training_campaigns' => tl_stage884_count_table('training_campaigns'),
                'training_campaign_tasks' => tl_stage884_count_table('training_campaign_tasks'),
                'training_participants' => tl_stage884_count_table('training_participants'),
                'training_proof_submissions' => tl_stage884_count_table('training_proof_submissions'),
                'training_reward_rules' => tl_stage884_count_table('training_reward_rules'),
                'training_reward_events' => tl_stage884_count_table('training_reward_events'),
            ],
            'boundary' => 'Read-only Training Lab DB adapter; no Microgifter mutation.',
        ];
    }
}

if (!function_exists('microgifter_training_sync_status')) {
    function microgifter_training_sync_status(): array
    {
        return microgifter_adapter_status();
    }
}

if (!function_exists('microgifter_adapter_sync_status')) {
    function microgifter_adapter_sync_status(): array
    {
        return microgifter_adapter_status();
    }
}

if (!function_exists('microgifter_campaign_sync_health')) {
    function microgifter_campaign_sync_health(): array
    {
        $campaigns = microgifter_training_campaign_catalog();
        $needsReview = 0;
        foreach ($campaigns as $campaign) {
            if ((int)($campaign['quantity_available'] ?? 0) <= 0 || (int)($campaign['task_count'] ?? 0) <= 0) $needsReview++;
        }
        return [
            'status' => 'connected',
            'mode' => 'training_lab_database_read_adapter',
            'inventory_freshness' => $needsReview === 0 ? 'fresh' : 'needs_review',
            'campaign_count' => count($campaigns),
            'needs_review_count' => $needsReview,
            'source' => 'training_lab_database',
            'boundary' => 'Freshness is read-only; no destructive sync.',
        ];
    }
}

if (!function_exists('microgifter_reward_inventory_refresh_preview')) {
    function microgifter_reward_inventory_refresh_preview(): array
    {
        return microgifter_campaign_sync_health();
    }
}

if (!function_exists('tl_stage884_real_read_adapter_summary')) {
    function tl_stage884_real_read_adapter_summary(int $userId = 0): array
    {
        $campaigns = microgifter_training_campaign_catalog();
        $awards = microgifter_training_user_awards($userId);
        $account = microgifter_user_account_status($userId);
        $status = microgifter_adapter_status();
        $inventory = microgifter_campaign_sync_health();
        $ready = !empty($status['connected']) && count($campaigns) > 0;
        return [
            'stage' => 'Stage 884 Real Microgifter Read Adapter Connection',
            'built_from' => 'Stage 883 Read-only Microgifter Adapter Wiring',
            'accepted' => $ready,
            'score' => $ready ? 100 : 80,
            'adapter_source' => tl_stage884_adapter_source(),
            'campaign_count' => count($campaigns),
            'award_count' => count($awards),
            'account_status' => $account,
            'adapter_status' => $status,
            'inventory_status' => $inventory,
            'sample_campaigns' => array_slice($campaigns, 0, 5),
            'sample_awards' => array_slice($awards, 0, 5),
            'safe_boundaries' => [
                'read_only_database_queries' => true,
                'no_new_sql' => true,
                'no_config_files_moved_or_overwritten' => true,
                'no_hard_auth_gates_forced' => true,
                'no_payment_processing' => true,
                'no_wallet_balance_mutation' => true,
                'no_production_claim_redeem_mutation' => true,
                'no_destructive_microgifter_sync' => true,
                'no_reward_issuing' => true,
            ],
            'next_recommended_step' => 'Use the live read adapter rows to drive the first real Training Lab operating workflow while mutation remains closed.',
        ];
    }
}
