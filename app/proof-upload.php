<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$campaigns = tl_app_campaign_options();
labs_page_start(['title'=>'Proof Upload | Training Lab','section'=>'app','active'=>'app-tasks']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('app-proof-upload'); ?>

<?php if (function_exists('tl_stage680_render_participant_communication')) tl_stage680_render_participant_communication((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage640_render_proof_evidence_quality')) tl_stage640_render_proof_evidence_quality(); ?>

<?php if (function_exists('tl_stage520_render_participant_mission')) tl_stage520_render_participant_mission((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage560_render_mission_runbook')) tl_stage560_render_mission_runbook((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>

<?php if (function_exists('tl_stage600_render_proof_review_console')) tl_stage600_render_proof_review_console(); ?>


<section class="labs-page-title labs-stage30-title">
  <div><span class="labs-eyebrow">Functional proof submission</span><h1>Submit Training Lab proof.</h1><p class="labs-copy">This creates a real <code>training_proof_submissions</code> row using text/link proof only. Real media upload processing remains disabled.</p></div>
  <a class="labs-btn" href="<?php echo labs_url('/app/workspace.php'); ?>">Back to Workspace</a>
</section>
<section class="labs-flow-grid">
  <form class="labs-card labs-stage30-form" action="<?php echo labs_url('/app/action-result.php'); ?>" method="post">
    <input type="hidden" name="confirm_training_action" value="1">
    <input type="hidden" name="training_action" value="submit_proof">
    <h2>Proof details</h2>
    <label>Campaign<select name="campaign_id"><?php foreach ($campaigns as $campaign): ?><option value="<?php echo htmlspecialchars($campaign['ref'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($campaign['title'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></label>
    <div class="labs-two-col"><label>User ID<input name="user_id" type="number" min="1" value="1"></label><label>Participant label<input name="participant_label" value="Demo Participant"></label></div>
    <label>Proof note<textarea name="proof_text" rows="6" required>I completed the training action and am submitting this Training Lab proof for review.</textarea></label>
    <label>External proof URL, optional<input name="external_url" placeholder="https://example.com/proof-note"></label>
    <button class="labs-btn labs-btn-primary" type="submit">Submit Proof to Review Queue</button>
  </form>
  <aside class="labs-card labs-review-card"><h2>Upload boundary</h2><p class="labs-muted">The file picker is intentionally not used in this functional block. This prevents real media upload processing while the standalone app flow is built.</p><div class="labs-proof-box"><label>Visual file picker<input type="file" disabled></label></div><p class="labs-safe-note">No media is uploaded, stored, scanned, or approved by this page.</p></aside>
</section>
<?php labs_page_end(['section'=>'app']); ?>
