<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';
$wallet = tl_stage34_wallet();
labs_page_start(['title'=>'Wallet | Training Lab','section'=>'app','active'=>'app-wallet']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('app-wallet'); ?>

<section class="labs-page-title"><div><span class="labs-eyebrow">Wallet preview</span><h1>Training rewards linked, not issued.</h1><p class="labs-copy">The wallet page previews how Training Lab reward events will map into the existing Microgifter wallet later. No wallet balance changes happen here.</p></div><button class="labs-btn" type="button" data-demo-action="reset-demo">Reset Demo State</button></section>
<section class="labs-card">
  <div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Reward</th><th>Source</th><th>Status</th><th>Boundary</th></tr></thead><tbody>
  <?php foreach ($wallet as $item): ?>
    <tr><td><?php echo htmlspecialchars($item['label']); ?></td><td><?php echo htmlspecialchars($item['source']); ?></td><td><?php echo $item['label']==='Movement Milestone'?'<span class="labs-pill" data-demo-reward-status>Pending</span>':'<span class="labs-pill">'.htmlspecialchars($item['status']).'</span>'; ?></td><td>No claim/redeem or balance write</td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</section>
<section class="labs-flow-grid">
  <article class="labs-card"><h2>Wallet integration contract</h2><p class="labs-muted">Training Lab creates eligibility and reward-event records later. The existing Microgifter wallet remains the owner of reward display, claim status, and redemption logic.</p><a class="labs-btn" href="<?php echo labs_url('/api/training/wallet-preview.php'); ?>">View JSON Contract</a></article>
  <article class="labs-card"><h2>Last demo update</h2><p class="labs-muted" data-demo-updated-at>Not updated yet</p><p class="labs-muted">This timestamp comes from localStorage only.</p></article>
</section>
<?php labs_page_end(['section'=>'app']); ?>
