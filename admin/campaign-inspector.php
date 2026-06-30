<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';

$campaignRef = isset($_GET['campaign']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$_GET['campaign']) : null;
$inspector = tl_training_campaign_inspector_summary($campaignRef);
$campaign = $inspector['campaign'] ?? [];
$counts = $inspector['counts'] ?? [];
$breakdowns = $inspector['breakdowns'] ?? [];
$campaigns = tl_training_recent_campaign_snapshots(10);

function tl_stage14_value($value, string $fallback = 'None yet'): string
{
    if ($value === null || $value === '') return $fallback;
    return (string)$value;
}

function tl_stage14_status_list(array $counts): string
{
    if (!$counts) return 'No rows yet';
    $parts = [];
    foreach ($counts as $key => $value) {
        $parts[] = ucwords(str_replace('_', ' ', (string)$key)) . ': ' . (int)$value;
    }
    return implode(' · ', $parts);
}

function tl_stage14_money($cents, $currency = 'USD'): string
{
    $prefix = strtoupper((string)$currency) === 'USD' ? '$' : strtoupper((string)$currency) . ' ';
    return $prefix . number_format(((int)$cents) / 100, 2);
}

labs_page_start(['title'=>'Campaign Inspector | Training Lab','section'=>'admin','active'=>'admin-campaign-inspector']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-campaign-inspector'); ?>
<?php if (function_exists('tl_stage800_render_assignment_preview')) tl_stage800_render_assignment_preview((string)($_GET['campaign'] ?? '')); ?>
<?php if (function_exists('tl_stage800_render_reward_campaign_import')) tl_stage800_render_reward_campaign_import(); ?>
<?php if (function_exists('tl_stage640_render_campaign_data_quality')) tl_stage640_render_campaign_data_quality((string)($_GET['campaign'] ?? '')); ?>

<?php if (function_exists('tl_stage600_render_campaign_state_control')) tl_stage600_render_campaign_state_control((string)($_GET['campaign'] ?? '')); ?>


<section class="labs-page-title labs-stage14-title">
  <div>
    <span class="labs-eyebrow">Campaign inspector</span>
    <h1>Inspect one campaign across tasks, participants, proofs, reviews, rewards, receipts, and events.</h1>
    <p class="labs-copy">This page is database-backed when the Training Lab tables are available and falls back to the demo seed only when needed. It is read-only: no edits, no approval writes, no reward issuing, no wallet changes, and no upload processing.</p>
  </div>
  <div class="labs-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/command-center.php'); ?>">Command Center</a><a class="labs-btn" href="<?php echo labs_url('/api/training/campaign-inspector.php' . ($campaignRef ? '?campaign=' . urlencode($campaignRef) : '')); ?>">Inspector JSON</a></div>
</section>

<section class="labs-stage14-selector labs-card">
  <div>
    <span class="labs-eyebrow">Selected campaign</span>
    <h2><?php echo htmlspecialchars(tl_training_campaign_label($campaign)); ?></h2>
    <p class="labs-muted"><?php echo htmlspecialchars(tl_stage14_value($campaign['summary'] ?? $campaign['description'] ?? null, 'No campaign summary stored yet.')); ?></p>
  </div>
  <form method="get" class="labs-stage14-form">
    <label for="campaign">Switch campaign</label>
    <select id="campaign" name="campaign">
      <?php foreach ($campaigns as $row): $value = (string)($row['id'] ?? $row['slug'] ?? $row['public_id'] ?? ''); ?>
        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $value !== '' && in_array($value, [(string)($campaign['slug'] ?? ''), (string)($campaign['public_id'] ?? ''), (string)($campaign['id'] ?? '')], true) ? 'selected' : ''; ?>><?php echo htmlspecialchars($row['title'] ?? 'Training Campaign'); ?></option>
      <?php endforeach; ?>
    </select>
    <button class="labs-btn labs-btn-primary" type="submit">Inspect</button>
  </form>
<a class="labs-btn" href="<?php echo labs_url('/admin/task-inspector.php'); ?>">Task Inspector</a></section>

<section class="labs-kpis labs-stage14-kpis">
  <div class="labs-kpi"><span class="labs-muted">Mode</span><strong><?php echo htmlspecialchars(ucwords(str_replace('-', ' ', (string)($inspector['mode'] ?? 'read-only')))); ?></strong><small><?php echo htmlspecialchars((string)($campaign['status'] ?? 'status unknown')); ?></small></div>
  <div class="labs-kpi"><span class="labs-muted">Tasks</span><strong><?php echo (int)($counts['tasks'] ?? 0); ?></strong><small><?php echo htmlspecialchars(tl_stage14_status_list($breakdowns['task_status'] ?? [])); ?></small></div>
  <div class="labs-kpi"><span class="labs-muted">Proof coverage</span><strong><?php echo (int)($counts['proof_coverage_percent'] ?? 0); ?>%</strong><small><?php echo (int)($counts['proofs'] ?? 0); ?> proof rows</small></div>
  <div class="labs-kpi"><span class="labs-muted">Reward preview</span><strong><?php echo (int)($counts['reward_events'] ?? 0); ?></strong><small><?php echo (int)($counts['reward_rules'] ?? 0); ?> rules, no issuing</small></div>
</section>

<section class="labs-stage13-tabs labs-stage14-tabs" aria-label="Campaign inspector sections">
  <a href="#stage14-overview">Overview</a>
  <a href="#stage14-tasks">Tasks</a>
  <a href="#stage14-proof">Proofs & Reviews</a>
  <a href="#stage14-rewards">Rewards</a>
  <a href="#stage14-timeline">Timeline</a>
  <a href="#stage14-safety">Safety</a>
</section>

<section class="labs-flow-grid" id="stage14-overview">
  <article class="labs-card">
    <div class="labs-card-headline"><div><span class="labs-eyebrow">Campaign record</span><h2><?php echo htmlspecialchars(tl_training_campaign_label($campaign)); ?></h2></div><span class="labs-pill"><?php echo htmlspecialchars((string)($campaign['visibility'] ?? 'visibility')); ?></span></div>
    <div class="labs-stage14-meta-grid">
      <div><span>Type</span><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($campaign['campaign_type'] ?? 'training')))); ?></strong></div>
      <div><span>Status</span><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($campaign['status'] ?? 'draft')))); ?></strong></div>
      <div><span>Starts</span><strong><?php echo htmlspecialchars(tl_stage14_value($campaign['starts_at'] ?? null)); ?></strong></div>
      <div><span>Ends</span><strong><?php echo htmlspecialchars(tl_stage14_value($campaign['ends_at'] ?? null)); ?></strong></div>
      <div><span>Target actions</span><strong><?php echo number_format((int)($campaign['target_action_count'] ?? 0)); ?></strong></div>
      <div><span>Updated</span><strong><?php echo htmlspecialchars(tl_stage14_value($campaign['updated_at'] ?? null)); ?></strong></div>
    </div>
  </article>
  <aside class="labs-card">
    <h2>Record totals</h2>
    <div class="labs-stage14-count-grid">
      <span><strong><?php echo (int)($counts['participants'] ?? 0); ?></strong>Participants</span>
      <span><strong><?php echo (int)($counts['reviews'] ?? 0); ?></strong>Reviews</span>
      <span><strong><?php echo (int)($counts['receipts'] ?? 0); ?></strong>Receipts</span>
      <span><strong><?php echo (int)($counts['events'] ?? 0); ?></strong>Events</span>
    </div>
  </aside>
