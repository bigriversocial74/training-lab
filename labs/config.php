<?php
/**
 * Microgifter Training Lab database config.
 *
 * IMPORTANT FOR DAVID'S CPANEL WORKFLOW
 * ------------------------------------
 * This file is packaged at:
 *   /labs/labs/config.php
 *
 * Because David extracts the zip, then moves the CONTENTS of the first /labs/
 * folder up into the web root.
 *
 * After that move, this file lands at:
 *   /labs/config.php
 *
 * Edit this final file:
 *   /labs/config.php
 *
 * No /config/ folder.
 * No root /config.php required.
 * No database password stored in /includes/.
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
        'proof_records_only_no_real_uploads' => true,
        'reward_events_only_no_wallet_balance_changes' => true,
        'payments_enabled' => false,
        'claim_redeem_enabled' => false,
        'use_existing_microgifter_auth' => true,
    ],
];
