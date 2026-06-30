<?php
/**
 * Functional Training Lab app service.
 *
 * Standalone-script boundary: these helpers write only to Training Lab tables.
 * They do not process real uploads, payments, or wallet balances.
 * Microgifter reward issuing and claim linking are adapter/developer-key gated
 * through the Training Lab rewards bridge.
 */
require_once __DIR__ . '/training-lab-actions.php';
require_once __DIR__ . '/training-lab-account-bridge.php';
require_once __DIR__ . '/training-lab-microgifter-rewards.php';
require_once __DIR__ . '/training-lab-product-engine.php';
require_once __DIR__ . '/training-lab-stage240-app-builds.php';
require_once __DIR__ . '/training-lab-stage280-app-builds.php';
require_once __DIR__ . '/training-lab-design-assets.php';
require_once __DIR__ . '/training-lab-stage520-core-flow.php';
require_once __DIR__ . '/training-lab-stage560-operational-run.php';
require_once __DIR__ . '/training-lab-stage600-workflow-control.php';
require_once __DIR__ . '/training-lab-stage640-data-quality.php';
require_once __DIR__ . '/training-lab-stage680-communication-rhythm.php';
require_once __DIR__ . '/training-lab-stage720-content-experience.php';
require_once __DIR__ . '/training-lab-stage760-merchant-commerce.php';
require_once __DIR__ . '/training-lab-stage800-microgifter-import.php';
require_once __DIR__ . '/training-lab-stage840-user-awards.php';
require_once __DIR__ . '/training-lab-stage880-adapter-sync.php';

if (!function_exists('tl_app_required_tables_status')) {
    function tl_app_required_tables_status(): array
    {
        $rows = [];
        foreach (tl_training_required_tables() as $table) {
            $rows[$table] = tl_table_exists($table);
        }
        return $rows;
    }
}

if (!function_exists('tl_app_count')) {
    function tl_app_count(string $table, string $where = '', array $params = []): int
    {
        $pdo = tl_db();
        if (!$pdo || !in_array($table, tl_training_required_tables(), true) || !tl_table_exists($table)) return 0;
        try {
            $safe = tl_db_safe_identifier($table);
            if (!$safe) return 0;
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $safe . '`' . ($where !== '' ? ' WHERE ' . $where : ''));
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) { return 0; }
    }
}

if (!function_exists('tl_app_campaign_options')) {
    function tl_app_campaign_options(): array
    {
        $pdo = tl_db();
        if ($pdo && tl_table_exists('training_campaigns')) {
            try {
                $rows = $pdo->query("SELECT id, public_id, slug, title, status, visibility, target_action_count, updated_at FROM training_campaigns ORDER BY updated_at DESC, id DESC LIMIT 100")->fetchAll();
                if ($rows) return array_map(function ($row) {
                    return [
                        'id' => (int)$row['id'],
                        'public_id' => (string)$row['public_id'],
                        'slug' => (string)$row['slug'],
                        'title' => (string)$row['title'],
                        'status' => (string)$row['status'],
                        'visibility' => (string)$row['visibility'],
                        'target_action_count' => (int)$row['target_action_count'],
                        'updated_at' => (string)$row['updated_at'],
                        'ref' => (string)($row['slug'] ?: $row['public_id']),
                    ];
                }, $rows);
            } catch (Throwable $e) {}
        }
        return array_map(function ($campaign) {
            return [
                'id' => (int)($campaign['db_id'] ?? 0),
                'public_id' => (string)($campaign['public_id'] ?? $campaign['id']),
                'slug' => (string)$campaign['id'],
                'title' => (string)$campaign['title'],
                'status' => strtolower((string)$campaign['status']),
                'visibility' => 'demo',
                'target_action_count' => (int)($campaign['total_actions'] ?? 5),
                'updated_at' => '',
                'ref' => (string)$campaign['id'],
            ];
        }, tl_stage34_campaigns());
    }
}

if (!function_exists('tl_app_default_campaign_ref')) {
    function tl_app_default_campaign_ref(): string
    {
        $options = tl_app_campaign_options();
        return (string)($options[0]['ref'] ?? 'movement-5');
    }
}

