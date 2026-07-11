<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$has = static fn(string $path,string $needle): bool => str_contains($read($path),$needle);
$lacks = static fn(string $path,string $needle): bool => !str_contains($read($path),$needle);
$exists = static fn(string $path): bool => is_file($root . '/' . $path);

$sections = [
    'Additive run and evidence schema' => [
        $has('database/limited_email_pilot_graduation_v1.sql','training_notification_pilot_runs'),
        $has('database/limited_email_pilot_graduation_v1.sql','training_notification_pilot_members'),
        $has('database/limited_email_pilot_graduation_v1.sql','training_notification_pilot_checks'),
        $has('database/limited_email_pilot_graduation_v1.sql','training_notification_pilot_events'),
    ],
    'Bounded cohort and batch controls' => [
        $has('includes/training-lab-limited-email-pilot.php',"'maximum_cohort'=>"),
        $has('includes/training-lab-limited-email-pilot.php',"'maximum_batch'=>"),
        $has('includes/training-lab-limited-email-pilot.php',"Only one limited live email pilot may be open"),
        $has('bin/limited-email-pilot-worker.php','max(1,min(3'),
    ],
    'Canary and webhook confirmation' => [
        $has('includes/training-lab-limited-email-pilot.php',"'pilot_canary','pilot_canary'"),
        $has('includes/training-lab-limited-email-pilot.php','tl_resend_readiness'),
        $has('includes/training-lab-limited-email-pilot.php','training_notification_provider_states WHERE outbox_id=?'),
        $has('includes/training-lab-limited-email-pilot.php',"canary_status='delivered'"),
    ],
    'Approval and worker isolation' => [
        $has('includes/training-lab-limited-email-pilot.php','tl_limited_email_pilot_approval_checks'),
        $has('includes/training-lab-limited-email-pilot.php','general_notification_worker_must_remain_disabled'),
        $has('includes/training-lab-limited-email-pilot.php',"pm.member_status='active'"),
        $has('docs/LIMITED-LIVE-EMAIL-PILOT-GRADUATION-V1.md','Do not schedule `bin/notification-worker.php`'),
    ],
    'Health metrics and automatic pause' => [
        $has('includes/training-lab-limited-email-pilot.php','missing_webhook'),
        $has('includes/training-lab-limited-email-pilot.php','stale_delays'),
        $has('includes/training-lab-limited-email-pilot.php','tl_limited_email_pilot_threshold_breaches'),
        $has('includes/training-lab-limited-email-pilot.php','tl_limited_email_pilot_auto_pause'),
    ],
    'Graduation and rejection' => [
        $has('includes/training-lab-limited-email-pilot.php','tl_limited_email_pilot_graduation_checks'),
        $has('includes/training-lab-limited-email-pilot.php',"run_status='graduated'"),
        $has('includes/training-lab-limited-email-pilot.php',"run_status='rejected'"),
        $has('includes/training-lab-limited-email-pilot.php','tl_limited_email_pilot_persist_checks'),
    ],
    'Administrator operations' => [
        $has('admin/email-pilot.php',"'required_role'=>'admin'"),
        $has('admin/email-pilot-action.php',"tl_security_guard_write('manage_limited_email_pilot'"),
        $has('admin/email-pilot.php','Process Next Bounded Batch'),
        $has('includes/training-lab-product-shell.php','admin-email-pilot'),
    ],
    'Disabled-first deployment' => [
        $has('labs/config-example.php',"'limited_email_pilot_enabled' => false"),
        $has('labs/config-example.php',"'limited_email_pilot_processing_enabled' => false"),
        $has('docs/LIMITED-LIVE-EMAIL-PILOT-GRADUATION-V1.md','Safe activation order'),
        $has('docs/LIMITED-LIVE-EMAIL-PILOT-GRADUATION-V1.md','## Emergency stop'),
    ],
    'Acceptance and CI integration' => [
        $exists('tests/limited-live-email-pilot-graduation-contract-test.php'),
        $exists('scripts/limited-live-email-pilot-graduation-quality-audit.php'),
        $has('run-quality-gate.sh','limited-live-email-pilot-graduation-contract-test.php'),
        $has('.github/workflows/quality-gate.yml','Limited live email pilot and graduation contract'),
    ],
    'Privacy and authority boundaries' => [
        $lacks('includes/training-lab-limited-email-pilot.php','curl_init('),
        !preg_match('/\bmail\s*\(/i',$read('includes/training-lab-limited-email-pilot.php')),
        $lacks('includes/training-lab-limited-email-pilot.php','wallet_balance'),
        $has('docs/LIMITED-LIVE-EMAIL-PILOT-GRADUATION-V1.md','does not enable unrestricted delivery'),
    ],
];

$failed = false;
echo "Limited Live Email Pilot + Graduation quality audit\n";
foreach ($sections as $name=>$checks) {
    $passed = count(array_filter($checks));
    $total = count($checks);
    $score = round(($passed/$total)*10,1);
    echo sprintf("%-39s %s/10 (%d/%d)\n",$name,number_format($score,$score===10.0?0:1),$passed,$total);
    if ($passed!==$total) $failed = true;
}
exit($failed?1:0);
