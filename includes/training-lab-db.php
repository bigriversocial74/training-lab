<?php
/**
 * Training Lab database helper.
 *
 * David's cPanel workflow:
 *   1. Extract zip. It creates /labs/.
 *   2. Move contents of that outer /labs/ folder into root.
 *
 * Therefore the config is packaged at:
 *   /labs/labs/config.php
 *
 * And after the move-up workflow it becomes:
 *   /labs/config.php
 *
 * From the final location:
 *   /includes/training-lab-db.php
 *
 * This resolves to:
 *   dirname(__DIR__) . '/labs/config.php'
 */

if (!function_exists('tl_db_config_path')) {
    function tl_db_config_path(): string
    {
        return dirname(__DIR__) . '/labs/config.php';
    }
}

if (!function_exists('tl_db_placeholder')) {
    function tl_db_placeholder($value): bool
    {
        $value = trim((string)$value);
        if ($value === '') return true;

        $upper = strtoupper($value);
        return strpos($upper, 'PUT_YOUR_') !== false
            || strpos($upper, 'YOUR_') !== false
            || strpos($upper, 'PASSWORD_HERE') !== false
            || strpos($upper, 'CHANGE_ME') !== false
            || strpos($upper, 'REPLACE_ME') !== false;
    }
}

if (!function_exists('tl_db_config_load')) {
    function tl_db_config_load(): array
    {
        static $loaded = null;
        if (is_array($loaded)) return $loaded;

        $path = tl_db_config_path();

        $diagnostics = [
            'expected_path' => $path,
            'file_exists' => is_file($path),
            'loaded' => false,
            'error' => null,
            'database_name_present' => false,
            'username_present' => false,
            'password_present' => false,
            'host_present' => false,
            'port_present' => false,
            'source' => 'examples/labs/labs/config.php',
        ];

        if (!is_file($path)) {
            $diagnostics['error'] = 'Missing /labs/config.php. In the zip this starts at /labs/labs/config.php, then after moving the outer /labs contents to root it becomes /labs/config.php.';
            return $loaded = ['config' => [], 'db' => [], 'diagnostics' => $diagnostics];
        }

        try {
            $config = require $path;
        } catch (Throwable $e) {
            $diagnostics['error'] = 'Could not load /labs/config.php: ' . $e->getMessage();
            return $loaded = ['config' => [], 'db' => [], 'diagnostics' => $diagnostics];
        }

        if (!is_array($config)) {
            $diagnostics['error'] = '/labs/config.php must return a PHP array.';
            return $loaded = ['config' => [], 'db' => [], 'diagnostics' => $diagnostics];
        }

        $db = $config['db'] ?? [];
        if (!is_array($db)) $db = [];

        $diagnostics['loaded'] = true;
        $diagnostics['database_name_present'] = !tl_db_placeholder($db['database'] ?? '');
        $diagnostics['username_present'] = !tl_db_placeholder($db['username'] ?? '');
        $diagnostics['password_present'] = !tl_db_placeholder($db['password'] ?? '');
        $diagnostics['host_present'] = !tl_db_placeholder($db['host'] ?? '');
        $diagnostics['port_present'] = isset($db['port']) && (int)($db['port'] ?? 0) > 0;

        $missing = [];
        if (!$diagnostics['host_present']) $missing[] = 'db.host';
        if (!$diagnostics['database_name_present']) $missing[] = 'db.database';
        if (!$diagnostics['username_present']) $missing[] = 'db.username';
        if (!$diagnostics['password_present']) $missing[] = 'db.password';

        if ($missing) {
            $diagnostics['error'] = 'Missing or placeholder config value: ' . implode(', ', $missing) . '.';
        }

        return $loaded = ['config' => $config, 'db' => $db, 'diagnostics' => $diagnostics];
    }
}

if (!function_exists('tl_db_config_diagnostics')) {
    function tl_db_config_diagnostics(): array
    {
        $loaded = tl_db_config_load();
        return $loaded['diagnostics'];
    }
}

if (!function_exists('tl_db_config_ready')) {
    function tl_db_config_ready(): bool
    {
        $d = tl_db_config_diagnostics();

        return !empty($d['file_exists'])
            && !empty($d['loaded'])
            && !empty($d['database_name_present'])
            && !empty($d['username_present'])
            && !empty($d['password_present'])
            && !empty($d['host_present']);
    }
}

