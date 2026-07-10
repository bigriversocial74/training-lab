<?php
/**
 * Role-aware Training Lab product shell.
 *
 * This layer controls page access and navigation only. It does not create a
 * second account, role, reward, wallet, payment, claim, or redemption system.
 */
require_once __DIR__ . '/training-lab-auth-gate.php';

if (!function_exists('tl_product_role')) {
    function tl_product_role(?array $user): string
    {
        if (!$user) return 'guest';
        $role = function_exists('tl_security_trusted_role')
            ? tl_security_trusted_role($user)
            : strtolower((string)($user['role'] ?? 'participant'));
        return in_array($role, ['participant', 'coach', 'reviewer', 'manager', 'admin'], true)
            ? $role
            : 'participant';
    }
}

if (!function_exists('tl_product_role_label')) {
    function tl_product_role_label(string $role): string
    {
        return [
            'participant' => 'Participant',
            'coach' => 'Coach',
            'reviewer' => 'Reviewer',
            'manager' => 'Merchant Manager',
            'admin' => 'Administrator',
            'guest' => 'Guest',
        ][$role] ?? 'Participant';
    }
}

if (!function_exists('tl_product_role_rank')) {
    function tl_product_role_rank(string $role): int
    {
        return ['guest' => 0, 'participant' => 1, 'coach' => 2, 'reviewer' => 2, 'manager' => 3, 'admin' => 4][$role] ?? 0;
    }
}

if (!function_exists('tl_product_role_allows')) {
    function tl_product_role_allows(string $role, string $requiredRole): bool
    {
        return tl_product_role_rank($role) >= tl_product_role_rank($requiredRole);
    }
}

if (!function_exists('tl_product_current_request_path')) {
    function tl_product_current_request_path(): string
    {
        $path = (string)($_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? '/app/index.php');
        $path = str_replace(["\r", "\n", "\0"], '', $path);
        if ($path === '' || !str_starts_with($path, '/') || preg_match('#^(?:https?:)?//#i', $path)) {
            return '/app/index.php';
        }
        return substr($path, 0, 1200);
    }
}

if (!function_exists('tl_product_required_role')) {
    function tl_product_required_role(array $page): string
    {
        $explicit = strtolower((string)($page['required_role'] ?? ''));
        if (in_array($explicit, ['participant', 'coach', 'reviewer', 'manager', 'admin'], true)) return $explicit;

        $section = (string)($page['section'] ?? 'public');
        if ($section === 'app') return 'participant';
        if ($section !== 'admin') return 'guest';

        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
        $reviewerPages = [
            'index.php',
            'review-queue.php',
            'review-workbench.php',
            'review-inspector.php',
            'participant-inspector.php',
            'task-inspector.php',
        ];
        return in_array($script, $reviewerPages, true) ? 'reviewer' : 'manager';
    }
}

if (!function_exists('tl_product_redirect')) {
    function tl_product_redirect(string $path, int $status = 302): void
    {
        $location = function_exists('labs_url') ? labs_url($path) : $path;
        if (!headers_sent()) header('Location: ' . $location, true, $status);
        exit;
    }
}

if (!function_exists('tl_product_require_page_access')) {
    function tl_product_require_page_access(array $page): ?array
    {
        $section = (string)($page['section'] ?? 'public');
        if (!in_array($section, ['app', 'admin'], true)) {
            return function_exists('tl_auth_current_user') ? tl_auth_current_user() : null;
        }

        $user = function_exists('tl_auth_current_user') ? tl_auth_current_user() : null;
        if (!$user && function_exists('tl_security_developer_key_valid') && tl_security_developer_key_valid()) {
            $user = ['id' => 'developer-key', 'name' => 'Developer', 'role' => 'admin', 'source' => 'developer_key'];
        }

        if (!$user) {
            $next = rawurlencode(tl_product_current_request_path());
            tl_product_redirect('/signin.php?next=' . $next);
        }

        $role = tl_product_role($user);
        $requiredRole = tl_product_required_role($page);
        if (!tl_product_role_allows($role, $requiredRole)) {
            $destination = tl_product_role_allows($role, 'reviewer') ? '/admin/index.php' : '/app/index.php';
            tl_product_redirect($destination . '?access=denied');
        }

        $user['role'] = $role;
        $user['role_label'] = tl_product_role_label($role);
        return $user;
    }
}

