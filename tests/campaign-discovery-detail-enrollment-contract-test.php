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

$service = $read('includes/training-lab-campaign-experience.php');
$enrollment = $read('includes/training-lab-campaign-enrollment.php');
$list = $read('app/campaigns.php');
$detail = $read('app/campaign-detail.php');
$htmlEnroll = $read('app/campaign-enroll.php');
$joinApi = $read('api/training/actions/join-campaign.php');
$appApi = $read('api/training/app-action.php');
$actionResult = $read('app/action-result.php');
$bootstrap = $read('api/training/actions/_action-bootstrap.php');
$layout = $read('includes/labs-layout.php');
$css = $read('assets/css/campaign-experience.css');
$runner = $read('run-quality-gate.sh');
$workflow = $read('.github/workflows/quality-gate.yml');
$docs = $read('docs/CAMPAIGN-DISCOVERY-DETAIL-ENROLLMENT-V1.md');

$requires($service, 'tl_campaign_user_id(array $user)', 'Campaign reads must derive the trusted participant identity.');
$requires($service, 'tp.user_id=?', 'Campaign catalog and detail must scope participant state by trusted user ID.');
$requires($service, "c.visibility='published' OR tp.id IS NOT NULL OR c.owner_user_id=?", 'Campaign visibility must be published, authorized, or owner-previewed.');
$requires($service, "['available', 'mine', 'completed', 'all']", 'Campaign catalog must define supported product filters.');
$requires($service, "'mine' => !empty(\$state['enrolled'])", 'My Campaigns must require accepted enrollment.');
$requires($service, "['available', 'upcoming', 'invited']", 'Available campaigns must include active invitations.');
$requires($service, 'mb_strtolower(implode', 'Campaign search must normalize searchable text.');
$requires($service, 'tl_campaign_datetime', 'Campaign date handling must be timezone-aware.');
$requires($service, "'invited'", 'Campaign state must represent invitations.');
$requires($service, "'ended_enrolled'", 'Campaign state must preserve ended enrollments.');
$requires($service, '$completed = $participantStatus === \'completed\';', 'Completion must come from the participant record, not global campaign status.');
$forbids($service, 'tl_stage34_campaigns', 'Campaign discovery must not use fixture campaign data.');

$requires($enrollment, 'beginTransaction()', 'Enrollment must run inside a transaction.');
$requires($enrollment, 'LIMIT 1 FOR UPDATE', 'Enrollment must lock the campaign row.');
$requires($enrollment, 'campaign_id = ? AND user_id = ? LIMIT 1 FOR UPDATE', 'Enrollment must lock an existing participant row.');
$requires($enrollment, "status='invited'", 'Enrollment must support invitation acceptance.');
$requires($enrollment, 'campaign_invitation_closed', 'Expired or closed invitations must be rejected.');
$requires($enrollment, "visibility !== 'published'", 'New self-enrollment must require published visibility.');
$requires($enrollment, "['scheduled','active']", 'Enrollment must require a joinable campaign status.');
$requires($enrollment, 'tl_campaign_datetime', 'Enrollment writes must use campaign-timezone date checks.');
$requires($enrollment, "SELECT COUNT(*) FROM training_participants WHERE campaign_id=? AND status<>'removed'", 'Capacity must be rechecked in the transaction.');
$requires($enrollment, "'already_joined'=>true", 'Existing enrollment must return idempotently.');
$requires($enrollment, 'if ($pdo->inTransaction()) $pdo->rollBack();', 'Enrollment failures must roll back.');
$forbids($enrollment, "\$_GET", 'Enrollment service must not trust query parameters.');
$forbids($enrollment, "\$_POST", 'Enrollment service must not trust raw form data.');

$requires($list, "['available', 'mine', 'completed']", 'Campaign list must expose available, current, and completed views.');
$requires($list, 'aria-label="Search campaigns"', 'Campaign search must have an accessible name.');
$requires($list, 'Accept Invitation', 'Campaign cards must distinguish invitation acceptance.');
$requires($list, 'tl_security_csrf_field()', 'Campaign card enrollment must include server-rendered CSRF protection.');
$requires($list, '/app/campaign-enroll.php', 'Campaign cards must use the protected enrollment route.');
$forbids($list, 'data-demo-', 'Campaign list must not use browser demo state.');
$forbids($list, 'tl_stage34_', 'Campaign list must not use fixture services.');
$forbids($list, '/api/training/', 'Campaign list must not expose API links.');
$forbids($list, "\$_GET['user_id']", 'Campaign list must not accept browser-selected participant IDs.');

$requires($detail, 'What you will complete', 'Campaign detail must explain the task path.');
$requires($detail, 'Before you begin', 'Campaign detail must show requirements.');
$requires($detail, 'Reward eligibility is created from verified Training Lab completion records.', 'Campaign detail must explain the reward boundary.');
$requires($detail, 'Accept Invitation', 'Campaign detail must distinguish invitation acceptance.');
$requires($detail, 'role="status"', 'Campaign detail flash feedback must be announced.');
$forbids($detail, 'data-demo-', 'Campaign detail must not use browser demo state.');
$forbids($detail, 'tl_stage34_', 'Campaign detail must not use fixture services.');
$forbids($detail, '/api/training/', 'Campaign detail must not expose API links.');
$forbids($detail, 'Campaign Builder', 'Campaign detail must not expose merchant builder controls to participants.');

