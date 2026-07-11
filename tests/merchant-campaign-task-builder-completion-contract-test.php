<?php
require_once __DIR__ . '/../includes/training-lab-campaign-builder.php';

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
$check(substr_count($service, 'beginTransaction()') >= 7, 'Campaign and task mutations must use transactions.');
$check(str_contains($service, 'FOR UPDATE'), 'Campaign and task mutations must use row locks.');
$check(str_contains($service, 'campaign_builder_archived_immutable'), 'Archived campaign immutability is required.');
$check(!str_contains($service, 'DELETE FROM training_campaigns'), 'Campaign deletion is prohibited; campaigns must be archived.');
$check(str_contains($service, "SELECT COUNT(*) FROM training_proof_submissions WHERE task_id = ?"), 'Task deletion must preserve tasks that have proof history.');
$check(str_contains($service, "UPDATE training_campaign_tasks SET status = 'archived'"), 'Referenced tasks must be archived instead of deleted.');
$check(str_contains($service, 'tl_campaign_builder_readiness'), 'Publish readiness service is required.');
$check(str_contains($service, 'campaign_builder_not_ready'), 'Publishing must fail closed when readiness checks fail.');
$check(str_contains($service, 'proof_instructions'), 'Proof-required task instructions are required.');
$check(str_contains($service, 'prerequisite_task_id'), 'Task prerequisite configuration is required.');
$check(str_contains($service, 'tl_campaign_builder_reorder_tasks'), 'Task reordering is required.');
$check(str_contains($service, 'tl_campaign_builder_duplicate'), 'Campaign duplication is required.');
$check(str_contains($service, 'tl_campaign_builder_attach_reward'), 'Reward-rule attachment is required.');
$check(str_contains($service, "'status'=>'draft'") || str_contains($service, "'status' => 'draft'"), 'New and duplicated campaigns must be drafts.');
$check(!preg_match('/\b(?:curl_|file_get_contents\s*\(\s*[\'\"]https?:|microgifter_training_reward_issue|training_lab_send_notification_email)\b/i', $service), 'Campaign Builder must not make external calls, send email, or issue rewards.');

$check(str_contains($page, 'Build the ordered participant experience'), 'Merchant task-builder UI is required.');
$check(str_contains($page, 'Publish readiness'), 'Publish-readiness UI is required.');
$check(str_contains($page, 'Participant Preview'), 'Participant preview is required.');
$check(str_contains($page, 'Duplicate as Draft'), 'Duplicate action must be visible.');
$check(str_contains($page, 'Archive Campaign'), 'Archive action must be visible.');
$check(str_contains($page, 'Attach Reward Rule'), 'Reward attachment UI is required.');
$check(str_contains($page, 'task_order'), 'Task reorder UI is required.');
$check(str_contains($page, "'required_role'=>'manager'"), 'Campaign Builder page must require manager access.');
$check(str_contains($page, 'campaign-builder.css'), 'Campaign Builder stylesheet must load on the builder page.');
$check(str_contains($legacy, '/admin/campaign-builder.php'), 'Legacy Campaigns route must forward to the completed builder.');

$check(str_contains($action, 'tl_security_request_data(false)'), 'Builder action route must parse POST body only.');
$check(str_contains($action, "tl_security_guard_write('update_campaign_plan'"), 'Builder actions must use the existing campaign-manage permission contract.');
$check(str_contains($action, "'publish_campaign'"), 'Builder action route must expose guarded publishing.');
$check(str_contains($action, "'delete_task'"), 'Builder action route must expose guarded task removal.');
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