if (!function_exists('tl_db_config')) {
    function tl_db_config(): array
    {
        if (!tl_db_config_ready()) return [];

        $loaded = tl_db_config_load();
        $db = $loaded['db'];

        $host = (string)($db['host'] ?? 'localhost');
        $port = (int)($db['port'] ?? 3306);
        $name = (string)($db['database'] ?? '');
        $charset = (string)($db['charset'] ?? 'utf8mb4');

        return [
            'dsn' => 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=' . $charset,
            'user' => (string)($db['username'] ?? ''),
            'pass' => (string)($db['password'] ?? ''),
        ];
    }
}

if (!function_exists('tl_db_connection_error')) {
    function tl_db_connection_error(): ?string
    {
        return $GLOBALS['tl_db_connection_error'] ?? null;
    }
}

if (!function_exists('tl_db')) {
    function tl_db(): ?PDO
    {
        static $pdo = null;
        static $attempted = false;

        if ($attempted) return $pdo;

        $attempted = true;
        $cfg = tl_db_config();

        if (empty($cfg['dsn'])) return null;

        try {
            $pdo = new PDO($cfg['dsn'], $cfg['user'] ?? '', $cfg['pass'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return $pdo;
        } catch (Throwable $e) {
            $GLOBALS['tl_db_connection_error'] = $e->getMessage();
            return null;
        }
    }
}

if (!function_exists('tl_db_ready')) {
    function tl_db_ready(): bool
    {
        return tl_db() instanceof PDO;
    }
}

if (!function_exists('tl_table_exists')) {
    function tl_table_exists(string $table): bool
    {
        $pdo = tl_db();
        if (!$pdo) return false;

        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
            );
            $stmt->execute([$table]);
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('tl_uuid')) {
    function tl_uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('tl_slug')) {
    function tl_slug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
        return $slug !== '' ? substr($slug, 0, 150) : 'training-campaign-' . substr(tl_uuid(), 0, 8);
    }
}

if (!function_exists('tl_json_response')) {
    function tl_json_response(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('tl_request_data')) {
    function tl_request_data(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        if (is_array($json)) return $json;
        return $_POST ?: $_GET ?: [];
    }
}

if (!function_exists('tl_training_required_tables')) {
    function tl_training_required_tables(): array
    {
        return [
            'training_campaigns',
            'training_campaign_tasks',
            'training_participants',
            'training_proof_submissions',
            'training_reviews',
            'training_action_receipts',
            'training_reward_rules',
            'training_reward_events',
            'training_streaks',
            'training_events',
            'training_permission_catalog',
        ];
    }
}

if (!function_exists('tl_table_row_count')) {
    function tl_table_row_count(string $table): ?int
    {
        if (!in_array($table, tl_training_required_tables(), true)) return null;
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists($table)) return null;

        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $table) . '`');
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('tl_db_status_summary')) {
    function tl_db_status_summary(): array
    {
        $tables = tl_training_required_tables();
        $tableStatus = [];
        $rowCounts = [];

        foreach ($tables as $table) {
            $exists = tl_table_exists($table);
            $tableStatus[$table] = $exists;
            $rowCounts[$table] = $exists ? tl_table_row_count($table) : null;
        }

        $missingTables = array_values(array_filter($tables, fn($table) => empty($tableStatus[$table])));
        $configReady = tl_db_config_ready();
        $connected = tl_db_ready();
        $allTablesPresent = count($missingTables) === 0;

        return [
            'db_configured' => $configReady && $connected && $allTablesPresent,
            'config_ready' => $configReady,
            'connected' => $connected,
            'connection_error' => $connected ? null : tl_db_connection_error(),
            'all_tables_present' => $allTablesPresent,
            'missing_tables' => $missingTables,
            'config' => tl_db_config_diagnostics(),
            'tables' => $tableStatus,
            'row_counts' => $rowCounts,
            'safe_boundaries' => [
                'proof_records_only_no_real_uploads' => true,
                'reward_events_only_no_wallet_balance_changes' => true,
                'no_payments' => true,
                'no_claim_redeem_logic' => true,
                'existing_auth_required_later' => true,
            ],
        ];
    }
}



