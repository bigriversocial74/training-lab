<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
tl_stage34_json(tl_stage280_reward_assurance(isset($_GET['user_id']) ? max(1, (int)$_GET['user_id']) : null, (string)($_GET['status'] ?? '')));
