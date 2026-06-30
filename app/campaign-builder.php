<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-microgifter-rewards.php';
$summary = tl_app_flow_summary();
$campaigns = tl_app_campaign_options();
$rewardCatalog = tl_mg_reward_catalog();
$rewardBridge = tl_mg_rewards_config();
$stage240Ops = tl_stage240_campaign_ops_state((string)($_GET['campaign'] ?? ''));
$stage240Campaign = $stage240Ops['campaign'] ?? null;
$stage240Tasks = $stage240Ops['tasks'] ?? [];
labs_page_start(['title' => 'Campaign Builder | Training Lab', 'section' => 'app', 'active' => 'app-campaign-builder']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('app-campaign-builder'); ?>
<?php if (function_exists('tl_stage800_render_reward_campaign_import')) tl_stage800_render_reward_campaign_import(); ?>
<?php if (function_exists('tl_stage800_render_assignment_preview')) tl_stage800_render_assignment_preview((string)($_GET['campaign'] ?? '')); ?>
<?php if (function_exists('tl_stage760_render_reward_package_builder')) tl_stage760_render_reward_package_builder((string)($_GET['campaign'] ?? '')); ?>
<?php if (function_exists('tl_stage760_render_merchant_sponsor_context')) tl_stage760_render_merchant_sponsor_context((string)($_GET['campaign'] ?? '')); ?>


<?php if (function_exists('tl_stage720_render_challenge_template_selection')) tl_stage720_render_challenge_template_selection(); ?>
<?php if (function_exists('tl_stage640_render_campaign_data_quality')) tl_stage640_render_campaign_data_quality((string)($_GET['campaign'] ?? '')); ?>

<?php if (function_exists('tl_stage520_render_campaign_builder')) tl_stage520_render_campaign_builder((string)($_GET['campaign'] ?? '')); ?>
<?php if (function_exists('tl_stage560_render_campaign_publish_planner')) tl_stage560_render_campaign_publish_planner((string)($_GET['campaign'] ?? '')); ?>

<?php if (function_exists('tl_stage600_render_campaign_state_control')) tl_stage600_render_campaign_state_control((string)($_GET['campaign'] ?? '')); ?>


<section class="labs-page-title labs-stage35-title">
  <div>
    <span class="labs-eyebrow">Campaign builder</span>
    <h1>Create a real Training Lab campaign blueprint.</h1>
    <p class="labs-copy">Build the standalone app campaign, task sequence, and reward rule in one form. This writes only to Training Lab tables.</p>
  </div>
  <div class="labs-actions"><a class="labs-btn" href="<?php echo labs_url('/app/flow-board.php'); ?>">Flow Board</a><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/task-runner.php'); ?>">Run Tasks</a></div>
</section>
<section class="labs-kpis labs-stage30-kpis">
  <div class="labs-kpi"><span class="labs-muted">Mode</span><strong><?php echo htmlspecialchars($summary['mode']); ?></strong><small><?php echo $summary['connected'] ? 'DB writes enabled' : 'DB required'; ?></small></div>
  <div class="labs-kpi"><span class="labs-muted">Campaigns</span><strong><?php echo (int)$summary['counts']['campaigns']; ?></strong><small>current total</small></div>
  <div class="labs-kpi"><span class="labs-muted">Tasks</span><strong><?php echo (int)$summary['counts']['tasks']; ?></strong><small>configured</small></div>
  <div class="labs-kpi"><span class="labs-muted">Boundary</span><strong>Safe</strong><small>training only</small></div>
</section>
<section class="labs-flow-grid labs-stage35-grid">
  <article class="labs-card labs-stage35-builder-card">
    <h2>Campaign blueprint</h2>
    <p class="labs-muted">Task lines use this format: <code>Task title | Instructions | checklist</code>. Add <code>[proof]</code> to require proof review.</p>
    <form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form labs-stage35-form">
      <input type="hidden" name="confirm_training_action" value="1">
      <input type="hidden" name="training_action" value="create_campaign_blueprint">
      <label>Campaign title<input name="title" value="Customer Service Readiness Sprint" required></label>
      <div class="labs-two-col">
        <label>Campaign type<select name="campaign_type"><option value="skills">Skills</option><option value="onboarding">Onboarding</option><option value="wellness">Wellness</option><option value="movement">Movement</option><option value="custom">Custom</option></select></label>
        <label>Status<select name="status"><option value="active">Active</option><option value="draft">Draft</option><option value="paused">Paused</option></select></label>
      </div>
      <label>Summary<textarea name="summary" rows="3">A standalone Training Lab sprint for completing a guided task sequence with proof review and simulated reward eligibility.</textarea></label>
      <label>Task blueprint<textarea name="task_blueprint" rows="9">Welcome checklist | Read the campaign instructions and confirm readiness. | checklist
Practice action | Complete the local training action and write a short note. | checklist
[proof] Submit final reflection | Explain what you completed and what changed. | text_reflection</textarea></label>
      <div class="labs-card labs-nested-card labs-reward-offer-picker">
        <h3>Microgifter reward offer</h3>
        <p class="labs-muted">Select a Microgifter reward template or Training Lab placeholder. Real issuance is adapter/key gated; eligibility still tracks in Training Lab reward events.</p>
        <label>Reward catalog<select name="catalog_template_ref" data-reward-catalog-select>
          <?php foreach ($rewardCatalog as $item): ?>
            <option value="<?php echo labs_e((string)$item['template_id']); ?>" data-label="<?php echo labs_e((string)$item['label']); ?>" data-type="<?php echo labs_e((string)$item['reward_type']); ?>" data-value="<?php echo (int)$item['value_cents']; ?>"><?php echo labs_e((string)$item['label']); ?> — <?php echo labs_e((string)$item['reward_type']); ?></option>
          <?php endforeach; ?>
        </select></label>
        <input type="hidden" name="microgifter_reward_bridge" value="1">
        <div class="labs-two-col">
          <label>Reward label<input name="reward_label" value="Readiness Badge"></label>
          <label>Reward value cents<input name="reward_value_cents" type="number" min="0" value="0"></label>
        </div>
        <div class="labs-two-col">
          <label>Reward type<select name="reward_type"><option value="badge">Badge</option><option value="microgift">Microgift</option><option value="entitlement">Entitlement</option><option value="wallet_credit_preview">Wallet credit preview</option><option value="custom">Custom</option></select></label>
          <label>Bridge mode<input value="<?php echo labs_e((string)$rewardBridge['mode']); ?>" readonly></label>
        </div>
      </div>
      <button class="labs-btn labs-btn-primary" type="submit">Create Campaign Blueprint</button>
    </form>
  </article>
  <aside class="labs-card">
    <h2>Recent campaigns</h2>
    <div class="labs-panel-list">
      <?php foreach (array_slice($campaigns, 0, 8) as $campaign): ?>
        <div class="labs-panel-item"><span class="labs-mini-icon">C</span><div><strong><?php echo htmlspecialchars($campaign['title'], ENT_QUOTES, 'UTF-8'); ?></strong><p><?php echo htmlspecialchars($campaign['status'] . ' · ' . $campaign['ref'], ENT_QUOTES, 'UTF-8'); ?></p></div><a class="labs-btn" href="<?php echo labs_url('/app/task-runner.php?campaign=' . rawurlencode((string)$campaign['ref'])); ?>">Run</a></div>
      <?php endforeach; if (!$campaigns): ?><p class="labs-muted">No campaigns yet. Create one to begin.</p><?php endif; ?>
    </div>
  </aside>
</section>
<section class="labs-safe-note">Campaign Builder boundary: campaign builder writes only to training_campaigns, training_campaign_tasks, training_reward_rules, and training_events.</section>

<section class="labs-flow-grid labs-stage240-grid">
  <article class="labs-card">
    <div class="labs-card-headline"><div><span class="labs-eyebrow">Build 6</span><h2>Campaign Operations Engine</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/product-ops.php?campaign=' . rawurlencode((string)($stage240Ops['campaign_ref'] ?? ''))); ?>">Ops API</a></div>
    <p class="labs-copy">Tighten the selected campaign after creation: update status, visibility, target actions, reward summary, and operator notes without leaving the core builder.</p>
    <?php if ($stage240Campaign): ?>
    <form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form">
      <input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="update_campaign_plan"><input type="hidden" name="campaign" value="<?php echo labs_e((string)$stage240Ops['campaign_ref']); ?>">
      <label>Title<input name="title" value="<?php echo labs_e((string)$stage240Campaign['title']); ?>"></label>
      <label>Summary<textarea name="summary" rows="3"><?php echo labs_e((string)($stage240Campaign['summary'] ?? '')); ?></textarea></label>
      <div class="labs-two-col"><label>Status<select name="status"><?php foreach (['draft','scheduled','active','paused','completed','archived'] as $s): ?><option value="<?php echo $s; ?>" <?php echo $s === (string)$stage240Campaign['status'] ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option><?php endforeach; ?></select></label><label>Visibility<select name="visibility"><?php foreach (['draft','private','published','archived'] as $v): ?><option value="<?php echo $v; ?>" <?php echo $v === (string)$stage240Campaign['visibility'] ? 'selected' : ''; ?>><?php echo ucfirst($v); ?></option><?php endforeach; ?></select></label></div>
      <div class="labs-two-col"><label>Target actions<input type="number" min="1" max="200" name="target_action_count" value="<?php echo (int)$stage240Campaign['target_action_count']; ?>"></label><label>Reward summary<input name="reward_summary" value="<?php echo labs_e((string)($stage240Campaign['reward_summary'] ?? '')); ?>"></label></div>
      <label>Operator notes<textarea name="operator_notes" rows="3" placeholder="Internal build note, rollout condition, or reviewer instruction."></textarea></label>
      <button class="labs-btn labs-btn-primary" type="submit">Update Campaign Plan</button>
    </form>
    <?php else: ?><p class="labs-muted">Create or select a campaign to enable operations.</p><?php endif; ?>
  </article>
  <aside class="labs-card">
    <h2>Add next task</h2>
    <?php if ($stage240Campaign): ?>
    <form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form">
      <input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="add_campaign_task"><input type="hidden" name="campaign" value="<?php echo labs_e((string)$stage240Ops['campaign_ref']); ?>">
      <label>Task title<input name="task_title" placeholder="New training task"></label>
      <label>Instructions<textarea name="instructions" rows="3" placeholder="What should the participant do?"></textarea></label>
      <div class="labs-two-col"><label>Type<select name="task_type"><option value="checklist">Checklist</option><option value="text_reflection">Text reflection</option><option value="movement">Movement</option><option value="custom">Custom</option></select></label><label class="labs-check-row"><input type="checkbox" name="proof_required" value="1"> Require review</label></div>
      <button class="labs-btn" type="submit">Add Task</button>
    </form>
    <?php endif; ?>
    <div class="labs-stage240-mini-list"><?php foreach (array_slice($stage240Tasks,0,8) as $task): ?><div><strong><?php echo labs_e((string)$task['title']); ?></strong><span><?php echo labs_e((string)$task['status']); ?> · #<?php echo (int)$task['position_no']; ?></span></div><?php endforeach; ?></div>
  </aside>
</section>

<?php labs_page_end(['section' => 'app']); ?>
