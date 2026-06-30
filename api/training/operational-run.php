<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/labs-layout.php';
$section = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_GET['section'] ?? 'summary'));
$campaign = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_GET['campaign'] ?? ''));
$userId = max(0, (int)($_GET['user_id'] ?? 0));
if ($section === 'account') { tl_stage34_json(tl_stage560_account_session_command()); exit; }
if ($section === 'campaign') { tl_stage34_json(tl_stage560_campaign_publish_planner($campaign)); exit; }
if ($section === 'mission') { tl_stage34_json(tl_stage560_mission_runbook($campaign, $userId)); exit; }
if ($section === 'assurance' || $section === 'admin') { tl_stage34_json(tl_stage560_review_reward_assurance()); exit; }
if ($section === 'ledger' || $section === 'reporting') { tl_stage34_json(tl_stage560_reporting_ledger()); exit; }
$summary = tl_stage560_operational_run_summary();
$summary['workflow_control_overlay'] = function_exists('tl_stage600_workflow_control_summary') ? tl_stage600_workflow_control_summary(false) : [];
$summary['data_quality_overlay'] = function_exists('tl_stage640_data_quality_summary') ? tl_stage640_data_quality_summary(false) : [];
$summary['communication_rhythm_overlay'] = function_exists('tl_stage680_communication_rhythm_summary') ? tl_stage680_communication_rhythm_summary(false) : [];
$summary['content_experience_overlay'] = function_exists('tl_stage720_content_experience_summary') ? tl_stage720_content_experience_summary(false) : [];
tl_stage34_json($summary);
exit;