foreach ([
    'HTML enrollment route' => $htmlEnroll,
    'specific join API' => $joinApi,
    'generic app API' => $appApi,
    'legacy app action result' => $actionResult,
] as $label => $content) {
    $requires($content, 'tl_campaign_secure_enroll', $label . ' must use the secure enrollment service.');
}
$requires($htmlEnroll, "tl_security_guard_write('join_campaign'", 'HTML enrollment must use the central write guard.');
$requires($htmlEnroll, 'tl_product_redirect($destination, 303)', 'HTML enrollment must use a post/redirect/get response.');
$requires($joinApi, '_action-bootstrap.php', 'Specific join API must preserve the shared action route architecture.');
$requires($joinApi, 'tl_action_wrap_user', 'Specific join API must receive the trusted user.');
$requires($bootstrap, 'function tl_action_wrap_user', 'Shared action bootstrap must provide a trusted-user wrapper.');

$requires($layout, "labs_asset('css/campaign-experience.css')", 'Shared shell must load campaign experience styles.');
$requires($css, '.labs-campaign-grid', 'Campaign stylesheet must include reusable discovery cards.');
$requires($css, '.labs-campaign-detail-hero', 'Campaign stylesheet must include detail layout.');
$requires($css, '@media(max-width:760px)', 'Campaign experience must include a mobile breakpoint.');
$requires($css, '@media(max-width:460px)', 'Campaign experience must include a narrow-phone breakpoint.');

$requires($docs, 'No SQL is required.', 'Documentation must explicitly state the SQL boundary.');
$requires($docs, 'does not create a second account, merchant, role, reward, wallet, payment, claim, redemption, or gift authority', 'Documentation must preserve authority boundaries.');
$requires($docs, 'Locks the campaign row', 'Documentation must describe transactional enrollment.');
$requires($runner, 'campaign-discovery-detail-enrollment-contract-test.php', 'Local quality gate must run the campaign contract.');
$requires($runner, 'campaign-discovery-detail-enrollment-quality-audit.php', 'Local quality gate must run the campaign scored audit.');
$requires($workflow, 'Campaign discovery detail and enrollment contract', 'GitHub Actions must run the campaign contract.');
$requires($workflow, 'Campaign discovery detail and enrollment scored audit', 'GitHub Actions must run the campaign scored audit.');

require_once $root . '/includes/training-lab-campaign-experience.php';
$future = (new DateTimeImmutable('now', new DateTimeZone('America/Phoenix')))->modify('+30 days')->format('Y-m-d H:i:s');
$past = (new DateTimeImmutable('now', new DateTimeZone('America/Phoenix')))->modify('-30 days')->format('Y-m-d H:i:s');

$invited = tl_campaign_derive_state([
    'participant_id'=>10,
    'participant_status'=>'invited',
    'status'=>'active',
    'visibility'=>'private',
    'timezone'=>'America/Phoenix',
    'enrolled_count'=>1,
]);
if (($invited['key'] ?? '') !== 'invited' || empty($invited['can_join']) || !empty($invited['enrolled'])) {
    $failures[] = 'Invitation state must be visible, acceptable, and not treated as completed enrollment.';
}

$unjoinedCompletedCampaign = tl_campaign_derive_state([
    'participant_id'=>null,
    'participant_status'=>'',
    'status'=>'completed',
    'visibility'=>'published',
    'timezone'=>'America/Phoenix',
    'enrolled_count'=>0,
]);
if (($unjoinedCompletedCampaign['key'] ?? '') === 'completed') {
    $failures[] = 'Global campaign completion must not mark an unjoined participant as complete.';
}

$upcoming = tl_campaign_derive_state([
    'participant_id'=>null,
    'participant_status'=>'',
    'status'=>'scheduled',
    'visibility'=>'published',
    'timezone'=>'America/Phoenix',
    'starts_at'=>$future,
    'enrolled_count'=>0,
]);
if (($upcoming['key'] ?? '') !== 'upcoming' || empty($upcoming['can_join'])) {
    $failures[] = 'Published scheduled campaigns must allow advance enrollment.';
}

$endedEnrollment = tl_campaign_derive_state([
    'participant_id'=>11,
    'participant_status'=>'active',
    'status'=>'active',
    'visibility'=>'published',
    'timezone'=>'America/Phoenix',
    'ends_at'=>$past,
    'enrolled_count'=>1,
]);
if (($endedEnrollment['key'] ?? '') !== 'ended_enrolled' || empty($endedEnrollment['enrolled'])) {
    $failures[] = 'Ended enrollments must retain participant access without appearing active.';
}

if ($failures) {
    fwrite(STDERR, "Campaign discovery, detail, and enrollment contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Campaign discovery, detail, and enrollment contract passed.\n";
