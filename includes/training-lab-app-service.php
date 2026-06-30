<?php
/**
 * Functional Training Lab app service.
 *
 * Standalone-script boundary: these helpers write only to Training Lab tables.
 * They do not process real uploads, payments, or wallet balances.
 * Microgifter reward issuing and claim linking are adapter/developer-key gated
 * through the Training Lab rewards bridge.
 */
require_once __DIR__ . '/training-lab-actions.php';
require_once __DIR__ . '/training-lab-account-bridge.php';
require_once __DIR__ . '/training-lab-microgifter-rewards.php';
require_once __DIR__ . '/training-lab-product-engine.php';
require_once __DIR__ . '/training-lab-stage240-app-builds.php';
require_once __DIR__ . '/training-lab-stage280-app-builds.php';
require_once __DIR__ . '/training-lab-design-assets.php';
require_once __DIR__ . '/training-lab-stage520-core-flow.php';
require_once __DIR__ . '/training-lab-stage560-operational-run.php';
require_once __DIR__ . '/training-lab-stage600-workflow-control.php';
require_once __DIR__ . '/training-lab-stage640-data-quality.php';
require_once __DIR__ . '/training-lab-stage680-communication-rhythm.php';
require_once __DIR__ . '/training-lab-stage720-content-experience.php';
require_once __DIR__ . '/training-lab-stage760-merchant-commerce.php';
require_once __DIR__ . '/training-lab-stage800-microgifter-import.php';
require_once __DIR__ . '/training-lab-stage840-user-awards.php';
require_once __DIR__ . '/training-lab-stage880-adapter-sync.php';
require_once __DIR__ . '/training-lab-stage883-readonly-adapter.php';

if (!function_exists('tl_app_required_tables_status')) {
    function tl_app_required_tables_status(): array
    {
        $rows = [];
        foreach (tl_training_required_tables() as $table) {
            $rows[$table] = tl_table_exists($table);
        }
        return $rows;
    }
}

if (!function_exists('tl_app_count')) {
    function tl_app_count(string $table, string $where = '', array $params = []): int
    {
        $pdo = tl_db();
        if (!$pdo || !in_array($table, tl_training_required_tables(), true) || !tl_table_exists($table)) return 0;
        try {
            $safe = tl_db_safe_identifier($table);
            if (!$safe) return 0;
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $safe . '`' . ($where !== '' ? ' WHERE ' . $where : ''));
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) { return 0; }
    }
}

if (!function_exists('tl_app_campaign_options')) {
    function tl_app_campaign_options(): array
    {
        $pdo = tl_db();
        if ($pdo && tl_table_exists('training_campaigns')) {
            try {
                $rows = $pdo->query("SELECT id, public_id, slug, title, status, visibility, target_action_count, updated_at FROM training_campaigns ORDER BY updated_at DESC, id DESC LIMIT 100")->fetchAll();
                if ($rows) return array_map(function ($row) {
                    return [
                        'id' => (int)$row['id'],
                        'public_id' => (string)$row['public_id'],
                        'slug' => (string)$row['slug'],
                        'title' => (string)$row['title'],
                        'status' => (string)$row['status'],
                        'visibility' => (string)$row['visibility'],
                        'target_action_count' => (int)$row['target_action_count'],
                        'updated_at' => (string)$row['updated_at'],
                        'ref' => (string)($row['slug'] ?: $row['public_id']),
                    ];
                }, $rows);
            } catch (Throwable $e) {}
        }
        return array_map(function ($campaign) {
            return [
                'id' => (int)($campaign['db_id'] ?? 0),
                'public_id' => (string)($campaign['public_id'] ?? $campaign['id']),
                'slug' => (string)$campaign['id'],
                'title' => (string)$campaign['title'],
                'status' => strtolower((string)$campaign['status']),
                'visibility' => 'demo',
                'target_action_count' => (int)($campaign['total_actions'] ?? 5),
                'updated_at' => '',
                'ref' => (string)$campaign['id'],
            ];
        }, tl_stage34_campaigns());
    }
}

if (!function_exists('tl_app_default_campaign_ref')) {
    function tl_app_default_campaign_ref(): string
    {
        $options = tl_app_campaign_options();
        return (string)($options[0]['ref'] ?? 'movement-5');
    }
}

if (!function_exists('tl_app_pending_proofs')) {
    function tl_app_pending_proofs(int $limit = 25): array
    {
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_proof_submissions')) return [];
        try {
            $sql = "SELECT p.*, c.title AS campaign_title, c.slug AS campaign_slug, t.title AS task_title,
                       COALESCE(tp.participant_label, CONCAT('User #', p.submitted_by_user_id)) AS participant_label,
                       (SELECT r.decision FROM training_reviews r WHERE r.proof_submission_id = p.id ORDER BY r.created_at DESC LIMIT 1) AS latest_decision
                    FROM training_proof_submissions p
                    LEFT JOIN training_campaigns c ON c.id = p.campaign_id
                    LEFT JOIN training_campaign_tasks t ON t.id = p.task_id
                    LEFT JOIN training_participants tp ON tp.id = p.participant_id
                    WHERE p.status IN ('submitted','in_review')
                    ORDER BY p.submitted_at ASC
                    LIMIT " . max(1, min(100, $limit));
            return $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}