</section>

<section class="labs-card" id="stage14-tasks">
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Task sequence</span><h2>Campaign tasks</h2></div><span class="labs-pill">Read-only</span></div>
  <div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>#</th><th>Task</th><th>Type</th><th>Proof</th><th>Duration</th><th>Status</th></tr></thead><tbody>
    <?php foreach (($inspector['tasks'] ?? []) as $task): ?>
      <tr><td><?php echo (int)($task['position_no'] ?? $task['day_no'] ?? 0); ?></td><td><strong><?php echo htmlspecialchars($task['title'] ?? 'Training task'); ?></strong><br><small><?php echo htmlspecialchars(tl_stage14_value($task['instructions'] ?? null, 'No instructions stored.')); ?></small></td><td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($task['task_type'] ?? 'task')))); ?></td><td><?php echo !empty($task['proof_required']) ? 'Required' : 'Optional'; ?></td><td><?php echo (int)($task['expected_duration_minutes'] ?? 0); ?> min</td><td><span class="labs-pill"><?php echo htmlspecialchars((string)($task['status'] ?? 'unknown')); ?></span></td></tr>
    <?php endforeach; ?>
    <?php if (empty($inspector['tasks'])): ?><tr><td colspan="6">No task rows found for this campaign yet.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>

<section class="labs-flow-grid" id="stage14-proof">
  <article class="labs-card">
    <div class="labs-card-headline"><div><span class="labs-eyebrow">Proof submissions</span><h2>Proof queue visibility</h2></div><span class="labs-pill"><?php echo htmlspecialchars(tl_stage14_status_list($breakdowns['proof_status'] ?? [])); ?></span></div>
    <div class="labs-panel-list labs-compact-list">
      <?php foreach (array_slice($inspector['proofs'] ?? [], 0, 10) as $proof): ?>
        <div class="labs-panel-item"><span class="labs-mini-icon">P</span><div><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($proof['proof_type'] ?? 'proof')))); ?></strong><p><?php echo htmlspecialchars(tl_stage14_value($proof['proof_text'] ?? null, 'No proof text.')); ?> · <?php echo htmlspecialchars(tl_stage14_value($proof['submitted_at'] ?? $proof['created_at'] ?? null)); ?></p></div><span class="labs-pill"><?php echo htmlspecialchars((string)($proof['status'] ?? 'submitted')); ?></span></div>
      <?php endforeach; ?>
      <?php if (empty($inspector['proofs'])): ?><div class="labs-panel-item"><span class="labs-mini-icon">—</span><div><strong>No proof rows</strong><p>The proof table is ready, but this campaign has no proof submissions yet.</p></div></div><?php endif; ?>
    </div>
  </article>
  <aside class="labs-card">
    <div class="labs-card-headline"><div><span class="labs-eyebrow">Reviews</span><h2>Decision visibility</h2></div><span class="labs-pill"><?php echo htmlspecialchars(tl_stage14_status_list($breakdowns['review_decision'] ?? [])); ?></span></div>
    <div class="labs-panel-list labs-compact-list">
      <?php foreach (array_slice($inspector['reviews'] ?? [], 0, 10) as $review): ?>
        <div class="labs-panel-item"><span class="labs-mini-icon">R</span><div><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($review['decision'] ?? 'pending')))); ?></strong><p><?php echo htmlspecialchars(tl_stage14_value($review['review_notes'] ?? null, 'No notes.')); ?> · <?php echo htmlspecialchars(tl_stage14_value($review['reviewed_at'] ?? $review['created_at'] ?? null)); ?></p></div></div>
      <?php endforeach; ?>
      <?php if (empty($inspector['reviews'])): ?><div class="labs-panel-item"><span class="labs-mini-icon">—</span><div><strong>No review rows</strong><p>No review decisions are connected to this campaign yet.</p></div></div><?php endif; ?>
    </div>
  </aside>
