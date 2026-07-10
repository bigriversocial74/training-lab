<?php
/**
 * Product-facing home view models for signed-in participants and operators.
 */
require_once __DIR__ . '/training-lab-app-service.php';
require_once __DIR__ . '/training-lab-product-shell.php';

if (!function_exists('tl_product_clean_campaign_ref')) {
    function tl_product_clean_campaign_ref(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($value)) ?: '';
    }
}

if (!function_exists('tl_product_participant_campaign_rows')) {
    function tl_product_participant_campaign_rows(int $userId): array
    {
        $pdo = function_exists('tl_db') ? tl_db() : null;
        if (!$pdo instanceof PDO || !tl_table_exists('training_participants') || !tl_table_exists('training_campaigns')) return [];

        try {
            $sql = "SELECT
                        tp.id AS participant_id,
                        tp.public_id AS participant_public_id,
                        tp.status AS participant_status,
                        tp.joined_at,
                        tp.updated_at AS participant_updated_at,
                        c.id AS campaign_id,
                        c.public_id AS campaign_public_id,
                        c.slug AS campaign_slug,
                        c.title AS campaign_title,
                        c.summary AS campaign_summary,
                        c.description AS campaign_description,
                        c.status AS campaign_status,
                        c.visibility AS campaign_visibility,
                        c.starts_at,
                        c.ends_at,
                        c.target_action_count,
                        COALESCE(s.completed_action_count, 0) AS completed_action_count,
                        COALESCE(s.current_streak_days, 0) AS current_streak_days,
                        COALESCE(s.longest_streak_days, 0) AS longest_streak_days,
                        (SELECT COUNT(*) FROM training_campaign_tasks t WHERE t.campaign_id=c.id AND t.status='active') AS task_count,
                        (SELECT COUNT(DISTINCT p.task_id) FROM training_proof_submissions p WHERE p.participant_id=tp.id AND p.status='approved') AS approved_task_count,
                        (SELECT COUNT(*) FROM training_proof_submissions p WHERE p.participant_id=tp.id AND p.status IN ('submitted','in_review')) AS pending_proof_count,
                        (SELECT COUNT(*) FROM training_proof_submissions p WHERE p.participant_id=tp.id AND (p.status='rejected' OR EXISTS (SELECT 1 FROM training_reviews r WHERE r.proof_submission_id=p.id AND r.decision='needs_more_info'))) AS revision_count,
                        (SELECT COUNT(*) FROM training_reward_events re WHERE re.participant_id=tp.id AND re.status<>'cancelled') AS reward_count,
                        (SELECT COUNT(*) FROM training_reward_events re WHERE re.participant_id=tp.id AND re.status='eligible') AS claimable_reward_count
                    FROM training_participants tp
                    INNER JOIN training_campaigns c ON c.id=tp.campaign_id
                    LEFT JOIN training_streaks s ON s.participant_id=tp.id
                    WHERE tp.user_id=? AND tp.status<>'removed'
                    ORDER BY
                        CASE tp.status WHEN 'active' THEN 0 WHEN 'invited' THEN 1 WHEN 'paused' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END,
                        tp.updated_at DESC,
                        tp.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_product_task_status')) {
    function tl_product_task_status(?array $proof): array
    {
        if (!$proof) return ['key' => 'not_started', 'label' => 'Not started', 'tone' => 'neutral'];
        $decision = (string)($proof['latest_decision'] ?? '');
        $status = (string)($proof['status'] ?? 'submitted');
        if ($decision === 'needs_more_info') return ['key' => 'needs_attention', 'label' => 'Needs an update', 'tone' => 'warning'];
        if ($decision === 'rejected' || $status === 'rejected') return ['key' => 'needs_attention', 'label' => 'Needs an update', 'tone' => 'warning'];
        if ($decision === 'approved' || $status === 'approved') return ['key' => 'complete', 'label' => 'Complete', 'tone' => 'success'];
        if (in_array($status, ['submitted', 'in_review'], true)) return ['key' => 'in_review', 'label' => 'In review', 'tone' => 'pending'];
        return ['key' => 'started', 'label' => 'In progress', 'tone' => 'info'];
    }
}

if (!function_exists('tl_product_recent_activity')) {
    function tl_product_recent_activity(array $context, int $limit = 6): array
    {
        $items = [];
        foreach (($context['proofs'] ?? []) as $proof) {
            $status = tl_product_task_status($proof);
            $items[] = [
                'type' => 'proof',
                'label' => (string)($proof['task_title'] ?? 'Training proof'),
                'detail' => $status['label'],
                'tone' => $status['tone'],
                'at' => (string)($proof['updated_at'] ?? $proof['submitted_at'] ?? $proof['created_at'] ?? ''),
            ];
        }
        foreach (($context['receipts'] ?? []) as $receipt) {
            $items[] = [
                'type' => 'receipt',
                'label' => (string)($receipt['receipt_type'] ?? '') === 'sequence_completed' ? 'Training completed' : 'Task verified',
                'detail' => 'Completion recorded',
                'tone' => 'success',
                'at' => (string)($receipt['issued_at'] ?? $receipt['created_at'] ?? ''),
            ];
        }
        foreach (($context['rewards'] ?? []) as $reward) {
            $items[] = [
                'type' => 'reward',
                'label' => (string)($reward['reward_label'] ?? 'Training reward'),
                'detail' => ucwords(str_replace('_', ' ', (string)($reward['status'] ?? 'eligible'))),
                'tone' => in_array((string)($reward['status'] ?? ''), ['issued', 'linked'], true) ? 'success' : 'info',
                'at' => (string)($reward['updated_at'] ?? $reward['created_at'] ?? ''),
            ];
        }
        usort($items, static function (array $a, array $b): int {
            return (strtotime((string)$b['at']) ?: 0) <=> (strtotime((string)$a['at']) ?: 0);
        });
        return array_slice($items, 0, max(1, min(20, $limit)));
    }
}

if (!function_exists('tl_product_participant_home')) {
    function tl_product_participant_home(array $user, string $campaignRef = ''): array
    {
        $userId = function_exists('tl_security_numeric_user_id') ? tl_security_numeric_user_id($user) : max(1, (int)($user['numeric_user_id'] ?? 1));
        $campaigns = tl_product_participant_campaign_rows($userId);
        $requested = tl_product_clean_campaign_ref($campaignRef);
        $selected = null;
        foreach ($campaigns as $row) {
            $references = [(string)$row['campaign_id'], (string)$row['campaign_public_id'], (string)$row['campaign_slug']];
            if ($requested !== '' && in_array($requested, $references, true)) {
                $selected = $row;
                break;
            }
        }
        if (!$selected) $selected = $campaigns[0] ?? null;

        $context = [];
        $tasks = [];
        $nextTask = null;
        $progress = 0;
        if ($selected) {
            $ref = (string)($selected['campaign_slug'] ?: $selected['campaign_public_id']);
            $context = tl_app_participant_context($ref, $userId);
            $taskRows = $context['tasks'] ?? [];
            foreach ($taskRows as $task) {
                $taskId = (string)($task['db_id'] ?? $task['id'] ?? '');
                $proof = ($context['proofs_by_task'][$taskId] ?? [])[0] ?? null;
                $status = tl_product_task_status(is_array($proof) ? $proof : null);
                $item = [
                    'id' => $taskId,
                    'title' => (string)($task['title'] ?? 'Training task'),
                    'instructions' => (string)($task['instructions'] ?? ''),
                    'proof_required' => (string)($task['proof'] ?? '') === 'Required' || !empty($task['proof_required']),
                    'status' => $status,
                    'href' => '/app/task-runner.php?campaign=' . rawurlencode($ref) . '&task=' . rawurlencode($taskId),
                ];
                $tasks[] = $item;
                if (!$nextTask && !in_array($status['key'], ['complete', 'in_review'], true)) $nextTask = $item;
            }
            $total = max(1, count($tasks));
            $complete = count(array_filter($tasks, static fn(array $task): bool => $task['status']['key'] === 'complete'));
            $progress = min(100, (int)round(($complete / $total) * 100));
        }

        $totals = [
            'campaigns' => count($campaigns),
            'completed_tasks' => array_sum(array_map(static fn(array $row): int => (int)$row['approved_task_count'], $campaigns)),
            'pending_reviews' => array_sum(array_map(static fn(array $row): int => (int)$row['pending_proof_count'], $campaigns)),
            'revisions' => array_sum(array_map(static fn(array $row): int => (int)$row['revision_count'], $campaigns)),
            'rewards' => array_sum(array_map(static fn(array $row): int => (int)$row['reward_count'], $campaigns)),
            'claimable' => array_sum(array_map(static fn(array $row): int => (int)$row['claimable_reward_count'], $campaigns)),
        ];

        if (!$selected) {
            $nextAction = ['label' => 'Find a campaign', 'detail' => 'Join a training campaign to begin your first task.', 'href' => '/app/campaigns.php'];
        } elseif ($nextTask) {
            $nextAction = ['label' => 'Continue training', 'detail' => $nextTask['title'], 'href' => $nextTask['href']];
        } elseif ($totals['pending_reviews'] > 0) {
            $nextAction = ['label' => 'View your progress', 'detail' => 'Your latest proof is waiting for review.', 'href' => '/app/progress-map.php?campaign=' . rawurlencode((string)$selected['campaign_slug'])];
        } elseif ($totals['claimable'] > 0) {
            $nextAction = ['label' => 'Claim your reward', 'detail' => 'A reward is ready for you.', 'href' => '/app/rewards.php'];
        } else {
            $nextAction = ['label' => 'Review your progress', 'detail' => 'See completed tasks, receipts, and rewards.', 'href' => '/app/progress-map.php?campaign=' . rawurlencode((string)$selected['campaign_slug'])];
        }

        $firstName = trim((string)($user['name'] ?? ''));
        if ($firstName !== '') $firstName = preg_split('/\s+/', $firstName)[0] ?? $firstName;

        return [
            'user' => $user,
            'user_id' => $userId,
            'first_name' => $firstName !== '' ? $firstName : 'there',
            'role' => tl_product_role($user),
            'campaigns' => $campaigns,
            'selected' => $selected,
            'context' => $context,
            'tasks' => $tasks,
            'next_task' => $nextTask,
            'next_action' => $nextAction,
            'progress_percent' => $progress,
            'totals' => $totals,
            'recent_activity' => tl_product_recent_activity($context),
        ];
    }
}

if (!function_exists('tl_product_admin_home')) {
    function tl_product_admin_home(array $user): array
    {
        $role = tl_product_role($user);
        $summary = tl_app_flow_summary();
        $counts = $summary['counts'] ?? [];
        $pending = tl_app_pending_proofs(8);
        $reviews = tl_app_recent_reviews(8);
        $campaigns = array_slice(tl_app_campaign_options(), 0, 6);
        $rewards = function_exists('tl_mg_stage160_bridge_summary') ? tl_mg_stage160_bridge_summary() : [];

        return [
            'user' => $user,
            'role' => $role,
            'role_label' => tl_product_role_label($role),
            'counts' => [
                'campaigns' => (int)($counts['campaigns'] ?? 0),
                'participants' => (int)($counts['participants'] ?? 0),
                'pending_proofs' => (int)($counts['pending_proofs'] ?? count($pending)),
                'rewards' => (int)($counts['reward_events'] ?? 0),
                'reviews' => (int)($counts['reviews'] ?? 0),
                'claimable' => (int)($rewards['counts']['claimable'] ?? 0),
                'retryable' => (int)($rewards['counts']['retryable'] ?? 0),
            ],
            'campaigns' => $campaigns,
            'pending_proofs' => $pending,
            'recent_reviews' => $reviews,
            'can_manage' => tl_product_role_allows($role, 'manager'),
        ];
    }
}
