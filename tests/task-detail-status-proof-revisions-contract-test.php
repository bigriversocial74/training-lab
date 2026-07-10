<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $path) use ($root, &$failures): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        $failures[] = $path . ' is missing.';
        return '';
    }
    return file_get_contents($full) ?: '';
};
$requires = static function (string $content, string $needle, string $message) use (&$failures): void {
    if (!str_contains($content, $needle)) $failures[] = $message;
};
$forbids = static function (string $content, string $needle, string $message) use (&$failures): void {
    if (str_contains($content, $needle)) $failures[] = $message;
};

$experience = $read('includes/training-lab-task-experience.php');
$submission = $read('includes/training-lab-task-submission.php');
$runnerPage = $read('app/task-runner.php');
$submitPage = $read('app/task-submit.php');
$proofRedirect = $read('app/proof-upload.php');
$submitApi = $read('api/training/actions/submit-proof.php');
$appApi = $read('api/training/app-action.php');
$actionResult = $read('app/action-result.php');
$layout = $read('includes/labs-layout.php');
$css = $read('assets/css/task-experience.css');
$docs = $read('docs/TASK-DETAIL-STATUS-PROOF-REVISIONS-V1.md');
$runner = $read('run-quality-gate.sh');
$workflow = $read('.github/workflows/quality-gate.yml');

$requires($experience, 'tl_campaign_user_id($user)', 'Task reads must derive the trusted participant identity.');
$requires($experience, "tp.user_id=? AND tp.status<>'removed'", 'Task campaign access must be scoped to the trusted enrolled participant.');
$requires($experience, '$pdo->prepare($sql)', 'Task campaign access must use prepared statements.');
$requires($experience, "WHERE campaign_id=? AND status='active' ORDER BY position_no ASC,id ASC", 'Task path must be active and position ordered.');
$forbids($runnerPage, "\$_GET['user_id']", 'Task page must not accept a browser-selected user ID.');
$forbids($runnerPage, '/admin/', 'Participant task page must not link to admin surfaces.');
$forbids($runnerPage, '/api/training/', 'Participant task page must not expose API links.');
$forbids($runnerPage, 'Stage ', 'Participant task page must not expose stage terminology.');
$forbids($runnerPage, 'Build ', 'Participant task page must not expose build terminology.');

foreach (['ready','locked','overdue','in_review','needs_revision','complete','ended_enrolled'] as $statusKey) {
    if (!str_contains($experience, "'{$statusKey}'") && $statusKey !== 'ended_enrolled') {
        $failures[] = 'Task status model is missing ' . $statusKey . '.';
    }
}
$requires($experience, "'close_after_due'", 'Task state must support closed-after-due behavior.');
$requires($experience, "'due_at'", 'Task state must support task due dates.');
$requires($experience, "'deadline'", 'Task state must support deadline aliases.');
$requires($experience, 'Complete the previous task before starting this one.', 'Locked task state must explain ordered prerequisites.');
$requires($experience, 'tl_task_history', 'Task experience must provide proof and review history.');
$requires($experience, 'SELECT id,public_id,decision,review_notes,reviewed_at,created_at FROM training_reviews', 'History must include reviewer decisions and notes.');

$requires($submission, 'beginTransaction()', 'Task submission must be transactional.');
$requires($submission, 'LIMIT 1 FOR UPDATE', 'Task submission must lock enrollment and task rows.');
$requires($submission, "t.position_no<?", 'Task submission must enforce ordered prerequisites.');
$requires($submission, 'task_prerequisite_incomplete', 'Task submission must reject out-of-order completion.');
$requires($submission, "'already_complete'=>true", 'Task completion must be idempotent.');
$requires($submission, 'proof_already_in_review', 'Task submission must reject duplicate proof while review is pending.');
$requires($submission, 'revision_number', 'Proof revision metadata must include a revision number.');
$requires($submission, 'revision_of_public_id', 'Proof revision metadata must link to the previous proof.');
$requires($submission, 'replaced_by_public_id', 'Previous proof metadata must link to the replacement.');
$requires($submission, "SET status='cancelled'", 'Previous revised proof must be retained and cancelled.');
$requires($submission, 'random_bytes(32)', 'Checklist receipts must use cryptographic verification material.');
$requires($submission, 'VALUES (?,?,?,?,?,NULL,?,?,?,?)', 'Checklist receipt insert must have the correct placeholder contract.');
$requires($submission, 'INSERT INTO training_streaks', 'Checklist completion must update participant progress.');
$requires($submission, '$pdo->commit();', 'Task writes must commit before reward evaluation.');
$requires($submission, 'tl_evaluate_rewards', 'Verified checklist completion must evaluate reward eligibility.');
$requires($submission, 'if ($pdo->inTransaction()) $pdo->rollBack();', 'Task write failures must roll back.');
$forbids($submission, "\$_POST", 'Task service must not trust raw form input.');
$forbids($submission, "\$_GET", 'Task service must not trust query input.');

$requires($runnerPage, 'Current status', 'Task page must show a clear status.');
$requires($runnerPage, 'Submission history', 'Task page must show proof history.');
$requires($runnerPage, 'Reviewer feedback', 'Task page must show revision feedback.');
$requires($runnerPage, 'Submit Updated Proof', 'Task page must provide a revision action.');
$requires($runnerPage, 'Your current proof is already in review', 'Task page must explain the in-review lock.');
$requires($runnerPage, 'textarea name="proof_text"', 'Task proof form must provide a blank participant text field.');
$forbids($runnerPage, '<textarea name="proof_text" rows="7" minlength="10" maxlength="5000">I completed', 'Task proof form must not prefill a completion claim.');
$requires($runnerPage, 'Real file upload processing remains disabled.', 'Task page must disclose the upload boundary.');
$requires($runnerPage, 'aria-current="step"', 'Task path must identify the current step accessibly.');
$requires($runnerPage, 'role="status"', 'Task feedback must be announced.');

