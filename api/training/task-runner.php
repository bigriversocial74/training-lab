<?php
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
$campaign = trim((string)($_GET['campaign'] ?? tl_app_default_campaign_ref()));
$userId = max(1, (int)($_GET['user_id'] ?? 1));
tl_app_json(['ok' => true, 'data' => ['context' => tl_app_participant_context($campaign, $userId), 'safe_boundary' => tl_app_stage35_summary()['safe_boundaries']]]);
