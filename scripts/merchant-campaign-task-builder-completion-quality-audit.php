<?php
$root = dirname(__DIR__);
$files = [
    'service'=>(string)file_get_contents($root . '/includes/training-lab-campaign-builder.php'),
    'page'=>(string)file_get_contents($root . '/admin/campaign-builder.php'),
    'legacy'=>(string)file_get_contents($root . '/admin/campaigns.php'),
    'action'=>(string)file_get_contents($root . '/admin/campaign-builder-action.php'),
    'security'=>(string)file_get_contents($root . '/includes/training-lab-security.php'),
    'acceptance'=>(string)file_get_contents($root . '/includes/training-lab-product-acceptance.php'),
    'gate'=>(string)file_get_contents($root . '/run-quality-gate.sh'),
    'workflow'=>(string)file_get_contents($root . '/.github/workflows/quality-gate.yml'),
    'docs'=>(string)file_get_contents($root . '/docs/MERCHANT-CAMPAIGN-TASK-BUILDER-COMPLETION-V1.md'),
];
$css = (string)file_get_contents($root . '/assets/css/campaign-builder.css');
$categories = [
    'Merchant ownership'=>[
        str_contains($files['service'], 'owner_user_id = ?'),
        str_contains($files['service'], 'campaign_builder_campaign_not_found'),
        str_contains($files['page'], "'required_role'=>'manager'"),
    ],
    'Campaign lifecycle'=>[
        str_contains($files['service'], 'tl_campaign_builder_create'),
        str_contains($files['service'], 'tl_campaign_builder_duplicate'),
        str_contains($files['service'], 'tl_campaign_builder_archive'),
        !str_contains($files['service'], 'DELETE FROM training_campaigns'),
    ],
    'Task authoring'=>[
        str_contains($files['service'], 'tl_campaign_builder_add_task'),
        str_contains($files['service'], 'tl_campaign_builder_update_task'),
        str_contains($files['service'], 'tl_campaign_builder_reorder_tasks'),
        str_contains($files['service'], 'tl_campaign_builder_delete_task'),
    ],
    'Proof and prerequisites'=>[
        str_contains($files['service'], 'proof_instructions'),
        str_contains($files['service'], 'prerequisite_task_id'),
        str_contains($files['service'], 'close_after_due'),
        str_contains($files['service'], 'training_proof_submissions WHERE task_id = ?'),
    ],
    'Schedule and cohort'=>[
        str_contains($files['service'], 'starts_at'),
        str_contains($files['service'], 'ends_at'),
        str_contains($files['service'], 'capacity'),
        str_contains($files['service'], 'enrollment_mode'),
    ],
    'Rewards and preview'=>[
        str_contains($files['service'], 'tl_campaign_builder_attach_reward'),
        str_contains($files['page'], 'Participant Preview'),
        str_contains($files['page'], 'Advanced Reward Rules'),
        str_contains($files['legacy'], '/admin/campaign-builder.php'),
    ],
    'Publish readiness'=>[
        str_contains($files['service'], 'tl_campaign_builder_readiness'),
        str_contains($files['service'], 'campaign_builder_not_ready'),
        str_contains($files['page'], 'Publish Campaign'),
    ],
    'Write security'=>[
        str_contains($files['action'], 'tl_security_guard_write'),
        str_contains($files['service'], 'beginTransaction()'),
        str_contains($files['service'], 'FOR UPDATE'),
        str_contains($files['action'], "tl_security_guard_write('update_campaign_plan'"),
    ],
    'Responsive operations'=>[
        is_file($root . '/assets/css/campaign-builder.css'),
        str_contains($css, '@media(max-width:760px)'),
        str_contains($css, '@media(forced-colors:active)'),
    ],
    'Acceptance and deployment'=>[
        str_contains($files['acceptance'], "'campaign_builder'=>'admin/campaign-builder.php'"),
        str_contains($files['gate'], 'merchant-campaign-task-builder-completion-quality-audit.php'),
        str_contains($files['workflow'], 'Merchant campaign and task builder completion scored audit'),
        str_contains($files['docs'], 'No new SQL required'),
        str_contains($files['docs'], 'Rollback'),
    ],
];
$total = 0;
foreach ($categories as $name=>$checks) {
    $passed = count(array_filter($checks));
    $score = (int)round($passed / max(1,count($checks)) * 10, 0);
    $total += $score;
    echo sprintf("%-34s %d/10 (%d/%d)\n", $name, $score, $passed, count($checks));
}
echo 'Section 19 total: ' . $total . "/100\n";
if ($total !== 100) exit(1);
echo "Section 19 source score: 10/10 in every category.\n";
