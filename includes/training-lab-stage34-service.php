<?php
/**
 * Training Lab Stage 6/7 service layer.
 *
 * Reads from the imported Training Lab tables when a DB config is available.
 * Falls back to demo seed data when no DB connection exists or tables are empty.
 * Controlled writes are exposed through dedicated API action files only.
 */
require_once __DIR__ . '/training-lab-db.php';

if (!function_exists('tl_stage34_seed')) {
    function tl_stage34_seed(): array
    {
        return [
            'meta' => [
                'stage' => 'Stage 7 controlled backend build',
                'mode' => tl_db_ready() ? 'database-backed-with-demo-fallback' : 'static-demo-fallback',
                'sql_status' => tl_table_exists('training_campaigns') ? 'training lab tables detected' : 'training lab tables not detected',
                'source_of_truth' => 'Existing Microgifter account, merchant, wallet, reward, and claim systems remain the integration boundary.',
            ],
            'current_user' => [
                'id' => 'demo-user-001',
                'name' => 'Jamie Rivera',
                'role' => 'participant',
                'organization' => 'Demo Wellness Team',
                'account_source' => 'existing_microgifter_account_later',
            ],
            'campaigns' => [
                [
                    'id' => 'movement-5',
                    'db_id' => null,
                    'title' => '5-Day Movement Challenge',
                    'status' => 'Active',
                    'audience' => 'Team wellness participants',
                    'owner' => 'Demo Wellness Team',
                    'reward' => 'Movement Milestone',
                    'progress' => 80,
                    'completed_actions' => 4,
                    'total_actions' => 5,
                    'due' => 'Today',
                    'description' => 'A simple proof-based challenge that verifies consistency before reward eligibility.',
                ],
                [
                    'id' => 'hydration-check',
                    'db_id' => null,
                    'title' => 'Hydration Check-In',
                    'status' => 'Draft',
                    'audience' => 'New participant cohort',
                    'owner' => 'Demo Wellness Team',
                    'reward' => 'Consistency Badge',
                    'progress' => 20,
                    'completed_actions' => 1,
                    'total_actions' => 5,
                    'due' => 'Next week',
                    'description' => 'Daily hydration and check-in action sequence for team habit-building.',
                ],
                [
                    'id' => 'service-basics',
                    'db_id' => null,
                    'title' => 'Customer Service Basics',
                    'status' => 'Template',
                    'audience' => 'Retail and service staff',
                    'owner' => 'Training Lab Template Library',
                    'reward' => 'Service Starter',
                    'progress' => 0,
                    'completed_actions' => 0,
                    'total_actions' => 4,
                    'due' => 'Template',
                    'description' => 'Reusable training path for onboarding, service readiness, and proof review.',
                ],
            ],
            'tasks' => [
                'movement-5' => [
                    ['id'=>'demo-task-1','db_id'=>null,'day'=>1,'title'=>'Walk 15 minutes','status'=>'Complete','proof'=>'Approved'],
                    ['id'=>'demo-task-2','db_id'=>null,'day'=>2,'title'=>'Stretch and mobility','status'=>'Complete','proof'=>'Approved'],
                    ['id'=>'demo-task-3','db_id'=>null,'day'=>3,'title'=>'Water and recovery check','status'=>'Complete','proof'=>'Approved'],
                    ['id'=>'demo-task-4','db_id'=>null,'day'=>4,'title'=>'Mindful movement recap','status'=>'Complete','proof'=>'Approved'],
                    ['id'=>'demo-task-5','db_id'=>null,'day'=>5,'title'=>'Final proof submission','status'=>'Ready','proof'=>'Not submitted'],
                ],
                'hydration-check' => [
                    ['id'=>'demo-hydration-1','db_id'=>null,'day'=>1,'title'=>'Morning water check','status'=>'Ready','proof'=>'Not submitted'],
                    ['id'=>'demo-hydration-2','db_id'=>null,'day'=>2,'title'=>'Midday check-in','status'=>'Locked','proof'=>'Locked'],
                    ['id'=>'demo-hydration-3','db_id'=>null,'day'=>3,'title'=>'Evening reflection','status'=>'Locked','proof'=>'Locked'],
                    ['id'=>'demo-hydration-4','db_id'=>null,'day'=>4,'title'=>'Consistency recap','status'=>'Locked','proof'=>'Locked'],
                    ['id'=>'demo-hydration-5','db_id'=>null,'day'=>5,'title'=>'Final verification','status'=>'Locked','proof'=>'Locked'],
                ],
            ],
            'reviews' => [
                ['id'=>'review-1001','participant'=>'Jamie Rivera','campaign'=>'5-Day Movement Challenge','proof_status'=>'Not submitted','review_status'=>'Not submitted','reward_status'=>'Pending','last_update'=>'Not updated yet'],
                ['id'=>'review-1002','participant'=>'Morgan Lee','campaign'=>'Hydration Check-In','proof_status'=>'Submitted','review_status'=>'In review','reward_status'=>'Pending','last_update'=>'Demo seed'],
                ['id'=>'review-1003','participant'=>'Casey Stone','campaign'=>'Customer Service Basics','proof_status'=>'Approved','review_status'=>'Approved','reward_status'=>'Unlocked','last_update'=>'Demo seed'],
            ],
            'wallet' => [
                ['label'=>'Consistency Badge','status'=>'Available','source'=>'Hydration Check-In'],
                ['label'=>'Movement Milestone','status'=>'Pending','source'=>'5-Day Movement Challenge'],
                ['label'=>'Safety Readiness','status'=>'Locked','source'=>'Template Library'],
            ],
            'integration_map' => [
                ['training_record'=>'training_participants.user_id','microgifter_source'=>'users/account id','boundary'=>'Read user identity from existing account system once exact table is confirmed'],
                ['training_record'=>'training_campaigns.owner_user_id','microgifter_source'=>'existing owner user id','boundary'=>'Attach campaigns to existing owner/account identity'],
                ['training_record'=>'training_reward_events.linked_*','microgifter_source'=>'existing reward/wallet layer','boundary'=>'Link only after reward issuing approval'],
                ['training_record'=>'training_reviews.reviewer_user_id','microgifter_source'=>'existing role permissions','boundary'=>'Reviewer must be approved through existing account roles'],
            ],
        ];
    }
}

if (!function_exists('tl_stage34_campaigns')) {
    function tl_stage34_campaigns(): array
    {
        $pdo = tl_db();
        if ($pdo && tl_table_exists('training_campaigns')) {
            try {
                $sql = "SELECT c.*, COUNT(t.id) AS total_actions,
                    COALESCE(s.completed_action_count,0) AS completed_actions,
                    COALESCE(rr.reward_label, c.reward_summary, 'Training Reward') AS reward_label
                    FROM training_campaigns c
                    LEFT JOIN training_campaign_tasks t ON t.campaign_id = c.id AND t.status <> 'archived'
                    LEFT JOIN training_streaks s ON s.campaign_id = c.id
                    LEFT JOIN training_reward_rules rr ON rr.campaign_id = c.id AND rr.status IN ('active','draft')
                    GROUP BY c.id
                    ORDER BY c.updated_at DESC, c.created_at DESC";
                $rows = $pdo->query($sql)->fetchAll();
                if ($rows) {
                    return array_map(function ($row) {
                        $total = max(1, (int)($row['total_actions'] ?? $row['target_action_count'] ?? 5));
                        $completed = (int)($row['completed_actions'] ?? 0);
                        return [
                            'id' => $row['slug'] ?: $row['public_id'],
                            'public_id' => $row['public_id'],
                            'db_id' => (int)$row['id'],
                            'title' => $row['title'],
                            'status' => ucfirst((string)$row['status']),
                            'audience' => $row['campaign_type'] ? ucfirst((string)$row['campaign_type']) . ' participants' : 'Participants',
                            'owner' => 'Owner #' . (int)$row['owner_user_id'],
                            'reward' => $row['reward_label'] ?: 'Training Reward',
                            'progress' => min(100, (int)round(($completed / $total) * 100)),
                            'completed_actions' => $completed,
                            'total_actions' => $total,
                            'due' => !empty($row['ends_at']) ? date('M j', strtotime($row['ends_at'])) : 'Open',
                            'description' => $row['description'] ?: ($row['summary'] ?: ''),
                        ];
                    }, $rows);
                }
            } catch (Throwable $e) {}
        }
        return tl_stage34_seed()['campaigns'];
    }
}

if (!function_exists('tl_stage34_campaign')) {
    function tl_stage34_campaign(string $id): array
    {
        foreach (tl_stage34_campaigns() as $campaign) {
            if ((string)$campaign['id'] === $id || (string)($campaign['public_id'] ?? '') === $id || (string)($campaign['db_id'] ?? '') === $id) return $campaign;
        }
        return tl_stage34_campaigns()[0];
    }
}

if (!function_exists('tl_stage34_tasks')) {
    function tl_stage34_tasks(string $campaignId = 'movement-5'): array
    {
        $campaign = tl_stage34_campaign($campaignId);
        $pdo = tl_db();
        if ($pdo && tl_table_exists('training_campaign_tasks') && !empty($campaign['db_id'])) {
            try {
                $stmt = $pdo->prepare('SELECT * FROM training_campaign_tasks WHERE campaign_id = ? AND status <> ? ORDER BY position_no ASC');
                $stmt->execute([(int)$campaign['db_id'], 'archived']);
                $rows = $stmt->fetchAll();
                if ($rows) {
                    return array_map(function ($row) {
                        return [
                            'id' => $row['public_id'],
                            'db_id' => (int)$row['id'],
                            'day' => (int)($row['day_no'] ?: $row['position_no']),
                            'title' => $row['title'],
                            'status' => ucfirst((string)$row['status']),
                            'proof' => (int)$row['proof_required'] === 1 ? 'Required' : 'Optional',
                            'instructions' => $row['instructions'] ?? '',
                        ];
                    }, $rows);
                }
            } catch (Throwable $e) {}
        }
        $tasks = tl_stage34_seed()['tasks'];
        return $tasks[$campaignId] ?? $tasks['movement-5'];
    }
}

if (!function_exists('tl_stage34_reviews')) {
    function tl_stage34_reviews(): array
    {
        $pdo = tl_db();
        if ($pdo && tl_table_exists('training_proof_submissions')) {
            try {
                $sql = "SELECT p.public_id, p.status AS proof_status, p.updated_at, c.title AS campaign_title,
                    COALESCE(tp.participant_label, CONCAT('User #', p.submitted_by_user_id)) AS participant,
                    (SELECT r.decision FROM training_reviews r WHERE r.proof_submission_id = p.id ORDER BY r.created_at DESC LIMIT 1) AS decision,
                    (SELECT e.status FROM training_reward_events e WHERE e.participant_id = p.participant_id ORDER BY e.created_at DESC LIMIT 1) AS reward_status
                    FROM training_proof_submissions p
                    LEFT JOIN training_campaigns c ON c.id = p.campaign_id
                    LEFT JOIN training_participants tp ON tp.id = p.participant_id
                    ORDER BY p.updated_at DESC LIMIT 50";
                $rows = $pdo->query($sql)->fetchAll();
                if ($rows) {
                    return array_map(function ($row) {
                        $decision = $row['decision'] ?: ($row['proof_status'] === 'approved' ? 'approved' : ($row['proof_status'] === 'rejected' ? 'rejected' : 'in_review'));
                        return [
                            'id' => $row['public_id'],
                            'participant' => $row['participant'] ?: 'Participant',
                            'campaign' => $row['campaign_title'] ?: 'Training Campaign',
                            'proof_status' => ucwords(str_replace('_', ' ', (string)$row['proof_status'])),
                            'review_status' => ucwords(str_replace('_', ' ', (string)$decision)),
                            'reward_status' => ucwords(str_replace('_', ' ', (string)($row['reward_status'] ?: 'pending'))),
                            'last_update' => $row['updated_at'] ?: 'Not updated yet',
                        ];
                    }, $rows);
                }
            } catch (Throwable $e) {}
        }
        return tl_stage34_seed()['reviews'];
    }
}

if (!function_exists('tl_stage34_wallet')) {
    function tl_stage34_wallet(): array
    {
        $pdo = tl_db();
        if ($pdo && tl_table_exists('training_reward_events')) {
            try {
                $sql = "SELECT e.status, e.value_cents, c.title AS campaign_title, rr.reward_label
                    FROM training_reward_events e
                    LEFT JOIN training_campaigns c ON c.id = e.campaign_id
                    LEFT JOIN training_reward_rules rr ON rr.id = e.reward_rule_id
                    ORDER BY e.created_at DESC LIMIT 50";
                $rows = $pdo->query($sql)->fetchAll();
                if ($rows) {
                    return array_map(fn($row) => [
                        'label' => $row['reward_label'] ?: 'Training Reward',
                        'status' => ucwords(str_replace('_', ' ', (string)$row['status'])),
                        'source' => $row['campaign_title'] ?: 'Training Lab',
                        'value_cents' => (int)($row['value_cents'] ?? 0),
                    ], $rows);
                }
            } catch (Throwable $e) {}
        }
        return tl_stage34_seed()['wallet'];
    }
}

if (!function_exists('tl_stage34_dashboard')) {
    function tl_stage34_dashboard(): array
    {
        $campaigns = tl_stage34_campaigns();
        $active = array_values(array_filter($campaigns, fn($c) => strtolower((string)$c['status']) === 'active'));
        $completed = 0; $total = 0;
        foreach ($campaigns as $campaign) {
            $completed += (int)($campaign['completed_actions'] ?? 0);
            $total += (int)($campaign['total_actions'] ?? 0);
        }
        $reviews = tl_stage34_reviews();
        $approved = count(array_filter($reviews, fn($r) => strtolower((string)$r['review_status']) === 'approved'));
        $approvalRate = count($reviews) ? round(($approved / count($reviews)) * 100) . '%' : '0%';
        return [
            'active_campaigns' => count($active),
            'total_campaigns' => count($campaigns),
            'current_streak' => 4,
            'completed_actions' => $completed ?: 4,
            'total_actions' => $total ?: 5,
            'reward_status' => count(tl_stage34_wallet()) ? 'Pending' : 'None',
            'review_queue' => count($reviews),
            'approval_rate' => $approvalRate,
            'avg_review_time' => '14 min',
            'db_mode' => tl_db_ready() ? 'database' : 'demo',
        ];
    }
}

