<?php
/**
 * Participant-facing campaign discovery, detail, and enrollment read model.
 */
require_once __DIR__ . '/training-lab-app-service.php';
require_once __DIR__ . '/training-lab-product-shell.php';

if (!function_exists('tl_campaign_clean_ref')) {
    function tl_campaign_clean_ref(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($value)) ?: '';
    }
}

if (!function_exists('tl_campaign_settings')) {
    function tl_campaign_settings($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('tl_campaign_user_id')) {
    function tl_campaign_user_id(array $user): int
    {
        return function_exists('tl_security_numeric_user_id')
            ? tl_security_numeric_user_id($user)
            : max(1, (int)($user['numeric_user_id'] ?? 1));
    }
}

if (!function_exists('tl_campaign_money')) {
    function tl_campaign_money(int $valueCents, string $currency = 'USD'): string
    {
        if ($valueCents <= 0) return 'Recognition reward';
        return ($currency === 'USD' ? '$' : $currency . ' ') . number_format($valueCents / 100, 2);
    }
}

if (!function_exists('tl_campaign_datetime')) {
    function tl_campaign_datetime($value, string $timezone = 'America/Phoenix'): ?DateTimeImmutable
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        try {
            $zone = new DateTimeZone($timezone !== '' ? $timezone : 'America/Phoenix');
            return new DateTimeImmutable($value, $zone);
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('tl_campaign_derive_state')) {
    function tl_campaign_derive_state(array $row): array
    {
        $settings = tl_campaign_settings($row['settings_json'] ?? null);
        $timezone = trim((string)($row['timezone'] ?? 'America/Phoenix')) ?: 'America/Phoenix';
        try { $now = new DateTimeImmutable('now', new DateTimeZone($timezone)); }
        catch (Throwable $e) { $now = new DateTimeImmutable('now', new DateTimeZone('America/Phoenix')); }
        $startsAt = tl_campaign_datetime($row['starts_at'] ?? null, $timezone);
        $endsAt = tl_campaign_datetime($row['ends_at'] ?? null, $timezone);
        $participantStatus = (string)($row['participant_status'] ?? '');
        $hasAccess = !empty($row['participant_id']) && $participantStatus !== 'removed';
        $invited = $hasAccess && $participantStatus === 'invited';
        $enrolled = $hasAccess && !$invited;
        $completed = $participantStatus === 'completed';
        $ended = $endsAt instanceof DateTimeImmutable && $endsAt < $now;
        $notStarted = $startsAt instanceof DateTimeImmutable && $startsAt > $now;
        $capacity = max(0, (int)($settings['participant_limit'] ?? $settings['capacity'] ?? 0));
        $enrolledCount = max(0, (int)($row['enrolled_count'] ?? 0));
        $full = !$hasAccess && $capacity > 0 && $enrolledCount >= $capacity;
        $published = (string)($row['visibility'] ?? '') === 'published';
        $joinableStatus = in_array((string)($row['status'] ?? ''), ['scheduled', 'active'], true);
        $canJoin = $invited || (!$hasAccess && $published && $joinableStatus && !$ended && !$full);

        if ($completed) {
            $state = ['key' => 'completed', 'label' => 'Completed', 'tone' => 'success', 'reason' => 'You completed this campaign.'];
        } elseif ($invited) {
            $state = ['key' => 'invited', 'label' => 'Invitation', 'tone' => 'pending', 'reason' => 'You have been invited to join this campaign.'];
        } elseif ($enrolled && $participantStatus === 'paused') {
            $state = ['key' => 'paused', 'label' => 'Paused', 'tone' => 'warning', 'reason' => 'Your enrollment is currently paused.'];
        } elseif ($enrolled && $ended) {
            $state = ['key' => 'ended_enrolled', 'label' => 'Campaign ended', 'tone' => 'neutral', 'reason' => 'The campaign has ended. Your progress remains available.'];
        } elseif ($enrolled) {
            $state = ['key' => 'enrolled', 'label' => $notStarted ? 'Enrolled · Starts soon' : 'In progress', 'tone' => 'info', 'reason' => $notStarted ? 'You are enrolled and can begin when the campaign starts.' : 'Continue from your next task.'];
        } elseif ($ended || in_array((string)($row['status'] ?? ''), ['completed', 'archived'], true)) {
            $state = ['key' => 'closed', 'label' => 'Closed', 'tone' => 'neutral', 'reason' => 'Enrollment is closed for this campaign.'];
        } elseif ($full) {
            $state = ['key' => 'full', 'label' => 'Full', 'tone' => 'warning', 'reason' => 'This campaign has reached its participant limit.'];
        } elseif ($notStarted && $canJoin) {
            $state = ['key' => 'upcoming', 'label' => 'Upcoming', 'tone' => 'pending', 'reason' => 'Enroll now and begin on the start date.'];
        } elseif ($canJoin) {
            $state = ['key' => 'available', 'label' => 'Open', 'tone' => 'success', 'reason' => 'Enrollment is open.'];
        } else {
            $state = ['key' => 'unavailable', 'label' => 'Unavailable', 'tone' => 'neutral', 'reason' => 'This campaign is not currently accepting enrollment.'];
        }

        return $state + [
            'has_access' => $hasAccess,
            'invited' => $invited,
            'enrolled' => $enrolled,
            'can_join' => $canJoin,
            'not_started' => $notStarted,
            'ended' => $ended,
            'full' => $full,
            'capacity' => $capacity,
            'spots_remaining' => $capacity > 0 ? max(0, $capacity - $enrolledCount) : null,
            'settings' => $settings,
            'timezone' => $timezone,
        ];
    }
}

if (!function_exists('tl_campaign_normalize')) {
    function tl_campaign_normalize(array $row): array
    {
        $state = tl_campaign_derive_state($row);
        $settings = $state['settings'];
        $ref = (string)($row['slug'] ?: $row['public_id'] ?: $row['id']);
        $requirements = $settings['requirements'] ?? [];
        if (is_string($requirements)) $requirements = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $requirements) ?: []));
        if (!is_array($requirements)) $requirements = [];
        $audience = trim((string)($settings['audience'] ?? ''));
        if ($audience === '') $audience = ucwords(str_replace('_', ' ', (string)($row['campaign_type'] ?? 'training')));

        return $row + [
            'ref' => $ref,
            'state' => $state,
            'requirements' => array_values(array_slice($requirements, 0, 12)),
            'audience_label' => $audience,
            'reward_display' => tl_campaign_money((int)($row['reward_value_cents'] ?? 0), (string)($row['reward_currency'] ?? 'USD')),
            'detail_href' => '/app/campaign-detail.php?id=' . rawurlencode($ref),
            'continue_href' => '/app/task-runner.php?campaign=' . rawurlencode($ref),
        ];
    }
}

