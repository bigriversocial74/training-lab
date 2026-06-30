<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$userId = max(1, (int)($_GET['user_id'] ?? 1));
$campaignRef = isset($_GET['campaign']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$_GET['campaign']) : tl_app_default_campaign_ref();
$state = tl_stage50_resource_state($campaignRef, $userId);
labs_page_start(['title' => 'Resource Hub | Training Lab', 'section' => 'app', 'active' => 'app-resource-hub']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('app-resource-hub'); ?>
<?php if (function_exists('tl_stage760_render_offer_preview_experience')) tl_stage760_render_offer_preview_experience((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>


<?php if (function_exists('tl_stage720_render_training_content_library')) tl_stage720_render_training_content_library((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>

<section class="labs-page-title labs-stage50-title"><div><span class="labs-eyebrow">Resource hub</span><h1>Give participants reusable guides, checklists, and demo resources.</h1><p class="labs-copy">Participants can save resource notes as Training Lab events. No file uploads or external delivery are added.</p></div><div class="labs-actions"><a class="labs-btn" href="<?php echo labs_url('/app/message-board.php?campaign=' . rawurlencode($campaignRef) . '&user_id=' . $userId); ?>">Message Board</a><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/reflection-journal.php?campaign=' . rawurlencode($campaignRef) . '&user_id=' . $userId); ?>">Write Reflection</a></div></section>
<section class="labs-stage50-resource-grid"><?php foreach ($state['resources'] as $resource): ?><article class="labs-card"><span class="labs-pill"><?php echo labs_e((string)$resource['type']); ?></span><h2><?php echo labs_e((string)$resource['title']); ?></h2><p class="labs-muted"><?php echo labs_e((string)$resource['body']); ?></p></article><?php endforeach; ?></section>
<section class="labs-flow-grid"><article class="labs-card"><h2>Save resource note</h2><form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form labs-stage45-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="save_resource_note"><input type="hidden" name="campaign" value="<?php echo labs_e($campaignRef); ?>"><label>User ID<input type="number" name="user_id" value="<?php echo $userId; ?>" min="1"></label><label>Resource title<input type="text" name="resource_title" value="Proof Quality Checklist"></label><label>Note<textarea name="resource_note" rows="5">I reviewed the resource and understand what proof should include.</textarea></label><button class="labs-btn labs-btn-primary" type="submit">Save Training Note</button></form></article><aside class="labs-card"><h2>Recent resource notes</h2><div class="labs-stage50-event-list"><?php foreach ($state['recent_notes'] as $event): $meta=json_decode((string)($event['metadata_json'] ?? '{}'), true) ?: []; ?><div><strong><?php echo labs_e((string)($meta['resource_title'] ?? 'Resource note')); ?></strong><p><?php echo labs_e((string)($meta['resource_note'] ?? '')); ?></p><small><?php echo labs_e((string)$event['created_at']); ?></small></div><?php endforeach; if (!$state['recent_notes']): ?><p class="labs-muted">No resource notes yet.</p><?php endif; ?></div></aside></section>
<section class="labs-safe-note">Resource notes are stored as training_events only. No media upload, storage processing, or outbound delivery is added.</section>
<?php labs_page_end(['section' => 'app']); ?>
