<?php
/**
 * Microgifter Training Lab database config example.
 *
 * IMPORTANT FOR DAVID'S CPANEL WORKFLOW
 * ------------------------------------
 * This file is packaged at /labs/labs/config.php. After extracting the zip and
 * moving the CONTENTS of the first /labs/ folder into web root, it lands at
 * /labs/config.php. Edit only that deployed private file.
 *
 * Do not commit live credentials, developer keys, or account bridge secrets.
 */
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'ywzyeite_microlabs',
        'username' => 'ywzyeite_microlabs',
        'password' => 'PUT_YOUR_DATABASE_PASSWORD_HERE',
        'charset' => 'utf8mb4',
    ],
    'training_lab' => [
        'mode' => 'database',
        'debug' => false,
        'allow_demo_session_login' => false,
        'proof_records_only_no_real_uploads' => true,
        'reward_events_only_no_wallet_balance_changes' => true,
        'payments_enabled' => false,
        'claim_redeem_enabled' => false,
        'use_existing_microgifter_auth' => true,
        'account_integration' => [
            'issuer' => 'https://microgifter.com',
            'audience' => 'training-lab',
            'max_ttl_seconds' => 300,
            'clock_skew_seconds' => 30,
            // Set TL_ACCOUNT_BRIDGE_SECRET in the server environment.
            // During rotation, TL_ACCOUNT_BRIDGE_PREVIOUS_SECRET may temporarily
            // contain the former secret. Never commit either secret here.
        ],
        // Prefer TL_DEVELOPER_KEY in the server environment. Never commit it here.
    ],
];
