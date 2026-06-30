<?php
/**
 * Controlled Training Lab write actions.
 *
 * Writes only to Training Lab tables imported by Stage 6. Does not upload files,
 * process payments, issue wallet balances, run claim/redeem logic, or create auth.
 */
require_once __DIR__ . '/training-lab-stage34-service.php';

if (!function_exists('tl_require_db')) {
    function tl_require_db(): PDO
    {
        $pdo = tl_db();
        if (!$pdo) throw new RuntimeException('Training Lab DB config not ready. Edit /labs/config.php after moving the outer /labs contents to root.');
        foreach (['training_campaigns','training_campaign_tasks','training_participants','training_proof_submissions','training_reviews','training_action_receipts','training_reward_rules','training_reward_events','training_streaks','training_events'] as $table) {
            if (!tl_table_exists($table)) throw new RuntimeException('Missing required table: ' . $table . '. Import training_lab_stage6_consolidated_import_safe.sql first.');
        }
        return $pdo;
    }
}

if (!function_exists('tl_log_event')) {
    function tl_log_event(?PDO $pdo, ?int $actorUserId, string $subjectType, ?int $subjectId, string $eventType, array $metadata = []): void
    {
        if (!$pdo) return;
        $stmt = $pdo->prepare('INSERT INTO training_events (public_id, actor_user_id, subject_type, subject_id, event_type, metadata_json) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([tl_uuid(), $actorUserId, $subjectType, $subjectId, $eventType, json_encode($metadata, JSON_UNESCAPED_SLASHES)]);
    }
}

if (!function_exists('tl_find_campaign_row')) {
    function tl_find_campaign_row(PDO $pdo, string $campaignRef): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM training_campaigns WHERE public_id = ? OR slug = ? OR id = ? LIMIT 1');
        $stmt->execute([$campaignRef, $campaignRef, ctype_digit($campaignRef) ? (int)$campaignRef : 0]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

if (!function_exists('tl_create_campaign')) {
    function tl_create_campaign(array $input): array
    {
        $pdo = tl_require_db();
        $title = trim((string)($input['title'] ?? '5-Day Movement Challenge'));
        $ownerUserId = max(1, (int)($input['owner_user_id'] ?? $input['created_by_user_id'] ?? 1));
        $creatorId = max(1, (int)($input['created_by_user_id'] ?? $ownerUserId));
        $slug = tl_slug((string)($input['slug'] ?? $title));
        $slugCheck = $pdo->prepare('SELECT id FROM training_campaigns WHERE owner_user_id = ? AND slug = ? LIMIT 1');
        $slugCheck->execute([$ownerUserId, $slug]);
        if ($slugCheck->fetchColumn()) {
            $slug .= '-' . strtolower(substr(tl_uuid(), 0, 8));
        }
        $publicId = tl_uuid();
        $summary = trim((string)($input['summary'] ?? 'Proof-based Training Lab campaign.'));
        $description = trim((string)($input['description'] ?? 'Complete the action sequence, submit proof, and become eligible for a Training Lab reward event.'));
        $target = max(1, (int)($input['target_action_count'] ?? 5));
        $rewardLabel = trim((string)($input['reward_label'] ?? $input['reward_summary'] ?? 'Movement Milestone'));
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO training_campaigns (public_id, owner_user_id, created_by_user_id, slug, title, summary, description, campaign_type, visibility, status, target_action_count, reward_summary, settings_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$publicId, $ownerUserId, $creatorId, $slug, $title, $summary, $description, $input['campaign_type'] ?? 'movement', $input['visibility'] ?? 'published', $input['status'] ?? 'active', $target, $rewardLabel, json_encode(['source' => 'stage7_controlled_action'], JSON_UNESCAPED_SLASHES)]);
            $campaignId = (int)$pdo->lastInsertId();
            $taskStmt = $pdo->prepare('INSERT INTO training_campaign_tasks (public_id, campaign_id, position_no, day_no, task_type, title, instructions, proof_required, expected_duration_minutes, status, settings_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            for ($i = 1; $i <= $target; $i++) {
                $taskInput = is_array($input['tasks'][$i - 1] ?? null) ? $input['tasks'][$i - 1] : [];
                $taskTitle = $taskInput['title'] ?? ($i === $target ? 'Final proof submission' : 'Day ' . $i . ' training action');
                $taskInstructions = $taskInput['instructions'] ?? 'Complete this action and keep proof ready for review.';
                $taskType = $taskInput['task_type'] ?? ($i === $target ? 'text_reflection' : 'checklist');
                if (!in_array($taskType, ['checklist','movement','photo_proof','video_proof','text_reflection','quiz','custom'], true)) $taskType = 'checklist';
                $proofRequired = array_key_exists('proof_required', $taskInput) ? (int)$taskInput['proof_required'] : ($i === $target ? 1 : 0);
                $taskStmt->execute([tl_uuid(), $campaignId, $i, $i, $taskType, $taskTitle, $taskInstructions, $proofRequired ? 1 : 0, 15, 'active', json_encode(['stage' => 'stage35_campaign_builder'], JSON_UNESCAPED_SLASHES)]);
            }
            $rewardType = (string)($input['reward_type'] ?? 'badge');
            if (!in_array($rewardType, ['badge','microgift','entitlement','wallet_credit_preview','custom'], true)) $rewardType = 'badge';
            $linkedTemplate = isset($input['linked_microgift_template_id']) && ctype_digit((string)$input['linked_microgift_template_id']) ? (int)$input['linked_microgift_template_id'] : null;
            $linkedProduct = isset($input['linked_catalog_product_id']) && ctype_digit((string)$input['linked_catalog_product_id']) ? (int)$input['linked_catalog_product_id'] : null;
            $ruleSettings = [
                'wallet_write' => false,
                'microgifter_reward_bridge' => !empty($input['microgifter_reward_bridge']),
                'catalog_template_ref' => (string)($input['catalog_template_ref'] ?? ''),
            ];
            $ruleStmt = $pdo->prepare('INSERT INTO training_reward_rules (public_id, campaign_id, rule_name, trigger_type, threshold_count, reward_type, reward_label, reward_value_cents, currency, linked_microgift_template_id, linked_catalog_product_id, status, settings_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ruleStmt->execute([tl_uuid(), $campaignId, $rewardLabel . ' eligibility', 'action_count', $target, $rewardType, $rewardLabel, (int)($input['reward_value_cents'] ?? 0), (string)($input['currency'] ?? 'USD'), $linkedTemplate, $linkedProduct, 'active', json_encode($ruleSettings, JSON_UNESCAPED_SLASHES)]);
            tl_log_event($pdo, $creatorId, 'campaign', $campaignId, 'campaign_created', ['public_id' => $publicId, 'slug' => $slug]);
            $pdo->commit();
            return ['campaign_id' => $campaignId, 'public_id' => $publicId, 'slug' => $slug, 'title' => $title];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_seed_demo_campaigns')) {
    function tl_seed_demo_campaigns(array $input = []): array
    {
        $pdo = tl_require_db();
        $count = (int)$pdo->query('SELECT COUNT(*) FROM training_campaigns')->fetchColumn();
        if ($count > 0 && empty($input['force'])) return ['seeded' => false, 'reason' => 'training_campaigns already has rows', 'campaign_count' => $count];
        $created = [];
        foreach (tl_stage34_seed()['campaigns'] as $campaign) {
            $created[] = tl_create_campaign([
                'title' => $campaign['title'],
                'slug' => $campaign['id'],
                'summary' => $campaign['description'],
                'description' => $campaign['description'],
                'target_action_count' => $campaign['total_actions'],
                'reward_label' => $campaign['reward'],
                'status' => strtolower($campaign['status']) === 'draft' ? 'draft' : 'active',
                'visibility' => 'published',
                'owner_user_id' => $input['owner_user_id'] ?? 1,
                'created_by_user_id' => $input['created_by_user_id'] ?? 1,
            ]);
        }
        return ['seeded' => true, 'created' => $created];
    }
}

if (!function_exists('tl_join_campaign')) {
    function tl_join_campaign(array $input): array
    {
        $pdo = tl_require_db();
        $campaignRef = (string)($input['campaign'] ?? $input['campaign_id'] ?? $input['slug'] ?? 'movement-5');
        $campaign = tl_find_campaign_row($pdo, $campaignRef);
        if (!$campaign) throw new RuntimeException('Campaign not found: ' . $campaignRef);
        $userId = max(1, (int)($input['user_id'] ?? 1));
        $label = trim((string)($input['participant_label'] ?? 'Demo Participant'));
        $stmt = $pdo->prepare('SELECT * FROM training_participants WHERE campaign_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([(int)$campaign['id'], $userId]);
        $existing = $stmt->fetch();
        if ($existing) return ['participant_id' => (int)$existing['id'], 'public_id' => $existing['public_id'], 'already_joined' => true];
        $pdo->beginTransaction();
        try {
            $publicId = tl_uuid();
            $stmt = $pdo->prepare('INSERT INTO training_participants (public_id, campaign_id, user_id, invited_by_user_id, participant_label, status, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$publicId, (int)$campaign['id'], $userId, $input['invited_by_user_id'] ?? null, $label, 'active', json_encode(['source' => 'stage7_join'], JSON_UNESCAPED_SLASHES)]);
            $participantId = (int)$pdo->lastInsertId();
            $streak = $pdo->prepare('INSERT IGNORE INTO training_streaks (campaign_id, participant_id, user_id, current_streak_days, longest_streak_days, completed_action_count) VALUES (?, ?, ?, 0, 0, 0)');
            $streak->execute([(int)$campaign['id'], $participantId, $userId]);
            tl_log_event($pdo, $userId, 'participant', $participantId, 'campaign_joined', ['campaign_id' => (int)$campaign['id']]);
            $pdo->commit();
            return ['participant_id' => $participantId, 'public_id' => $publicId, 'already_joined' => false];
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}

if (!function_exists('tl_submit_proof')) {
    function tl_submit_proof(array $input): array
    {
        $pdo = tl_require_db();
        $campaignRef = (string)($input['campaign'] ?? $input['campaign_id'] ?? 'movement-5');
        $campaign = tl_find_campaign_row($pdo, $campaignRef);
        if (!$campaign) throw new RuntimeException('Campaign not found.');
        $userId = max(1, (int)($input['user_id'] ?? 1));
        $join = tl_join_campaign(['campaign_id' => (string)$campaign['id'], 'user_id' => $userId, 'participant_label' => $input['participant_label'] ?? 'Demo Participant']);
        $participantId = (int)$join['participant_id'];
        if (!empty($input['task_id'])) {
            $stmt = $pdo->prepare('SELECT * FROM training_campaign_tasks WHERE id = ? OR public_id = ? LIMIT 1');
            $stmt->execute([ctype_digit((string)$input['task_id']) ? (int)$input['task_id'] : 0, (string)$input['task_id']]);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM training_campaign_tasks WHERE campaign_id = ? ORDER BY proof_required DESC, position_no DESC LIMIT 1');
            $stmt->execute([(int)$campaign['id']]);
        }
        $task = $stmt->fetch();
        if (!$task) throw new RuntimeException('Task not found.');
        $publicId = tl_uuid();
        $proofText = trim((string)($input['proof_text'] ?? 'Participant completed the training action.'));
        $externalUrl = trim((string)($input['external_url'] ?? '')) ?: null;
        $stmt = $pdo->prepare('INSERT INTO training_proof_submissions (public_id, campaign_id, task_id, participant_id, submitted_by_user_id, proof_type, proof_text, external_url, status, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$publicId, (int)$campaign['id'], (int)$task['id'], $participantId, $userId, $externalUrl ? 'external_link' : 'text', $proofText, $externalUrl, 'submitted', json_encode(['real_upload' => false, 'stage' => 'stage7_controlled_proof_record'], JSON_UNESCAPED_SLASHES)]);
        $proofId = (int)$pdo->lastInsertId();
        tl_log_event($pdo, $userId, 'proof', $proofId, 'proof_submitted', ['real_upload' => false]);
        return ['proof_submission_id' => $proofId, 'public_id' => $publicId, 'status' => 'submitted'];
    }
}

if (!function_exists('tl_review_proof')) {
    function tl_review_proof(array $input): array
    {
        $pdo = tl_require_db();
        $proofRef = (string)($input['proof'] ?? $input['proof_id'] ?? $input['public_id'] ?? '');
        if ($proofRef === '') {
            $row = $pdo->query("SELECT * FROM training_proof_submissions WHERE status IN ('submitted','in_review') ORDER BY submitted_at ASC LIMIT 1")->fetch();
        } else {
            $stmt = $pdo->prepare('SELECT * FROM training_proof_submissions WHERE id = ? OR public_id = ? LIMIT 1');
            $stmt->execute([ctype_digit($proofRef) ? (int)$proofRef : 0, $proofRef]);
            $row = $stmt->fetch();
        }
        if (!$row) throw new RuntimeException('Proof submission not found.');
        $decision = in_array(($input['decision'] ?? 'approved'), ['approved','rejected','needs_more_info'], true) ? $input['decision'] : 'approved';
        $reviewerUserId = max(1, (int)($input['reviewer_user_id'] ?? 1));
        $pdo->beginTransaction();
        try {
            $reviewPublicId = tl_uuid();
            $stmt = $pdo->prepare('INSERT INTO training_reviews (public_id, proof_submission_id, reviewer_user_id, decision, review_notes, metadata_json) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$reviewPublicId, (int)$row['id'], $reviewerUserId, $decision, $input['review_notes'] ?? null, json_encode(['stage' => 'stage7_manual_review'], JSON_UNESCAPED_SLASHES)]);
            $reviewId = (int)$pdo->lastInsertId();
            $proofStatus = $decision === 'approved' ? 'approved' : ($decision === 'rejected' ? 'rejected' : 'in_review');
            $upd = $pdo->prepare('UPDATE training_proof_submissions SET status = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?');
            $upd->execute([$proofStatus, (int)$row['id']]);
            $receipt = null;
            if ($decision === 'approved') {
                $receipt = tl_create_action_receipt($pdo, $row, $reviewId);
                tl_evaluate_rewards_for_participant($pdo, (int)$row['campaign_id'], (int)$row['participant_id'], (int)$row['submitted_by_user_id'], $receipt['receipt_id']);
            }
            tl_log_event($pdo, $reviewerUserId, 'review', $reviewId, 'proof_reviewed', ['decision' => $decision]);
            $pdo->commit();
            return ['review_id' => $reviewId, 'public_id' => $reviewPublicId, 'decision' => $decision, 'proof_status' => $proofStatus, 'receipt' => $receipt];
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}

if (!function_exists('tl_create_action_receipt')) {
    function tl_create_action_receipt(PDO $pdo, array $proofRow, int $reviewId): array
    {
        $existing = $pdo->prepare("SELECT id, public_id, verification_hash FROM training_action_receipts WHERE proof_submission_id = ? AND receipt_type = 'task_completed' AND receipt_status = 'active' ORDER BY id ASC LIMIT 1");
        $existing->execute([(int)$proofRow['id']]);
        $existingReceipt = $existing->fetch();
        if ($existingReceipt) {
            tl_log_event($pdo, (int)$proofRow['submitted_by_user_id'], 'receipt', (int)$existingReceipt['id'], 'action_receipt_reused', ['proof_submission_id' => (int)$proofRow['id'], 'review_id' => $reviewId]);
            return ['receipt_id' => (int)$existingReceipt['id'], 'public_id' => (string)$existingReceipt['public_id'], 'verification_hash' => (string)$existingReceipt['verification_hash'], 'reused' => true];
        }

        $publicId = tl_uuid();
        $hash = hash('sha256', implode('|', [$proofRow['id'], $proofRow['campaign_id'], $proofRow['participant_id'], $reviewId, microtime(true)]));
        $stmt = $pdo->prepare('INSERT INTO training_action_receipts (public_id, campaign_id, participant_id, user_id, proof_submission_id, review_id, receipt_type, verification_hash, receipt_status, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$publicId, (int)$proofRow['campaign_id'], (int)$proofRow['participant_id'], (int)$proofRow['submitted_by_user_id'], (int)$proofRow['id'], $reviewId, 'task_completed', $hash, 'active', json_encode(['stage' => 'stage30_action_receipt', 'duplicate_guard' => true], JSON_UNESCAPED_SLASHES)]);
        $receiptId = (int)$pdo->lastInsertId();
        $upd = $pdo->prepare('UPDATE training_streaks SET completed_action_count = completed_action_count + 1, current_streak_days = current_streak_days + 1, longest_streak_days = GREATEST(longest_streak_days, current_streak_days + 1), last_action_date = CURRENT_DATE WHERE participant_id = ?');
        $upd->execute([(int)$proofRow['participant_id']]);
        tl_log_event($pdo, (int)$proofRow['submitted_by_user_id'], 'receipt', $receiptId, 'action_receipt_created', ['proof_submission_id' => (int)$proofRow['id'], 'duplicate_guard' => true]);
        return ['receipt_id' => $receiptId, 'public_id' => $publicId, 'verification_hash' => $hash, 'reused' => false];
    }
}

if (!function_exists('tl_evaluate_rewards_for_participant')) {
    function tl_evaluate_rewards_for_participant(PDO $pdo, int $campaignId, int $participantId, int $userId, ?int $receiptId = null): array
    {
        $streakStmt = $pdo->prepare('SELECT * FROM training_streaks WHERE participant_id = ? LIMIT 1');
        $streakStmt->execute([$participantId]);
        $streak = $streakStmt->fetch() ?: ['completed_action_count' => 0, 'current_streak_days' => 0];
        $rules = $pdo->prepare("SELECT * FROM training_reward_rules WHERE campaign_id = ? AND status = 'active' ORDER BY threshold_count ASC");
        $rules->execute([$campaignId]);
        $created = [];
        foreach ($rules->fetchAll() as $rule) {
            $qualifies = false;
            if ($rule['trigger_type'] === 'action_count' && (int)$streak['completed_action_count'] >= (int)$rule['threshold_count']) $qualifies = true;
            if ($rule['trigger_type'] === 'sequence_completed' && (int)$streak['completed_action_count'] >= (int)$rule['threshold_count']) $qualifies = true;
            if ($rule['trigger_type'] === 'streak_days' && (int)$streak['current_streak_days'] >= (int)$rule['threshold_count']) $qualifies = true;
            if ($rule['trigger_type'] === 'manual') $qualifies = false;
            if (!$qualifies) continue;
            $dupe = $pdo->prepare("SELECT id FROM training_reward_events WHERE participant_id = ? AND reward_rule_id = ? AND status <> 'cancelled' LIMIT 1");
            $dupe->execute([$participantId, (int)$rule['id']]);
            if ($dupe->fetchColumn()) continue;
            $publicId = tl_uuid();
            $stmt = $pdo->prepare('INSERT INTO training_reward_events (public_id, campaign_id, participant_id, user_id, action_receipt_id, reward_rule_id, status, value_cents, currency, eligibility_reason, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$publicId, $campaignId, $participantId, $userId, $receiptId, (int)$rule['id'], 'eligible', (int)$rule['reward_value_cents'], $rule['currency'], 'Training Lab eligibility reached. Wallet/reward issuing not executed by this action.', json_encode(['wallet_write' => false, 'claim_redeem' => false, 'claim_status' => 'not_claimed', 'lifecycle_state' => 'available_to_claim', 'source' => 'training_reward_rule'], JSON_UNESCAPED_SLASHES)]);
            $eventId = (int)$pdo->lastInsertId();
            tl_log_event($pdo, $userId, 'reward_event', $eventId, 'reward_eligible', ['wallet_write' => false]);
            $created[] = ['reward_event_id' => $eventId, 'public_id' => $publicId, 'status' => 'eligible'];
        }
        return $created;
    }
}
