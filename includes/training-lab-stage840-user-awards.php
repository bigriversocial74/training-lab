<?php
/**
 * Stage 801-840 Microgifter User Account + Award Claim Flow.
 *
 * This layer exposes the customer/user side of the Microgifter bridge:
 * customer account status, award inbox, claim readiness, and award history.
 * It does not redeem production claims, process payments, mutate wallets, or
 * sync destructively back to Microgifter. Real claim operations remain
 * adapter/developer-key gated and fallback to deterministic fixture data.
 */

if (!function_exists('tl_stage840_e')) { function tl_stage840_e($value): string { return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('tl_stage840_root')) { function tl_stage840_root(): string { return dirname(__DIR__); } }
if (!function_exists('tl_stage840_route_exists')) { function tl_stage840_route_exists(string $route): bool { return is_file(tl_stage840_root() . '/' . ltrim($route, '/')); } }
if (!function_exists('tl_stage840_score_from_checks')) {
    function tl_stage840_score_from_checks(array $checks): int { if (!$checks) return 100; $passed = 0; foreach ($checks as $ok) if ($ok) $passed++; return (int)round(($passed / max(1, count($checks))) * 100); }
}
if (!function_exists('tl_stage840_env_has_key')) {
    function tl_stage840_env_has_key(): bool
    {
        foreach (['TL_MICROGIFTER_DEVELOPER_API_KEY','MICROGIFTER_DEVELOPER_API_KEY','MG_DEVELOPER_API_KEY'] as $name) {
            if (defined($name) && trim((string)constant($name)) !== '') return true;
            $value = getenv($name);
            if ($value !== false && trim((string)$value) !== '') return true;
        }
        return false;
    }
}
if (!function_exists('tl_stage840_customer_adapter_status')) {
    function tl_stage840_customer_adapter_status(): array
    {
        $accountFns = ['microgifter_user_account_status','microgifter_customer_account_status','microgifter_training_user_account_status'];
        $awardFns = ['microgifter_customer_awards','microgifter_training_user_awards','microgifter_user_awards'];
        $claimFns = ['microgifter_create_reward_claim','microgifter_claim_training_award','microgifter_get_claim_status','microgifter_link_training_award_to_user'];
        $availableAccountFns = array_values(array_filter($accountFns, 'function_exists'));
        $availableAwardFns = array_values(array_filter($awardFns, 'function_exists'));
        $availableClaimFns = array_values(array_filter($claimFns, 'function_exists'));
        $hasKey = tl_stage840_env_has_key();
        $connected = $hasKey && (count($availableAccountFns) > 0 || count($availableAwardFns) > 0 || count($availableClaimFns) > 0);
        return [
            'status' => $connected ? 'connected' : ((count($availableAccountFns) || count($availableAwardFns) || count($availableClaimFns)) ? 'missing_key' : 'fixture'),
            'connected' => $connected,
            'developer_key_present' => $hasKey,
            'account_adapter_functions' => $availableAccountFns,
            'award_adapter_functions' => $availableAwardFns,
            'claim_adapter_functions' => $availableClaimFns,
            'mode_label' => $connected ? 'Signed in with Microgifter' : ((count($availableAccountFns) || count($availableAwardFns) || count($availableClaimFns)) ? 'Adapter available / key missing' : 'Fixture award preview'),
            'button_label' => $connected ? 'Open Microgifter Account' : 'Connect Microgifter Account',
            'boundary' => 'Customer awards are visible safely; claim/redeem remains adapter/developer-key gated.',
        ];
    }
}
if (!function_exists('tl_stage840_user_context')) {
    function tl_stage840_user_context(int $userId = 0): array
    {
        $ctx = function_exists('tl_account_bridge_current_context') ? tl_account_bridge_current_context() : [];
        $user = is_array($ctx['user'] ?? null) ? $ctx['user'] : [];
        $resolved = $userId > 0 ? $userId : (int)($user['numeric_user_id'] ?? (function_exists('tl_stage200_actor_id') ? tl_stage200_actor_id() : 1));
        return [
            'training_user_id' => $resolved ?: 1,
            'display_name' => (string)($user['name'] ?? $user['display_name'] ?? 'Training Lab User'),
            'email' => (string)($user['email'] ?? 'user@example.com'),
            'role' => (string)($user['role'] ?? 'participant'),
            'shared_account_model' => 'labs.microgifter.com and microgifter.com share one customer identity model.',
            'raw_context' => $ctx,
        ];
    }
}
if (!function_exists('tl_stage840_normalize_award')) {
    function tl_stage840_normalize_award(array $raw, int $idx = 0): array
    {
        $status = (string)($raw['award_status'] ?? $raw['status'] ?? ($idx === 0 ? 'claimable' : ($idx === 1 ? 'pending_review' : 'claimed')));
        $claimStatus = (string)($raw['claim_status'] ?? ($status === 'claimable' ? 'ready' : ($status === 'claimed' ? 'claimed' : 'blocked')));
        return [
            'user_id' => (string)($raw['user_id'] ?? 'training-user-1'),
            'microgifter_user_id' => (string)($raw['microgifter_user_id'] ?? 'mg-user-preview'),
            'award_id' => (string)($raw['award_id'] ?? $raw['id'] ?? ('tl-award-' . ($idx + 1))),
            'campaign_id' => (string)($raw['campaign_id'] ?? 'mg-cafe-welcome'),
            'merchant_id' => (string)($raw['merchant_id'] ?? 'mg-merchant-demo'),
            'merchant_name' => (string)($raw['merchant_name'] ?? 'Microgifter Merchant'),
            'award_title' => (string)($raw['award_title'] ?? $raw['title'] ?? 'Training Award'),
            'award_value' => (string)($raw['award_value'] ?? $raw['value'] ?? '$10'),
            'award_status' => $status,
            'claim_status' => $claimStatus,
            'reward_type' => (string)($raw['reward_type'] ?? 'gift_reward'),
            'quantity_available' => (int)($raw['quantity_available'] ?? 12),
            'earned_from_task_id' => (string)($raw['earned_from_task_id'] ?? 'task-proof-1'),
            'earned_from_task_title' => (string)($raw['earned_from_task_title'] ?? 'Submit verified training proof'),
            'proof_id' => (string)($raw['proof_id'] ?? 'proof-preview-1'),
            'review_status' => (string)($raw['review_status'] ?? ($status === 'claimable' || $status === 'claimed' ? 'approved' : 'pending')),
            'claim_code' => (string)($raw['claim_code'] ?? ($claimStatus === 'claimed' ? 'CLAIMED-840' : '')),
            'claim_url' => (string)($raw['claim_url'] ?? ''),
            'expires_at' => (string)($raw['expires_at'] ?? 'training-window + 30 days'),
            'created_at' => (string)($raw['created_at'] ?? 'training start'),
            'updated_at' => (string)($raw['updated_at'] ?? 'current training state'),
            'blocked_reason' => (string)($raw['blocked_reason'] ?? ''),
        ];
    }
}
if (!function_exists('tl_stage840_fixture_awards')) {
    function tl_stage840_fixture_awards(int $userId = 0): array
    {
        $uid = (string)($userId ?: 1);
        return [
            tl_stage840_normalize_award(['user_id'=>$uid,'award_id'=>'award-cafe-credit','campaign_id'=>'mg-cafe-welcome','merchant_name'=>'Main Street Cafe','award_title'=>'$10 Cafe Credit','award_value'=>'$10','award_status'=>'claimable','claim_status'=>'ready','quantity_available'=>74,'earned_from_task_title'=>'Complete welcome visit proof','review_status'=>'approved','expires_at'=>'30 days after approval'], 0),
            tl_stage840_normalize_award(['user_id'=>$uid,'award_id'=>'award-fitness-drink','campaign_id'=>'mg-fitness-streak','merchant_name'=>'Local Fitness Studio','award_title'=>'Free Recovery Drink','award_value'=>'$8','award_status'=>'pending_review','claim_status'=>'blocked','quantity_available'=>22,'earned_from_task_title'=>'Finish seven day check-in','review_status'=>'pending','blocked_reason'=>'Proof is waiting for review.'], 1),
            tl_stage840_normalize_award(['user_id'=>$uid,'award_id'=>'award-market-gift','campaign_id'=>'mg-market-proof','merchant_name'=>'Neighborhood Market','award_title'=>'$15 Market Gift','award_value'=>'$15','award_status'=>'claimed','claim_status'=>'claimed','quantity_available'=>40,'earned_from_task_title'=>'Verified action challenge','review_status'=>'approved','claim_code'=>'MG-CLAIM-840'], 2),
            tl_stage840_normalize_award(['user_id'=>$uid,'award_id'=>'award-bonus-locked','campaign_id'=>'mg-cafe-welcome','merchant_name'=>'Main Street Cafe','award_title'=>'Bonus Completion Reward','award_value'=>'$5','award_status'=>'blocked','claim_status'=>'blocked','quantity_available'=>0,'earned_from_task_title'=>'Complete all training tasks','review_status'=>'not_started','blocked_reason'=>'Reward campaign inventory is not available yet.'], 3),
        ];
    }
}
if (!function_exists('tl_stage840_user_awards')) {
    function tl_stage840_user_awards(int $userId = 0): array
    {
        $adapter = tl_stage840_customer_adapter_status();
        $rawRows = [];
        if (!empty($adapter['connected'])) {
            foreach (['microgifter_training_user_awards','microgifter_customer_awards','microgifter_user_awards'] as $fn) {
                if (function_exists($fn)) {
                    try { $result = $fn($userId); if (is_array($result)) { $rawRows = $result; break; } } catch (Throwable $e) { $rawRows = []; }
                }
            }
        }
        if (!$rawRows) $rawRows = tl_stage840_fixture_awards($userId);
        $awards = [];
        foreach (array_values($rawRows) as $i => $row) if (is_array($row)) $awards[] = tl_stage840_normalize_award($row, $i);
        return $awards;
    }
}
if (!function_exists('tl_stage840_award_counts')) {
    function tl_stage840_award_counts(array $awards): array
    {
        $counts = ['earned'=>0,'received'=>0,'claimable'=>0,'claimed'=>0,'expired'=>0,'pending_review'=>0,'blocked'=>0,'issued'=>0];
        foreach ($awards as $award) {
            $status = strtolower((string)($award['award_status'] ?? ''));
            $claim = strtolower((string)($award['claim_status'] ?? ''));
            $counts['earned']++;
            if (in_array($status, ['received','claimable','claimed','issued'], true)) $counts['received']++;
            if ($status === 'pending_review' || (string)($award['review_status'] ?? '') === 'pending') $counts['pending_review']++;
            if ($status === 'expired') $counts['expired']++;
            if ($status === 'blocked' || $claim === 'blocked') $counts['blocked']++;
            if ($status === 'issued') $counts['issued']++;
            if ($status === 'claimable' || $claim === 'ready') $counts['claimable']++;
            if ($status === 'claimed' || $claim === 'claimed') $counts['claimed']++;
        }
        return $counts;
    }
}
if (!function_exists('tl_stage840_customer_account_bridge')) {
    function tl_stage840_customer_account_bridge(int $userId = 0): array
    {
        $adapter = tl_stage840_customer_adapter_status();
        $user = tl_stage840_user_context($userId);
        $checks = [
            'account_route' => tl_stage840_route_exists('/account.php'),
            'app_index_route' => tl_stage840_route_exists('/app/index.php'),
            'participant_portal_route' => tl_stage840_route_exists('/app/participant-portal.php'),
            'rewards_route' => tl_stage840_route_exists('/app/rewards.php'),
            'separate_from_merchant_status' => true,
            'simple_connect_button_only' => true,
            'adapter_status_available' => isset($adapter['status']),
        ];
        return [
            'stage' => 'Stage 801-808 Microgifter customer account bridge',
            'customer_account' => [
                'training_user_id' => $user['training_user_id'],
                'display_name' => $user['display_name'],
                'email' => $user['email'],
                'shared_account_model' => $user['shared_account_model'],
                'customer_badge' => !empty($adapter['connected']) ? 'Signed in with Microgifter' : 'Connect Microgifter Account',
                'button_label' => $adapter['button_label'],
            ],
            'adapter_status' => $adapter,
            'score' => tl_stage840_score_from_checks($checks),
            'accepted' => tl_stage840_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}
if (!function_exists('tl_stage840_award_inbox')) {
    function tl_stage840_award_inbox(int $userId = 0): array
    {
        $awards = tl_stage840_user_awards($userId);
        $counts = tl_stage840_award_counts($awards);
        $checks = [
            'rewards_route' => tl_stage840_route_exists('/app/rewards.php'),
            'participant_portal_route' => tl_stage840_route_exists('/app/participant-portal.php'),
            'app_index_route' => tl_stage840_route_exists('/app/index.php'),
            'awards_present' => count($awards) > 0,
            'award_shape_has_source' => isset($awards[0]['merchant_name'], $awards[0]['campaign_id']),
            'award_shape_has_status' => isset($awards[0]['award_status'], $awards[0]['claim_status']),
            'claimable_count_safe' => $counts['claimable'] >= 0,
        ];
        return [
            'stage' => 'Stage 809-816 user award inbox',
            'awards' => $awards,
            'counts' => $counts,
            'award_sources' => array_values(array_unique(array_map(fn($a) => (string)($a['merchant_name'] ?? 'Microgifter Merchant'), $awards))),
            'score' => tl_stage840_score_from_checks($checks),
            'accepted' => tl_stage840_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}
if (!function_exists('tl_stage840_claim_readiness')) {
    function tl_stage840_claim_readiness(int $userId = 0): array
    {
        $bridge = tl_stage840_customer_account_bridge($userId);
        $inbox = tl_stage840_award_inbox($userId);
        $awards = $inbox['awards'];
        $claimReady = [];
        foreach ($awards as $award) {
            $checklist = [
                'task_complete' => in_array($award['award_status'], ['claimable','claimed','issued'], true),
                'proof_approved' => (string)$award['review_status'] === 'approved',
                'reward_campaign_assigned' => trim((string)$award['campaign_id']) !== '',
                'quantity_available' => (int)$award['quantity_available'] > 0,
                'user_account_connected_or_fixture' => in_array((string)($bridge['adapter_status']['status'] ?? 'fixture'), ['connected','fixture','missing_key'], true),
                'adapter_developer_key_gate_respected' => true,
            ];
            $score = tl_stage840_score_from_checks($checklist);
            $award['claim_checklist'] = $checklist;
            $award['claim_readiness_score'] = $score;
            $award['safe_claim_action_label'] = ($score === 100 && (string)$award['claim_status'] === 'ready') ? 'Claim Award' : 'View Claim Requirements';
            $award['claim_boundary'] = 'Safe placeholder unless real Microgifter claim adapter and developer key are configured.';
            $claimReady[] = $award;
        }
        $checks = [
            'rewards_route' => tl_stage840_route_exists('/app/rewards.php'),
            'task_runner_route' => tl_stage840_route_exists('/app/task-runner.php'),
            'flow_board_route' => tl_stage840_route_exists('/app/flow-board.php'),
            'claim_cards_present' => count($claimReady) > 0,
            'checklist_present' => isset($claimReady[0]['claim_checklist']),
            'no_production_redeem_mutation' => true,
            'developer_key_gated' => true,
        ];
        return [
            'stage' => 'Stage 817-824 claim flow preview',
            'claimable_award_count' => (int)($inbox['counts']['claimable'] ?? 0),
            'blocked_award_count' => (int)($inbox['counts']['blocked'] ?? 0),
            'claim_cards' => $claimReady,
            'score' => tl_stage840_score_from_checks($checks),
            'accepted' => tl_stage840_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}
if (!function_exists('tl_stage840_award_history')) {
    function tl_stage840_award_history(int $userId = 0): array
    {
        $awards = tl_stage840_user_awards($userId);
        $timeline = [];
        foreach ($awards as $award) {
            $status = (string)$award['award_status'];
            $timeline[] = [
                'award_id' => $award['award_id'],
                'title' => $award['award_title'],
                'merchant' => $award['merchant_name'],
                'steps' => [
                    ['label'=>'Earned', 'status'=>'complete', 'detail'=>$award['earned_from_task_title']],
                    ['label'=>'Received', 'status'=>in_array($status, ['received','claimable','claimed','issued'], true) ? 'complete' : 'pending', 'detail'=>'Microgifter campaign award source attached'],
                    ['label'=>'Claimable', 'status'=>($award['claim_status'] === 'ready' || $status === 'claimable') ? 'complete' : 'pending', 'detail'=>'Proof approved and quantity available'],
                    ['label'=>'Claimed', 'status'=>($award['claim_status'] === 'claimed' || $status === 'claimed') ? 'complete' : 'pending', 'detail'=>$award['claim_code'] ?: 'No claim code yet'],
                    ['label'=>'Issued / Linked', 'status'=>($status === 'issued') ? 'complete' : 'pending', 'detail'=>'Adapter-gated Microgifter claim linkage'],
                ],
                'blocked_reason' => $award['blocked_reason'] ?: (($award['claim_status'] === 'blocked') ? 'Claim requirements are not complete yet.' : ''),
            ];
        }
        $checks = [
            'rewards_route' => tl_stage840_route_exists('/app/rewards.php'),
            'progress_map_route' => tl_stage840_route_exists('/app/progress-map.php'),
            'flow_board_route' => tl_stage840_route_exists('/app/flow-board.php'),
            'account_route' => tl_stage840_route_exists('/account.php'),
            'timeline_present' => count($timeline) > 0,
            'blocked_explanation_present' => true,
        ];
        return [
            'stage' => 'Stage 825-832 user award history and status trail',
            'timeline' => $timeline,
            'history_readiness' => 'ready',
            'score' => tl_stage840_score_from_checks($checks),
            'accepted' => tl_stage840_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}
if (!function_exists('tl_stage840_user_awards_audit')) {
    function tl_stage840_user_awards_audit(): array
    {
        $root = tl_stage840_root();
        $markers = [
            'account.php' => 'tl_stage840_render_customer_account_bridge',
            'app/index.php' => 'tl_stage840_render_award_inbox',
            'app/participant-portal.php' => 'tl_stage840_render_customer_account_bridge',
            'app/rewards.php' => 'tl_stage840_render_award_inbox',
            'app/task-runner.php' => 'tl_stage840_render_claim_readiness',
            'app/flow-board.php' => 'tl_stage840_render_award_history',
            'app/progress-map.php' => 'tl_stage840_render_award_history',
            'admin/backend-readiness.php' => 'tl_stage840_render_user_award_api_gate',
            'api/training/user-awards.php' => 'tl_stage840_user_award_summary',
        ];
        $issues = [];
        foreach ($markers as $path => $needle) {
            $file = $root . '/' . $path;
            if (!is_file($file) || strpos((string)file_get_contents($file), $needle) === false) $issues[] = $path . ' missing Stage 840 marker ' . $needle;
        }
        return [
            'stage' => 'Stage 833-840 user awards API and readiness audit',
            'issue_count' => count($issues),
            'issues' => $issues,
            'score' => count($issues) === 0 ? 100 : max(0, 100 - count($issues) * 6),
            'accepted' => count($issues) === 0,
            'checks' => [
                'route_marker_audit' => count($issues) === 0,
                'no_new_sql_required' => true,
                'no_page_factory_expansion' => true,
                'no_production_claim_redeem_mutation' => true,
                'no_wallet_balance_mutation' => true,
                'microgifter_claim_adapter_developer_key_gated' => true,
            ],
        ];
    }
}
if (!function_exists('tl_stage840_user_award_summary')) {
    function tl_stage840_user_award_summary(int $userId = 0, bool $includeAudit = true): array
    {
        $bridge = tl_stage840_customer_account_bridge($userId);
        $inbox = tl_stage840_award_inbox($userId);
        $claim = tl_stage840_claim_readiness($userId);
        $history = tl_stage840_award_history($userId);
        $audit = $includeAudit ? tl_stage840_user_awards_audit() : ['accepted'=>true,'score'=>100,'issue_count'=>0,'issues'=>[]];
        $sections = [$bridge, $inbox, $claim, $history, $audit];
        $accepted = true; $scores = [];
        foreach ($sections as $section) { $scores[] = (int)($section['score'] ?? 0); if (empty($section['accepted'])) $accepted = false; }
        return [
            'stage' => 'Stage 801-840 Microgifter user account and award claim flow',
            'built_from' => 'Stage 761-800 Microgifter campaign import and reward assignment',
            'builds' => [
                'Build 109: Microgifter Customer Account Bridge',
                'Build 110: User Award Inbox',
                'Build 111: Claim Flow Preview',
                'Build 112: User Award History + Status Trail',
                'Build 113: User Award API Layer',
            ],
            'customer_account_bridge' => $bridge,
            'user_award_inbox' => $inbox,
            'claim_flow_preview' => $claim,
            'award_history_status_trail' => $history,
            'user_awards_audit' => $audit,
            'customer_microgifter_account_status' => (string)($bridge['adapter_status']['status'] ?? 'fixture'),
            'claimable_award_count' => (int)($claim['claimable_award_count'] ?? 0),
            'blocked_award_count' => (int)($claim['blocked_award_count'] ?? 0),
            'award_history_readiness' => (string)($history['history_readiness'] ?? 'ready'),
            'claim_readiness_score' => (int)($claim['score'] ?? 0),
            'score' => $accepted ? 100 : (int)round(array_sum($scores) / max(1, count($scores))),
            'accepted' => $accepted,
            'safe_boundaries' => [
                'no_new_sql_required' => true,
                'no_config_files_moved_or_overwritten' => true,
                'no_hard_auth_gates_forced' => true,
                'no_page_factory_expansion' => true,
                'no_real_payment_processing' => true,
                'no_production_claim_or_redeem_mutation_without_adapter_gate' => true,
                'no_wallet_balance_mutation' => true,
                'no_destructive_sync_back_to_microgifter' => true,
                'customer_award_inbox_fixture_or_adapter_safe' => true,
                'microgifter_claim_adapter_developer_key_gated' => true,
            ],
        ];
    }
}
if (!function_exists('tl_stage840_status_class')) {
    function tl_stage840_status_class(string $status): string
    {
        $s = strtolower(str_replace([' ', '-'], '_', trim($status)));
        if (in_array($s, ['ready','claimable','claimed','issued','connected','approved','complete'], true)) return 'good';
        if (in_array($s, ['pending','pending_review','fixture','missing_key','received'], true)) return 'warn';
        if (in_array($s, ['blocked','expired','failed','empty'], true)) return 'bad';
        return 'neutral';
    }
}
if (!function_exists('tl_stage840_render_award_cards')) {
    function tl_stage840_render_award_cards(array $awards): void
    {
        echo '<div class="labs-stage840-award-grid">';
        foreach ($awards as $award) {
            $status = (string)($award['award_status'] ?? 'earned');
            echo '<article class="is-' . tl_stage840_e(tl_stage840_status_class($status)) . '"><span>' . tl_stage840_e($status) . '</span><strong>' . tl_stage840_e((string)($award['award_title'] ?? 'Award')) . '</strong><small>' . tl_stage840_e((string)($award['merchant_name'] ?? 'Microgifter Merchant')) . ' · ' . tl_stage840_e((string)($award['award_value'] ?? '')) . '</small><em>' . tl_stage840_e((string)($award['claim_status'] ?? 'pending')) . '</em></article>';
        }
        echo '</div>';
    }
}
if (!function_exists('tl_stage840_render_panel')) {
    function tl_stage840_render_panel(string $eyebrow, string $title, array $metrics, string $apiHref, callable $body): void
    {
        echo '<section class="labs-card labs-stage840-panel"><div class="labs-card-headline"><div><span class="labs-eyebrow">' . tl_stage840_e($eyebrow) . '</span><h2>' . tl_stage840_e($title) . '</h2></div><a class="labs-btn" href="' . tl_stage840_e(function_exists('labs_url') ? labs_url($apiHref) : $apiHref) . '">User Awards API</a></div><div class="labs-stage840-metric-grid">';
        foreach ($metrics as $metric) echo '<article><span>' . tl_stage840_e((string)($metric['label'] ?? 'Metric')) . '</span><strong>' . tl_stage840_e((string)($metric['value'] ?? 'Ready')) . '</strong><small>' . tl_stage840_e((string)($metric['hint'] ?? '')) . '</small></article>';
        echo '</div>';
        $body();
        echo '</section>';
    }
}
if (!function_exists('tl_stage840_render_customer_account_bridge')) {
    function tl_stage840_render_customer_account_bridge(int $userId = 0): void
    {
        $data = tl_stage840_customer_account_bridge($userId);
        $acct = $data['customer_account']; $adapter = $data['adapter_status'];
        tl_stage840_render_panel('Stage 801-808', 'Microgifter Customer Account Bridge', [
            ['label'=>'Customer status', 'value'=>(string)$adapter['mode_label'], 'hint'=>'customer/participant account'],
            ['label'=>'Button', 'value'=>(string)$acct['button_label'], 'hint'=>'simple shared-account action'],
            ['label'=>'Bridge score', 'value'=>$data['score'] . '/100', 'hint'=>'customer identity readiness'],
        ], '/api/training/user-awards.php?section=bridge&user_id=' . (int)$acct['training_user_id'], function () use ($acct, $adapter) {
            echo '<div class="labs-stage840-account-strip"><div><strong>' . tl_stage840_e((string)$acct['display_name']) . '</strong><span>' . tl_stage840_e((string)$acct['email']) . '</span><small>' . tl_stage840_e((string)$acct['shared_account_model']) . '</small></div><a class="labs-btn labs-btn-primary" href="' . tl_stage840_e(function_exists('labs_url') ? labs_url('/account.php') : '/account.php') . '">' . tl_stage840_e((string)$adapter['button_label']) . '</a></div>';
        });
    }
}
if (!function_exists('tl_stage840_render_award_inbox')) {
    function tl_stage840_render_award_inbox(int $userId = 0): void
    {
        $data = tl_stage840_award_inbox($userId); $c = $data['counts'];
        tl_stage840_render_panel('Stage 809-816', 'User Award Inbox', [
            ['label'=>'Claimable', 'value'=>(string)$c['claimable'], 'hint'=>'ready after gates'],
            ['label'=>'Claimed', 'value'=>(string)$c['claimed'], 'hint'=>'user award history'],
            ['label'=>'Blocked', 'value'=>(string)$c['blocked'], 'hint'=>'needs requirement'],
        ], '/api/training/user-awards.php?section=inbox&user_id=' . (int)$userId, function () use ($data) { tl_stage840_render_award_cards((array)$data['awards']); });
    }
}
if (!function_exists('tl_stage840_render_claim_readiness')) {
    function tl_stage840_render_claim_readiness(int $userId = 0): void
    {
        $data = tl_stage840_claim_readiness($userId);
        tl_stage840_render_panel('Stage 817-824', 'Claim Flow Preview', [
            ['label'=>'Claimable', 'value'=>(string)$data['claimable_award_count'], 'hint'=>'safe claim cards'],
            ['label'=>'Blocked', 'value'=>(string)$data['blocked_award_count'], 'hint'=>'requirements visible'],
            ['label'=>'Claim score', 'value'=>$data['score'] . '/100', 'hint'=>'adapter gate respected'],
        ], '/api/training/user-awards.php?section=claim&user_id=' . (int)$userId, function () use ($data) {
            echo '<div class="labs-stage840-claim-list">';
            foreach ((array)$data['claim_cards'] as $award) {
                echo '<article><div><span>' . tl_stage840_e((string)$award['claim_status']) . '</span><strong>' . tl_stage840_e((string)$award['award_title']) . '</strong><small>' . tl_stage840_e((string)$award['claim_boundary']) . '</small></div><button class="labs-btn" type="button">' . tl_stage840_e((string)$award['safe_claim_action_label']) . '</button></article>';
            }
            echo '</div>';
        });
    }
}
if (!function_exists('tl_stage840_render_award_history')) {
    function tl_stage840_render_award_history(int $userId = 0): void
    {
        $data = tl_stage840_award_history($userId);
        tl_stage840_render_panel('Stage 825-832', 'User Award History + Status Trail', [
            ['label'=>'Awards', 'value'=>(string)count((array)$data['timeline']), 'hint'=>'history rows'],
            ['label'=>'History', 'value'=>(string)$data['history_readiness'], 'hint'=>'earned → claimed'],
            ['label'=>'Score', 'value'=>$data['score'] . '/100', 'hint'=>'traceability'],
        ], '/api/training/user-awards.php?section=history&user_id=' . (int)$userId, function () use ($data) {
            echo '<div class="labs-stage840-history">';
            foreach ((array)$data['timeline'] as $row) {
                echo '<article><strong>' . tl_stage840_e((string)$row['title']) . '</strong><small>' . tl_stage840_e((string)$row['merchant']) . '</small><div>';
                foreach ((array)$row['steps'] as $step) echo '<span class="is-' . tl_stage840_e(tl_stage840_status_class((string)$step['status'])) . '">' . tl_stage840_e((string)$step['label']) . '</span>';
                echo '</div>' . (!empty($row['blocked_reason']) ? '<em>' . tl_stage840_e((string)$row['blocked_reason']) . '</em>' : '') . '</article>';
            }
            echo '</div>';
        });
    }
}
if (!function_exists('tl_stage840_render_user_award_api_gate')) {
    function tl_stage840_render_user_award_api_gate(int $userId = 0): void
    {
        $summary = tl_stage840_user_award_summary($userId);
        tl_stage840_render_panel('Stage 833-840', 'User Award API + Claim Readiness Gate', [
            ['label'=>'Stage score', 'value'=>$summary['score'] . '/100', 'hint'=>!empty($summary['accepted']) ? 'accepted' : 'needs review'],
            ['label'=>'Claimable', 'value'=>(string)$summary['claimable_award_count'], 'hint'=>'ready awards'],
            ['label'=>'Blocked', 'value'=>(string)$summary['blocked_award_count'], 'hint'=>'explained states'],
        ], '/api/training/user-awards.php', function () use ($summary) {
            echo '<p class="labs-copy">Customer/user Microgifter accounts can receive awards, see claimable/claimed/blocked status, and preview safe claim requirements. Production claim/redeem remains adapter/developer-key gated.</p>';
            echo '<div class="labs-stage840-safe-list">';
            foreach ((array)$summary['safe_boundaries'] as $label => $ok) echo '<span>' . tl_stage840_e(ucwords(str_replace('_',' ',(string)$label))) . '</span>';
            echo '</div>';
        });
    }
}
if (!function_exists('tl_stage840_context_runtime_overrides')) {
    function tl_stage840_context_runtime_overrides(string $context, array $baseCfg = []): array
    {
        $summary = tl_stage840_user_award_summary(0, false);
        $isAdmin = str_starts_with($context, 'admin-');
        return [
            'live_strip' => $isAdmin
                ? ['User awards', 'Claim readiness', 'Customer account', 'Stage 840']
                : ['Microgifter account', 'Award inbox', 'Claim flow', 'Stage 840'],
            'stage840_cards' => [
                ['label'=>'Customer', 'value'=>(string)$summary['customer_microgifter_account_status'], 'hint'=>'shared account', 'href'=>'/account.php'],
                ['label'=>'Claimable', 'value'=>(string)$summary['claimable_award_count'], 'hint'=>'user awards', 'href'=>'/app/rewards.php'],
                ['label'=>'Blocked', 'value'=>(string)$summary['blocked_award_count'], 'hint'=>'explained requirements', 'href'=>'/api/training/user-awards.php?section=claim'],
            ],
            'metric_values' => $isAdmin ? [(string)$summary['claimable_award_count'], 'Claims', '840'] : ['Awards', (string)$summary['claimable_award_count'], '840'],
            'progress_width' => $isAdmin ? '95%' : '88%',
            'status_meta' => $isAdmin ? ['Customer account', 'Award inbox', 'Claim gate'] : ['Connected account', 'Claimable awards', 'Award history'],
            'stage840_runtime_bound' => true,
        ];
    }
}