if (!function_exists('tl_campaign_catalog')) {
    function tl_campaign_catalog(array $user, string $filter = 'available', string $query = ''): array
    {
        $pdo = function_exists('tl_db') ? tl_db() : null;
        if (!$pdo instanceof PDO || !tl_table_exists('training_campaigns')) return [];
        $userId = tl_campaign_user_id($user);
        $filter = in_array($filter, ['available', 'mine', 'completed', 'all'], true) ? $filter : 'available';
        $query = mb_strtolower(trim($query));

        try {
            $sql = "SELECT
                        c.*,
                        tp.id AS participant_id,
                        tp.public_id AS participant_public_id,
                        tp.status AS participant_status,
                        tp.joined_at,
                        (SELECT COUNT(*) FROM training_campaign_tasks t WHERE t.campaign_id=c.id AND t.status='active') AS task_count,
                        (SELECT COUNT(*) FROM training_participants ep WHERE ep.campaign_id=c.id AND ep.status NOT IN ('removed')) AS enrolled_count,
                        (SELECT rr.reward_label FROM training_reward_rules rr WHERE rr.campaign_id=c.id AND rr.status IN ('active','draft') ORDER BY FIELD(rr.status,'active','draft'),rr.id LIMIT 1) AS reward_label,
                        (SELECT rr.reward_value_cents FROM training_reward_rules rr WHERE rr.campaign_id=c.id AND rr.status IN ('active','draft') ORDER BY FIELD(rr.status,'active','draft'),rr.id LIMIT 1) AS reward_value_cents,
                        (SELECT rr.currency FROM training_reward_rules rr WHERE rr.campaign_id=c.id AND rr.status IN ('active','draft') ORDER BY FIELD(rr.status,'active','draft'),rr.id LIMIT 1) AS reward_currency
                    FROM training_campaigns c
                    LEFT JOIN training_participants tp ON tp.campaign_id=c.id AND tp.user_id=? AND tp.status<>'removed'
                    WHERE (c.visibility='published' OR tp.id IS NOT NULL OR c.owner_user_id=?)
                    ORDER BY
                        CASE WHEN tp.status='invited' THEN 0 WHEN tp.id IS NOT NULL AND tp.status='active' THEN 1 WHEN c.status='active' THEN 2 WHEN c.status='scheduled' THEN 3 ELSE 4 END,
                        COALESCE(c.starts_at,c.updated_at) DESC,
                        c.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $userId]);
            $rows = array_map('tl_campaign_normalize', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (Throwable $e) {
            return [];
        }

        return array_values(array_filter($rows, static function (array $campaign) use ($filter, $query): bool {
            $state = $campaign['state'];
            $matchesFilter = match ($filter) {
                'mine' => !empty($state['enrolled']) && $state['key'] !== 'completed',
                'completed' => $state['key'] === 'completed',
                'all' => true,
                default => in_array($state['key'], ['available', 'upcoming', 'invited'], true),
            };
            if (!$matchesFilter) return false;
            if ($query === '') return true;
            $haystack = mb_strtolower(implode(' ', [
                (string)($campaign['title'] ?? ''),
                (string)($campaign['summary'] ?? ''),
                (string)($campaign['description'] ?? ''),
                (string)($campaign['campaign_type'] ?? ''),
                (string)($campaign['audience_label'] ?? ''),
            ]));
            return str_contains($haystack, $query);
        }));
    }
}

