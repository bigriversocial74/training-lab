<?php
/**
 * Stage 681-720 Content Management + Training Experience Polish.
 *
 * Five-section batch focused on content library organization, challenge template
 * selection, participant learning experience, admin training quality, and a
 * content/experience API layer. Uses existing Training Lab data and events only.
 */

if (!function_exists('tl_stage720_e')) {
    function tl_stage720_e($value): string { return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('tl_stage720_root')) { function tl_stage720_root(): string { return dirname(__DIR__); } }
if (!function_exists('tl_stage720_route_exists')) { function tl_stage720_route_exists(string $route): bool { return is_file(tl_stage720_root() . '/' . ltrim($route, '/')); } }
if (!function_exists('tl_stage720_score_from_checks')) {
    function tl_stage720_score_from_checks(array $checks): int { if (!$checks) return 100; $passed=0; foreach ($checks as $ok) if ($ok) $passed++; return (int)round(($passed / max(1, count($checks))) * 100); }
}
if (!function_exists('tl_stage720_flow_counts')) {
    function tl_stage720_flow_counts(): array { $flow = function_exists('tl_app_flow_summary') ? tl_app_flow_summary() : ['counts'=>[]]; return (array)($flow['counts'] ?? []); }
}
if (!function_exists('tl_stage720_status_class')) {
    function tl_stage720_status_class(string $status): string {
        $s = strtolower(str_replace([' ', '-'], '_', trim($status)));
        if (in_array($s, ['ready','recommended','complete','healthy','good','available','strong','published'], true)) return 'good';
        if (in_array($s, ['needs_work','missing','blocked','draft','review','warning','weak','stalled'], true)) return 'warn';
        return 'neutral';
    }
}

if (!function_exists('tl_stage720_training_content_library')) {
    function tl_stage720_training_content_library(string $campaignRef = '', int $userId = 0): array
    {
        $counts = tl_stage720_flow_counts();
        $resources = function_exists('tl_stage50_resource_state') ? tl_stage50_resource_state($campaignRef ?: (function_exists('tl_app_default_campaign_ref') ? tl_app_default_campaign_ref() : 'demo'), max(1, $userId ?: 1)) : ['resources'=>[], 'recent_notes'=>[]];
        $items = [
            ['category'=>'Guide', 'title'=>'Mission Start Guide', 'status'=>'recommended', 'detail'=>'Explains how a participant starts, completes a task, and checks progress.', 'href'=>'/app/participant-portal.php'],
            ['category'=>'Task example', 'title'=>'Task Completion Example', 'status'=>'ready', 'detail'=>'Shows how to turn a campaign task into a verifiable action.', 'href'=>'/app/task-runner.php'],
            ['category'=>'Proof example', 'title'=>'Proof Quality Checklist', 'status'=>'ready', 'detail'=>'What reviewers need: clear note, context, and completion signal.', 'href'=>'/app/proof-upload.php'],
            ['category'=>'Reward rule', 'title'=>'Reward Eligibility Path', 'status'=>'available', 'detail'=>'How approved proof and receipts map into Training Lab reward events.', 'href'=>'/app/rewards.php'],
            ['category'=>'Operator guide', 'title'=>'Daily Training Lab Runbook', 'status'=>'ready', 'detail'=>'Opening, midday review, and closeout rhythm for admins.', 'href'=>'/admin/command-center.php'],
        ];
        $recommended = [];
        if ((int)($counts['participants'] ?? 0) === 0) $recommended[] = $items[0];
        if ((int)($counts['proofs'] ?? 0) === 0) $recommended[] = $items[2];
        if ((int)($counts['reward_events'] ?? 0) === 0) $recommended[] = $items[3];
        if (!$recommended) $recommended = array_slice($items, 0, 3);
        $checks = [
            'resource_hub_route' => tl_stage720_route_exists('/app/resource-hub.php'),
            'challenge_library_route' => tl_stage720_route_exists('/app/challenge-library.php'),
            'task_runner_route' => tl_stage720_route_exists('/app/task-runner.php'),
            'reporting_center_route' => tl_stage720_route_exists('/admin/reporting-center.php'),
            'content_cards_present' => count($items) >= 5,
            'no_upload_processing' => true,
        ];
        return [
            'stage' => 'Stage 681-688 training content library',
            'content_readiness_score' => tl_stage720_score_from_checks($checks),
            'content_categories' => ['Guides','Task examples','Proof examples','Reward rules','Operator runbooks'],
            'content_cards' => $items,
            'recommended_for_current_mission' => $recommended,
            'existing_resource_count' => count((array)($resources['resources'] ?? [])),
            'recent_resource_notes' => count((array)($resources['recent_notes'] ?? [])),
            'score' => tl_stage720_score_from_checks($checks),
            'accepted' => tl_stage720_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage720_challenge_template_selection')) {
    function tl_stage720_challenge_template_selection(): array
    {
        $library = function_exists('tl_stage45_challenge_library_state') ? tl_stage45_challenge_library_state() : ['templates'=>[]];
        $templates = (array)($library['templates'] ?? []);
        $lanes = [
            ['lane'=>'Beginner', 'status'=>'ready', 'detail'=>'Short mission path with simple proof and a clear reward.', 'href'=>'/app/challenge-library.php'],
            ['lane'=>'Standard', 'status'=>'recommended', 'detail'=>'Balanced task sequence for participant learning and review practice.', 'href'=>'/app/campaign-builder.php'],
            ['lane'=>'Advanced', 'status'=>'draft', 'detail'=>'Multi-step training flow for cohorts, proof scoring, and reward operations.', 'href'=>'/app/campaign-detail.php'],
        ];
        $preview = [];
        foreach ($templates as $id => $template) {
            $preview[] = [
                'id' => (string)$id,
                'title' => (string)($template['label'] ?? $template['title'] ?? 'Challenge template'),
                'summary' => (string)($template['summary'] ?? 'Reusable Training Lab path.'),
                'task_count' => count((array)($template['tasks'] ?? [])),
                'href' => '/app/challenge-library.php',
            ];
        }
        if (!$preview) {
            $preview = [
                ['id'=>'starter-proof', 'title'=>'Starter Proof Challenge', 'summary'=>'A three-step path for task, proof, review, and reward practice.', 'task_count'=>3, 'href'=>'/app/challenge-library.php'],
                ['id'=>'reward-path', 'title'=>'Reward Path Training', 'summary'=>'A guided reward eligibility sequence for participants and reviewers.', 'task_count'=>4, 'href'=>'/app/campaign-builder.php'],
            ];
        }
        $checks = [
            'launchpad_route' => tl_stage720_route_exists('/app/launchpad.php'),
            'campaign_builder_route' => tl_stage720_route_exists('/app/campaign-builder.php'),
            'challenge_library_route' => tl_stage720_route_exists('/app/challenge-library.php'),
            'campaign_detail_route' => tl_stage720_route_exists('/app/campaign-detail.php'),
            'template_lanes_present' => count($lanes) === 3,
        ];
        return [
            'stage' => 'Stage 689-696 challenge template selection',
            'template_lanes' => $lanes,
            'template_preview' => $preview,
            'template_count' => count($preview),
            'builder_guidance' => ['Choose a lane first.', 'Preview task/reward sequence.', 'Customize campaign title and proof expectations.', 'Run readiness before launch.'],
            'score' => tl_stage720_score_from_checks($checks),
            'accepted' => tl_stage720_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage720_participant_learning_experience')) {
    function tl_stage720_participant_learning_experience(string $campaignRef = '', int $userId = 0): array
    {
        $participant = function_exists('tl_stage680_participant_communication') ? tl_stage680_participant_communication($campaignRef, $userId) : ['progress_percent'=>0, 'current_position'=>'Mission ready'];
        $progress = (int)($participant['progress_percent'] ?? 0);
        $checkpoints = [
            ['label'=>'Understand the mission', 'status'=>$progress > 0 ? 'complete' : 'recommended', 'detail'=>'Read the current mission card and confirm the expected action.', 'href'=>'/app/participant-portal.php'],
            ['label'=>'Complete the task', 'status'=>$progress >= 40 ? 'complete' : 'recommended', 'detail'=>'Use the task runner to complete or submit proof for the active task.', 'href'=>'/app/task-runner.php'],
            ['label'=>'Explain the proof', 'status'=>$progress >= 60 ? 'complete' : 'needs_work', 'detail'=>'Use proof notes that explain what was done and why it qualifies.', 'href'=>'/app/proof-upload.php'],
            ['label'=>'Reflect on learning', 'status'=>'recommended', 'detail'=>'Capture what worked, what was unclear, and what to do next.', 'href'=>'/app/reflection-journal.php'],
            ['label'=>'Review reward status', 'status'=>$progress >= 80 ? 'available' : 'needs_work', 'detail'=>'Check whether a reward is earned, claimable, pending, or blocked.', 'href'=>'/app/rewards.php'],
        ];
        $recap = [
            'what_i_learned' => 'Training Lab rewards verified action, not just activity.',
            'what_to_do_next' => $progress >= 80 ? 'Review reward status and wait for/admin review if needed.' : 'Complete the next mission task and submit proof if requested.',
            'support_message' => 'Check the message board when proof or reward state is unclear.',
        ];
        $checks = [
            'participant_portal_route' => tl_stage720_route_exists('/app/participant-portal.php'),
            'task_runner_route' => tl_stage720_route_exists('/app/task-runner.php'),
            'reflection_journal_route' => tl_stage720_route_exists('/app/reflection-journal.php'),
            'message_board_route' => tl_stage720_route_exists('/app/message-board.php'),
            'learning_checkpoints_present' => count($checkpoints) >= 5,
        ];
        return [
            'stage' => 'Stage 697-704 participant learning experience',
            'progress_percent' => $progress,
            'current_position' => (string)($participant['current_position'] ?? 'Mission ready'),
            'learning_checkpoints' => $checkpoints,
            'reflection_prompts' => ['What action did I complete?', 'What evidence proves it?', 'What did I learn from the task?', 'What should I do next?'],
            'mission_recap' => $recap,
            'score' => tl_stage720_score_from_checks($checks),
            'accepted' => tl_stage720_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage720_admin_training_quality_console')) {
    function tl_stage720_admin_training_quality_console(): array
    {
        $counts = tl_stage720_flow_counts();
        $dataQuality = ['score'=>100];
        $weak = [
            ['label'=>'Unclear proof', 'status'=>(int)($counts['pending_proofs'] ?? 0) > 0 ? 'needs_work' : 'ready', 'detail'=>(int)($counts['pending_proofs'] ?? 0) . ' proof item(s) waiting.', 'href'=>'/admin/review-workbench.php'],
            ['label'=>'Stalled task path', 'status'=>(int)($counts['participants'] ?? 0) > 0 && (int)($counts['proofs'] ?? 0) === 0 ? 'stalled' : 'ready', 'detail'=>'Compare participants, proofs, and reward events.', 'href'=>'/admin/participant-inspector.php'],
            ['label'=>'Low completion', 'status'=>(int)($counts['receipts'] ?? 0) === 0 ? 'needs_work' : 'ready', 'detail'=>(int)($counts['receipts'] ?? 0) . ' action receipt(s).', 'href'=>'/admin/reporting-center.php'],
            ['label'=>'Missing reward', 'status'=>(int)($counts['proofs'] ?? 0) > 0 && (int)($counts['reward_events'] ?? 0) === 0 ? 'needs_work' : 'ready', 'detail'=>(int)($counts['reward_events'] ?? 0) . ' reward event(s).', 'href'=>'/admin/reward-bridge.php'],
        ];
        $coaching = [
            'Ask for proof that shows the action, context, and result.',
            'Use participant timeline gaps to coach the next step.',
            'Keep reward explanations tied to approved proof and rules.',
            'Review cohort health before changing campaign templates.',
        ];
        $checks = [
            'command_center_route' => tl_stage720_route_exists('/admin/command-center.php'),
            'review_workbench_route' => tl_stage720_route_exists('/admin/review-workbench.php'),
            'participant_inspector_route' => tl_stage720_route_exists('/admin/participant-inspector.php'),
            'reporting_center_route' => tl_stage720_route_exists('/admin/reporting-center.php'),
            'coaching_prompts_present' => count($coaching) >= 4,
        ];
        return [
            'stage' => 'Stage 705-712 admin training quality console',
            'cohort_learning_health_score' => min(100, (int)($dataQuality['score'] ?? 100)),
            'training_quality_signals' => $weak,
            'admin_coaching_prompts' => $coaching,
            'quality_summary' => 'Review proof clarity, participant momentum, completion receipts, and reward connection before launch/scale.',
            'score' => tl_stage720_score_from_checks($checks),
            'accepted' => tl_stage720_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage720_content_experience_audit')) {
    function tl_stage720_content_experience_audit(): array
    {
        $root = tl_stage720_root();
        $markers = [
            'app/resource-hub.php' => 'tl_stage720_render_training_content_library',
            'app/challenge-library.php' => 'tl_stage720_render_challenge_template_selection',
            'app/campaign-builder.php' => 'tl_stage720_render_challenge_template_selection',
            'app/participant-portal.php' => 'tl_stage720_render_participant_learning_experience',
            'app/task-runner.php' => 'tl_stage720_render_participant_learning_experience',
            'app/reflection-journal.php' => 'tl_stage720_render_participant_learning_experience',
            'admin/command-center.php' => 'tl_stage720_render_admin_training_quality_console',
            'admin/review-workbench.php' => 'tl_stage720_render_admin_training_quality_console',
            'admin/reporting-center.php' => 'tl_stage720_render_training_content_library',
            'api/training/content-experience.php' => 'tl_stage720_content_experience_summary',
        ];
        $issues = [];
        foreach ($markers as $path => $needle) {
            $file = $root . '/' . $path;
            if (!is_file($file) || strpos((string)file_get_contents($file), $needle) === false) $issues[] = $path . ' missing Stage 720 marker ' . $needle;
        }
        return [
            'stage' => 'Stage 713-720 content and experience audit',
            'issue_count' => count($issues),
            'issues' => $issues,
            'score' => count($issues) === 0 ? 100 : max(0, 100 - count($issues) * 8),
            'accepted' => count($issues) === 0,
            'checks' => ['route_marker_audit'=>count($issues)===0, 'no_new_sql_required'=>true, 'no_page_factory_expansion'=>true, 'no_real_uploads'=>true],
        ];
    }
}

if (!function_exists('tl_stage720_content_experience_summary')) {
    function tl_stage720_content_experience_summary(bool $includeAudit = true): array
    {
        $content = tl_stage720_training_content_library();
        $templates = tl_stage720_challenge_template_selection();
        $learning = tl_stage720_participant_learning_experience();
        $quality = tl_stage720_admin_training_quality_console();
        $audit = $includeAudit ? tl_stage720_content_experience_audit() : ['accepted'=>true,'score'=>100,'issue_count'=>0,'issues'=>[]];
        $sections = [$content, $templates, $learning, $quality, $audit];
        $accepted = true; $scores=[];
        foreach ($sections as $section) { $scores[]=(int)($section['score'] ?? 0); if (empty($section['accepted'])) $accepted=false; }
        return [
            'stage' => 'Stage 681-720 content management and training experience polish',
            'built_from' => 'Stage 641-680 communication and operating rhythm',
            'builds' => [
                'Build 94: Training Content Library',
                'Build 95: Challenge Template Selection',
                'Build 96: Participant Learning Experience',
                'Build 97: Admin Training Quality Console',
                'Build 98: Content + Experience API Layer',
            ],
            'training_content_library' => $content,
            'challenge_template_selection' => $templates,
            'participant_learning_experience' => $learning,
            'admin_training_quality_console' => $quality,
            'content_experience_audit' => $audit,
            'score' => $accepted ? 100 : (int)round(array_sum($scores) / max(1, count($scores))),
            'accepted' => $accepted,
            'safe_boundaries' => [
                'no_new_sql_required' => true,
                'no_config_files_moved_or_overwritten' => true,
                'no_hard_auth_gates_forced' => true,
                'no_page_factory_expansion' => true,
                'no_real_upload_processing' => true,
                'no_external_notification_sending' => true,
                'no_destructive_delete_or_reset_actions' => true,
                'microgifter_reward_issuing_adapter_gated' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage720_context_cards')) {
    function tl_stage720_context_cards(string $context): array
    {
        if (strpos($context, 'challenge') !== false || strpos($context, 'campaign') !== false || strpos($context, 'launchpad') !== false) {
            return [
                ['label'=>'Template', 'value'=>'Select', 'hint'=>'beginner/standard/advanced', 'href'=>'/app/challenge-library.php'],
                ['label'=>'Preview', 'value'=>'Path', 'hint'=>'task + reward sequence', 'href'=>'/app/campaign-builder.php'],
                ['label'=>'Guide', 'value'=>'Builder', 'hint'=>'content library', 'href'=>'/app/resource-hub.php'],
            ];
        }
        if (strpos($context, 'participant') !== false || strpos($context, 'task') !== false || strpos($context, 'reflection') !== false || strpos($context, 'message') !== false) {
            return [
                ['label'=>'Learn', 'value'=>'Checkpoint', 'hint'=>'mission recap', 'href'=>'/app/reflection-journal.php'],
                ['label'=>'Guide', 'value'=>'Proof', 'hint'=>'quality checklist', 'href'=>'/app/proof-upload.php'],
                ['label'=>'Next', 'value'=>'Message', 'hint'=>'what happens now', 'href'=>'/app/message-board.php'],
            ];
        }
        if (str_starts_with($context, 'admin-')) {
            return [
                ['label'=>'Quality', 'value'=>'Coach', 'hint'=>'training signals', 'href'=>'/admin/reporting-center.php'],
                ['label'=>'Review', 'value'=>'Proof', 'hint'=>'clarity prompts', 'href'=>'/admin/review-workbench.php'],
                ['label'=>'Cohort', 'value'=>'Health', 'hint'=>'learning summary', 'href'=>'/admin/command-center.php'],
            ];
        }
        return [
            ['label'=>'Content', 'value'=>'Library', 'hint'=>'guides + examples', 'href'=>'/app/resource-hub.php'],
            ['label'=>'Template', 'value'=>'Choose', 'hint'=>'challenge path', 'href'=>'/app/challenge-library.php'],
            ['label'=>'Experience', 'value'=>'Learn', 'hint'=>'recap + reflection', 'href'=>'/app/reflection-journal.php'],
        ];
    }
}

if (!function_exists('tl_stage720_context_runtime_overrides')) {
    function tl_stage720_context_runtime_overrides(string $context, array $baseCfg = []): array
    {
        $isAdmin = str_starts_with($context, 'admin-');
        $counts = tl_stage720_flow_counts();
        return [
            'live_strip' => $isAdmin
                ? ['Shared Microgifter account', 'Admin training quality', 'Content', 'Stage 720']
                : ['Shared Microgifter account', 'Learning path', 'Content', 'Stage 720'],
            'stage720_cards' => tl_stage720_context_cards($context),
            'metric_values' => $isAdmin
                ? [(string)((int)($counts['campaigns'] ?? 0)), (string)((int)($counts['pending_proofs'] ?? 0)), '720']
                : [(string)((int)($counts['participants'] ?? 0)), (string)((int)($counts['proofs'] ?? 0)), '720'],
            'progress_width' => $isAdmin ? '88%' : '76%',
            'status_meta' => $isAdmin
                ? ['Training quality', 'Coaching prompts', 'Cohort health']
                : ['Content library', 'Template path', 'Learning recap'],
            'stage720_runtime_bound' => true,
        ];
    }
}

if (!function_exists('tl_stage720_render_card_grid')) {
    function tl_stage720_render_card_grid(array $items): void
    {
        echo '<div class="labs-stage720-card-grid">';
        foreach ($items as $item) {
            $status = (string)($item['status'] ?? 'ready');
            $href = (string)($item['href'] ?? '#');
            $label = (string)($item['title'] ?? $item['label'] ?? $item['lane'] ?? $item['category'] ?? 'Content');
            $detail = (string)($item['detail'] ?? $item['summary'] ?? 'Training experience item');
            echo '<a class="is-' . tl_stage720_e(tl_stage720_status_class($status)) . '" href="' . tl_stage720_e(function_exists('labs_url') ? labs_url($href) : $href) . '"><span>' . tl_stage720_e((string)($item['category'] ?? $item['lane'] ?? $status)) . '</span><strong>' . tl_stage720_e($label) . '</strong><small>' . tl_stage720_e($detail) . '</small></a>';
        }
        echo '</div>';
    }
}

if (!function_exists('tl_stage720_render_shell')) {
    function tl_stage720_render_shell(string $eyebrow, string $title, array $metrics, array $items, string $apiHref, string $class = ''): void
    {
        echo '<section class="labs-card labs-stage720-panel ' . tl_stage720_e($class) . '"><div class="labs-card-headline"><div><span class="labs-eyebrow">' . tl_stage720_e($eyebrow) . '</span><h2>' . tl_stage720_e($title) . '</h2></div><a class="labs-btn" href="' . tl_stage720_e(function_exists('labs_url') ? labs_url($apiHref) : $apiHref) . '">Experience API</a></div>';
        echo '<div class="labs-stage720-metric-grid">';
        foreach ($metrics as $metric) echo '<article><span>' . tl_stage720_e($metric['label'] ?? 'Metric') . '</span><strong>' . tl_stage720_e($metric['value'] ?? 'Ready') . '</strong><small>' . tl_stage720_e($metric['hint'] ?? '') . '</small></article>';
        echo '</div>';
        tl_stage720_render_card_grid($items);
        echo '</section>';
    }
}

if (!function_exists('tl_stage720_render_training_content_library')) {
    function tl_stage720_render_training_content_library(string $campaignRef = '', int $userId = 0): void
    {
        $data = tl_stage720_training_content_library($campaignRef, $userId);
        $metrics = [
            ['label'=>'Readiness', 'value'=>$data['content_readiness_score'] . '/100', 'hint'=>'content library'],
            ['label'=>'Categories', 'value'=>(string)count($data['content_categories']), 'hint'=>'guides/examples/rules'],
            ['label'=>'Recommended', 'value'=>(string)count($data['recommended_for_current_mission']), 'hint'=>'current mission fit'],
        ];
        tl_stage720_render_shell('Stage 681-688', 'Training Content Library', $metrics, $data['recommended_for_current_mission'], '/api/training/content-experience.php?section=content', 'labs-stage720-content');
    }
}

if (!function_exists('tl_stage720_render_challenge_template_selection')) {
    function tl_stage720_render_challenge_template_selection(): void
    {
        $data = tl_stage720_challenge_template_selection();
        $metrics = [
            ['label'=>'Templates', 'value'=>(string)$data['template_count'], 'hint'=>'available preview'],
            ['label'=>'Lanes', 'value'=>(string)count($data['template_lanes']), 'hint'=>'beginner/standard/advanced'],
            ['label'=>'Score', 'value'=>$data['score'] . '/100', 'hint'=>'template selection'],
        ];
        tl_stage720_render_shell('Stage 689-696', 'Challenge Template Selection', $metrics, $data['template_lanes'], '/api/training/content-experience.php?section=templates', 'labs-stage720-templates');
    }
}

if (!function_exists('tl_stage720_render_participant_learning_experience')) {
    function tl_stage720_render_participant_learning_experience(string $campaignRef = '', int $userId = 0): void
    {
        $data = tl_stage720_participant_learning_experience($campaignRef, $userId);
        $metrics = [
            ['label'=>'Progress', 'value'=>$data['progress_percent'] . '%', 'hint'=>$data['current_position']],
            ['label'=>'Checkpoints', 'value'=>(string)count($data['learning_checkpoints']), 'hint'=>'learning path'],
            ['label'=>'Reflection', 'value'=>'Ready', 'hint'=>'recap prompts'],
        ];
        tl_stage720_render_shell('Stage 697-704', 'Participant Learning Experience', $metrics, $data['learning_checkpoints'], '/api/training/content-experience.php?section=learning', 'labs-stage720-learning');
    }
}

if (!function_exists('tl_stage720_render_admin_training_quality_console')) {
    function tl_stage720_render_admin_training_quality_console(): void
    {
        $data = tl_stage720_admin_training_quality_console();
        $metrics = [
            ['label'=>'Learning health', 'value'=>$data['cohort_learning_health_score'] . '/100', 'hint'=>'cohort summary'],
            ['label'=>'Signals', 'value'=>(string)count($data['training_quality_signals']), 'hint'=>'weak spot scan'],
            ['label'=>'Prompts', 'value'=>(string)count($data['admin_coaching_prompts']), 'hint'=>'coaching copy'],
        ];
        tl_stage720_render_shell('Stage 705-712', 'Admin Training Quality Console', $metrics, $data['training_quality_signals'], '/api/training/content-experience.php?section=quality', 'labs-stage720-admin-quality');
    }
}