if (!function_exists('tl_product_app_nav')) {
    function tl_product_app_nav(string $role): array
    {
        $groups = [
            'My Training' => [
                'app-dashboard' => ['/app/index.php', 'Home'],
                'app-campaigns' => ['/app/campaigns.php', 'Campaigns'],
                'app-task-runner' => ['/app/task-runner.php', 'Tasks'],
                'app-progress-map' => ['/app/progress-map.php', 'Progress'],
                'app-rewards' => ['/app/rewards.php', 'Rewards'],
            ],
            'Account' => [
                'app-resource-hub' => ['/app/resource-hub.php', 'Resources'],
                'account' => ['/account.php', 'Account'],
            ],
        ];
        if (tl_product_role_allows($role, 'reviewer')) {
            $groups[tl_product_role_allows($role, 'manager') ? 'Manage' : 'Review'] = [
                'admin-overview' => ['/admin/index.php', tl_product_role_allows($role, 'manager') ? 'Manage Training' : 'Review Overview'],
                'admin-review-workbench' => ['/admin/review-workbench.php', 'Review Proof'],
            ];
        }
        return $groups;
    }
}

if (!function_exists('tl_product_admin_nav')) {
    function tl_product_admin_nav(string $role): array
    {
        if (!tl_product_role_allows($role, 'manager')) {
            return [
                'Review' => [
                    'admin-overview' => ['/admin/index.php', 'Review Overview'],
                    'admin-review' => ['/admin/review-queue.php', 'Review Queue'],
                    'admin-review-workbench' => ['/admin/review-workbench.php', 'Review Proof'],
                ],
                'My Training' => [
                    'app-dashboard' => ['/app/index.php', 'Participant Home'],
                    'account' => ['/account.php', 'Account'],
                ],
            ];
        }

        $groups = [
            'Training Operations' => [
                'admin-overview' => ['/admin/index.php', 'Dashboard'],
                'admin-campaigns' => ['/admin/campaigns.php', 'Campaigns'],
                'admin-cohort-manager' => ['/admin/cohort-manager.php', 'Participants'],
                'admin-review-workbench' => ['/admin/review-workbench.php', 'Reviews'],
            ],
            'Rewards & Insights' => [
                'admin-reward-rules' => ['/admin/reward-rules.php', 'Reward Rules'],
                'admin-reward-bridge' => ['/admin/reward-bridge.php', 'Fulfillment'],
                'admin-analytics' => ['/admin/analytics.php', 'Analytics'],
            ],
            'Account' => [
                'app-dashboard' => ['/app/index.php', 'My Training'],
                'account' => ['/account.php', 'Account'],
            ],
        ];

        if ($role === 'admin') {
            $groups['System'] = [
                'admin-reward-operations' => ['/admin/reward-operations.php', 'Advanced Rewards'],
                'admin-permissions' => ['/admin/permissions.php', 'Access'],
                'admin-backend-readiness' => ['/admin/backend-readiness.php', 'System Health'],
                'admin-db-health' => ['/admin/db-health.php', 'Database'],
                'admin-deployment-acceptance' => ['/admin/deployment-acceptance.php', 'Deployment'],
            ];
        }
        return $groups;
    }
}

if (!function_exists('tl_product_top_nav')) {
    function tl_product_top_nav(?array $user): array
    {
        if (!$user) {
            return [
                'home' => ['/', 'Home'],
                'how-it-works' => ['/how-it-works.php', 'How It Works'],
                'pricing' => ['/pricing.php', 'Pricing'],
                'signin' => ['/signin.php', 'Sign In'],
                'signup' => ['/signup.php', 'Start Training'],
            ];
        }

        $role = tl_product_role($user);
        $items = [
            'app-dashboard' => ['/app/index.php', 'My Training'],
            'app-campaigns' => ['/app/campaigns.php', 'Campaigns'],
            'app-rewards' => ['/app/rewards.php', 'Rewards'],
        ];
        if (tl_product_role_allows($role, 'reviewer')) {
            $items['admin-overview'] = ['/admin/index.php', tl_product_role_allows($role, 'manager') ? 'Manage' : 'Review'];
        }
        return $items;
    }
}
