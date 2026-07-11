<?php
require_once __DIR__ . '/../includes/training-lab-campaign-builder-runtime.php';

$root = dirname(__DIR__);
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static function (string $path) use ($root, $check): string {
    $full = $root . '/' . $path;
    $check(is_file($full), 'Missing required file: ' . $path);
    return is_file($full) ? (string)file_get_contents($full) : '';
};

$service = $read('includes/training-lab-campaign-builder.php');
$runtime = $read('includes/training-lab-campaign-builder-runtime.php');
$page = $read('admin/campaign-builder.php');
$legacy = $read('admin/campaigns.php');
$action = $read('admin/campaign-builder-action.php');
$css = $read('assets/css/campaign-builder.css');
$security = $read('includes/training-lab-security.php');
$shell = $read('includes/training-lab-product-shell.php');
$acceptance = $read('includes/training-lab-product-acceptance.php');
$gate = $read('run-quality-gate.sh');
$workflow = $read('.github/workflows/quality-gate.yml');
$docs = $read('docs/MERCHANT-CAMPAIGN-TASK-BUILDER-COMPLETION-V1.md');

$check(str_contains($service, "owner_user_id = ?"), 'Manager campaign reads must enforce owner_user_id scope.');
$check(str_contains($service, "INNER JOIN training_campaigns c ON c.id = t.campaign_id"), 'Task writes must resolve ownership through the campaign.');
$check(substr_count($service . $runtime, 'beginTransaction()') >= 10, 'Campaign and task mutations must use transactions.');
$check(str_contains($service . $runtime, 'FOR UPDATE'), 'Campaign and task mutations must use row locks.');
$check(str_contains($service . $runtime, 'campaign_builder_archived_immutable'), 'Archived campaign immutability is required.');
$check(!str_contains($service . $runtime, 'DELETE FROM training_campaigns'), 'Campaign deletion is prohibited; campaigns must be archived.');
$check(str_contains($runtime, "SELECT COUNT(*) FROM training_proof_submissions WHERE task_id = ?"), 'Task deletion must preserve tasks that have proof history.');
$check(str_contains($runtime, "UPDATE training_campaign_tasks SET status = 'archived'"), 'Referenced tasks must be archived instead of deleted.');
$check(str_contains($service, 'tl_campaign_builder_readiness'), 'Publish readiness service is required.');
$check(str_contains($service, 'campaign_builder_not_ready'), 'Publishing must fail closed when readiness checks fail.');
$check(str_contains($service . $runtime, 'proof_instructions'), 'Proof-required task instructions are required.');
$check(str_contains($runtime, 'campaign_builder_prerequisite_order_invalid'), 'Prerequisites must be restricted to earlier tasks.');
$check(str_contains($runtime, "(int)\$prerequisite['campaign_id'] !== \$campaignId"), 'Prerequisites must be restricted to the same campaign.');
$check(str_contains($runtime, "\$settings['due_at'] = \$dueAt"), 'Builder due dates must use the participant task-engine setting contract.');
$check(str_contains($runtime, "\$settings['close_after_due'] = \$closeAfterDue"), 'Close-after-due must use the participant task-engine setting contract.');
$check(str_contains($runtime, "\$settings['prerequisite_task_id'] = \$prerequisiteId"), 'Prerequisite metadata must be available at the shared task setting level.');
$check(str_contains($runtime, 'tl_campaign_builder_clear_prerequisite_references'), 'Task removal must clear dependent prerequisite references.');
$check(str_contains($service, 'tl_campaign_builder_reorder_tasks'), 'Task reordering is required.');
$check(str_contains($runtime, 'tl_campaign_builder_duplicate_v2'), 'Campaign duplication must preserve builder task metadata.');
$check(str_contains($runtime, "if (\$requestedTrigger === 'campaign_completion') \$requestedTrigger = 'sequence_completed'"), 'Campaign-completion rewards must normalize to the established sequence_completed trigger.');
$check(str_contains($runtime, "['action_count','sequence_completed','streak_days','manual']"), 'Reward trigger values must match the established reward schema.');
$check(str_contains($service, "'status'=>'draft'") || str_contains($service, "'status' => 'draft'"), 'New and duplicated campaigns must be drafts.');
$check(!preg_match('/\b(?:curl_|file_get_contents\s*\(\s*[\'\"]https?:|microgifter_training_reward_issue|training_lab_send_notification_email)\b/i', $service . $runtime), 'Campaign Builder must not make external calls, send email, or issue rewards.');

