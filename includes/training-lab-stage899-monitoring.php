<?php
declare(strict_types=1);

/** Stage 899 health scoring, escalation reporting, CLI parsing, and UI. */
require_once __DIR__ . '/training-lab-stage899-runner.php';

if (!function_exists('tl_stage899_summary')) {
    function tl_stage899_summary(): array
    {
        $readiness = tl_stage899_readiness();
        $config = tl_stage899_config();
        $pdo = function_exists('tl_db') ? tl_db() : null;
        $runs = $pdo instanceof PDO ? tl_stage899_recent_runs($pdo, (int)$config['health_window']) : [];
        $metrics = tl_stage899_run_metrics($runs);
        $queue = $pdo instanceof PDO ? tl_stage898_queue_metrics($pdo) : [];
        $last = null;
        foreach ($runs as $row) {
            if (in_array((string)($row['event_type'] ?? ''), ['stage899_limited_processing_completed','stage899_limited_processing_idle','stage899_limited_processing_suspended'], true)) {
                $last = $row;
                break;
            }
        }
        $lastAt = $last ? (strtotime((string)($last['created_at'] ?? '')) ?: 0) : 0;
        $lastAge = $lastAt > 0 ? max(0, time() - $lastAt) : null;
        $stale = !empty($config['enabled']) && $lastAge !== null && $lastAge > (int)$config['stale_after_seconds'];
        $rollingBelowThreshold = $metrics['success_rate'] !== null
            && (int)$metrics['success_rate'] < (int)$config['min_success_rate_percent'];
        $alerts = [
            'suspension_latched'=>!empty($readiness['suspension']['suspended']),
            'scheduler_stale'=>$stale,
            'canary_graduation_lost'=>empty($readiness['graduation']['graduated']),
            'stage898_canary_enabled'=>empty($readiness['stage898_disabled']),
            'stage897_manual_batch_enabled'=>empty($readiness['stage897_disabled']),
            'normal_worker_enabled'=>empty($readiness['normal_worker_disabled']),
            'active_stage896_pilot'=>empty($readiness['checks']['no_active_stage896_pilot']),
            'quarantined_handoffs'=>(int)($queue['quarantined'] ?? 0) > 0,
            'rolling_success_below_threshold'=>$rollingBelowThreshold,
        ];
        $health = empty($config['enabled'])
            ? 'disabled'
            : (!empty($alerts['suspension_latched'])
                ? 'suspended'
                : (count(array_filter($alerts)) > 0
                    ? 'degraded'
                    : (!empty($readiness['ready_to_run']) ? 'ready' : 'blocked')));
        return $readiness + [
            'health'=>$health,
            'alerts'=>$alerts,
            'metrics'=>$metrics,
            'queue'=>$queue,
            'last_run'=>$last,
            'last_run_age_seconds'=>$lastAge,
            'recent_runs'=>$runs,
            'minimum_success_rate_percent'=>(int)$config['min_success_rate_percent'],
            'health_window'=>(int)$config['health_window'],
            'stale_after_seconds'=>(int)$config['stale_after_seconds'],
            'commands'=>[
                'observe'=>'php /path/to/training-lab/bin/reward-limited-scheduler.php --observe',
                'run'=>'php /path/to/training-lab/bin/reward-limited-scheduler.php --run',
            ],
            'safe_boundaries'=>[
                'cli_execution_only'=>true,
                'maximum_two_items_per_run'=>true,
                'item_and_total_value_ceilings'=>true,
                'reuses_stage896_single_item_controller'=>true,
                'requires_three_or_more_clean_canaries'=>true,
                'stage898_canary_must_be_disabled'=>true,
                'stage897_manual_batch_must_be_disabled'=>true,
                'normal_stage892_worker_remains_disabled'=>true,
                'stop_on_first_non_verified_result'=>true,
                'automatic_suspension_and_escalation'=>true,
                'protected_web_surfaces_cannot_issue'=>true,
                'no_microgifter_change'=>true,
                'no_new_sql'=>true,
            ],
        ];
    }
}

if (!function_exists('tl_stage899_parse_cli_arguments')) {
    function tl_stage899_parse_cli_arguments(array $argv): array
    {
        $mode = 'observe';
        $help = false;
        $errors = [];
        foreach (array_slice($argv, 1) as $argument) {
            if ($argument === '--observe') $mode = 'observe';
            elseif ($argument === '--run') $mode = 'run';
            elseif ($argument === '--help' || $argument === '-h') $help = true;
            else $errors[] = 'Unknown argument: ' . $argument;
        }
        if (in_array('--observe', $argv, true) && in_array('--run', $argv, true)) {
            $errors[] = 'Choose only one of --observe or --run.';
        }
        return ['mode'=>$mode,'help'=>$help,'errors'=>$errors,'explicit_run'=>$mode === 'run' && in_array('--run', $argv, true)];
    }
}

