<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/labs-layout.php';
$section = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_GET['section'] ?? 'summary'));
$campaign = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_GET['campaign'] ?? ''));
$userId = max(0, (int)($_GET['user_id'] ?? 0));
if ($section === 'participant') { tl_stage34_json(tl_stage680_participant_communication($campaign, $userId)); exit; }
if ($section === 'admin') { tl_stage34_json(tl_stage680_admin_communication_console()); exit; }
if ($section === 'followup' || $section === 'reminders') { tl_stage34_json(tl_stage680_mission_followup_logic($campaign, $userId)); exit; }
if ($section === 'rhythm' || $section === 'daily') { tl_stage34_json(tl_stage680_operator_daily_rhythm()); exit; }
if ($section === 'audit') { tl_stage34_json(tl_stage680_communication_rhythm_audit()); exit; }
$summary = tl_stage680_communication_rhythm_summary();
$summary['content_experience_overlay'] = function_exists('tl_stage720_content_experience_summary') ? tl_stage720_content_experience_summary(false) : [];
tl_stage34_json($summary);
exit;
