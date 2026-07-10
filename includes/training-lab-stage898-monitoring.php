<?php
declare(strict_types=1);

/** Stage 898 health scoring and protected operator rendering. */
require_once __DIR__ . '/training-lab-stage898-runner.php';

if (!function_exists('tl_stage898_health_metrics')) {
    function tl_stage898_health_metrics(array $runs): array
    {
        $metrics = ['window'=>count($runs),'verified'=>0,'idle'=>0,'paused'=>0,'failed'=>0,'skipped'=>0,'attempted'=>0,'success_rate'=>null,'consecutive_non_success'=>0];
        foreach ($runs as $run) {
            $eventType = (string)($run['event_type'] ?? '');
            if ($eventType === 'stage898_worker_canary_completed') { $metrics['verified']++; $metrics['attempted']++; }
            elseif ($eventType === 'stage898_worker_canary_idle') $metrics['idle']++;
            elseif ($eventType === 'stage898_worker_canary_paused') { $metrics['paused']++; $metrics['attempted']++; }
            elseif ($eventType === 'stage898_worker_canary_failed') { $metrics['failed']++; $metrics['attempted']++; }
            elseif ($eventType === 'stage898_worker_canary_skipped') $metrics['skipped']++;
        }
        if ($metrics['attempted'] > 0) $metrics['success_rate'] = (int)round(($metrics['verified'] / $metrics['attempted']) * 100);
        foreach ($runs as $run) {
            $eventType = (string)($run['event_type'] ?? '');
            if ($eventType === 'stage898_worker_canary_completed' || $eventType === 'stage898_worker_canary_idle') break;
            if ($eventType === 'stage898_worker_canary_paused' || $eventType === 'stage898_worker_canary_failed') $metrics['consecutive_non_success']++;
        }
        return $metrics;
    }
}

if (!function_exists('tl_stage898_summary')) {
    function tl_stage898_summary(): array
    {
        $readiness = tl_stage898_readiness();
        $config = tl_stage898_config();
        $pdo = function_exists('tl_db') ? tl_db() : null;
        $runs = $pdo instanceof PDO ? tl_stage898_recent_runs($pdo, (int)$config['health_window']) : [];
        $metrics = tl_stage898_health_metrics($runs);
        $queue = $pdo instanceof PDO ? tl_stage898_queue_metrics($pdo) : [];
        $last = $runs[0] ?? null;
        $lastAt = $last ? strtotime((string)($last['created_at'] ?? '')) : false;
        $lastAge = $lastAt ? max(0, time() - $lastAt) : null;
        $stale = !empty($config['enabled']) && $lastAge !== null && $lastAge > (int)$config['stale_after_seconds'];
        $alerts = [
            'pause_latched'=>!empty($readiness['pause']['paused']),
            'canary_stale'=>$stale,
            'normal_worker_enabled'=>empty($readiness['normal_worker_disabled']),
            'active_stage896_pilot'=>empty($readiness['checks']['no_active_stage896_pilot']),
            'quarantined_handoffs'=>(int)($queue['quarantined'] ?? 0) > 0,
            'consecutive_non_success'=>(int)$metrics['consecutive_non_success'] > 0,
        ];
        $health = empty($config['enabled'])
            ? 'disabled'
            : (!empty($alerts['pause_latched']) ? 'paused'
                : (count(array_filter($alerts)) > 0 ? 'degraded'
                    : (!empty($readiness['ready_to_run']) ? 'ready' : 'blocked')));
        return $readiness + [
            'health'=>$health,
            'alerts'=>$alerts,
            'metrics'=>$metrics,
            'queue'=>$queue,
            'last_run'=>$last,
            'last_run_age_seconds'=>$lastAge,
            'recent_runs'=>$runs,
            'commands'=>[
                'observe'=>'php /path/to/training-lab/bin/reward-worker-canary.php --observe',
                'run'=>'php /path/to/training-lab/bin/reward-worker-canary.php --run',
            ],
            'safe_boundaries'=>[
                'cli_execution_only'=>true,
                'one_eligible_handoff_maximum'=>true,
                'reuses_stage896_single_item_controller'=>true,
                'requires_clean_stage897_batch'=>true,
                'immediate_signed_readback'=>true,
                'automatic_pause_on_uncertainty'=>true,
                'normal_stage892_worker_remains_disabled'=>true,
                'no_http_execution_route'=>true,
                'no_microgifter_change'=>true,
                'no_new_sql'=>true,
            ],
        ];
    }
}

