<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/labs-layout.php';
$section = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_GET['section'] ?? 'summary'));
$campaign = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_GET['campaign'] ?? ''));
$userId = max(0, (int)($_GET['user_id'] ?? 0));
if ($section === 'content' || $section === 'library') { tl_stage34_json(tl_stage720_training_content_library($campaign, $userId)); exit; }
if ($section === 'templates' || $section === 'challenge') { tl_stage34_json(tl_stage720_challenge_template_selection()); exit; }
if ($section === 'learning' || $section === 'participant') { tl_stage34_json(tl_stage720_participant_learning_experience($campaign, $userId)); exit; }
if ($section === 'quality' || $section === 'admin') { tl_stage34_json(tl_stage720_admin_training_quality_console()); exit; }
if ($section === 'audit') { tl_stage34_json(tl_stage720_content_experience_audit()); exit; }
tl_stage34_json(tl_stage720_content_experience_summary());
exit;
