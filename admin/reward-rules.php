<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-reward-management.php';
$page = ['title'=>'Reward Rules | Training Lab','section'=>'admin','active'=>'admin-reward-rules','required_role'=>'manager'];
$user = tl_product_require_page_access($page);
$campaignRef = tl_campaign_clean_ref((string)($_GET['campaign'] ?? ''));
$state = tl_reward_management_rules($user ?? [], $campaignRef);
$campaign = $state['campaign'];
labs_page_start($page);
?>
<section class="labs-product-hero">
  <article class="labs-product-hero-main"><span class="labs-product-kicker">Reward rules</span><h1>Define when training earns a reward.</h1><p>Create clear eligibility rules without changing Microgifter wallet, claim, payment, or redemption authority.</p></article>
  <aside class="labs-product-next"><div><span>Selected campaign</span><h2><?php echo $campaign ? labs_e((string)$campaign['title']) : 'No campaign selected'; ?></h2><p><?php echo count($state['rules']); ?> configured rule<?php echo count($state['rules']) === 1 ? '' : 's'; ?>.</p></div><?php if ($campaign): ?><a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/admin/reward-rule-edit.php?campaign=' . rawurlencode((string)($campaign['slug'] ?: $campaign['public_id']))), ENT_QUOTES, 'UTF-8'); ?>">Add Reward Rule</a><?php endif; ?></aside>
</section>
<section class="labs-product-card">
  <form method="get" class="labs-reward-campaign-picker"><label for="reward-campaign">Campaign</label><select id="reward-campaign" name="campaign" onchange="this.form.submit()"><?php foreach ($state['campaigns'] as $option): $ref=(string)($option['slug'] ?: $option['public_id']); ?><option value="<?php echo labs_e($ref); ?>"<?php echo $campaign && (int)$campaign['id']===(int)$option['id'] ? ' selected' : ''; ?>><?php echo labs_e((string)$option['title']); ?> · <?php echo (int)$option['active_rule_count']; ?> active</option><?php endforeach; ?></select><noscript><button class="labs-btn" type="submit">Load</button></noscript></form>
</section>
<?php if (!$campaign): ?>
<section class="labs-product-empty"><h2>Create a campaign first.</h2><p>Reward rules belong to an existing Training Lab campaign.</p><a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/admin/campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">Open Campaigns</a></section>
<?php elseif (!$state['rules']): ?>
<section class="labs-product-empty"><h2>No reward rules yet.</h2><p>Add a draft rule, preview its trigger and value, then activate it when the campaign is ready.</p><a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/admin/reward-rule-edit.php?campaign=' . rawurlencode((string)($campaign['slug'] ?: $campaign['public_id']))), ENT_QUOTES, 'UTF-8'); ?>">Create Reward Rule</a></section>
<?php else: ?>
<section class="labs-reward-rule-grid">
<?php foreach ($state['rules'] as $rule): ?>
<article class="labs-product-card labs-reward-rule-card">
  <div class="labs-product-card-head"><div><span class="labs-product-status is-<?php echo (string)$rule['status']==='active' ? 'success' : ((string)$rule['status']==='paused' ? 'warning' : 'neutral'); ?>"><?php echo labs_e(ucfirst((string)$rule['status'])); ?></span><h2><?php echo labs_e((string)$rule['reward_label']); ?></h2><p><?php echo labs_e((string)$rule['rule_name']); ?></p></div><strong class="labs-reward-value"><?php echo labs_e((string)$rule['display_value']); ?></strong></div>
  <dl class="labs-reward-rule-facts"><div><dt>Trigger</dt><dd><?php echo labs_e((string)$rule['trigger_label']); ?></dd></div><div><dt>Threshold</dt><dd><?php echo (int)$rule['threshold_count']; ?></dd></div><div><dt>Type</dt><dd><?php echo labs_e(ucwords(str_replace('_',' ',(string)$rule['reward_type']))); ?></dd></div><div><dt>Earned</dt><dd><?php echo (int)$rule['reward_event_count']; ?></dd></div></dl>
  <div class="labs-actions"><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/admin/reward-rule-edit.php?campaign=' . rawurlencode((string)($campaign['slug'] ?: $campaign['public_id'])) . '&rule=' . rawurlencode((string)$rule['public_id'])), ENT_QUOTES, 'UTF-8'); ?>">Edit</a><?php if ((string)$rule['status']!=='archived'): ?><form method="post" action="<?php echo htmlspecialchars(labs_url('/admin/reward-rule-status.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="campaign_id" value="<?php echo labs_e((string)($campaign['slug'] ?: $campaign['public_id'])); ?>"><input type="hidden" name="rule_id" value="<?php echo labs_e((string)$rule['public_id']); ?>"><?php $action=(string)$rule['status']==='active'?'pause':((string)$rule['status']==='paused'?'resume':'activate'); ?><input type="hidden" name="rule_action" value="<?php echo $action; ?>"><button class="labs-btn labs-btn-primary" type="submit"><?php echo ucfirst($action); ?></button></form><form method="post" action="<?php echo htmlspecialchars(labs_url('/admin/reward-rule-status.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="campaign_id" value="<?php echo labs_e((string)($campaign['slug'] ?: $campaign['public_id'])); ?>"><input type="hidden" name="rule_id" value="<?php echo labs_e((string)$rule['public_id']); ?>"><input type="hidden" name="rule_action" value="archive"><button class="labs-btn" type="submit">Archive</button></form><?php endif; ?></div>
</article>
<?php endforeach; ?>
</section>
<?php endif; ?>
<section class="labs-safe-note">Reward rules create Training Lab eligibility only. Microgifter delivery remains behind the existing signed, idempotent, account-linked reward bridge.</section>
<?php labs_page_end(['section'=>'admin']); ?>
