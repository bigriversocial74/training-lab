<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/labs-layout.php';
$section = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_GET['section'] ?? 'summary'));
$campaign = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_GET['campaign'] ?? ''));
$userId = max(0, (int)($_GET['user_id'] ?? 0));
if ($section === 'campaign') { tl_stage34_json(tl_stage600_campaign_state_control($campaign)); exit; }
if ($section === 'timeline' || $section === 'participant') { tl_stage34_json(tl_stage600_participant_timeline($campaign, $userId)); exit; }
if ($section === 'proof' || $section === 'review') { tl_stage34_json(tl_stage600_proof_review_console()); exit; }
if ($section === 'reward') { tl_stage34_json(tl_stage600_reward_operations()); exit; }
if ($section === 'operator' || $section === 'snapshot') { tl_stage34_json(tl_stage600_operator_command_snapshot()); exit; }
if ($section === 'audit') { tl_stage34_json(tl_stage600_workflow_control_audit()); exit; }
tl_stage34_json(tl_stage600_workflow_control_summary());
exit;
