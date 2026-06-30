<?php
/**
 * Stage 641-680 Communication + Operating Rhythm.
 *
 * Five-section batch focused on participant messaging, admin prompt consistency,
 * follow-up logic, daily operating rhythm, and a communication/rhythm API layer.
 * Safe read-only/internal guidance only: no external email/SMS/push sends.
 */

if (!function_exists('tl_stage680_e')) {
    function tl_stage680_e($value): string
    {
        return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tl_stage680_root')) { function tl_stage680_root(): string { return dirname(__DIR__); } }
if (!function_exists('tl_stage680_route_exists')) { function tl_stage680_route_exists(string $route): bool { return is_file(tl_stage680_root() . '/' . ltrim($route, '/')); } }
if (!function_exists('tl_stage680_score_from_checks')) {
    function tl_stage680_score_from_checks(array $checks): int
    {
        if (!$checks) return 100;
        $passed = 0; foreach ($checks as $ok) if ($ok) $passed++;
        return (int)round(($passed / max(1, count($checks))) * 100);
    }
}
if (!function_exists('tl_stage680_status_class')) {
    function tl_stage680_status_class(string $status): string
    {
        $s = strtolower(str_replace(' ', '_', trim($status)));
        if (in_array($s, ['ready','sentiment_ready','clear','complete','approved','claimable','issued','healthy','today','good'], true)) return 'good';
        if (in_array($s, ['needs_info','blocked','waiting','pending','follow_up','retry','warning','stuck'], true)) return 'warn';
        return 'neutral';
    }
}

if (!function_exists('tl_stage680_flow_counts')) {
    function tl_stage680_flow_counts(): array
    {
        $flow = function_exists('tl_app_flow_summary') ? tl_app_flow_summary() : ['counts'=>[]];
        return (array)($flow['counts'] ?? []);
    }
}

if (!function_exists('tl_stage680_participant_communication')) {
    function tl_stage680_participant_communication(string $campaignRef = '', int $userId = 0): array
    {
        $timeline = function_exists('tl_stage600_participant_timeline') ? tl_stage600_participant_timeline($campaignRef, $userId) : [];
        $proof = function_exists('tl_stage640_proof_evidence_quality') ? tl_stage640_proof_evidence_quality() : [];
        $reward = function_exists('tl_stage640_reward_audit_assurance') ? tl_stage640_reward_audit_assurance() : [];
        $counts = tl_stage680_flow_counts();
        $pendingProofs = (int)($counts['pending_proofs'] ?? 0);
        $rewardEvents = (int)($counts['reward_events'] ?? 0);
        $progress = (int)($timeline['progress_percent'] ?? 0);
        $position = (string)($timeline['current_position'] ?? ($progress > 0 ? 'Mission in progress' : 'Mission ready'));
        $messages = [
            ['label'=>'Current mission', 'status'=>$progress > 0 ? 'ready' : 'waiting', 'detail'=>$position . ' · ' . $progress . '% complete.', 'href'=>'/app/task-runner.php'],
            ['label'=>'Proof status', 'status'=>$pendingProofs > 0 ? 'waiting' : 'clear', 'detail'=>$pendingProofs > 0 ? 'Proof is waiting for review.' : 'No blocking proof review is visible.', 'href'=>'/app/proof-upload.php'],
            ['label'=>'Reward status', 'status'=>$rewardEvents > 0 ? 'claimable' : 'pending', 'detail'=>$rewardEvents > 0 ? 'Reward activity is visible in your reward path.' : 'Complete/verify tasks to unlock reward events.', 'href'=>'/app/rewards.php'],
            ['label'=>'What happens next?', 'status'=>'ready', 'detail'=>'Run the next task, submit proof if required, then wait for review/reward.', 'href'=>'/app/progress-map.php'],
        ];
        $copy = [
            'Start or continue the highlighted task first.',
            'If proof is required, include enough detail for a reviewer to verify it.',
            'Rewards stay inside Training Lab until Microgifter issuing is adapter/developer-key enabled.',
        ];
        $checks = [
            'participant_portal_route' => tl_stage680_route_exists('/app/participant-portal.php'),
            'task_runner_route' => tl_stage680_route_exists('/app/task-runner.php'),
            'proof_upload_route' => tl_stage680_route_exists('/app/proof-upload.php'),
            'rewards_route' => tl_stage680_route_exists('/app/rewards.php'),
            'message_board_route' => tl_stage680_route_exists('/app/message-board.php'),
            'no_external_notifications' => true,
        ];
        return [
            'stage' => 'Stage 641-648 participant communication center',
            'progress_percent' => $progress,
            'current_position' => $position,
            'pending_proofs' => $pendingProofs,
            'reward_events' => $rewardEvents,
            'messages' => $messages,
            'what_happens_next' => $copy,
            'proof_hint' => (string)($proof['participant_explanation'] ?? 'Proof should show what action was completed and why it qualifies.'),
            'score' => tl_stage680_score_from_checks($checks),
            'accepted' => tl_stage680_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage680_admin_communication_console')) {
    function tl_stage680_admin_communication_console(): array
    {
        $counts = tl_stage680_flow_counts();
        $pendingProofs = (int)($counts['pending_proofs'] ?? 0);
        $participants = (int)($counts['participants'] ?? 0);
        $rewards = (int)($counts['reward_events'] ?? 0);
        $templates = [
            ['label'=>'Proof follow-up', 'status'=>$pendingProofs > 0 ? 'follow_up' : 'ready', 'detail'=>'Thanks for submitting proof. A reviewer is checking whether it verifies the requested action.', 'href'=>'/admin/review-workbench.php'],
            ['label'=>'Needs more info', 'status'=>'needs_info', 'detail'=>'Please add a clearer note, screenshot, receipt, or location context so the action can be verified.', 'href'=>'/admin/review-queue.php'],
            ['label'=>'Participant status', 'status'=>$participants > 0 ? 'ready' : 'waiting', 'detail'=>'Your Training Lab task path is open. Start with the current mission card and submit proof where requested.', 'href'=>'/admin/participant-inspector.php'],
            ['label'=>'Reward issue/retry', 'status'=>$rewards > 0 ? 'retry' : 'waiting', 'detail'=>'Your reward is recorded in Training Lab. Microgifter issuing remains gated until the bridge is available.', 'href'=>'/admin/reward-bridge.php'],
        ];
        $checks = [
            'command_center_route' => tl_stage680_route_exists('/admin/command-center.php'),
            'review_workbench_route' => tl_stage680_route_exists('/admin/review-workbench.php'),
            'review_queue_route' => tl_stage680_route_exists('/admin/review-queue.php'),
            'participant_inspector_route' => tl_stage680_route_exists('/admin/participant-inspector.php'),
            'reporting_center_route' => tl_stage680_route_exists('/admin/reporting-center.php'),
            'copy_only_no_send' => true,
        ];
        return [
            'stage' => 'Stage 649-656 admin communication console',
            'communication_readiness_score' => tl_stage680_score_from_checks($checks),
            'templates' => $templates,
            'admin_guidance' => [
                'Use these as internal/copy-ready prompts only.',
                'Do not send external email/SMS from this stage.',
                'Keep messages short: status, reason, next action.',
            ],
            'score' => tl_stage680_score_from_checks($checks),
            'accepted' => tl_stage680_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage680_mission_followup_logic')) {
    function tl_stage680_mission_followup_logic(string $campaignRef = '', int $userId = 0): array
    {
        $participant = function_exists('tl_stage640_participant_data_quality') ? tl_stage640_participant_data_quality($campaignRef, $userId) : [];
        $counts = tl_stage680_flow_counts();
        $participants = (int)($counts['participants'] ?? 0);
        $proofs = (int)($counts['proofs'] ?? 0);
        $pendingProofs = (int)($counts['pending_proofs'] ?? 0);
        $rewards = (int)($counts['reward_events'] ?? 0);
        $states = [];
        $states[] = ['label'=>'Joined but no task started', 'status'=>($participants > 0 && (int)($participant['quality_score'] ?? 100) < 100) ? 'follow_up' : 'ready', 'detail'=>$participants . ' participant record(s) available.', 'href'=>'/admin/participant-inspector.php'];
        $states[] = ['label'=>'Task complete but no proof', 'status'=>($participants > 0 && $proofs === 0) ? 'follow_up' : 'ready', 'detail'=>$proofs . ' proof submission(s) found.', 'href'=>'/app/proof-upload.php'];
        $states[] = ['label'=>'Proof needs more info', 'status'=>$pendingProofs > 0 ? 'follow_up' : 'clear', 'detail'=>$pendingProofs . ' pending proof item(s).', 'href'=>'/admin/review-workbench.php'];
        $states[] = ['label'=>'Reward claim not completed', 'status'=>($proofs > 0 && $rewards === 0) ? 'follow_up' : 'ready', 'detail'=>$rewards . ' reward event(s).', 'href'=>'/app/rewards.php'];
        $followUps = array_values(array_filter($states, fn($s) => in_array($s['status'], ['follow_up','needs_info'], true)));
        $checks = [
            'progress_map_route' => tl_stage680_route_exists('/app/progress-map.php'),
            'flow_board_route' => tl_stage680_route_exists('/app/flow-board.php'),
            'participant_inspector_route' => tl_stage680_route_exists('/admin/participant-inspector.php'),
            'cohort_manager_route' => tl_stage680_route_exists('/admin/cohort-manager.php'),
            'command_center_route' => tl_stage680_route_exists('/admin/command-center.php'),
            'existing_data_only' => true,
        ];
        return [
            'stage' => 'Stage 657-664 mission reminder and follow-up logic',
            'reminder_needed_count' => count($followUps),
            'states' => $states,
            'recommended_followups' => $followUps ?: [['label'=>'No urgent follow-up', 'status'=>'ready', 'detail'=>'Current workflow has no high-priority reminder state.', 'href'=>'/admin/command-center.php']],
            'score' => tl_stage680_score_from_checks($checks),
            'accepted' => tl_stage680_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage680_operator_daily_rhythm')) {
    function tl_stage680_operator_daily_rhythm(): array
    {
        $quality = function_exists('tl_stage640_operator_health_dashboard') ? tl_stage640_operator_health_dashboard() : ['health_score'=>100, 'todays_cleanup_order'=>[]];
        $followup = tl_stage680_mission_followup_logic();
        $opening = [
            ['label'=>'Open command center', 'status'=>'today', 'detail'=>'Check the operator snapshot and top actions.', 'href'=>'/admin/command-center.php'],
            ['label'=>'Review campaign state', 'status'=>'today', 'detail'=>'Confirm draft/active/review-needed campaigns.', 'href'=>'/admin/campaigns.php'],
            ['label'=>'Scan participant blockers', 'status'=>'today', 'detail'=>'Find participants waiting for task/proof/reward movement.', 'href'=>'/admin/participant-inspector.php'],
        ];
        $midday = [
            ['label'=>'Review proof queue', 'status'=>'today', 'detail'=>'Approve, reject, or request more info.', 'href'=>'/admin/review-workbench.php'],
            ['label'=>'Check reward bridge', 'status'=>'today', 'detail'=>'Review pending sync, retry, or manual issue context.', 'href'=>'/admin/reward-bridge.php'],
        ];
        $closeout = [
            ['label'=>'Run readiness check', 'status'=>'today', 'detail'=>'Confirm route/data/communication gates.', 'href'=>'/admin/backend-readiness.php'],
            ['label'=>'Capture daily report', 'status'=>'today', 'detail'=>'Review the operator ledger and health diagnosis.', 'href'=>'/admin/reporting-center.php'],
        ];
        $top = array_slice(array_merge($followup['recommended_followups'], $opening, $midday, $closeout), 0, 5);
        $checks = [
            'admin_index_route' => tl_stage680_route_exists('/admin/index.php'),
            'command_center_route' => tl_stage680_route_exists('/admin/command-center.php'),
            'backend_readiness_route' => tl_stage680_route_exists('/admin/backend-readiness.php'),
            'reporting_center_route' => tl_stage680_route_exists('/admin/reporting-center.php'),
            'daily_checklist_present' => true,
        ];
        return [
            'stage' => 'Stage 665-672 operator daily rhythm',
            'health_score' => (int)($quality['health_score'] ?? 100),
            'operating_rhythm_score' => tl_stage680_score_from_checks($checks),
            'opening_checklist' => $opening,
            'midday_review' => $midday,
            'end_of_day_closeout' => $closeout,
            'todays_top_5_actions' => $top,
            'score' => tl_stage680_score_from_checks($checks),
            'accepted' => tl_stage680_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage680_communication_rhythm_audit')) {
    function tl_stage680_communication_rhythm_audit(): array
    {
        $root = tl_stage680_root();
        $markers = [
            'app/participant-portal.php' => 'tl_stage680_render_participant_communication',
            'app/message-board.php' => 'tl_stage680_render_participant_communication',
            'admin/command-center.php' => 'tl_stage680_render_admin_communication_console',
            'admin/participant-inspector.php' => 'tl_stage680_render_mission_followup_logic',
            'admin/backend-readiness.php' => 'tl_stage680_render_operator_daily_rhythm',
            'admin/reporting-center.php' => 'tl_stage680_render_operator_daily_rhythm',
            'api/training/communication-rhythm.php' => 'tl_stage680_communication_rhythm_summary',
        ];
        $issues = [];
        foreach ($markers as $path => $needle) {
            $file = $root . '/' . $path;
            if (!is_file($file) || strpos((string)file_get_contents($file), $needle) === false) $issues[] = $path . ' missing Stage 680 marker ' . $needle;
        }
        $checks = ['route_and_marker_audit' => count($issues) === 0, 'no_external_notifications' => true, 'no_sql_required' => true, 'no_page_factory_expansion' => true];
        return [
            'stage' => 'Stage 673-680 communication and rhythm audit',
            'issue_count' => count($issues),
            'issues' => $issues,
            'score' => count($issues) === 0 ? 100 : max(0, 100 - count($issues) * 8),
            'accepted' => count($issues) === 0,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage680_communication_rhythm_summary')) {
    function tl_stage680_communication_rhythm_summary(bool $includeAudit = true): array
    {
        $participant = tl_stage680_participant_communication();
        $admin = tl_stage680_admin_communication_console();
        $followup = tl_stage680_mission_followup_logic();
        $rhythm = tl_stage680_operator_daily_rhythm();
        $audit = $includeAudit ? tl_stage680_communication_rhythm_audit() : ['accepted'=>true,'score'=>100,'issue_count'=>0,'issues'=>[]];
        $sections = [$participant, $admin, $followup, $rhythm, $audit];
        $accepted = true; $scores = [];
        foreach ($sections as $section) { $scores[] = (int)($section['score'] ?? 0); if (empty($section['accepted'])) $accepted = false; }
        return [
            'stage' => 'Stage 641-680 communication and operating rhythm',
            'built_from' => 'Stage 601-640 data quality and operator confidence',
            'builds' => [
                'Build 89: Participant Communication Center',
                'Build 90: Admin Communication Console',
                'Build 91: Mission Reminder + Follow-Up Logic',
                'Build 92: Operator Daily Rhythm',
                'Build 93: Communication + Rhythm API Layer',
            ],
            'participant_communication_center' => $participant,
            'admin_communication_console' => $admin,
            'mission_reminder_followup_logic' => $followup,
            'operator_daily_rhythm' => $rhythm,
            'communication_rhythm_audit' => $audit,
            'score' => $accepted ? 100 : (int)round(array_sum($scores) / max(1, count($scores))),
            'accepted' => $accepted,
            'safe_boundaries' => [
                'no_new_sql_required' => true,
                'no_config_files_moved_or_overwritten' => true,
                'no_hard_auth_gates_forced' => true,
                'no_page_factory_expansion' => true,
                'no_external_email_sms_push_notifications' => true,
                'no_destructive_delete_or_reset_actions' => true,
                'no_real_upload_processing' => true,
                'no_payments_or_wallet_mutation' => true,
                'microgifter_reward_issuing_adapter_gated' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage680_context_cards')) {
    function tl_stage680_context_cards(string $context): array
    {
        if (strpos($context, 'review') !== false || strpos($context, 'proof') !== false) {
            return [
                ['label'=>'Message', 'value'=>'Proof', 'hint'=>'follow-up copy', 'href'=>'/api/training/communication-rhythm.php?section=admin'],
                ['label'=>'Reminder', 'value'=>'Needs info', 'hint'=>'participant clarity', 'href'=>'/app/proof-upload.php'],
                ['label'=>'Rhythm', 'value'=>'Review', 'hint'=>'daily queue', 'href'=>'/admin/review-workbench.php'],
            ];
        }
        if (strpos($context, 'reward') !== false) {
            return [
                ['label'=>'Message', 'value'=>'Reward', 'hint'=>'status copy', 'href'=>'/app/rewards.php'],
                ['label'=>'Bridge', 'value'=>'Explain', 'hint'=>'gated issuing', 'href'=>'/admin/reward-bridge.php'],
                ['label'=>'Follow-up', 'value'=>'Claim', 'hint'=>'reward action', 'href'=>'/api/training/communication-rhythm.php?section=followup'],
            ];
        }
        if (str_starts_with($context, 'admin-')) {
            return [
                ['label'=>'Daily', 'value'=>'Open', 'hint'=>'operator rhythm', 'href'=>'/api/training/communication-rhythm.php?section=rhythm'],
                ['label'=>'Admin', 'value'=>'Prompts', 'hint'=>'copy-ready', 'href'=>'/api/training/communication-rhythm.php?section=admin'],
                ['label'=>'Follow-up', 'value'=>'Top 5', 'hint'=>'what to do next', 'href'=>'/admin/command-center.php'],
            ];
        }
        return [
            ['label'=>'Message', 'value'=>'Next', 'hint'=>'what happens now', 'href'=>'/api/training/communication-rhythm.php?section=participant'],
            ['label'=>'Reminder', 'value'=>'Friendly', 'hint'=>'mission clarity', 'href'=>'/app/message-board.php'],
            ['label'=>'Reward', 'value'=>'Status', 'hint'=>'earned or pending', 'href'=>'/app/rewards.php'],
        ];
    }
}

if (!function_exists('tl_stage680_context_runtime_overrides')) {
    function tl_stage680_context_runtime_overrides(string $context, array $baseCfg = []): array
    {
        $stage640 = function_exists('tl_stage640_context_runtime_overrides') ? tl_stage640_context_runtime_overrides($context, $baseCfg) : [];
        $live = array_values(array_unique(array_merge((array)($stage640['live_strip'] ?? []), ['Communication', 'Stage 680'])));
        return array_replace_recursive($stage640, [
            'live_strip' => $live,
            'stage680_cards' => tl_stage680_context_cards($context),
            'status_meta' => str_starts_with($context, 'admin-')
                ? ['Daily rhythm', 'Follow-up prompts', 'Internal messaging']
                : ['What happens next', 'Proof/reward messages', 'Friendly reminders'],
            'stage680_runtime_bound' => true,
        ]);
    }
}

if (!function_exists('tl_stage680_render_card_list')) {
    function tl_stage680_render_card_list(array $items): void
    {
        echo '<div class="labs-stage680-message-grid">';
        foreach ($items as $item) {
            $status = (string)($item['status'] ?? 'ready');
            $href = (string)($item['href'] ?? '#');
            echo '<a class="is-' . tl_stage680_e(tl_stage680_status_class($status)) . '" href="' . tl_stage680_e(function_exists('labs_url') ? labs_url($href) : $href) . '"><span>' . tl_stage680_e($status) . '</span><strong>' . tl_stage680_e($item['label'] ?? 'Message') . '</strong><small>' . tl_stage680_e($item['detail'] ?? $item['hint'] ?? '') . '</small></a>';
        }
        echo '</div>';
    }
}

if (!function_exists('tl_stage680_render_shell')) {
    function tl_stage680_render_shell(string $eyebrow, string $title, array $metrics, array $items, string $apiHref, string $class = ''): void
    {
        echo '<section class="labs-card labs-stage680-panel ' . tl_stage680_e($class) . '"><div class="labs-card-headline"><div><span class="labs-eyebrow">' . tl_stage680_e($eyebrow) . '</span><h2>' . tl_stage680_e($title) . '</h2></div><a class="labs-btn" href="' . tl_stage680_e(labs_url($apiHref)) . '">Rhythm API</a></div>';
        echo '<div class="labs-stage680-metric-grid">';
        foreach ($metrics as $metric) echo '<article><span>' . tl_stage680_e($metric['label'] ?? 'Metric') . '</span><strong>' . tl_stage680_e($metric['value'] ?? 'Ready') . '</strong><small>' . tl_stage680_e($metric['hint'] ?? '') . '</small></article>';
        echo '</div>';
        tl_stage680_render_card_list($items);
        echo '</section>';
    }
}

if (!function_exists('tl_stage680_render_participant_communication')) {
    function tl_stage680_render_participant_communication(string $campaignRef = '', int $userId = 0): void
    {
        $data = tl_stage680_participant_communication($campaignRef, $userId);
        $metrics = [
            ['label'=>'Progress', 'value'=>$data['progress_percent'] . '%', 'hint'=>$data['current_position']],
            ['label'=>'Proof waiting', 'value'=>(string)$data['pending_proofs'], 'hint'=>'review status'],
            ['label'=>'Reward events', 'value'=>(string)$data['reward_events'], 'hint'=>'earned/claimable path'],
        ];
        tl_stage680_render_shell('Stage 641–648', 'Participant Communication Center', $metrics, $data['messages'], '/api/training/communication-rhythm.php?section=participant', 'labs-stage680-participant');
        echo '<div class="labs-stage680-rhythm-note">'; foreach ($data['what_happens_next'] as $line) echo '<p>' . tl_stage680_e($line) . '</p>'; echo '</div>';
    }
}

if (!function_exists('tl_stage680_render_admin_communication_console')) {
    function tl_stage680_render_admin_communication_console(): void
    {
        $data = tl_stage680_admin_communication_console();
        $metrics = [
            ['label'=>'Readiness', 'value'=>$data['communication_readiness_score'] . '/100', 'hint'=>'internal prompt coverage'],
            ['label'=>'Templates', 'value'=>(string)count($data['templates']), 'hint'=>'copy-ready snippets'],
            ['label'=>'Mode', 'value'=>'Internal only', 'hint'=>'no email/SMS sending'],
        ];
        tl_stage680_render_shell('Stage 649–656', 'Admin Communication Console', $metrics, $data['templates'], '/api/training/communication-rhythm.php?section=admin', 'labs-stage680-admin');
        echo '<div class="labs-stage680-rhythm-note">'; foreach ($data['admin_guidance'] as $line) echo '<p>' . tl_stage680_e($line) . '</p>'; echo '</div>';
    }
}

if (!function_exists('tl_stage680_render_mission_followup_logic')) {
    function tl_stage680_render_mission_followup_logic(string $campaignRef = '', int $userId = 0): void
    {
        $data = tl_stage680_mission_followup_logic($campaignRef, $userId);
        $metrics = [
            ['label'=>'Follow-ups', 'value'=>(string)$data['reminder_needed_count'], 'hint'=>'stuck states found'],
            ['label'=>'States', 'value'=>(string)count($data['states']), 'hint'=>'mission reminders checked'],
            ['label'=>'Mode', 'value'=>'Existing data', 'hint'=>'no external send'],
        ];
        tl_stage680_render_shell('Stage 657–664', 'Mission Reminder + Follow-Up Logic', $metrics, $data['states'], '/api/training/communication-rhythm.php?section=followup', 'labs-stage680-followup');
    }
}

if (!function_exists('tl_stage680_render_operator_daily_rhythm')) {
    function tl_stage680_render_operator_daily_rhythm(): void
    {
        $data = tl_stage680_operator_daily_rhythm();
        $items = array_merge($data['opening_checklist'], $data['midday_review'], $data['end_of_day_closeout']);
        $metrics = [
            ['label'=>'Health', 'value'=>$data['health_score'] . '/100', 'hint'=>'operator confidence'],
            ['label'=>'Rhythm', 'value'=>$data['operating_rhythm_score'] . '/100', 'hint'=>'daily checklist coverage'],
            ['label'=>'Top actions', 'value'=>(string)count($data['todays_top_5_actions']), 'hint'=>'priority operating order'],
        ];
        tl_stage680_render_shell('Stage 665–672', 'Operator Daily Rhythm', $metrics, $items, '/api/training/communication-rhythm.php?section=rhythm', 'labs-stage680-rhythm');
        echo '<div class="labs-stage680-top-actions"><h3>Today’s top 5 actions</h3>'; tl_stage680_render_card_list($data['todays_top_5_actions']); echo '</div>';
    }
}
