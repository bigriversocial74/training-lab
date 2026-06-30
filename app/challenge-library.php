<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$state = tl_stage45_challenge_library_state();
labs_page_start(['title' => 'Challenge Library | Training Lab', 'section' => 'app', 'active' => 'app-challenge-library']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('app-challenge-library'); ?>
<?php if (function_exists('tl_stage760_render_offer_preview_experience')) tl_stage760_render_offer_preview_experience((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>


<?php if (function_exists('tl_stage720_render_challenge_template_selection')) tl_stage720_render_challenge_template_selection(); ?>

<section class="labs-page-title labs-stage45-title"><div><span class="labs-eyebrow">Challenge library</span><h1>Start from reusable Training Lab challenge templates.</h1><p class="labs-copy">Create complete standalone campaigns with task sequences and simulated reward eligibility rules from documented templates.</p></div><div class="labs-actions"><a class="labs-btn" href="<?php echo labs_url('/app/campaign-builder.php'); ?>">Custom Builder</a><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/scenario-runner.php'); ?>">Scenario Runner</a></div></section>
<section class="labs-stage45-template-grid"><?php foreach ($state['templates'] as $id => $template): ?><article class="labs-card"><span class="labs-eyebrow"><?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?></span><h2><?php echo htmlspecialchars((string)$template['label'], ENT_QUOTES, 'UTF-8'); ?></h2><p class="labs-muted"><?php echo htmlspecialchars((string)$template['summary'], ENT_QUOTES, 'UTF-8'); ?></p><div class="labs-stage45-task-mini"><?php foreach ($template['tasks'] as $i => $task): ?><div><span><?php echo $i + 1; ?></span><?php echo htmlspecialchars((string)$task['title'], ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div><form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="create_challenge_template"><input type="hidden" name="template_id" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>"><label>Campaign title<input type="text" name="title" value="<?php echo htmlspecialchars((string)$template['title'], ENT_QUOTES, 'UTF-8'); ?>"></label><button class="labs-btn labs-btn-primary" type="submit">Create Challenge</button></form></article><?php endforeach; ?></section>
<section class="labs-safe-note">Challenge Library creates Training Lab campaigns, tasks, and reward rules only. It does not touch production campaign, payment, wallet, claim, or reward issuing systems.</section>
<?php labs_page_end(['section' => 'app']); ?>
