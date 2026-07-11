<?php
$root = dirname(__DIR__);
$files = [
    'service'=>(string)file_get_contents($root . '/includes/training-lab-campaign-builder.php'),
    'runtime'=>(string)file_get_contents($root . '/includes/training-lab-campaign-builder-runtime.php'),
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
$combined = $files['service'] . $files['runtime'];
$categories = [
    'Merchant ownership'=>[
        str_contains($files['service'], 'owner_user_id = ?'),
        str_contains($files['service'], 'campaign_builder_campaign_not_found'),
        str_contains($files['runtime'], "c.owner_user_id = ?"),
        str_contains($files['page'], "'required_role'=>'manager'"),
    ],
    'Campaign lifecycle'=>[
        str_contains($files['service'], 'tl_campaign_builder_create'),
        str_contains($files['runtime'], 'tl_campaign_builder_duplicate_v2'),
        str_contains($files['service'], 'tl_campaign_builder_archive'),
        !str_contains($combined, 'DELETE FROM training_campaigns'),
    ],
    'Task authoring'=>[
        str_contains($files['runtime'], 'tl_campaign_builder_add_task_v2'),
        str_contains($files['runtime'], 'tl_campaign_builder_update_task_v2'),
        str_contains($files['service'], 'tl_campaign_builder_reorder_tasks'),
        str_contains($files['runtime'], 'tl_campaign_builder_delete_task_v2'),
    ],
    'Proof and prerequisites'=>[
        str_contains($files['runtime'], 'proof_instructions'),
        str_contains($files['runtime'], 'campaign_builder_prerequisite_order_invalid'),
        str_contains($files['runtime'], "\$settings['prerequisite_task_id'] = \$prerequisiteId"),
        str_contains($files['runtime'], 'tl_campaign_builder_clear_prerequisite_references'),
    ],
    'Schedule and cohort'=>[
        str_contains($files['service'], 'starts_at'),
        str_contains($files['service'], 'ends_at'),
        str_contains($files['service'], 'capacity'),
        str_contains($files['runtime'], "\$settings['due_at'] = \$dueAt"),
    ],
    'Rewards and preview'=>[
        str_contains($files['runtime'], 'tl_campaign_builder_attach_reward_v2'),
        str_contains($files['runtime'], 'sequence_completed'),
        str_contains($files['page'], 'Participant Preview'),
        str_contains($files['page'], 'Advanced Reward Rules'),
    ],
    'Publish readiness'=>[
        str_contains($files['service'], 'tl_campaign_builder_readiness'),
        str_contains($files['service'], 'campaign_builder_not_ready'),
        str_contains($files['runtime'], 'campaign_builder_live_task_not_ready'),
        str_contains($files['page'], 'Publish Campaign'),
    ],
    'Write security'=>[
        str_contains($files['action'], 'tl_security_guard_write'),
        substr_count($combined, 'beginTransaction()') >= 10,
        str_contains($combined, 'FOR UPDATE'),
        str_contains($files['action'], 'training-lab-campaign-builder-runtime.php'),
    ],
    'Responsive operations'=>[
        is_file($root . '/assets/css/campaign-builder.css'),
        str_contains($css, '@media(max-width:760px)'),
        str_contains($css, '@media(forced-colors:active)'),
        str_contains($files['page'], 'labs-builder-shell'),
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
