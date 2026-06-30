<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/labs-layout.php';
$section = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_GET['section'] ?? 'summary'));
$campaign = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_GET['campaign'] ?? ''));
$userId = max(0, (int)($_GET['user_id'] ?? 0));
if ($section === 'campaign') { tl_stage34_json(tl_stage640_campaign_data_quality($campaign)); exit; }
if ($section === 'participant' || $section === 'identity') { tl_stage34_json(tl_stage640_participant_data_quality($campaign, $userId)); exit; }
if ($section === 'proof' || $section === 'evidence') { tl_stage34_json(tl_stage640_proof_evidence_quality()); exit; }
if ($section === 'reward' || $section === 'assurance') { tl_stage34_json(tl_stage640_reward_audit_assurance()); exit; }
if ($section === 'health' || $section === 'operator') { tl_stage34_json(tl_stage640_operator_health_dashboard()); exit; }
if ($section === 'audit') { tl_stage34_json(tl_stage640_data_quality_audit()); exit; }
$summary = tl_stage640_data_quality_summary();
$summary['communication_rhythm_overlay'] = function_exists('tl_stage680_communication_rhythm_summary') ? tl_stage680_communication_rhythm_summary(false) : [];
$summary['content_experience_overlay'] = function_exists('tl_stage720_content_experience_summary') ? tl_stage720_content_experience_summary(false) : [];
tl_stage34_json($summary);
exit;
