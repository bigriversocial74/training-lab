<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/labs-layout.php';
$section = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_GET['section'] ?? 'summary'));
$campaign = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_GET['campaign'] ?? ''));
$userId = max(0, (int)($_GET['user_id'] ?? 0));
if ($section === 'account') {
    tl_stage34_json(tl_stage520_account_flow());
    exit;
}
if ($section === 'campaign') {
    tl_stage34_json(tl_stage520_campaign_flow($campaign));
    exit;
}
if ($section === 'mission') {
    tl_stage34_json(tl_stage520_participant_mission($campaign, $userId));
    exit;
}
if ($section === 'admin') {
    tl_stage34_json(tl_stage520_admin_operations());
    exit;
}
if ($section === 'launch') {
    tl_stage34_json(tl_stage520_launch_snapshot());
    exit;
}
$summary = tl_stage520_core_flow_summary();
$summary['operational_run_overlay'] = function_exists('tl_stage560_operational_run_summary') ? tl_stage560_operational_run_summary(false) : [];
$summary['workflow_control_overlay'] = function_exists('tl_stage600_workflow_control_summary') ? tl_stage600_workflow_control_summary(false) : [];
$summary['data_quality_overlay'] = function_exists('tl_stage640_data_quality_summary') ? tl_stage640_data_quality_summary(false) : [];
$summary['communication_rhythm_overlay'] = function_exists('tl_stage680_communication_rhythm_summary') ? tl_stage680_communication_rhythm_summary(false) : [];
$summary['content_experience_overlay'] = function_exists('tl_stage720_content_experience_summary') ? tl_stage720_content_experience_summary(false) : [];
tl_stage34_json($summary);