if (!function_exists('tl_stage899_render_admin_page')) {
    function tl_stage899_render_admin_page(?array $result = null, string $error = ''): void
    {
        if (!function_exists('labs_e') || !function_exists('labs_url')) return;
        $summary = tl_stage899_summary();
        $last = is_array($summary['last_run'] ?? null) ? (array)$summary['last_run'] : [];
        $lastRun = is_array($last['run'] ?? null) ? (array)$last['run'] : [];
        echo '<section class="labs-page-title"><div><span class="labs-eyebrow">Stage 899</span><h1>Canary Graduation & Limited Scheduled Processing</h1><p class="labs-copy">Graduate from one-item canaries to a maximum two-item CLI schedule with rolling health thresholds, automatic suspension, and escalation reporting.</p></div><a class="labs-btn" href="' . labs_e(labs_url('/api/training/reward-limited-scheduler.php')) . '">Scheduler JSON</a></section>';
        echo '<section class="labs-kpis">';
        echo '<div class="labs-kpi"><span>Health</span><strong>' . labs_e(ucfirst((string)$summary['health'])) . '</strong><small>' . (int)$summary['score'] . '% readiness</small></div>';
        echo '<div class="labs-kpi"><span>Canary graduation</span><strong>' . (int)($summary['graduation']['verified_count'] ?? 0) . '</strong><small>minimum ' . (int)($summary['graduation']['minimum_verified_required'] ?? 3) . '</small></div>';
        echo '<div class="labs-kpi"><span>Rolling success</span><strong>' . ($summary['metrics']['success_rate'] === null ? '—' : (int)$summary['metrics']['success_rate'] . '%') . '</strong><small>minimum ' . (int)$summary['minimum_success_rate_percent'] . '%</small></div>';
        echo '<div class="labs-kpi"><span>Batch limit</span><strong>' . (int)$summary['max_batch_size'] . '</strong><small>$' . number_format(((int)$summary['max_total_value_cents']) / 100, 2) . ' total ceiling</small></div>';
        echo '</section>';
        if ($error !== '') echo '<section class="labs-card labs-error-card"><h2>Scheduler needs attention</h2><p class="labs-copy">' . labs_e($error) . '</p></section>';
        if ($result) echo '<section class="labs-card"><h2>Operator action complete</h2><pre>' . labs_e(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre></section>';
        if (!empty($summary['suspension']['suspended'])) {
            echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Automatic suspension</span><h2>Scheduled processing is stopped</h2><p class="labs-copy">Reason: ' . labs_e((string)$summary['suspension']['reason']) . '. Resolve any active pilot, quarantine, or canary pause before acknowledging.</p></div></div>';
            echo '<form method="post" class="labs-stage30-form">' . (function_exists('tl_security_csrf_field') ? tl_security_csrf_field() : '') . '<input type="hidden" name="training_action" value="stage899_acknowledge_suspension"><label>Type ACKNOWLEDGE LIMITED PROCESSING SUSPENSION<input type="text" name="confirmation_phrase" autocomplete="off" required></label><button class="labs-btn labs-btn-primary" type="submit">Acknowledge Suspension</button></form></section>';
        }
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">CLI-only schedule</span><h2>Limited scheduler command</h2><p class="labs-copy">The browser cannot issue rewards. Run observe first, then schedule the run command only after all graduation checks pass.</p></div></div><div class="labs-stage25-code"><strong>Observe</strong><pre>' . labs_e((string)$summary['commands']['observe']) . '</pre><strong>Process up to ' . (int)$summary['max_batch_size'] . '</strong><pre>' . labs_e((string)$summary['commands']['run']) . '</pre></div></section>';
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Monitoring</span><h2>Limited scheduler health</h2></div></div><div class="labs-kpis"><div class="labs-kpi"><span>Completed</span><strong>' . (int)$summary['metrics']['completed'] . '</strong></div><div class="labs-kpi"><span>Suspended</span><strong>' . (int)$summary['metrics']['suspended'] . '</strong></div><div class="labs-kpi"><span>Verified items</span><strong>' . (int)$summary['metrics']['verified_items'] . '</strong></div><div class="labs-kpi"><span>Due queue</span><strong>' . (int)($summary['queue']['due'] ?? 0) . '</strong></div></div>';
        echo '<p class="labs-copy">Last run: ' . labs_e((string)($lastRun['status'] ?? 'Not run')) . ' · ' . labs_e((string)($last['created_at'] ?? '—')) . '</p></section>';
        echo '<section class="labs-safe-note">Stage 899 never activates the normal Stage 892 worker. Stage 897 and Stage 898 execution must be disabled. Every reward still uses the Stage 896 signed issue and immediate read-back path.</section>';
    }
}

if (!function_exists('tl_stage899_render_reward_bridge_panel')) {
    function tl_stage899_render_reward_bridge_panel(): void
    {
        if (!function_exists('labs_e') || !function_exists('labs_url')) return;
        $summary = tl_stage899_summary();
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 899</span><h2>Limited Scheduled Processing</h2><p class="labs-copy">Canary graduation, two-item scheduling, rolling health thresholds, and automatic suspension.</p></div><a class="labs-btn labs-btn-primary" href="' . labs_e(labs_url('/admin/reward-limited-scheduler.php')) . '">Open Scheduler Monitor</a></div>';
        echo '<div class="labs-kpis"><div class="labs-kpi"><span>Health</span><strong>' . labs_e(ucfirst((string)$summary['health'])) . '</strong></div><div class="labs-kpi"><span>Readiness</span><strong>' . (int)$summary['score'] . '%</strong></div><div class="labs-kpi"><span>Canaries</span><strong>' . (int)($summary['graduation']['verified_count'] ?? 0) . '</strong></div><div class="labs-kpi"><span>Suspension</span><strong>' . (!empty($summary['suspension']['suspended']) ? 'Latched' : 'Clear') . '</strong></div></div></section>';
    }
}
