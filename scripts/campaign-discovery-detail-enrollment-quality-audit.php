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
        $has('includes/training-lab-campaign-experience.php', 'tl_campaign_user_id(array $user)'),
        $has('includes/training-lab-campaign-experience.php', 'tp.user_id=?'),
        $lacks('app/campaigns.php', "\$_GET['user_id']"),
        $lacks('app/campaign-detail.php', "\$_GET['user_id']"),
        $lacks('includes/training-lab-campaign-enrollment.php', "\$_POST"),
    ],
    'Catalog visibility' => [
        $has('includes/training-lab-campaign-experience.php', "c.visibility='published' OR tp.id IS NOT NULL OR c.owner_user_id=?"),
        $has('includes/training-lab-campaign-experience.php', '$stmt = $pdo->prepare($sql)'),
        $has('includes/training-lab-campaign-experience.php', "LEFT JOIN training_participants tp ON tp.campaign_id=c.id AND tp.user_id=?"),
        $lacks('includes/training-lab-campaign-experience.php', 'tl_stage34_campaigns'),
    ],
    'Discovery filters' => [
        $has('app/campaigns.php', "['available', 'mine', 'completed']"),
        $has('app/campaigns.php', 'aria-label="Search campaigns"'),
        $has('includes/training-lab-campaign-experience.php', "'mine' => !empty(\$state['enrolled'])"),
        $has('includes/training-lab-campaign-experience.php', "['available', 'upcoming', 'invited']"),
        $has('includes/training-lab-campaign-experience.php', 'mb_strtolower(implode'),
    ],
    'Campaign detail' => [
        $has('app/campaign-detail.php', 'What you will complete'),
        $has('app/campaign-detail.php', 'Before you begin'),
        $has('app/campaign-detail.php', 'Reward eligibility is created from verified Training Lab completion records.'),
        $has('app/campaign-detail.php', 'Participation'),
        $lacks('app/campaign-detail.php', 'Campaign Builder'),
        $lacks('app/campaign-detail.php', '/api/training/'),
    ],
    'Enrollment eligibility' => [
        $has('includes/training-lab-campaign-enrollment.php', "visibility !== 'published'"),
        $has('includes/training-lab-campaign-enrollment.php', "['scheduled','active']"),
        $has('includes/training-lab-campaign-enrollment.php', 'tl_campaign_datetime'),
        $has('includes/training-lab-campaign-enrollment.php', 'campaign_invitation_closed'),
        $has('includes/training-lab-campaign-enrollment.php', 'campaign_full'),
    ],
    'Transaction idempotency' => [
        $has('includes/training-lab-campaign-enrollment.php', 'beginTransaction()'),
        $has('includes/training-lab-campaign-enrollment.php', 'LIMIT 1 FOR UPDATE'),
        $has('includes/training-lab-campaign-enrollment.php', "'already_joined'=>true"),
        $has('includes/training-lab-campaign-enrollment.php', 'if ($pdo->inTransaction()) $pdo->rollBack();'),
        $has('includes/training-lab-campaign-enrollment.php', 'INSERT IGNORE INTO training_streaks'),
    ],
    'Invitation states' => [
        $has('includes/training-lab-campaign-experience.php', "'invited'"),
        $has('includes/training-lab-campaign-experience.php', "'ended_enrolled'"),
        $has('includes/training-lab-campaign-experience.php', '$completed = $participantStatus === \'completed\';'),
        $has('app/campaigns.php', 'Accept Invitation'),
        $has('app/campaign-detail.php', 'Accept Invitation'),
        $has('includes/training-lab-campaign-enrollment.php', 'campaign_invitation_accepted'),
    ],
    'Protected routes' => [
        $has('app/campaign-enroll.php', "tl_security_guard_write('join_campaign'"),
        $has('app/campaign-enroll.php', 'tl_campaign_secure_enroll'),
        $has('api/training/actions/join-campaign.php', 'tl_action_wrap_user'),
        $has('api/training/app-action.php', "if (\$action === 'join_campaign')"),
        $has('app/action-result.php', "if (\$action === 'join_campaign')"),
        $has('api/training/actions/_action-bootstrap.php', 'function tl_action_wrap_user'),
    ],
    'Responsive accessibility' => [
        $exists('assets/css/campaign-experience.css'),
        $has('assets/css/campaign-experience.css', '.labs-campaign-grid'),
        $has('assets/css/campaign-experience.css', '.labs-campaign-detail-hero'),
        $has('assets/css/campaign-experience.css', '@media(max-width:760px)'),
        $has('assets/css/campaign-experience.css', '@media(max-width:460px)'),
        $has('app/campaigns.php', 'aria-live="polite"'),
        $has('app/campaign-detail.php', 'role="status"'),
    ],
    'Product language' => [
        $lacks('app/campaigns.php', 'data-demo-'),
        $lacks('app/campaign-detail.php', 'data-demo-'),
        $lacks('app/campaigns.php', 'Stage '),
        $lacks('app/campaign-detail.php', 'Stage '),
        $lacks('app/campaigns.php', 'JSON Contract'),
        $lacks('app/campaign-detail.php', 'integration contract'),
    ],
    'Acceptance and boundaries' => [
        $exists('tests/campaign-discovery-detail-enrollment-contract-test.php'),
        $exists('docs/CAMPAIGN-DISCOVERY-DETAIL-ENROLLMENT-V1.md'),
        $has('docs/CAMPAIGN-DISCOVERY-DETAIL-ENROLLMENT-V1.md', 'No SQL is required.'),
        $has('docs/CAMPAIGN-DISCOVERY-DETAIL-ENROLLMENT-V1.md', 'does not create a second account, merchant, role, reward, wallet, payment, claim, redemption, or gift authority'),
        $has('run-quality-gate.sh', 'campaign-discovery-detail-enrollment-contract-test.php'),
        $has('.github/workflows/quality-gate.yml', 'Campaign discovery detail and enrollment contract'),
    ],
];

$failed = false;
echo "Campaign Discovery, Detail + Enrollment quality audit\n";
foreach ($sections as $name => $checks) {
    $passed = count(array_filter($checks));
    $total = count($checks);
    $score = $total > 0 ? round(($passed / $total) * 10, 1) : 0;
    $display = number_format($score, $score === 10.0 ? 0 : 1);
    echo sprintf("%-30s %s/10 (%d/%d checks)\n", $name, $display, $passed, $total);
    if ($passed !== $total) $failed = true;
}

if ($failed) {
    fwrite(STDERR, "Campaign experience has not reached 10/10 in every category.\n");
    exit(1);
}

echo "Every Campaign Discovery, Detail + Enrollment section scored 10/10.\n";
