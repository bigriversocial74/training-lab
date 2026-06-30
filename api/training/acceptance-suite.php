<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/labs-layout.php';
require_once __DIR__ . '/../../includes/training-lab-design-assets.php';
function tl_stage680_latest_api_summary(): array {
    if (function_exists('tl_stage880_adapter_sync_summary')) return tl_stage880_adapter_sync_summary();
    if (function_exists('tl_stage840_user_award_summary')) return tl_stage840_user_award_summary();
    if (function_exists('tl_stage800_microgifter_campaign_import_summary')) return tl_stage800_microgifter_campaign_import_summary();
    if (function_exists('tl_stage760_merchant_commerce_summary')) return tl_stage760_merchant_commerce_summary();
    if (function_exists('tl_stage720_content_experience_summary')) return tl_stage720_content_experience_summary();
    if (function_exists('tl_stage680_communication_rhythm_summary')) return tl_stage680_communication_rhythm_summary();
    if (function_exists('tl_stage640_data_quality_summary')) return tl_stage640_data_quality_summary();
    if (function_exists('tl_stage600_workflow_control_summary')) return tl_stage600_workflow_control_summary();
    if (function_exists('tl_stage560_operational_run_summary')) return tl_stage560_operational_run_summary();
    if (function_exists('tl_stage520_core_flow_summary')) return tl_stage520_core_flow_summary();
    if (function_exists('tl_stage480_acceptance_summary')) return tl_stage480_acceptance_summary();
    return ['stage'=>'fallback','score'=>0,'accepted'=>false];
}
tl_stage34_json(tl_stage680_latest_api_summary());
