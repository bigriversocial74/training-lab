<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage886-admin.php';

$user = tl_auth_current_user();
if (!tl_security_developer_key_valid() && !tl_auth_role_allowed($user, 'manager')) {
    http_response_code(403);
    labs_page_start(['title'=>'Account Integration | Training Lab','section'=>'admin','active'=>'admin-account-integration']);
    echo '<section class="labs-card labs-error-card"><h1>Access denied</h1><p class="labs-copy">A trusted manager or administrator account is required.</p></section>';
    labs_page_end(['section'=>'admin']);
    exit;
}
$summary = tl_stage886_admin_summary();
$currentSession = is_array($summary['current_session'] ?? null) ? $summary['current_session'] : [];
labs_page_start(['title'=>'Account Integration | Training Lab','section'=>'admin','active'=>'admin-account-integration']);
?>
<section class="labs-page-title"><div><span class="labs-eyebrow">Stage 889</span><h1>Shared Account Session Hardening</h1><p class="labs-copy">Signed Microgifter identity handoff, persistent account links, independent trusted-session lifetimes, nonce replay protection, role synchronization, and revocation diagnostics.</p></div><a class="labs-btn" href="<?php echo labs_e(labs_url('/api/training/account-integration-status.php')); ?>">View JSON</a></section>
<section class="labs-kpis">
  <div class="labs-kpi"><span class="labs-muted">Configured</span><strong><?php echo $summary['configured'] ? 'Yes' : 'No'; ?></strong><small>secret + claims</small></div>
  <div class="labs-kpi"><span class="labs-muted">Schema</span><strong><?php echo $summary['schema_ready'] ? 'Ready' : 'Import SQL'; ?></strong><small>links + nonces</small></div>
  <div class="labs-kpi"><span class="labs-muted">Active links</span><strong><?php echo (int)$summary['counts']['active']; ?></strong><small>persistent identities</small></div>
  <div class="labs-kpi"><span class="labs-muted">Current session</span><strong><?php echo !empty($currentSession['active']) ? 'Active' : 'None'; ?></strong><small>independent lifetime</small></div>
</section>
<section class="labs-card"><h2>Identity assertion contract</h2><p class="labs-copy"><strong>Issuer:</strong> <?php echo labs_e((string)$summary['issuer']); ?> &nbsp; <strong>Audience:</strong> <?php echo labs_e((string)$summary['audience']); ?> &nbsp; <strong>Maximum assertion TTL:</strong> <?php echo (int)$summary['max_ttl_seconds']; ?> seconds &nbsp; <strong>Clock skew:</strong> <?php echo (int)$summary['clock_skew_seconds']; ?> seconds</p></section>
<section class="labs-card"><h2>Trusted session policy</h2><p class="labs-copy"><strong>Absolute session TTL:</strong> <?php echo (int)$summary['session_ttl_seconds']; ?> seconds &nbsp; <strong>Idle timeout:</strong> <?php echo (int)$summary['session_idle_ttl_seconds']; ?> seconds<?php echo (int)$summary['session_idle_ttl_seconds'] === 0 ? ' (disabled)' : ''; ?></p>
<?php if (!empty($currentSession['source'])): ?><div class="labs-table-wrap"><table class="labs-table"><tbody><tr><th>Assertion expires</th><td><?php echo labs_e((string)($currentSession['assertion_expires_at'] ?? '')); ?></td></tr><tr><th>Session started</th><td><?php echo labs_e((string)($currentSession['session_started_at'] ?? '')); ?></td></tr><tr><th>Session expires</th><td><?php echo labs_e((string)($currentSession['session_expires_at'] ?? '')); ?></td></tr><tr><th>Last activity</th><td><?php echo labs_e((string)($currentSession['session_last_seen_at'] ?? '')); ?></td></tr><tr><th>Expiry state</th><td><?php echo labs_e((string)($currentSession['expiry_reason'] ?? 'active')); ?></td></tr></tbody></table></div><?php else: ?><p class="labs-copy">No signed Microgifter session is active in this browser.</p><?php endif; ?>
</section>
<section class="labs-card"><h2>Recent account links</h2><div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>User</th><th>Role</th><th>Context</th><th>Status</th><th>Last authentication</th></tr></thead><tbody>
<?php foreach ($summary['recent_links'] as $link): ?><tr><td><?php echo labs_e((string)($link['display_name'] ?: $link['email'] ?: $link['microgifter_user_id'])); ?><br><small><?php echo labs_e((string)$link['public_id']); ?></small></td><td><?php echo labs_e((string)$link['role']); ?></td><td><?php echo labs_e(trim((string)$link['merchant_context'].' '.(string)$link['organization_context'])); ?></td><td><?php echo labs_e((string)$link['link_status']); ?></td><td><?php echo labs_e((string)($link['last_authenticated_at'] ?? '')); ?></td></tr><?php endforeach; ?>
<?php if (!$summary['recent_links']): ?><tr><td colspan="5">No linked identities yet.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="labs-safe-note">Assertions remain short-lived and single-use. Account links stay active until revoked or suspended. Trusted sessions use their own absolute and idle deadlines. No passwords are copied, and no Microgifter authentication tables, payments, wallets, claims, redemptions, or rewards are changed.</section>
<?php labs_page_end(['section'=>'admin']); ?>
