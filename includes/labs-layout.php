<?php
require_once __DIR__ . '/training-lab-security.php';
tl_security_headers(false);

$componentPath = __DIR__ . '/labs-components.php';
if (is_file($componentPath)) require_once $componentPath;
$designPath = __DIR__ . '/training-lab-design-assets.php';
if (is_file($designPath)) require_once $designPath;

if (!function_exists('labs_base_path')) {
    function labs_base_path(): string
    {
        static $base = null;
        if ($base !== null) return $base;
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = str_replace('\\', '/', dirname($script));
        $dir = $dir === '/' || $dir === '.' ? '' : rtrim($dir, '/');
        if (preg_match('#/(admin|app|api)(/|$)#', $dir)) {
            $dir = (string)preg_replace('#/(admin|app|api)(/.*)?$#', '', $dir);
        }
        return $base = $dir === '/' ? '' : $dir;
    }
}

if (!function_exists('labs_url')) {
    function labs_url(string $path = ''): string
    {
        $path = trim($path);
        if ($path === '') return labs_base_path() !== '' ? labs_base_path() . '/' : '/';
        if (preg_match('#^(https?:)?//#', $path) || str_starts_with($path, 'mailto:') || str_starts_with($path, 'tel:') || str_starts_with($path, '#')) return $path;
        $base = labs_base_path();
        $path = '/' . ltrim($path, '/');
        return ($base === '' ? '' : $base) . $path;
    }
}

if (!function_exists('labs_asset')) {
    function labs_asset(string $path): string { return labs_url('/assets/' . ltrim($path, '/')); }
}

if (!function_exists('labs_is_active')) {
    function labs_is_active(string $current, string $target): string { return $current === $target ? ' is-active' : ''; }
}

