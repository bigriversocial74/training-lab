<?php
/**
 * Stage 885 Proof Review + Award Handoff Preview.
 *
 * Real Training Lab operating workflow:
 * submitted proof -> review decision -> Training Lab receipt/eligibility record ->
 * award handoff preview. This remains inside Training Lab tables and does not
 * issue Microgifter rewards, mutate wallets, process payments, or redeem claims.
 */
require_once __DIR__ . '/training-lab-app-service.php';
require_once __DIR__ . '/training-lab-stage884-real-read-adapter.php';

if (!function_exists('tl_stage885_e')) { function tl_stage885_e($value): string { return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }

if (!function_exists('tl_stage885_fetch_queue')) {
    function tl_stage885_fetch_queue(int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $pdo = function_exists('tl_db') ? tl_db() : null;
        if (!$pdo || !tl_table_exists('training_proof_submissions')) return [];
        try {
            $sql = "SELECT p.id, p.public_id, p.campaign_id, p.task_id, p.participant_id, p.submitted_by_user_id, p.proof_type, p.proof_text, p.external_url, p.status AS proof_status, p.submitted_at, p.reviewed_at, p.created_at, p.updated_at,
                           c.public_id AS campaign_public_id, c.slug AS campaign_slug, c.title AS campaign_title, c.target_action_count,
                           t.title AS task_title, t.position_no, t.proof_required,
                           COALESCE(tp.participant_label, CONCAT('User #', p.submitted_by_user_id)) AS participant_label,
                           (SELECT r.decision FROM training_reviews r WHERE r.proof_submission_id = p.id ORDER BY COALESCE(r.reviewed_at, r.created_at) DESC, r.id DESC LIMIT 1) AS latest_decision,
                           (SELECT r.review_notes FROM training_reviews r WHERE r.proof_submission_id = p.id ORDER BY COALESCE(r.reviewed_at, r.created_at) DESC, r.id DESC LIMIT 1) AS latest_review_notes,
                           (SELECT COUNT(*) FROM training_reviews r WHERE r.proof_submission_id = p.id) AS review_count,
                           (SELECT COUNT(*) FROM training_action_receipts ar WHERE ar.proof_submission_id = p.id AND ar.receipt_status = 'active') AS receipt_count,
                           (SELECT COUNT(*) FROM training_reward_events re WHERE re.participant_id = p.participant_id AND re.campaign_id = p.campaign_id AND re.status <> 'cancelled') AS reward_event_count
                    FROM training_proof_submissions p
                    LEFT JOIN training_campaigns c ON c.id = p.campaign_id
                    LEFT JOIN training_campaign_tasks t ON t.id = p.task_id
                    LEFT JOIN training_participants tp ON tp.id = p.participant_id
                    ORDER BY CASE WHEN p.status IN ('submitted','in_review') THEN 0 ELSE 1 END, COALESCE(p.updated_at, p.submitted_at, p.created_at) DESC, p.id DESC
                    LIMIT " . $limit;
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_stage885_status_counts')) {
    function tl_stage885_status_counts(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $status = strtolower((string)($row['proof_status'] ?? 'unknown'));
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        ksort($counts);
        return $counts;
    }
}

if (!function_exists('tl_stage885_find_proof')) {
    function tl_stage885_find_proof(?string $proofRef = null): ?array
    {
        $proofRef = trim((string)$proofRef);
        $pdo = function_exists('tl_db') ? tl_db() : null;
        if (!$pdo || !tl_table_exists('training_proof_submissions')) return null;
        try {
            $base = "SELECT p.*, c.public_id AS campaign_public_id, c.slug AS campaign_slug, c.title AS campaign_title, c.target_action_count,
                            t.title AS task_title, t.position_no, t.proof_required,
                            COALESCE(tp.participant_label, CONCAT('User #', p.submitted_by_user_id)) AS participant_label
                     FROM training_proof_submissions p
                     LEFT JOIN training_campaigns c ON c.id = p.campaign_id
                     LEFT JOIN training_campaign_tasks t ON t.id = p.task_id
                     LEFT JOIN training_participants tp ON tp.id = p.participant_id";
            if ($proofRef !== '') {
                $stmt = $pdo->prepare($base . " WHERE p.id = ? OR p.public_id = ? ORDER BY p.id DESC LIMIT 1");
                $stmt->execute([ctype_digit($proofRef) ? (int)$proofRef : 0, $proofRef]);
            } else {
                $stmt = $pdo->query($base . " WHERE p.status IN ('submitted','in_review') ORDER BY p.submitted_at ASC, p.id ASC LIMIT 1");
            }
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('tl_stage885_handoff_preview_for_proof')) {
    function tl_stage885_handoff_preview_for_proof(?string $proofRef = null): array
    {
        $proof = tl_stage885_find_proof($proofRef);
        if (!$proof) {
            return [
                'ready' => false,
                'status' => 'no_proof',
                'detail' => 'No proof submission is available for handoff preview.',
                'would_issue_microgifter_reward' => false,
            ];
        }
        $pdo = tl_db();
        $reviews = $receipts = $events = [];
        try {
            if ($pdo && tl_table_exists('training_reviews')) {
                $stmt = $pdo->prepare('SELECT id, public_id, decision, review_notes, reviewer_user_id, reviewed_at, created_at FROM training_reviews WHERE proof_submission_id = ? ORDER BY COALESCE(reviewed_at, created_at) DESC, id DESC LIMIT 10');
                $stmt->execute([(int)$proof['id']]);
                $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
            if ($pdo && tl_table_exists('training_action_receipts')) {
                $stmt = $pdo->prepare('SELECT id, public_id, receipt_type, receipt_status, verification_hash, issued_at, created_at FROM training_action_receipts WHERE proof_submission_id = ? ORDER BY created_at DESC, id DESC LIMIT 10');
                $stmt->execute([(int)$proof['id']]);
                $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
            if ($pdo && tl_table_exists('training_reward_events')) {
                $stmt = $pdo->prepare('SELECT re.id, re.public_id, re.status, re.value_cents, re.currency, re.eligibility_reason, re.created_at, re.updated_at, rr.reward_label, rr.reward_type
                                       FROM training_reward_events re
                                       LEFT JOIN training_reward_rules rr ON rr.id = re.reward_rule_id
                                       WHERE re.participant_id = ? AND re.campaign_id = ? AND re.status <> ?
                                       ORDER BY COALESCE(re.updated_at, re.created_at) DESC, re.id DESC LIMIT 10');
                $stmt->execute([(int)$proof['participant_id'], (int)$proof['campaign_id'], 'cancelled']);
                $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (Throwable $e) {}
        $latestReview = $reviews[0] ?? [];
        $decision = strtolower((string)($latestReview['decision'] ?? ''));
        $proofStatus = strtolower((string)($proof['status'] ?? 'submitted'));
        $approved = $decision === 'approved' || $proofStatus === 'approved';
        $previewStatus = $approved ? (count($events) ? 'handoff_preview_ready' : 'approved_receipt_ready') : 'blocked_pending_review';
        return [
            'ready' => $approved,
            'status' => $previewStatus,
            'proof' => [
                'id' => (int)$proof['id'],
                'public_id' => (string)$proof['public_id'],
                'status' => (string)$proof['status'],
                'campaign_name' => (string)($proof['campaign_title'] ?? 'Training Campaign'),
                'task_title' => (string)($proof['task_title'] ?? 'Training task'),
                'participant_label' => (string)($proof['participant_label'] ?? 'Participant'),
            ],
            'latest_review' => $latestReview,
            'receipts' => $receipts,
            'reward_events' => $events,
            'handoff' => [
                'target_system' => 'Microgifter reward layer',
                'handoff_mode' => 'preview_only',
                'would_issue_microgifter_reward' => false,
                'would_create_claim' => false,
                'would_mutate_wallet' => false,
                'blocked_until' => 'production issuing/developer-key gate is intentionally opened in a later stage',
            ],
        ];
    }
}

if (!function_exists('tl_stage885_submit_review_decision')) {
    function tl_stage885_submit_review_decision(array $input): array
    {
        $decision = (string)($input['decision'] ?? '');
        if (!in_array($decision, ['approved','rejected','needs_more_info'], true)) {
            throw new RuntimeException('Invalid Stage 885 decision.');
        }
        $input['training_action'] = 'review_proof';
        $input['confirm_training_action'] = '1';
        $result = tl_review_proof($input);
        $proofRef = (string)($input['proof_id'] ?? $input['proof'] ?? $input['public_id'] ?? '');
        $preview = tl_stage885_handoff_preview_for_proof($proofRef);
        return [
            'label' => 'Stage 885 proof review decision recorded',
            'stage' => 'Stage 885 Proof Review + Award Handoff Preview',
            'decision_result' => $result,
            'handoff_preview' => $preview,
            'safe_boundaries' => [
                'training_lab_review_write' => true,
                'training_lab_receipt_or_eligibility_write' => ($decision === 'approved'),
                'no_new_sql' => true,
                'no_config_files_moved_or_overwritten' => true,
                'no_payment_processing' => true,
                'no_wallet_balance_mutation' => true,
                'no_production_claim_redeem_mutation' => true,
                'no_destructive_microgifter_sync' => true,
                'no_microgifter_reward_issuing' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage885_summary')) {
    function tl_stage885_summary(?string $proofRef = null): array
    {
        $queue = tl_stage885_fetch_queue(50);
        $pending = array_values(array_filter($queue, fn($row) => in_array(strtolower((string)($row['proof_status'] ?? '')), ['submitted','in_review'], true)));
        $selected = $proofRef ? tl_stage885_find_proof($proofRef) : null;
        $selectedRef = $proofRef ?: (string)($pending[0]['public_id'] ?? $queue[0]['public_id'] ?? '');
        $handoff = tl_stage885_handoff_preview_for_proof($selectedRef);
        $stage884 = function_exists('tl_stage884_real_read_adapter_summary') ? tl_stage884_real_read_adapter_summary(0) : [];
        $accepted = !empty($stage884['accepted']) && count($queue) > 0;
        return [
            'stage' => 'Stage 885 Proof Review + Award Handoff Preview',
            'built_from' => 'Stage 884 Real Microgifter Read Adapter Connection',
            'accepted' => $accepted,
            'score' => $accepted ? 100 : 80,
            'mode' => tl_db_ready() ? 'database' : 'demo-fallback',
            'queue_count' => count($queue),
            'pending_review_count' => count($pending),
            'status_counts' => tl_stage885_status_counts($queue),
            'selected_proof_ref' => $selectedRef,
            'queue' => array_slice($queue, 0, 20),
            'handoff_preview' => $handoff,
            'stage884_adapter' => [
                'accepted' => !empty($stage884['accepted']),
                'adapter_source' => (string)($stage884['adapter_source'] ?? ''),
                'campaign_count' => (int)($stage884['campaign_count'] ?? 0),
                'award_count' => (int)($stage884['award_count'] ?? 0),
            ],
            'safe_boundaries' => [
                'review_decisions_write_training_lab_only' => true,
                'award_handoff_preview_only' => true,
                'no_new_sql' => true,
                'no_config_files_moved_or_overwritten' => true,
                'no_payment_processing' => true,
                'no_wallet_balance_mutation' => true,
                'no_production_claim_redeem_mutation' => true,
                'no_destructive_microgifter_sync' => true,
                'no_microgifter_reward_issuing' => true,
            ],
            'next_recommended_step' => 'Approve or reject the submitted proof in Review Workbench, then verify the award handoff preview remains preview-only.',
        ];
    }
}

if (!function_exists('tl_stage885_render_workflow')) {
    function tl_stage885_render_workflow(?string $proofRef = null): void
    {
        $summary = tl_stage885_summary($proofRef);
        $queue = (array)($summary['queue'] ?? []);
        $handoff = (array)($summary['handoff_preview'] ?? []);
        echo '<section class="labs-page-title"><div><span class="labs-eyebrow">Stage 885</span><h1>Proof Review + Award Handoff Preview</h1><p class="labs-copy">Review submitted proof, record a Training Lab decision, and generate a preview-only Microgifter award handoff. Production issuing remains closed.</p></div><div class="labs-actions"><a class="labs-btn labs-btn-primary" href="' . tl_stage885_e(function_exists('labs_url') ? labs_url('/api/training/proof-review-workflow.php') : '/api/training/proof-review-workflow.php') . '">View JSON</a><a class="labs-btn" href="' . tl_stage885_e(function_exists('labs_url') ? labs_url('/admin/reward-bridge.php') : '/admin/reward-bridge.php') . '">Reward Bridge</a></div></section>';
        echo '<section class="labs-kpis"><div class="labs-kpi"><span class="labs-muted">Accepted</span><strong>' . (!empty($summary['accepted']) ? 'Yes' : 'Check') . '</strong><small>workflow gate</small></div><div class="labs-kpi"><span class="labs-muted">Queue</span><strong>' . (int)$summary['queue_count'] . '</strong><small>proof rows</small></div><div class="labs-kpi"><span class="labs-muted">Pending</span><strong>' . (int)$summary['pending_review_count'] . '</strong><small>submitted/in review</small></div><div class="labs-kpi"><span class="labs-muted">Handoff</span><strong>' . tl_stage885_e((string)($handoff['status'] ?? 'preview')) . '</strong><small>preview only</small></div></section>';
        echo '<section class="labs-card"><h2>Decision queue</h2><div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Participant</th><th>Campaign</th><th>Task</th><th>Status</th><th>Review</th><th>Action</th></tr></thead><tbody>';
        foreach ($queue as $row) {
            $publicId = (string)($row['public_id'] ?? '');
            echo '<tr><td>' . tl_stage885_e((string)($row['participant_label'] ?? 'Participant')) . '</td><td>' . tl_stage885_e((string)($row['campaign_title'] ?? 'Campaign')) . '</td><td>' . tl_stage885_e((string)($row['task_title'] ?? 'Task')) . '</td><td><span class="labs-pill">' . tl_stage885_e((string)($row['proof_status'] ?? 'submitted')) . '</span></td><td>' . tl_stage885_e((string)($row['latest_decision'] ?? 'pending')) . '</td><td><form action="' . tl_stage885_e(function_exists('labs_url') ? labs_url('/admin/action-result.php') : '/admin/action-result.php') . '" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="stage885_review_proof"><input type="hidden" name="proof_id" value="' . tl_stage885_e($publicId) . '"><label>Decision<select name="decision"><option value="approved">Approve</option><option value="needs_more_info">Needs more info</option><option value="rejected">Reject</option></select></label><label>Notes<textarea name="review_notes" rows="2">Stage 885 controlled proof review.</textarea></label><button class="labs-btn labs-btn-primary" type="submit">Submit</button></form></td></tr>';
        }
        if (!$queue) echo '<tr><td colspan="6">No proof rows found yet.</td></tr>';
        echo '</tbody></table></div></section>';
        echo '<section class="labs-card"><h2>Award handoff preview</h2><pre class="labs-stage25-code">' . tl_stage885_e(json_encode($handoff, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre></section>';
        echo '<section class="labs-safe-note">Stage 885 writes Training Lab review/receipt/eligibility records only. It does not issue Microgifter rewards, mutate wallets, process payments, redeem claims, or destructively sync.</section>';
    }
}
