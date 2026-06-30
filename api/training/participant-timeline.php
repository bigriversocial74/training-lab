<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
$userId = max(1, (int)($_GET['user_id'] ?? tl_stage240_actor_id()));
tl_stage34_json(['stage'=>'Stage 201-240 participant timeline','user_id'=>$userId,'timeline'=>tl_stage240_user_activity_timeline($userId, 50)]);