if (!function_exists('labs_nav_link')) {
    function labs_nav_link(string $active, string $key, string $href, string $label, string $class = ''): void
    {
        $isActive = $active === $key;
        $classes = trim($class . ($isActive ? ' is-active' : ''));
        echo '<a class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . ($isActive ? ' aria-current="page"' : '') . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }
}

if (!function_exists('labs_core_app_nav')) {
    function labs_core_app_nav(): array
    {
        return [
            'Start' => [
                'app-dashboard' => ['/app/index.php', 'Dashboard'],
                'account' => ['/account.php', 'Account'],
                'app-workspace' => ['/app/workspace.php', 'Workspace'],
                'app-launchpad' => ['/app/launchpad.php', 'Launchpad'],
            ],
            'Build & Run' => [
                'app-campaign-builder' => ['/app/campaign-builder.php', 'Campaign Builder'],
                'app-campaigns' => ['/app/campaigns.php', 'Campaigns'],
                'app-participant-portal' => ['/app/participant-portal.php', 'Mission Control'],
                'app-task-runner' => ['/app/task-runner.php', 'Task Runner'],
                'app-tasks' => ['/app/proof-upload.php', 'Proof Upload'],
            ],
            'Progress' => [
                'app-flow-board' => ['/app/flow-board.php', 'Flow Board'],
                'app-progress-map' => ['/app/progress-map.php', 'Progress Map'],
                'app-rewards' => ['/app/rewards.php', 'Rewards'],
                'app-resource-hub' => ['/app/resource-hub.php', 'Resource Hub'],
            ],
        ];
    }
}

if (!function_exists('labs_core_admin_nav')) {
    function labs_core_admin_nav(): array
    {
        return [
            'Command' => [
                'admin-overview' => ['/admin/index.php', 'Overview'],
                'admin-command-center' => ['/admin/command-center.php', 'Command Center'],
                'admin-flow-control' => ['/admin/flow-control.php', 'Flow Control'],
                'admin-backend-readiness' => ['/admin/backend-readiness.php', 'Backend Readiness'],
                'admin-reward-bridge' => ['/admin/reward-bridge.php', 'Reward Bridge'],
            ],
            'Operations' => [
                'admin-campaigns' => ['/admin/campaigns.php', 'Campaigns'],
                'admin-campaign-inspector' => ['/admin/campaign-inspector.php', 'Campaign Inspector'],
                'admin-cohort-manager' => ['/admin/cohort-manager.php', 'Cohort Manager'],
                'admin-review' => ['/admin/review-queue.php', 'Review Queue'],
                'admin-review-workbench' => ['/admin/review-workbench.php', 'Stage 885 Review Workflow'],
            ],
            'Access & QA' => [
                'admin-permissions' => ['/admin/permissions.php', 'Roles & Permissions'],
                'admin-reporting-center' => ['/admin/reporting-center.php', 'Reporting Center'],
                'admin-event-timeline' => ['/admin/event-timeline.php', 'Event Timeline'],
                'admin-db-health' => ['/admin/db-health.php', 'DB Health'],
                'admin-deployment-acceptance' => ['/admin/deployment-acceptance.php', 'Deployment QA'],
                'admin-live-smoke' => ['/admin/live-smoke.php', 'Live Smoke'],
                'admin-adapter-readiness' => ['/admin/adapter-readiness.php', 'Adapter Readiness'],
                'admin-route-check' => ['/admin/route-check.php', 'Route Check'],
            ],
        ];
    }
}

if (!function_exists('labs_flatten_nav')) {
    function labs_flatten_nav(array $groups): array
    {
        $flat = [];
        foreach ($groups as $items) foreach ($items as $key => $item) $flat[$key] = $item;
        return $flat;
    }
}

if (!function_exists('labs_page_start')) {
    function labs_page_start(array $page = []): void
    {
        $title = $page['title'] ?? 'Training Lab by Microgifter';
        $section = $page['section'] ?? 'public';
        $active = $page['active'] ?? '';
        $bodyClass = 'labs-shell labs-section-' . preg_replace('/[^a-z0-9\-]/i', '', $section);
        ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?php echo htmlspecialchars(tl_security_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
  <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars(labs_asset('css/labs.css'), ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars(labs_asset('css/security-accessibility.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="<?php echo htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8'); ?>">
  <a class="labs-skip-link" href="#main-content">Skip to main content</a>
  <div class="labs-page">
    <header class="labs-topbar">
      <a class="labs-brand" href="<?php echo htmlspecialchars(labs_url('/'), ENT_QUOTES, 'UTF-8'); ?>">
        <span class="labs-brand-mark">TL</span>
        <span><strong>Training Lab</strong><small>standalone script</small></span>
      </a>
      <button class="labs-menu-toggle" type="button" aria-label="Open menu" aria-controls="labs-primary-nav" aria-expanded="false" data-labs-menu-open><span></span><span></span><span></span></button>
      <nav class="labs-public-nav" id="labs-primary-nav" aria-label="Main navigation">
        <button class="labs-nav-close" type="button" aria-label="Close menu" data-labs-menu-close>&times;</button>
        <?php $labsUser = function_exists('tl_auth_current_user') ? tl_auth_current_user() : null; ?>
        <?php labs_nav_link($active, 'home', labs_url('/'), 'Home'); ?>
        <?php labs_nav_link($active, 'app-dashboard', labs_url('/app/index.php'), 'App'); ?>
        <?php labs_nav_link($active, 'app-flow-board', labs_url('/app/flow-board.php'), 'Flow'); ?>
        <?php labs_nav_link($active, 'app-task-runner', labs_url('/app/task-runner.php'), 'Run'); ?>
        <?php labs_nav_link($active, 'app-rewards', labs_url('/app/rewards.php'), 'Rewards'); ?>
        <?php labs_nav_link($active, 'admin-command-center', labs_url('/admin/command-center.php'), 'Admin'); ?>
        <?php labs_nav_link($active, 'admin-backend-readiness', labs_url('/admin/backend-readiness.php'), 'Backend'); ?>
        <?php if ($labsUser): ?>
          <a class="labs-account-chip<?php echo labs_is_active($active, 'account'); ?>" href="<?php echo htmlspecialchars(labs_url('/account.php'), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $active === 'account' ? ' aria-current="page"' : ''; ?>><span><?php echo labs_e((string)($labsUser['name'] ?? 'Account')); ?></span><small><?php echo labs_e((string)($labsUser['role'] ?? 'participant')); ?></small></a>
        <?php else: ?>
          <?php labs_nav_link($active, 'signin', labs_url('/signin.php'), 'Login'); ?>
          <?php labs_nav_link($active, 'signup', labs_url('/signup.php'), 'Start Training', 'labs-nav-cta'); ?>
        <?php endif; ?>
      </nav>
      <div class="labs-nav-overlay" data-labs-menu-close></div>
    </header>
<?php
        if ($section === 'app' || $section === 'admin') labs_workspace_start($section, $active);
        else echo '<main id="main-content" class="labs-main" tabindex="-1">';
    }
}

if (!function_exists('labs_workspace_start')) {
    function labs_workspace_start(string $section, string $active): void
    {
        $isAdmin = $section === 'admin';
        $groups = $isAdmin ? labs_core_admin_nav() : labs_core_app_nav();
        ?>
    <div class="labs-workspace">
      <button class="labs-workspace-toggle" type="button" aria-controls="labs-workspace-nav" aria-expanded="false" data-labs-workspace-open><?php echo $isAdmin ? 'Admin Menu' : 'App Menu'; ?></button>
      <aside class="labs-sidebar" id="labs-workspace-nav">
        <div class="labs-sidebar-head">
          <div><div class="labs-sidebar-label"><?php echo $isAdmin ? 'Training Lab Admin' : 'Training Lab App'; ?></div><strong><?php echo $isAdmin ? 'Core backend' : 'Core workflow'; ?></strong></div>
          <button class="labs-sidebar-close" type="button" aria-label="Close workspace menu" data-labs-workspace-close>&times;</button>
        </div>
        <nav aria-label="Workspace navigation">
          <?php foreach ($groups as $groupLabel => $items): ?>
            <div class="labs-sidebar-group-label"><?php echo htmlspecialchars((string)$groupLabel, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php foreach ($items as $key => [$href, $label]): $isActive = $active === $key; ?>
              <a class="<?php echo $isActive ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(labs_url($href), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </nav>
        <div class="labs-sidebar-note">Focused core menus. Account sync, roles, workflow, review, and diagnostics stay active.</div>
      </aside>
      <div class="labs-workspace-overlay" data-labs-workspace-close></div>
      <main id="main-content" class="labs-main labs-workspace-main" tabindex="-1">
<?php
    }
}

if (!function_exists('labs_page_end')) {
    function labs_page_end(array $page = []): void
    {
        $section = $page['section'] ?? 'public';
        if ($section === 'app' || $section === 'admin') echo "      </main>\n    </div>\n"; else echo "    </main>\n";
        ?>
    <footer class="labs-footer"><span>Training Lab by Microgifter</span><nav><a href="<?php echo htmlspecialchars(labs_url('/about.php'), ENT_QUOTES, 'UTF-8'); ?>">About</a><a href="<?php echo htmlspecialchars(labs_url('/how-it-works.php'), ENT_QUOTES, 'UTF-8'); ?>">How It Works</a><a href="<?php echo htmlspecialchars(labs_url('/contact.php'), ENT_QUOTES, 'UTF-8'); ?>">Contact</a></nav><span>Standalone safe mode</span></footer>
  </div>
  <script src="<?php echo htmlspecialchars(labs_asset('js/labs.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
<?php
    }
}