if (!function_exists('tl_campaign_detail')) {
    function tl_campaign_detail(array $user, string $campaignRef): ?array
    {
        $campaignRef = tl_campaign_clean_ref($campaignRef);
        if ($campaignRef === '') return null;
        $pdo = function_exists('tl_db') ? tl_db() : null;
        if (!$pdo instanceof PDO || !tl_table_exists('training_campaigns')) return null;
        $userId = tl_campaign_user_id($user);

        try {
            $sql = "SELECT
                        c.*,
                        tp.id AS participant_id,
                        tp.public_id AS participant_public_id,
                        tp.status AS participant_status,
                        tp.joined_at,
                        (SELECT COUNT(*) FROM training_campaign_tasks t WHERE t.campaign_id=c.id AND t.status='active') AS task_count,
                        (SELECT COUNT(*) FROM training_participants ep WHERE ep.campaign_id=c.id AND ep.status<>'removed') AS enrolled_count,
                        (SELECT rr.reward_label FROM training_reward_rules rr WHERE rr.campaign_id=c.id AND rr.status IN ('active','draft') ORDER BY FIELD(rr.status,'active','draft'),rr.id LIMIT 1) AS reward_label,
                        (SELECT rr.reward_value_cents FROM training_reward_rules rr WHERE rr.campaign_id=c.id AND rr.status IN ('active','draft') ORDER BY FIELD(rr.status,'active','draft'),rr.id LIMIT 1) AS reward_value_cents,
                        (SELECT rr.currency FROM training_reward_rules rr WHERE rr.campaign_id=c.id AND rr.status IN ('active','draft') ORDER BY FIELD(rr.status,'active','draft'),rr.id LIMIT 1) AS reward_currency
                    FROM training_campaigns c
                    LEFT JOIN training_participants tp ON tp.campaign_id=c.id AND tp.user_id=? AND tp.status<>'removed'
                    WHERE (c.id=? OR c.public_id=? OR c.slug=?)
                      AND (c.visibility='published' OR tp.id IS NOT NULL OR c.owner_user_id=?)
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $numeric = ctype_digit($campaignRef) ? (int)$campaignRef : 0;
            $stmt->execute([$userId, $numeric, $campaignRef, $campaignRef, $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            $campaign = tl_campaign_normalize($row);

            $taskStmt = $pdo->prepare("SELECT id,public_id,position_no,day_no,task_type,title,instructions,proof_required,expected_duration_minutes,status FROM training_campaign_tasks WHERE campaign_id=? AND status='active' ORDER BY position_no,id");
            $taskStmt->execute([(int)$campaign['id']]);
            $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $context = !empty($campaign['state']['enrolled']) ? tl_app_participant_context((string)$campaign['ref'], $userId) : [];
            foreach ($tasks as &$task) {
                $taskId = (string)$task['id'];
                $proof = ($context['proofs_by_task'][$taskId] ?? [])[0] ?? null;
                $task['participant_status'] = function_exists('tl_product_task_status')
                    ? tl_product_task_status(is_array($proof) ? $proof : null)
                    : ['key' => $proof ? (string)($proof['status'] ?? 'submitted') : 'not_started', 'label' => $proof ? ucfirst((string)($proof['status'] ?? 'submitted')) : 'Not started', 'tone' => 'neutral'];
            }
            unset($task);

            $campaign['tasks'] = $tasks;
            $campaign['participant_context'] = $context;
            return $campaign;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('tl_campaign_flash_set')) {
    function tl_campaign_flash_set(string $type, string $message): void
    {
        if (function_exists('tl_security_session_start')) tl_security_session_start();
        $_SESSION['_tl_campaign_flash'] = [
            'type' => in_array($type, ['success', 'error', 'info'], true) ? $type : 'info',
            'message' => mb_substr(trim($message), 0, 400),
        ];
    }
}

if (!function_exists('tl_campaign_flash_take')) {
    function tl_campaign_flash_take(): ?array
    {
        if (function_exists('tl_security_session_start')) tl_security_session_start();
        $flash = $_SESSION['_tl_campaign_flash'] ?? null;
        unset($_SESSION['_tl_campaign_flash']);
        return is_array($flash) ? $flash : null;
    }
}