$requires($submitPage, "tl_security_guard_write('complete_task'", 'HTML task submission must use the central write guard.');
$requires($submitPage, 'tl_task_secure_submit', 'HTML task submission must use the secure task service.');
$requires($submitPage, 'tl_product_redirect($destination, 303)', 'HTML task submission must use post/redirect/get.');
$requires($submitApi, '_action-bootstrap.php', 'Proof API must preserve the shared action wrapper.');
$requires($submitApi, 'tl_action_wrap_user', 'Proof API must receive the trusted user.');
$requires($submitApi, 'tl_task_secure_submit', 'Proof API must use the secure task service.');
$requires($appApi, "in_array(\$action, ['complete_task','submit_proof'], true)", 'Generic app API must route both task actions through the secure service.');
$requires($actionResult, "in_array(\$action, ['complete_task','submit_proof'], true)", 'Legacy app action result must route both task actions through the secure service.');
$requires($proofRedirect, 'tl_product_redirect', 'Legacy proof upload must redirect to the task experience.');
$forbids($proofRedirect, '<input type="file"', 'Legacy proof route must not expose a file picker.');

$requires($layout, "labs_asset('css/task-experience.css')", 'Shared shell must load task experience styles.');
$requires($css, '.labs-task-hero', 'Task stylesheet must include the task hero.');
$requires($css, '.labs-task-history', 'Task stylesheet must include proof history.');
$requires($css, '.labs-task-path', 'Task stylesheet must include ordered path navigation.');
$requires($css, '@media(max-width:760px)', 'Task experience must include a mobile breakpoint.');
$requires($css, '@media(max-width:460px)', 'Task experience must include a narrow-phone breakpoint.');

$requires($docs, 'No SQL is required.', 'Documentation must state the SQL boundary.');
$requires($docs, 'It does not enable image, video, audio, or file upload processing.', 'Documentation must preserve the upload boundary.');
$requires($docs, 'revision_of_public_id', 'Documentation must describe revision lineage.');
$requires($docs, 'does not create a second account, role, merchant, reward, wallet, payment, claim, redemption, or gift authority', 'Documentation must preserve authority boundaries.');
$requires($runner, 'task-detail-status-proof-revisions-contract-test.php', 'Local quality gate must run the Section 3 contract.');
$requires($runner, 'task-detail-status-proof-revisions-quality-audit.php', 'Local quality gate must run the Section 3 scored audit.');
$requires($workflow, 'Task detail status and proof revisions contract', 'GitHub Actions must run the Section 3 contract.');
$requires($workflow, 'Task detail status and proof revisions scored audit', 'GitHub Actions must run the Section 3 scored audit.');

require_once $root . '/includes/training-lab-task-experience.php';
$campaign = [
    'participant_status'=>'active',
    'status'=>'active',
    'timezone'=>'America/Phoenix',
    'starts_at'=>null,
    'ends_at'=>null,
];
$task = ['settings_json'=>null];

$ready = tl_task_status_model($task, null, false, true, $campaign);
if (($ready['key'] ?? '') !== 'ready' || empty($ready['can_submit'])) $failures[] = 'A valid unlocked task must be ready and submittable.';

$locked = tl_task_status_model($task, null, false, false, $campaign);
if (($locked['key'] ?? '') !== 'locked' || !empty($locked['can_submit'])) $failures[] = 'A task with incomplete prerequisites must be locked.';

$inReview = tl_task_status_model($task, ['status'=>'submitted','latest_decision'=>''], false, true, $campaign);
if (($inReview['key'] ?? '') !== 'in_review' || !empty($inReview['can_submit'])) $failures[] = 'A submitted proof must block duplicate submissions while in review.';

$revision = tl_task_status_model($task, ['status'=>'in_review','latest_decision'=>'needs_more_info','latest_review_notes'=>'Add a clearer result.'], false, true, $campaign);
if (($revision['key'] ?? '') !== 'needs_revision' || empty($revision['can_submit'])) $failures[] = 'Needs-more-info proof must allow a revision.';

$complete = tl_task_status_model($task, ['status'=>'approved','latest_decision'=>'approved'], false, true, $campaign);
if (($complete['key'] ?? '') !== 'complete' || !empty($complete['can_submit'])) $failures[] = 'Approved proof must be complete and idempotent.';

$pastDue = (new DateTimeImmutable('now', new DateTimeZone('America/Phoenix')))->modify('-1 day')->format('Y-m-d H:i:s');
$overdue = tl_task_status_model(['settings_json'=>json_encode(['due_at'=>$pastDue])], null, false, true, $campaign);
if (($overdue['key'] ?? '') !== 'overdue' || empty($overdue['can_submit'])) $failures[] = 'An overdue task must remain submittable unless explicitly closed.';

$closed = tl_task_status_model(['settings_json'=>json_encode(['due_at'=>$pastDue,'close_after_due'=>true])], null, false, true, $campaign);
if (($closed['key'] ?? '') !== 'locked' || !empty($closed['can_submit'])) $failures[] = 'A task configured to close after its due date must be locked.';

if ($failures) {
    fwrite(STDERR, "Task detail, status, and proof revisions contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Task detail, status, and proof revisions contract passed.\n";
