<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
$ref = isset($_GET['task']) ? tl_stage20_clean_ref((string)$_GET['task']) : null;
tl_stage34_json(tl_training_task_inspector_summary($ref));