if (!function_exists('tl_training_expected_table_columns')) {
    function tl_training_expected_table_columns(): array
    {
        return [
            'training_campaigns' => ['id','public_id','owner_user_id','created_by_user_id','merchant_location_id','slug','title','summary','description','campaign_type','visibility','status','starts_at','ends_at','timezone','target_action_count','reward_summary','settings_json','created_at','updated_at'],
            'training_campaign_tasks' => ['id','public_id','campaign_id','position_no','day_no','task_type','title','instructions','proof_required','expected_duration_minutes','status','settings_json','created_at','updated_at'],
            'training_participants' => ['id','public_id','campaign_id','user_id','invited_by_user_id','participant_label','status','joined_at','completed_at','removed_at','metadata_json','created_at','updated_at'],
            'training_proof_submissions' => ['id','public_id','campaign_id','task_id','participant_id','submitted_by_user_id','proof_type','proof_text','storage_reference','external_url','status','submitted_at','reviewed_at','metadata_json','created_at','updated_at'],
            'training_reviews' => ['id','public_id','proof_submission_id','reviewer_user_id','decision','review_notes','reviewed_at','metadata_json','created_at'],
            'training_action_receipts' => ['id','public_id','campaign_id','participant_id','user_id','proof_submission_id','review_id','receipt_type','verification_hash','receipt_status','issued_at','voided_at','metadata_json','created_at'],
            'training_reward_rules' => ['id','public_id','campaign_id','rule_name','trigger_type','threshold_count','reward_type','reward_label','reward_value_cents','currency','linked_microgift_template_id','linked_catalog_product_id','status','settings_json','created_at','updated_at'],
            'training_reward_events' => ['id','public_id','campaign_id','participant_id','user_id','action_receipt_id','reward_rule_id','status','linked_gift_id','linked_microgift_instance_id','linked_digital_entitlement_id','linked_wallet_event_id','value_cents','currency','eligibility_reason','issued_at','cancelled_at','failure_message','metadata_json','created_at','updated_at'],
            'training_streaks' => ['id','campaign_id','participant_id','user_id','current_streak_days','longest_streak_days','completed_action_count','last_action_date','updated_at','created_at'],
            'training_events' => ['id','public_id','actor_user_id','subject_type','subject_id','event_type','metadata_json','created_at'],
            'training_permission_catalog' => ['id','slug','name','description','created_at'],
        ];
    }
}

if (!function_exists('tl_db_safe_identifier')) {
    function tl_db_safe_identifier(string $identifier): ?string
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $identifier) ? $identifier : null;
    }
}

if (!function_exists('tl_table_columns')) {
    function tl_table_columns(string $table): array
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) return $cache[$table];

        if (!in_array($table, tl_training_required_tables(), true)) return $cache[$table] = [];
        $safeTable = tl_db_safe_identifier($table);
        $pdo = tl_db();
        if (!$pdo || !$safeTable || !tl_table_exists($table)) return $cache[$table] = [];

        try {
            $stmt = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position');
            $stmt->execute([$safeTable]);
            $columns = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            return $cache[$table] = $columns;
        } catch (Throwable $e) {
            return $cache[$table] = [];
        }
    }
}

if (!function_exists('tl_table_has_column')) {
    function tl_table_has_column(string $table, string $column): bool
    {
        return in_array($column, tl_table_columns($table), true);
    }
}

if (!function_exists('tl_table_datetime_max')) {
    function tl_table_datetime_max(string $table): ?string
    {
        if (!in_array($table, tl_training_required_tables(), true)) return null;
        $safeTable = tl_db_safe_identifier($table);
        $pdo = tl_db();
        if (!$pdo || !$safeTable || !tl_table_exists($table)) return null;

        foreach (['updated_at', 'created_at', 'submitted_at', 'reviewed_at', 'issued_at', 'joined_at'] as $column) {
            if (!tl_table_has_column($table, $column)) continue;
            $safeColumn = tl_db_safe_identifier($column);
            if (!$safeColumn) continue;
            try {
                $stmt = $pdo->query('SELECT MAX(`' . $safeColumn . '`) FROM `' . $safeTable . '`');
                $value = $stmt ? $stmt->fetchColumn() : null;
                if ($value) return (string)$value;
            } catch (Throwable $e) {
                continue;
            }
        }

        return null;
    }
}

if (!function_exists('tl_training_table_diagnostics')) {
    function tl_training_table_diagnostics(): array
    {
        $expected = tl_training_expected_table_columns();
        $diagnostics = [];

        foreach (tl_training_required_tables() as $table) {
            $exists = tl_table_exists($table);
            $columns = $exists ? tl_table_columns($table) : [];
            $expectedColumns = $expected[$table] ?? [];
            $missingColumns = array_values(array_diff($expectedColumns, $columns));
            $diagnostics[$table] = [
                'exists' => $exists,
                'row_count' => $exists ? tl_table_row_count($table) : null,
                'expected_column_count' => count($expectedColumns),
                'actual_column_count' => count($columns),
                'missing_columns' => $missingColumns,
                'schema_ready' => $exists && count($missingColumns) === 0,
                'last_activity_at' => $exists ? tl_table_datetime_max($table) : null,
            ];
        }

        return $diagnostics;
    }
}
