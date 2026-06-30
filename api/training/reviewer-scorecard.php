<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
tl_stage34_json(tl_stage280_reviewer_scorecard(isset($_GET['reviewer_user_id']) ? max(1, (int)$_GET['reviewer_user_id']) : null));
