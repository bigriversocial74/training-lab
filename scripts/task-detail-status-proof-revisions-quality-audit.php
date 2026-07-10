<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [];
$load = static function (string $path) use ($root, &$files): string {
    if (!array_key_exists($path, $files)) $files[$path] = is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
    return $files[$path];
};
$has = static fn(string $path, string $needle): bool => str_contains($load($path), $needle);
$lacks = static fn(string $path, string $needle): bool => !str_contains($load($path), $needle);
$exists = static fn(string $path): bool => is_file($root . '/' . $path);

$sections = [
    'Trusted identity' => [
        $has('includes/training-lab-task-experience.php', 'tl_campaign_user_id($user)'),
        $has('includes/training-lab-task-experience.php', "tp.user_id=? AND tp.status<>'removed'"),
        $has('includes/training-lab-task-submission.php', 'tl_campaign_user_id($user)'),
        $lacks('app/task-runner.php', "\$_GET['user_id']"),
        $lacks('includes/training-lab-task-submission.php', "\$_POST"),
    ],
    'Task detail' => [
        $has('app/task-runner.php', 'Current status'),
        $has('app/task-runner.php', 'Task path'),
        $has('app/task-runner.php', 'Campaign progress'),
        $has('app/task-runner.php', 'Previous Task'),
        $has('app/task-runner.php', 'Next Task'),
        $lacks('app/task-runner.php', '/admin/'),
        $lacks('app/task-runner.php', '/api/training/'),
    ],
    'Ordered prerequisites' => [
        $has('includes/training-lab-task-experience.php', "ORDER BY position_no ASC,id ASC"),
        $has('includes/training-lab-task-submission.php', 't.position_no<?'),
        $has('includes/training-lab-task-submission.php', 'task_prerequisite_incomplete'),
        $has('includes/training-lab-task-submission.php', 'Complete the previous task before starting this one.'),
        $has('includes/training-lab-task-submission.php', 'FOR UPDATE'),
    ],
    'Status model' => [
        $has('includes/training-lab-task-experience.php', "'ready'"),
        $has('includes/training-lab-task-experience.php', "'locked'"),
        $has('includes/training-lab-task-experience.php', "'overdue'"),
        $has('includes/training-lab-task-experience.php', "'in_review'"),
        $has('includes/training-lab-task-experience.php', "'needs_revision'"),
        $has('includes/training-lab-task-experience.php', "'complete'"),
        $has('includes/training-lab-task-experience.php', "'close_after_due'"),
    ],
    'Proof submission' => [
        $has('app/task-runner.php', 'textarea name="proof_text"'),
        $has('app/task-runner.php', 'Supporting link'),
        $has('includes/training-lab-task-submission.php', 'mb_strlen($proofText) < 10'),
        $has('includes/training-lab-task-submission.php', 'tl_action_external_url'),
        $has('includes/training-lab-task-submission.php', "'proof_already_in_review'"),
        $lacks('app/task-runner.php', '<input type="file"'),
        $lacks('app/task-runner.php', '>I completed '),
    ],
    'Proof revisions' => [
        $has('includes/training-lab-task-submission.php', "'revision_number'"),
        $has('includes/training-lab-task-submission.php', "'revision_of_public_id'"),
        $has('includes/training-lab-task-submission.php', "'replaced_by_public_id'"),
        $has('includes/training-lab-task-submission.php', "SET status='cancelled'"),
        $has('includes/training-lab-task-experience.php', 'tl_task_history'),
        $has('app/task-runner.php', 'Reviewer feedback'),
        $has('app/task-runner.php', 'Submit Updated Proof'),
    ],
    'Transaction idempotency' => [
        $has('includes/training-lab-task-submission.php', 'beginTransaction()'),
        $has('includes/training-lab-task-submission.php', "'already_complete'=>true"),
        $has('includes/training-lab-task-submission.php', 'VALUES (?,?,?,?,?,NULL,?,?,?,?)'),
        $has('includes/training-lab-task-submission.php', 'random_bytes(32)'),
        $has('includes/training-lab-task-submission.php', 'INSERT INTO training_streaks'),
        $has('includes/training-lab-task-submission.php', 'if ($pdo->inTransaction()) $pdo->rollBack();'),
        $has('includes/training-lab-task-submission.php', 'tl_evaluate_rewards'),
    ],
    'Protected routes' => [
        $has('app/task-submit.php', "tl_security_guard_write('complete_task'"),
        $has('app/task-submit.php', 'tl_task_secure_submit'),
        $has('api/training/actions/submit-proof.php', 'tl_action_wrap_user'),
        $has('api/training/actions/submit-proof.php', 'tl_task_secure_submit'),
        $has('api/training/app-action.php', "['complete_task','submit_proof']"),
        $has('app/action-result.php', "['complete_task','submit_proof']"),
        $has('app/proof-upload.php', 'tl_product_redirect'),
    ],
    'Responsive accessibility' => [
        $exists('assets/css/task-experience.css'),
        $has('includes/labs-layout.php', "labs_asset('css/task-experience.css')"),
        $has('assets/css/task-experience.css', '.labs-task-hero'),
        $has('assets/css/task-experience.css', '.labs-task-history'),
        $has('assets/css/task-experience.css', '@media(max-width:760px)'),
        $has('assets/css/task-experience.css', '@media(max-width:460px)'),
        $has('app/task-runner.php', 'aria-current="step"'),
        $has('app/task-runner.php', 'role="status"'),
    ],
    'Product language' => [
        $lacks('app/task-runner.php', 'Stage '),
        $lacks('app/task-runner.php', 'Build '),
        $lacks('app/task-runner.php', 'engine readiness'),
        $lacks('app/task-runner.php', 'Review Queue'),
        $lacks('app/task-runner.php', 'User ID'),
        $has('app/task-runner.php', 'Real file upload processing remains disabled.'),
    ],
    'Acceptance and boundaries' => [
        $exists('tests/task-detail-status-proof-revisions-contract-test.php'),
        $exists('docs/TASK-DETAIL-STATUS-PROOF-REVISIONS-V1.md'),
        $has('docs/TASK-DETAIL-STATUS-PROOF-REVISIONS-V1.md', 'No SQL is required.'),
        $has('docs/TASK-DETAIL-STATUS-PROOF-REVISIONS-V1.md', 'It does not enable image, video, audio, or file upload processing.'),
        $has('docs/TASK-DETAIL-STATUS-PROOF-REVISIONS-V1.md', 'does not create a second account, role, merchant, reward, wallet, payment, claim, redemption, or gift authority'),
        $has('run-quality-gate.sh', 'task-detail-status-proof-revisions-contract-test.php'),
        $has('.github/workflows/quality-gate.yml', 'Task detail status and proof revisions contract'),
    ],
];

$failed = false;
echo "Task Detail, Status + Proof Revisions quality audit\n";
foreach ($sections as $name => $checks) {
    $passed = count(array_filter($checks));
    $total = count($checks);
    $score = $total > 0 ? round(($passed / $total) * 10, 1) : 0;
    $display = number_format($score, $score === 10.0 ? 0 : 1);
    echo sprintf("%-30s %s/10 (%d/%d checks)\n", $name, $display, $passed, $total);
    if ($passed !== $total) $failed = true;
}

if ($failed) {
    fwrite(STDERR, "Task experience has not reached 10/10 in every category.\n");
    exit(1);
}

echo "Every Task Detail, Status + Proof Revisions section scored 10/10.\n";
