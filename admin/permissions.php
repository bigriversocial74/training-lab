<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-account-bridge.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$ctx = tl_account_bridge_current_context();
$roles = tl_account_bridge_roles();
labs_page_start(['title' => 'Roles & Permissions | Training Lab', 'section' => 'admin', 'active' => 'admin-permissions']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-permissions'); ?>

<section class="labs-page-title labs-stage130-title">
  <div><span class="labs-eyebrow">Auth and permissions</span><h1>Role map for Training Lab accounts.</h1><p class="labs-copy">Permissions are mapped to the existing Training Lab permission catalog and can gate actions when auth enforcement is enabled.</p></div>
  <div class="labs-actions"><a class="labs-btn" href="<?php echo labs_url('/account.php'); ?>">Account Bridge</a><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/api/training/permissions.php'); ?>">Permissions JSON</a></div>
</section>
<section class="labs-kpis labs-stage130-kpis">
  <div class="labs-kpi"><span>Current Role</span><strong><?php echo labs_e((string)($ctx['user']['role'] ?? 'guest')); ?></strong><small><?php echo !empty($ctx['authenticated']) ? 'active session' : 'not logged in'; ?></small></div>
  <div class="labs-kpi"><span>Roles</span><strong><?php echo count($roles); ?></strong><small>permission profiles</small></div>
  <div class="labs-kpi"><span>Catalog</span><strong><?php echo tl_app_count('training_permission_catalog'); ?></strong><small>DB permission rows</small></div>
  <div class="labs-kpi"><span>Enforcement</span><strong><?php echo !empty($ctx['enforcement_enabled']) ? 'On' : 'Soft'; ?></strong><small>configurable gate</small></div>
</section>
<section class="labs-card">
  <div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Role</th><th>Description</th><th>Permissions</th></tr></thead><tbody>
  <?php foreach ($roles as $slug => $role): ?><tr><td><?php echo labs_e((string)$role['label']); ?></td><td><?php echo labs_e((string)$role['description']); ?></td><td><div class="labs-stage130-permission-list"><?php foreach ($role['permissions'] as $perm): ?><span><?php echo labs_e((string)$perm); ?></span><?php endforeach; ?></div></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<section class="labs-safe-note">This page defines Training Lab role behavior. It does not create production roles or mutate production permissions until Microgifter role mapping is explicitly wired.</section>
<?php labs_page_end(['section' => 'admin']); ?>
