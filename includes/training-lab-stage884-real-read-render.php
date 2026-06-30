<?php
/**
 * Stage 884 Real Read Adapter admin rendering helpers.
 */

if (!function_exists('tl_stage884_render_e')) { function tl_stage884_render_e($value): string { return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }

if (!function_exists('tl_stage884_render_real_read_adapter')) {
    function tl_stage884_render_real_read_adapter(int $userId = 0): void
    {
        $summary = function_exists('tl_stage884_real_read_adapter_summary') ? tl_stage884_real_read_adapter_summary($userId) : [];
        $safe = (array)($summary['safe_boundaries'] ?? []);
        echo '<section class="labs-page-title"><div><span class="labs-eyebrow">Stage 884</span><h1>Real Microgifter Read Adapter Connection</h1><p class="labs-copy">Live Training Lab database rows are exposed through Microgifter-style read functions. Mutation remains closed.</p></div><a class="labs-btn labs-btn-primary" href="' . tl_stage884_render_e(function_exists('labs_url') ? labs_url('/api/training/microgifter-adapter-sync.php?section=readonly') : '/api/training/microgifter-adapter-sync.php?section=readonly') . '">View JSON</a></section>';
        echo '<section class="labs-kpis">';
        echo '<div class="labs-kpi"><span class="labs-muted">Accepted</span><strong>' . (!empty($summary['accepted']) ? 'Yes' : 'Check') . '</strong><small>real-read gate</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Source</span><strong>' . tl_stage884_render_e((string)($summary['adapter_source'] ?? 'unknown')) . '</strong><small>adapter source</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Campaigns</span><strong>' . (int)($summary['campaign_count'] ?? 0) . '</strong><small>DB-backed catalog</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Awards</span><strong>' . (int)($summary['award_count'] ?? 0) . '</strong><small>DB-backed awards</small></div>';
        echo '</section>';

        echo '<section class="labs-card"><h2>Sample campaigns</h2><div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Campaign</th><th>Status</th><th>Tasks</th><th>Participants</th><th>Available</th></tr></thead><tbody>';
        foreach ((array)($summary['sample_campaigns'] ?? []) as $campaign) {
            echo '<tr><td>' . tl_stage884_render_e((string)($campaign['campaign_name'] ?? 'Campaign')) . '</td><td>' . tl_stage884_render_e((string)($campaign['campaign_status'] ?? '')) . '</td><td>' . (int)($campaign['task_count'] ?? 0) . '</td><td>' . (int)($campaign['participant_count'] ?? 0) . '</td><td>' . (int)($campaign['quantity_available'] ?? 0) . '</td></tr>';
        }
        if (empty($summary['sample_campaigns'])) echo '<tr><td colspan="5">No live campaign rows were returned.</td></tr>';
        echo '</tbody></table></div></section>';

        echo '<section class="labs-card"><h2>Read-only safety boundary</h2><div class="labs-stage880-card-grid">';
        foreach ($safe as $label => $ok) {
            echo '<article class="' . (!empty($ok) ? 'is-good' : 'is-bad') . '"><span>' . (!empty($ok) ? 'pass' : 'check') . '</span><strong>' . tl_stage884_render_e(str_replace('_', ' ', (string)$label)) . '</strong><small>Stage 884 boundary</small></article>';
        }
        echo '</div></section>';
    }
}
