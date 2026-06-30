<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$summary = tl_app_flow_summary();
$campaigns = tl_app_campaign_options();
$defaultCampaign = tl_app_default_campaign_ref();
$recentProofs = tl_app_recent_proofs(8);
$participants = tl_app_participant_progress(8);
labs_page_start(['title' => 'Workspace | Training Lab', 'section' => 'app', 'active' => 'app-workspace']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('app-workspace'); ?>

<section class="labs-page-title labs-stage30-title">
  <div>
    <span class="labs-eyebrow">Functional app workflow</span>
    <h1>Build and run the standalone Training Lab flow.</h1>
    <p class="labs-copy">Create a training campaign, join as a participant, submit text/link proof, and move the record into admin review. These are real Training Lab table writes, not browser-only state.</p>
  </div>
  <div class="labs-actions">
    <a class="labs-btn" href="<?php echo labs_url('/admin/flow-control.php'); ?>">Admin Flow Control</a>
    <a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/api/training/flow-state.php'); ?>">Flow JSON</a>
  </div>
</section>

<section class="labs-kpis labs-stage30-kpis">
  <div class="labs-kpi"><span class="labs-muted">Mode</span><strong><?php echo htmlspecialchars($summary['mode']); ?></strong><small><?php echo $summary['connected'] ? 'DB connected' : 'fallback only'; ?></small></div>
  <div class="labs-kpi"><span class="labs-muted">Campaigns</span><strong><?php echo (int)$summary['counts']['campaigns']; ?></strong><small>Training Lab table</small></div>
  <div class="labs-kpi"><span class="labs-muted">Participants</span><strong><?php echo (int)$summary['counts']['participants']; ?></strong><small>joined records</small></div>
  <div class="labs-kpi"><span class="labs-muted">Pending Proof</span><strong><?php echo (int)$summary['counts']['pending_proofs']; ?></strong><small>ready for admin</small></div>
</section>

<?php if (!$summary['connected']): ?>
<section class="labs-card labs-warning-card">
  <h2>Database is not connected yet.</h2>
  <p class="labs-copy">This standalone app block is ready, but write actions need the existing Training Lab DB config and tables. Your DB Health page already tells you whether the install is connected.</p>
  <a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/db-health.php'); ?>">Open DB Health</a>
</section>
<?php endif; ?>

<section class="labs-flow-grid labs-stage30-grid">
  <article class="labs-card">
    <span class="labs-eyebrow">Step 1</span>
    <h2>Seed or create campaigns</h2>
    <p class="labs-muted">Seed demo campaigns when the lab is empty, or create a new standalone training campaign.</p>
    <form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form">
      <input type="hidden" name="confirm_training_action" value="1">
      <input type="hidden" name="training_action" value="seed_demo">
      <button class="labs-btn" type="submit">Seed Demo Campaigns</button>
    </form>
    <hr class="labs-soft-rule">
    <form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form">
      <input type="hidden" name="confirm_training_action" value="1">
      <input type="hidden" name="training_action" value="create_campaign">
      <label>Campaign title<input name="title" value="5-Day Local Action Challenge" required></label>
      <label>Summary<textarea name="summary" rows="3">Complete a short local action sequence, submit proof, and unlock simulated Training Lab reward eligibility.</textarea></label>
      <div class="labs-two-col">
        <label>Target action count<input name="target_action_count" type="number" min="1" max="30" value="5"></label>
        <label>Reward label<input name="reward_label" value="Local Action Badge"></label>
      </div>
      <button class="labs-btn labs-btn-primary" type="submit">Create Training Campaign</button>
    </form>
  </article>

  <article class="labs-card">
    <span class="labs-eyebrow">Step 2</span>
    <h2>Join as participant</h2>
    <p class="labs-muted">Creates or reuses a Training Lab participant and streak row.</p>
    <form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form">
      <input type="hidden" name="confirm_training_action" value="1">
      <input type="hidden" name="training_action" value="join_campaign">
      <label>Campaign<select name="campaign_id">
        <?php foreach ($campaigns as $campaign): ?>
          <option value="<?php echo htmlspecialchars($campaign['ref'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $campaign['ref'] === $defaultCampaign ? 'selected' : ''; ?>><?php echo htmlspecialchars($campaign['title'] . ' · ' . $campaign['status'], ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
      </select></label>
      <div class="labs-two-col">
        <label>User ID<input name="user_id" type="number" min="1" value="1"></label>
        <label>Participant label<input name="participant_label" value="Demo Participant"></label>
      </div>
      <button class="labs-btn labs-btn-primary" type="submit">Join Campaign</button>
    </form>
  </article>

  <article class="labs-card">
    <span class="labs-eyebrow">Step 3</span>
    <h2>Submit proof</h2>
    <p class="labs-muted">Stores proof text/link in Training Lab tables only. No real media upload processing is enabled.</p>
    <form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form">
      <input type="hidden" name="confirm_training_action" value="1">
      <input type="hidden" name="training_action" value="submit_proof">
      <label>Campaign<select name="campaign_id">
        <?php foreach ($campaigns as $campaign): ?>
          <option value="<?php echo htmlspecialchars($campaign['ref'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($campaign['title'], ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
      </select></label>
      <div class="labs-two-col">
        <label>User ID<input name="user_id" type="number" min="1" value="1"></label>
        <label>Participant label<input name="participant_label" value="Demo Participant"></label>
      </div>
      <label>Proof note<textarea name="proof_text" rows="5" required>I completed the training action and am submitting this Training Lab proof for review.</textarea></label>
      <label>External proof URL, optional<input name="external_url" placeholder="https://example.com/proof-note"></label>
      <button class="labs-btn labs-btn-primary" type="submit">Submit Training Proof</button>
    </form>
  </article>

  <aside class="labs-card labs-stage30-status-card">
    <span class="labs-eyebrow">Live flow status</span>
    <h2>Current records</h2>
    <div class="labs-stage30-count-list">
      <div><span>Tasks</span><strong><?php echo (int)$summary['counts']['tasks']; ?></strong></div>
      <div><span>Proofs</span><strong><?php echo (int)$summary['counts']['proofs']; ?></strong></div>
      <div><span>Reviews</span><strong><?php echo (int)$summary['counts']['reviews']; ?></strong></div>
      <div><span>Receipts</span><strong><?php echo (int)$summary['counts']['receipts']; ?></strong></div>
      <div><span>Reward Events</span><strong><?php echo (int)$summary['counts']['reward_events']; ?></strong></div>
    </div>
    <p class="labs-safe-note">Writes stay inside Training Lab tables. Wallet/reward/claim/payment systems are not touched.</p>
  </aside>
</section>

<section class="labs-flow-grid">
  <article class="labs-card">
    <h2>Recent proof submissions</h2>
    <?php if (!$recentProofs): ?><p class="labs-muted">No proof submissions yet.</p><?php endif; ?>
    <div class="labs-panel-list">
      <?php foreach ($recentProofs as $proof): ?>
        <div class="labs-panel-item">
          <span class="labs-mini-icon">P</span>
          <div><strong><?php echo htmlspecialchars((string)$proof['participant_label'], ENT_QUOTES, 'UTF-8'); ?></strong><p><?php echo htmlspecialchars((string)$proof['campaign_title'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string)$proof['task_title'], ENT_QUOTES, 'UTF-8'); ?></p></div>
          <span class="labs-pill"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)$proof['status'])), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </article>
  <article class="labs-card">
    <h2>Participant progress</h2>
    <?php if (!$participants): ?><p class="labs-muted">No participants yet.</p><?php endif; ?>
    <div class="labs-panel-list">
      <?php foreach ($participants as $person): ?>
        <div class="labs-panel-item">
          <span class="labs-mini-icon">U</span>
          <div><strong><?php echo htmlspecialchars((string)($person['participant_label'] ?: 'Participant'), ENT_QUOTES, 'UTF-8'); ?></strong><p><?php echo htmlspecialchars((string)$person['campaign_title'], ENT_QUOTES, 'UTF-8'); ?></p></div>
          <span class="labs-pill"><?php echo (int)$person['completed_action_count']; ?>/<?php echo (int)$person['target_action_count']; ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </article>
</section>
<?php labs_page_end(['section' => 'app']); ?>
