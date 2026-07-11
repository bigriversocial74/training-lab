<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-pilot-communications-actions.php';
$page = ['title'=>'Notification Templates | Training Lab','section'=>'admin','active'=>'admin-notification-templates','required_role'=>'manager'];
$user = tl_product_require_page_access($page);
tl_security_session_start();
$flash = $_SESSION['tl_pilot_communications_flash'] ?? null;
unset($_SESSION['tl_pilot_communications_flash']);
$scope = tl_notifications_scope($user);
$templates = tl_notifications_tables_ready() ? tl_notifications_templates($user) : [];
$keys = ['participant_invited','task_reminder','proof_submitted','review_approved','review_revision_required','review_rejected','reward_earned','reward_delivery_succeeded','reward_delivery_failed'];
$selectedKey = tl_action_enum($_GET['template'] ?? '', $keys, $keys[0]);
$system = null; $override = null;
foreach ($templates as $row) {
    if ((string)$row['template_key'] !== $selectedKey) continue;
    if ((int)$row['owner_user_id'] === 0) $system = $row; else $override = $row;
}
$effective = $override && (string)$override['status'] === 'active' ? $override : $system;
$sample = ['participant_name'=>'Jordan Lee','campaign_title'=>'Hospitality Service Sprint','task_title'=>'Complete the guest follow-up','reward_label'=>'Local dining reward','review_status'=>'approved','action_url'=>'https://labs.example.com/app/index.php','unsubscribe_url'=>'https://labs.example.com/notification-preferences.php?token=example'];
$preview = $effective ? tl_notifications_render($effective, $sample) : ['subject'=>'','text'=>''];
labs_page_start($page);
?>
<section class="labs-product-hero"><article class="labs-product-hero-main"><span class="labs-product-kicker">Communication templates</span><h1>Clear lifecycle messages with safe merchant overrides.</h1><p>System templates remain immutable. A merchant override can change only approved copy and placeholders for campaigns owned by the signed-in manager.</p></article><aside class="labs-product-next"><div><span>Available templates</span><h2><?php echo count($keys); ?></h2><p>Email only. No SMS, push, wallet, gift, or account authority is created.</p></div><a class="labs-btn" href="<?php echo labs_url('/admin/pilot-communications.php'); ?>">Pilot Dashboard</a></aside></section>
<?php if (is_array($flash)): ?><div class="labs-alert is-<?php echo labs_e((string)($flash['tone'] ?? 'info')); ?>" role="status"><?php echo labs_e((string)($flash['message'] ?? '')); ?></div><?php endif; ?>
<?php if (!tl_notifications_tables_ready()): ?><section class="labs-product-card"><h2>Migration required</h2><p>Import <code>database/pilot_operations_communications_v1.sql</code> before managing templates.</p></section><?php else: ?>
<section class="labs-template-layout">
  <nav class="labs-product-card labs-template-nav" aria-label="Notification templates"><span class="labs-product-kicker">Template library</span><?php foreach ($keys as $key): ?><a class="<?php echo $selectedKey === $key ? 'is-active' : ''; ?>" href="<?php echo labs_url('/admin/notification-templates.php?template=' . rawurlencode($key)); ?>"><?php echo labs_e(ucwords(str_replace('_',' ',$key))); ?></a><?php endforeach; ?></nav>
  <div class="labs-template-main">
    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker"><?php echo labs_e((string)($effective['message_class'] ?? 'transactional')); ?></span><h2><?php echo labs_e(ucwords(str_replace('_',' ',$selectedKey))); ?></h2><p><?php echo $override ? 'A merchant override exists. System copy remains available as the fallback.' : 'This merchant currently uses the immutable system template.'; ?></p></div><span class="labs-product-status is-<?php echo $override && (string)$override['status']==='active'?'success':'neutral'; ?>"><?php echo $override ? labs_e((string)$override['status']) : 'system'; ?></span></div>
      <form class="labs-template-form" action="<?php echo labs_url('/admin/pilot-communications-action.php'); ?>" method="post">
        <?php echo tl_security_csrf_field(); ?><input type="hidden" name="communications_action" value="save_notification_template"><input type="hidden" name="template_key" value="<?php echo labs_e($selectedKey); ?>">
        <label>Template name<input type="text" name="template_name" maxlength="191" required value="<?php echo labs_e((string)($override['template_name'] ?? $system['template_name'] ?? ucwords(str_replace('_',' ',$selectedKey)))); ?>"></label>
        <label>Subject<input type="text" name="subject_template" maxlength="255" required value="<?php echo labs_e((string)($override['subject_template'] ?? $system['subject_template'] ?? '')); ?>"></label>
        <label>Plain-text message<textarea name="body_template" rows="12" maxlength="20000" required><?php echo labs_e((string)($override['body_template'] ?? $system['body_template'] ?? '')); ?></textarea></label>
        <div class="labs-template-placeholders"><strong>Allowed placeholders</strong><?php foreach (tl_notifications_allowed_placeholders() as $placeholder): ?><code>{{<?php echo labs_e($placeholder); ?>}}</code><?php endforeach; ?></div>
        <div class="labs-actions"><button class="labs-btn labs-btn-primary" type="submit">Save Merchant Override</button></div>
      </form>
      <?php if ($override): ?><div class="labs-actions"><form action="<?php echo labs_url('/admin/pilot-communications-action.php'); ?>" method="post"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="communications_action" value="notification_template_action"><input type="hidden" name="template_key" value="<?php echo labs_e($selectedKey); ?>"><input type="hidden" name="template_action" value="<?php echo (string)$override['status']==='paused'?'resume':'pause'; ?>"><button class="labs-btn" type="submit"><?php echo (string)$override['status']==='paused'?'Resume Override':'Pause Override'; ?></button></form><form action="<?php echo labs_url('/admin/pilot-communications-action.php'); ?>" method="post"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="communications_action" value="notification_template_action"><input type="hidden" name="template_key" value="<?php echo labs_e($selectedKey); ?>"><input type="hidden" name="template_action" value="archive"><button class="labs-btn" type="submit">Archive Override</button></form></div><?php endif; ?>
    </section>
    <section class="labs-product-card labs-template-preview"><span class="labs-product-kicker">Participant preview</span><h2><?php echo labs_e((string)$preview['subject']); ?></h2><pre><?php echo labs_e((string)$preview['text']); ?></pre><p>Preview data is illustrative. Actual recipient identity is resolved only from the active account link at delivery time.</p></section>
  </div>
</section>
<?php endif; ?>
<section class="labs-safe-note">Template rendering is plain text, strips subject line breaks, accepts only allowlisted placeholders, and never exposes provider credentials or account-link secrets.</section>
<?php labs_page_end(['section'=>'admin']); ?>
