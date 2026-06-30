<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
$ref = isset($_GET['participant']) ? tl_stage20_clean_ref((string)$_GET['participant']) : null;
tl_stage34_json(tl_training_participant_inspector_summary($ref));