if (!function_exists('tl_stage898_parse_cli_arguments')) {
    function tl_stage898_parse_cli_arguments(array $argv): array
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

if (!function_exists('tl_stage898_render_admin_page')) {
    function tl_stage898_render_admin_page(?array $result = null, string $error = ''): void
    {
        if (!function_exists('labs_e') || !function_exists('labs_url')) return;
        $summary = tl_stage898_summary();
        $last = is_array($summary['last_run'] ?? null) ? (array)$summary['last_run'] : [];
        $lastRun = is_array($last['run'] ?? null) ? (array)$last['run'] : [];
        echo '<section class="labs-page-title"><div><span class="labs-eyebrow">Stage 898</span><h1>Scheduled Worker Canary & Monitoring</h1><p class="labs-copy">Run one automatically selected low-value reward through the proven Stage 896 path, verify it immediately, and pause all later canaries on any uncertainty.</p></div><a class="labs-btn" href="' . labs_e(labs_url('/api/training/reward-worker-canary.php')) . '">Canary JSON</a></section>';
        echo '<section class="labs-kpis">';
        echo '<div class="labs-kpi"><span>Health</span><strong>' . labs_e(ucfirst((string)$summary['health'])) . '</strong><small>' . (int)$summary['score'] . '% readiness</small></div>';
        echo '<div class="labs-kpi"><span>Last run</span><strong>' . labs_e((string)($lastRun['status'] ?? 'Not run')) . '</strong><small>' . labs_e((string)($last['created_at'] ?? '—')) . '</small></div>';
        echo '<div class="labs-kpi"><span>Due queue</span><strong>' . (int)($summary['queue']['due'] ?? 0) . '</strong><small>eligible timing</small></div>';
        echo '<div class="labs-kpi"><span>Normal worker</span><strong>' . (!empty($summary['normal_worker_disabled']) ? 'Disabled' : 'STOP') . '</strong><small>must stay off</small></div>';
        echo '</section>';
        if ($error !== '') echo '<section class="labs-card labs-error-card"><h2>Canary needs attention</h2><p class="labs-copy">' . labs_e($error) . '</p></section>';
        if ($result) echo '<section class="labs-card"><h2>Operator action complete</h2><pre>' . labs_e(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre></section>';
        if (!empty($summary['pause']['paused'])) {
            echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Paused</span><h2>Operator review required</h2><p class="labs-copy">Reason: ' . labs_e((string)$summary['pause']['reason']) . '. Resolve any active Stage 896 pilot before acknowledging the pause.</p></div></div>';
            echo '<form method="post" class="labs-stage30-form">' . (function_exists('tl_security_csrf_field') ? tl_security_csrf_field() : '') . '<input type="hidden" name="training_action" value="stage898_acknowledge_canary_pause"><label>Type ACKNOWLEDGE CANARY PAUSE<input type="text" name="confirmation_phrase" autocomplete="off" required></label><button class="labs-btn labs-btn-primary" type="submit">Acknowledge Pause</button></form></section>';
        }
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Cron command</span><h2>CLI-only canary</h2><p class="labs-copy">The web interface cannot issue rewards. Configure cron only after Stage 897 has a clean completed batch and all readiness checks are green.</p></div></div><div class="labs-stage25-code"><strong>Observe</strong><pre>' . labs_e((string)$summary['commands']['observe']) . '</pre><strong>Run one canary</strong><pre>' . labs_e((string)$summary['commands']['run']) . '</pre></div></section>';
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Monitoring</span><h2>Recent canary health</h2></div></div><div class="labs-kpis"><div class="labs-kpi"><span>Verified</span><strong>' . (int)$summary['metrics']['verified'] . '</strong></div><div class="labs-kpi"><span>Idle</span><strong>' . (int)$summary['metrics']['idle'] . '</strong></div><div class="labs-kpi"><span>Paused</span><strong>' . (int)$summary['metrics']['paused'] . '</strong></div><div class="labs-kpi"><span>Success</span><strong>' . ($summary['metrics']['success_rate'] === null ? '—' : (int)$summary['metrics']['success_rate'] . '%') . '</strong></div></div></section>';
        echo '<section class="labs-safe-note">Stage 898 runs one reward maximum, reuses Stage 896, requires fresh Stage 897 evidence, and automatically latches a pause on any non-verified result. The normal Stage 892 worker remains disabled.</section>';
    }
}

if (!function_exists('tl_stage898_render_reward_bridge_panel')) {
    function tl_stage898_render_reward_bridge_panel(): void
    {
        if (!function_exists('labs_e') || !function_exists('labs_url')) return;
        $summary = tl_stage898_summary();
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 898</span><h2>Scheduled Worker Canary</h2><p class="labs-copy">One CLI-only automated reward, immediate read-back, health monitoring, and an automatic pause latch.</p></div><a class="labs-btn labs-btn-primary" href="' . labs_e(labs_url('/admin/reward-worker-canary.php')) . '">Open Canary Monitor</a></div>';
        echo '<div class="labs-kpis"><div class="labs-kpi"><span>Health</span><strong>' . labs_e(ucfirst((string)$summary['health'])) . '</strong></div><div class="labs-kpi"><span>Readiness</span><strong>' . (int)$summary['score'] . '%</strong></div><div class="labs-kpi"><span>Due</span><strong>' . (int)($summary['queue']['due'] ?? 0) . '</strong></div><div class="labs-kpi"><span>Pause</span><strong>' . (!empty($summary['pause']['paused']) ? 'Latched' : 'Clear') . '</strong></div></div></section>';
    }
}
