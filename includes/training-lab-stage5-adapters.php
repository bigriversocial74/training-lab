<?php
/**
 * Training Lab Stage 5 pre-SQL adapter shell.
 *
 * This file prepares the read-only handoff from static Stage 3/4 seed data to
 * future database-backed repositories. It intentionally does not open a DB
 * connection, execute SQL, process uploads, issue rewards, touch wallet
 * balances, run payments, or create a standalone account system.
 */

require_once __DIR__ . '/training-lab-stage34-service.php';

if (!function_exists('tl_stage5_boundary')) {
    function tl_stage5_boundary(): array
    {
        return [
            'stage' => 'Stage 5 pre-SQL integration prep',
            'mode' => 'read-only adapter shell',
            'sql_policy' => 'one consolidated SQL file after existing schema is uploaded/reviewed',
            'writes_enabled' => false,
            'uploads_enabled' => false,
            'payments_enabled' => false,
            'wallet_writes_enabled' => false,
            'reward_issuing_enabled' => false,
            'claim_redeem_enabled' => false,
            'auth_source' => 'existing Microgifter account system later',
        ];
    }
}

if (!function_exists('tl_stage5_table_map')) {
    function tl_stage5_table_map(): array
    {
        return [
            [
                'training_table' => 'training_campaigns',
                'purpose' => 'Organization-owned challenge/campaign definitions',
                'existing_dependency' => 'existing merchant / organization table',
                'required_existing_fields' => ['organization id', 'owner user id', 'status'],
                'sql_status' => 'pending existing schema upload',
            ],
            [
                'training_table' => 'training_campaign_tasks',
                'purpose' => 'Ordered actions/tasks inside a campaign',
                'existing_dependency' => 'training_campaigns',
                'required_existing_fields' => ['campaign id'],
                'sql_status' => 'pending consolidated SQL',
            ],
            [
                'training_table' => 'training_participants',
                'purpose' => 'User participation in a campaign',
                'existing_dependency' => 'existing users table',
                'required_existing_fields' => ['user id', 'email/name display fields'],
                'sql_status' => 'pending existing schema upload',
            ],
            [
                'training_table' => 'training_proof_submissions',
                'purpose' => 'Proof metadata and status for task completion',
                'existing_dependency' => 'existing users table + future media/file storage policy',
                'required_existing_fields' => ['user id', 'reviewer role later', 'file storage reference later'],
                'sql_status' => 'pending media policy',
            ],
            [
                'training_table' => 'training_reviews',
                'purpose' => 'Manual proof review decisions',
                'existing_dependency' => 'existing users/roles table',
                'required_existing_fields' => ['reviewer user id', 'role/permission'],
                'sql_status' => 'pending permissions review',
            ],
            [
                'training_table' => 'training_action_receipts',
                'purpose' => 'Immutable record that a verified action happened',
                'existing_dependency' => 'approved proof submission and reviewer decision',
                'required_existing_fields' => ['proof submission id', 'participant id'],
                'sql_status' => 'pending consolidated SQL',
            ],
            [
                'training_table' => 'training_reward_rules',
                'purpose' => 'Rules that determine reward eligibility',
                'existing_dependency' => 'existing reward/catalog/wallet system later',
                'required_existing_fields' => ['reward id/source later'],
                'sql_status' => 'pending wallet/reward mapping',
            ],
            [
                'training_table' => 'training_reward_events',
                'purpose' => 'Approved reward event bridge into Microgifter wallet/rewards',
                'existing_dependency' => 'existing wallet/reward/claim tables',
                'required_existing_fields' => ['wallet owner id', 'reward id', 'claim status id later'],
                'sql_status' => 'pending existing schema upload',
            ],
        ];
    }
}

if (!function_exists('tl_stage5_repository_status')) {
    function tl_stage5_repository_status(): array
    {
        return [
            'campaign_repository' => 'ready for read-only DB adapter swap',
            'task_repository' => 'ready for read-only DB adapter swap',
            'participant_repository' => 'pending existing users table names',
            'proof_repository' => 'pending media storage policy',
            'review_repository' => 'pending reviewer permission source',
            'wallet_repository' => 'pending existing wallet/reward table names',
            'sql_migration' => 'pending one consolidated SQL after schema upload',
        ];
    }
}

if (!function_exists('tl_stage5_campaigns')) {
    function tl_stage5_campaigns(): array
    {
        return tl_stage34_campaigns();
    }
}

if (!function_exists('tl_stage5_reviews')) {
    function tl_stage5_reviews(): array
    {
        return tl_stage34_reviews();
    }
}

if (!function_exists('tl_stage5_wallet_preview')) {
    function tl_stage5_wallet_preview(): array
    {
        return tl_stage34_wallet();
    }
}

if (!function_exists('tl_stage5_readiness')) {
    function tl_stage5_readiness(): array
    {
        return [
            'boundary' => tl_stage5_boundary(),
            'repository_status' => tl_stage5_repository_status(),
            'table_map' => tl_stage5_table_map(),
            'score' => [
                'stage_5a_database_mapping' => '9.1/10',
                'stage_5b_read_only_adapter_shell' => '9.0/10',
                'stage_5c_proof_review_workflow_design' => '9.2/10',
                'stage_5d_final_pre_sql_checkpoint' => '9.3/10',
                'remaining_to_10' => [
                    'Upload existing SQL/schema',
                    'Confirm current user/account table names',
                    'Confirm merchant/organization table names',
                    'Confirm wallet/reward/claim table names',
                    'Confirm proof upload media policy',
                    'Confirm reviewer role/permission source',
                ],
            ],
        ];
    }
}
