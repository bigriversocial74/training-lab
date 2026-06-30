<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';
$tasks = tl_stage34_tasks('movement-5');
labs_page_start(['title'=>'Tasks | Training Lab','section'=>'app','active'=>'app-tasks']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('app-sequence-tasks'); ?>

<section class="labs-page-title">
  <div><span class="labs-eyebrow">Verified action sequence</span><h1>Complete the sequence before reward eligibility.</h1><p class="labs-copy">Task data is centralized through the Training Lab service layer. Use this page to follow the sequence before reward eligibility.</p></div>
  <a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/proof-upload.php'); ?>">Go to Proof Step</a>
</section>
<section class="labs-card">
  <div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Day</th><th>Action</th><th>Status</th><th>Proof</th></tr></thead><tbody>
  <?php foreach ($tasks as $task): ?>
    <tr><td>Day <?php echo (int)$task['day']; ?></td><td><?php echo htmlspecialchars($task['title']); ?></td><td><span class="labs-pill"><?php echo htmlspecialchars($task['status']); ?></span></td><td><?php echo $task['day'] === 5 ? '<span class="labs-pill" data-demo-proof-status>Not submitted</span>' : htmlspecialchars($task['proof']); ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</section>
<section class="labs-section-band"><span class="labs-eyebrow">Sequence readiness</span><h2>Sequence UI is connected to the Training Lab task model.</h2><p class="labs-copy">Next step is stronger backend validation inside the standalone Training Lab tables.</p></section>
<?php labs_page_end(['section'=>'app']); ?>