</section>

<section class="labs-flow-grid" id="stage14-rewards">
  <article class="labs-card">
    <div class="labs-card-headline"><div><span class="labs-eyebrow">Reward rules</span><h2>Configured preview rules</h2></div><span class="labs-pill">No issuing</span></div>
    <div class="labs-panel-list labs-compact-list">
      <?php foreach (($inspector['reward_rules'] ?? []) as $rule): ?>
        <div class="labs-panel-item"><span class="labs-mini-icon">$</span><div><strong><?php echo htmlspecialchars($rule['rule_name'] ?? $rule['reward_label'] ?? 'Reward rule'); ?></strong><p><?php echo htmlspecialchars(($rule['trigger_type'] ?? 'trigger') . ' · ' . ($rule['reward_type'] ?? 'reward') . ' · ' . tl_stage14_money($rule['reward_value_cents'] ?? 0, $rule['currency'] ?? 'USD')); ?></p></div><span class="labs-pill"><?php echo htmlspecialchars((string)($rule['status'] ?? 'preview')); ?></span></div>
      <?php endforeach; ?>
      <?php if (empty($inspector['reward_rules'])): ?><div class="labs-panel-item"><span class="labs-mini-icon">—</span><div><strong>No reward rules</strong><p>No preview reward rules are configured for this campaign.</p></div></div><?php endif; ?>
    </div>
  </article>
  <aside class="labs-card">
    <div class="labs-card-headline"><div><span class="labs-eyebrow">Reward events</span><h2>Eligibility preview</h2></div><span class="labs-pill"><?php echo htmlspecialchars(tl_stage14_status_list($breakdowns['reward_event_status'] ?? [])); ?></span></div>
    <div class="labs-panel-list labs-compact-list">
      <?php foreach (array_slice($inspector['reward_events'] ?? [], 0, 10) as $event): ?>
        <div class="labs-panel-item"><span class="labs-mini-icon">E</span><div><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($event['status'] ?? 'event')))); ?></strong><p><?php echo htmlspecialchars(tl_stage14_value($event['eligibility_reason'] ?? null, 'No eligibility reason.')); ?> · <?php echo htmlspecialchars(tl_stage14_money($event['value_cents'] ?? 0, $event['currency'] ?? 'USD')); ?></p></div></div>
      <?php endforeach; ?>
      <?php if (empty($inspector['reward_events'])): ?><div class="labs-panel-item"><span class="labs-mini-icon">—</span><div><strong>No reward events</strong><p>No Microgifter wallet/reward events are issued by this inspector.</p></div></div><?php endif; ?>
    </div>
  </aside>
</section>

<section class="labs-flow-grid" id="stage14-timeline">
  <article class="labs-card">
    <div class="labs-card-headline"><div><span class="labs-eyebrow">Timeline</span><h2>Recent campaign trail</h2></div><span class="labs-pill">Read-only log</span></div>
    <div class="labs-stage14-timeline">
      <?php foreach (($inspector['timeline'] ?? []) as $item): ?>
        <div><span></span><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($item['type'] ?? 'event')))); ?></strong><p><?php echo htmlspecialchars(tl_stage14_value($item['label'] ?? null, 'Campaign')); ?> · <?php echo htmlspecialchars(tl_stage14_value($item['at'] ?? null)); ?></p></div>
      <?php endforeach; ?>
      <?php if (empty($inspector['timeline'])): ?><div><span></span><strong>No timeline rows yet</strong><p>Events and proofs will appear here after rows exist.</p></div><?php endif; ?>
    </div>
  </article>
  <aside class="labs-card" id="stage14-safety">
    <h2>Safe boundary lock</h2>
    <div class="labs-stage13-boundary-grid labs-stage14-boundaries">
      <span>Read-only inspector</span><span>No campaign writes</span><span>No task writes</span><span>No approvals</span><span>No reward issuing</span><span>No wallet changes</span><span>No upload processing</span><span>No new SQL</span>
    </div>
  </aside>
</section>
<?php labs_page_end(['section'=>'admin']); ?>