$check(str_contains($page, 'Build the ordered participant experience'), 'Merchant task-builder UI is required.');
$check(str_contains($page, 'Publish readiness'), 'Publish-readiness UI is required.');
$check(str_contains($page, 'Participant Preview'), 'Participant preview is required.');
$check(str_contains($page, 'Duplicate as Draft'), 'Duplicate action must be visible.');
$check(str_contains($page, 'Archive Campaign'), 'Archive action must be visible.');
$check(str_contains($page, 'Attach Reward Rule'), 'Reward attachment UI is required.');
$check(str_contains($page, 'prerequisite_task_id'), 'Task prerequisite editor is required.');
$check(str_contains($page, 'task_order'), 'Task reorder UI is required.');
$check(str_contains($page, "'required_role'=>'manager'"), 'Campaign Builder page must require manager access.');
$check(str_contains($page, 'campaign-builder.css'), 'Campaign Builder stylesheet must load on the builder page.');
$check(str_contains($legacy, '/admin/campaign-builder.php'), 'Legacy Campaigns route must forward to the completed builder.');

$check(str_contains($action, 'training-lab-campaign-builder-runtime.php'), 'Builder action route must load runtime hardening.');
$check(str_contains($action, 'tl_security_request_data(false)'), 'Builder action route must parse POST body only.');
$check(str_contains($action, "tl_security_guard_write('update_campaign_plan'"), 'Builder actions must use the existing campaign-manage permission contract.');
$check(str_contains($action, 'tl_campaign_builder_add_task_v2'), 'Task creation must use the hardened runtime.');
$check(str_contains($action, 'tl_campaign_builder_update_task_v2'), 'Task updates must use the hardened runtime.');
$check(str_contains($action, 'tl_campaign_builder_delete_task_v2'), 'Task removal must use the hardened runtime.');
$check(str_contains($action, 'tl_campaign_builder_attach_reward_v2'), 'Reward attachment must use the established-schema runtime.');
$check(str_contains($action, "'publish_campaign'"), 'Builder action route must expose guarded publishing.');
$check(str_contains($action, '303'), 'Builder writes must use Post/Redirect/Get.');

$order = tl_campaign_builder_normalize_task_order('4, 2,4, 7');
$check($order === [4,2,7], 'Task-order normalization must remove duplicates while preserving order.');
$check(str_contains($security, "'update_campaign_plan'=>'training.campaign.manage'"), 'Existing campaign-manage permission mapping must remain active.');
$check(str_contains($shell, "'/admin/campaigns.php', 'Campaigns'"), 'Manager navigation must retain the Campaigns destination.');
$check(str_contains($acceptance, "'campaign_builder'=>'admin/campaign-builder.php'"), 'Canonical acceptance must require the Campaign Builder route.');
$check(str_contains($acceptance, "'campaign_builder'=>'includes/training-lab-campaign-builder.php'"), 'Canonical acceptance must require the Campaign Builder service.');
$check(str_contains($gate, 'merchant-campaign-task-builder-completion-contract-test.php'), 'Local quality gate must run the Section 19 contract.');
$check(str_contains($workflow, 'Merchant campaign and task builder completion contract'), 'PHP matrix must run the Section 19 contract.');
$check(str_contains($docs, 'No new SQL required'), 'Section 19 deployment guide must state the SQL requirement.');
$check(str_contains($docs, 'Rollback'), 'Section 19 deployment guide must include rollback.');
$check(str_contains($css, '@media(max-width:760px)'), 'Campaign Builder must include phone reflow.');
$check(str_contains($css, '@media(forced-colors:active)'), 'Campaign Builder must support forced colors.');

if ($failures) {
    fwrite(STDERR, "Merchant Campaign + Task Builder contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Merchant Campaign + Task Builder completion contract passed.\n";
