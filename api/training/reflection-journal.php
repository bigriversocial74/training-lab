<?php
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
tl_app_json(['ok' => true, 'data' => tl_stage45_reflection_state(isset($_GET['campaign']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$_GET['campaign']) : tl_app_default_campaign_ref(), max(1, (int)($_GET['user_id'] ?? 1)))]);