if (!function_exists('tl_app_pending_proofs')) {
    function tl_app_pending_proofs(int $limit = 25): array
    {
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_proof_submissions')) return [];
        try {
            $sql = "SELECT p.*, c.title AS campaign_title, c.slug AS campaign_slug, t.title AS task_title,
                       COALESCE(tp.participant_label, CONCAT('User #', p.submitted_by_user_id)) AS participant_label,
                       (SELECT r.decision FROM training_reviews r WHERE r.proof_submission_id = p.id ORDER BY r.created_at DESC LIMIT 1) AS latest_decision
                    FROM training_proof_submissions p
                    LEFT JOIN training_campaigns c ON c.id = p.campaign_id
                    LEFT JOIN training_campaign_tasks t ON t.id = p.task_id
                    LEFT JOIN training_participants tp ON tp.id = p.participant_id
                    WHERE p.status IN ('submitted','in_review')
                    ORDER BY p.submitted_at ASC
                    LIMIT " . max(1, min(100, $limit));
            return $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_app_recent_proofs')) {
    function tl_app_recent_proofs(int $limit = 20): array
    {
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_proof_submissions')) return [];
        try {
            $sql = "SELECT p.*, c.title AS campaign_title, t.title AS task_title,
                       COALESCE(tp.participant_label, CONCAT('User #', p.submitted_by_user_id)) AS participant_label,
                       (SELECT r.decision FROM training_reviews r WHERE r.proof_submission_id = p.id ORDER BY r.created_at DESC LIMIT 1) AS latest_decision
                    FROM training_proof_submissions p
                    LEFT JOIN training_campaigns c ON c.id = p.campaign_id
                    LEFT JOIN training_campaign_tasks t ON t.id = p.task_id
                    LEFT JOIN training_participants tp ON tp.id = p.participant_id
                    ORDER BY p.updated_at DESC, p.submitted_at DESC
                    LIMIT " . max(1, min(100, $limit));
            return $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_app_recent_reviews')) {
    function tl_app_recent_reviews(int $limit = 20): array
    {
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_reviews')) return [];
        try {
            $sql = "SELECT r.*, p.public_id AS proof_public_id, p.status AS proof_status, c.title AS campaign_title
                    FROM training_reviews r
                    LEFT JOIN training_proof_submissions p ON p.id = r.proof_submission_id
                    LEFT JOIN training_campaigns c ON c.id = p.campaign_id
                    ORDER BY r.created_at DESC LIMIT " . max(1, min(100, $limit));
            return $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_app_recent_receipts')) {
    function tl_app_recent_receipts(int $limit = 20): array
    {
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_action_receipts')) return [];
        try {
            $sql = "SELECT ar.*, c.title AS campaign_title, COALESCE(tp.participant_label, CONCAT('User #', ar.user_id)) AS participant_label
                    FROM training_action_receipts ar
                    LEFT JOIN training_campaigns c ON c.id = ar.campaign_id
                    LEFT JOIN training_participants tp ON tp.id = ar.participant_id
                    ORDER BY ar.created_at DESC LIMIT " . max(1, min(100, $limit));
            return $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_app_recent_rewards')) {
    function tl_app_recent_rewards(int $limit = 20): array
    {
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_reward_events')) return [];
        try {
            $sql = "SELECT re.*, c.title AS campaign_title, rr.reward_label, COALESCE(tp.participant_label, CONCAT('User #', re.user_id)) AS participant_label
                    FROM training_reward_events re
                    LEFT JOIN training_campaigns c ON c.id = re.campaign_id
                    LEFT JOIN training_reward_rules rr ON rr.id = re.reward_rule_id
                    LEFT JOIN training_participants tp ON tp.id = re.participant_id
                    ORDER BY re.created_at DESC LIMIT " . max(1, min(100, $limit));
            return $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_app_recent_events')) {
    function tl_app_recent_events(int $limit = 30): array
    {
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_events')) return [];
        try {
            return $pdo->query("SELECT * FROM training_events ORDER BY created_at DESC LIMIT " . max(1, min(100, $limit)))->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_app_participant_progress')) {
    function tl_app_participant_progress(int $limit = 20): array
    {
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_participants')) return [];
        try {
            $sql = "SELECT tp.*, c.title AS campaign_title,
                       COALESCE(s.completed_action_count, 0) AS completed_action_count,
                       COALESCE(s.current_streak_days, 0) AS current_streak_days,
                       COALESCE(s.longest_streak_days, 0) AS longest_streak_days,
                       c.target_action_count
                    FROM training_participants tp
                    LEFT JOIN training_campaigns c ON c.id = tp.campaign_id
                    LEFT JOIN training_streaks s ON s.participant_id = tp.id
                    ORDER BY tp.updated_at DESC LIMIT " . max(1, min(100, $limit));
            return $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_app_flow_summary')) {
    function tl_app_flow_summary(): array
    {
        $tables = tl_app_required_tables_status();
        $readyTables = count(array_filter($tables));
        $requiredTables = count($tables);
        return [
            'stage' => 'Stage 30 functional standalone app block',
            'mode' => tl_db_ready() ? 'database' : 'demo-fallback',
            'db_configured' => tl_db_config_ready(),
            'connected' => tl_db_ready(),
            'table_health' => [
                'ready' => $readyTables,
                'required' => $requiredTables,
                'all_present' => $readyTables === $requiredTables,
                'tables' => $tables,
            ],
            'counts' => [
                'campaigns' => tl_app_count('training_campaigns'),
                'tasks' => tl_app_count('training_campaign_tasks'),
                'participants' => tl_app_count('training_participants'),
                'proofs' => tl_app_count('training_proof_submissions'),
                'pending_proofs' => tl_app_count('training_proof_submissions', "status IN ('submitted','in_review')"),
                'approved_proofs' => tl_app_count('training_proof_submissions', "status = 'approved'"),
                'reviews' => tl_app_count('training_reviews'),
                'receipts' => tl_app_count('training_action_receipts'),
                'reward_events' => tl_app_count('training_reward_events'),
                'events' => tl_app_count('training_events'),
            ],
            'campaigns' => tl_app_campaign_options(),
            'pending_proofs' => tl_app_pending_proofs(20),
            'recent_proofs' => tl_app_recent_proofs(20),
            'recent_reviews' => tl_app_recent_reviews(20),
            'recent_receipts' => tl_app_recent_receipts(20),
            'recent_rewards' => tl_app_recent_rewards(20),
            'recent_events' => tl_app_recent_events(30),
            'participant_progress' => tl_app_participant_progress(20),
            'auth_bridge' => function_exists('tl_account_bridge_current_context') ? tl_account_bridge_current_context() : [],
            'stage130_backend' => function_exists('tl_stage130_backend_summary') ? tl_stage130_backend_summary() : [],
            'microgifter_rewards_bridge' => function_exists('tl_mg_reward_bridge_summary') ? tl_mg_reward_bridge_summary() : [],
            'stage160_reward_lifecycle' => function_exists('tl_mg_stage160_bridge_summary') ? tl_mg_stage160_bridge_summary() : [],
            'stage280_stacked_app_builds' => function_exists('tl_stage280_summary') ? tl_stage280_summary() : [],
            'stage300_design_app_integration' => function_exists('tl_stage300_design_summary') ? tl_stage300_design_summary() : [],
            'safe_boundaries' => [
                'standalone_training_script' => true,
                'writes_only_training_tables' => true,
                'no_real_upload_processing' => true,
                'no_payments' => true,
                'no_wallet_balance_changes' => true,
                'microgifter_reward_issuing_requires_adapter_or_developer_key' => true,
                'training_reward_claim_tracking_active' => true,
                'auth_bridge_scaffold_active' => true,
                'auth_gate_enforcement_configurable' => true,
            ],
        ];
    }
}

if (!function_exists('tl_training_handle_app_action')) {
    function tl_training_handle_app_action(array $input): array
    {
        $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['training_action'] ?? $input['action'] ?? ''));
        if ($action === '') throw new RuntimeException('Missing training action.');
        if ((string)($input['confirm_training_action'] ?? '') !== '1') {
            throw new RuntimeException('Training action confirmation missing.');
        }
        if (function_exists('tl_account_bridge_apply_actor_to_input')) {
            $input = tl_account_bridge_apply_actor_to_input($input);
        }
        if (function_exists('tl_account_bridge_authorize_action')) {
            tl_account_bridge_authorize_action($action);
        }

        if ($action === 'update_campaign_status') {
            return ['action' => $action, 'label' => 'Update campaign status', 'result' => tl_stage130_update_campaign_status($input)];
        }
        if ($action === 'reconcile_participant_progress') {
            return ['action' => $action, 'label' => 'Reconcile participant progress', 'result' => tl_stage130_reconcile_participant_progress($input)];
        }
        if ($action === 'backend_health_snapshot') {
            return ['action' => $action, 'label' => 'Create backend health snapshot', 'result' => tl_stage130_backend_health_snapshot($input)];
        }


        if ($action === 'save_training_note') {
            return ['action' => $action, 'label' => 'Save training note', 'result' => tl_stage200_save_training_note($input)];
        }
        if ($action === 'save_campaign_checkpoint') {
            return ['action' => $action, 'label' => 'Save campaign checkpoint', 'result' => tl_stage200_save_campaign_checkpoint($input)];
        }
        if ($action === 'mark_participant_focus') {
            return ['action' => $action, 'label' => 'Update participant focus', 'result' => tl_stage200_mark_participant_focus($input)];
        }
        if ($action === 'create_workflow_snapshot') {
            return ['action' => $action, 'label' => 'Create workflow snapshot', 'result' => tl_stage200_create_workflow_snapshot($input)];
        }
        if ($action === 'run_core_workflow_qa') {
            return ['action' => $action, 'label' => 'Run core workflow QA', 'result' => tl_stage200_run_core_qa($input)];
        }


        if ($action === 'update_campaign_plan') {
            return ['action' => $action, 'label' => 'Update campaign plan', 'result' => tl_stage240_update_campaign_plan($input)];
        }
        if ($action === 'add_campaign_task') {
            return ['action' => $action, 'label' => 'Add campaign task', 'result' => tl_stage240_add_campaign_task($input)];
        }
        if ($action === 'update_task_status') {
            return ['action' => $action, 'label' => 'Update task status', 'result' => tl_stage240_update_task_status($input)];
        }
        if ($action === 'save_participant_checkpoint') {
            return ['action' => $action, 'label' => 'Save participant checkpoint', 'result' => tl_stage240_save_participant_checkpoint($input)];
        }
        if ($action === 'create_review_sla_snapshot') {
            return ['action' => $action, 'label' => 'Create review SLA snapshot', 'result' => tl_stage240_create_review_sla_snapshot($input)];
        }
        if ($action === 'create_fulfillment_snapshot') {
            return ['action' => $action, 'label' => 'Create reward fulfillment snapshot', 'result' => tl_stage240_create_fulfillment_snapshot($input)];
        }
        if ($action === 'run_product_self_test') {
            return ['action' => $action, 'label' => 'Run product self-test', 'result' => tl_stage240_run_product_self_test($input + ['log_event' => 1])];
        }


        if ($action === 'create_account_link_snapshot') {
            return ['action' => $action, 'label' => 'Create account link snapshot', 'result' => tl_stage280_create_account_link_snapshot($input)];
        }
        if ($action === 'save_proof_quality_note') {
            return ['action' => $action, 'label' => 'Save proof quality note', 'result' => tl_stage280_save_proof_quality_note($input)];
        }
        if ($action === 'save_reviewer_quality_snapshot') {
            return ['action' => $action, 'label' => 'Save reviewer quality snapshot', 'result' => tl_stage280_save_reviewer_quality_snapshot($input)];
        }
        if ($action === 'run_reward_assurance') {
            return ['action' => $action, 'label' => 'Run reward assurance', 'result' => tl_stage280_run_reward_assurance($input)];
        }
        if ($action === 'run_release_candidate_qa') {
            return ['action' => $action, 'label' => 'Run release candidate QA', 'result' => tl_stage280_run_release_candidate($input)];
        }

        if ($action === 'seed_demo') {
            return ['action' => $action, 'label' => 'Seed demo campaigns', 'result' => tl_seed_demo_campaigns($input)];
        }
        if ($action === 'create_campaign') {
            return ['action' => $action, 'label' => 'Create campaign', 'result' => tl_create_campaign($input)];
        }
        if ($action === 'join_campaign') {
            return ['action' => $action, 'label' => 'Join campaign', 'result' => tl_join_campaign($input)];
        }
        if ($action === 'submit_proof') {
            return ['action' => $action, 'label' => 'Submit proof', 'result' => tl_submit_proof($input)];
        }
        if ($action === 'review_proof') {
            return ['action' => $action, 'label' => 'Review proof', 'result' => tl_review_proof($input)];
        }
        if ($action === 'create_campaign_blueprint') {
            return ['action' => $action, 'label' => 'Create campaign blueprint', 'result' => tl_create_campaign_blueprint($input)];
        }
        if ($action === 'complete_task') {
            return ['action' => $action, 'label' => 'Complete training task', 'result' => tl_complete_task($input)];
        }
        if ($action === 'queue_reward_event') {
            return ['action' => $action, 'label' => 'Queue simulated reward event', 'result' => tl_queue_reward_event($input)];
        }

        if ($action === 'offer_microgifter_reward') {
            return ['action' => $action, 'label' => 'Offer Microgifter reward for campaign', 'result' => tl_mg_offer_reward_for_campaign($input)];
        }
        if ($action === 'claim_training_reward') {
            return ['action' => $action, 'label' => 'Claim Training Lab reward', 'result' => tl_mg_claim_training_reward($input)];
        }

        if ($action === 'retry_microgifter_reward_issue') {
            return ['action' => $action, 'label' => 'Retry Microgifter reward issue', 'result' => tl_mg_stage160_retry_microgifter_issue($input)];
        }
        if ($action === 'mark_reward_manual_issued') {
            return ['action' => $action, 'label' => 'Mark reward manually issued', 'result' => tl_mg_stage160_mark_manual_issued($input)];
        }
        if ($action === 'cancel_training_reward') {
            return ['action' => $action, 'label' => 'Cancel Training Lab reward', 'result' => tl_mg_stage160_cancel_reward($input)];
        }
        if ($action === 'reconcile_reward_lifecycle') {
            return ['action' => $action, 'label' => 'Reconcile reward lifecycle', 'result' => tl_mg_stage160_reconcile_lifecycle($input)];
        }


        if ($action === 'cohort_invite') {
            return ['action' => $action, 'label' => 'Add cohort participant', 'result' => tl_stage40_cohort_invite($input)];
        }
        if ($action === 'quick_checkin') {
            return ['action' => $action, 'label' => 'Participant check-in', 'result' => tl_stage40_quick_checkin($input)];
        }
        if ($action === 'update_participant_status') {
            return ['action' => $action, 'label' => 'Update participant status', 'result' => tl_stage40_update_participant_status($input)];
        }
        if ($action === 'finalize_participant') {
            return ['action' => $action, 'label' => 'Finalize participant certificate', 'result' => tl_stage40_finalize_participant($input)];
        }


        if ($action === 'create_challenge_template') {
            return ['action' => $action, 'label' => 'Create challenge from template', 'result' => tl_stage45_create_challenge_template($input)];
        }
        if ($action === 'submit_reflection_journal') {
            return ['action' => $action, 'label' => 'Submit reflection journal', 'result' => tl_stage45_submit_reflection_journal($input)];
        }
        if ($action === 'log_coach_note') {
            return ['action' => $action, 'label' => 'Log coach note', 'result' => tl_stage45_log_coach_note($input)];
        }
        if ($action === 'manual_progress_adjustment') {
            return ['action' => $action, 'label' => 'Manual progress adjustment', 'result' => tl_stage45_manual_progress_adjustment($input)];
        }
        if ($action === 'seed_training_scenario') {
            return ['action' => $action, 'label' => 'Seed training scenario', 'result' => tl_stage45_seed_training_scenario($input)];
        }


        if ($action === 'create_learning_path') {
            return ['action' => $action, 'label' => 'Create learning path', 'result' => tl_stage50_create_learning_path($input)];
        }
        if ($action === 'save_resource_note') {
            return ['action' => $action, 'label' => 'Save resource note', 'result' => tl_stage50_save_resource_note($input)];
        }
        if ($action === 'send_training_message') {
            return ['action' => $action, 'label' => 'Post training message', 'result' => tl_stage50_send_training_message($input)];
        }
        if ($action === 'create_report_snapshot') {
            return ['action' => $action, 'label' => 'Create report snapshot', 'result' => tl_stage50_create_report_snapshot($input)];
        }
        if ($action === 'log_demo_checkpoint') {
            return ['action' => $action, 'label' => 'Log demo checkpoint', 'result' => tl_stage50_log_demo_checkpoint($input)];
        }



        if ($action === 'create_training_plan') {
            return ['action' => $action, 'label' => 'Create training automation plan', 'result' => tl_stage55_create_training_plan($input)];
        }
        if ($action === 'save_training_reminder') {
            return ['action' => $action, 'label' => 'Save training reminder', 'result' => tl_stage55_save_training_reminder($input)];
        }
        if ($action === 'save_evidence_note') {
            return ['action' => $action, 'label' => 'Save evidence locker note', 'result' => tl_stage55_save_evidence_note($input)];
        }
        if ($action === 'save_review_rubric') {
            return ['action' => $action, 'label' => 'Save review rubric', 'result' => tl_stage55_save_review_rubric($input)];
        }
        if ($action === 'log_release_check') {
            return ['action' => $action, 'label' => 'Log release board check', 'result' => tl_stage55_log_release_check($input)];
        }





        // Stage 71–90 functional experience and operations actions.
        if ($action === 'save_guided_onboarding') {
            return ['action' => $action, 'label' => "Save Onboarding Plan", 'result' => tl_stage90_save_section_record('guided-onboarding', $input)];
        }
        if ($action === 'save_daily_agenda') {
            return ['action' => $action, 'label' => "Save Daily Agenda", 'result' => tl_stage90_save_section_record('daily-agenda', $input)];
        }
        if ($action === 'log_focus_block') {
            return ['action' => $action, 'label' => "Log Focus Block", 'result' => tl_stage90_save_section_record('focus-timer', $input)];
        }
        if ($action === 'save_decision_journal') {
            return ['action' => $action, 'label' => "Save Decision Journal", 'result' => tl_stage90_save_section_record('decision-journal', $input)];
        }
        if ($action === 'submit_peer_review') {
            return ['action' => $action, 'label' => "Submit Peer Review", 'result' => tl_stage90_save_section_record('peer-review-room', $input)];
        }
        if ($action === 'save_practice_lab') {
            return ['action' => $action, 'label' => "Save Practice Result", 'result' => tl_stage90_save_section_record('practice-lab', $input)];
        }
        if ($action === 'save_scenario_debrief') {
            return ['action' => $action, 'label' => "Save Debrief", 'result' => tl_stage90_save_section_record('scenario-debrief', $input)];
        }
        if ($action === 'save_resource_checklist') {
            return ['action' => $action, 'label' => "Save Checklist", 'result' => tl_stage90_save_section_record('resource-checklist', $input)];
        }
        if ($action === 'save_milestone_tracker') {
            return ['action' => $action, 'label' => "Save Milestone", 'result' => tl_stage90_save_section_record('milestone-tracker', $input)];
        }
        if ($action === 'save_outcome_snapshot') {
            return ['action' => $action, 'label' => "Save Outcome Snapshot", 'result' => tl_stage90_save_section_record('outcome-dashboard', $input)];
        }
        if ($action === 'save_prompt_lab') {
            return ['action' => $action, 'label' => "Save Prompt Draft", 'result' => tl_stage90_save_section_record('prompt-lab', $input)];
        }
        if ($action === 'save_habit_builder') {
            return ['action' => $action, 'label' => "Save Habit Plan", 'result' => tl_stage90_save_section_record('habit-builder', $input)];
        }
        if ($action === 'save_content_studio') {
            return ['action' => $action, 'label' => "Save Content Note", 'result' => tl_stage90_save_section_record('content-studio', $input)];
        }
        if ($action === 'save_program_rule') {
            return ['action' => $action, 'label' => "Save Program Rule", 'result' => tl_stage90_save_section_record('program-rules', $input)];
        }
        if ($action === 'save_risk_register') {
            return ['action' => $action, 'label' => "Save Risk", 'result' => tl_stage90_save_section_record('risk-register', $input)];
        }
        if ($action === 'save_support_ticket') {
            return ['action' => $action, 'label' => "Save Support Ticket", 'result' => tl_stage90_save_section_record('support-desk', $input)];
        }
        if ($action === 'save_audit_annotation') {
            return ['action' => $action, 'label' => "Save Audit Annotation", 'result' => tl_stage90_save_section_record('audit-trail-plus', $input)];
        }
        if ($action === 'save_backup_plan') {
            return ['action' => $action, 'label' => "Save Backup Plan", 'result' => tl_stage90_save_section_record('backup-planner', $input)];
        }
        if ($action === 'save_integration_sandbox') {
            return ['action' => $action, 'label' => "Save Integration Note", 'result' => tl_stage90_save_section_record('integration-sandbox', $input)];
        }
        if ($action === 'log_final_review') {
            return ['action' => $action, 'label' => "Log Final Review", 'result' => tl_stage90_save_section_record('final-review-console', $input)];
        }

        if ($action === 'save_skill_rating') {
            return ['action' => $action, 'label' => 'Save skill rating', 'result' => tl_stage70_save_skill_rating($input)];
        }
        if ($action === 'submit_assessment_response') {
            return ['action' => $action, 'label' => 'Submit assessment response', 'result' => tl_stage70_submit_assessment_response($input)];
        }
        if ($action === 'create_goal_plan') {
            return ['action' => $action, 'label' => 'Create goal plan', 'result' => tl_stage70_create_goal_plan($input)];
        }
        if ($action === 'design_badge_blueprint') {
            return ['action' => $action, 'label' => 'Design badge blueprint', 'result' => tl_stage70_design_badge_blueprint($input)];
        }
        if ($action === 'submit_team_pulse') {
            return ['action' => $action, 'label' => 'Submit team pulse', 'result' => tl_stage70_submit_team_pulse($input)];
        }
        if ($action === 'add_calendar_marker') {
            return ['action' => $action, 'label' => 'Add calendar marker', 'result' => tl_stage70_add_calendar_marker($input)];
        }
        if ($action === 'save_mentor_note') {
            return ['action' => $action, 'label' => 'Save mentor note', 'result' => tl_stage70_save_mentor_note($input)];
        }
        if ($action === 'submit_feedback_item') {
            return ['action' => $action, 'label' => 'Submit feedback item', 'result' => tl_stage70_submit_feedback_item($input)];
        }
        if ($action === 'create_sprint_item') {
            return ['action' => $action, 'label' => 'Create sprint item', 'result' => tl_stage70_create_sprint_item($input)];
        }
        if ($action === 'submit_knowledge_check') {
            return ['action' => $action, 'label' => 'Submit knowledge check', 'result' => tl_stage70_submit_knowledge_check($input)];
        }
        if ($action === 'save_demo_narrative') {
            return ['action' => $action, 'label' => 'Save demo narrative', 'result' => tl_stage70_save_demo_narrative($input)];
        }
        if ($action === 'save_operator_playbook') {
            return ['action' => $action, 'label' => 'Save operator playbook', 'result' => tl_stage70_save_operator_playbook($input)];
        }
        if ($action === 'log_launch_readiness') {
            return ['action' => $action, 'label' => 'Log launch readiness', 'result' => tl_stage70_log_launch_readiness($input)];
        }


        // Stage 91–120 functional mastery and deployment simulation actions.
        if (function_exists('tl_stage120_resolve_action')) {
            $stage120Slug = tl_stage120_resolve_action($action);
            if ($stage120Slug !== null) {
                $stage120Section = tl_stage120_section($stage120Slug);
                return [
                    'action' => $action,
                    'label' => (string)($stage120Section['button'] ?? 'Save Stage 120 Record'),
                    'result' => tl_stage120_save_section_record($stage120Slug, $input),
                ];
            }
        }

        throw new RuntimeException('Unsupported Training Lab action: ' . $action);
    }
}


if (!function_exists('tl_app_parse_task_blueprint')) {
    function tl_app_parse_task_blueprint(string $blueprint, int $fallbackCount = 5): array
    {
        $tasks = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($blueprint));
        foreach ($lines ?: [] as $line) {
            $line = trim((string)$line);
            if ($line === '') continue;
            $proofRequired = false;
            if (preg_match('/\[(proof|required)\]/i', $line)) {
                $proofRequired = true;
                $line = trim((string)preg_replace('/\[(proof|required)\]/i', '', $line));
            }
            if (preg_match('/\[(checklist|no proof|no-proof)\]/i', $line)) {
                $proofRequired = false;
                $line = trim((string)preg_replace('/\[(checklist|no proof|no-proof)\]/i', '', $line));
            }
            $parts = array_map('trim', explode('|', $line));
            $title = $parts[0] ?? 'Training task';
            $instructions = $parts[1] ?? 'Complete this Training Lab action.';
            $type = $proofRequired ? 'text_reflection' : 'checklist';
            if (isset($parts[2]) && $parts[2] !== '') {
                $typeCandidate = preg_replace('/[^a-z_]/', '', strtolower($parts[2]));
                if (in_array($typeCandidate, ['checklist','movement','photo_proof','video_proof','text_reflection','quiz','custom'], true)) {
                    $type = $typeCandidate;
                    $proofRequired = in_array($typeCandidate, ['photo_proof','video_proof','text_reflection'], true) || $proofRequired;
                }
            }
            $tasks[] = [
                'title' => mb_substr($title, 0, 180),
                'instructions' => $instructions,
                'proof_required' => $proofRequired ? 1 : 0,
                'task_type' => $type,
            ];
        }
        if (!$tasks) {
            for ($i = 1; $i <= max(1, min(30, $fallbackCount)); $i++) {
                $tasks[] = [
                    'title' => $i === $fallbackCount ? 'Final proof submission' : 'Day ' . $i . ' training action',
                    'instructions' => 'Complete this Training Lab action.',
                    'proof_required' => $i === $fallbackCount ? 1 : 0,
                    'task_type' => $i === $fallbackCount ? 'text_reflection' : 'checklist',
                ];
            }
        }
        return array_slice($tasks, 0, 30);
    }
}

if (!function_exists('tl_create_campaign_blueprint')) {
    function tl_create_campaign_blueprint(array $input): array
    {
        $tasks = tl_app_parse_task_blueprint((string)($input['task_blueprint'] ?? ''), (int)($input['target_action_count'] ?? 5));
        $result = tl_create_campaign([
            'title' => $input['title'] ?? 'Training Lab Campaign',
            'slug' => $input['slug'] ?? ($input['title'] ?? 'training-lab-campaign'),
            'summary' => $input['summary'] ?? 'Standalone Training Lab campaign created from the campaign builder.',
            'description' => $input['description'] ?? ($input['summary'] ?? 'Complete the configured task path and submit Training Lab proof.'),
            'campaign_type' => $input['campaign_type'] ?? 'custom',
            'visibility' => $input['visibility'] ?? 'published',
            'status' => $input['status'] ?? 'active',
            'target_action_count' => count($tasks),
            'reward_label' => $input['reward_label'] ?? 'Training Lab Completion Badge',
            'reward_value_cents' => $input['reward_value_cents'] ?? 0,
            'owner_user_id' => $input['owner_user_id'] ?? 1,
            'created_by_user_id' => $input['created_by_user_id'] ?? ($input['owner_user_id'] ?? 1),
            'tasks' => $tasks,
        ]);
        return $result + ['task_count' => count($tasks), 'builder' => 'stage35_campaign_builder'];
    }
}

if (!function_exists('tl_app_task_rows')) {
    function tl_app_task_rows(string $campaignRef): array
    {
        $campaign = tl_stage34_campaign($campaignRef ?: tl_app_default_campaign_ref());
        return tl_stage34_tasks((string)($campaign['id'] ?? $campaignRef));
    }
}

if (!function_exists('tl_app_participant_context')) {
    function tl_app_participant_context(string $campaignRef = '', int $userId = 1): array
    {
        $campaignRef = $campaignRef !== '' ? $campaignRef : tl_app_default_campaign_ref();
        $campaign = tl_stage34_campaign($campaignRef);
        $tasks = tl_stage34_tasks((string)$campaign['id']);
        $pdo = tl_db();
        $participant = null;
        $proofs = [];
        $receipts = [];
        $rewards = [];
        $streak = null;
        if ($pdo && !empty($campaign['db_id'])) {
            try {
                $stmt = $pdo->prepare('SELECT * FROM training_participants WHERE campaign_id = ? AND user_id = ? LIMIT 1');
                $stmt->execute([(int)$campaign['db_id'], $userId]);
                $participant = $stmt->fetch() ?: null;
                if ($participant) {
                    $proofStmt = $pdo->prepare('SELECT p.*, t.title AS task_title, t.position_no, t.proof_required, (SELECT r.decision FROM training_reviews r WHERE r.proof_submission_id = p.id ORDER BY r.created_at DESC LIMIT 1) AS latest_decision FROM training_proof_submissions p LEFT JOIN training_campaign_tasks t ON t.id = p.task_id WHERE p.participant_id = ? ORDER BY t.position_no ASC, p.created_at DESC');
                    $proofStmt->execute([(int)$participant['id']]);
                    $proofs = $proofStmt->fetchAll();
                    $receiptStmt = $pdo->prepare('SELECT * FROM training_action_receipts WHERE participant_id = ? ORDER BY created_at DESC LIMIT 50');
                    $receiptStmt->execute([(int)$participant['id']]);
                    $receipts = $receiptStmt->fetchAll();
                    $rewardStmt = $pdo->prepare('SELECT re.*, rr.reward_label FROM training_reward_events re LEFT JOIN training_reward_rules rr ON rr.id = re.reward_rule_id WHERE re.participant_id = ? ORDER BY re.created_at DESC LIMIT 50');
                    $rewardStmt->execute([(int)$participant['id']]);
                    $rewards = $rewardStmt->fetchAll();
                    $streakStmt = $pdo->prepare('SELECT * FROM training_streaks WHERE participant_id = ? LIMIT 1');
                    $streakStmt->execute([(int)$participant['id']]);
                    $streak = $streakStmt->fetch() ?: null;
                }
            } catch (Throwable $e) {}
        }
        $proofsByTask = [];
        foreach ($proofs as $proof) {
            $proofsByTask[(string)$proof['task_id']][] = $proof;
        }
        return [
            'campaign' => $campaign,
            'tasks' => $tasks,
            'user_id' => $userId,
            'participant' => $participant,
            'proofs' => $proofs,
            'proofs_by_task' => $proofsByTask,
            'receipts' => $receipts,
            'rewards' => $rewards,
            'streak' => $streak,
            'joined' => (bool)$participant,
        ];
    }
}

if (!function_exists('tl_complete_task')) {
    function tl_complete_task(array $input): array
    {
        $pdo = tl_require_db();
        $campaignRef = (string)($input['campaign'] ?? $input['campaign_id'] ?? tl_app_default_campaign_ref());
        $campaign = tl_find_campaign_row($pdo, $campaignRef);
        if (!$campaign) throw new RuntimeException('Campaign not found for task completion.');
        $userId = max(1, (int)($input['user_id'] ?? 1));
        $join = tl_join_campaign(['campaign_id' => (string)$campaign['id'], 'user_id' => $userId, 'participant_label' => $input['participant_label'] ?? 'Demo Participant']);
        $participantId = (int)$join['participant_id'];
        $taskRef = (string)($input['task_id'] ?? '');
        if ($taskRef === '') throw new RuntimeException('Missing task id.');
        $stmt = $pdo->prepare('SELECT * FROM training_campaign_tasks WHERE campaign_id = ? AND (id = ? OR public_id = ?) LIMIT 1');
        $stmt->execute([(int)$campaign['id'], ctype_digit($taskRef) ? (int)$taskRef : 0, $taskRef]);
        $task = $stmt->fetch();
        if (!$task) throw new RuntimeException('Task not found.');

        $dupe = $pdo->prepare("SELECT p.*, (SELECT r.id FROM training_reviews r WHERE r.proof_submission_id = p.id AND r.decision = 'approved' ORDER BY r.id ASC LIMIT 1) AS approved_review_id FROM training_proof_submissions p WHERE p.task_id = ? AND p.participant_id = ? AND p.status <> 'cancelled' ORDER BY p.id ASC LIMIT 1");
        $dupe->execute([(int)$task['id'], $participantId]);
        $existing = $dupe->fetch();
        if ($existing && !empty($existing['approved_review_id'])) {
            return ['task_id' => (int)$task['id'], 'proof_submission_id' => (int)$existing['id'], 'already_completed' => true, 'status' => 'approved'];
        }

        $pdo->beginTransaction();
        try {
            $publicId = tl_uuid();
            $proofText = trim((string)($input['proof_text'] ?? 'Checklist task completed in the standalone Training Lab task runner.'));
            $proofType = ((int)$task['proof_required'] === 1) ? 'text' : 'none';
            $proofStatus = ((int)$task['proof_required'] === 1 && empty($input['auto_approve'])) ? 'submitted' : 'approved';
            $proof = $pdo->prepare('INSERT INTO training_proof_submissions (public_id, campaign_id, task_id, participant_id, submitted_by_user_id, proof_type, proof_text, external_url, status, reviewed_at, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $proof->execute([$publicId, (int)$campaign['id'], (int)$task['id'], $participantId, $userId, $proofType, $proofText, trim((string)($input['external_url'] ?? '')) ?: null, $proofStatus, $proofStatus === 'approved' ? date('Y-m-d H:i:s') : null, json_encode(['stage' => 'stage35_task_runner', 'real_upload' => false, 'auto_approved_checklist' => $proofStatus === 'approved'], JSON_UNESCAPED_SLASHES)]);
            $proofId = (int)$pdo->lastInsertId();
            tl_log_event($pdo, $userId, 'proof', $proofId, 'task_proof_recorded', ['task_id' => (int)$task['id'], 'auto_approved' => $proofStatus === 'approved']);
            $review = null;
            $receipt = null;
            if ($proofStatus === 'approved') {
                $reviewPublicId = tl_uuid();
                $reviewStmt = $pdo->prepare('INSERT INTO training_reviews (public_id, proof_submission_id, reviewer_user_id, decision, review_notes, metadata_json) VALUES (?, ?, ?, ?, ?, ?)');
                $reviewStmt->execute([$reviewPublicId, $proofId, max(1, (int)($input['reviewer_user_id'] ?? 1)), 'approved', 'Auto-approved checklist completion inside Stage 35 task runner.', json_encode(['stage' => 'stage35_auto_checklist_review'], JSON_UNESCAPED_SLASHES)]);
                $reviewId = (int)$pdo->lastInsertId();
                $row = $pdo->prepare('SELECT * FROM training_proof_submissions WHERE id = ? LIMIT 1');
                $row->execute([$proofId]);
                $proofRow = $row->fetch();
                $receipt = tl_create_action_receipt($pdo, $proofRow, $reviewId);
                tl_evaluate_rewards_for_participant($pdo, (int)$campaign['id'], $participantId, $userId, $receipt['receipt_id']);
                $review = ['review_id' => $reviewId, 'public_id' => $reviewPublicId, 'decision' => 'approved'];
            }
            $pdo->commit();
            return ['task_id' => (int)$task['id'], 'proof_submission_id' => $proofId, 'public_id' => $publicId, 'status' => $proofStatus, 'review' => $review, 'receipt' => $receipt, 'already_completed' => false];
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}

if (!function_exists('tl_queue_reward_event')) {
    function tl_queue_reward_event(array $input): array
    {
        $pdo = tl_require_db();
        $rewardRef = (string)($input['reward_event_id'] ?? $input['reward'] ?? '');
        if ($rewardRef === '') {
            $row = $pdo->query("SELECT * FROM training_reward_events WHERE status = 'eligible' ORDER BY created_at ASC LIMIT 1")->fetch();
        } else {
            $stmt = $pdo->prepare('SELECT * FROM training_reward_events WHERE id = ? OR public_id = ? LIMIT 1');
            $stmt->execute([ctype_digit($rewardRef) ? (int)$rewardRef : 0, $rewardRef]);
            $row = $stmt->fetch();
        }
        if (!$row) throw new RuntimeException('No eligible Training Lab reward event found.');
        if ((string)$row['status'] !== 'eligible') return ['reward_event_id' => (int)$row['id'], 'status' => (string)$row['status'], 'changed' => false];
        $stmt = $pdo->prepare("UPDATE training_reward_events SET status = 'queued', metadata_json = JSON_SET(COALESCE(metadata_json, JSON_OBJECT()), '$.stage35_queue_preview', true, '$.wallet_write', false) WHERE id = ?");
        $stmt->execute([(int)$row['id']]);
        tl_log_event($pdo, (int)$row['user_id'], 'reward_event', (int)$row['id'], 'reward_queued_preview', ['wallet_write' => false, 'claim_redeem' => false]);
        return ['reward_event_id' => (int)$row['id'], 'public_id' => (string)$row['public_id'], 'status' => 'queued', 'changed' => true, 'wallet_write' => false];
    }
}

if (!function_exists('tl_app_stage35_summary')) {
    function tl_app_stage35_summary(): array
    {
        return [
            'stage' => 'Stage 35 functional app builder suite',
            'sections' => [
                'campaign_builder' => '/app/campaign-builder.php',
                'participant_portal' => '/app/participant-portal.php',
                'task_runner' => '/app/task-runner.php',
                'review_workbench' => '/admin/review-workbench.php',
                'flow_board' => '/app/flow-board.php',
            ],
            'new_actions' => ['create_campaign_blueprint','complete_task','queue_reward_event'],
            'safe_boundaries' => [
                'training_tables_only' => true,
                'no_real_uploads' => true,
                'no_payments' => true,
                'no_wallet_writes' => true,
                'no_real_reward_issuing' => true,
                'no_claim_redeem' => true,
            ],
        ];
    }
}

if (!function_exists('tl_app_json')) {
    function tl_app_json(array $payload, int $status = 200): void
    {
        tl_json_response($payload, $status);
    }
}

if (!function_exists('tl_stage40_participant_rows')) {
    function tl_stage40_participant_rows(int $limit = 50): array
    {
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_participants')) return [];
        try {
            $sql = "SELECT tp.*, c.title AS campaign_title, c.slug AS campaign_slug, c.target_action_count,
                       COALESCE(s.completed_action_count, 0) AS completed_action_count,
                       COALESCE(s.current_streak_days, 0) AS current_streak_days,
                       COALESCE(s.longest_streak_days, 0) AS longest_streak_days,
                       (SELECT COUNT(*) FROM training_proof_submissions p WHERE p.participant_id = tp.id) AS proof_count,
                       (SELECT COUNT(*) FROM training_proof_submissions p WHERE p.participant_id = tp.id AND p.status = 'approved') AS approved_proof_count,
                       (SELECT COUNT(*) FROM training_action_receipts ar WHERE ar.participant_id = tp.id AND ar.receipt_status = 'active') AS receipt_count,
                       (SELECT COUNT(*) FROM training_reward_events re WHERE re.participant_id = tp.id AND re.status <> 'cancelled') AS reward_event_count
                    FROM training_participants tp
                    LEFT JOIN training_campaigns c ON c.id = tp.campaign_id
                    LEFT JOIN training_streaks s ON s.participant_id = tp.id
                    ORDER BY tp.updated_at DESC, tp.id DESC
                    LIMIT " . max(1, min(100, $limit));
            return $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_stage40_campaign_task_matrix')) {
    function tl_stage40_campaign_task_matrix(string $campaignRef = ''): array
    {
        $campaignRef = $campaignRef !== '' ? $campaignRef : tl_app_default_campaign_ref();
        $campaign = tl_stage34_campaign($campaignRef);
        $tasks = tl_stage34_tasks((string)($campaign['id'] ?? $campaignRef));
        $pdo = tl_db();
        $taskRows = [];
        if ($pdo && !empty($campaign['db_id']) && tl_table_exists('training_campaign_tasks')) {
            try {
                $stmt = $pdo->prepare("SELECT t.*,
                           (SELECT COUNT(*) FROM training_proof_submissions p WHERE p.task_id = t.id) AS proof_count,
                           (SELECT COUNT(*) FROM training_proof_submissions p WHERE p.task_id = t.id AND p.status = 'approved') AS approved_count
                        FROM training_campaign_tasks t
                        WHERE t.campaign_id = ?
                        ORDER BY t.position_no ASC");
                $stmt->execute([(int)$campaign['db_id']]);
                $taskRows = $stmt->fetchAll();
            } catch (Throwable $e) { $taskRows = []; }
        }
        return ['campaign' => $campaign, 'tasks' => $taskRows ?: $tasks];
    }
}

if (!function_exists('tl_stage40_next_task_for_participant')) {
    function tl_stage40_next_task_for_participant(int $campaignId, int $participantId): ?array
    {
        $pdo = tl_db();
        if (!$pdo || !$campaignId || !$participantId) return null;
        try {
            $stmt = $pdo->prepare("SELECT t.*,
                       (SELECT p.status FROM training_proof_submissions p WHERE p.task_id = t.id AND p.participant_id = ? AND p.status <> 'cancelled' ORDER BY p.created_at DESC LIMIT 1) AS latest_status,
                       (SELECT p.public_id FROM training_proof_submissions p WHERE p.task_id = t.id AND p.participant_id = ? AND p.status <> 'cancelled' ORDER BY p.created_at DESC LIMIT 1) AS latest_proof_public_id
                    FROM training_campaign_tasks t
                    WHERE t.campaign_id = ? AND t.status = 'active'
                    ORDER BY t.position_no ASC");
            $stmt->execute([$participantId, $participantId, $campaignId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                if ((string)($row['latest_status'] ?? '') !== 'approved') return $row;
            }
            return $rows ? end($rows) : null;
        } catch (Throwable $e) { return null; }
    }
}

if (!function_exists('tl_stage40_launchpad_state')) {
    function tl_stage40_launchpad_state(string $campaignRef = '', int $userId = 1): array
    {
        $campaignRef = $campaignRef !== '' ? $campaignRef : tl_app_default_campaign_ref();
        $context = tl_app_participant_context($campaignRef, $userId);
        $nextTask = null;
        if (!empty($context['participant']['id']) && !empty($context['campaign']['db_id'])) {
            $nextTask = tl_stage40_next_task_for_participant((int)$context['campaign']['db_id'], (int)$context['participant']['id']);
        }
        return [
            'stage' => 'Stage 40 participant operations suite',
            'summary' => tl_app_flow_summary(),
            'campaigns' => tl_app_campaign_options(),
            'selected_campaign_ref' => $campaignRef,
            'user_id' => $userId,
            'participant_context' => $context,
            'next_task' => $nextTask,
            'cohort' => tl_stage40_participant_rows(50),
            'safe_boundaries' => [
                'standalone_training_script' => true,
                'writes_only_training_tables' => true,
                'no_real_upload_processing' => true,
                'no_payments' => true,
                'no_wallet_balance_changes' => true,
                'microgifter_reward_issuing_requires_adapter_or_developer_key' => true,
                'training_reward_claim_tracking_active' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage40_certificate_candidates')) {
    function tl_stage40_certificate_candidates(int $limit = 50): array
    {
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_participants')) return [];
        try {
            $sql = "SELECT tp.*, c.title AS campaign_title, c.slug AS campaign_slug, c.target_action_count,
                       COALESCE(s.completed_action_count, 0) AS completed_action_count,
                       COALESCE(s.longest_streak_days, 0) AS longest_streak_days,
                       (SELECT COUNT(*) FROM training_action_receipts ar WHERE ar.participant_id = tp.id AND ar.receipt_type = 'sequence_completed' AND ar.receipt_status = 'active') AS certificate_count,
                       (SELECT ar.public_id FROM training_action_receipts ar WHERE ar.participant_id = tp.id AND ar.receipt_type = 'sequence_completed' AND ar.receipt_status = 'active' ORDER BY ar.created_at DESC LIMIT 1) AS certificate_public_id,
                       (SELECT ar.verification_hash FROM training_action_receipts ar WHERE ar.participant_id = tp.id AND ar.receipt_type = 'sequence_completed' AND ar.receipt_status = 'active' ORDER BY ar.created_at DESC LIMIT 1) AS certificate_hash
                    FROM training_participants tp
                    LEFT JOIN training_campaigns c ON c.id = tp.campaign_id
                    LEFT JOIN training_streaks s ON s.participant_id = tp.id
                    ORDER BY certificate_count DESC, completed_action_count DESC, tp.updated_at DESC
                    LIMIT " . max(1, min(100, $limit));
            return $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_stage40_cohort_invite')) {
    function tl_stage40_cohort_invite(array $input): array
    {
        $result = tl_join_campaign([
            'campaign' => $input['campaign'] ?? $input['campaign_id'] ?? tl_app_default_campaign_ref(),
            'user_id' => max(1, (int)($input['user_id'] ?? 1)),
            'participant_label' => trim((string)($input['participant_label'] ?? 'Training Participant')) ?: 'Training Participant',
            'invited_by_user_id' => isset($input['invited_by_user_id']) ? (int)$input['invited_by_user_id'] : null,
        ]);
        return $result + ['cohort_action' => 'joined_or_loaded', 'stage' => 'stage40_cohort_manager'];
    }
}

if (!function_exists('tl_stage40_quick_checkin')) {
    function tl_stage40_quick_checkin(array $input): array
    {
        $pdo = tl_require_db();
        $campaignRef = (string)($input['campaign'] ?? $input['campaign_id'] ?? tl_app_default_campaign_ref());
        $campaign = tl_find_campaign_row($pdo, $campaignRef);
        if (!$campaign) throw new RuntimeException('Campaign not found for check-in.');
        $userId = max(1, (int)($input['user_id'] ?? 1));
        $join = tl_join_campaign(['campaign_id' => (string)$campaign['id'], 'user_id' => $userId, 'participant_label' => $input['participant_label'] ?? 'Demo Participant']);
        $participantId = (int)$join['participant_id'];
        $taskRef = trim((string)($input['task_id'] ?? ''));
        if ($taskRef === '') {
            $nextTask = tl_stage40_next_task_for_participant((int)$campaign['id'], $participantId);
            if (!$nextTask) throw new RuntimeException('No active task found for check-in.');
            $taskRef = (string)$nextTask['id'];
        }
        $proofText = trim((string)($input['proof_text'] ?? ''));
        if ($proofText === '') $proofText = 'Quick check-in completed from Stage 40 participant operations.';
        $taskStmt = $pdo->prepare('SELECT proof_required FROM training_campaign_tasks WHERE campaign_id = ? AND (id = ? OR public_id = ?) LIMIT 1');
        $taskStmt->execute([(int)$campaign['id'], ctype_digit($taskRef) ? (int)$taskRef : 0, $taskRef]);
        $proofRequired = (int)($taskStmt->fetchColumn() ?: 0) === 1;
        $result = tl_complete_task([
            'campaign' => (string)$campaign['id'],
            'task_id' => $taskRef,
            'user_id' => $userId,
            'participant_label' => $input['participant_label'] ?? 'Demo Participant',
            'proof_text' => $proofText,
            'external_url' => trim((string)($input['external_url'] ?? '')),
            'auto_approve' => $proofRequired ? 0 : 1,
            'reviewer_user_id' => $input['reviewer_user_id'] ?? 1,
        ]);
        tl_log_event($pdo, $userId, 'participant', $participantId, 'participant_checkin_saved', ['task_id' => $taskRef, 'proof_required' => $proofRequired, 'auto_approved' => !$proofRequired]);
        return $result + ['participant_id' => $participantId, 'proof_required' => $proofRequired, 'stage' => 'stage40_checkin'];
    }
}

if (!function_exists('tl_stage40_update_participant_status')) {
    function tl_stage40_update_participant_status(array $input): array
    {
        $pdo = tl_require_db();
        $ref = trim((string)($input['participant_id'] ?? $input['participant'] ?? ''));
        if ($ref === '') throw new RuntimeException('Missing participant.');
        $status = (string)($input['status'] ?? 'active');
        if (!in_array($status, ['invited','active','paused','completed','removed'], true)) $status = 'active';
        $stmt = $pdo->prepare('SELECT * FROM training_participants WHERE id = ? OR public_id = ? LIMIT 1');
        $stmt->execute([ctype_digit($ref) ? (int)$ref : 0, $ref]);
        $participant = $stmt->fetch();
        if (!$participant) throw new RuntimeException('Participant not found.');
        $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
        $removedAt = $status === 'removed' ? date('Y-m-d H:i:s') : null;
        $upd = $pdo->prepare('UPDATE training_participants SET status = ?, completed_at = ?, removed_at = ?, metadata_json = JSON_SET(COALESCE(metadata_json, JSON_OBJECT()), "$.stage40_status_update", ?, "$.status_note", ?) WHERE id = ?');
        $upd->execute([$status, $completedAt, $removedAt, date('c'), trim((string)($input['status_note'] ?? '')), (int)$participant['id']]);
        tl_log_event($pdo, max(1, (int)($input['actor_user_id'] ?? 1)), 'participant', (int)$participant['id'], 'participant_status_updated', ['status' => $status]);
        return ['participant_id' => (int)$participant['id'], 'public_id' => (string)$participant['public_id'], 'status' => $status];
    }
}

if (!function_exists('tl_stage40_finalize_participant')) {
    function tl_stage40_finalize_participant(array $input): array
    {
        $pdo = tl_require_db();
        $ref = trim((string)($input['participant_id'] ?? $input['participant'] ?? ''));
        if ($ref === '') throw new RuntimeException('Missing participant.');
        $stmt = $pdo->prepare('SELECT tp.*, c.title AS campaign_title, c.target_action_count, c.id AS campaign_db_id, COALESCE(s.completed_action_count, 0) AS completed_action_count FROM training_participants tp LEFT JOIN training_campaigns c ON c.id = tp.campaign_id LEFT JOIN training_streaks s ON s.participant_id = tp.id WHERE tp.id = ? OR tp.public_id = ? LIMIT 1');
        $stmt->execute([ctype_digit($ref) ? (int)$ref : 0, $ref]);
        $participant = $stmt->fetch();
        if (!$participant) throw new RuntimeException('Participant not found.');
        $actor = max(1, (int)($input['actor_user_id'] ?? 1));
        $pdo->beginTransaction();
        try {
            $existing = $pdo->prepare("SELECT * FROM training_action_receipts WHERE participant_id = ? AND receipt_type = 'sequence_completed' AND receipt_status = 'active' ORDER BY id ASC LIMIT 1");
            $existing->execute([(int)$participant['id']]);
            $receipt = $existing->fetch();
            $reused = true;
            if (!$receipt) {
                $publicId = tl_uuid();
                $hash = hash('sha256', implode('|', ['sequence_completed', $participant['id'], $participant['campaign_id'], $participant['user_id'], microtime(true)]));
                $ins = $pdo->prepare('INSERT INTO training_action_receipts (public_id, campaign_id, participant_id, user_id, proof_submission_id, review_id, receipt_type, verification_hash, receipt_status, metadata_json) VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?)');
                $ins->execute([$publicId, (int)$participant['campaign_id'], (int)$participant['id'], (int)$participant['user_id'], 'sequence_completed', $hash, 'active', json_encode(['stage' => 'stage40_certificate_center', 'certificate_preview' => true, 'wallet_write' => false], JSON_UNESCAPED_SLASHES)]);
                $receiptId = (int)$pdo->lastInsertId();
                $receipt = ['id' => $receiptId, 'public_id' => $publicId, 'verification_hash' => $hash];
                $reused = false;
            }
            $upd = $pdo->prepare("UPDATE training_participants SET status = 'completed', completed_at = COALESCE(completed_at, CURRENT_TIMESTAMP), metadata_json = JSON_SET(COALESCE(metadata_json, JSON_OBJECT()), '$.stage40_certificate_ready', true) WHERE id = ?");
            $upd->execute([(int)$participant['id']]);
            tl_evaluate_rewards_for_participant($pdo, (int)$participant['campaign_id'], (int)$participant['id'], (int)$participant['user_id'], (int)$receipt['id']);
            tl_log_event($pdo, $actor, 'receipt', (int)$receipt['id'], 'completion_certificate_created', ['participant_id' => (int)$participant['id'], 'reused' => $reused, 'wallet_write' => false]);
            $pdo->commit();
            return ['participant_id' => (int)$participant['id'], 'receipt_id' => (int)$receipt['id'], 'public_id' => (string)$receipt['public_id'], 'verification_hash' => (string)$receipt['verification_hash'], 'reused' => $reused, 'status' => 'completed', 'wallet_write' => false];
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}

if (!function_exists('tl_app_stage40_summary')) {
    function tl_app_stage40_summary(): array
    {
        return [
            'stage' => 'Stage 40 functional participant operations suite',
            'sections' => [
                'participant_launchpad' => '/app/launchpad.php',
                'daily_checkin' => '/app/check-in.php',
                'progress_map' => '/app/progress-map.php',
                'cohort_manager' => '/admin/cohort-manager.php',
                'certificate_center' => '/admin/cohort-manager.php',
            ],
            'new_actions' => ['cohort_invite','quick_checkin','update_participant_status','finalize_participant'],
            'counts' => tl_app_flow_summary()['counts'] ?? [],
            'safe_boundaries' => [
                'training_tables_only' => true,
                'no_real_uploads' => true,
                'no_payments' => true,
                'no_wallet_writes' => true,
                'no_real_reward_issuing' => true,
                'no_claim_redeem' => true,
                'direct_extract_package' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage45_templates')) {
    function tl_stage45_templates(): array
    {
        return [
            'merchant-launch' => [
                'label' => 'Merchant Launch Sprint',
                'title' => 'Merchant Launch Sprint',
                'summary' => 'A five-step campaign for onboarding a local merchant into repeatable customer engagement.',
                'campaign_type' => 'onboarding',
                'reward_label' => 'Merchant Launch Badge',
                'tasks' => [
                    ['title' => 'Define the local offer', 'instructions' => 'Write the offer, target customer, and redemption expectation.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Map the customer action', 'instructions' => 'Explain the exact action a customer should take.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Create the training message', 'instructions' => 'Draft the message that would be used in a campaign, table tent, or landing page.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Review the expected proof', 'instructions' => 'Describe how the merchant would verify completion.', 'task_type' => 'checklist', 'proof_required' => 0],
                    ['title' => 'Submit final launch reflection', 'instructions' => 'Summarize what worked and what should improve.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                ],
            ],
            'community-rewards' => [
                'label' => 'Community Rewards Challenge',
                'title' => 'Community Rewards Challenge',
                'summary' => 'A practical sequence for creating support, participation, and simulated rewards.',
                'campaign_type' => 'movement',
                'reward_label' => 'Community Support Badge',
                'tasks' => [
                    ['title' => 'Choose a community objective', 'instructions' => 'Define the local cause, customer group, or shared outcome.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Identify the reward signal', 'instructions' => 'Explain what action should create reward eligibility.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Run a simulated check-in', 'instructions' => 'Complete a check-in from the participant side.', 'task_type' => 'checklist', 'proof_required' => 0],
                    ['title' => 'Submit support proof', 'instructions' => 'Write what support happened and who benefited.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Review community impact', 'instructions' => 'Summarize the impact and next step.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                ],
            ],
            'review-practice' => [
                'label' => 'Review Practice Lab',
                'title' => 'Review Practice Lab',
                'summary' => 'A focused scenario for practicing proof review, feedback, receipts, and simulated reward eligibility.',
                'campaign_type' => 'skills',
                'reward_label' => 'Reviewer Readiness Badge',
                'tasks' => [
                    ['title' => 'Submit proof sample A', 'instructions' => 'Submit a clear text proof for reviewer approval.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Submit proof sample B', 'instructions' => 'Submit a second proof that needs review notes.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Practice feedback language', 'instructions' => 'Draft a review note that is clear and helpful.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Confirm receipt logic', 'instructions' => 'Check the action receipt after approval.', 'task_type' => 'checklist', 'proof_required' => 0],
                    ['title' => 'Review the reward preview', 'instructions' => 'Confirm the simulated reward boundary stays safe.', 'task_type' => 'checklist', 'proof_required' => 0],
                ],
            ],
        ];
    }
}

if (!function_exists('tl_stage45_template')) {
    function tl_stage45_template(string $templateId): array
    {
        $templates = tl_stage45_templates();
        return $templates[$templateId] ?? $templates['merchant-launch'];
    }
}

if (!function_exists('tl_stage45_recent_activity')) {
    function tl_stage45_recent_activity(int $limit = 25): array
    {
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_events')) return [];
        try {
            $sql = "SELECT * FROM training_events ORDER BY created_at DESC, id DESC LIMIT " . max(1, min(100, $limit));
            return $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_stage45_reflection_tasks')) {
    function tl_stage45_reflection_tasks(string $campaignRef = ''): array
    {
        $campaignRef = $campaignRef !== '' ? $campaignRef : tl_app_default_campaign_ref();
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_campaign_tasks')) return [];
        try {
            $campaign = tl_find_campaign_row($pdo, $campaignRef);
            if (!$campaign) return [];
            $stmt = $pdo->prepare("SELECT * FROM training_campaign_tasks WHERE campaign_id = ? AND status = 'active' ORDER BY proof_required DESC, position_no ASC");
            $stmt->execute([(int)$campaign['id']]);
            return $stmt->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_stage45_coach_state')) {
    function tl_stage45_coach_state(string $campaignRef = '', int $userId = 1): array
    {
        $campaignRef = $campaignRef !== '' ? $campaignRef : tl_app_default_campaign_ref();
        $launch = function_exists('tl_stage40_launchpad_state') ? tl_stage40_launchpad_state($campaignRef, $userId) : [];
        $nextTask = $launch['next_task'] ?? null;
        $recommendations = [];
        if ($nextTask) {
            $recommendations[] = ['title' => 'Continue next task', 'body' => 'Work on: ' . (string)$nextTask['title'], 'href' => '/app/check-in.php?campaign=' . rawurlencode($campaignRef) . '&user_id=' . $userId];
        }
        $recommendations[] = ['title' => 'Write a reflection', 'body' => 'Add a short proof/reflection that can move into review.', 'href' => '/app/reflection-journal.php?campaign=' . rawurlencode($campaignRef) . '&user_id=' . $userId];
        $recommendations[] = ['title' => 'Review the flow board', 'body' => 'Check how proof, reviews, receipts, and simulated rewards connect.', 'href' => '/app/flow-board.php'];
        return [
            'stage' => 'Stage 41 coaching dashboard',
            'campaign_ref' => $campaignRef,
            'user_id' => $userId,
            'launchpad' => $launch,
            'recommendations' => $recommendations,
            'recent_activity' => tl_stage45_recent_activity(12),
            'safe_boundaries' => tl_stage45_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage45_reflection_state')) {
    function tl_stage45_reflection_state(string $campaignRef = '', int $userId = 1): array
    {
        $campaignRef = $campaignRef !== '' ? $campaignRef : tl_app_default_campaign_ref();
        return [
            'stage' => 'Stage 42 reflection journal',
            'campaign_ref' => $campaignRef,
            'user_id' => $userId,
            'campaigns' => tl_app_campaign_options(),
            'tasks' => tl_stage45_reflection_tasks($campaignRef),
            'context' => tl_app_participant_context($campaignRef, $userId),
            'safe_boundaries' => tl_stage45_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage45_challenge_library_state')) {
    function tl_stage45_challenge_library_state(): array
    {
        return [
            'stage' => 'Stage 43 challenge library',
            'templates' => tl_stage45_templates(),
            'campaigns' => tl_app_campaign_options(),
            'safe_boundaries' => tl_stage45_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage45_intervention_state')) {
    function tl_stage45_intervention_state(): array
    {
        return [
            'stage' => 'Stage 44 intervention center',
            'participants' => function_exists('tl_stage40_participant_rows') ? tl_stage40_participant_rows(100) : [],
            'pending_proofs' => tl_app_pending_proofs(50),
            'recent_events' => tl_stage45_recent_activity(20),
            'safe_boundaries' => tl_stage45_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage45_scenario_state')) {
    function tl_stage45_scenario_state(): array
    {
        return [
            'stage' => 'Stage 45 scenario runner',
            'templates' => tl_stage45_templates(),
            'campaigns' => tl_app_campaign_options(),
            'recent_events' => tl_stage45_recent_activity(12),
            'safe_boundaries' => tl_stage45_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage45_chunk_state')) {
    function tl_stage45_chunk_state(): array
    {
        return [
            'stage' => 'Stage 45 functional coaching and scenario suite',
            'sections' => [
                'stage41' => 'Coach Dashboard',
                'stage42' => 'Reflection Journal',
                'stage43' => 'Challenge Library',
                'stage44' => 'Intervention Center',
                'stage45' => 'Scenario Runner',
            ],
            'summary' => tl_app_flow_summary(),
            'templates' => array_keys(tl_stage45_templates()),
            'safe_boundaries' => tl_stage45_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage45_safe_boundaries')) {
    function tl_stage45_safe_boundaries(): array
    {
        return [
            'standalone_training_script' => true,
            'writes_only_training_tables' => true,
            'no_real_upload_processing' => true,
            'no_payments' => true,
            'no_wallet_balance_changes' => true,
            'no_microgifter_reward_issuing' => true,
            'no_claim_redeem_logic' => true,
            'no_auth_gate_added' => true,
            'no_new_sql_required' => true,
        ];
    }
}

if (!function_exists('tl_stage45_create_challenge_template')) {
    function tl_stage45_create_challenge_template(array $input): array
    {
        $templateId = preg_replace('/[^a-z0-9\-]/i', '', (string)($input['template_id'] ?? 'merchant-launch')) ?: 'merchant-launch';
        $template = tl_stage45_template($templateId);
        $title = trim((string)($input['title'] ?? $template['title']));
        $slugBase = tl_slug((string)($input['slug'] ?? $title));
        $result = tl_create_campaign([
            'title' => $title,
            'slug' => $slugBase . '-' . strtolower(substr(tl_uuid(), 0, 6)),
            'summary' => $input['summary'] ?? $template['summary'],
            'description' => $input['description'] ?? $template['summary'],
            'campaign_type' => $template['campaign_type'] ?? 'custom',
            'visibility' => $input['visibility'] ?? 'published',
            'status' => $input['status'] ?? 'active',
            'target_action_count' => count($template['tasks']),
            'reward_label' => $input['reward_label'] ?? $template['reward_label'],
            'tasks' => $template['tasks'],
            'owner_user_id' => max(1, (int)($input['owner_user_id'] ?? 1)),
            'created_by_user_id' => max(1, (int)($input['created_by_user_id'] ?? 1)),
        ]);
        return $result + ['template_id' => $templateId, 'template_label' => $template['label'], 'stage' => 'stage45_challenge_library'];
    }
}

if (!function_exists('tl_stage45_submit_reflection_journal')) {
    function tl_stage45_submit_reflection_journal(array $input): array
    {
        $pdo = tl_require_db();
        $campaignRef = (string)($input['campaign'] ?? $input['campaign_id'] ?? tl_app_default_campaign_ref());
        $campaign = tl_find_campaign_row($pdo, $campaignRef);
        if (!$campaign) throw new RuntimeException('Campaign not found for reflection journal.');
        $taskRef = trim((string)($input['task_id'] ?? ''));
        if ($taskRef === '') {
            $stmt = $pdo->prepare("SELECT * FROM training_campaign_tasks WHERE campaign_id = ? AND status = 'active' ORDER BY proof_required DESC, position_no ASC LIMIT 1");
            $stmt->execute([(int)$campaign['id']]);
            $task = $stmt->fetch();
            if (!$task) throw new RuntimeException('No active task found for reflection journal.');
            $taskRef = (string)$task['id'];
        }
        $prompt = trim((string)($input['journal_prompt'] ?? 'Training reflection'));
        $reflection = trim((string)($input['reflection_text'] ?? $input['proof_text'] ?? ''));
        if ($reflection === '') throw new RuntimeException('Reflection text is required.');
        $proof = tl_submit_proof([
            'campaign' => (string)$campaign['id'],
            'task_id' => $taskRef,
            'user_id' => max(1, (int)($input['user_id'] ?? 1)),
            'participant_label' => $input['participant_label'] ?? 'Reflection Participant',
            'proof_text' => $prompt . "\n\n" . $reflection,
            'external_url' => trim((string)($input['external_url'] ?? '')),
        ]);
        tl_log_event($pdo, max(1, (int)($input['user_id'] ?? 1)), 'proof', (int)$proof['proof_submission_id'], 'reflection_journal_submitted', ['prompt' => $prompt, 'stage' => 'stage45_reflection_journal']);
        return $proof + ['prompt' => $prompt, 'stage' => 'stage45_reflection_journal'];
    }
}

if (!function_exists('tl_stage45_log_coach_note')) {
    function tl_stage45_log_coach_note(array $input): array
    {
        $pdo = tl_require_db();
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        $note = trim((string)($input['coach_note'] ?? $input['note'] ?? ''));
        if ($note === '') throw new RuntimeException('Coach note is required.');
        $participantRef = trim((string)($input['participant_id'] ?? ''));
        $subjectType = 'system';
        $subjectId = null;
        if ($participantRef !== '' && tl_table_exists('training_participants')) {
            $stmt = $pdo->prepare('SELECT id FROM training_participants WHERE id = ? OR public_id = ? LIMIT 1');
            $stmt->execute([ctype_digit($participantRef) ? (int)$participantRef : 0, $participantRef]);
            $found = $stmt->fetchColumn();
            if ($found) { $subjectType = 'participant'; $subjectId = (int)$found; }
        }
        tl_log_event($pdo, $actor, $subjectType, $subjectId, 'coach_note_logged', ['note' => $note, 'priority' => (string)($input['priority'] ?? 'normal'), 'stage' => 'stage45_coach_dashboard']);
        return ['logged' => true, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'note' => $note];
    }
}

if (!function_exists('tl_stage45_manual_progress_adjustment')) {
    function tl_stage45_manual_progress_adjustment(array $input): array
    {
        $pdo = tl_require_db();
        $participantRef = trim((string)($input['participant_id'] ?? ''));
        if ($participantRef === '') throw new RuntimeException('Participant is required for manual adjustment.');
        $stmt = $pdo->prepare('SELECT * FROM training_participants WHERE id = ? OR public_id = ? LIMIT 1');
        $stmt->execute([ctype_digit($participantRef) ? (int)$participantRef : 0, $participantRef]);
        $participant = $stmt->fetch();
        if (!$participant) throw new RuntimeException('Participant not found for manual adjustment.');
        $amount = max(0, min(5, (int)($input['completed_action_delta'] ?? 1)));
        $note = trim((string)($input['adjustment_note'] ?? 'Manual Training Lab progress adjustment.')) ?: 'Manual Training Lab progress adjustment.';
        $publicId = tl_uuid();
        $hash = hash('sha256', implode('|', ['manual', $participant['id'], $amount, $note, microtime(true)]));
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare('INSERT INTO training_action_receipts (public_id, campaign_id, participant_id, user_id, proof_submission_id, review_id, receipt_type, verification_hash, receipt_status, metadata_json) VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?)');
            $ins->execute([$publicId, (int)$participant['campaign_id'], (int)$participant['id'], (int)$participant['user_id'], 'manual_adjustment', $hash, 'active', json_encode(['stage' => 'stage45_intervention_center', 'note' => $note, 'completed_action_delta' => $amount, 'wallet_write' => false], JSON_UNESCAPED_SLASHES)]);
            $receiptId = (int)$pdo->lastInsertId();
            if ($amount > 0 && tl_table_exists('training_streaks')) {
                $up = $pdo->prepare('UPDATE training_streaks SET completed_action_count = completed_action_count + ?, current_streak_days = current_streak_days + ?, longest_streak_days = GREATEST(longest_streak_days, current_streak_days + ?), last_action_date = CURRENT_DATE WHERE participant_id = ?');
                $up->execute([$amount, $amount, $amount, (int)$participant['id']]);
            }
            tl_log_event($pdo, max(1, (int)($input['actor_user_id'] ?? 1)), 'receipt', $receiptId, 'manual_progress_adjustment', ['participant_id' => (int)$participant['id'], 'delta' => $amount, 'note' => $note]);
            $pdo->commit();
            return ['receipt_id' => $receiptId, 'public_id' => $publicId, 'verification_hash' => $hash, 'completed_action_delta' => $amount];
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}

if (!function_exists('tl_stage45_seed_training_scenario')) {
    function tl_stage45_seed_training_scenario(array $input): array
    {
        $templateId = preg_replace('/[^a-z0-9\-]/i', '', (string)($input['template_id'] ?? 'review-practice')) ?: 'review-practice';
        $participantCount = max(1, min(8, (int)($input['participant_count'] ?? 3)));
        $campaign = tl_stage45_create_challenge_template([
            'template_id' => $templateId,
            'title' => (string)($input['title'] ?? (tl_stage45_template($templateId)['title'] . ' Scenario')),
            'owner_user_id' => max(1, (int)($input['owner_user_id'] ?? 1)),
            'created_by_user_id' => max(1, (int)($input['created_by_user_id'] ?? 1)),
        ]);
        $participants = [];
        $proofs = [];
        for ($i = 1; $i <= $participantCount; $i++) {
            $userId = max(1, (int)($input['start_user_id'] ?? 20)) + $i - 1;
            $join = tl_join_campaign(['campaign_id' => (string)$campaign['campaign_id'], 'user_id' => $userId, 'participant_label' => 'Scenario Participant ' . $i]);
            $participants[] = $join;
            if (!empty($input['include_sample_proofs'])) {
                $proofs[] = tl_submit_proof(['campaign' => (string)$campaign['campaign_id'], 'user_id' => $userId, 'participant_label' => 'Scenario Participant ' . $i, 'proof_text' => 'Scenario proof sample for participant ' . $i . '. This is text-only Training Lab proof; no real upload was processed.']);
            }
        }
        return ['campaign' => $campaign, 'participants' => $participants, 'proofs' => $proofs, 'scenario' => $templateId, 'stage' => 'stage45_scenario_runner'];
    }
}

if (!function_exists('tl_stage50_safe_boundaries')) {
    function tl_stage50_safe_boundaries(): array
    {
        return [
            'standalone_training_script' => true,
            'writes_only_training_tables' => true,
            'resource_notes_are_training_events_only' => true,
            'messages_are_not_email_or_sms' => true,
            'report_snapshots_are_training_events_only' => true,
            'demo_ops_do_not_delete_or_reset_data' => true,
            'no_real_upload_processing' => true,
            'no_payments' => true,
            'no_wallet_balance_changes' => true,
            'no_microgifter_reward_issuing' => true,
            'no_claim_redeem_logic' => true,
            'no_auth_gate_added' => true,
        ];
    }
}

if (!function_exists('tl_stage50_learning_templates')) {
    function tl_stage50_learning_templates(): array
    {
        return [
            'operator-onboarding' => [
                'label' => 'Operator Onboarding Path',
                'title' => 'Operator Onboarding Path',
                'summary' => 'A guided standalone Training Lab path for learning the campaign, proof, review, and reward-simulation loop.',
                'campaign_type' => 'onboarding',
                'reward_label' => 'Operator Onboarding Completion Badge',
                'tasks' => [
                    ['title' => 'Understand the Training Lab boundary', 'instructions' => 'Read the safe-boundary summary and explain what the standalone Training Lab can and cannot touch.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Create a campaign objective', 'instructions' => 'Define a campaign objective, target participant, and completion signal.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Submit sample proof', 'instructions' => 'Submit a text proof that an admin can review.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Review receipts and reward preview', 'instructions' => 'Confirm the receipt and simulated reward event are Training Lab-only.', 'task_type' => 'checklist', 'proof_required' => 0],
                    ['title' => 'Write final operating note', 'instructions' => 'Summarize the participant journey and what should happen in the next training path.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                ],
            ],
            'coach-practice' => [
                'label' => 'Coach Practice Path',
                'title' => 'Coach Practice Path',
                'summary' => 'A path for practicing participant coaching, interventions, reflection feedback, and status updates.',
                'campaign_type' => 'skills',
                'reward_label' => 'Coach Practice Badge',
                'tasks' => [
                    ['title' => 'Review participant context', 'instructions' => 'Open the coach dashboard and identify the next best action.', 'task_type' => 'checklist', 'proof_required' => 0],
                    ['title' => 'Write a coach note', 'instructions' => 'Log a Training Lab-only coach note with a recommended next step.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Submit participant reflection', 'instructions' => 'Submit a reflection from the participant side.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Run review workbench', 'instructions' => 'Approve, reject, or request more information for a sample proof.', 'task_type' => 'checklist', 'proof_required' => 0],
                    ['title' => 'Complete coaching recap', 'instructions' => 'Write what the coach should do next.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                ],
            ],
            'merchant-demo' => [
                'label' => 'Merchant Demo Path',
                'title' => 'Merchant Demo Path',
                'summary' => 'A demo-ready flow for showing a merchant how campaigns, participation, proof, review, and reporting fit together.',
                'campaign_type' => 'movement',
                'reward_label' => 'Merchant Demo Badge',
                'tasks' => [
                    ['title' => 'Define merchant campaign story', 'instructions' => 'Describe the merchant, target customer action, and why the campaign matters.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Join as a sample participant', 'instructions' => 'Join the campaign from the participant app.', 'task_type' => 'checklist', 'proof_required' => 0],
                    ['title' => 'Complete a check-in', 'instructions' => 'Use the check-in flow to create participant activity.', 'task_type' => 'checklist', 'proof_required' => 0],
                    ['title' => 'Submit demo proof', 'instructions' => 'Submit text proof for admin review.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                    ['title' => 'Open reporting snapshot', 'instructions' => 'Create a Training Lab report snapshot and summarize the campaign state.', 'task_type' => 'text_reflection', 'proof_required' => 1],
                ],
            ],
        ];
    }
}

if (!function_exists('tl_stage50_template')) {
    function tl_stage50_template(string $templateId): array
    {
        $templates = tl_stage50_learning_templates();
        return $templates[$templateId] ?? $templates['operator-onboarding'];
    }
}

if (!function_exists('tl_stage50_recent_events_by_type')) {
    function tl_stage50_recent_events_by_type(array $types, int $limit = 25): array
    {
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_events')) return [];
        $types = array_values(array_filter(array_map('strval', $types)));
        if (!$types) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($types), '?'));
            $sql = "SELECT * FROM training_events WHERE event_type IN ($placeholders) ORDER BY created_at DESC, id DESC LIMIT " . max(1, min(100, $limit));
            $stmt = $pdo->prepare($sql);
            $stmt->execute($types);
            return $stmt->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_stage50_learning_path_state')) {
    function tl_stage50_learning_path_state(): array
    {
        return [
            'stage' => 'Stage 46 learning path planner',
            'templates' => tl_stage50_learning_templates(),
            'campaigns' => tl_app_campaign_options(),
            'recent_path_events' => tl_stage50_recent_events_by_type(['learning_path_created'], 10),
            'safe_boundaries' => tl_stage50_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage50_resource_state')) {
    function tl_stage50_resource_state(string $campaignRef = '', int $userId = 1): array
    {
        $campaignRef = $campaignRef !== '' ? $campaignRef : tl_app_default_campaign_ref();
        return [
            'stage' => 'Stage 47 resource hub',
            'campaign_ref' => $campaignRef,
            'user_id' => $userId,
            'campaigns' => tl_app_campaign_options(),
            'context' => tl_app_participant_context($campaignRef, $userId),
            'resources' => [
                ['title' => 'Training Lab Safe Boundary', 'type' => 'guide', 'body' => 'All app actions stay inside Training Lab tables and do not touch production rewards, wallets, uploads, payments, or claims.'],
                ['title' => 'Proof Quality Checklist', 'type' => 'checklist', 'body' => 'Good proof is specific, text-readable, tied to one task, and easy for the admin to review.'],
                ['title' => 'Coach Note Pattern', 'type' => 'template', 'body' => 'Observation, risk, recommendation, and next participant action.'],
                ['title' => 'Demo Storyline', 'type' => 'demo', 'body' => 'Campaign → participant → proof → review → receipt → simulated reward → report snapshot.'],
            ],
            'recent_notes' => tl_stage50_recent_events_by_type(['resource_note_saved'], 20),
            'safe_boundaries' => tl_stage50_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage50_message_board_state')) {
    function tl_stage50_message_board_state(string $campaignRef = '', int $userId = 1): array
    {
        $campaignRef = $campaignRef !== '' ? $campaignRef : tl_app_default_campaign_ref();
        return [
            'stage' => 'Stage 48 message board',
            'campaign_ref' => $campaignRef,
            'user_id' => $userId,
            'campaigns' => tl_app_campaign_options(),
            'participants' => function_exists('tl_stage40_participant_rows') ? tl_stage40_participant_rows(80) : [],
            'messages' => tl_stage50_recent_events_by_type(['training_message_sent'], 30),
            'safe_boundaries' => tl_stage50_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage50_reporting_state')) {
    function tl_stage50_reporting_state(): array
    {
        $summary = tl_app_flow_summary();
        return [
            'stage' => 'Stage 49 reporting center',
            'summary' => $summary,
            'stage35' => function_exists('tl_app_stage35_summary') ? tl_app_stage35_summary() : [],
            'stage40' => function_exists('tl_app_stage40_summary') ? tl_app_stage40_summary() : [],
            'stage45' => function_exists('tl_stage45_chunk_state') ? tl_stage45_chunk_state() : [],
            'snapshots' => tl_stage50_recent_events_by_type(['training_report_snapshot'], 20),
            'safe_boundaries' => tl_stage50_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage50_demo_ops_state')) {
    function tl_stage50_demo_ops_state(): array
    {
        $requiredRoots = ['admin','api','app','assets','config','database','includes','labs','index.php','signin.php','signup.php'];
        $root = dirname(__DIR__);
        $structure = [];
        foreach ($requiredRoots as $rel) {
            $structure[$rel] = is_dir($root . '/' . $rel) || is_file($root . '/' . $rel);
        }
        return [
            'stage' => 'Stage 50 demo operations center',
            'structure' => $structure,
            'all_structure_ready' => !in_array(false, $structure, true),
            'checkpoints' => tl_stage50_recent_events_by_type(['demo_checkpoint_logged'], 20),
            'counts' => tl_app_flow_summary()['counts'] ?? [],
            'safe_boundaries' => tl_stage50_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage50_chunk_state')) {
    function tl_stage50_chunk_state(): array
    {
        return [
            'stage' => 'Stage 50 functional learning and communications suite',
            'sections' => [
                'stage46' => 'Learning Path Planner',
                'stage47' => 'Resource Hub',
                'stage48' => 'Message Board',
                'stage49' => 'Reporting Center',
                'stage50' => 'Demo Operations Center',
            ],
            'learning_paths' => count(tl_stage50_learning_templates()),
            'messages' => count(tl_stage50_recent_events_by_type(['training_message_sent'], 100)),
            'resource_notes' => count(tl_stage50_recent_events_by_type(['resource_note_saved'], 100)),
            'snapshots' => count(tl_stage50_recent_events_by_type(['training_report_snapshot','demo_checkpoint_logged'], 100)),
            'safe_boundaries' => tl_stage50_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage50_event_insert')) {
    function tl_stage50_event_insert(?int $actorUserId, string $subjectType, ?int $subjectId, string $eventType, array $metadata = []): array
    {
        $pdo = tl_require_db();
        $publicId = tl_uuid();
        $stmt = $pdo->prepare('INSERT INTO training_events (public_id, actor_user_id, subject_type, subject_id, event_type, metadata_json) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$publicId, $actorUserId, $subjectType, $subjectId, $eventType, json_encode($metadata, JSON_UNESCAPED_SLASHES)]);
        return ['event_id' => (int)$pdo->lastInsertId(), 'public_id' => $publicId, 'event_type' => $eventType, 'metadata' => $metadata];
    }
}

if (!function_exists('tl_stage50_create_learning_path')) {
    function tl_stage50_create_learning_path(array $input): array
    {
        $templateId = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($input['template_id'] ?? 'operator-onboarding')));
        $template = tl_stage50_template($templateId);
        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') $title = $template['title'];
        $result = tl_create_campaign_blueprint([
            'title' => $title,
            'slug' => $input['slug'] ?? $title,
            'summary' => $input['summary'] ?? $template['summary'],
            'description' => $input['description'] ?? ($template['summary'] . ' This path was created from the Stage 50 Learning Path Planner.'),
            'campaign_type' => $template['campaign_type'] ?? 'skills',
            'visibility' => 'published',
            'status' => 'active',
            'reward_label' => $input['reward_label'] ?? $template['reward_label'],
            'owner_user_id' => max(1, (int)($input['owner_user_id'] ?? 1)),
            'created_by_user_id' => max(1, (int)($input['created_by_user_id'] ?? $input['owner_user_id'] ?? 1)),
            'tasks' => $template['tasks'],
        ]);
        $event = tl_stage50_event_insert(max(1, (int)($input['created_by_user_id'] ?? 1)), 'campaign', (int)($result['campaign_id'] ?? 0), 'learning_path_created', ['template_id' => $templateId, 'task_count' => count($template['tasks'])]);
        return $result + ['template_id' => $templateId, 'learning_event' => $event, 'stage' => 'stage50_learning_path'];
    }
}

if (!function_exists('tl_stage50_save_resource_note')) {
    function tl_stage50_save_resource_note(array $input): array
    {
        $campaignRef = (string)($input['campaign'] ?? $input['campaign_id'] ?? tl_app_default_campaign_ref());
        $userId = max(1, (int)($input['user_id'] ?? 1));
        $resourceTitle = trim((string)($input['resource_title'] ?? 'Training Lab resource'));
        $note = trim((string)($input['resource_note'] ?? ''));
        if ($note === '') throw new RuntimeException('Resource note cannot be empty.');
        $campaignId = null;
        $pdo = tl_require_db();
        $campaign = tl_find_campaign_row($pdo, $campaignRef);
        if ($campaign) $campaignId = (int)$campaign['id'];
        return tl_stage50_event_insert($userId, $campaignId ? 'campaign' : 'system', $campaignId, 'resource_note_saved', [
            'campaign_ref' => $campaignRef,
            'resource_title' => substr($resourceTitle, 0, 180),
            'resource_note' => substr($note, 0, 2000),
            'training_only' => true,
        ]);
    }
}

if (!function_exists('tl_stage50_send_training_message')) {
    function tl_stage50_send_training_message(array $input): array
    {
        $actorUserId = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        $recipientUserId = max(0, (int)($input['recipient_user_id'] ?? 0));
        $campaignRef = (string)($input['campaign'] ?? $input['campaign_id'] ?? tl_app_default_campaign_ref());
        $topic = trim((string)($input['message_topic'] ?? 'Training update'));
        $message = trim((string)($input['message_body'] ?? ''));
        if ($message === '') throw new RuntimeException('Message body cannot be empty.');
        $pdo = tl_require_db();
        $campaign = tl_find_campaign_row($pdo, $campaignRef);
        $participantId = null;
        if ($campaign && $recipientUserId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM training_participants WHERE campaign_id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([(int)$campaign['id'], $recipientUserId]);
            $participantId = (int)($stmt->fetchColumn() ?: 0) ?: null;
        }
        return tl_stage50_event_insert($actorUserId, $participantId ? 'participant' : 'system', $participantId, 'training_message_sent', [
            'campaign_ref' => $campaignRef,
            'campaign_id' => $campaign ? (int)$campaign['id'] : null,
            'recipient_user_id' => $recipientUserId ?: null,
            'message_topic' => substr($topic, 0, 160),
            'message_body' => substr($message, 0, 2500),
            'delivery' => 'training_board_only_no_email_sms',
        ]);
    }
}

if (!function_exists('tl_stage50_create_report_snapshot')) {
    function tl_stage50_create_report_snapshot(array $input): array
    {
        $actorUserId = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        $label = trim((string)($input['snapshot_label'] ?? 'Training Lab report snapshot'));
        $summary = tl_app_flow_summary();
        return tl_stage50_event_insert($actorUserId, 'system', null, 'training_report_snapshot', [
            'snapshot_label' => substr($label, 0, 180),
            'counts' => $summary['counts'] ?? [],
            'mode' => $summary['mode'] ?? 'unknown',
            'table_health' => $summary['table_health'] ?? [],
            'server_file_written' => false,
        ]);
    }
}

if (!function_exists('tl_stage50_log_demo_checkpoint')) {
    function tl_stage50_log_demo_checkpoint(array $input): array
    {
        $actorUserId = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        $checkpoint = trim((string)($input['checkpoint_label'] ?? 'Demo checkpoint'));
        $note = trim((string)($input['checkpoint_note'] ?? ''));
        return tl_stage50_event_insert($actorUserId, 'system', null, 'demo_checkpoint_logged', [
            'checkpoint_label' => substr($checkpoint, 0, 180),
            'checkpoint_note' => substr($note, 0, 2000),
            'delete_or_reset_performed' => false,
            'training_only' => true,
        ]);
    }
}


if (!function_exists('tl_stage55_safe_boundaries')) {
    function tl_stage55_safe_boundaries(): array
    {
        return [
            'standalone_training_script' => true,
            'writes_only_training_tables' => true,
            'automation_plans_are_events_only' => true,
            'reminders_are_not_sent_externally' => true,
            'evidence_locker_is_text_only' => true,
            'rubrics_are_training_metadata_only' => true,
            'release_checks_do_not_deploy_or_delete' => true,
            'no_real_upload_processing' => true,
            'no_payments' => true,
            'no_wallet_balance_changes' => true,
            'no_microgifter_reward_issuing' => true,
            'no_claim_redeem_logic' => true,
            'no_email_sms_or_push_delivery' => true,
            'no_auth_gate_added' => true,
            'no_new_sql_required' => true,
        ];
    }
}

if (!function_exists('tl_stage55_event_insert')) {
    function tl_stage55_event_insert(?int $actorUserId, string $subjectType, ?int $subjectId, string $eventType, array $metadata = []): array
    {
        if (function_exists('tl_stage50_event_insert')) {
            return tl_stage50_event_insert($actorUserId, $subjectType, $subjectId, $eventType, $metadata);
        }
        $pdo = tl_require_db();
        $publicId = tl_uuid();
        $stmt = $pdo->prepare('INSERT INTO training_events (public_id, actor_user_id, subject_type, subject_id, event_type, metadata_json) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$publicId, $actorUserId, $subjectType, $subjectId, $eventType, json_encode($metadata, JSON_UNESCAPED_SLASHES)]);
        return ['event_id' => (int)$pdo->lastInsertId(), 'public_id' => $publicId, 'event_type' => $eventType, 'metadata' => $metadata];
    }
}

if (!function_exists('tl_stage55_recent')) {
    function tl_stage55_recent(array $types, int $limit = 25): array
    {
        if (function_exists('tl_stage50_recent_events_by_type')) return tl_stage50_recent_events_by_type($types, $limit);
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_events')) return [];
        $types = array_values(array_filter(array_map('strval', $types)));
        if (!$types) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($types), '?'));
            $stmt = $pdo->prepare("SELECT * FROM training_events WHERE event_type IN ($placeholders) ORDER BY created_at DESC, id DESC LIMIT " . max(1, min(100, $limit)));
            $stmt->execute($types);
            return $stmt->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_stage55_plan_templates')) {
    function tl_stage55_plan_templates(): array
    {
        return [
            'proof-review-loop' => [
                'label' => 'Proof Review Loop',
                'summary' => 'Plan the sequence from participant proof to admin review, receipt, simulated reward, and coach follow-up.',
                'steps' => ['Participant submits text proof', 'Admin reviews proof', 'Receipt is recorded', 'Simulated reward eligibility is checked', 'Coach follow-up note is logged'],
            ],
            'cohort-launch' => [
                'label' => 'Cohort Launch Plan',
                'summary' => 'Plan the training flow for launching a new cohort through launchpad, check-in, message board, and progress map.',
                'steps' => ['Create learning path', 'Invite cohort participants', 'Post welcome message', 'Run check-in', 'Review progress map'],
            ],
            'demo-readiness' => [
                'label' => 'Demo Readiness Plan',
                'summary' => 'Plan a demo sequence with seed data, campaign story, task proof, report snapshot, and release board check.',
                'steps' => ['Seed scenario', 'Create report snapshot', 'Open flow board', 'Run release check', 'Document next build risk'],
            ],
        ];
    }
}

if (!function_exists('tl_stage55_release_checks')) {
    function tl_stage55_release_checks(): array
    {
        $root = dirname(__DIR__);
        $routes = [
            'app/automation-planner.php', 'app/reminder-center.php', 'app/evidence-locker.php',
            'admin/rubric-builder.php', 'admin/release-board.php',
            'api/training/automation-planner.php', 'api/training/reminder-center.php', 'api/training/evidence-locker.php',
            'api/training/rubric-builder.php', 'api/training/release-board.php',
        ];
        $routeStatus = [];
        foreach ($routes as $route) $routeStatus[$route] = is_file($root . '/' . $route);
        $required = tl_app_required_tables_status();
        $activeGateFiles = [
            'app/index.php','app/campaigns.php','app/campaign-detail.php','app/proof-upload.php','app/rewards.php','app/wallet.php','app/sequence-tasks.php',
            'admin/index.php','admin/campaigns.php','admin/review-queue.php','admin/stage7-control.php','admin/db-health.php',
        ];
        $authGateFindings = [];
        foreach ($activeGateFiles as $rel) {
            $text = is_file($root . '/' . $rel) ? file_get_contents($root . '/' . $rel) : '';
            $authGateFindings[$rel] = strpos((string)$text, 'training-lab-auth-gate.php') === false && strpos((string)$text, 'tl_require_training_auth') === false;
        }
        return [
            'routes' => $routeStatus,
            'routes_ready' => !in_array(false, $routeStatus, true),
            'required_tables' => $required,
            'tables_ready' => !in_array(false, $required, true),
            'active_pages_without_auth_gate' => $authGateFindings,
            'auth_gate_absent' => !in_array(false, $authGateFindings, true),
            'direct_extract_root' => is_dir($root . '/admin') && is_dir($root . '/app') && is_dir($root . '/api') && is_file($root . '/index.php'),
        ];
    }
}

if (!function_exists('tl_stage55_automation_state')) {
    function tl_stage55_automation_state(string $campaignRef = '', int $userId = 1): array
    {
        return [
            'stage' => 'Stage 51 automation planner',
            'campaign_ref' => $campaignRef !== '' ? $campaignRef : tl_app_default_campaign_ref(),
            'user_id' => $userId,
            'templates' => tl_stage55_plan_templates(),
            'campaigns' => tl_app_campaign_options(),
            'recent_plans' => tl_stage55_recent(['training_plan_created'], 20),
            'safe_boundaries' => tl_stage55_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage55_reminder_state')) {
    function tl_stage55_reminder_state(string $campaignRef = '', int $userId = 1): array
    {
        return [
            'stage' => 'Stage 52 reminder center',
            'campaign_ref' => $campaignRef !== '' ? $campaignRef : tl_app_default_campaign_ref(),
            'user_id' => $userId,
            'campaigns' => tl_app_campaign_options(),
            'participants' => function_exists('tl_stage40_participant_rows') ? tl_stage40_participant_rows(80) : [],
            'reminders' => tl_stage55_recent(['training_reminder_saved'], 30),
            'safe_boundaries' => tl_stage55_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage55_evidence_state')) {
    function tl_stage55_evidence_state(string $campaignRef = '', int $userId = 1): array
    {
        return [
            'stage' => 'Stage 53 evidence locker',
            'campaign_ref' => $campaignRef !== '' ? $campaignRef : tl_app_default_campaign_ref(),
            'user_id' => $userId,
            'campaigns' => tl_app_campaign_options(),
            'context' => tl_app_participant_context($campaignRef !== '' ? $campaignRef : tl_app_default_campaign_ref(), $userId),
            'evidence_notes' => tl_stage55_recent(['evidence_note_saved'], 30),
            'safe_boundaries' => tl_stage55_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage55_rubric_state')) {
    function tl_stage55_rubric_state(): array
    {
        return [
            'stage' => 'Stage 54 rubric builder',
            'campaigns' => tl_app_campaign_options(),
            'rubrics' => tl_stage55_recent(['review_rubric_saved'], 30),
            'safe_boundaries' => tl_stage55_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage55_release_state')) {
    function tl_stage55_release_state(): array
    {
        $checks = tl_stage55_release_checks();
        return [
            'stage' => 'Stage 55 release board',
            'checks' => $checks,
            'score' => (($checks['routes_ready'] ? 25 : 0) + ($checks['tables_ready'] ? 25 : 0) + ($checks['auth_gate_absent'] ? 25 : 0) + ($checks['direct_extract_root'] ? 25 : 0)),
            'recent_release_checks' => tl_stage55_recent(['release_board_check_logged'], 30),
            'safe_boundaries' => tl_stage55_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage55_chunk_state')) {
    function tl_stage55_chunk_state(): array
    {
        return [
            'stage' => 'Stage 55 functional automation and quality suite',
            'sections' => [
                'stage51' => 'Automation Planner',
                'stage52' => 'Reminder Center',
                'stage53' => 'Evidence Locker',
                'stage54' => 'Review Rubric Builder',
                'stage55' => 'Release Board',
            ],
            'plans' => count(tl_stage55_recent(['training_plan_created'], 100)),
            'reminders' => count(tl_stage55_recent(['training_reminder_saved'], 100)),
            'evidence_notes' => count(tl_stage55_recent(['evidence_note_saved'], 100)),
            'rubrics' => count(tl_stage55_recent(['review_rubric_saved'], 100)),
            'release_checks' => count(tl_stage55_recent(['release_board_check_logged'], 100)),
            'release_score' => tl_stage55_release_state()['score'] ?? 0,
            'safe_boundaries' => tl_stage55_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage55_create_training_plan')) {
    function tl_stage55_create_training_plan(array $input): array
    {
        $templateId = preg_replace('/[^a-z0-9\-]/i', '', (string)($input['template_id'] ?? 'proof-review-loop')) ?: 'proof-review-loop';
        $templates = tl_stage55_plan_templates();
        $template = $templates[$templateId] ?? $templates['proof-review-loop'];
        $campaignRef = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref()));
        $actor = max(1, (int)($input['actor_user_id'] ?? 1));
        $title = trim((string)($input['plan_title'] ?? $template['label'])) ?: $template['label'];
        $notes = trim((string)($input['plan_notes'] ?? $template['summary']));
        return tl_stage55_event_insert($actor, 'training_plan', null, 'training_plan_created', [
            'template_id' => $templateId,
            'campaign_ref' => $campaignRef,
            'plan_title' => $title,
            'plan_notes' => $notes,
            'steps' => $template['steps'],
            'boundary' => 'Training Lab metadata event only; no automation is executed externally.',
        ]);
    }
}

if (!function_exists('tl_stage55_save_training_reminder')) {
    function tl_stage55_save_training_reminder(array $input): array
    {
        $campaignRef = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref()));
        $actor = max(1, (int)($input['actor_user_id'] ?? 1));
        $participant = max(1, (int)($input['participant_user_id'] ?? $actor));
        $label = trim((string)($input['reminder_label'] ?? 'Training reminder')) ?: 'Training reminder';
        $due = trim((string)($input['due_label'] ?? 'Next session')) ?: 'Next session';
        $body = trim((string)($input['reminder_body'] ?? 'Return to the Training Lab and complete your next task.'));
        return tl_stage55_event_insert($actor, 'training_reminder', $participant, 'training_reminder_saved', [
            'campaign_ref' => $campaignRef,
            'participant_user_id' => $participant,
            'reminder_label' => $label,
            'due_label' => $due,
            'reminder_body' => $body,
            'delivery' => 'saved-only; no email, SMS, or push sent',
        ]);
    }
}

if (!function_exists('tl_stage55_save_evidence_note')) {
    function tl_stage55_save_evidence_note(array $input): array
    {
        $campaignRef = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref()));
        $actor = max(1, (int)($input['actor_user_id'] ?? 1));
        $title = trim((string)($input['evidence_title'] ?? 'Evidence note')) ?: 'Evidence note';
        $body = trim((string)($input['evidence_body'] ?? 'Text-only evidence note for Training Lab review.'));
        return tl_stage55_event_insert($actor, 'training_evidence', $actor, 'evidence_note_saved', [
            'campaign_ref' => $campaignRef,
            'evidence_title' => $title,
            'evidence_body' => $body,
            'format' => 'text-only',
            'upload_processing' => 'none',
        ]);
    }
}

if (!function_exists('tl_stage55_save_review_rubric')) {
    function tl_stage55_save_review_rubric(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? 1));
        $campaignRef = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref()));
        $name = trim((string)($input['rubric_name'] ?? 'Proof Review Rubric')) ?: 'Proof Review Rubric';
        $criteria = trim((string)($input['criteria'] ?? "Specificity\nTask alignment\nReview clarity\nTraining boundary"));
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $criteria) ?: [])));
        return tl_stage55_event_insert($actor, 'review_rubric', null, 'review_rubric_saved', [
            'campaign_ref' => $campaignRef,
            'rubric_name' => $name,
            'criteria' => $lines,
            'storage' => 'training event metadata only',
        ]);
    }
}

if (!function_exists('tl_stage55_log_release_check')) {
    function tl_stage55_log_release_check(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? 1));
        $state = tl_stage55_release_state();
        $notes = trim((string)($input['release_notes'] ?? 'Stage 55 release check completed.'));
        return tl_stage55_event_insert($actor, 'release_board', null, 'release_board_check_logged', [
            'score' => $state['score'] ?? 0,
            'notes' => $notes,
            'checks' => $state['checks'] ?? [],
            'deployment' => 'none; Training Lab check event only',
        ]);
    }
}


if (!function_exists('tl_stage70_safe_boundaries')) {
    function tl_stage70_safe_boundaries(): array
    {
        return [
            'standalone_training_script' => true,
            'writes_only_training_events_or_training_tables' => true,
            'no_real_upload_processing' => true,
            'no_payments' => true,
            'no_wallet_balance_changes' => true,
            'no_microgifter_reward_issuing' => true,
            'no_claim_redeem_logic' => true,
            'no_real_email_sms_push' => true,
            'no_auth_gate_added' => true,
            'no_new_sql_required' => true,
        ];
    }
}

if (!function_exists('tl_stage70_recent')) {
    function tl_stage70_recent(array $types, int $limit = 25): array
    {
        if (function_exists('tl_stage55_recent')) return tl_stage55_recent($types, $limit);
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_events')) return [];
        $types = array_values(array_filter(array_map('strval', $types)));
        if (!$types) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($types), '?'));
            $stmt = $pdo->prepare("SELECT * FROM training_events WHERE event_type IN ($placeholders) ORDER BY created_at DESC, id DESC LIMIT " . max(1, min(100, $limit)));
            $stmt->execute($types);
            return $stmt->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_stage70_domain_templates')) {
    function tl_stage70_domain_templates(): array
    {
        return [
            'proof_quality' => 'Proof Quality',
            'consistency' => 'Consistency',
            'communication' => 'Communication',
            'coachability' => 'Coachability',
            'completion_velocity' => 'Completion Velocity',
            'team_support' => 'Team Support',
        ];
    }
}

if (!function_exists('tl_stage70_knowledge_questions')) {
    function tl_stage70_knowledge_questions(): array
    {
        return [
            'boundaries' => 'Which systems are intentionally blocked from this standalone Training Lab?',
            'proof' => 'What should a useful proof submission include?',
            'reviews' => 'What should an admin review decision make clear?',
            'rewards' => 'Why are reward events simulated instead of issued to a wallet?',
            'qa' => 'What should be checked before the next build package is uploaded?',
        ];
    }
}

if (!function_exists('tl_stage70_event_state')) {
    function tl_stage70_event_state(string $stage, array $eventTypes, array $extra = []): array
    {
        return array_merge([
            'stage' => $stage,
            'campaigns' => tl_app_campaign_options(),
            'participants' => function_exists('tl_stage40_participant_rows') ? tl_stage40_participant_rows(100) : [],
            'recent_events' => tl_stage70_recent($eventTypes, 50),
            'safe_boundaries' => tl_stage70_safe_boundaries(),
        ], $extra);
    }
}

if (!function_exists('tl_stage70_skill_matrix_state')) {
    function tl_stage70_skill_matrix_state(string $campaignRef = '', int $userId = 1): array
    {
        return tl_stage70_event_state('Stage 56 skill matrix', ['skill_rating_saved'], [
            'campaign_ref' => $campaignRef ?: tl_app_default_campaign_ref(),
            'user_id' => $userId,
            'domains' => tl_stage70_domain_templates(),
        ]);
    }
}

if (!function_exists('tl_stage70_assessment_state')) {
    function tl_stage70_assessment_state(string $campaignRef = '', int $userId = 1): array
    {
        return tl_stage70_event_state('Stage 57 assessment center', ['assessment_response_submitted'], [
            'campaign_ref' => $campaignRef ?: tl_app_default_campaign_ref(),
            'user_id' => $userId,
            'questions' => [
                'readiness' => 'How ready is the participant for the next training task?',
                'confidence' => 'What confidence score should be recorded?',
                'blocker' => 'What blocker needs attention before the next proof?',
            ],
        ]);
    }
}

if (!function_exists('tl_stage70_goal_planner_state')) {
    function tl_stage70_goal_planner_state(string $campaignRef = '', int $userId = 1): array
    {
        return tl_stage70_event_state('Stage 58 goal planner', ['goal_plan_created'], [
            'campaign_ref' => $campaignRef ?: tl_app_default_campaign_ref(),
            'user_id' => $userId,
            'goal_types' => ['completion' => 'Completion', 'quality' => 'Quality', 'streak' => 'Streak', 'communication' => 'Communication'],
        ]);
    }
}

if (!function_exists('tl_stage70_badge_studio_state')) {
    function tl_stage70_badge_studio_state(): array
    {
        return tl_stage70_event_state('Stage 59 badge studio', ['badge_blueprint_saved'], [
            'badge_types' => ['completion' => 'Completion badge', 'streak' => 'Streak badge', 'coach' => 'Coach recognition', 'team' => 'Team support badge'],
        ]);
    }
}

if (!function_exists('tl_stage70_team_pulse_state')) {
    function tl_stage70_team_pulse_state(string $campaignRef = '', int $userId = 1): array
    {
        return tl_stage70_event_state('Stage 60 team pulse', ['team_pulse_submitted'], [
            'campaign_ref' => $campaignRef ?: tl_app_default_campaign_ref(),
            'user_id' => $userId,
            'pulse_scale' => ['1' => 'Blocked', '2' => 'Needs help', '3' => 'Steady', '4' => 'Strong', '5' => 'Excellent'],
        ]);
    }
}

if (!function_exists('tl_stage70_calendar_state')) {
    function tl_stage70_calendar_state(string $campaignRef = '', int $userId = 1): array
    {
        return tl_stage70_event_state('Stage 61 cohort calendar', ['calendar_marker_added'], [
            'campaign_ref' => $campaignRef ?: tl_app_default_campaign_ref(),
            'user_id' => $userId,
            'marker_types' => ['session' => 'Training session', 'review' => 'Review window', 'checkpoint' => 'Checkpoint', 'demo' => 'Demo milestone'],
        ]);
    }
}

if (!function_exists('tl_stage70_mentor_notes_state')) {
    function tl_stage70_mentor_notes_state(string $campaignRef = '', int $userId = 1): array
    {
        return tl_stage70_event_state('Stage 62 mentor notes', ['mentor_note_saved'], [
            'campaign_ref' => $campaignRef ?: tl_app_default_campaign_ref(),
            'user_id' => $userId,
        ]);
    }
}

if (!function_exists('tl_stage70_feedback_state')) {
    function tl_stage70_feedback_state(string $campaignRef = '', int $userId = 1): array
    {
        return tl_stage70_event_state('Stage 63 feedback inbox', ['feedback_item_submitted'], [
            'campaign_ref' => $campaignRef ?: tl_app_default_campaign_ref(),
            'user_id' => $userId,
            'feedback_types' => ['question' => 'Question', 'blocker' => 'Blocker', 'idea' => 'Idea', 'risk' => 'Risk'],
        ]);
    }
}

if (!function_exists('tl_stage70_sprint_state')) {
    function tl_stage70_sprint_state(string $campaignRef = '', int $userId = 1): array
    {
        return tl_stage70_event_state('Stage 64 sprint board', ['sprint_item_created'], [
            'campaign_ref' => $campaignRef ?: tl_app_default_campaign_ref(),
            'user_id' => $userId,
            'lanes' => ['todo' => 'To Do', 'doing' => 'Doing', 'review' => 'Review', 'done' => 'Done'],
        ]);
    }
}

if (!function_exists('tl_stage70_knowledge_state')) {
    function tl_stage70_knowledge_state(string $campaignRef = '', int $userId = 1): array
    {
        return tl_stage70_event_state('Stage 65 knowledge checks', ['knowledge_check_submitted'], [
            'campaign_ref' => $campaignRef ?: tl_app_default_campaign_ref(),
            'user_id' => $userId,
            'questions' => tl_stage70_knowledge_questions(),
        ]);
    }
}

if (!function_exists('tl_stage70_review_analytics_state')) {
    function tl_stage70_review_analytics_state(): array
    {
        $summary = tl_app_flow_summary();
        return tl_stage70_event_state('Stage 66 review analytics', ['proof_reviewed','review_rubric_saved','assessment_response_submitted'], [
            'counts' => $summary['counts'] ?? [],
            'recent_reviews' => tl_app_recent_reviews(40),
        ]);
    }
}

if (!function_exists('tl_stage70_certificate_verify_state')) {
    function tl_stage70_certificate_verify_state(): array
    {
        return tl_stage70_event_state('Stage 67 certificate verification', ['participant_certificate_finalized','certificate_verification_checked'], [
            'participants' => function_exists('tl_stage40_participant_rows') ? tl_stage40_participant_rows(150) : [],
        ]);
    }
}

if (!function_exists('tl_stage70_demo_narrative_state')) {
    function tl_stage70_demo_narrative_state(): array
    {
        return tl_stage70_event_state('Stage 68 demo narrative builder', ['demo_narrative_saved','training_scenario_seeded'], [
            'story_types' => ['investor' => 'Investor demo', 'merchant' => 'Merchant demo', 'coach' => 'Coach demo', 'qa' => 'QA walkthrough'],
        ]);
    }
}

if (!function_exists('tl_stage70_operator_playbook_state')) {
    function tl_stage70_operator_playbook_state(): array
    {
        return tl_stage70_event_state('Stage 69 operator playbook', ['operator_playbook_saved','release_board_check_logged'], [
            'playbook_types' => ['upload' => 'Upload/extract QA', 'demo' => 'Demo runbook', 'review' => 'Review operations', 'support' => 'Support checklist'],
        ]);
    }
}

if (!function_exists('tl_stage70_launch_readiness_state')) {
    function tl_stage70_launch_readiness_state(): array
    {
        $root = dirname(__DIR__);
        $routes = [
            'app/skill-matrix.php','app/assessment-center.php','app/goal-planner.php','app/badge-studio.php','app/team-pulse.php','app/cohort-calendar.php','app/mentor-notes.php','app/feedback-inbox.php','app/sprint-board.php','app/knowledge-checks.php',
            'admin/review-analytics.php','admin/certificate-verify.php','admin/demo-narrative.php','admin/operator-playbook.php','admin/launch-readiness.php',
            'api/training/skill-matrix.php','api/training/assessment-center.php','api/training/goal-planner.php','api/training/badge-studio.php','api/training/team-pulse.php','api/training/cohort-calendar.php','api/training/mentor-notes.php','api/training/feedback-inbox.php','api/training/sprint-board.php','api/training/knowledge-checks.php','api/training/review-analytics.php','api/training/certificate-verify.php','api/training/demo-narrative.php','api/training/operator-playbook.php','api/training/launch-readiness.php',
        ];
        $routeStatus = [];
        foreach ($routes as $route) $routeStatus[$route] = is_file($root . '/' . $route);
        $tables = tl_app_required_tables_status();
        $score = 0;
        $score += !in_array(false, $routeStatus, true) ? 35 : 0;
        $score += !in_array(false, $tables, true) ? 35 : 0;
        $score += (is_dir($root . '/admin') && is_dir($root . '/app') && is_dir($root . '/api') && is_file($root . '/index.php')) ? 15 : 0;
        $score += function_exists('tl_stage70_safe_boundaries') ? 15 : 0;
        return tl_stage70_event_state('Stage 70 launch readiness hub', ['launch_readiness_logged'], [
            'routes' => $routeStatus,
            'routes_ready' => !in_array(false, $routeStatus, true),
            'tables' => $tables,
            'tables_ready' => !in_array(false, $tables, true),
            'direct_extract_root' => is_dir($root . '/admin') && is_dir($root . '/app') && is_dir($root . '/api') && is_file($root . '/index.php'),
            'score' => $score,
        ]);
    }
}

if (!function_exists('tl_stage70_chunk_state')) {
    function tl_stage70_chunk_state(): array
    {
        $events = [
            'skill_ratings' => ['skill_rating_saved'],
            'assessments' => ['assessment_response_submitted'],
            'goals' => ['goal_plan_created'],
            'badges' => ['badge_blueprint_saved'],
            'pulses' => ['team_pulse_submitted'],
            'calendar_markers' => ['calendar_marker_added'],
            'mentor_notes' => ['mentor_note_saved'],
            'feedback_items' => ['feedback_item_submitted'],
            'sprint_items' => ['sprint_item_created'],
            'knowledge_checks' => ['knowledge_check_submitted'],
            'demo_narratives' => ['demo_narrative_saved'],
            'operator_playbooks' => ['operator_playbook_saved'],
            'launch_readiness_logs' => ['launch_readiness_logged'],
        ];
        $counts = [];
        foreach ($events as $key => $types) $counts[$key] = count(tl_stage70_recent($types, 100));
        return [
            'stage' => 'Stage 70 functional training operating system suite',
            'sections' => [
                'stage56' => 'Skill Matrix', 'stage57' => 'Assessment Center', 'stage58' => 'Goal Planner', 'stage59' => 'Badge Studio', 'stage60' => 'Team Pulse',
                'stage61' => 'Cohort Calendar', 'stage62' => 'Mentor Notes', 'stage63' => 'Feedback Inbox', 'stage64' => 'Sprint Board', 'stage65' => 'Knowledge Checks',
                'stage66' => 'Review Analytics', 'stage67' => 'Certificate Verification', 'stage68' => 'Demo Narrative Builder', 'stage69' => 'Operator Playbook', 'stage70' => 'Launch Readiness Hub',
            ],
            'counts' => $counts,
            'readiness_score' => tl_stage70_launch_readiness_state()['score'] ?? 0,
            'safe_boundaries' => tl_stage70_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage70_clean_text')) {
    function tl_stage70_clean_text(string $value, int $max = 2000): string
    {
        $value = trim(strip_tags($value));
        if (function_exists('mb_substr')) return mb_substr($value, 0, $max);
        return substr($value, 0, $max);
    }
}

if (!function_exists('tl_stage70_save_skill_rating')) {
    function tl_stage70_save_skill_rating(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        $domain = preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['skill_domain'] ?? 'proof_quality')) ?: 'proof_quality';
        $score = max(1, min(5, (int)($input['skill_score'] ?? 3)));
        return tl_stage55_event_insert($actor, 'skill_matrix', $actor, 'skill_rating_saved', [
            'campaign_ref' => preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref())),
            'skill_domain' => $domain,
            'skill_label' => tl_stage70_domain_templates()[$domain] ?? $domain,
            'skill_score' => $score,
            'notes' => tl_stage70_clean_text((string)($input['skill_notes'] ?? ''), 1000),
            'stage' => 'stage56_skill_matrix',
        ]);
    }
}

if (!function_exists('tl_stage70_submit_assessment_response')) {
    function tl_stage70_submit_assessment_response(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        return tl_stage55_event_insert($actor, 'assessment', $actor, 'assessment_response_submitted', [
            'campaign_ref' => preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref())),
            'readiness' => max(1, min(5, (int)($input['readiness_score'] ?? 3))),
            'confidence' => max(1, min(5, (int)($input['confidence_score'] ?? 3))),
            'blocker' => tl_stage70_clean_text((string)($input['blocker_note'] ?? ''), 1200),
            'next_step' => tl_stage70_clean_text((string)($input['next_step'] ?? ''), 1200),
            'stage' => 'stage57_assessment_center',
        ]);
    }
}

if (!function_exists('tl_stage70_create_goal_plan')) {
    function tl_stage70_create_goal_plan(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        return tl_stage55_event_insert($actor, 'goal_plan', $actor, 'goal_plan_created', [
            'campaign_ref' => preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref())),
            'goal_type' => preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['goal_type'] ?? 'completion')),
            'goal_title' => tl_stage70_clean_text((string)($input['goal_title'] ?? 'Complete the next training milestone'), 180),
            'goal_notes' => tl_stage70_clean_text((string)($input['goal_notes'] ?? ''), 1500),
            'target_label' => tl_stage70_clean_text((string)($input['target_label'] ?? 'This week'), 120),
            'stage' => 'stage58_goal_planner',
        ]);
    }
}

if (!function_exists('tl_stage70_design_badge_blueprint')) {
    function tl_stage70_design_badge_blueprint(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? 1));
        return tl_stage55_event_insert($actor, 'badge_blueprint', null, 'badge_blueprint_saved', [
            'campaign_ref' => preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref())),
            'badge_type' => preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['badge_type'] ?? 'completion')),
            'badge_label' => tl_stage70_clean_text((string)($input['badge_label'] ?? 'Training Completion Badge'), 180),
            'criteria' => tl_stage70_clean_text((string)($input['badge_criteria'] ?? 'Complete the training path and receive admin review.'), 1500),
            'wallet_write' => false,
            'real_reward_issue' => false,
            'stage' => 'stage59_badge_studio',
        ]);
    }
}

if (!function_exists('tl_stage70_submit_team_pulse')) {
    function tl_stage70_submit_team_pulse(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        return tl_stage55_event_insert($actor, 'team_pulse', $actor, 'team_pulse_submitted', [
            'campaign_ref' => preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref())),
            'pulse_score' => max(1, min(5, (int)($input['pulse_score'] ?? 3))),
            'pulse_note' => tl_stage70_clean_text((string)($input['pulse_note'] ?? ''), 1200),
            'needs_support' => !empty($input['needs_support']),
            'stage' => 'stage60_team_pulse',
        ]);
    }
}

if (!function_exists('tl_stage70_add_calendar_marker')) {
    function tl_stage70_add_calendar_marker(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        return tl_stage55_event_insert($actor, 'calendar_marker', null, 'calendar_marker_added', [
            'campaign_ref' => preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref())),
            'marker_type' => preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['marker_type'] ?? 'session')),
            'marker_label' => tl_stage70_clean_text((string)($input['marker_label'] ?? 'Training checkpoint'), 180),
            'marker_when' => tl_stage70_clean_text((string)($input['marker_when'] ?? 'Next session'), 120),
            'calendar_delivery' => 'saved-only; no external calendar invite sent',
            'stage' => 'stage61_cohort_calendar',
        ]);
    }
}

if (!function_exists('tl_stage70_save_mentor_note')) {
    function tl_stage70_save_mentor_note(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? 1));
        $participant = max(1, (int)($input['participant_user_id'] ?? $actor));
        return tl_stage55_event_insert($actor, 'mentor_note', $participant, 'mentor_note_saved', [
            'campaign_ref' => preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref())),
            'participant_user_id' => $participant,
            'note_title' => tl_stage70_clean_text((string)($input['note_title'] ?? 'Mentor note'), 180),
            'note_body' => tl_stage70_clean_text((string)($input['note_body'] ?? ''), 2000),
            'stage' => 'stage62_mentor_notes',
        ]);
    }
}

if (!function_exists('tl_stage70_submit_feedback_item')) {
    function tl_stage70_submit_feedback_item(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        return tl_stage55_event_insert($actor, 'feedback_item', $actor, 'feedback_item_submitted', [
            'campaign_ref' => preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref())),
            'feedback_type' => preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['feedback_type'] ?? 'question')),
            'feedback_title' => tl_stage70_clean_text((string)($input['feedback_title'] ?? 'Training feedback'), 180),
            'feedback_body' => tl_stage70_clean_text((string)($input['feedback_body'] ?? ''), 2000),
            'delivery' => 'Training Lab event only; no external ticket created',
            'stage' => 'stage63_feedback_inbox',
        ]);
    }
}

if (!function_exists('tl_stage70_create_sprint_item')) {
    function tl_stage70_create_sprint_item(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? 1));
        return tl_stage55_event_insert($actor, 'sprint_item', null, 'sprint_item_created', [
            'campaign_ref' => preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref())),
            'lane' => preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['lane'] ?? 'todo')),
            'item_title' => tl_stage70_clean_text((string)($input['item_title'] ?? 'Training sprint item'), 180),
            'item_notes' => tl_stage70_clean_text((string)($input['item_notes'] ?? ''), 1500),
            'stage' => 'stage64_sprint_board',
        ]);
    }
}

if (!function_exists('tl_stage70_submit_knowledge_check')) {
    function tl_stage70_submit_knowledge_check(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        $question = preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['question_id'] ?? 'boundaries')) ?: 'boundaries';
        $answer = tl_stage70_clean_text((string)($input['answer_text'] ?? ''), 2000);
        $score = strlen($answer) >= 50 ? 1 : 0;
        return tl_stage55_event_insert($actor, 'knowledge_check', $actor, 'knowledge_check_submitted', [
            'campaign_ref' => preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref())),
            'question_id' => $question,
            'question' => tl_stage70_knowledge_questions()[$question] ?? $question,
            'answer_text' => $answer,
            'auto_score' => $score,
            'review_needed' => true,
            'stage' => 'stage65_knowledge_checks',
        ]);
    }
}

if (!function_exists('tl_stage70_save_demo_narrative')) {
    function tl_stage70_save_demo_narrative(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? 1));
        return tl_stage55_event_insert($actor, 'demo_narrative', null, 'demo_narrative_saved', [
            'story_type' => preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['story_type'] ?? 'merchant')),
            'story_title' => tl_stage70_clean_text((string)($input['story_title'] ?? 'Training Lab demo story'), 180),
            'story_body' => tl_stage70_clean_text((string)($input['story_body'] ?? ''), 3000),
            'stage' => 'stage68_demo_narrative',
        ]);
    }
}

if (!function_exists('tl_stage70_save_operator_playbook')) {
    function tl_stage70_save_operator_playbook(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? 1));
        return tl_stage55_event_insert($actor, 'operator_playbook', null, 'operator_playbook_saved', [
            'playbook_type' => preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['playbook_type'] ?? 'demo')),
            'playbook_title' => tl_stage70_clean_text((string)($input['playbook_title'] ?? 'Training Lab operator playbook'), 180),
            'playbook_steps' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($input['playbook_steps'] ?? 'Open QA center\nRun demo scenario\nReview flow board')) ?: []))),
            'stage' => 'stage69_operator_playbook',
        ]);
    }
}

if (!function_exists('tl_stage70_log_launch_readiness')) {
    function tl_stage70_log_launch_readiness(array $input): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? 1));
        $state = tl_stage70_launch_readiness_state();
        return tl_stage55_event_insert($actor, 'launch_readiness', null, 'launch_readiness_logged', [
            'score' => $state['score'] ?? 0,
            'routes_ready' => $state['routes_ready'] ?? false,
            'tables_ready' => $state['tables_ready'] ?? false,
            'direct_extract_root' => $state['direct_extract_root'] ?? false,
            'notes' => tl_stage70_clean_text((string)($input['readiness_notes'] ?? 'Stage 70 readiness check logged.'), 1500),
            'stage' => 'stage70_launch_readiness',
        ]);
    }
}


if (!function_exists('tl_stage90_sections')) {
    function tl_stage90_sections(): array
    {
        return [
            'guided-onboarding' => ['area' => 'app', 'slug' => 'guided-onboarding', 'title' => "Guided Onboarding", 'stage' => 'Stage 71', 'summary' => "Capture the participant starting profile and onboarding commitments.", 'action' => 'save_guided_onboarding', 'button' => "Save Onboarding Plan", 'event_type' => 'onboarding_plan_saved', 'fields' => ["campaign_ref", "participant_label", "readiness_note", "commitment_text"]],
            'daily-agenda' => ['area' => 'app', 'slug' => 'daily-agenda', 'title' => "Daily Agenda", 'stage' => 'Stage 72', 'summary' => "Plan the participant day and queue a Training Lab agenda record.", 'action' => 'save_daily_agenda', 'button' => "Save Daily Agenda", 'event_type' => 'daily_agenda_saved', 'fields' => ["campaign_ref", "agenda_title", "agenda_items", "priority_note"]],
            'focus-timer' => ['area' => 'app', 'slug' => 'focus-timer', 'title' => "Focus Timer", 'stage' => 'Stage 73', 'summary' => "Record a focused practice block without running any real timer service.", 'action' => 'log_focus_block', 'button' => "Log Focus Block", 'event_type' => 'focus_block_logged', 'fields' => ["campaign_ref", "focus_label", "focus_minutes", "focus_notes"]],
            'decision-journal' => ['area' => 'app', 'slug' => 'decision-journal', 'title' => "Decision Journal", 'stage' => 'Stage 74', 'summary' => "Capture decisions, tradeoffs, and next actions during training.", 'action' => 'save_decision_journal', 'button' => "Save Decision Journal", 'event_type' => 'decision_journal_saved', 'fields' => ["campaign_ref", "decision_title", "decision_context", "next_action"]],
            'peer-review-room' => ['area' => 'app', 'slug' => 'peer-review-room', 'title' => "Peer Review Room", 'stage' => 'Stage 75', 'summary' => "Collect peer review feedback as Training Lab metadata only.", 'action' => 'submit_peer_review', 'button' => "Submit Peer Review", 'event_type' => 'peer_review_submitted', 'fields' => ["campaign_ref", "peer_label", "feedback_body", "review_score"]],
            'practice-lab' => ['area' => 'app', 'slug' => 'practice-lab', 'title' => "Practice Lab", 'stage' => 'Stage 76', 'summary' => "Run a practice exercise and save the result in the Training Lab event stream.", 'action' => 'save_practice_lab', 'button' => "Save Practice Result", 'event_type' => 'practice_lab_saved', 'fields' => ["campaign_ref", "exercise_title", "exercise_result", "improvement_note"]],
            'scenario-debrief' => ['area' => 'app', 'slug' => 'scenario-debrief', 'title' => "Scenario Debrief", 'stage' => 'Stage 77', 'summary' => "Debrief a scenario and document what changed.", 'action' => 'save_scenario_debrief', 'button' => "Save Debrief", 'event_type' => 'scenario_debrief_saved', 'fields' => ["campaign_ref", "scenario_title", "what_happened", "lesson_learned"]],
            'resource-checklist' => ['area' => 'app', 'slug' => 'resource-checklist', 'title' => "Resource Checklist", 'stage' => 'Stage 78', 'summary' => "Track resource readiness and completion notes.", 'action' => 'save_resource_checklist', 'button' => "Save Checklist", 'event_type' => 'resource_checklist_saved', 'fields' => ["campaign_ref", "resource_title", "checklist_notes", "status_label"]],
            'milestone-tracker' => ['area' => 'app', 'slug' => 'milestone-tracker', 'title' => "Milestone Tracker", 'stage' => 'Stage 79', 'summary' => "Record milestone progress and blockers.", 'action' => 'save_milestone_tracker', 'button' => "Save Milestone", 'event_type' => 'milestone_tracker_saved', 'fields' => ["campaign_ref", "milestone_title", "milestone_status", "blocker_note"]],
            'outcome-dashboard' => ['area' => 'app', 'slug' => 'outcome-dashboard', 'title' => "Outcome Dashboard", 'stage' => 'Stage 80', 'summary' => "Summarize participant outcomes as a Training Lab event snapshot.", 'action' => 'save_outcome_snapshot', 'button' => "Save Outcome Snapshot", 'event_type' => 'outcome_snapshot_saved', 'fields' => ["campaign_ref", "outcome_title", "outcome_notes", "score_label"]],
            'prompt-lab' => ['area' => 'app', 'slug' => 'prompt-lab', 'title' => "Prompt Lab", 'stage' => 'Stage 81', 'summary' => "Draft training prompts without calling external AI services.", 'action' => 'save_prompt_lab', 'button' => "Save Prompt Draft", 'event_type' => 'prompt_lab_saved', 'fields' => ["campaign_ref", "prompt_title", "prompt_text", "review_note"]],
            'habit-builder' => ['area' => 'app', 'slug' => 'habit-builder', 'title' => "Habit Builder", 'stage' => 'Stage 82', 'summary' => "Create a habit plan for repeated Training Lab activity.", 'action' => 'save_habit_builder', 'button' => "Save Habit Plan", 'event_type' => 'habit_builder_saved', 'fields' => ["campaign_ref", "habit_title", "cadence_label", "habit_notes"]],
            'content-studio' => ['area' => 'admin', 'slug' => 'content-studio', 'title' => "Content Studio", 'stage' => 'Stage 83', 'summary' => "Create reusable training content notes without publishing anywhere.", 'action' => 'save_content_studio', 'button' => "Save Content Note", 'event_type' => 'content_studio_saved', 'fields' => ["campaign_ref", "content_title", "content_body", "audience_label"]],
            'program-rules' => ['area' => 'admin', 'slug' => 'program-rules', 'title' => "Program Rules Center", 'stage' => 'Stage 84', 'summary' => "Document program rules and boundaries as Training Lab metadata.", 'action' => 'save_program_rule', 'button' => "Save Program Rule", 'event_type' => 'program_rule_saved', 'fields' => ["campaign_ref", "rule_title", "rule_body", "severity_label"]],
            'risk-register' => ['area' => 'admin', 'slug' => 'risk-register', 'title' => "Risk Register", 'stage' => 'Stage 85', 'summary' => "Track training risks, blockers, and mitigation notes.", 'action' => 'save_risk_register', 'button' => "Save Risk", 'event_type' => 'risk_register_saved', 'fields' => ["campaign_ref", "risk_title", "risk_body", "mitigation_note"]],
            'support-desk' => ['area' => 'admin', 'slug' => 'support-desk', 'title' => "Support Desk", 'stage' => 'Stage 86', 'summary' => "Log internal support tickets without external delivery.", 'action' => 'save_support_ticket', 'button' => "Save Support Ticket", 'event_type' => 'support_ticket_saved', 'fields' => ["campaign_ref", "ticket_title", "ticket_body", "priority_label"]],
            'audit-trail-plus' => ['area' => 'admin', 'slug' => 'audit-trail-plus', 'title' => "Audit Trail Plus", 'stage' => 'Stage 87', 'summary' => "Create an audit annotation tied to the existing Training Lab event feed.", 'action' => 'save_audit_annotation', 'button' => "Save Audit Annotation", 'event_type' => 'audit_annotation_saved', 'fields' => ["campaign_ref", "annotation_title", "annotation_body", "audit_scope"]],
            'backup-planner' => ['area' => 'admin', 'slug' => 'backup-planner', 'title' => "Backup Snapshot Planner", 'stage' => 'Stage 88', 'summary' => "Plan backup checkpoints without running backups or deletes.", 'action' => 'save_backup_plan', 'button' => "Save Backup Plan", 'event_type' => 'backup_plan_saved', 'fields' => ["campaign_ref", "backup_label", "backup_scope", "backup_note"]],
            'integration-sandbox' => ['area' => 'admin', 'slug' => 'integration-sandbox', 'title' => "Integration Sandbox", 'stage' => 'Stage 89', 'summary' => "Document future integration mapping without calling external APIs.", 'action' => 'save_integration_sandbox', 'button' => "Save Integration Note", 'event_type' => 'integration_sandbox_saved', 'fields' => ["campaign_ref", "integration_label", "payload_note", "boundary_note"]],
            'final-review-console' => ['area' => 'admin', 'slug' => 'final-review-console', 'title' => "Final Review Console", 'stage' => 'Stage 90', 'summary' => "Score the whole standalone Training Lab package and record launch notes.", 'action' => 'log_final_review', 'button' => "Log Final Review", 'event_type' => 'final_review_logged', 'fields' => ["campaign_ref", "review_title", "review_notes", "readiness_score"]]
        ];
    }
}

if (!function_exists('tl_stage90_section')) {
    function tl_stage90_section(string $slug): array
    {
        $sections = tl_stage90_sections();
        return $sections[$slug] ?? $sections['guided-onboarding'];
    }
}

if (!function_exists('tl_stage90_safe_boundaries')) {
    function tl_stage90_safe_boundaries(): array
    {
        $prior = function_exists('tl_stage70_safe_boundaries') ? tl_stage70_safe_boundaries() : [];
        return array_merge($prior, [
            'stage90_experience_ops_added' => true,
            'writes_training_events_only_for_new_stage90_actions' => true,
            'no_external_ai_calls' => true,
            'no_external_integrations_called' => true,
            'no_backup_or_delete_execution' => true,
            'no_real_notifications_sent' => true,
        ]);
    }
}

if (!function_exists('tl_stage90_clean')) {
    function tl_stage90_clean(string $value, int $max = 2500): string
    {
        $value = trim(strip_tags($value));
        return strlen($value) > $max ? substr($value, 0, $max) : $value;
    }
}

if (!function_exists('tl_stage90_recent')) {
    function tl_stage90_recent(array $types, int $limit = 30): array
    {
        if (function_exists('tl_stage70_recent')) return tl_stage70_recent($types, $limit);
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_events')) return [];
        $types = array_values(array_filter(array_map('strval', $types)));
        if (!$types) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($types), '?'));
            $stmt = $pdo->prepare("SELECT * FROM training_events WHERE event_type IN ($placeholders) ORDER BY created_at DESC, id DESC LIMIT " . max(1, min(100, $limit)));
            $stmt->execute($types);
            return $stmt->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_stage90_section_state')) {
    function tl_stage90_section_state(string $slug): array
    {
        $section = tl_stage90_section($slug);
        return [
            'stage' => $section['stage'],
            'section' => $section,
            'campaigns' => tl_app_campaign_options(),
            'participants' => function_exists('tl_stage40_participant_rows') ? tl_stage40_participant_rows(100) : [],
            'recent_events' => tl_stage90_recent([(string)$section['event_type']], 40),
            'related_events' => tl_stage90_recent(array_values(array_column(tl_stage90_sections(), 'event_type')), 80),
            'safe_boundaries' => tl_stage90_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage90_readiness_score')) {
    function tl_stage90_readiness_score(): int
    {
        $root = dirname(__DIR__);
        $sections = tl_stage90_sections();
        $routes = 0;
        $expected = 0;
        foreach ($sections as $slug => $section) {
            $expected += 2;
            $area = $section['area'];
            if (is_file($root . '/' . $area . '/' . $slug . '.php')) $routes++;
            if (is_file($root . '/api/training/' . $slug . '.php')) $routes++;
        }
        $score = $expected > 0 ? (int)round(($routes / $expected) * 65) : 0;
        $tables = tl_app_required_tables_status();
        if ($tables && !in_array(false, $tables, true)) $score += 20;
        if (is_file($root . '/includes/labs-layout.php')) $score += 5;
        if (is_file($root . '/includes/training-lab-app-service.php')) $score += 5;
        if (is_file($root . '/stage-71-90-functional-experience-ops-review-report.md')) $score += 5;
        return min(100, $score);
    }
}

if (!function_exists('tl_stage90_chunk_state')) {
    function tl_stage90_chunk_state(): array
    {
        $sections = tl_stage90_sections();
        $counts = [];
        foreach ($sections as $slug => $section) {
            $counts[$slug] = count(tl_stage90_recent([(string)$section['event_type']], 100));
        }
        return [
            'stage' => 'Stage 90 functional experience and operations suite',
            'sections' => array_map(function ($section) { return ['stage' => $section['stage'], 'title' => $section['title'], 'area' => $section['area'], 'slug' => $section['slug']]; }, $sections),
            'counts' => $counts,
            'readiness_score' => tl_stage90_readiness_score(),
            'recent_events' => tl_stage90_recent(array_values(array_column($sections, 'event_type')), 100),
            'safe_boundaries' => tl_stage90_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage90_save_section_record')) {
    function tl_stage90_save_section_record(string $slug, array $input): array
    {
        $section = tl_stage90_section($slug);
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        $metadata = [
            'stage' => $section['stage'],
            'section_slug' => $slug,
            'section_title' => $section['title'],
            'campaign_ref' => preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref())),
            'training_only' => true,
            'external_delivery' => 'none',
            'writes' => 'training_events metadata only for this Stage 90 action',
        ];
        foreach ((array)$section['fields'] as $field) {
            if ($field === 'campaign_ref') continue;
            $metadata[$field] = tl_stage90_clean((string)($input[$field] ?? ''), $field === 'readiness_score' || $field === 'focus_minutes' || $field === 'review_score' ? 40 : 2500);
        }
        if ($slug === 'final-review-console') {
            $metadata['computed_readiness_score'] = tl_stage90_readiness_score();
        }
        return tl_stage55_event_insert($actor, 'stage90_' . $slug, $actor, (string)$section['event_type'], $metadata);
    }
}

if (!function_exists('tl_stage90_render_workspace_page')) {
    function tl_stage90_render_workspace_page(string $slug): void
    {
        $state = tl_stage90_section_state($slug);
        $section = $state['section'];
        $isAdmin = $section['area'] === 'admin';
        $pageSection = $isAdmin ? 'admin' : 'app';
        $active = ($isAdmin ? 'admin-' : 'app-') . $section['slug'];
        labs_page_start(['title' => $section['title'] . ' | Training Lab', 'section' => $pageSection, 'active' => $active]);
        ?>
<section class="labs-page-title labs-stage90-title">
  <div>
    <span class="labs-eyebrow"><?php echo labs_e((string)$section['stage']); ?> · Stage 71–90</span>
    <h1><?php echo labs_e((string)$section['title']); ?></h1>
    <p class="labs-copy"><?php echo labs_e((string)$section['summary']); ?></p>
  </div>
  <div class="labs-actions">
    <a class="labs-btn" href="<?php echo labs_url('/app/flow-board.php'); ?>">Outcome Dashboard</a>
    <a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/qa-center.php'); ?>">Final Review</a>
  </div>
</section>
<section class="labs-flow-grid labs-stage90-grid">
  <article class="labs-card">
    <h2><?php echo labs_e((string)$section['button']); ?></h2>
    <form action="<?php echo labs_url($isAdmin ? '/admin/action-result.php' : '/app/action-result.php'); ?>" method="post" class="labs-stage30-form labs-stage90-form">
      <input type="hidden" name="confirm_training_action" value="1">
      <input type="hidden" name="training_action" value="<?php echo labs_e((string)$section['action']); ?>">
      <input type="hidden" name="actor_user_id" value="1">
      <label>Campaign
        <select name="campaign_ref"><?php foreach ($state['campaigns'] as $campaign): ?><option value="<?php echo labs_e((string)$campaign['ref']); ?>"><?php echo labs_e((string)$campaign['title']); ?></option><?php endforeach; ?></select>
      </label>
      <?php foreach ((array)$section['fields'] as $field): if ($field === 'campaign_ref') continue; $label = ucwords(str_replace('_', ' ', (string)$field)); ?>
        <label><?php echo labs_e($label); ?>
          <?php if (preg_match('/body|note|items|context|learned|text|scope|mitigation|payload|commitment|agenda|rules?/i', (string)$field)): ?>
            <textarea name="<?php echo labs_e((string)$field); ?>" rows="5"><?php echo labs_e('Training Lab note for ' . strtolower($label) . '.'); ?></textarea>
          <?php else: ?>
            <input name="<?php echo labs_e((string)$field); ?>" value="<?php echo labs_e($label . ' sample'); ?>">
          <?php endif; ?>
        </label>
      <?php endforeach; ?>
      <button class="labs-btn labs-btn-primary" type="submit"><?php echo labs_e((string)$section['button']); ?></button>
    </form>
  </article>
  <aside class="labs-card">
    <h2>Recent <?php echo labs_e((string)$section['title']); ?> records</h2>
    <div class="labs-stage90-list">
      <?php foreach ($state['recent_events'] as $event): $meta = json_decode((string)($event['metadata_json'] ?? '{}'), true) ?: []; ?>
        <div class="labs-stage90-event"><strong><?php echo labs_e((string)($meta['section_title'] ?? $event['event_type'])); ?></strong><p><?php echo labs_e((string)($meta['review_notes'] ?? $meta['commitment_text'] ?? $meta['agenda_items'] ?? $meta['decision_context'] ?? $meta['feedback_body'] ?? $meta['lesson_learned'] ?? $meta['outcome_notes'] ?? $meta['prompt_text'] ?? $meta['risk_body'] ?? $meta['ticket_body'] ?? $meta['annotation_body'] ?? $meta['payload_note'] ?? 'Training Lab record saved.')); ?></p><small><?php echo labs_e((string)($event['created_at'] ?? '')); ?></small></div>
      <?php endforeach; if (!$state['recent_events']): ?><p class="labs-muted">No records yet for this section.</p><?php endif; ?>
    </div>
  </aside>
</section>
<section class="labs-card labs-stage90-map">
  <h2>Stage 71–90 map</h2>
  <div class="labs-stage13-link-grid">
    <?php foreach (tl_stage90_sections() as $item): $href = '/' . $item['area'] . '/' . $item['slug'] . '.php'; ?>
      <a href="<?php echo labs_url($href); ?>"><span><?php echo labs_e((string)$item['title']); ?></span><strong><?php echo labs_e(str_replace('Stage ', '', (string)$item['stage'])); ?></strong></a>
    <?php endforeach; ?>
  </div>
</section>
<section class="labs-safe-note">Stage 71–90 standalone boundary: new actions write Training Lab event metadata only. No real uploads, payments, wallet changes, reward issuing, claim/redeem, external AI calls, external integrations, backups, deletes, email, SMS, or push delivery.</section>
        <?php
        labs_page_end(['section' => $pageSection]);
    }
}


if (!function_exists('tl_stage120_sections')) {
    function tl_stage120_sections(): array
    {
        return [
            'enrollment-wizard' => ['area' => 'app', 'slug' => 'enrollment-wizard', 'title' => 'Enrollment Wizard', 'stage' => 'Stage 91', 'summary' => 'Capture participant intake and program placement decisions.', 'action' => 'stage120_save_enrollment_wizard', 'button' => 'Save Enrollment Intake', 'event_type' => 'stage120_enrollment_wizard_saved', 'fields' => ['campaign_ref', 'participant_label', 'intake_goal', 'placement_note']],
            'role-simulator' => ['area' => 'app', 'slug' => 'role-simulator', 'title' => 'Role Simulator', 'stage' => 'Stage 92', 'summary' => 'Practice participant, coach, reviewer, and operator roles without changing permissions.', 'action' => 'stage120_save_role_simulator', 'button' => 'Save Role Simulation', 'event_type' => 'stage120_role_simulator_saved', 'fields' => ['campaign_ref', 'role_label', 'simulation_context', 'reflection_note']],
            'training-marketplace' => ['area' => 'app', 'slug' => 'training-marketplace', 'title' => 'Training Marketplace', 'stage' => 'Stage 93', 'summary' => 'Browse and stage training modules as internal Training Lab offers only.', 'action' => 'stage120_save_training_marketplace', 'button' => 'Save Module Selection', 'event_type' => 'stage120_training_marketplace_saved', 'fields' => ['campaign_ref', 'module_title', 'module_reason', 'expected_outcome']],
            'operator-console' => ['area' => 'app', 'slug' => 'operator-console', 'title' => 'Operator Console', 'stage' => 'Stage 94', 'summary' => 'Run the participant-side operating cockpit for daily training actions.', 'action' => 'stage120_save_operator_console', 'button' => 'Save Operator Console Note', 'event_type' => 'stage120_operator_console_saved', 'fields' => ['campaign_ref', 'operator_focus', 'current_blocker', 'next_move']],
            'live-demo-script' => ['area' => 'app', 'slug' => 'live-demo-script', 'title' => 'Live Demo Script', 'stage' => 'Stage 95', 'summary' => 'Prepare a guided script for showing the standalone Training Lab flow.', 'action' => 'stage120_save_live_demo_script', 'button' => 'Save Demo Script', 'event_type' => 'stage120_live_demo_script_saved', 'fields' => ['campaign_ref', 'script_title', 'script_steps', 'close_note']],
            'learning-contract' => ['area' => 'app', 'slug' => 'learning-contract', 'title' => 'Learning Contract', 'stage' => 'Stage 96', 'summary' => 'Record participant commitments, constraints, and completion expectations.', 'action' => 'stage120_save_learning_contract', 'button' => 'Save Learning Contract', 'event_type' => 'stage120_learning_contract_saved', 'fields' => ['campaign_ref', 'participant_label', 'commitment_terms', 'success_definition']],
            'success-plan' => ['area' => 'app', 'slug' => 'success-plan', 'title' => 'Success Plan', 'stage' => 'Stage 97', 'summary' => 'Turn the participant objective into milestones, proof, and review checkpoints.', 'action' => 'stage120_save_success_plan', 'button' => 'Save Success Plan', 'event_type' => 'stage120_success_plan_saved', 'fields' => ['campaign_ref', 'success_goal', 'milestone_notes', 'review_checkpoint']],
            'escalation-matrix' => ['area' => 'app', 'slug' => 'escalation-matrix', 'title' => 'Escalation Matrix', 'stage' => 'Stage 98', 'summary' => 'Document what happens when a participant needs help or a review is blocked.', 'action' => 'stage120_save_escalation_matrix', 'button' => 'Save Escalation Matrix', 'event_type' => 'stage120_escalation_matrix_saved', 'fields' => ['campaign_ref', 'trigger_label', 'response_plan', 'owner_note']],
            'outcome-review' => ['area' => 'app', 'slug' => 'outcome-review', 'title' => 'Outcome Review', 'stage' => 'Stage 99', 'summary' => 'Summarize what changed after a participant completed training activity.', 'action' => 'stage120_save_outcome_review', 'button' => 'Save Outcome Review', 'event_type' => 'stage120_outcome_review_saved', 'fields' => ['campaign_ref', 'outcome_label', 'evidence_summary', 'decision_note']],
            'system-tour' => ['area' => 'app', 'slug' => 'system-tour', 'title' => 'System Tour', 'stage' => 'Stage 100', 'summary' => 'Guide users through the standalone script routes and training lifecycle.', 'action' => 'stage120_save_system_tour', 'button' => 'Save Tour Checkpoint', 'event_type' => 'stage120_system_tour_saved', 'fields' => ['campaign_ref', 'tour_area', 'tour_note', 'confidence_score']],
            'practice-queue' => ['area' => 'app', 'slug' => 'practice-queue', 'title' => 'Practice Queue', 'stage' => 'Stage 101', 'summary' => 'Queue practice items for participants without sending external notifications.', 'action' => 'stage120_save_practice_queue', 'button' => 'Save Practice Queue Item', 'event_type' => 'stage120_practice_queue_saved', 'fields' => ['campaign_ref', 'practice_title', 'practice_steps', 'priority_label']],
            'evidence-review-room' => ['area' => 'app', 'slug' => 'evidence-review-room', 'title' => 'Evidence Review Room', 'stage' => 'Stage 102', 'summary' => 'Prepare text evidence for review without file uploads or media processing.', 'action' => 'stage120_save_evidence_review_room', 'button' => 'Save Evidence Review', 'event_type' => 'stage120_evidence_review_room_saved', 'fields' => ['campaign_ref', 'evidence_title', 'evidence_body', 'review_focus']],
            'cohort-scoreboard' => ['area' => 'app', 'slug' => 'cohort-scoreboard', 'title' => 'Cohort Scoreboard', 'stage' => 'Stage 103', 'summary' => 'Track cohort progress signals as Training Lab event metadata.', 'action' => 'stage120_save_cohort_scoreboard', 'button' => 'Save Scoreboard Note', 'event_type' => 'stage120_cohort_scoreboard_saved', 'fields' => ['campaign_ref', 'scoreboard_label', 'score_summary', 'cohort_note']],
            'facilitator-briefing' => ['area' => 'app', 'slug' => 'facilitator-briefing', 'title' => 'Facilitator Briefing', 'stage' => 'Stage 104', 'summary' => 'Prepare facilitator notes before a training session or demo.', 'action' => 'stage120_save_facilitator_briefing', 'button' => 'Save Facilitator Brief', 'event_type' => 'stage120_facilitator_briefing_saved', 'fields' => ['campaign_ref', 'brief_title', 'talking_points', 'risk_note']],
            'participant-directory' => ['area' => 'app', 'slug' => 'participant-directory', 'title' => 'Participant Directory', 'stage' => 'Stage 105', 'summary' => 'Maintain a Training Lab-only directory note without modifying auth users.', 'action' => 'stage120_save_participant_directory', 'button' => 'Save Directory Note', 'event_type' => 'stage120_participant_directory_saved', 'fields' => ['campaign_ref', 'participant_label', 'directory_note', 'status_label']],
            'implementation-roadmap-app' => ['area' => 'app', 'slug' => 'implementation-roadmap-app', 'title' => 'Implementation Roadmap', 'stage' => 'Stage 106', 'summary' => 'Plan participant implementation steps inside the app experience.', 'action' => 'stage120_save_implementation_roadmap_app', 'button' => 'Save Implementation Step', 'event_type' => 'stage120_implementation_roadmap_app_saved', 'fields' => ['campaign_ref', 'roadmap_step', 'owner_note', 'timeline_note']],
            'readiness-checklist' => ['area' => 'app', 'slug' => 'readiness-checklist', 'title' => 'Readiness Checklist', 'stage' => 'Stage 107', 'summary' => 'Capture participant readiness checks before final review.', 'action' => 'stage120_save_readiness_checklist', 'button' => 'Save Readiness Checklist', 'event_type' => 'stage120_readiness_checklist_saved', 'fields' => ['campaign_ref', 'checklist_title', 'checklist_items', 'readiness_note']],
            'training-retrospective' => ['area' => 'app', 'slug' => 'training-retrospective', 'title' => 'Training Retrospective', 'stage' => 'Stage 108', 'summary' => 'Capture what worked, what failed, and what to improve after a sprint.', 'action' => 'stage120_save_training_retrospective', 'button' => 'Save Retrospective', 'event_type' => 'stage120_training_retrospective_saved', 'fields' => ['campaign_ref', 'retro_title', 'worked_note', 'improve_note']],
            'admin-intake-desk' => ['area' => 'admin', 'slug' => 'admin-intake-desk', 'title' => 'Admin Intake Desk', 'stage' => 'Stage 109', 'summary' => 'Review intake signals and route participants to the correct training path.', 'action' => 'stage120_save_admin_intake_desk', 'button' => 'Save Intake Desk Note', 'event_type' => 'stage120_admin_intake_desk_saved', 'fields' => ['campaign_ref', 'intake_label', 'triage_note', 'routing_decision']],
            'workflow-composer' => ['area' => 'admin', 'slug' => 'workflow-composer', 'title' => 'Workflow Composer', 'stage' => 'Stage 110', 'summary' => 'Compose future workflow sequences as Training Lab event metadata only.', 'action' => 'stage120_save_workflow_composer', 'button' => 'Save Workflow Draft', 'event_type' => 'stage120_workflow_composer_saved', 'fields' => ['campaign_ref', 'workflow_title', 'workflow_steps', 'boundary_note']],
            'insight-console' => ['area' => 'admin', 'slug' => 'insight-console', 'title' => 'Insight Console', 'stage' => 'Stage 111', 'summary' => 'Collect operator insights from the Training Lab flow and recent records.', 'action' => 'stage120_save_insight_console', 'button' => 'Save Insight Note', 'event_type' => 'stage120_insight_console_saved', 'fields' => ['campaign_ref', 'insight_title', 'insight_body', 'recommended_action']],
            'quality-gates' => ['area' => 'admin', 'slug' => 'quality-gates', 'title' => 'Quality Gates', 'stage' => 'Stage 112', 'summary' => 'Define quality gates for proof, review, reward simulation, and release readiness.', 'action' => 'stage120_save_quality_gates', 'button' => 'Save Quality Gate', 'event_type' => 'stage120_quality_gates_saved', 'fields' => ['campaign_ref', 'gate_title', 'gate_rules', 'pass_condition']],
            'data-stewardship' => ['area' => 'admin', 'slug' => 'data-stewardship', 'title' => 'Data Stewardship', 'stage' => 'Stage 113', 'summary' => 'Document data handling expectations without moving or exposing config secrets.', 'action' => 'stage120_save_data_stewardship', 'button' => 'Save Stewardship Note', 'event_type' => 'stage120_data_stewardship_saved', 'fields' => ['campaign_ref', 'data_area', 'stewardship_note', 'boundary_label']],
            'simulator-controls' => ['area' => 'admin', 'slug' => 'simulator-controls', 'title' => 'Simulator Controls', 'stage' => 'Stage 114', 'summary' => 'Adjust simulator notes without resets, deletes, external calls, or production side effects.', 'action' => 'stage120_save_simulator_controls', 'button' => 'Save Simulator Control', 'event_type' => 'stage120_simulator_controls_saved', 'fields' => ['campaign_ref', 'control_label', 'control_note', 'expected_effect']],
            'persona-builder' => ['area' => 'admin', 'slug' => 'persona-builder', 'title' => 'Persona Builder', 'stage' => 'Stage 115', 'summary' => 'Create demo personas as text metadata for training scenarios.', 'action' => 'stage120_save_persona_builder', 'button' => 'Save Persona', 'event_type' => 'stage120_persona_builder_saved', 'fields' => ['campaign_ref', 'persona_name', 'persona_context', 'scenario_fit']],
            'content-calendar' => ['area' => 'admin', 'slug' => 'content-calendar', 'title' => 'Content Calendar', 'stage' => 'Stage 116', 'summary' => 'Plan training content cadence without publishing or external delivery.', 'action' => 'stage120_save_content_calendar', 'button' => 'Save Calendar Entry', 'event_type' => 'stage120_content_calendar_saved', 'fields' => ['campaign_ref', 'content_date', 'content_topic', 'delivery_note']],
            'rollout-planner' => ['area' => 'admin', 'slug' => 'rollout-planner', 'title' => 'Rollout Planner', 'stage' => 'Stage 117', 'summary' => 'Plan internal rollout waves for the standalone Training Lab script.', 'action' => 'stage120_save_rollout_planner', 'button' => 'Save Rollout Plan', 'event_type' => 'stage120_rollout_planner_saved', 'fields' => ['campaign_ref', 'rollout_phase', 'rollout_tasks', 'owner_note']],
            'sop-checklist' => ['area' => 'admin', 'slug' => 'sop-checklist', 'title' => 'SOP Checklist', 'stage' => 'Stage 118', 'summary' => 'Document repeatable standard operating procedures for the lab.', 'action' => 'stage120_save_sop_checklist', 'button' => 'Save SOP Checklist', 'event_type' => 'stage120_sop_checklist_saved', 'fields' => ['campaign_ref', 'sop_title', 'sop_steps', 'verification_note']],
            'partner-enablement' => ['area' => 'admin', 'slug' => 'partner-enablement', 'title' => 'Partner Enablement', 'stage' => 'Stage 119', 'summary' => 'Prepare partner-facing enablement notes without sending anything externally.', 'action' => 'stage120_save_partner_enablement', 'button' => 'Save Partner Enablement Note', 'event_type' => 'stage120_partner_enablement_saved', 'fields' => ['campaign_ref', 'partner_label', 'enablement_body', 'followup_note']],
            'master-control-room' => ['area' => 'admin', 'slug' => 'master-control-room', 'title' => 'Master Control Room', 'stage' => 'Stage 120', 'summary' => 'Consolidate Stage 91–120 readiness, routes, records, and operating notes.', 'action' => 'stage120_save_master_control_room', 'button' => 'Save Master Control Review', 'event_type' => 'stage120_master_control_room_saved', 'fields' => ['campaign_ref', 'control_title', 'control_notes', 'readiness_score']],
        ];
    }
}

if (!function_exists('tl_stage120_section')) {
    function tl_stage120_section(string $slug): array
    {
        $sections = tl_stage120_sections();
        return $sections[$slug] ?? $sections['enrollment-wizard'];
    }
}

if (!function_exists('tl_stage120_resolve_action')) {
    function tl_stage120_resolve_action(string $action): ?string
    {
        foreach (tl_stage120_sections() as $slug => $section) {
            if ((string)($section['action'] ?? '') === $action) return (string)$slug;
        }
        return null;
    }
}

if (!function_exists('tl_stage120_safe_boundaries')) {
    function tl_stage120_safe_boundaries(): array
    {
        $prior = function_exists('tl_stage90_safe_boundaries') ? tl_stage90_safe_boundaries() : [];
        return array_merge($prior, [
            'stage120_mastery_deployment_simulation_added' => true,
            'writes_training_events_only_for_new_stage120_actions' => true,
            'no_real_enrollment_or_auth_user_changes' => true,
            'no_real_marketplace_or_commerce_actions' => true,
            'no_external_publishing_or_partner_delivery' => true,
            'no_external_integrations_called' => true,
            'no_backups_resets_deletes_or_config_changes' => true,
        ]);
    }
}

if (!function_exists('tl_stage120_clean')) {
    function tl_stage120_clean(string $value, int $max = 3000): string
    {
        $value = trim(strip_tags($value));
        return strlen($value) > $max ? substr($value, 0, $max) : $value;
    }
}

if (!function_exists('tl_stage120_recent')) {
    function tl_stage120_recent(array $types, int $limit = 50): array
    {
        if (function_exists('tl_stage90_recent')) return tl_stage90_recent($types, $limit);
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_events')) return [];
        $types = array_values(array_filter(array_map('strval', $types)));
        if (!$types) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($types), '?'));
            $stmt = $pdo->prepare("SELECT * FROM training_events WHERE event_type IN ($placeholders) ORDER BY created_at DESC, id DESC LIMIT " . max(1, min(200, $limit)));
            $stmt->execute($types);
            return $stmt->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_stage120_section_state')) {
    function tl_stage120_section_state(string $slug): array
    {
        $section = tl_stage120_section($slug);
        return [
            'stage' => $section['stage'],
            'section' => $section,
            'campaigns' => tl_app_campaign_options(),
            'participants' => function_exists('tl_stage40_participant_rows') ? tl_stage40_participant_rows(150) : [],
            'recent_events' => tl_stage120_recent([(string)$section['event_type']], 50),
            'related_events' => tl_stage120_recent(array_values(array_column(tl_stage120_sections(), 'event_type')), 120),
            'summary' => tl_app_flow_summary(),
            'stage90' => function_exists('tl_stage90_chunk_state') ? tl_stage90_chunk_state() : [],
            'safe_boundaries' => tl_stage120_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage120_readiness_score')) {
    function tl_stage120_readiness_score(): int
    {
        $root = dirname(__DIR__);
        $sections = tl_stage120_sections();
        $routes = 0;
        $expected = 0;
        foreach ($sections as $slug => $section) {
            $expected += 2;
            $area = (string)$section['area'];
            if (is_file($root . '/' . $area . '/' . $slug . '.php')) $routes++;
            if (is_file($root . '/api/training/' . $slug . '.php')) $routes++;
        }
        $score = $expected > 0 ? (int)round(($routes / $expected) * 65) : 0;
        $tables = tl_app_required_tables_status();
        if ($tables && !in_array(false, $tables, true)) $score += 20;
        if (is_file($root . '/includes/labs-layout.php')) $score += 4;
        if (is_file($root . '/includes/training-lab-app-service.php')) $score += 4;
        if (is_file($root . '/stage-91-120-functional-mastery-deployment-review-report.md')) $score += 7;
        return min(100, $score);
    }
}

if (!function_exists('tl_stage120_chunk_state')) {
    function tl_stage120_chunk_state(): array
    {
        $sections = tl_stage120_sections();
        $counts = [];
        foreach ($sections as $slug => $section) {
            $counts[$slug] = count(tl_stage120_recent([(string)$section['event_type']], 200));
        }
        return [
            'stage' => 'Stage 120 functional mastery and deployment simulation suite',
            'sections' => array_map(function ($section) { return ['stage' => $section['stage'], 'title' => $section['title'], 'area' => $section['area'], 'slug' => $section['slug']]; }, $sections),
            'counts' => $counts,
            'readiness_score' => tl_stage120_readiness_score(),
            'recent_events' => tl_stage120_recent(array_values(array_column($sections, 'event_type')), 160),
            'safe_boundaries' => tl_stage120_safe_boundaries(),
        ];
    }
}

if (!function_exists('tl_stage120_save_section_record')) {
    function tl_stage120_save_section_record(string $slug, array $input): array
    {
        $section = tl_stage120_section($slug);
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        $metadata = [
            'stage' => $section['stage'],
            'section_slug' => $slug,
            'section_title' => $section['title'],
            'campaign_ref' => preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($input['campaign_ref'] ?? tl_app_default_campaign_ref())),
            'training_only' => true,
            'external_delivery' => 'none',
            'writes' => 'training_events metadata only for this Stage 120 action',
            'no_production_side_effects' => true,
        ];
        foreach ((array)$section['fields'] as $field) {
            if ($field === 'campaign_ref') continue;
            $metadata[$field] = tl_stage120_clean((string)($input[$field] ?? ''), preg_match('/score|minutes|date/i', (string)$field) ? 80 : 3000);
        }
        if ($slug === 'master-control-room') {
            $metadata['computed_readiness_score'] = tl_stage120_readiness_score();
        }
        return tl_stage55_event_insert($actor, 'stage120_' . $slug, $actor, (string)$section['event_type'], $metadata);
    }
}

if (!function_exists('tl_stage120_render_workspace_page')) {
    function tl_stage120_render_workspace_page(string $slug): void
    {
        $state = tl_stage120_section_state($slug);
        $section = $state['section'];
        $isAdmin = $section['area'] === 'admin';
        $pageSection = $isAdmin ? 'admin' : 'app';
        $active = ($isAdmin ? 'admin-' : 'app-') . $section['slug'];
        labs_page_start(['title' => $section['title'] . ' | Training Lab', 'section' => $pageSection, 'active' => $active]);
        ?>
<section class="labs-page-title labs-stage120-title">
  <div>
    <span class="labs-eyebrow"><?php echo labs_e((string)$section['stage']); ?> · Stage 91–120</span>
    <h1><?php echo labs_e((string)$section['title']); ?></h1>
    <p class="labs-copy"><?php echo labs_e((string)$section['summary']); ?></p>
  </div>
  <div class="labs-actions">
    <a class="labs-btn" href="<?php echo labs_url('/app/launchpad.php'); ?>">Enrollment Wizard</a>
    <a class="labs-btn" href="<?php echo labs_url('/app/task-runner.php'); ?>">Practice Queue</a>
    <a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/command-center.php'); ?>">Master Control</a>
  </div>
</section>
<section class="labs-kpis labs-stage120-kpis">
  <div class="labs-kpi"><span>Mode</span><strong><?php echo labs_e((string)($state['summary']['mode'] ?? 'demo')); ?></strong><small>standalone Training Lab</small></div>
  <div class="labs-kpi"><span>Routes</span><strong><?php echo (int)tl_stage120_readiness_score(); ?>/100</strong><small>Stage 120 readiness</small></div>
  <div class="labs-kpi"><span>Records</span><strong><?php echo count($state['recent_events']); ?></strong><small>this section</small></div>
  <div class="labs-kpi"><span>Suite</span><strong><?php echo count(tl_stage120_sections()); ?></strong><small>new sections</small></div>
</section>
<section class="labs-flow-grid labs-stage120-grid">
  <article class="labs-card">
    <h2><?php echo labs_e((string)$section['button']); ?></h2>
    <form action="<?php echo labs_url($isAdmin ? '/admin/action-result.php' : '/app/action-result.php'); ?>" method="post" class="labs-stage30-form labs-stage120-form">
      <input type="hidden" name="confirm_training_action" value="1">
      <input type="hidden" name="training_action" value="<?php echo labs_e((string)$section['action']); ?>">
      <input type="hidden" name="actor_user_id" value="1">
      <label>Campaign
        <select name="campaign_ref"><?php foreach ($state['campaigns'] as $campaign): ?><option value="<?php echo labs_e((string)$campaign['ref']); ?>"><?php echo labs_e((string)$campaign['title']); ?></option><?php endforeach; ?></select>
      </label>
      <?php foreach ((array)$section['fields'] as $field): if ($field === 'campaign_ref') continue; $label = ucwords(str_replace('_', ' ', (string)$field)); ?>
        <label><?php echo labs_e($label); ?>
          <?php if (preg_match('/body|note|steps|summary|context|plan|terms|definition|rules|items|points|matrix|roadmap|contract|evidence|brief|tasks/i', (string)$field)): ?>
            <textarea name="<?php echo labs_e((string)$field); ?>" rows="5"><?php echo labs_e('Training Lab note for ' . strtolower($label) . '.'); ?></textarea>
          <?php else: ?>
            <input name="<?php echo labs_e((string)$field); ?>" value="<?php echo labs_e($label . ' sample'); ?>">
          <?php endif; ?>
        </label>
      <?php endforeach; ?>
      <button class="labs-btn labs-btn-primary" type="submit"><?php echo labs_e((string)$section['button']); ?></button>
    </form>
  </article>
  <aside class="labs-card">
    <h2>Recent <?php echo labs_e((string)$section['title']); ?> records</h2>
    <div class="labs-stage90-list">
      <?php foreach ($state['recent_events'] as $event): $meta = json_decode((string)($event['metadata_json'] ?? '{}'), true) ?: []; ?>
        <div class="labs-stage90-event"><strong><?php echo labs_e((string)($meta['section_title'] ?? $event['event_type'])); ?></strong><p><?php echo labs_e((string)($meta['control_notes'] ?? $meta['intake_goal'] ?? $meta['simulation_context'] ?? $meta['module_reason'] ?? $meta['current_blocker'] ?? $meta['script_steps'] ?? $meta['commitment_terms'] ?? $meta['success_goal'] ?? $meta['response_plan'] ?? $meta['evidence_summary'] ?? $meta['tour_note'] ?? $meta['practice_steps'] ?? $meta['triage_note'] ?? $meta['workflow_steps'] ?? $meta['insight_body'] ?? $meta['gate_rules'] ?? $meta['stewardship_note'] ?? $meta['persona_context'] ?? $meta['rollout_tasks'] ?? 'Training Lab record saved.')); ?></p><small><?php echo labs_e((string)($event['created_at'] ?? '')); ?></small></div>
      <?php endforeach; if (!$state['recent_events']): ?><p class="labs-muted">No records yet for this section.</p><?php endif; ?>
    </div>
  </aside>
</section>
<section class="labs-card labs-stage120-map">
  <h2>Stage 91–120 map</h2>
  <div class="labs-stage13-link-grid">
    <?php foreach (tl_stage120_sections() as $item): $href = '/' . $item['area'] . '/' . $item['slug'] . '.php'; ?>
      <a href="<?php echo labs_url($href); ?>"><span><?php echo labs_e((string)$item['title']); ?></span><strong><?php echo labs_e(str_replace('Stage ', '', (string)$item['stage'])); ?></strong></a>
    <?php endforeach; ?>
  </div>
</section>
<section class="labs-safe-note">Stage 91–120 standalone boundary: new actions write Training Lab event metadata only. No real enrollment/auth changes, commerce, external publishing, partner delivery, integrations, backups, resets, deletes, config changes, uploads, payments, wallet writes, real rewards, claims, email, SMS, push, or external AI calls.</section>
        <?php
        labs_page_end(['section' => $pageSection]);
    }
}


// -----------------------------------------------------------------------------
// Stage 123–130 backend workflow hardening + account bridge summary
// -----------------------------------------------------------------------------
if (!function_exists('tl_stage130_core_routes')) {
    function tl_stage130_core_routes(): array
    {
        return [
            '/signin.php','/signup.php','/account.php',
            '/app/index.php','/app/campaign-builder.php','/app/campaigns.php','/app/participant-portal.php','/app/task-runner.php','/app/proof-upload.php','/app/flow-board.php','/app/rewards.php',
            '/admin/index.php','/admin/command-center.php','/admin/backend-readiness.php','/admin/permissions.php','/admin/reward-bridge.php','/admin/campaigns.php','/admin/review-queue.php','/admin/review-workbench.php','/admin/reporting-center.php','/admin/db-health.php','/admin/route-check.php',
            '/api/training/auth-status.php','/api/training/account-bridge.php','/api/training/backend-readiness.php','/api/training/permissions.php','/api/training/reward-bridge.php','/api/training/rewards.php','/api/training/ops-overview.php','/api/training/app-action.php','/api/training/flow-state.php',
        ];
    }
}

if (!function_exists('tl_stage130_route_readiness')) {
    function tl_stage130_route_readiness(): array
    {
        $root = dirname(__DIR__);
        $checks = [];
        $ready = 0;
        foreach (tl_stage130_core_routes() as $route) {
            $exists = is_file($root . $route);
            $checks[] = ['route' => $route, 'exists' => $exists];
            if ($exists) $ready++;
        }
        return ['ready' => $ready, 'expected' => count($checks), 'all_ready' => $ready === count($checks), 'checks' => $checks];
    }
}

if (!function_exists('tl_stage130_workflow_score')) {
    function tl_stage130_workflow_score(): array
    {
        $routes = tl_stage130_route_readiness();
        $tables = tl_app_required_tables_status();
        $tableReady = count(array_filter($tables));
        $tableExpected = count($tables);
        $auth = function_exists('tl_account_bridge_current_context') ? tl_account_bridge_current_context() : [];
        $score = 0;
        $score += $routes['all_ready'] ? 25 : (int)floor(($routes['ready'] / max(1, $routes['expected'])) * 25);
        $score += ($tableReady === $tableExpected) ? 25 : (int)floor(($tableReady / max(1, $tableExpected)) * 25);
        $score += function_exists('tl_account_bridge_roles') ? 15 : 0;
        $score += function_exists('tl_stage130_reconcile_participant_progress') ? 15 : 0;
        $score += function_exists('tl_stage130_update_campaign_status') ? 10 : 0;
        $score += function_exists('tl_stage130_backend_health_snapshot') ? 10 : 0;
        $score += function_exists('tl_mg_reward_bridge_summary') ? 10 : 0;
        return [
            'score' => min(100, $score),
            'routes' => $routes,
            'tables' => ['ready' => $tableReady, 'expected' => $tableExpected, 'all_ready' => $tableReady === $tableExpected, 'details' => $tables],
            'auth_bridge_ready' => !empty($auth['roles']),
            'workflow_actions' => ['update_campaign_status','reconcile_participant_progress','backend_health_snapshot','offer_microgifter_reward','claim_training_reward','retry_microgifter_reward_issue','mark_reward_manual_issued','cancel_training_reward','reconcile_reward_lifecycle'],
        ];
    }
}

if (!function_exists('tl_stage130_update_campaign_status')) {
    function tl_stage130_update_campaign_status(array $input): array
    {
        $pdo = tl_require_db();
        $campaignRef = trim((string)($input['campaign'] ?? $input['campaign_id'] ?? $input['slug'] ?? ''));
        if ($campaignRef === '') throw new RuntimeException('Campaign is required.');
        $campaign = tl_find_campaign_row($pdo, $campaignRef);
        if (!$campaign) throw new RuntimeException('Campaign not found.');
        $status = strtolower(trim((string)($input['status'] ?? 'active')));
        if (!in_array($status, ['draft','scheduled','active','paused','completed','archived'], true)) throw new RuntimeException('Invalid campaign status.');
        $visibility = strtolower(trim((string)($input['visibility'] ?? $campaign['visibility'] ?? 'published')));
        if (!in_array($visibility, ['draft','private','published','archived'], true)) $visibility = (string)$campaign['visibility'];
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? $input['owner_user_id'] ?? 1));
        $stmt = $pdo->prepare('UPDATE training_campaigns SET status = ?, visibility = ? WHERE id = ?');
        $stmt->execute([$status, $visibility, (int)$campaign['id']]);
        tl_log_event($pdo, $actor, 'campaign', (int)$campaign['id'], 'campaign_status_updated', ['status' => $status, 'visibility' => $visibility, 'stage' => 'stage130_backend_hardening']);
        return ['campaign_id' => (int)$campaign['id'], 'status' => $status, 'visibility' => $visibility];
    }
}

if (!function_exists('tl_stage130_reconcile_participant_progress')) {
    function tl_stage130_reconcile_participant_progress(array $input): array
    {
        $pdo = tl_require_db();
        $participantRef = trim((string)($input['participant_id'] ?? $input['participant'] ?? ''));
        if ($participantRef !== '') {
            $stmt = $pdo->prepare('SELECT * FROM training_participants WHERE id = ? OR public_id = ? LIMIT 1');
            $stmt->execute([ctype_digit($participantRef) ? (int)$participantRef : 0, $participantRef]);
            $participants = $stmt->fetchAll();
        } else {
            $participants = $pdo->query('SELECT * FROM training_participants ORDER BY updated_at DESC LIMIT 200')->fetchAll();
        }
        $updated = [];
        foreach ($participants as $participant) {
            $receiptStmt = $pdo->prepare("SELECT COUNT(*) FROM training_action_receipts WHERE participant_id = ? AND receipt_status = 'active'");
            $receiptStmt->execute([(int)$participant['id']]);
            $completed = (int)$receiptStmt->fetchColumn();
            $streak = $pdo->prepare('INSERT INTO training_streaks (campaign_id, participant_id, user_id, completed_action_count, current_streak_days, longest_streak_days, last_action_date) VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE) ON DUPLICATE KEY UPDATE completed_action_count = VALUES(completed_action_count), current_streak_days = GREATEST(current_streak_days, VALUES(current_streak_days)), longest_streak_days = GREATEST(longest_streak_days, VALUES(longest_streak_days)), last_action_date = IF(VALUES(completed_action_count) > 0, CURRENT_DATE, last_action_date)');
            $streak->execute([(int)$participant['campaign_id'], (int)$participant['id'], (int)$participant['user_id'], $completed, $completed, $completed]);
            $campaign = $pdo->prepare('SELECT target_action_count FROM training_campaigns WHERE id = ? LIMIT 1');
            $campaign->execute([(int)$participant['campaign_id']]);
            $target = (int)($campaign->fetchColumn() ?: 0);
            if ($target > 0 && $completed >= $target && !in_array((string)$participant['status'], ['completed','removed'], true)) {
                $upd = $pdo->prepare("UPDATE training_participants SET status = 'completed', completed_at = COALESCE(completed_at, CURRENT_TIMESTAMP) WHERE id = ?");
                $upd->execute([(int)$participant['id']]);
            }
            $updated[] = ['participant_id' => (int)$participant['id'], 'campaign_id' => (int)$participant['campaign_id'], 'completed_action_count' => $completed, 'target_action_count' => $target, 'status_after' => ($target > 0 && $completed >= $target) ? 'completed' : (string)$participant['status']];
        }
        tl_log_event($pdo, max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1)), 'system', null, 'participant_progress_reconciled', ['count' => count($updated), 'stage' => 'stage130_backend_hardening']);
        return ['reconciled' => count($updated), 'participants' => $updated];
    }
}

if (!function_exists('tl_stage130_backend_health_snapshot')) {
    function tl_stage130_backend_health_snapshot(array $input = []): array
    {
        $pdo = tl_require_db();
        $score = tl_stage130_workflow_score();
        tl_log_event($pdo, max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1)), 'system', null, 'backend_health_snapshot', ['score' => $score['score'], 'stage' => 'stage130_backend_hardening', 'auth_bridge' => true]);
        return $score;
    }
}

if (!function_exists('tl_stage130_backend_summary')) {
    function tl_stage130_backend_summary(): array
    {
        return [
            'stage' => 'Stage 123–130 backend workflow hardening and account bridge',
            'workflow_score' => tl_stage130_workflow_score(),
            'auth_context' => function_exists('tl_account_bridge_current_context') ? tl_account_bridge_current_context() : [],
            'microgifter_rewards' => function_exists('tl_mg_reward_bridge_summary') ? tl_mg_reward_bridge_summary() : [],
            'new_backend_actions' => ['update_campaign_status','reconcile_participant_progress','backend_health_snapshot'],
            'auth_model' => [
                'login_with_microgifter' => true,
                'sync_existing_microgifter_session' => true,
                'create_training_account_with_microgifter_option' => true,
                'microgifter_creation_adapter_required' => true,
                'roles' => function_exists('tl_account_bridge_roles') ? array_keys(tl_account_bridge_roles()) : [],
            ],
            'safe_boundaries' => [
                'no_unknown_microgifter_auth_table_writes' => true,
                'no_password_storage_in_training_tables' => true,
                'training_events_audit_only_for_account_bridge' => true,
                'microgifter_rewards_require_bridge_adapter' => true,
                'training_in_app_claim_tracking_active' => true,
                'no_payment_processing' => true,
            ],
        ];
    }
}