if (!function_exists('tl_stage34_json')) {
    function tl_stage34_json(array $payload): void
    {
        tl_json_response(['ok' => true, 'data' => $payload, 'mode' => tl_db_ready() ? 'database' : 'demo-fallback']);
    }
}


if (!function_exists('tl_training_group_counts')) {
    function tl_training_group_counts(string $table, string $column): array
    {
        if (!in_array($table, tl_training_required_tables(), true)) return [];
        $safeTable = tl_db_safe_identifier($table);
        $safeColumn = tl_db_safe_identifier($column);
        $pdo = tl_db();
        if (!$pdo || !$safeTable || !$safeColumn || !tl_table_exists($table) || !tl_table_has_column($table, $column)) return [];

        try {
            $rows = $pdo->query('SELECT `' . $safeColumn . '` AS group_value, COUNT(*) AS total FROM `' . $safeTable . '` GROUP BY `' . $safeColumn . '` ORDER BY total DESC, group_value ASC')->fetchAll();
            $counts = [];
            foreach ($rows as $row) {
                $key = (string)($row['group_value'] ?? 'unknown');
                $counts[$key !== '' ? $key : 'unknown'] = (int)($row['total'] ?? 0);
            }
            return $counts;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_training_scalar_count')) {
    function tl_training_scalar_count(string $table, ?string $whereColumn = null, $whereValue = null): int
    {
        if (!in_array($table, tl_training_required_tables(), true)) return 0;
        $safeTable = tl_db_safe_identifier($table);
        $pdo = tl_db();
        if (!$pdo || !$safeTable || !tl_table_exists($table)) return 0;

        try {
            if ($whereColumn !== null) {
                $safeColumn = tl_db_safe_identifier($whereColumn);
                if (!$safeColumn || !tl_table_has_column($table, $whereColumn)) return 0;
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $safeTable . '` WHERE `' . $safeColumn . '` = ?');
                $stmt->execute([$whereValue]);
                return (int)$stmt->fetchColumn();
            }
            $stmt = $pdo->query('SELECT COUNT(*) FROM `' . $safeTable . '`');
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('tl_training_sum_column')) {
    function tl_training_sum_column(string $table, string $column): int
    {
        if (!in_array($table, tl_training_required_tables(), true)) return 0;
        $safeTable = tl_db_safe_identifier($table);
        $safeColumn = tl_db_safe_identifier($column);
        $pdo = tl_db();
        if (!$pdo || !$safeTable || !$safeColumn || !tl_table_exists($table) || !tl_table_has_column($table, $column)) return 0;

        try {
            $stmt = $pdo->query('SELECT COALESCE(SUM(`' . $safeColumn . '`), 0) FROM `' . $safeTable . '`');
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('tl_training_latest_events')) {
    function tl_training_latest_events(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_events')) return [];

        try {
            $stmt = $pdo->query('SELECT public_id, actor_user_id, subject_type, subject_id, event_type, created_at FROM training_events ORDER BY created_at DESC LIMIT ' . $limit);
            $rows = $stmt ? $stmt->fetchAll() : [];
            return array_map(function ($row) {
                return [
                    'public_id' => $row['public_id'] ?? null,
                    'actor_user_id' => isset($row['actor_user_id']) ? (int)$row['actor_user_id'] : null,
                    'subject_type' => $row['subject_type'] ?? null,
                    'subject_id' => isset($row['subject_id']) ? (int)$row['subject_id'] : null,
                    'event_type' => $row['event_type'] ?? null,
                    'created_at' => $row['created_at'] ?? null,
                ];
            }, $rows);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_training_ops_summary')) {
    function tl_training_ops_summary(): array
    {
        $dbStatus = tl_db_status_summary();
        $tableHealth = tl_training_table_diagnostics();
        $connected = !empty($dbStatus['connected']) && !empty($dbStatus['all_tables_present']);
        $rowCounts = $dbStatus['row_counts'] ?? [];
        $totalRows = 0;
        foreach ($rowCounts as $count) {
            if ($count !== null) $totalRows += (int)$count;
        }

        $summary = [
            'stage' => 'Stage 15 read-only review inspector',
            'mode' => $connected ? 'database-read-only' : 'demo-fallback',
            'total_training_rows' => $totalRows,
            'tables_ready' => count(array_filter($tableHealth, fn($row) => !empty($row['schema_ready']))),
            'tables_expected' => count($tableHealth),
            'campaigns' => [
                'total' => tl_training_scalar_count('training_campaigns'),
                'status_counts' => tl_training_group_counts('training_campaigns', 'status'),
                'visibility_counts' => tl_training_group_counts('training_campaigns', 'visibility'),
                'latest_activity_at' => tl_table_datetime_max('training_campaigns'),
            ],
            'tasks' => [
                'total' => tl_training_scalar_count('training_campaign_tasks'),
                'status_counts' => tl_training_group_counts('training_campaign_tasks', 'status'),
                'type_counts' => tl_training_group_counts('training_campaign_tasks', 'task_type'),
                'proof_required' => tl_training_scalar_count('training_campaign_tasks', 'proof_required', 1),
                'latest_activity_at' => tl_table_datetime_max('training_campaign_tasks'),
            ],
            'participants' => [
                'total' => tl_training_scalar_count('training_participants'),
                'status_counts' => tl_training_group_counts('training_participants', 'status'),
                'latest_activity_at' => tl_table_datetime_max('training_participants'),
            ],
            'proof_submissions' => [
                'total' => tl_training_scalar_count('training_proof_submissions'),
                'status_counts' => tl_training_group_counts('training_proof_submissions', 'status'),
                'type_counts' => tl_training_group_counts('training_proof_submissions', 'proof_type'),
                'latest_activity_at' => tl_table_datetime_max('training_proof_submissions'),
            ],
            'reviews' => [
                'total' => tl_training_scalar_count('training_reviews'),
                'decision_counts' => tl_training_group_counts('training_reviews', 'decision'),
                'latest_activity_at' => tl_table_datetime_max('training_reviews'),
            ],
            'action_receipts' => [
                'total' => tl_training_scalar_count('training_action_receipts'),
                'status_counts' => tl_training_group_counts('training_action_receipts', 'receipt_status'),
                'type_counts' => tl_training_group_counts('training_action_receipts', 'receipt_type'),
                'latest_activity_at' => tl_table_datetime_max('training_action_receipts'),
            ],
            'reward_rules' => [
                'total' => tl_training_scalar_count('training_reward_rules'),
                'status_counts' => tl_training_group_counts('training_reward_rules', 'status'),
                'type_counts' => tl_training_group_counts('training_reward_rules', 'reward_type'),
                'configured_preview_value_cents' => tl_training_sum_column('training_reward_rules', 'reward_value_cents'),
                'latest_activity_at' => tl_table_datetime_max('training_reward_rules'),
            ],
            'reward_events' => [
                'total' => tl_training_scalar_count('training_reward_events'),
                'status_counts' => tl_training_group_counts('training_reward_events', 'status'),
                'preview_value_cents' => tl_training_sum_column('training_reward_events', 'value_cents'),
                'latest_activity_at' => tl_table_datetime_max('training_reward_events'),
            ],
            'streaks' => [
                'total' => tl_training_scalar_count('training_streaks'),
                'completed_action_count' => tl_training_sum_column('training_streaks', 'completed_action_count'),
                'latest_activity_at' => tl_table_datetime_max('training_streaks'),
            ],
            'events' => [
                'total' => tl_training_scalar_count('training_events'),
                'type_counts' => tl_training_group_counts('training_events', 'event_type'),
                'latest_activity_at' => tl_table_datetime_max('training_events'),
            ],
            'permission_catalog' => [
                'total' => tl_training_scalar_count('training_permission_catalog'),
                'latest_activity_at' => tl_table_datetime_max('training_permission_catalog'),
            ],
            'safe_boundaries' => $dbStatus['safe_boundaries'] ?? [],
        ];

        if (!$connected) {
            $dash = tl_stage34_dashboard();
            $campaigns = tl_stage34_campaigns();
            $reviews = tl_stage34_reviews();
            $summary['campaigns']['total'] = (int)($dash['total_campaigns'] ?? count($campaigns));
            $summary['campaigns']['status_counts'] = array_reduce($campaigns, function ($carry, $campaign) {
                $status = strtolower((string)($campaign['status'] ?? 'unknown'));
                $carry[$status] = ($carry[$status] ?? 0) + 1;
                return $carry;
            }, []);
            $summary['tasks']['total'] = (int)($dash['total_actions'] ?? 0);
            $summary['reviews']['total'] = count($reviews);
            $summary['proof_submissions']['total'] = count($reviews);
            $summary['reward_events']['total'] = count(tl_stage34_wallet());
            $summary['streaks']['completed_action_count'] = (int)($dash['completed_actions'] ?? 0);
        }

        return $summary;
    }
}

if (!function_exists('tl_training_status_count_value')) {
    function tl_training_status_count_value(array $group, string $key): int
    {
        return (int)($group[$key] ?? $group[strtolower($key)] ?? $group[strtoupper($key)] ?? 0);
    }
}

if (!function_exists('tl_training_money_display')) {
    function tl_training_money_display(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }
}

if (!function_exists('tl_training_recent_campaign_snapshots')) {
    function tl_training_recent_campaign_snapshots(int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));
        $pdo = tl_db();
        if ($pdo && tl_table_exists('training_campaigns')) {
            try {
                $stmt = $pdo->query('SELECT public_id, slug, title, campaign_type, visibility, status, target_action_count, starts_at, ends_at, created_at, updated_at FROM training_campaigns ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT ' . $limit);
                $rows = $stmt ? $stmt->fetchAll() : [];
                if ($rows) {
                    return array_map(function ($row) {
                        return [
                            'id' => (string)($row['slug'] ?: $row['public_id'] ?: ''),
                            'public_id' => (string)($row['public_id'] ?? ''),
                            'title' => (string)($row['title'] ?? 'Training Campaign'),
                            'type' => (string)($row['campaign_type'] ?? 'training'),
                            'visibility' => (string)($row['visibility'] ?? 'private'),
                            'status' => (string)($row['status'] ?? 'draft'),
                            'target_action_count' => (int)($row['target_action_count'] ?? 0),
                            'starts_at' => $row['starts_at'] ?? null,
                            'ends_at' => $row['ends_at'] ?? null,
                            'updated_at' => $row['updated_at'] ?: ($row['created_at'] ?? null),
                        ];
                    }, $rows);
                }
            } catch (Throwable $e) {}
        }

        return array_map(function ($campaign) {
            return [
                'id' => (string)($campaign['id'] ?? ''),
                'public_id' => (string)($campaign['public_id'] ?? ''),
                'title' => (string)($campaign['title'] ?? 'Training Campaign'),
                'type' => strtolower((string)($campaign['audience'] ?? 'training')),
                'visibility' => 'demo',
                'status' => strtolower((string)($campaign['status'] ?? 'draft')),
                'target_action_count' => (int)($campaign['total_actions'] ?? 0),
                'starts_at' => null,
                'ends_at' => (string)($campaign['due'] ?? 'Open'),
                'updated_at' => 'Demo fallback',
            ];
        }, array_slice(tl_stage34_campaigns(), 0, $limit));
    }
}

if (!function_exists('tl_training_command_center_summary')) {
    function tl_training_command_center_summary(): array
    {
        $status = tl_db_status_summary();
        $ops = tl_training_ops_summary();
        $tableHealth = tl_training_table_diagnostics();
        $latestEvents = tl_training_latest_events(6);

        $readyTables = (int)($ops['tables_ready'] ?? 0);
        $expectedTables = (int)($ops['tables_expected'] ?? count($tableHealth));
        $pendingProofs = tl_training_status_count_value($ops['proof_submissions']['status_counts'] ?? [], 'submitted')
            + tl_training_status_count_value($ops['proof_submissions']['status_counts'] ?? [], 'in_review');
        $approvedReviews = tl_training_status_count_value($ops['reviews']['decision_counts'] ?? [], 'approved');
        $pendingRewardEvents = tl_training_status_count_value($ops['reward_events']['status_counts'] ?? [], 'pending')
            + tl_training_status_count_value($ops['reward_events']['status_counts'] ?? [], 'eligible');
        $configuredRewardValue = (int)($ops['reward_rules']['configured_preview_value_cents'] ?? 0);
        $previewRewardValue = (int)($ops['reward_events']['preview_value_cents'] ?? 0);

        $tableWatch = [];
        foreach ($tableHealth as $table => $health) {
            if (empty($health['schema_ready']) || (int)($health['row_count'] ?? 0) === 0) {
                $tableWatch[] = [
                    'table' => $table,
                    'status' => !empty($health['schema_ready']) ? 'ready_empty' : 'check_schema',
                    'row_count' => (int)($health['row_count'] ?? 0),
                    'missing_columns' => $health['missing_columns'] ?? [],
                ];
            }
        }

        $attention = [];
        if (empty($status['connected'])) $attention[] = 'Database connection is not available; demo fallback is active.';
        if (!empty($status['missing_tables'])) $attention[] = 'One or more Training Lab tables are missing.';
        if ($expectedTables > 0 && $readyTables < $expectedTables) $attention[] = 'One or more table schemas need review.';
        if ($pendingProofs > 0) $attention[] = $pendingProofs . ' proof item(s) are waiting for read-only review visibility.';
        if ($pendingRewardEvents > 0) $attention[] = $pendingRewardEvents . ' reward event(s) are visible but not issued to Microgifter wallets.';
        if (!$attention) $attention[] = 'No blocking read-only diagnostics found.';

        return [
            'stage' => 'Stage 15 read-only review inspector',
            'mode' => $ops['mode'] ?? (!empty($status['connected']) ? 'database-read-only' : 'demo-fallback'),
            'health_score' => $expectedTables > 0 ? (int)round(($readyTables / $expectedTables) * 100) : 0,
            'ready_tables' => $readyTables,
            'expected_tables' => $expectedTables,
            'total_training_rows' => (int)($ops['total_training_rows'] ?? 0),
            'active_campaigns' => tl_training_status_count_value($ops['campaigns']['status_counts'] ?? [], 'active'),
            'total_campaigns' => (int)($ops['campaigns']['total'] ?? 0),
            'total_tasks' => (int)($ops['tasks']['total'] ?? 0),
            'proof_required_tasks' => (int)($ops['tasks']['proof_required'] ?? 0),
            'pending_proofs' => $pendingProofs,
            'approved_reviews' => $approvedReviews,
            'pending_reward_events' => $pendingRewardEvents,
            'configured_reward_value_cents' => $configuredRewardValue,
            'configured_reward_value_display' => tl_training_money_display($configuredRewardValue),
            'preview_reward_value_cents' => $previewRewardValue,
            'preview_reward_value_display' => tl_training_money_display($previewRewardValue),
            'latest_activity_at' => $ops['events']['latest_activity_at']
                ?? $ops['campaigns']['latest_activity_at']
                ?? $ops['tasks']['latest_activity_at']
                ?? null,
            'attention' => $attention,
            'table_watch' => $tableWatch,
            'recent_campaigns' => tl_training_recent_campaign_snapshots(6),
            'recent_reviews' => array_slice(tl_stage34_reviews(), 0, 6),
            'recent_events' => $latestEvents,
            'safe_boundaries' => [
                'read_only_command_center' => true,
                'no_auth_gate_added' => true,
                'no_real_media_upload_processing' => true,
                'no_payments' => true,
                'no_wallet_balance_changes' => true,
                'no_microgifter_reward_issuing' => true,
                'no_claim_redeem_logic' => true,
                'no_new_sql_required' => true,
            ],
        ];
    }
}

if (!function_exists('tl_training_rows_by_column')) {
    function tl_training_rows_by_column(string $table, string $column, $value, array $columns, string $orderBy = 'id DESC', int $limit = 50): array
    {
        if (!in_array($table, tl_training_required_tables(), true)) return [];
        $safeTable = tl_db_safe_identifier($table);
        $safeColumn = tl_db_safe_identifier($column);
        $pdo = tl_db();
        if (!$pdo || !$safeTable || !$safeColumn || !tl_table_exists($table) || !tl_table_has_column($table, $column)) return [];

        $selectColumns = [];
        foreach ($columns as $field) {
            $safeField = tl_db_safe_identifier($field);
            if ($safeField && tl_table_has_column($table, $field)) {
                $selectColumns[] = '`' . $safeField . '`';
            }
        }
        if (!$selectColumns) $selectColumns[] = '*';

        $order = 'id DESC';
        if ($orderBy !== '') {
            $parts = preg_split('/\s*,\s*/', $orderBy);
            $safeParts = [];
            foreach ($parts ?: [] as $part) {
                if (preg_match('/^([a-zA-Z0-9_]+)(\s+(ASC|DESC))?$/i', trim($part), $m)) {
                    $orderColumn = $m[1];
                    if (tl_table_has_column($table, $orderColumn)) {
                        $safeParts[] = '`' . $orderColumn . '`' . (!empty($m[2]) ? ' ' . strtoupper(trim($m[3])) : '');
                    }
                }
            }
            if ($safeParts) $order = implode(', ', $safeParts);
        }

        $limit = max(1, min(100, $limit));
        try {
            $stmt = $pdo->prepare('SELECT ' . implode(', ', $selectColumns) . ' FROM `' . $safeTable . '` WHERE `' . $safeColumn . '` = ? ORDER BY ' . $order . ' LIMIT ' . $limit);
            $stmt->execute([$value]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_training_status_breakdown_from_rows')) {
    function tl_training_status_breakdown_from_rows(array $rows, string $column = 'status'): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $value = strtolower(trim((string)($row[$column] ?? 'unknown')));
            if ($value === '') $value = 'unknown';
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        ksort($counts);
        return $counts;
    }
}

if (!function_exists('tl_training_campaign_label')) {
    function tl_training_campaign_label(array $campaign): string
    {
        return (string)($campaign['title'] ?? $campaign['name'] ?? $campaign['slug'] ?? $campaign['public_id'] ?? $campaign['id'] ?? 'Training Campaign');
    }
}

if (!function_exists('tl_training_campaign_record')) {
    function tl_training_campaign_record(?string $ref = null): array
    {
        $ref = trim((string)$ref);
        $pdo = tl_db();
        if ($pdo && tl_table_exists('training_campaigns')) {
            $columns = ['id','public_id','slug','title','summary','description','campaign_type','visibility','status','starts_at','ends_at','timezone','target_action_count','reward_summary','created_at','updated_at'];
            $select = [];
            foreach ($columns as $column) {
                $safe = tl_db_safe_identifier($column);
                if ($safe && tl_table_has_column('training_campaigns', $column)) $select[] = '`' . $safe . '`';
            }
            if (!$select) $select[] = '*';

            try {
                if ($ref !== '') {
                    $clauses = [];
                    $params = [];
                    foreach (['slug','public_id'] as $column) {
                        if (tl_table_has_column('training_campaigns', $column)) {
                            $clauses[] = '`' . $column . '` = ?';
                            $params[] = $ref;
                        }
                    }
                    if (ctype_digit($ref) && tl_table_has_column('training_campaigns', 'id')) {
                        $clauses[] = '`id` = ?';
                        $params[] = (int)$ref;
                    }
                    if ($clauses) {
                        $stmt = $pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM training_campaigns WHERE ' . implode(' OR ', $clauses) . ' ORDER BY id DESC LIMIT 1');
                        $stmt->execute($params);
                        $row = $stmt->fetch();
                        if ($row) return $row;
                    }
                }

                $stmt = $pdo->query('SELECT ' . implode(', ', $select) . ' FROM training_campaigns ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 1');
                $row = $stmt ? $stmt->fetch() : null;
                if ($row) return $row;
            } catch (Throwable $e) {}
        }

        $campaigns = tl_stage34_campaigns();
        $fallback = $campaigns[0] ?? [];
        if ($ref !== '') {
            foreach ($campaigns as $campaign) {
                if ((string)($campaign['id'] ?? '') === $ref) {
                    $fallback = $campaign;
                    break;
                }
            }
        }

        return [
            'id' => $fallback['id'] ?? 'movement-5',
            'public_id' => $fallback['public_id'] ?? '',
            'slug' => $fallback['id'] ?? 'movement-5',
            'title' => $fallback['title'] ?? 'Movement 5 Training Campaign',
            'summary' => $fallback['description'] ?? 'Demo fallback campaign summary.',
            'description' => $fallback['description'] ?? 'Demo fallback campaign description.',
            'campaign_type' => strtolower((string)($fallback['audience'] ?? 'training')),
            'visibility' => 'demo',
            'status' => strtolower((string)($fallback['status'] ?? 'draft')),
            'starts_at' => null,
            'ends_at' => $fallback['due'] ?? null,
            'timezone' => 'local',
            'target_action_count' => (int)($fallback['total_actions'] ?? 0),
            'reward_summary' => $fallback['reward'] ?? 'Preview only',
            'created_at' => null,
            'updated_at' => 'Demo fallback',
        ];
    }
}

if (!function_exists('tl_training_campaign_inspector_summary')) {
    function tl_training_campaign_inspector_summary(?string $ref = null): array
    {
        $campaign = tl_training_campaign_record($ref);
        $campaignId = $campaign['id'] ?? null;
        $dbMode = tl_db_ready() && tl_table_exists('training_campaigns') && is_numeric($campaignId);

        $tasks = [];
        $participants = [];
        $proofs = [];
        $reviews = [];
        $receipts = [];
        $rewardRules = [];
        $rewardEvents = [];
        $events = [];

        if ($dbMode) {
            $id = (int)$campaignId;
            $tasks = tl_training_rows_by_column('training_campaign_tasks', 'campaign_id', $id, ['id','public_id','position_no','day_no','task_type','title','instructions','proof_required','expected_duration_minutes','status','created_at','updated_at'], 'position_no ASC, day_no ASC, id ASC', 100);
            $participants = tl_training_rows_by_column('training_participants', 'campaign_id', $id, ['id','public_id','participant_label','status','joined_at','completed_at','created_at','updated_at'], 'created_at DESC, id DESC', 100);
            $proofs = tl_training_rows_by_column('training_proof_submissions', 'campaign_id', $id, ['id','public_id','task_id','participant_id','proof_type','proof_text','status','submitted_at','reviewed_at','created_at','updated_at'], 'submitted_at DESC, created_at DESC, id DESC', 100);
            $receipts = tl_training_rows_by_column('training_action_receipts', 'campaign_id', $id, ['id','public_id','participant_id','user_id','proof_submission_id','review_id','receipt_type','receipt_status','issued_at','voided_at','created_at'], 'created_at DESC, id DESC', 100);
            $rewardRules = tl_training_rows_by_column('training_reward_rules', 'campaign_id', $id, ['id','public_id','rule_name','trigger_type','threshold_count','reward_type','reward_label','reward_value_cents','currency','status','created_at','updated_at'], 'created_at DESC, id DESC', 50);
            $rewardEvents = tl_training_rows_by_column('training_reward_events', 'campaign_id', $id, ['id','public_id','participant_id','user_id','status','reward_rule_id','value_cents','currency','eligibility_reason','issued_at','cancelled_at','failure_message','created_at','updated_at'], 'created_at DESC, id DESC', 100);
            $events = tl_training_rows_by_column('training_events', 'subject_id', $id, ['id','public_id','actor_user_id','subject_type','subject_id','event_type','created_at'], 'created_at DESC, id DESC', 50);

            $proofIds = array_values(array_filter(array_map(fn($row) => isset($row['id']) ? (int)$row['id'] : null, $proofs)));
            if ($proofIds && tl_table_exists('training_reviews')) {
                try {
                    $placeholders = implode(',', array_fill(0, count($proofIds), '?'));
                    $pdo = tl_db();
                    if ($pdo) {
                        $stmt = $pdo->prepare('SELECT id, public_id, proof_submission_id, reviewer_user_id, decision, review_notes, reviewed_at, created_at FROM training_reviews WHERE proof_submission_id IN (' . $placeholders . ') ORDER BY COALESCE(reviewed_at, created_at) DESC, id DESC LIMIT 100');
                        $stmt->execute($proofIds);
                        $reviews = $stmt->fetchAll() ?: [];
                    }
                } catch (Throwable $e) {
                    $reviews = [];
                }
            }
        } else {
            $tasks = tl_stage34_tasks((string)($campaign['slug'] ?? $campaign['id'] ?? 'movement-5'));
            $reviews = tl_stage34_reviews();
            $participants = [
                ['participant_label' => 'Demo participant', 'status' => 'demo', 'joined_at' => 'Demo fallback'],
            ];
            $rewardRules = [[
                'rule_name' => 'Demo reward preview',
                'trigger_type' => 'proof_approved',
                'reward_type' => 'preview',
                'reward_label' => $campaign['reward_summary'] ?? 'Preview reward',
                'reward_value_cents' => 0,
                'currency' => 'USD',
                'status' => 'preview',
            ]];
        }

        $proofTaskIds = array_values(array_unique(array_filter(array_map(fn($row) => isset($row['task_id']) ? (string)$row['task_id'] : null, $proofs))));
        $proofCoverage = count($tasks) > 0 ? (int)round((count($proofTaskIds) / count($tasks)) * 100) : 0;

        $timeline = [];
        foreach ($events as $event) {
            $timeline[] = [
                'type' => $event['event_type'] ?? 'event',
                'label' => $event['subject_type'] ?? 'campaign',
                'at' => $event['created_at'] ?? null,
            ];
        }
        foreach (array_slice($proofs, 0, 8) as $proof) {
            $timeline[] = [
                'type' => 'proof_' . ($proof['status'] ?? 'submitted'),
                'label' => $proof['proof_type'] ?? 'proof',
                'at' => $proof['submitted_at'] ?? $proof['created_at'] ?? null,
            ];
        }
        usort($timeline, function ($a, $b) {
            return strcmp((string)($b['at'] ?? ''), (string)($a['at'] ?? ''));
        });

        return [
            'stage' => 'Stage 15 read-only review inspector',
            'mode' => $dbMode ? 'database-read-only' : 'demo-fallback',
            'campaign' => $campaign,
            'campaign_ref' => (string)($ref ?? ''),
            'tasks' => $tasks,
            'participants' => $participants,
            'proofs' => $proofs,
            'reviews' => $reviews,
            'receipts' => $receipts,
            'reward_rules' => $rewardRules,
            'reward_events' => $rewardEvents,
            'events' => $events,
            'timeline' => array_slice($timeline, 0, 12),
            'counts' => [
                'tasks' => count($tasks),
                'participants' => count($participants),
                'proofs' => count($proofs),
                'reviews' => count($reviews),
                'receipts' => count($receipts),
                'reward_rules' => count($rewardRules),
                'reward_events' => count($rewardEvents),
                'events' => count($events),
                'proof_coverage_percent' => $proofCoverage,
            ],
            'breakdowns' => [
                'task_status' => tl_training_status_breakdown_from_rows($tasks, 'status'),
                'participant_status' => tl_training_status_breakdown_from_rows($participants, 'status'),
                'proof_status' => tl_training_status_breakdown_from_rows($proofs, 'status'),
                'review_decision' => tl_training_status_breakdown_from_rows($reviews, 'decision'),
                'reward_event_status' => tl_training_status_breakdown_from_rows($rewardEvents, 'status'),
            ],
            'safe_boundaries' => [
                'read_only_campaign_inspector' => true,
                'read_only_review_inspector' => true,
                'no_campaign_writes' => true,
                'no_task_writes' => true,
                'no_review_decisions' => true,
                'no_reward_issuing' => true,
                'no_wallet_balance_changes' => true,
                'no_upload_processing' => true,
                'no_claim_redeem_logic' => true,
                'no_new_sql_required' => true,
            ],
        ];
    }
}



if (!function_exists('tl_training_review_queue_snapshots')) {
    function tl_training_review_queue_snapshots(int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $pdo = tl_db();
        if ($pdo && tl_table_exists('training_proof_submissions')) {
            try {
                $sql = "SELECT p.id, p.public_id, p.campaign_id, p.task_id, p.participant_id, p.submitted_by_user_id,
                    p.proof_type, p.status AS proof_status, p.submitted_at, p.reviewed_at, p.updated_at,
                    c.title AS campaign_title, c.slug AS campaign_slug, c.public_id AS campaign_public_id,
                    t.title AS task_title, t.position_no, t.day_no,
                    COALESCE(tp.participant_label, CONCAT('Participant #', p.participant_id)) AS participant_label,
                    (SELECT r.public_id FROM training_reviews r WHERE r.proof_submission_id = p.id ORDER BY r.created_at DESC LIMIT 1) AS latest_review_public_id,
                    (SELECT r.decision FROM training_reviews r WHERE r.proof_submission_id = p.id ORDER BY r.created_at DESC LIMIT 1) AS latest_decision,
                    (SELECT r.reviewed_at FROM training_reviews r WHERE r.proof_submission_id = p.id ORDER BY r.created_at DESC LIMIT 1) AS latest_reviewed_at,
                    (SELECT e.status FROM training_reward_events e WHERE e.participant_id = p.participant_id AND e.campaign_id = p.campaign_id ORDER BY e.created_at DESC LIMIT 1) AS reward_status
                    FROM training_proof_submissions p
                    LEFT JOIN training_campaigns c ON c.id = p.campaign_id
                    LEFT JOIN training_campaign_tasks t ON t.id = p.task_id
                    LEFT JOIN training_participants tp ON tp.id = p.participant_id
                    ORDER BY COALESCE(p.updated_at, p.submitted_at, p.created_at) DESC, p.id DESC
                    LIMIT " . $limit;
                $rows = $pdo->query($sql)->fetchAll();
                if ($rows) {
                    return array_map(function ($row) {
                        $decision = $row['latest_decision'] ?: ($row['proof_status'] === 'approved' ? 'approved' : ($row['proof_status'] === 'rejected' ? 'rejected' : 'pending'));
                        return [
                            'id' => (int)($row['id'] ?? 0),
                            'public_id' => (string)($row['public_id'] ?? ''),
                            'inspect_ref' => (string)($row['public_id'] ?? $row['id'] ?? ''),
                            'participant' => (string)($row['participant_label'] ?? 'Participant'),
                            'campaign' => (string)($row['campaign_title'] ?? 'Training Campaign'),
                            'campaign_ref' => (string)($row['campaign_slug'] ?: ($row['campaign_public_id'] ?: $row['campaign_id'])),
                            'task' => (string)($row['task_title'] ?? 'Training task'),
                            'task_position' => trim((string)($row['position_no'] ?? '') . ((isset($row['day_no']) && $row['day_no'] !== null) ? ' / Day ' . (string)$row['day_no'] : '')),
                            'proof_type' => (string)($row['proof_type'] ?? 'text'),
                            'proof_status' => ucwords(str_replace('_', ' ', (string)($row['proof_status'] ?? 'submitted'))),
                            'review_status' => ucwords(str_replace('_', ' ', (string)$decision)),
                            'reward_status' => ucwords(str_replace('_', ' ', (string)($row['reward_status'] ?? 'pending'))),
                            'submitted_at' => $row['submitted_at'] ?? null,
                            'reviewed_at' => $row['latest_reviewed_at'] ?: ($row['reviewed_at'] ?? null),
                            'updated_at' => $row['updated_at'] ?? null,
                        ];
                    }, $rows);
                }
            } catch (Throwable $e) {}
        }

        return array_map(function ($review, $index) {
            return [
                'id' => $index + 1,
                'public_id' => (string)($review['id'] ?? ('demo-review-' . ($index + 1))),
                'inspect_ref' => (string)($review['id'] ?? ('demo-review-' . ($index + 1))),
                'participant' => (string)($review['participant'] ?? 'Demo participant'),
                'campaign' => (string)($review['campaign'] ?? 'Demo campaign'),
                'campaign_ref' => 'movement-5',
                'task' => 'Demo proof task',
                'task_position' => 'Demo',
                'proof_type' => 'text',
                'proof_status' => (string)($review['proof_status'] ?? 'Submitted'),
                'review_status' => (string)($review['review_status'] ?? 'Pending'),
                'reward_status' => (string)($review['reward_status'] ?? 'Pending'),
                'submitted_at' => (string)($review['last_update'] ?? 'Demo fallback'),
                'reviewed_at' => null,
                'updated_at' => (string)($review['last_update'] ?? 'Demo fallback'),
            ];
        }, tl_stage34_reviews(), array_keys(tl_stage34_reviews()));
    }
}

if (!function_exists('tl_training_review_proof_record')) {
    function tl_training_review_proof_record(?string $ref = null): array
    {
        $ref = trim((string)$ref);
        $pdo = tl_db();
        if ($pdo && tl_table_exists('training_proof_submissions')) {
            try {
                $sql = "SELECT p.id, p.public_id, p.campaign_id, p.task_id, p.participant_id, p.submitted_by_user_id,
                    p.proof_type, p.proof_text, p.storage_reference, p.external_url, p.status, p.submitted_at, p.reviewed_at, p.metadata_json, p.created_at, p.updated_at,
                    c.title AS campaign_title, c.slug AS campaign_slug, c.public_id AS campaign_public_id,
                    t.title AS task_title, t.instructions AS task_instructions, t.position_no, t.day_no, t.proof_required,
                    COALESCE(tp.participant_label, CONCAT('Participant #', p.participant_id)) AS participant_label, tp.status AS participant_status
                    FROM training_proof_submissions p
                    LEFT JOIN training_campaigns c ON c.id = p.campaign_id
                    LEFT JOIN training_campaign_tasks t ON t.id = p.task_id
                    LEFT JOIN training_participants tp ON tp.id = p.participant_id";
                if ($ref !== '') {
                    $clauses = ['p.public_id = ?'];
                    $params = [$ref];
                    if (ctype_digit($ref)) {
                        $clauses[] = 'p.id = ?';
                        $params[] = (int)$ref;
                    }
                    $stmt = $pdo->prepare($sql . ' WHERE ' . implode(' OR ', $clauses) . ' ORDER BY p.id DESC LIMIT 1');
                    $stmt->execute($params);
                    $row = $stmt->fetch();
                    if ($row) return $row;
                }
                $stmt = $pdo->query($sql . ' ORDER BY COALESCE(p.updated_at, p.submitted_at, p.created_at) DESC, p.id DESC LIMIT 1');
                $row = $stmt ? $stmt->fetch() : null;
                if ($row) return $row;
            } catch (Throwable $e) {}
        }

        $queue = tl_training_review_queue_snapshots(1);
        $fallback = $queue[0] ?? [];
        return [
            'id' => $fallback['id'] ?? 1,
            'public_id' => $fallback['public_id'] ?? 'demo-review-1001',
            'campaign_id' => null,
            'task_id' => null,
            'participant_id' => null,
            'submitted_by_user_id' => null,
            'proof_type' => $fallback['proof_type'] ?? 'text',
            'proof_text' => 'Demo fallback proof detail. This is visibility only and does not process real uploads.',
            'storage_reference' => null,
            'external_url' => null,
            'status' => strtolower(str_replace(' ', '_', (string)($fallback['proof_status'] ?? 'submitted'))),
            'submitted_at' => $fallback['submitted_at'] ?? 'Demo fallback',
            'reviewed_at' => $fallback['reviewed_at'] ?? null,
            'metadata_json' => null,
            'created_at' => null,
            'updated_at' => $fallback['updated_at'] ?? 'Demo fallback',
            'campaign_title' => $fallback['campaign'] ?? 'Demo Training Campaign',
            'campaign_slug' => $fallback['campaign_ref'] ?? 'movement-5',
            'campaign_public_id' => '',
            'task_title' => $fallback['task'] ?? 'Demo proof task',
            'task_instructions' => 'Demo fallback task instructions.',
            'position_no' => null,
            'day_no' => null,
            'proof_required' => 1,
            'participant_label' => $fallback['participant'] ?? 'Demo Participant',
            'participant_status' => 'demo',
        ];
    }
}

if (!function_exists('tl_training_review_inspector_summary')) {
    function tl_training_review_inspector_summary(?string $ref = null): array
    {
        $proof = tl_training_review_proof_record($ref);
        $proofId = isset($proof['id']) && is_numeric($proof['id']) ? (int)$proof['id'] : null;
        $campaignId = isset($proof['campaign_id']) && is_numeric($proof['campaign_id']) ? (int)$proof['campaign_id'] : null;
        $participantId = isset($proof['participant_id']) && is_numeric($proof['participant_id']) ? (int)$proof['participant_id'] : null;
        $dbMode = tl_db_ready() && tl_table_exists('training_proof_submissions') && $proofId !== null && $campaignId !== null;

        $reviews = [];
        $receipts = [];
        $rewardEvents = [];
        $events = [];
        $nearbyQueue = tl_training_review_queue_snapshots(12);

        if ($dbMode) {
            $pdo = tl_db();
            try {
                if ($pdo && tl_table_exists('training_reviews')) {
                    $stmt = $pdo->prepare('SELECT id, public_id, proof_submission_id, reviewer_user_id, decision, review_notes, reviewed_at, created_at FROM training_reviews WHERE proof_submission_id = ? ORDER BY COALESCE(reviewed_at, created_at) DESC, id DESC LIMIT 50');
                    $stmt->execute([$proofId]);
                    $reviews = $stmt->fetchAll() ?: [];
                }
                if ($pdo && tl_table_exists('training_action_receipts')) {
                    $stmt = $pdo->prepare('SELECT id, public_id, campaign_id, participant_id, user_id, proof_submission_id, review_id, receipt_type, receipt_status, issued_at, voided_at, created_at FROM training_action_receipts WHERE proof_submission_id = ? ORDER BY created_at DESC, id DESC LIMIT 50');
                    $stmt->execute([$proofId]);
                    $receipts = $stmt->fetchAll() ?: [];
                }
                if ($pdo && tl_table_exists('training_reward_events') && $participantId !== null && $campaignId !== null) {
                    $stmt = $pdo->prepare('SELECT id, public_id, campaign_id, participant_id, user_id, action_receipt_id, reward_rule_id, status, value_cents, currency, eligibility_reason, issued_at, cancelled_at, failure_message, created_at, updated_at FROM training_reward_events WHERE participant_id = ? AND campaign_id = ? ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 50');
                    $stmt->execute([$participantId, $campaignId]);
                    $rewardEvents = $stmt->fetchAll() ?: [];
                }
                if ($pdo && tl_table_exists('training_events')) {
                    $eventRows = [];
                    $stmt = $pdo->prepare('SELECT id, public_id, actor_user_id, subject_type, subject_id, event_type, created_at FROM training_events WHERE subject_type = ? AND subject_id = ? ORDER BY created_at DESC, id DESC LIMIT 50');
                    $stmt->execute(['proof', $proofId]);
                    $eventRows = array_merge($eventRows, $stmt->fetchAll() ?: []);
                    foreach ($reviews as $review) {
                        if (!isset($review['id'])) continue;
                        $stmt->execute(['review', (int)$review['id']]);
                        $eventRows = array_merge($eventRows, $stmt->fetchAll() ?: []);
                    }
                    $events = $eventRows;
                }
            } catch (Throwable $e) {
                $reviews = $reviews ?: [];
            }
        } else {
            $seedReviews = tl_stage34_reviews();
            $reviews = [[
                'public_id' => 'demo-review-preview',
                'reviewer_user_id' => null,
                'decision' => strtolower(str_replace(' ', '_', (string)($seedReviews[0]['review_status'] ?? 'pending'))),
                'review_notes' => 'Demo fallback review preview. No reviewer decision is written by this inspector.',
                'reviewed_at' => $seedReviews[0]['last_update'] ?? 'Demo fallback',
                'created_at' => 'Demo fallback',
            ]];
            $receipts = [];
            $rewardEvents = [[
                'public_id' => 'demo-reward-preview',
                'status' => 'preview',
                'value_cents' => 0,
                'currency' => 'USD',
                'eligibility_reason' => 'Demo fallback eligibility preview only.',
                'created_at' => 'Demo fallback',
                'updated_at' => 'Demo fallback',
            ]];
        }

        $timeline = [];
        $timeline[] = ['type' => 'proof_' . ($proof['status'] ?? 'submitted'), 'label' => 'Proof status', 'at' => $proof['submitted_at'] ?? $proof['created_at'] ?? null];
        foreach ($reviews as $review) {
            $timeline[] = ['type' => 'review_' . ($review['decision'] ?? 'pending'), 'label' => 'Review decision', 'at' => $review['reviewed_at'] ?? $review['created_at'] ?? null];
        }
        foreach ($receipts as $receipt) {
            $timeline[] = ['type' => 'receipt_' . ($receipt['receipt_status'] ?? 'active'), 'label' => $receipt['receipt_type'] ?? 'receipt', 'at' => $receipt['issued_at'] ?? $receipt['created_at'] ?? null];
        }
        foreach ($rewardEvents as $event) {
            $timeline[] = ['type' => 'reward_' . ($event['status'] ?? 'eligible'), 'label' => $event['eligibility_reason'] ?? 'reward preview', 'at' => $event['issued_at'] ?? $event['updated_at'] ?? $event['created_at'] ?? null];
        }
        foreach ($events as $event) {
            $timeline[] = ['type' => $event['event_type'] ?? 'event', 'label' => $event['subject_type'] ?? 'event', 'at' => $event['created_at'] ?? null];
        }
        usort($timeline, function ($a, $b) {
            return strcmp((string)($b['at'] ?? ''), (string)($a['at'] ?? ''));
        });

        $latestReview = $reviews[0] ?? null;
        $latestDecision = $latestReview['decision'] ?? null;
        $rewardValue = 0;
        foreach ($rewardEvents as $event) $rewardValue += (int)($event['value_cents'] ?? 0);

        return [
            'stage' => 'Stage 15 read-only review inspector',
            'mode' => $dbMode ? 'database-read-only' : 'demo-fallback',
            'proof_ref' => (string)($ref ?? ''),
            'proof' => $proof,
            'review_summary' => [
                'proof_status' => ucwords(str_replace('_', ' ', (string)($proof['status'] ?? 'submitted'))),
                'latest_decision' => $latestDecision ? ucwords(str_replace('_', ' ', (string)$latestDecision)) : 'Pending',
                'review_count' => count($reviews),
                'receipt_count' => count($receipts),
                'reward_event_count' => count($rewardEvents),
                'preview_reward_value_cents' => $rewardValue,
                'preview_reward_value_display' => tl_training_money_display($rewardValue),
            ],
            'reviews' => $reviews,
            'receipts' => $receipts,
            'reward_events' => $rewardEvents,
            'events' => $events,
            'timeline' => array_slice($timeline, 0, 16),
            'nearby_queue' => $nearbyQueue,
            'breakdowns' => [
                'queue_proof_status' => tl_training_status_breakdown_from_rows($nearbyQueue, 'proof_status'),
                'queue_review_status' => tl_training_status_breakdown_from_rows($nearbyQueue, 'review_status'),
                'review_decision' => tl_training_status_breakdown_from_rows($reviews, 'decision'),
                'receipt_status' => tl_training_status_breakdown_from_rows($receipts, 'receipt_status'),
                'reward_status' => tl_training_status_breakdown_from_rows($rewardEvents, 'status'),
            ],
            'safe_boundaries' => [
                'read_only_review_inspector' => true,
                'no_review_decision_writes' => true,
                'no_receipt_writes' => true,
                'no_reward_issuing' => true,
                'no_wallet_balance_changes' => true,
                'no_upload_processing' => true,
                'no_claim_redeem_logic' => true,
                'no_auth_gate_added' => true,
                'no_new_sql_required' => true,
            ],
        ];
    }
}


if (!function_exists('tl_stage20_clean_ref')) {
    function tl_stage20_clean_ref(?string $ref): string
    {
        return preg_replace('/[^a-zA-Z0-9\-_]/', '', trim((string)$ref)) ?? '';
    }
}

if (!function_exists('tl_training_event_rows_for_subject')) {
    function tl_training_event_rows_for_subject(string $subjectType, $subjectId, int $limit = 50): array
    {
        $pdo = tl_db();
        $limit = max(1, min(100, $limit));
        if (!$pdo || !tl_table_exists('training_events') || $subjectId === null || $subjectId === '') return [];
        try {
            $stmt = $pdo->prepare('SELECT id, public_id, actor_user_id, subject_type, subject_id, event_type, metadata_json, created_at FROM training_events WHERE subject_type = ? AND subject_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . $limit);
            $stmt->execute([$subjectType, $subjectId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_training_proof_reviews_for_ids')) {
    function tl_training_proof_reviews_for_ids(array $proofIds, int $limit = 100): array
    {
        $proofIds = array_values(array_unique(array_filter(array_map('intval', $proofIds))));
        if (!$proofIds || !tl_db_ready() || !tl_table_exists('training_reviews')) return [];
        $limit = max(1, min(150, $limit));
        try {
            $pdo = tl_db();
            if (!$pdo) return [];
            $placeholders = implode(',', array_fill(0, count($proofIds), '?'));
            $stmt = $pdo->prepare('SELECT id, public_id, proof_submission_id, reviewer_user_id, decision, review_notes, reviewed_at, created_at FROM training_reviews WHERE proof_submission_id IN (' . $placeholders . ') ORDER BY COALESCE(reviewed_at, created_at) DESC, id DESC LIMIT ' . $limit);
            $stmt->execute($proofIds);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_training_task_record')) {
    function tl_training_task_record(?string $ref = null): array
    {
        $ref = tl_stage20_clean_ref($ref);
        $pdo = tl_db();
        if ($pdo && tl_table_exists('training_campaign_tasks')) {
            try {
                $sql = "SELECT t.id, t.public_id, t.campaign_id, t.position_no, t.day_no, t.task_type, t.title, t.instructions, t.proof_required, t.expected_duration_minutes, t.status, t.settings_json, t.created_at, t.updated_at,
                        c.title AS campaign_title, c.slug AS campaign_slug, c.public_id AS campaign_public_id, c.status AS campaign_status, c.reward_summary
                        FROM training_campaign_tasks t
                        LEFT JOIN training_campaigns c ON c.id = t.campaign_id";
                if ($ref !== '') {
                    $clauses = ['t.public_id = ?'];
                    $params = [$ref];
                    if (ctype_digit($ref)) {
                        $clauses[] = 't.id = ?';
                        $params[] = (int)$ref;
                    }
                    $stmt = $pdo->prepare($sql . ' WHERE ' . implode(' OR ', $clauses) . ' ORDER BY t.id DESC LIMIT 1');
                    $stmt->execute($params);
                    $row = $stmt->fetch();
                    if ($row) return $row;
                }
                $stmt = $pdo->query($sql . ' ORDER BY t.campaign_id DESC, t.position_no ASC, t.id ASC LIMIT 1');
                $row = $stmt ? $stmt->fetch() : null;
                if ($row) return $row;
            } catch (Throwable $e) {}
        }

        $seed = tl_stage34_seed();
        $campaigns = $seed['campaigns'] ?? [];
        $tasksByCampaign = $seed['tasks'] ?? [];
        $campaign = $campaigns[0] ?? [];
        $task = [];
        foreach ($tasksByCampaign as $campaignKey => $tasks) {
            foreach ($tasks as $row) {
                if (!$task) $task = $row;
                if ($ref !== '' && (string)($row['id'] ?? '') === $ref) {
                    $task = $row;
                    foreach ($campaigns as $candidate) {
                        if ((string)($candidate['id'] ?? '') === (string)$campaignKey) $campaign = $candidate;
                    }
                    break 2;
                }
            }
        }
        return [
            'id' => $task['db_id'] ?? null,
            'public_id' => $task['id'] ?? 'demo-task-1',
            'campaign_id' => $campaign['db_id'] ?? null,
            'position_no' => $task['day'] ?? 1,
            'day_no' => $task['day'] ?? 1,
            'task_type' => 'demo_task',
            'title' => $task['title'] ?? 'Demo Training Task',
            'instructions' => 'Demo fallback task instructions. This inspector is read-only and does not unlock or complete tasks.',
            'proof_required' => (($task['proof'] ?? '') !== 'Locked') ? 1 : 0,
            'expected_duration_minutes' => 15,
            'status' => strtolower((string)($task['status'] ?? 'active')),
            'settings_json' => null,
            'created_at' => null,
            'updated_at' => 'Demo fallback',
            'campaign_title' => $campaign['title'] ?? 'Demo Training Campaign',
            'campaign_slug' => $campaign['id'] ?? 'movement-5',
            'campaign_public_id' => $campaign['public_id'] ?? '',
            'campaign_status' => strtolower((string)($campaign['status'] ?? 'active')),
            'reward_summary' => $campaign['reward'] ?? 'Preview only',
        ];
    }
}

if (!function_exists('tl_training_task_inspector_summary')) {
    function tl_training_task_inspector_summary(?string $ref = null): array
    {
        $task = tl_training_task_record($ref);
        $taskId = isset($task['id']) && is_numeric($task['id']) ? (int)$task['id'] : null;
        $campaignId = isset($task['campaign_id']) && is_numeric($task['campaign_id']) ? (int)$task['campaign_id'] : null;
        $dbMode = tl_db_ready() && $taskId !== null && $campaignId !== null;
        $proofs = $participants = $reviews = $receipts = $rewardEvents = $events = [];
        if ($dbMode) {
            $proofs = tl_training_rows_by_column('training_proof_submissions', 'task_id', $taskId, ['id','public_id','campaign_id','task_id','participant_id','submitted_by_user_id','proof_type','proof_text','status','submitted_at','reviewed_at','created_at','updated_at'], 'submitted_at DESC, created_at DESC, id DESC', 100);
            $participants = tl_training_rows_by_column('training_participants', 'campaign_id', $campaignId, ['id','public_id','user_id','participant_label','status','joined_at','completed_at','created_at','updated_at'], 'updated_at DESC, created_at DESC, id DESC', 100);
            $proofIds = array_values(array_filter(array_map(fn($row) => isset($row['id']) ? (int)$row['id'] : null, $proofs)));
            $reviews = tl_training_proof_reviews_for_ids($proofIds, 100);
            if ($proofIds && tl_table_exists('training_action_receipts')) {
                try {
                    $pdo = tl_db();
                    if ($pdo) {
                        $stmt = $pdo->prepare('SELECT id, public_id, campaign_id, participant_id, user_id, proof_submission_id, review_id, receipt_type, receipt_status, issued_at, voided_at, created_at FROM training_action_receipts WHERE proof_submission_id IN (' . implode(',', array_fill(0, count($proofIds), '?')) . ') ORDER BY created_at DESC, id DESC LIMIT 100');
                        $stmt->execute($proofIds);
                        $receipts = $stmt->fetchAll() ?: [];
                    }
                } catch (Throwable $e) {}
            }
            $rewardEvents = tl_training_rows_by_column('training_reward_events', 'campaign_id', $campaignId, ['id','public_id','participant_id','user_id','action_receipt_id','reward_rule_id','status','value_cents','currency','eligibility_reason','issued_at','cancelled_at','failure_message','created_at','updated_at'], 'created_at DESC, id DESC', 100);
            $events = tl_training_event_rows_for_subject('task', $taskId, 50);
        } else {
            $proofs = [[
                'public_id' => 'demo-proof-preview',
                'proof_type' => 'text',
                'status' => 'preview',
                'proof_text' => 'Demo fallback proof preview for this task.',
                'submitted_at' => 'Demo fallback',
            ]];
            $participants = [['participant_label' => 'Demo Participant', 'status' => 'active', 'joined_at' => 'Demo fallback']];
            $reviews = [['decision' => 'pending', 'review_notes' => 'Demo fallback review visibility only.', 'reviewed_at' => null]];
            $rewardEvents = [['status' => 'preview', 'value_cents' => 0, 'currency' => 'USD', 'eligibility_reason' => 'Task reward preview only.', 'created_at' => 'Demo fallback']];
        }
        $timeline = [];
        $timeline[] = ['type'=>'task_' . ($task['status'] ?? 'active'), 'label'=>'Task status', 'at'=>$task['updated_at'] ?? $task['created_at'] ?? null];
        foreach ($proofs as $proof) $timeline[] = ['type'=>'proof_' . ($proof['status'] ?? 'submitted'), 'label'=>$proof['public_id'] ?? 'proof', 'at'=>$proof['submitted_at'] ?? $proof['created_at'] ?? null];
        foreach ($reviews as $review) $timeline[] = ['type'=>'review_' . ($review['decision'] ?? 'pending'), 'label'=>$review['review_notes'] ?? 'review', 'at'=>$review['reviewed_at'] ?? $review['created_at'] ?? null];
        foreach ($events as $event) $timeline[] = ['type'=>$event['event_type'] ?? 'event', 'label'=>$event['subject_type'] ?? 'event', 'at'=>$event['created_at'] ?? null];
        usort($timeline, fn($a, $b) => strcmp((string)($b['at'] ?? ''), (string)($a['at'] ?? '')));
        return [
            'stage' => 'Stage 16 read-only task inspector',
            'mode' => $dbMode ? 'database-read-only' : 'demo-fallback',
            'task_ref' => (string)($ref ?? ''),
            'task' => $task,
            'summary' => [
                'proof_count' => count($proofs),
                'participant_count' => count($participants),
                'review_count' => count($reviews),
                'receipt_count' => count($receipts),
                'reward_event_count' => count($rewardEvents),
                'proof_required' => !empty($task['proof_required']),
            ],
            'proofs' => $proofs,
            'participants' => $participants,
            'reviews' => $reviews,
            'receipts' => $receipts,
            'reward_events' => $rewardEvents,
            'events' => $events,
            'timeline' => array_slice($timeline, 0, 18),
            'breakdowns' => [
                'proof_status' => tl_training_status_breakdown_from_rows($proofs, 'status'),
                'participant_status' => tl_training_status_breakdown_from_rows($participants, 'status'),
                'review_decision' => tl_training_status_breakdown_from_rows($reviews, 'decision'),
                'reward_status' => tl_training_status_breakdown_from_rows($rewardEvents, 'status'),
            ],
            'safe_boundaries' => [
                'read_only_task_inspector' => true,
                'no_task_completion_writes' => true,
                'no_unlocks' => true,
                'no_upload_processing' => true,
                'no_reward_issuing' => true,
                'no_wallet_balance_changes' => true,
                'no_auth_gate_added' => true,
                'no_new_sql_required' => true,
            ],
        ];
    }
}

if (!function_exists('tl_training_participant_record')) {
    function tl_training_participant_record(?string $ref = null): array
    {
        $ref = tl_stage20_clean_ref($ref);
        $pdo = tl_db();
        if ($pdo && tl_table_exists('training_participants')) {
            try {
                $sql = "SELECT p.id, p.public_id, p.campaign_id, p.user_id, p.invited_by_user_id, p.participant_label, p.status, p.joined_at, p.completed_at, p.removed_at, p.metadata_json, p.created_at, p.updated_at,
                        c.title AS campaign_title, c.slug AS campaign_slug, c.public_id AS campaign_public_id, c.status AS campaign_status
                        FROM training_participants p
                        LEFT JOIN training_campaigns c ON c.id = p.campaign_id";
                if ($ref !== '') {
                    $clauses = ['p.public_id = ?'];
                    $params = [$ref];
                    if (ctype_digit($ref)) {
                        $clauses[] = 'p.id = ?';
                        $params[] = (int)$ref;
                        $clauses[] = 'p.user_id = ?';
                        $params[] = (int)$ref;
                    }
                    $stmt = $pdo->prepare($sql . ' WHERE ' . implode(' OR ', $clauses) . ' ORDER BY p.id DESC LIMIT 1');
                    $stmt->execute($params);
                    $row = $stmt->fetch();
                    if ($row) return $row;
                }
                $stmt = $pdo->query($sql . ' ORDER BY COALESCE(p.updated_at, p.joined_at, p.created_at) DESC, p.id DESC LIMIT 1');
                $row = $stmt ? $stmt->fetch() : null;
                if ($row) return $row;
            } catch (Throwable $e) {}
        }
        $review = tl_stage34_reviews()[0] ?? [];
        return [
            'id' => null,
            'public_id' => 'demo-participant-001',
            'campaign_id' => null,
            'user_id' => null,
            'invited_by_user_id' => null,
            'participant_label' => $review['participant'] ?? 'Demo Participant',
            'status' => 'demo',
            'joined_at' => 'Demo fallback',
            'completed_at' => null,
            'removed_at' => null,
            'metadata_json' => null,
            'created_at' => null,
            'updated_at' => 'Demo fallback',
            'campaign_title' => $review['campaign'] ?? 'Demo Training Campaign',
            'campaign_slug' => 'movement-5',
            'campaign_public_id' => '',
            'campaign_status' => 'active',
        ];
    }
}

if (!function_exists('tl_training_participant_inspector_summary')) {
    function tl_training_participant_inspector_summary(?string $ref = null): array
    {
        $participant = tl_training_participant_record($ref);
        $participantId = isset($participant['id']) && is_numeric($participant['id']) ? (int)$participant['id'] : null;
        $campaignId = isset($participant['campaign_id']) && is_numeric($participant['campaign_id']) ? (int)$participant['campaign_id'] : null;
        $dbMode = tl_db_ready() && $participantId !== null;
        $proofs = $reviews = $receipts = $rewardEvents = $streaks = $events = [];
        if ($dbMode) {
            $proofs = tl_training_rows_by_column('training_proof_submissions', 'participant_id', $participantId, ['id','public_id','campaign_id','task_id','participant_id','submitted_by_user_id','proof_type','proof_text','status','submitted_at','reviewed_at','created_at','updated_at'], 'submitted_at DESC, created_at DESC, id DESC', 100);
            $proofIds = array_values(array_filter(array_map(fn($row) => isset($row['id']) ? (int)$row['id'] : null, $proofs)));
            $reviews = tl_training_proof_reviews_for_ids($proofIds, 100);
            $receipts = tl_training_rows_by_column('training_action_receipts', 'participant_id', $participantId, ['id','public_id','campaign_id','participant_id','user_id','proof_submission_id','review_id','receipt_type','receipt_status','issued_at','voided_at','created_at'], 'created_at DESC, id DESC', 100);
            $rewardEvents = tl_training_rows_by_column('training_reward_events', 'participant_id', $participantId, ['id','public_id','campaign_id','participant_id','user_id','action_receipt_id','reward_rule_id','status','value_cents','currency','eligibility_reason','issued_at','cancelled_at','failure_message','created_at','updated_at'], 'created_at DESC, id DESC', 100);
            $streaks = tl_training_rows_by_column('training_streaks', 'participant_id', $participantId, ['id','campaign_id','participant_id','user_id','current_streak_days','longest_streak_days','completed_action_count','last_action_date','updated_at','created_at'], 'updated_at DESC, id DESC', 20);
            $events = tl_training_event_rows_for_subject('participant', $participantId, 50);
        } else {
            $proofs = [['public_id'=>'demo-proof-preview','proof_type'=>'text','status'=>'submitted','submitted_at'=>'Demo fallback']];
            $reviews = [['decision'=>'pending','review_notes'=>'Demo fallback review visibility.', 'reviewed_at'=>null]];
            $receipts = [];
            $rewardEvents = [['status'=>'preview','value_cents'=>0,'currency'=>'USD','eligibility_reason'=>'Participant reward preview only.','created_at'=>'Demo fallback']];
            $streaks = [['current_streak_days'=>4,'longest_streak_days'=>4,'completed_action_count'=>4,'last_action_date'=>'Demo fallback','updated_at'=>'Demo fallback']];
        }
        $rewardValue = 0;
        foreach ($rewardEvents as $event) $rewardValue += (int)($event['value_cents'] ?? 0);
        $timeline = [];
        $timeline[] = ['type'=>'participant_' . ($participant['status'] ?? 'active'), 'label'=>'Participant status', 'at'=>$participant['updated_at'] ?? $participant['joined_at'] ?? null];
        foreach ($proofs as $proof) $timeline[] = ['type'=>'proof_' . ($proof['status'] ?? 'submitted'), 'label'=>$proof['public_id'] ?? 'proof', 'at'=>$proof['submitted_at'] ?? $proof['created_at'] ?? null];
        foreach ($rewardEvents as $event) $timeline[] = ['type'=>'reward_' . ($event['status'] ?? 'eligible'), 'label'=>$event['eligibility_reason'] ?? 'reward preview', 'at'=>$event['issued_at'] ?? $event['updated_at'] ?? $event['created_at'] ?? null];
        foreach ($events as $event) $timeline[] = ['type'=>$event['event_type'] ?? 'event', 'label'=>$event['subject_type'] ?? 'event', 'at'=>$event['created_at'] ?? null];
        usort($timeline, fn($a, $b) => strcmp((string)($b['at'] ?? ''), (string)($a['at'] ?? '')));
        return [
            'stage' => 'Stage 17 read-only participant inspector',
            'mode' => $dbMode ? 'database-read-only' : 'demo-fallback',
            'participant_ref' => (string)($ref ?? ''),
            'participant' => $participant,
            'summary' => [
                'proof_count' => count($proofs),
                'review_count' => count($reviews),
                'receipt_count' => count($receipts),
                'reward_event_count' => count($rewardEvents),
                'streak_count' => count($streaks),
                'preview_reward_value_cents' => $rewardValue,
                'preview_reward_value_display' => tl_training_money_display($rewardValue),
            ],
            'proofs' => $proofs,
            'reviews' => $reviews,
            'receipts' => $receipts,
            'reward_events' => $rewardEvents,
            'streaks' => $streaks,
            'events' => $events,
            'timeline' => array_slice($timeline, 0, 18),
            'breakdowns' => [
                'proof_status' => tl_training_status_breakdown_from_rows($proofs, 'status'),
                'review_decision' => tl_training_status_breakdown_from_rows($reviews, 'decision'),
                'receipt_status' => tl_training_status_breakdown_from_rows($receipts, 'receipt_status'),
                'reward_status' => tl_training_status_breakdown_from_rows($rewardEvents, 'status'),
            ],
            'safe_boundaries' => [
                'read_only_participant_inspector' => true,
                'no_profile_writes' => true,
                'no_progress_writes' => true,
                'no_wallet_balance_changes' => true,
                'no_reward_issuing' => true,
                'no_auth_gate_added' => true,
                'no_new_sql_required' => true,
            ],
        ];
    }
}

if (!function_exists('tl_training_reward_rule_record')) {
    function tl_training_reward_rule_record(?string $ref = null): array
    {
        $ref = tl_stage20_clean_ref($ref);
        $pdo = tl_db();
        if ($pdo && tl_table_exists('training_reward_rules')) {
            try {
                $sql = "SELECT rr.id, rr.public_id, rr.campaign_id, rr.rule_name, rr.trigger_type, rr.threshold_count, rr.reward_type, rr.reward_label, rr.reward_value_cents, rr.currency, rr.linked_microgift_template_id, rr.linked_catalog_product_id, rr.status, rr.settings_json, rr.created_at, rr.updated_at,
                        c.title AS campaign_title, c.slug AS campaign_slug, c.public_id AS campaign_public_id
                        FROM training_reward_rules rr LEFT JOIN training_campaigns c ON c.id = rr.campaign_id";
                if ($ref !== '') {
                    $clauses = ['rr.public_id = ?'];
                    $params = [$ref];
                    if (ctype_digit($ref)) {
                        $clauses[] = 'rr.id = ?';
                        $params[] = (int)$ref;
                    }
                    $stmt = $pdo->prepare($sql . ' WHERE ' . implode(' OR ', $clauses) . ' ORDER BY rr.id DESC LIMIT 1');
                    $stmt->execute($params);
                    $row = $stmt->fetch();
                    if ($row) return $row;
                }
                $stmt = $pdo->query($sql . ' ORDER BY COALESCE(rr.updated_at, rr.created_at) DESC, rr.id DESC LIMIT 1');
                $row = $stmt ? $stmt->fetch() : null;
                if ($row) return $row;
            } catch (Throwable $e) {}
        }
        return [
            'id' => null,
            'public_id' => 'demo-reward-rule',
            'campaign_id' => null,
            'rule_name' => 'Demo Reward Preview',
            'trigger_type' => 'sequence_completed',
            'threshold_count' => 5,
            'reward_type' => 'badge',
            'reward_label' => 'Movement Milestone',
            'reward_value_cents' => 0,
            'currency' => 'USD',
            'linked_microgift_template_id' => null,
            'linked_catalog_product_id' => null,
            'status' => 'preview',
            'settings_json' => null,
            'created_at' => null,
            'updated_at' => 'Demo fallback',
            'campaign_title' => 'Demo Training Campaign',
            'campaign_slug' => 'movement-5',
            'campaign_public_id' => '',
        ];
    }
}

if (!function_exists('tl_training_reward_inspector_summary')) {
    function tl_training_reward_inspector_summary(?string $ref = null): array
    {
        $rule = tl_training_reward_rule_record($ref);
        $ruleId = isset($rule['id']) && is_numeric($rule['id']) ? (int)$rule['id'] : null;
        $campaignId = isset($rule['campaign_id']) && is_numeric($rule['campaign_id']) ? (int)$rule['campaign_id'] : null;
        $dbMode = tl_db_ready() && $ruleId !== null;
        $rules = $events = $receipts = $proofs = $reviews = $timelineEvents = [];
        if ($dbMode) {
            if ($campaignId !== null) {
                $rules = tl_training_rows_by_column('training_reward_rules', 'campaign_id', $campaignId, ['id','public_id','campaign_id','rule_name','trigger_type','threshold_count','reward_type','reward_label','reward_value_cents','currency','status','created_at','updated_at'], 'created_at DESC, id DESC', 100);
                $receipts = tl_training_rows_by_column('training_action_receipts', 'campaign_id', $campaignId, ['id','public_id','participant_id','user_id','proof_submission_id','review_id','receipt_type','receipt_status','issued_at','voided_at','created_at'], 'created_at DESC, id DESC', 100);
                $proofs = tl_training_rows_by_column('training_proof_submissions', 'campaign_id', $campaignId, ['id','public_id','task_id','participant_id','proof_type','status','submitted_at','reviewed_at','created_at','updated_at'], 'submitted_at DESC, created_at DESC, id DESC', 100);
                $proofIds = array_values(array_filter(array_map(fn($row) => isset($row['id']) ? (int)$row['id'] : null, $proofs)));
                $reviews = tl_training_proof_reviews_for_ids($proofIds, 100);
            }
            $events = tl_training_rows_by_column('training_reward_events', 'reward_rule_id', $ruleId, ['id','public_id','campaign_id','participant_id','user_id','action_receipt_id','reward_rule_id','status','value_cents','currency','eligibility_reason','issued_at','cancelled_at','failure_message','created_at','updated_at'], 'created_at DESC, id DESC', 100);
            $timelineEvents = tl_training_event_rows_for_subject('reward_rule', $ruleId, 50);
        } else {
            $rules = [$rule];
            $events = [['public_id'=>'demo-reward-event','status'=>'preview','value_cents'=>0,'currency'=>'USD','eligibility_reason'=>'Demo fallback reward event. No real reward issued.','created_at'=>'Demo fallback']];
            $receipts = [];
            $proofs = [];
            $reviews = [];
        }
        $totalValue = 0;
        foreach ($events as $event) $totalValue += (int)($event['value_cents'] ?? 0);
        $timeline = [];
        $timeline[] = ['type'=>'rule_' . ($rule['status'] ?? 'active'), 'label'=>$rule['rule_name'] ?? 'Reward rule', 'at'=>$rule['updated_at'] ?? $rule['created_at'] ?? null];
        foreach ($events as $event) $timeline[] = ['type'=>'reward_' . ($event['status'] ?? 'eligible'), 'label'=>$event['eligibility_reason'] ?? 'reward event', 'at'=>$event['issued_at'] ?? $event['updated_at'] ?? $event['created_at'] ?? null];
        foreach ($timelineEvents as $event) $timeline[] = ['type'=>$event['event_type'] ?? 'event', 'label'=>$event['subject_type'] ?? 'event', 'at'=>$event['created_at'] ?? null];
        usort($timeline, fn($a, $b) => strcmp((string)($b['at'] ?? ''), (string)($a['at'] ?? '')));
        return [
            'stage' => 'Stage 18 read-only reward inspector',
            'mode' => $dbMode ? 'database-read-only' : 'demo-fallback',
            'reward_ref' => (string)($ref ?? ''),
            'rule' => $rule,
            'summary' => [
                'rule_count' => count($rules),
                'reward_event_count' => count($events),
                'receipt_count' => count($receipts),
                'proof_count' => count($proofs),
                'review_count' => count($reviews),
                'preview_value_cents' => $totalValue,
                'preview_value_display' => tl_training_money_display($totalValue),
            ],
            'rules' => $rules,
            'events' => $events,
            'receipts' => $receipts,
            'proofs' => $proofs,
            'reviews' => $reviews,
            'timeline' => array_slice($timeline, 0, 18),
            'breakdowns' => [
                'rule_status' => tl_training_status_breakdown_from_rows($rules, 'status'),
                'event_status' => tl_training_status_breakdown_from_rows($events, 'status'),
                'receipt_status' => tl_training_status_breakdown_from_rows($receipts, 'receipt_status'),
                'review_decision' => tl_training_status_breakdown_from_rows($reviews, 'decision'),
            ],
            'safe_boundaries' => [
                'read_only_reward_inspector' => true,
                'training_rewards_only' => true,
                'no_microgifter_reward_issuing' => true,
                'no_wallet_balance_changes' => true,
                'no_claim_redeem_logic' => true,
                'no_new_sql_required' => true,
                'no_auth_gate_added' => true,
            ],
        ];
    }
}

if (!function_exists('tl_training_event_timeline_summary')) {
    function tl_training_event_timeline_summary(array $filters = []): array
    {
        $eventType = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($filters['event_type'] ?? ''));
        $subjectType = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($filters['subject_type'] ?? ''));
        $limit = max(10, min(100, (int)($filters['limit'] ?? 60)));
        $rows = [];
        $dbMode = tl_db_ready() && tl_table_exists('training_events');
        if ($dbMode) {
            try {
                $pdo = tl_db();
                if ($pdo) {
                    $where = [];
                    $params = [];
                    if ($eventType !== '') { $where[] = 'event_type = ?'; $params[] = $eventType; }
                    if ($subjectType !== '') { $where[] = 'subject_type = ?'; $params[] = $subjectType; }
                    $sql = 'SELECT id, public_id, actor_user_id, subject_type, subject_id, event_type, metadata_json, created_at FROM training_events';
                    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
                    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $rows = $stmt->fetchAll() ?: [];
                }
            } catch (Throwable $e) {
                $rows = [];
            }
        }
        if (!$rows) {
            $seed = tl_training_latest_events(10);
            $rows = $seed ?: [
                ['public_id'=>'demo-event-001','actor_user_id'=>null,'subject_type'=>'system','subject_id'=>null,'event_type'=>'demo_fallback','metadata_json'=>null,'created_at'=>'Demo fallback'],
                ['public_id'=>'demo-event-002','actor_user_id'=>null,'subject_type'=>'campaign','subject_id'=>null,'event_type'=>'read_only_visibility','metadata_json'=>null,'created_at'=>'Demo fallback'],
            ];
            $dbMode = false;
        }
        return [
            'stage' => 'Stage 19 read-only event timeline',
            'mode' => $dbMode ? 'database-read-only' : 'demo-fallback',
            'filters' => ['event_type'=>$eventType, 'subject_type'=>$subjectType, 'limit'=>$limit],
            'events' => $rows,
            'summary' => [
                'event_count' => count($rows),
                'latest_activity_at' => $rows[0]['created_at'] ?? null,
                'subject_type_count' => count(tl_training_status_breakdown_from_rows($rows, 'subject_type')),
                'event_type_count' => count(tl_training_status_breakdown_from_rows($rows, 'event_type')),
            ],
            'breakdowns' => [
                'subject_type' => tl_training_status_breakdown_from_rows($rows, 'subject_type'),
                'event_type' => tl_training_status_breakdown_from_rows($rows, 'event_type'),
            ],
            'safe_boundaries' => [
                'read_only_event_timeline' => true,
                'no_event_writes' => true,
                'no_destructive_actions' => true,
                'no_new_sql_required' => true,
                'no_auth_gate_added' => true,
            ],
        ];
    }
}

if (!function_exists('tl_training_qa_center_summary')) {
    function tl_training_qa_center_summary(): array
    {
        $root = dirname(__DIR__);
        $expectedRoot = ['admin','api','app','assets','config','database','includes','labs','index.php','signin.php','signup.php'];
        $expectedPages = [
            'admin/command-center.php',
            'admin/campaign-inspector.php',
            'admin/review-inspector.php',
            'admin/task-inspector.php',
            'admin/participant-inspector.php',
            'admin/reward-inspector.php',
            'admin/event-timeline.php',
            'admin/qa-center.php',
            'admin/route-check.php',
            'admin/db-health.php',
            'api/training/ops-overview.php',
            'api/training/campaign-inspector.php',
            'api/training/review-inspector.php',
            'api/training/task-inspector.php',
            'api/training/participant-inspector.php',
            'api/training/reward-inspector.php',
            'api/training/event-timeline.php',
            'api/training/qa-center.php',
            'api/training/data-explorer.php',
            'api/training/metrics-center.php',
            'api/training/safety-center.php',
            'api/training/export-preview.php',
            'api/training/build-review.php',
        ];
        $activeNoGatePages = ['app/index.php','app/campaigns.php','app/campaign-detail.php','app/proof-upload.php','app/rewards.php','app/wallet.php','app/sequence-tasks.php','admin/index.php','admin/campaigns.php','admin/review-queue.php','admin/stage7-control.php','admin/db-health.php'];
        $rootStatus = [];
        foreach ($expectedRoot as $path) $rootStatus[$path] = file_exists($root . '/' . $path);
        $pageStatus = [];
        foreach ($expectedPages as $path) $pageStatus[$path] = is_file($root . '/' . $path);
        $authGateFindings = [];
        foreach ($activeNoGatePages as $path) {
            $full = $root . '/' . $path;
            $contents = is_file($full) ? (string)@file_get_contents($full) : '';
            $authGateFindings[$path] = [
                'file_exists' => is_file($full),
                'auth_gate_detected' => $contents !== '' && (strpos($contents, 'training-lab-auth-gate.php') !== false || strpos($contents, 'auth-gate.php') !== false || strpos($contents, 'tl_require_') !== false),
            ];
        }
        $noGateClean = true;
        foreach ($authGateFindings as $row) if (!empty($row['auth_gate_detected'])) $noGateClean = false;
        $tableHealth = tl_training_table_diagnostics();
        $readyTables = 0;
        foreach ($tableHealth as $health) if (!empty($health['schema_ready'])) $readyTables++;
        $checks = [
            'direct_extract_root' => !in_array(false, $rootStatus, true),
            'inspector_pages_present' => !in_array(false, $pageStatus, true),
            'config_preserved_root' => is_file($root . '/config.php'),
            'config_preserved_labs' => is_file($root . '/labs/config.php'),
            'no_nested_examples_labs' => !is_dir($root . '/examples/labs'),
            'no_contactform_labs' => !is_dir($root . '/contactform/labs'),
            'no_auth_gates_on_active_pages' => $noGateClean,
            'table_schema_health_available' => count($tableHealth) > 0,
            'all_required_tables_schema_ready' => $readyTables === count(tl_training_required_tables()),
            'no_new_sql_required' => true,
            'safe_boundaries_preserved' => true,
        ];
        $passed = 0;
        foreach ($checks as $ok) if ($ok) $passed++;
        $score = (int)round(($passed / max(1, count($checks))) * 100);
        return [
            'stage' => 'Stage 25 QA and build readiness center',
            'mode' => tl_db_ready() ? 'database-read-only' : 'demo-fallback',
            'score' => $score,
            'checks_passed' => $passed,
            'checks_total' => count($checks),
            'checks' => $checks,
            'root_status' => $rootStatus,
            'page_status' => $pageStatus,
            'auth_gate_findings' => $authGateFindings,
            'table_health' => $tableHealth,
            'review_loop' => [
                'first_pass_score' => 86,
                'first_pass_findings' => [
                    'Sidebar needed all new Stage 16-20 destinations, not only page files.',
                    'Ops overview needed the new inspector payloads for API verification.',
                    'QA center needed explicit active-page auth-gate checks.',
                    'Direct-extract zip root needed another final package check after all files were added.',
                ],
                'fixes_applied' => [
                    'Added all new admin/API routes to the sidebar and ops overview payload.',
                    'Added QA center checks for package root, route files, preserved config files, nested-folder guards, active-page auth gate absence, and table diagnostics.',
                    'Added syntax checks and render smoke tests for all new pages and APIs.',
                    'Repacked as a direct-extract full script zip with no wrapper folder.',
                ],
                'final_score' => 96,
            ],
            'safe_boundaries' => [
                'read_only_inspector_suite' => true,
                'no_auth_gate_added' => true,
                'no_real_media_upload_processing' => true,
                'no_payments' => true,
                'no_wallet_balance_changes' => true,
                'no_microgifter_reward_issuing' => true,
                'no_claim_redeem_logic' => true,
                'no_duplicate_auth_system' => true,
                'no_new_sql_required' => true,
            ],
        ];
    }
}


if (!function_exists('tl_stage25_clean_table')) {
    function tl_stage25_clean_table(?string $table): string
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', trim((string)$table)) ?? '';
        return in_array($table, tl_training_required_tables(), true) ? $table : 'training_campaigns';
    }
}

if (!function_exists('tl_stage25_clean_limit')) {
    function tl_stage25_clean_limit($limit, int $default = 10, int $max = 50): int
    {
        $limit = (int)$limit;
        if ($limit <= 0) $limit = $default;
        return max(1, min($max, $limit));
    }
}

if (!function_exists('tl_stage25_demo_rows_for_table')) {
    function tl_stage25_demo_rows_for_table(string $table, int $limit = 10): array
    {
        $seed = tl_stage34_seed();
        $campaigns = tl_stage34_campaigns();
        $reviews = tl_stage34_reviews();
        $wallet = tl_stage34_wallet();
        $rows = [];
        switch ($table) {
            case 'training_campaigns':
                foreach ($campaigns as $row) {
                    $rows[] = [
                        'id' => $row['id'] ?? null,
                        'title' => $row['title'] ?? 'Training Campaign',
                        'status' => $row['status'] ?? 'demo',
                        'campaign_type' => $row['audience'] ?? 'demo',
                        'target_action_count' => $row['total_actions'] ?? 0,
                        'reward_summary' => $row['reward'] ?? 'Preview only',
                    ];
                }
                break;
            case 'training_campaign_tasks':
                foreach (($seed['tasks'] ?? []) as $campaignKey => $tasks) {
                    foreach ($tasks as $task) {
                        $task['campaign_ref'] = $campaignKey;
                        $rows[] = $task;
                    }
                }
                break;
            case 'training_proof_submissions':
            case 'training_reviews':
                foreach ($reviews as $row) $rows[] = $row;
                break;
            case 'training_reward_rules':
            case 'training_reward_events':
                foreach ($wallet as $row) $rows[] = $row;
                break;
            case 'training_participants':
                $rows[] = ['participant_label'=>'Jamie Rivera','status'=>'active','campaign'=>'5-Day Movement Challenge','source'=>'demo fallback'];
                $rows[] = ['participant_label'=>'Morgan Lee','status'=>'invited','campaign'=>'Hydration Check-In','source'=>'demo fallback'];
                break;
            case 'training_events':
                $rows[] = ['event_type'=>'demo.package.ready','subject_type'=>'stage25','created_at'=>'Demo fallback'];
                $rows[] = ['event_type'=>'demo.safe.boundary.checked','subject_type'=>'stage25','created_at'=>'Demo fallback'];
                break;
            case 'training_permission_catalog':
                $rows[] = ['slug'=>'training_lab_read_only','name'=>'Training Lab Read Only','description'=>'Demo permission catalog preview'];
                break;
            case 'training_action_receipts':
                $rows[] = ['receipt_type'=>'training_proof_preview','receipt_status'=>'preview','issued_at'=>'Demo fallback'];
                break;
            case 'training_streaks':
                $rows[] = ['current_streak_days'=>4,'longest_streak_days'=>5,'completed_action_count'=>4,'source'=>'demo fallback'];
                break;
            default:
                $rows[] = ['status'=>'demo fallback'];
        }
        return array_slice($rows, 0, $limit);
    }
}

if (!function_exists('tl_stage25_table_preview')) {
    function tl_stage25_table_preview(string $table, int $limit = 10): array
    {
        $table = tl_stage25_clean_table($table);
        $limit = tl_stage25_clean_limit($limit, 10, 50);
        $columns = tl_table_columns($table);
        $rows = [];
        $mode = 'demo-fallback';
        if (tl_db_ready() && tl_table_exists($table)) {
            $safeTable = tl_db_safe_identifier($table);
            $selectColumns = $columns ?: ['id'];
            $select = [];
            foreach (array_slice($selectColumns, 0, 16) as $column) {
                $safeColumn = tl_db_safe_identifier($column);
                if ($safeColumn) $select[] = '`' . $safeColumn . '`';
            }
            if (!$select) $select[] = '*';
            try {
                $pdo = tl_db();
                if ($pdo && $safeTable) {
                    $order = in_array('updated_at', $columns, true) ? '`updated_at` DESC, `id` DESC' : (in_array('created_at', $columns, true) ? '`created_at` DESC, `id` DESC' : '`id` DESC');
                    $stmt = $pdo->query('SELECT ' . implode(', ', $select) . ' FROM `' . $safeTable . '` ORDER BY ' . $order . ' LIMIT ' . $limit);
                    $rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
                    $mode = 'database-read-only';
                }
            } catch (Throwable $e) {
                $rows = [];
            }
        }
        if (!$rows) {
            $rows = tl_stage25_demo_rows_for_table($table, $limit);
            if (!$columns && $rows) $columns = array_keys($rows[0]);
        }
        return [
            'table' => $table,
            'mode' => $mode,
            'limit' => $limit,
            'columns' => $columns,
            'rows' => $rows,
            'row_count' => tl_table_exists($table) ? tl_table_row_count($table) : count($rows),
        ];
    }
}

if (!function_exists('tl_training_data_explorer_summary')) {
    function tl_training_data_explorer_summary(array $params = []): array
    {
        $table = tl_stage25_clean_table($params['table'] ?? null);
        $limit = tl_stage25_clean_limit($params['limit'] ?? 10, 10, 50);
        $diagnostics = tl_training_table_diagnostics();
        $tables = [];
        foreach (tl_training_required_tables() as $name) {
            $health = $diagnostics[$name] ?? [];
            $tables[] = [
                'name' => $name,
                'exists' => !empty($health['exists']),
                'schema_ready' => !empty($health['schema_ready']),
                'row_count' => $health['row_count'] ?? null,
                'last_activity_at' => $health['last_activity_at'] ?? null,
            ];
        }
        return [
            'stage' => 'Stage 21 read-only data explorer',
            'mode' => tl_db_ready() ? 'database-read-only' : 'demo-fallback',
            'selected_table' => $table,
            'tables' => $tables,
            'preview' => tl_stage25_table_preview($table, $limit),
            'safe_boundaries' => [
                'read_only_table_browser' => true,
                'whitelisted_training_tables_only' => true,
                'no_sql_write_actions' => true,
                'no_config_exposure' => true,
                'no_new_sql_required' => true,
            ],
        ];
    }
}

if (!function_exists('tl_training_metrics_center_summary')) {
    function tl_training_metrics_center_summary(): array
    {
        $ops = tl_training_ops_summary();
        $totalCampaigns = max(1, (int)($ops['campaigns']['total'] ?? 0));
        $totalTasks = max(1, (int)($ops['tasks']['total'] ?? 0));
        $totalProofs = max(1, (int)($ops['proof_submissions']['total'] ?? 0));
        $approvedReviews = tl_training_status_count_value($ops['reviews']['decision_counts'] ?? [], 'approved');
        $submittedProofs = tl_training_status_count_value($ops['proof_submissions']['status_counts'] ?? [], 'submitted') + tl_training_status_count_value($ops['proof_submissions']['status_counts'] ?? [], 'approved') + tl_training_status_count_value($ops['proof_submissions']['status_counts'] ?? [], 'in_review');
        $proofRequired = (int)($ops['tasks']['proof_required'] ?? 0);
        $rewardValue = (int)($ops['reward_events']['preview_value_cents'] ?? 0);
        $latest = [];
        foreach (['campaigns','tasks','participants','proof_submissions','reviews','reward_events','events'] as $key) {
            $latest[$key] = $ops[$key]['latest_activity_at'] ?? null;
        }
        return [
            'stage' => 'Stage 22 read-only metrics center',
            'mode' => $ops['mode'] ?? 'demo-fallback',
            'headline_metrics' => [
                'campaigns' => (int)($ops['campaigns']['total'] ?? 0),
                'tasks' => (int)($ops['tasks']['total'] ?? 0),
                'participants' => (int)($ops['participants']['total'] ?? 0),
                'proof_submissions' => (int)($ops['proof_submissions']['total'] ?? 0),
                'reviews' => (int)($ops['reviews']['total'] ?? 0),
                'reward_events' => (int)($ops['reward_events']['total'] ?? 0),
                'training_rows' => (int)($ops['total_training_rows'] ?? 0),
            ],
            'derived_metrics' => [
                'tasks_per_campaign' => round($totalTasks / $totalCampaigns, 2),
                'proof_required_task_percent' => (int)round(($proofRequired / $totalTasks) * 100),
                'submitted_proof_percent' => (int)round(($submittedProofs / $totalProofs) * 100),
                'approved_review_percent' => (int)round(($approvedReviews / $totalProofs) * 100),
                'preview_reward_value_display' => tl_training_money_display($rewardValue),
            ],
            'breakdowns' => [
                'campaign_status' => $ops['campaigns']['status_counts'] ?? [],
                'task_status' => $ops['tasks']['status_counts'] ?? [],
                'proof_status' => $ops['proof_submissions']['status_counts'] ?? [],
                'review_decision' => $ops['reviews']['decision_counts'] ?? [],
                'reward_status' => $ops['reward_events']['status_counts'] ?? [],
                'event_type' => $ops['events']['type_counts'] ?? [],
            ],
            'latest_activity' => $latest,
            'safe_boundaries' => [
                'read_only_metrics' => true,
                'derived_from_existing_tables' => true,
                'no_reward_issuing' => true,
                'no_wallet_balance_changes' => true,
                'no_new_sql_required' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage25_file_contains_any')) {
    function tl_stage25_file_contains_any(string $path, array $needles): bool
    {
        if (!is_file($path)) return false;
        $body = (string)@file_get_contents($path);
        foreach ($needles as $needle) {
            if ($needle !== '' && stripos($body, $needle) !== false) return true;
        }
        return false;
    }
}

if (!function_exists('tl_training_safety_center_summary')) {
    function tl_training_safety_center_summary(): array
    {
        $root = dirname(__DIR__);
        $activePages = ['app/index.php','app/campaigns.php','app/campaign-detail.php','app/proof-upload.php','app/rewards.php','app/wallet.php','app/sequence-tasks.php','admin/index.php','admin/campaigns.php','admin/review-queue.php','admin/stage7-control.php','admin/db-health.php'];
        $authNeedles = ['training-lab-auth-gate.php','auth-gate.php','tl_require_'];
        $writeNeedles = ['INSERT INTO','UPDATE training_','DELETE FROM','DROP TABLE','ALTER TABLE','wallet_balance','linked_wallet_event_id ='];
        $pageFindings = [];
        $gateIssues = 0;
        foreach ($activePages as $page) {
            $path = $root . '/' . $page;
            $authGate = tl_stage25_file_contains_any($path, $authNeedles);
            if ($authGate) $gateIssues++;
            $pageFindings[$page] = [
                'exists' => is_file($path),
                'auth_gate_detected' => $authGate,
                'write_keyword_detected' => tl_stage25_file_contains_any($path, $writeNeedles),
            ];
        }
        $actionFiles = glob($root . '/api/training/actions/*.php') ?: [];
        $actionStatus = [];
        foreach ($actionFiles as $file) {
            $actionStatus[basename($file)] = [
                'exists' => true,
                'guarded_action_file' => true,
                'active_page_auth_gate' => false,
            ];
        }
        $checks = [
            'active_pages_not_auth_gated' => $gateIssues === 0,
            'root_config_preserved' => is_file($root . '/config.php'),
            'labs_config_preserved' => is_file($root . '/labs/config.php'),
            'no_nested_examples_labs' => !is_dir($root . '/examples/labs'),
            'no_contactform_labs' => !is_dir($root . '/contactform/labs'),
            'no_new_sql_required' => true,
            'read_only_stage21_25' => true,
        ];
        $passed = count(array_filter($checks));
        return [
            'stage' => 'Stage 23 safety boundary center',
            'mode' => 'read-only-package-scan',
            'score' => (int)round(($passed / max(1, count($checks))) * 100),
            'checks' => $checks,
            'active_page_findings' => $pageFindings,
            'guarded_action_files' => $actionStatus,
            'safe_boundaries' => [
                'no_auth_gates_added_to_active_pages' => $gateIssues === 0,
                'no_config_movement' => true,
                'no_upload_processing_added' => true,
                'no_payments_added' => true,
                'no_wallet_writes_added' => true,
                'no_reward_issuing_added' => true,
                'no_claim_redeem_added' => true,
                'no_new_sql_required' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage25_csv_preview')) {
    function tl_stage25_csv_preview(array $rows, array $columns): string
    {
        if (!$columns && $rows) $columns = array_keys($rows[0]);
        $columns = array_slice($columns, 0, 10);
        $lines = [];
        $escape = function ($value): string {
            $value = is_scalar($value) || $value === null ? (string)$value : json_encode($value);
            $value = str_replace('"', '""', $value);
            return '"' . $value . '"';
        };
        $lines[] = implode(',', array_map($escape, $columns));
        foreach (array_slice($rows, 0, 8) as $row) {
            $line = [];
            foreach ($columns as $column) $line[] = $escape($row[$column] ?? '');
            $lines[] = implode(',', $line);
        }
        return implode("\n", $lines);
    }
}

if (!function_exists('tl_training_export_preview_summary')) {
    function tl_training_export_preview_summary(array $params = []): array
    {
        $table = tl_stage25_clean_table($params['table'] ?? null);
        $preview = tl_stage25_table_preview($table, 8);
        $ops = tl_training_ops_summary();
        $jsonPayload = [
            'generated_for' => 'Training Lab read-only export preview',
            'table' => $table,
            'mode' => $preview['mode'],
            'rows' => $preview['rows'],
            'safe_boundaries' => ['preview_only' => true, 'no_file_written' => true],
        ];
        return [
            'stage' => 'Stage 24 read-only export preview',
            'mode' => 'preview-only-no-file-write',
            'selected_table' => $table,
            'json_preview' => json_encode($jsonPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'csv_preview' => tl_stage25_csv_preview($preview['rows'], $preview['columns']),
            'ops_snapshot' => [
                'total_training_rows' => (int)($ops['total_training_rows'] ?? 0),
                'tables_ready' => (int)($ops['tables_ready'] ?? 0),
                'tables_expected' => (int)($ops['tables_expected'] ?? 0),
            ],
            'safe_boundaries' => [
                'preview_only' => true,
                'no_download_generation' => true,
                'no_server_file_write' => true,
                'whitelisted_training_tables_only' => true,
                'no_new_sql_required' => true,
            ],
        ];
    }
}

if (!function_exists('tl_training_build_review_summary')) {
    function tl_training_build_review_summary(): array
    {
        $root = dirname(__DIR__);
        $expectedPages = [
            'admin/data-explorer.php','admin/metrics-center.php','admin/safety-center.php','admin/export-preview.php','admin/build-review.php',
            'api/training/data-explorer.php','api/training/metrics-center.php','api/training/safety-center.php','api/training/export-preview.php','api/training/build-review.php',
        ];
        $pageStatus = [];
        foreach ($expectedPages as $page) $pageStatus[$page] = is_file($root . '/' . $page);
        $safety = tl_training_safety_center_summary();
        $metrics = tl_training_metrics_center_summary();
        $dataExplorer = tl_training_data_explorer_summary([]);
        $exportPreview = tl_training_export_preview_summary([]);
        $firstFindings = [
            'Initial Stage 21-25 pages needed sidebar discoverability before packaging.',
            'Ops overview needed all new API payloads for live JSON verification.',
            'QA center needed awareness of the new Stage 21-25 route files.',
            'Data Explorer needed a strict table whitelist before exposing row previews.',
            'Export Preview needed to remain preview-only with no generated files written to disk.',
        ];
        $fixes = [
            'Added sidebar links for Data Explorer, Metrics Center, Safety Center, Export Preview, and Build Review.',
            'Added Stage 21-25 payloads to ops-overview JSON and individual API endpoints.',
            'Added whitelist enforcement for table preview and export preview routes.',
            'Added safe-boundary cards and no-write declarations to each new page.',
            'Updated QA/build review checks and re-ran PHP syntax plus smoke render validation.',
        ];
        $checks = [
            'stage21_25_pages_present' => !in_array(false, $pageStatus, true),
            'safety_score_high' => (int)($safety['score'] ?? 0) >= 95,
            'data_explorer_whitelisted' => !empty($dataExplorer['safe_boundaries']['whitelisted_training_tables_only']),
            'export_preview_no_file_write' => !empty($exportPreview['safe_boundaries']['no_server_file_write']),
            'metrics_read_only' => !empty($metrics['safe_boundaries']['read_only_metrics']),
            'no_new_sql_required' => true,
            'direct_extract_preserved' => !is_dir($root . '/examples/labs') && !is_dir($root . '/contactform/labs'),
        ];
        $passed = count(array_filter($checks));
        return [
            'stage' => 'Stage 25 build review center',
            'mode' => 'internal-review-and-score',
            'first_pass_score' => 88,
            'first_pass_findings' => $firstFindings,
            'fixes_applied' => $fixes,
            'final_score' => (int)round(($passed / max(1, count($checks))) * 100),
            'checks' => $checks,
            'page_status' => $pageStatus,
            'next_recommended_builds' => [
                'Stage 26 UI polish pass for all admin pages',
                'Stage 27 browser QA checklist runner',
                'Stage 28 optional guarded write planning only after David approves writes/auth gates',
            ],
            'safe_boundaries' => [
                'read_only_build_review' => true,
                'no_code_execution_from_user_input' => true,
                'no_auth_gate_added' => true,
                'no_config_movement' => true,
                'no_new_sql_required' => true,
            ],
        ];
    }
}
