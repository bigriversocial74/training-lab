<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';
$status = [
    'db_configured' => tl_db_ready(),
    'training_tables' => array_filter(['training_campaigns','training_campaign_tasks','training_participants','training_proof_submissions','training_reviews','training_action_receipts','training_reward_rules','training_reward_events','training_streaks','training_events'], 'tl_table_exists'),
];
labs_page_start(['title'=>'Backend Controls | Training Lab','section'=>'admin','active'=>'admin-stage7']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-stage7'); ?>

<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Controlled backend workflow</span>
    <h1>Backend action controls</h1>
    <p class="labs-copy">Use these endpoints after the Training Lab SQL has imported and DB config is present. These actions write only to Training Lab tables.</p>
  </div>
  <a class="labs-btn" href="<?php echo labs_url('/api/training/db-status.php'); ?>">DB Status JSON</a>
</section>
<section class="labs-kpis">
  <div class="labs-kpi"><span class="labs-muted">DB configured</span><strong><?php echo $status['db_configured'] ? 'Yes' : 'No'; ?></strong><small>local config/env</small></div>
  <div class="labs-kpi"><span class="labs-muted">Detected tables</span><strong><?php echo count($status['training_tables']); ?></strong><small>Training Lab tables</small></div>
  <div class="labs-kpi"><span class="labs-muted">Wallet writes</span><strong>No</strong><small>reward events only</small></div>
  <div class="labs-kpi"><span class="labs-muted">Uploads</span><strong>No</strong><small>metadata only</small></div>
</section>
<section class="labs-grid-2">
  <article class="labs-card">
    <h2>Action endpoint order</h2>
    <ol class="labs-steps">
      <li><strong>Seed demo campaigns</strong><span>/api/training/actions/seed-demo.php</span></li>
      <li><strong>Create campaign</strong><span>/api/training/actions/create-campaign.php</span></li>
      <li><strong>Join campaign</strong><span>/api/training/actions/join-campaign.php</span></li>
      <li><strong>Submit proof record</strong><span>/api/training/actions/submit-proof.php</span></li>
      <li><strong>Review proof</strong><span>/api/training/actions/review-proof.php</span></li>
      <li><strong>Evaluate rewards</strong><span>/api/training/actions/evaluate-rewards.php</span></li>
    </ol>
  </article>
  <article class="labs-card">
    <h2>Hard stop boundaries</h2>
    <p class="labs-copy">This build does not process real media uploads, payments, claim/redeem behavior, or wallet balance changes. Reward eligibility is written to <code>training_reward_events</code> only.</p>
    <div class="labs-note">Next hardening pass maps these rows to the existing Microgifter account, wallet, reward, and claim tables after exact table names are confirmed.</div>
  </article>
</section>
<?php labs_page_end(['section'=>'admin']); ?>
