<?php
declare(strict_types=1);
$root = dirname(__DIR__);
putenv('TL_LIMITED_EMAIL_PILOT_ENABLED=false');
putenv('TL_LIMITED_EMAIL_PILOT_PROCESSING_ENABLED=false');
require_once $root . '/includes/training-lab-limited-email-pilot.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void { if (!$condition) $failures[] = $message; };
$read = static function (string $path) use ($root, &$failures): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) { $failures[] = $path . ' is missing.'; return ''; }
    return file_get_contents($full) ?: '';
};

$sql = $read('database/limited_email_pilot_graduation_v1.sql');
$service = $read('includes/training-lab-limited-email-pilot.php');
$page = $read('admin/email-pilot.php');
$action = $read('admin/email-pilot-action.php');
$worker = $read('bin/limited-email-pilot-worker.php');
$checkCli = $read('bin/limited-email-pilot-check.php');
$config = $read('labs/config-example.php');
$nav = $read('includes/training-lab-product-shell.php');
$acceptance = $read('includes/training-lab-product-acceptance.php');
$docs = $read('docs/LIMITED-LIVE-EMAIL-PILOT-GRADUATION-V1.md');

$assert(str_contains($sql,'CREATE TABLE IF NOT EXISTS training_notification_pilot_runs'), 'Pilot run table must be additive.');
$assert(str_contains($sql,'CREATE TABLE IF NOT EXISTS training_notification_pilot_members'), 'Pilot member table must be additive.');
$assert(str_contains($sql,'CREATE TABLE IF NOT EXISTS training_notification_pilot_checks'), 'Pilot check table must be additive.');
$assert(str_contains($sql,'CREATE TABLE IF NOT EXISTS training_notification_pilot_events'), 'Pilot event table must be additive.');
$assert(!str_contains($sql,'ALTER TABLE users') && !str_contains($sql,'ALTER TABLE wallets'), 'Section 18 must not alter Microgifter authority tables.');

$configuration = tl_limited_email_pilot_config();
$assert($configuration['enabled'] === false && $configuration['processing_enabled'] === false, 'Both Section 18 gates must default to disabled.');
$assert((int)$configuration['maximum_cohort'] <= 10, 'Maximum cohort must never exceed 10.');
$assert((int)$configuration['maximum_batch'] <= 3, 'Maximum batch must never exceed 3.');

$assert(str_contains($service,"run_status IN ('draft','canary_sent','canary_confirmed','approved','running','paused')"), 'Only one open limited pilot may exist at a time.');
$assert(str_contains($service,"LOWER(email)=?"), 'The fixed administrator recipient must match an active account link.');
$assert(str_contains($service,"'pilot_canary','pilot_canary'"), 'The canary must be recorded in the existing outbox.');
$assert(str_contains($service,'training_notification_provider_states WHERE outbox_id=?'), 'Canary approval must depend on reconciled provider state.');
$assert(str_contains($service,"general_notification_worker_must_remain_disabled"), 'The unrestricted notification worker must be rejected.');
$assert(str_contains($service,"pm.member_status='active'"), 'Participant processing must be restricted to active pilot members.');
$assert(str_contains($service,'min(3,$limit)') || str_contains($service,'min(3, (int)'), 'Pilot batches must be capped at three.');
$assert(str_contains($service,'tl_limited_email_pilot_auto_pause'), 'Automatic health pause must be implemented.');
$assert(str_contains($service,"run_status='graduated'"), 'Graduation must be an explicit persisted decision.');
$assert(str_contains($service,"run_status='rejected'"), 'Rejection must be an explicit persisted decision.');
$assert(!preg_match('/\bmail\s*\(/i',$service), 'Section 18 must not use PHP mail().');
$assert(!str_contains($service,'curl_init('), 'Section 18 orchestration must not create another HTTP transport.');
$assert(!str_contains($service,'wallet_balance') && !str_contains($service,'microgifter_issue'), 'Section 18 must not create wallet or Microgifter issuing authority.');

$run = ['maximum_bounces'=>0,'maximum_complaints'=>0,'maximum_provider_failures'=>0,'maximum_orphaned_events'=>0];
$healthy = ['bounced'=>0,'complained'=>0,'provider_failed'=>0,'local_failed'=>0,'orphaned'=>0,'provider_suppressed'=>0,'missing_webhook'=>0,'stale_delays'=>0];
$assert(tl_limited_email_pilot_threshold_breaches($run,$healthy) === [], 'Healthy metrics must not create a pause.');
$unhealthy = $healthy;
$unhealthy['complained'] = 1;
$unhealthy['missing_webhook'] = 1;
$breaches = tl_limited_email_pilot_threshold_breaches($run,$unhealthy);
$assert(in_array('complaint_threshold',$breaches,true) && in_array('missing_webhook_confirmation',$breaches,true), 'Complaint and missing webhook conditions must pause the pilot.');

$assert(str_contains($page,"'required_role'=>'admin'"), 'Pilot dashboard must require administrator access.');
$assert(str_contains($action,"tl_security_guard_write('manage_limited_email_pilot'"), 'Pilot actions must use the protected write guard.');
$assert(str_contains($action,'tl_limited_email_pilot_admin($user)'), 'Pilot actions must explicitly enforce administrator access.');
$assert(str_contains($worker,"getopt('', ['run:','limit::','json'])"), 'Bounded worker must require an explicit run and optional limit.');
$assert(str_contains($worker,"max(1,min(3"), 'Bounded worker CLI must cap batches at three.');
$assert(str_contains($checkCli,"'read_only'=>true"), 'Pilot diagnostic CLI must be read-only.');
$assert(str_contains($config,"'limited_email_pilot_enabled' => false") && str_contains($config,"'limited_email_pilot_processing_enabled' => false"), 'Configuration example must keep both Section 18 gates disabled.');
$assert(str_contains($nav,'admin-email-pilot'), 'Administrator navigation must expose the limited email pilot.');
$assert(str_contains($acceptance,'training-lab-limited-email-pilot.php') && str_contains($acceptance,'limited_email_pilot_graduation_v1.sql'), 'Canonical acceptance must include Section 18 service and migration.');
$assert(str_contains($docs,'Safe activation order') && str_contains($docs,'Automatic pause conditions') && str_contains($docs,'## Rollback'), 'Section 18 documentation must cover activation, automatic pause, and rollback.');

if ($failures) {
    fwrite(STDERR,"Limited Live Email Pilot + Graduation contract failed:\n- " . implode("\n- ",$failures) . "\n");
    exit(1);
}
echo "Limited Live Email Pilot + Graduation contract passed.\n";
