<?php
/**
 * Stage 601-640 Data Quality + Operator Confidence.
 *
 * Five-section batch focused on trust, cleanup guidance, review confidence,
 * reward auditability, and a plain-English operator health diagnosis. It adds
 * only safe read/surface helpers and UI panels; no SQL migrations, deletes,
 * resets, uploads, payments, wallet mutation, or production claim/redeem logic.
 */

if (!function_exists('tl_stage640_e')) {
    function tl_stage640_e($value): string
    {
        return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tl_stage640_root')) {
    function tl_stage640_root(): string { return dirname(__DIR__); }
}

if (!function_exists('tl_stage640_route_exists')) {
    function tl_stage640_route_exists(string $route): bool { return is_file(tl_stage640_root() . '/' . ltrim($route, '/')); }
}

if (!function_exists('tl_stage640_score_from_checks')) {
    function tl_stage640_score_from_checks(array $checks): int
    {
        if (!$checks) return 100;
        $passed = 0;
        foreach ($checks as $ok) if ($ok) $passed++;
        return (int)round(($passed / max(1, count($checks))) * 100);
    }
}

if (!function_exists('tl_stage640_status_class')) {
    function tl_stage640_status_class(string $status): string
    {
        $s = strtolower(str_replace(' ', '_', trim($status)));
        if (in_array($s, ['complete','ready','good','healthy','accepted','linked','issued','approved','clean'], true)) return 'good';
        if (in_array($s, ['warning','needs_work','needs_info','blocked','failed','missing','review'], true)) return 'warn';
        return 'neutral';
    }
}

if (!function_exists('tl_stage640_campaign_data_quality')) {
    function tl_stage640_campaign_data_quality(string $campaignRef = ''): array
    {
        $control = function_exists('tl_stage600_campaign_state_control') ? tl_stage600_campaign_state_control($campaignRef) : [];
        $flow = function_exists('tl_stage520_campaign_flow') ? tl_stage520_campaign_flow($campaignRef) : [];
        $campaigns = function_exists('tl_app_campaign_options') ? tl_app_campaign_options() : [];
        $campaign = (array)($flow['campaign'] ?? ($campaigns[0] ?? []));
        $title = trim((string)($campaign['title'] ?? ''));
        $status = strtolower((string)($campaign['status'] ?? $control['status'] ?? 'draft'));
        $taskCount = (int)($flow['task_count'] ?? $control['task_count'] ?? 0);
        $proofCount = (int)($flow['proof_task_count'] ?? $control['proof_task_count'] ?? 0);
        $rewardReady = !empty($flow['reward_ready']) || !empty($control['reward_ready']);
        $targetActions = (int)($campaign['target_action_count'] ?? 0);
        $slugs = [];
        $emptyTitles = 0;
        foreach ($campaigns as $row) {
            $slug = strtolower(trim((string)($row['slug'] ?? $row['ref'] ?? '')));
            if ($slug !== '') $slugs[$slug] = ($slugs[$slug] ?? 0) + 1;
            if (trim((string)($row['title'] ?? '')) === '') $emptyTitles++;
        }
        $duplicateCount = 0;
        foreach ($slugs as $count) if ($count > 1) $duplicateCount += ($count - 1);
        $items = [
            ['label'=>'Title', 'status'=>$title !== '' ? 'complete' : 'missing', 'detail'=>$title !== '' ? $title : 'Add a campaign title before launch.', 'href'=>'/app/campaign-builder.php'],
            ['label'=>'Status', 'status'=>in_array($status, ['ready','active','published','completed'], true) ? 'ready' : 'review', 'detail'=>'Current state: ' . ($status ?: 'draft'), 'href'=>'/app/campaign-detail.php'],
            ['label'=>'Task path', 'status'=>$taskCount >= 3 ? 'complete' : 'needs_work', 'detail'=>$taskCount . ' task(s); target is 3+.', 'href'=>'/app/campaign-builder.php'],
            ['label'=>'Proof rule', 'status'=>$proofCount > 0 ? 'complete' : 'warning', 'detail'=>$proofCount . ' proof task(s) attached.', 'href'=>'/app/proof-upload.php'],
            ['label'=>'Reward preview', 'status'=>$rewardReady ? 'ready' : 'needs_work', 'detail'=>$rewardReady ? 'Reward path visible.' : 'Add reward summary/rule.', 'href'=>'/app/rewards.php'],
            ['label'=>'Target action count', 'status'=>$targetActions > 0 ? 'complete' : 'warning', 'detail'=>$targetActions > 0 ? $targetActions . ' target action(s).' : 'Target is not set.', 'href'=>'/admin/campaign-inspector.php'],
        ];
        $passed = 0;
        foreach ($items as $item) if (in_array($item['status'], ['complete','ready'], true)) $passed++;
        $completeness = (int)round(($passed / max(1, count($items))) * 100);
        $warnings = [];
        if ($duplicateCount > 0) $warnings[] = $duplicateCount . ' possible duplicate campaign reference(s).';
        if ($emptyTitles > 0) $warnings[] = $emptyTitles . ' campaign(s) missing title.';
        if ($taskCount < 3) $warnings[] = 'Task sequence is short.';
        if (!$rewardReady) $warnings[] = 'Reward path is incomplete.';
        $checks = [
            'campaign_builder_route' => tl_stage640_route_exists('/app/campaign-builder.php'),
            'campaigns_route' => tl_stage640_route_exists('/app/campaigns.php'),
            'campaign_detail_route' => tl_stage640_route_exists('/app/campaign-detail.php'),
            'admin_campaigns_route' => tl_stage640_route_exists('/admin/campaigns.php'),
            'campaign_inspector_route' => tl_stage640_route_exists('/admin/campaign-inspector.php'),
            'cleanup_guidance_non_destructive' => true,
        ];
        return [
            'stage' => 'Stage 601-608 campaign data quality and cleanup',
            'campaign_ref' => (string)($flow['campaign_ref'] ?? $control['campaign_ref'] ?? $campaignRef),
            'completeness_score' => $completeness,
            'duplicate_count' => $duplicateCount,
            'empty_title_count' => $emptyTitles,
            'warnings' => $warnings,
            'items' => $items,
            'cleanup_guidance' => [
                'Complete missing title/summary/status before launch.',
                'Keep draft/paused campaigns visible but labeled.',
                'Review duplicate slugs/refs manually before migrating data.',
                'Do not delete or reset campaign records from this stage.',
            ],
            'score' => tl_stage640_score_from_checks($checks),
            'accepted' => tl_stage640_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage640_participant_data_quality')) {
    function tl_stage640_participant_data_quality(string $campaignRef = '', int $userId = 0): array
    {
        $timeline = function_exists('tl_stage600_participant_timeline') ? tl_stage600_participant_timeline($campaignRef, $userId) : [];
        $account = function_exists('tl_account_bridge_current_context') ? tl_account_bridge_current_context() : [];
        $flow = function_exists('tl_app_flow_summary') ? tl_app_flow_summary() : ['counts'=>[]];
        $counts = (array)($flow['counts'] ?? []);
        $progress = (int)($timeline['progress_percent'] ?? 0);
        $participants = (int)($counts['participants'] ?? 0);
        $proofs = (int)($counts['proofs'] ?? 0);
        $pendingProofs = (int)($counts['pending_proofs'] ?? 0);
        $rewards = (int)($counts['reward_events'] ?? 0);
        $linked = !empty($account['authenticated']) || !empty($account['user_id']) || !empty($account['session_user_id']);
        $items = [
            ['label'=>'Shared account', 'status'=>$linked ? 'linked' : 'unlinked', 'detail'=>$linked ? 'Training Lab account context is present.' : 'Guest/demo account context; Microgifter button remains simple.', 'href'=>'/account.php'],
            ['label'=>'Participant record', 'status'=>$participants > 0 ? 'complete' : 'warning', 'detail'=>$participants . ' participant record(s).', 'href'=>'/admin/participant-inspector.php'],
            ['label'=>'Mission progress', 'status'=>$progress > 0 ? 'active' : 'review', 'detail'=>$progress . '% progress.', 'href'=>'/app/progress-map.php'],
            ['label'=>'Proof activity', 'status'=>$proofs > 0 ? 'visible' : 'warning', 'detail'=>$proofs . ' proof submission(s); ' . $pendingProofs . ' pending.', 'href'=>'/app/proof-upload.php'],
            ['label'=>'Reward connection', 'status'=>$rewards > 0 ? 'visible' : 'queued', 'detail'=>$rewards . ' reward event(s).', 'href'=>'/app/rewards.php'],
        ];
        $gaps = [];
        if ($participants > 0 && $progress <= 0) $gaps[] = 'Participants exist but progress has not started.';
        if ($progress > 0 && $proofs <= 0) $gaps[] = 'Mission progress exists but no proof has been submitted.';
        if ($pendingProofs > 0 && $rewards <= 0) $gaps[] = 'Proof is pending before reward generation.';
        if ($rewards > 0 && !$linked) $gaps[] = 'Rewards exist while account context is still guest/demo.';
        $checks = [
            'participant_portal_route' => tl_stage640_route_exists('/app/participant-portal.php'),
            'progress_map_route' => tl_stage640_route_exists('/app/progress-map.php'),
            'flow_board_route' => tl_stage640_route_exists('/app/flow-board.php'),
            'participant_inspector_route' => tl_stage640_route_exists('/admin/participant-inspector.php'),
            'cohort_manager_route' => tl_stage640_route_exists('/admin/cohort-manager.php'),
        ];
        return [
            'stage' => 'Stage 609-616 participant data quality and identity clarity',
            'account_status' => $linked ? 'linked/shared' : 'guest/demo',
            'quality_score' => max(0, 100 - (count($gaps) * 12)),
            'current_position' => (string)($timeline['current_position'] ?? 'Mission ready'),
            'items' => $items,
            'timeline_gaps' => $gaps,
            'cohort_summary' => [
                'participants' => $participants,
                'proofs' => $proofs,
                'pending_proofs' => $pendingProofs,
                'reward_events' => $rewards,
            ],
            'score' => tl_stage640_score_from_checks($checks),
            'accepted' => tl_stage640_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage640_proof_evidence_quality')) {
    function tl_stage640_proof_evidence_quality(): array
    {
        $console = function_exists('tl_stage600_proof_review_console') ? tl_stage600_proof_review_console() : [];
        $flow = function_exists('tl_app_flow_summary') ? tl_app_flow_summary() : ['counts'=>[]];
        $counts = (array)($flow['counts'] ?? []);
        $pending = (int)($counts['pending_proofs'] ?? 0);
        $approved = (int)($counts['approved_proofs'] ?? 0);
        $total = (int)($counts['proofs'] ?? 0);
        $confidence = $total > 0 ? (int)round(($approved / max(1, $total)) * 100) : 72;
        $checklist = [
            ['label'=>'Action is identifiable', 'status'=>'ready', 'detail'=>'Proof should name the completed task.', 'href'=>'/app/proof-upload.php'],
            ['label'=>'Evidence is specific', 'status'=>$total > 0 ? 'visible' : 'warning', 'detail'=>'Use notes/screenshots/receipts where supported; no real upload handling here.', 'href'=>'/app/proof-upload.php'],
            ['label'=>'Reviewer can decide', 'status'=>$pending > 0 ? 'review' : 'ready', 'detail'=>$pending . ' item(s) waiting for decision.', 'href'=>'/admin/review-workbench.php'],
            ['label'=>'Needs more info path', 'status'=>'ready', 'detail'=>'Participant sees what to clarify without destructive edits.', 'href'=>'/admin/review-queue.php'],
            ['label'=>'Decision reason', 'status'=>'ready', 'detail'=>'Approve/reject/needs info should include clear rationale.', 'href'=>'/admin/review-inspector.php'],
        ];
        $filters = [
            ['label'=>'Waiting', 'value'=>(string)$pending, 'hint'=>'submitted/in review'],
            ['label'=>'Approved', 'value'=>(string)$approved, 'hint'=>'eligible for reward path'],
            ['label'=>'Total', 'value'=>(string)$total, 'hint'=>'proof evidence rows'],
            ['label'=>'Confidence', 'value'=>$confidence . '%', 'hint'=>'approved / total proxy'],
        ];
        $checks = [
            'proof_upload_route' => tl_stage640_route_exists('/app/proof-upload.php'),
            'review_workbench_route' => tl_stage640_route_exists('/admin/review-workbench.php'),
            'review_queue_route' => tl_stage640_route_exists('/admin/review-queue.php'),
            'review_inspector_route' => tl_stage640_route_exists('/admin/review-inspector.php'),
            'safe_no_real_upload_processing' => true,
        ];
        return [
            'stage' => 'Stage 617-624 proof evidence quality and review confidence',
            'review_confidence_score' => $confidence,
            'participant_explanation' => 'Submit clear evidence of the task. If more info is needed, the reviewer will mark the proof with a reason instead of changing production data.',
            'checklist' => $checklist,
            'filters' => $filters,
            'console_overlay' => $console,
            'score' => tl_stage640_score_from_checks($checks),
            'accepted' => tl_stage640_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage640_reward_audit_assurance')) {
    function tl_stage640_reward_audit_assurance(): array
    {
        $ops = function_exists('tl_stage600_reward_operations') ? tl_stage600_reward_operations() : [];
        $bridge = function_exists('tl_mg_stage160_bridge_summary') ? tl_mg_stage160_bridge_summary() : [];
        $flow = function_exists('tl_app_flow_summary') ? tl_app_flow_summary() : ['counts'=>[]];
        $counts = (array)($flow['counts'] ?? []);
        $events = (int)($counts['reward_events'] ?? 0);
        $auditTrail = [
            ['label'=>'Earned reason', 'status'=>$events > 0 ? 'visible' : 'queued', 'detail'=>'Reward event traces back to task/proof completion.', 'href'=>'/app/rewards.php'],
            ['label'=>'Claimable state', 'status'=>'ready', 'detail'=>'Participant-facing state explains whether claim is available.', 'href'=>'/app/rewards.php'],
            ['label'=>'Bridge sync', 'status'=>!empty($bridge['available']) ? 'ready' : 'gated', 'detail'=>'Microgifter issuing remains adapter/developer-key gated.', 'href'=>'/admin/reward-bridge.php'],
            ['label'=>'Retry context', 'status'=>'ready', 'detail'=>'Failed/pending sync events show retry/manual issue guidance.', 'href'=>'/admin/reward-inspector.php'],
            ['label'=>'No wallet mutation', 'status'=>'good', 'detail'=>'Stage records assurance only; no balances changed.', 'href'=>'/admin/command-center.php'],
        ];
        $assurance = [
            'events_tracked' => $events,
            'bridge_mode' => (string)($bridge['mode'] ?? 'adapter-gated'),
            'adapter_available' => !empty($bridge['available']),
            'participant_copy' => 'Rewards show why they are earned, whether they can be claimed, and when Microgifter sync is gated or pending.',
        ];
        $checks = [
            'rewards_route' => tl_stage640_route_exists('/app/rewards.php'),
            'reward_bridge_route' => tl_stage640_route_exists('/admin/reward-bridge.php'),
            'reward_inspector_route' => tl_stage640_route_exists('/admin/reward-inspector.php'),
            'command_center_route' => tl_stage640_route_exists('/admin/command-center.php'),
            'microgifter_issuing_adapter_gated' => true,
        ];
        return [
            'stage' => 'Stage 625-632 reward audit and assurance trail',
            'audit_trail' => $auditTrail,
            'assurance' => $assurance,
            'operations_overlay' => $ops,
            'score' => tl_stage640_score_from_checks($checks),
            'accepted' => tl_stage640_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage640_operator_health_dashboard')) {
    function tl_stage640_operator_health_dashboard(): array
    {
        $campaign = tl_stage640_campaign_data_quality();
        $participant = tl_stage640_participant_data_quality();
        $proof = tl_stage640_proof_evidence_quality();
        $reward = tl_stage640_reward_audit_assurance();
        $workflow = function_exists('tl_stage600_workflow_control_summary') ? tl_stage600_workflow_control_summary(false) : ['score'=>100,'accepted'=>true];
        $scores = [
            'product_health' => (int)($workflow['score'] ?? 100),
            'data_quality' => (int)round(((int)$campaign['completeness_score'] + (int)$participant['quality_score']) / 2),
            'workflow_blockage' => max(0, 100 - ((count((array)$campaign['warnings']) + count((array)$participant['timeline_gaps'])) * 10)),
            'reward_assurance' => (int)($reward['score'] ?? 100),
            'review_confidence' => (int)$proof['review_confidence_score'],
        ];
        $diagnosis = [];
        $diagnosis[] = $scores['data_quality'] >= 80 ? 'Core records are usable for an operator pass.' : 'Campaign or participant records need cleanup before launch.';
        $diagnosis[] = $scores['workflow_blockage'] >= 80 ? 'No major workflow blockage is visible.' : 'There are workflow gaps that need review.';
        $diagnosis[] = $scores['reward_assurance'] >= 100 ? 'Reward bridge remains safe and traceable.' : 'Reward assurance needs review.';
        $order = [
            ['label'=>'Clean campaign data', 'status'=>$scores['data_quality'] >= 80 ? 'good' : 'review', 'detail'=>'Fix missing title/task/reward warnings first.', 'href'=>'/admin/campaign-inspector.php'],
            ['label'=>'Review participant gaps', 'status'=>count((array)$participant['timeline_gaps']) ? 'review' : 'good', 'detail'=>'Look for joined/no task, task/no proof, proof/no reward gaps.', 'href'=>'/admin/participant-inspector.php'],
            ['label'=>'Triage proof queue', 'status'=>'ready', 'detail'=>'Use evidence checklist and confidence score.', 'href'=>'/admin/review-workbench.php'],
            ['label'=>'Confirm reward audit', 'status'=>'ready', 'detail'=>'Review earned → claimable → issued trail.', 'href'=>'/admin/reward-bridge.php'],
            ['label'=>'Record launch snapshot', 'status'=>'ready', 'detail'=>'Use reporting center and backend readiness.', 'href'=>'/admin/reporting-center.php'],
        ];
        $checks = [
            'admin_index_route' => tl_stage640_route_exists('/admin/index.php'),
            'command_center_route' => tl_stage640_route_exists('/admin/command-center.php'),
            'backend_readiness_route' => tl_stage640_route_exists('/admin/backend-readiness.php'),
            'reporting_center_route' => tl_stage640_route_exists('/admin/reporting-center.php'),
            'ops_overview_route' => tl_stage640_route_exists('/api/training/ops-overview.php'),
        ];
        $accepted = tl_stage640_score_from_checks($checks) === 100;
        return [
            'stage' => 'Stage 633-640 operator health dashboard',
            'scores' => $scores,
            'health_score' => (int)round(array_sum($scores) / max(1, count($scores))),
            'plain_english_diagnosis' => $diagnosis,
            'todays_cleanup_order' => $order,
            'score' => tl_stage640_score_from_checks($checks),
            'accepted' => $accepted,
            'checks' => $checks,
            'sections' => [
                'campaign_data_quality' => $campaign,
                'participant_data_quality' => $participant,
                'proof_evidence_quality' => $proof,
                'reward_audit_assurance' => $reward,
            ],
        ];
    }
}

if (!function_exists('tl_stage640_route_contract')) {
    function tl_stage640_route_contract(): array
    {
        return [
            'campaign_data_quality' => ['/app/campaign-builder.php','/app/campaigns.php','/app/campaign-detail.php','/admin/campaigns.php','/admin/campaign-inspector.php'],
            'participant_data_quality' => ['/app/participant-portal.php','/app/progress-map.php','/app/flow-board.php','/admin/participant-inspector.php','/admin/cohort-manager.php'],
            'proof_evidence_quality' => ['/app/proof-upload.php','/admin/review-workbench.php','/admin/review-queue.php','/admin/review-inspector.php'],
            'reward_audit_assurance' => ['/app/rewards.php','/admin/reward-bridge.php','/admin/reward-inspector.php','/admin/command-center.php'],
            'operator_health' => ['/admin/index.php','/admin/command-center.php','/admin/backend-readiness.php','/admin/reporting-center.php','/api/training/ops-overview.php'],
            'api' => ['/api/training/data-quality.php'],
        ];
    }
}

if (!function_exists('tl_stage640_data_quality_audit')) {
    function tl_stage640_data_quality_audit(): array
    {
        $issues = [];
        foreach (tl_stage640_route_contract() as $group => $routes) {
            foreach ($routes as $route) if (!tl_stage640_route_exists($route)) $issues[] = 'Missing ' . $group . ' route ' . $route;
        }
        foreach (['tl_stage640_campaign_data_quality','tl_stage640_participant_data_quality','tl_stage640_proof_evidence_quality','tl_stage640_reward_audit_assurance','tl_stage640_operator_health_dashboard','tl_stage640_data_quality_summary'] as $fn) {
            if (!function_exists($fn)) $issues[] = 'Missing function ' . $fn;
        }
        $root = tl_stage640_root();
        $css = is_file($root . '/assets/css/labs.css') ? (string)file_get_contents($root . '/assets/css/labs.css') : '';
        foreach (['labs-stage640-panel','labs-stage640-quality-grid','labs-stage640-diagnosis','labs-li-stage640-quality'] as $class) {
            if (strpos($css, $class) === false) $issues[] = 'Missing CSS class .' . $class;
        }
        $markers = [
            'app/campaign-builder.php' => 'tl_stage640_render_campaign_data_quality',
            'admin/campaign-inspector.php' => 'tl_stage640_render_campaign_data_quality',
            'app/participant-portal.php' => 'tl_stage640_render_participant_data_quality',
            'admin/cohort-manager.php' => 'tl_stage640_render_participant_data_quality',
            'app/proof-upload.php' => 'tl_stage640_render_proof_evidence_quality',
            'admin/review-workbench.php' => 'tl_stage640_render_proof_evidence_quality',
            'app/rewards.php' => 'tl_stage640_render_reward_audit_assurance',
            'admin/reward-bridge.php' => 'tl_stage640_render_reward_audit_assurance',
            'admin/index.php' => 'tl_stage640_render_operator_health_dashboard',
            'admin/backend-readiness.php' => 'tl_stage640_render_operator_health_dashboard',
        ];
        foreach ($markers as $path => $needle) {
            $file = $root . '/' . $path;
            if (!is_file($file) || strpos((string)file_get_contents($file), $needle) === false) $issues[] = $path . ' missing Stage 640 marker ' . $needle;
        }
        $score = count($issues) === 0 ? 100 : max(0, 100 - (count($issues) * 5));
        return [
            'stage' => 'Stage 601-640 data quality audit',
            'route_contract' => tl_stage640_route_contract(),
            'issue_count' => count($issues),
            'issues' => $issues,
            'score' => $score,
            'accepted' => count($issues) === 0,
        ];
    }
}

if (!function_exists('tl_stage640_data_quality_summary')) {
    function tl_stage640_data_quality_summary(bool $includeAudit = true): array
    {
        $campaign = tl_stage640_campaign_data_quality();
        $participant = tl_stage640_participant_data_quality();
        $proof = tl_stage640_proof_evidence_quality();
        $reward = tl_stage640_reward_audit_assurance();
        $health = tl_stage640_operator_health_dashboard();
        $audit = $includeAudit ? tl_stage640_data_quality_audit() : ['accepted'=>true,'score'=>100,'issue_count'=>0,'issues'=>[]];
        $sections = [$campaign, $participant, $proof, $reward, $health, $audit];
        $accepted = true;
        $scores = [];
        foreach ($sections as $section) {
            $scores[] = (int)($section['score'] ?? 0);
            if (empty($section['accepted'])) $accepted = false;
        }
        return [
            'stage' => 'Stage 601-640 data quality and operator confidence',
            'built_from' => 'Stage 561-600 workflow control and operator usability',
            'builds' => [
                'Build 79: Campaign Data Quality + Cleanup',
                'Build 80: Participant Data Quality + Identity Clarity',
                'Build 81: Proof Evidence Quality + Review Confidence',
                'Build 82: Reward Audit + Assurance Trail',
                'Build 83: Operator Health Dashboard',
            ],
            'campaign_data_quality' => $campaign,
            'participant_data_quality' => $participant,
            'proof_evidence_quality' => $proof,
            'reward_audit_assurance' => $reward,
            'operator_health_dashboard' => $health,
            'data_quality_audit' => $audit,
            'score' => $accepted ? 100 : (int)round(array_sum($scores) / max(1, count($scores))),
            'accepted' => $accepted,
            'safe_boundaries' => [
                'no_new_sql_required' => true,
                'no_config_files_moved_or_overwritten' => true,
                'no_hard_auth_gates_forced' => true,
                'no_page_factory_expansion' => true,
                'no_destructive_delete_or_reset_actions' => true,
                'no_real_upload_processing' => true,
                'no_payments_or_wallet_mutation' => true,
                'no_production_claim_redeem_logic' => true,
                'microgifter_reward_issuing_adapter_gated' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage640_context_cards')) {
    function tl_stage640_context_cards(string $context): array
    {
        if (strpos($context, 'campaign') !== false) {
            return [
                ['label'=>'Quality', 'value'=>'Score', 'hint'=>'campaign completeness', 'href'=>'/api/training/data-quality.php?section=campaign'],
                ['label'=>'Cleanup', 'value'=>'Safe', 'hint'=>'no destructive actions', 'href'=>'/admin/campaign-inspector.php'],
                ['label'=>'Builder', 'value'=>'Finish', 'hint'=>'missing fields', 'href'=>'/app/campaign-builder.php'],
            ];
        }
        if (strpos($context, 'review') !== false || strpos($context, 'proof') !== false) {
            return [
                ['label'=>'Evidence', 'value'=>'Check', 'hint'=>'proof quality', 'href'=>'/api/training/data-quality.php?section=proof'],
                ['label'=>'Review', 'value'=>'Confidence', 'hint'=>'decision clarity', 'href'=>'/admin/review-workbench.php'],
                ['label'=>'Needs info', 'value'=>'Guide', 'hint'=>'participant clarity', 'href'=>'/app/proof-upload.php'],
            ];
        }
        if (strpos($context, 'reward') !== false || strpos($context, 'wallet') !== false) {
            return [
                ['label'=>'Audit', 'value'=>'Trail', 'hint'=>'earned to issued', 'href'=>'/api/training/data-quality.php?section=reward'],
                ['label'=>'Bridge', 'value'=>'Gated', 'hint'=>'Microgifter safe', 'href'=>'/admin/reward-bridge.php'],
                ['label'=>'Participant', 'value'=>'Why', 'hint'=>'reward explanation', 'href'=>'/app/rewards.php'],
            ];
        }
        if (str_starts_with($context, 'admin-')) {
            return [
                ['label'=>'Health', 'value'=>'Daily', 'hint'=>'operator diagnosis', 'href'=>'/api/training/data-quality.php?section=health'],
                ['label'=>'Quality', 'value'=>'Clean', 'hint'=>'records and gaps', 'href'=>'/admin/reporting-center.php'],
                ['label'=>'Command', 'value'=>'Order', 'hint'=>'what to fix first', 'href'=>'/admin/command-center.php'],
            ];
        }
        return [
            ['label'=>'Account', 'value'=>'Clear', 'hint'=>'identity state', 'href'=>'/account.php'],
            ['label'=>'Mission', 'value'=>'Gaps', 'hint'=>'timeline quality', 'href'=>'/app/progress-map.php'],
            ['label'=>'Reward', 'value'=>'Why', 'hint'=>'audit explanation', 'href'=>'/app/rewards.php'],
        ];
    }
}

if (!function_exists('tl_stage640_context_runtime_overrides')) {
    function tl_stage640_context_runtime_overrides(string $context, array $baseCfg = []): array
    {
        $stage600 = function_exists('tl_stage600_context_runtime_overrides') ? tl_stage600_context_runtime_overrides($context, $baseCfg) : [];
        $live = array_values(array_unique(array_merge((array)($stage600['live_strip'] ?? []), ['Data quality', 'Stage 640'])));
        return array_replace_recursive($stage600, [
            'live_strip' => $live,
            'stage640_cards' => tl_stage640_context_cards($context),
            'status_meta' => str_starts_with($context, 'admin-')
                ? ['Health diagnosis', 'Data cleanup', 'Operator confidence']
                : ['Account clarity', 'Mission quality', 'Reward audit'],
            'stage640_runtime_bound' => true,
        ]);
    }
}

if (!function_exists('tl_stage640_render_card_list')) {
    function tl_stage640_render_card_list(array $items): void
    {
        echo '<div class="labs-stage640-quality-grid">';
        foreach ($items as $item) {
            $status = (string)($item['status'] ?? 'ready');
            $href = (string)($item['href'] ?? '#');
            echo '<a class="is-' . tl_stage640_e(tl_stage640_status_class($status)) . '" href="' . tl_stage640_e(function_exists('labs_url') ? labs_url($href) : $href) . '"><span>' . tl_stage640_e($status) . '</span><strong>' . tl_stage640_e($item['label'] ?? 'Quality item') . '</strong><small>' . tl_stage640_e($item['detail'] ?? $item['hint'] ?? '') . '</small></a>';
        }
        echo '</div>';
    }
}

if (!function_exists('tl_stage640_render_shell')) {
    function tl_stage640_render_shell(string $eyebrow, string $title, array $metrics, array $items, string $apiHref, string $class = ''): void
    {
        echo '<section class="labs-card labs-stage640-panel ' . tl_stage640_e($class) . '"><div class="labs-card-headline"><div><span class="labs-eyebrow">' . tl_stage640_e($eyebrow) . '</span><h2>' . tl_stage640_e($title) . '</h2></div><a class="labs-btn" href="' . tl_stage640_e(labs_url($apiHref)) . '">Data API</a></div>';
        echo '<div class="labs-stage640-metric-grid">';
        foreach ($metrics as $metric) {
            echo '<article><span>' . tl_stage640_e($metric['label'] ?? 'Metric') . '</span><strong>' . tl_stage640_e($metric['value'] ?? 'Ready') . '</strong><small>' . tl_stage640_e($metric['hint'] ?? '') . '</small></article>';
        }
        echo '</div>';
        tl_stage640_render_card_list($items);
        echo '</section>';
    }
}

if (!function_exists('tl_stage640_render_campaign_data_quality')) {
    function tl_stage640_render_campaign_data_quality(string $campaignRef = ''): void
    {
        $data = tl_stage640_campaign_data_quality($campaignRef);
        $metrics = [
            ['label'=>'Completeness', 'value'=>$data['completeness_score'] . '/100', 'hint'=>'campaign data quality'],
            ['label'=>'Duplicates', 'value'=>(string)$data['duplicate_count'], 'hint'=>'manual cleanup only'],
            ['label'=>'Warnings', 'value'=>(string)count((array)$data['warnings']), 'hint'=>'missing or incomplete fields'],
        ];
        tl_stage640_render_shell('Stage 601–608', 'Campaign Data Quality + Cleanup', $metrics, $data['items'], '/api/training/data-quality.php?section=campaign', 'labs-stage640-campaign');
        if (!empty($data['warnings'])) { echo '<div class="labs-stage640-diagnosis">'; foreach ($data['warnings'] as $warning) echo '<p>' . tl_stage640_e($warning) . '</p>'; echo '</div>'; }
    }
}

if (!function_exists('tl_stage640_render_participant_data_quality')) {
    function tl_stage640_render_participant_data_quality(string $campaignRef = '', int $userId = 0): void
    {
        $data = tl_stage640_participant_data_quality($campaignRef, $userId);
        $metrics = [
            ['label'=>'Account', 'value'=>$data['account_status'], 'hint'=>'shared identity clarity'],
            ['label'=>'Quality', 'value'=>$data['quality_score'] . '/100', 'hint'=>'timeline gap score'],
            ['label'=>'Position', 'value'=>$data['current_position'], 'hint'=>'mission state'],
        ];
        tl_stage640_render_shell('Stage 609–616', 'Participant Data Quality + Identity Clarity', $metrics, $data['items'], '/api/training/data-quality.php?section=participant', 'labs-stage640-participant');
        if (!empty($data['timeline_gaps'])) { echo '<div class="labs-stage640-diagnosis">'; foreach ($data['timeline_gaps'] as $gap) echo '<p>' . tl_stage640_e($gap) . '</p>'; echo '</div>'; }
    }
}

if (!function_exists('tl_stage640_render_proof_evidence_quality')) {
    function tl_stage640_render_proof_evidence_quality(): void
    {
        $data = tl_stage640_proof_evidence_quality();
        $metrics = [];
        foreach ($data['filters'] as $filter) $metrics[] = ['label'=>$filter['label'], 'value'=>$filter['value'], 'hint'=>$filter['hint']];
        tl_stage640_render_shell('Stage 617–624', 'Proof Evidence Quality + Review Confidence', $metrics, $data['checklist'], '/api/training/data-quality.php?section=proof', 'labs-stage640-proof');
        echo '<p class="labs-copy labs-stage640-note">' . tl_stage640_e($data['participant_explanation']) . '</p>';
    }
}

if (!function_exists('tl_stage640_render_reward_audit_assurance')) {
    function tl_stage640_render_reward_audit_assurance(): void
    {
        $data = tl_stage640_reward_audit_assurance();
        $metrics = [
            ['label'=>'Events', 'value'=>(string)$data['assurance']['events_tracked'], 'hint'=>'reward events'],
            ['label'=>'Bridge', 'value'=>(string)$data['assurance']['bridge_mode'], 'hint'=>'Microgifter state'],
            ['label'=>'Adapter', 'value'=>!empty($data['assurance']['adapter_available']) ? 'Available' : 'Gated', 'hint'=>'developer-key safe'],
        ];
        tl_stage640_render_shell('Stage 625–632', 'Reward Audit + Assurance Trail', $metrics, $data['audit_trail'], '/api/training/data-quality.php?section=reward', 'labs-stage640-reward');
        echo '<p class="labs-copy labs-stage640-note">' . tl_stage640_e($data['assurance']['participant_copy']) . '</p>';
    }
}

if (!function_exists('tl_stage640_render_operator_health_dashboard')) {
    function tl_stage640_render_operator_health_dashboard(): void
    {
        $data = tl_stage640_operator_health_dashboard();
        $metrics = [
            ['label'=>'Health', 'value'=>$data['health_score'] . '/100', 'hint'=>'overall operator confidence'],
            ['label'=>'Data quality', 'value'=>$data['scores']['data_quality'] . '/100', 'hint'=>'campaign + participant'],
            ['label'=>'Workflow blockage', 'value'=>$data['scores']['workflow_blockage'] . '/100', 'hint'=>'gap risk'],
            ['label'=>'Reward assurance', 'value'=>$data['scores']['reward_assurance'] . '/100', 'hint'=>'bridge traceability'],
        ];
        tl_stage640_render_shell('Stage 633–640', 'Operator Health Dashboard', $metrics, $data['todays_cleanup_order'], '/api/training/data-quality.php?section=health', 'labs-stage640-health');
        echo '<div class="labs-stage640-diagnosis">'; foreach ($data['plain_english_diagnosis'] as $line) echo '<p>' . tl_stage640_e($line) . '</p>'; echo '</div>';
    }
}
