<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
$ref = isset($_GET['reward']) ? tl_stage20_clean_ref((string)$_GET['reward']) : null;
tl_stage34_json(tl_training_reward_inspector_summary($ref));
