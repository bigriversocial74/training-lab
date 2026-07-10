<?php
/** Controlled Training Lab writes. No uploads, payments, wallet, or claim/redeem mutation. */
require_once __DIR__ . '/training-lab-stage34-service.php';

if (!function_exists('tl_action_clean')) {
    function tl_action_clean($value, int $max = 500, bool $required = false, string $label = 'Value'): string
    {
        $value = trim((string)$value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        if ($max > 0 && mb_strlen($value) > $max) $value = mb_substr($value, 0, $max);
        if ($required && $value === '') throw new RuntimeException($label . ' is required.');
        return $value;
    }
}

if (!function_exists('tl_action_enum')) {
    function tl_action_enum($value, array $allowed, string $fallback): string
    {
        $value = strtolower(trim((string)$value));
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}

if (!function_exists('tl_action_external_url')) {
    function tl_action_external_url($value): ?string
    {
        $value = tl_action_clean($value, 2048);
        if ($value === '') return null;
        if (!filter_var($value, FILTER_VALIDATE_URL)) throw new RuntimeException('Proof URL is invalid.');
        $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http','https'], true)) throw new RuntimeException('Proof URL must use http or https.');
        return $value;
    }
}

if (!function_exists('tl_require_db')) {
    function tl_require_db(): PDO
    {
        $pdo = tl_db();
        if (!$pdo) throw new RuntimeException('Training Lab database is not available. Check the private deployment configuration.');
        foreach (['training_campaigns','training_campaign_tasks','training_participants','training_proof_submissions','training_reviews','training_action_receipts','training_reward_rules','training_reward_events','training_streaks','training_events'] as $table) {
            if (!tl_table_exists($table)) throw new RuntimeException('Training Lab schema is incomplete. Missing required table: ' . $table . '.');
        }
        return $pdo;
    }
}

if (!function_exists('tl_log_event')) {
    function tl_log_event(?PDO $pdo, ?int $actorUserId, string $subjectType, ?int $subjectId, string $eventType, array $metadata = []): void
    {
        if (!$pdo) return;
        if (!in_array($subjectType, ['campaign','task','participant','proof','review','receipt','reward_rule','reward_event','streak','system'], true)) $subjectType = 'system';
        $eventType = tl_action_clean($eventType, 120, true, 'Event type');
        $stmt = $pdo->prepare('INSERT INTO training_events (public_id, actor_user_id, subject_type, subject_id, event_type, metadata_json) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([tl_uuid(), $actorUserId, $subjectType, $subjectId, $eventType, json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
    }
}

if (!function_exists('tl_find_campaign_row')) {
    function tl_find_campaign_row(PDO $pdo, string $campaignRef): ?array
    {
        $campaignRef = tl_action_clean($campaignRef, 180, true, 'Campaign reference');
        $stmt = $pdo->prepare('SELECT * FROM training_campaigns WHERE public_id = ? OR slug = ? OR id = ? LIMIT 1');
        $stmt->execute([$campaignRef, $campaignRef, ctype_digit($campaignRef) ? (int)$campaignRef : 0]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('tl_create_campaign')) {
    function tl_create_campaign(array $input): array
    {
        $pdo = tl_require_db();
        $title = tl_action_clean($input['title'] ?? '5-Day Movement Challenge', 180, true, 'Campaign title');
        $ownerUserId = max(1, (int)($input['owner_user_id'] ?? $input['created_by_user_id'] ?? 1));
        $creatorId = max(1, (int)($input['created_by_user_id'] ?? $ownerUserId));
        $slug = tl_slug((string)($input['slug'] ?? $title));
        $slugCheck = $pdo->prepare('SELECT id FROM training_campaigns WHERE owner_user_id = ? AND slug = ? LIMIT 1');
        $slugCheck->execute([$ownerUserId, $slug]);
        if ($slugCheck->fetchColumn()) $slug .= '-' . strtolower(substr(tl_uuid(), 0, 8));
        $publicId = tl_uuid();
        $summary = tl_action_clean($input['summary'] ?? 'Proof-based Training Lab campaign.', 500);
        $description = tl_action_clean($input['description'] ?? 'Complete the action sequence, submit proof, and become eligible for a Training Lab reward event.', 5000);
        $target = max(1, min(200, (int)($input['target_action_count'] ?? 5)));
        $rewardLabel = tl_action_clean($input['reward_label'] ?? $input['reward_summary'] ?? 'Movement Milestone', 255, true, 'Reward label');
        $campaignType = tl_action_enum($input['campaign_type'] ?? 'movement', ['movement','learning','onboarding','service','sales','wellness','custom'], 'custom');
        $visibility = tl_action_enum($input['visibility'] ?? 'published', ['draft','private','published','archived'], 'draft');
        $status = tl_action_enum($input['status'] ?? 'active', ['draft','scheduled','active','paused','completed','archived'], 'draft');
        $currency = strtoupper(tl_action_clean($input['currency'] ?? 'USD', 3));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = 'USD';
        $rewardValue = max(0, min(100000000, (int)($input['reward_value_cents'] ?? 0)));

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO training_campaigns (public_id, owner_user_id, created_by_user_id, slug, title, summary, description, campaign_type, visibility, status, target_action_count, reward_summary, settings_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$publicId, $ownerUserId, $creatorId, $slug, $title, $summary, $description, $campaignType, $visibility, $status, $target, $rewardLabel, json_encode(['source'=>'stage7_controlled_action'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            $campaignId = (int)$pdo->lastInsertId();
            $taskStmt = $pdo->prepare('INSERT INTO training_campaign_tasks (public_id, campaign_id, position_no, day_no, task_type, title, instructions, proof_required, expected_duration_minutes, status, settings_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            for ($i = 1; $i <= $target; $i++) {
                $taskInput = is_array($input['tasks'][$i - 1] ?? null) ? $input['tasks'][$i - 1] : [];
                $taskTitle = tl_action_clean($taskInput['title'] ?? ($i === $target ? 'Final proof submission' : 'Day ' . $i . ' training action'), 180, true, 'Task title');
                $taskInstructions = tl_action_clean($taskInput['instructions'] ?? 'Complete this action and keep proof ready for review.', 5000);
                $taskType = tl_action_enum($taskInput['task_type'] ?? ($i === $target ? 'text_reflection' : 'checklist'), ['checklist','movement','photo_proof','video_proof','text_reflection','quiz','custom'], 'checklist');
                $proofRequired = array_key_exists('proof_required', $taskInput) ? (int)(bool)$taskInput['proof_required'] : ($i === $target ? 1 : 0);
                $duration = max(1, min(1440, (int)($taskInput['expected_duration_minutes'] ?? 15)));
                $taskStmt->execute([tl_uuid(), $campaignId, $i, $i, $taskType, $taskTitle, $taskInstructions, $proofRequired, $duration, 'active', json_encode(['stage'=>'stage35_campaign_builder'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            }
            $rewardType = tl_action_enum($input['reward_type'] ?? 'badge', ['badge','microgift','entitlement','wallet_credit_preview','custom'], 'badge');
            $linkedTemplate = isset($input['linked_microgift_template_id']) && ctype_digit((string)$input['linked_microgift_template_id']) ? (int)$input['linked_microgift_template_id'] : null;
            $linkedProduct = isset($input['linked_catalog_product_id']) && ctype_digit((string)$input['linked_catalog_product_id']) ? (int)$input['linked_catalog_product_id'] : null;
            $ruleStmt = $pdo->prepare('INSERT INTO training_reward_rules (public_id, campaign_id, rule_name, trigger_type, threshold_count, reward_type, reward_label, reward_value_cents, currency, linked_microgift_template_id, linked_catalog_product_id, status, settings_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ruleStmt->execute([tl_uuid(), $campaignId, $rewardLabel . ' eligibility', 'action_count', $target, $rewardType, $rewardLabel, $rewardValue, $currency, $linkedTemplate, $linkedProduct, 'active', json_encode(['wallet_write'=>false,'microgifter_reward_bridge'=>!empty($input['microgifter_reward_bridge']),'catalog_template_ref'=>tl_action_clean($input['catalog_template_ref'] ?? '', 180)], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            tl_log_event($pdo, $creatorId, 'campaign', $campaignId, 'campaign_created', ['public_id'=>$publicId,'slug'=>$slug]);
            $pdo->commit();
            return ['campaign_id'=>$campaignId,'public_id'=>$publicId,'slug'=>$slug,'title'=>$title];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_seed_demo_campaigns')) {
    function tl_seed_demo_campaigns(array $input = []): array
    {
        $pdo = tl_require_db();
        $count = (int)$pdo->query('SELECT COUNT(*) FROM training_campaigns')->fetchColumn();
        if ($count > 0 && empty($input['force'])) return ['seeded'=>false,'reason'=>'training_campaigns already has rows','campaign_count'=>$count];
        $created = [];
        foreach (tl_stage34_seed()['campaigns'] as $campaign) {
            $created[] = tl_create_campaign(['title'=>$campaign['title'],'slug'=>$campaign['id'],'summary'=>$campaign['description'],'description'=>$campaign['description'],'target_action_count'=>$campaign['total_actions'],'reward_label'=>$campaign['reward'],'status'=>strtolower($campaign['status']) === 'draft' ? 'draft' : 'active','visibility'=>'published','owner_user_id'=>$input['owner_user_id'] ?? 1,'created_by_user_id'=>$input['created_by_user_id'] ?? 1]);
        }
        return ['seeded'=>true,'created'=>$created];
    }
}

if (!function_exists('tl_join_campaign')) {
    function tl_join_campaign(array $input): array
    {
        $pdo = tl_require_db();
        $campaignRef = (string)($input['campaign'] ?? $input['campaign_id'] ?? $input['slug'] ?? 'movement-5');
        $campaign = tl_find_campaign_row($pdo, $campaignRef);
        if (!$campaign) throw new RuntimeException('Campaign not found.');
        $userId = max(1, (int)($input['user_id'] ?? 1));
        $label = tl_action_clean($input['participant_label'] ?? 'Training Participant', 180, true, 'Participant label');
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM training_participants WHERE campaign_id = ? AND user_id = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([(int)$campaign['id'], $userId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) { $pdo->commit(); return ['participant_id'=>(int)$existing['id'],'public_id'=>$existing['public_id'],'already_joined'=>true]; }
            $publicId = tl_uuid();
            $invitedBy = isset($input['invited_by_user_id']) ? max(1, (int)$input['invited_by_user_id']) : null;
            $stmt = $pdo->prepare('INSERT INTO training_participants (public_id, campaign_id, user_id, invited_by_user_id, participant_label, status, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$publicId, (int)$campaign['id'], $userId, $invitedBy, $label, 'active', json_encode(['source'=>'stage7_join'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            $participantId = (int)$pdo->lastInsertId();
            $streak = $pdo->prepare('INSERT IGNORE INTO training_streaks (campaign_id, participant_id, user_id, current_streak_days, longest_streak_days, completed_action_count) VALUES (?, ?, ?, 0, 0, 0)');
            $streak->execute([(int)$campaign['id'], $participantId, $userId]);
            tl_log_event($pdo, $userId, 'participant', $participantId, 'campaign_joined', ['campaign_id'=>(int)$campaign['id']]);
            $pdo->commit();
            return ['participant_id'=>$participantId,'public_id'=>$publicId,'already_joined'=>false];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
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
        $join = tl_join_campaign(['campaign_id'=>(string)$campaign['id'],'user_id'=>$userId,'participant_label'=>$input['participant_label'] ?? 'Training Participant']);
        $participantId = (int)$join['participant_id'];
        if (!empty($input['task_id'])) {
            $taskRef = tl_action_clean($input['task_id'], 180, true, 'Task reference');
            $stmt = $pdo->prepare('SELECT * FROM training_campaign_tasks WHERE campaign_id = ? AND (id = ? OR public_id = ?) LIMIT 1');
            $stmt->execute([(int)$campaign['id'], ctype_digit($taskRef) ? (int)$taskRef : 0, $taskRef]);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM training_campaign_tasks WHERE campaign_id = ? AND status = ? ORDER BY proof_required DESC, position_no DESC LIMIT 1');
            $stmt->execute([(int)$campaign['id'], 'active']);
        }
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) throw new RuntimeException('Task not found for the selected campaign.');
        $publicId = tl_uuid();
        $proofText = tl_action_clean($input['proof_text'] ?? 'Participant completed the training action.', 5000, true, 'Proof text');
        $externalUrl = tl_action_external_url($input['external_url'] ?? '');
        $stmt = $pdo->prepare('INSERT INTO training_proof_submissions (public_id, campaign_id, task_id, participant_id, submitted_by_user_id, proof_type, proof_text, external_url, status, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$publicId, (int)$campaign['id'], (int)$task['id'], $participantId, $userId, $externalUrl ? 'external_link' : 'text', $proofText, $externalUrl, 'submitted', json_encode(['real_upload'=>false,'stage'=>'stage7_controlled_proof_record'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
        $proofId = (int)$pdo->lastInsertId();
        tl_log_event($pdo, $userId, 'proof', $proofId, 'proof_submitted', ['real_upload'=>false,'task_id'=>(int)$task['id']]);
        return ['proof_submission_id'=>$proofId,'public_id'=>$publicId,'status'=>'submitted'];
    }
}

if (!function_exists('tl_review_proof')) {
    function tl_review_proof(array $input): array
    {
        $pdo = tl_require_db();
        $proofRef = tl_action_clean($input['proof'] ?? $input['proof_id'] ?? $input['public_id'] ?? '', 180);
        $decision = tl_action_enum($input['decision'] ?? '', ['approved','rejected','needs_more_info'], '');
        if ($decision === '') throw new RuntimeException('A valid review decision is required.');
        $reviewerUserId = max(1, (int)($input['reviewer_user_id'] ?? 1));
        $reviewNotes = tl_action_clean($input['review_notes'] ?? '', 4000);
        $pdo->beginTransaction();
        try {
            if ($proofRef === '') $stmt = $pdo->query("SELECT * FROM training_proof_submissions WHERE status IN ('submitted','in_review') ORDER BY submitted_at ASC, id ASC LIMIT 1 FOR UPDATE");
            else { $stmt = $pdo->prepare('SELECT * FROM training_proof_submissions WHERE id = ? OR public_id = ? LIMIT 1 FOR UPDATE'); $stmt->execute([ctype_digit($proofRef) ? (int)$proofRef : 0, $proofRef]); }
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (!$row) throw new RuntimeException('Proof submission not found.');
            if (in_array((string)$row['status'], ['approved','rejected'], true)) {
                $reviewStmt = $pdo->prepare('SELECT id, public_id, decision FROM training_reviews WHERE proof_submission_id = ? ORDER BY id DESC LIMIT 1');
                $reviewStmt->execute([(int)$row['id']]);
                $existingReview = $reviewStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $receiptStmt = $pdo->prepare("SELECT id, public_id, verification_hash FROM training_action_receipts WHERE proof_submission_id = ? AND receipt_type = 'task_completed' AND receipt_status = 'active' ORDER BY id ASC LIMIT 1");
                $receiptStmt->execute([(int)$row['id']]);
                $existingReceipt = $receiptStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                $pdo->commit();
                return ['review_id'=>(int)($existingReview['id'] ?? 0),'public_id'=>(string)($existingReview['public_id'] ?? ''),'decision'=>(string)($existingReview['decision'] ?? $row['status']),'proof_status'=>(string)$row['status'],'receipt'=>$existingReceipt ? ['receipt_id'=>(int)$existingReceipt['id'],'public_id'=>(string)$existingReceipt['public_id'],'verification_hash'=>(string)$existingReceipt['verification_hash'],'reused'=>true] : null,'idempotent'=>true];
            }
            $reviewPublicId = tl_uuid();
            $stmt = $pdo->prepare('INSERT INTO training_reviews (public_id, proof_submission_id, reviewer_user_id, decision, review_notes, metadata_json) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$reviewPublicId, (int)$row['id'], $reviewerUserId, $decision, $reviewNotes !== '' ? $reviewNotes : null, json_encode(['stage'=>'stage7_manual_review'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            $reviewId = (int)$pdo->lastInsertId();
            $proofStatus = $decision === 'approved' ? 'approved' : ($decision === 'rejected' ? 'rejected' : 'in_review');
            $upd = $pdo->prepare('UPDATE training_proof_submissions SET status = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?');
            $upd->execute([$proofStatus, (int)$row['id']]);
            $receipt = null;
            if ($decision === 'approved') {
                $receipt = tl_create_action_receipt($pdo, $row, $reviewId);
                tl_evaluate_rewards_for_participant($pdo, (int)$row['campaign_id'], (int)$row['participant_id'], (int)$row['submitted_by_user_id'], $receipt['receipt_id']);
            }
            tl_log_event($pdo, $reviewerUserId, 'review', $reviewId, 'proof_reviewed', ['decision'=>$decision]);
            $pdo->commit();
            return ['review_id'=>$reviewId,'public_id'=>$reviewPublicId,'decision'=>$decision,'proof_status'=>$proofStatus,'receipt'=>$receipt,'idempotent'=>false];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_create_action_receipt')) {
    function tl_create_action_receipt(PDO $pdo, array $proofRow, int $reviewId): array
    {
        $existing = $pdo->prepare("SELECT id, public_id, verification_hash FROM training_action_receipts WHERE proof_submission_id = ? AND receipt_type = 'task_completed' AND receipt_status = 'active' ORDER BY id ASC LIMIT 1 FOR UPDATE");
        $existing->execute([(int)$proofRow['id']]);
        $existingReceipt = $existing->fetch(PDO::FETCH_ASSOC);
        if ($existingReceipt) {
            tl_log_event($pdo, (int)$proofRow['submitted_by_user_id'], 'receipt', (int)$existingReceipt['id'], 'action_receipt_reused', ['proof_submission_id'=>(int)$proofRow['id'],'review_id'=>$reviewId]);
            return ['receipt_id'=>(int)$existingReceipt['id'],'public_id'=>(string)$existingReceipt['public_id'],'verification_hash'=>(string)$existingReceipt['verification_hash'],'reused'=>true];
        }
        $publicId = tl_uuid();
        $hash = hash('sha256', random_bytes(32) . '|' . $proofRow['id'] . '|' . $reviewId);
        $stmt = $pdo->prepare('INSERT INTO training_action_receipts (public_id, campaign_id, participant_id, user_id, proof_submission_id, review_id, receipt_type, verification_hash, receipt_status, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$publicId, (int)$proofRow['campaign_id'], (int)$proofRow['participant_id'], (int)$proofRow['submitted_by_user_id'], (int)$proofRow['id'], $reviewId, 'task_completed', $hash, 'active', json_encode(['stage'=>'stage30_action_receipt','duplicate_guard'=>true], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
        $receiptId = (int)$pdo->lastInsertId();
        $upd = $pdo->prepare('UPDATE training_streaks SET completed_action_count = completed_action_count + 1, current_streak_days = current_streak_days + 1, longest_streak_days = GREATEST(longest_streak_days, current_streak_days + 1), last_action_date = CURRENT_DATE WHERE participant_id = ?');
        $upd->execute([(int)$proofRow['participant_id']]);
        tl_log_event($pdo, (int)$proofRow['submitted_by_user_id'], 'receipt', $receiptId, 'action_receipt_created', ['proof_submission_id'=>(int)$proofRow['id'],'duplicate_guard'=>true]);
        return ['receipt_id'=>$receiptId,'public_id'=>$publicId,'verification_hash'=>$hash,'reused'=>false];
    }
}

if (!function_exists('tl_evaluate_rewards_for_participant')) {
    function tl_evaluate_rewards_for_participant(PDO $pdo, int $campaignId, int $participantId, int $userId, ?int $receiptId = null): array
    {
        $streakStmt = $pdo->prepare('SELECT * FROM training_streaks WHERE participant_id = ? LIMIT 1 FOR UPDATE');
        $streakStmt->execute([$participantId]);
        $streak = $streakStmt->fetch(PDO::FETCH_ASSOC) ?: ['completed_action_count'=>0,'current_streak_days'=>0];
        $rules = $pdo->prepare("SELECT * FROM training_reward_rules WHERE campaign_id = ? AND status = 'active' ORDER BY threshold_count ASC");
        $rules->execute([$campaignId]);
        $created = [];
        foreach ($rules->fetchAll(PDO::FETCH_ASSOC) as $rule) {
            $qualifies = false;
            if ($rule['trigger_type'] === 'action_count' && (int)$streak['completed_action_count'] >= (int)$rule['threshold_count']) $qualifies = true;
            if ($rule['trigger_type'] === 'sequence_completed' && (int)$streak['completed_action_count'] >= (int)$rule['threshold_count']) $qualifies = true;
            if ($rule['trigger_type'] === 'streak_days' && (int)$streak['current_streak_days'] >= (int)$rule['threshold_count']) $qualifies = true;
            if (!$qualifies || $rule['trigger_type'] === 'manual') continue;
            $dupe = $pdo->prepare("SELECT id FROM training_reward_events WHERE participant_id = ? AND reward_rule_id = ? AND status <> 'cancelled' LIMIT 1 FOR UPDATE");
            $dupe->execute([$participantId, (int)$rule['id']]);
            if ($dupe->fetchColumn()) continue;
            $publicId = tl_uuid();
            $stmt = $pdo->prepare('INSERT INTO training_reward_events (public_id, campaign_id, participant_id, user_id, action_receipt_id, reward_rule_id, status, value_cents, currency, eligibility_reason, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$publicId, $campaignId, $participantId, $userId, $receiptId, (int)$rule['id'], 'eligible', max(0, (int)$rule['reward_value_cents']), (string)$rule['currency'], 'Training Lab eligibility reached. Wallet/reward issuing not executed by this action.', json_encode(['wallet_write'=>false,'claim_redeem'=>false,'claim_status'=>'not_claimed','lifecycle_state'=>'available_to_claim','source'=>'training_reward_rule'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            $eventId = (int)$pdo->lastInsertId();
            tl_log_event($pdo, $userId, 'reward_event', $eventId, 'reward_eligible', ['wallet_write'=>false]);
            $created[] = ['reward_event_id'=>$eventId,'public_id'=>$publicId,'status'=>'eligible'];
        }
        return $created;
    }
}
