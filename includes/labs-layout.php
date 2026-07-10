<?php
require_once __DIR__ . '/training-lab-security.php';
tl_security_headers(false);

$componentPath = __DIR__ . '/labs-components.php';
if (is_file($componentPath)) require_once $componentPath;
$designPath = __DIR__ . '/training-lab-design-assets.php';
if (is_file($designPath)) require_once $designPath;
$productShellPath = __DIR__ . '/training-lab-product-shell.php';
if (is_file($productShellPath)) require_once $productShellPath;

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
    function labs_core_app_nav(?array $user = null): array
    {
        $role = function_exists('tl_product_role') ? tl_product_role($user) : 'participant';
        return function_exists('tl_product_app_nav') ? tl_product_app_nav($role) : [];
    }
}

if (!function_exists('labs_core_admin_nav')) {
    function labs_core_admin_nav(?array $user = null): array
    {
        $role = function_exists('tl_product_role') ? tl_product_role($user) : 'reviewer';
        return function_exists('tl_product_admin_nav') ? tl_product_admin_nav($role) : [];
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
        $labsUser = function_exists('tl_product_require_page_access')
            ? tl_product_require_page_access($page)
            : (function_exists('tl_auth_current_user') ? tl_auth_current_user() : null);
        $role = function_exists('tl_product_role') ? tl_product_role($labsUser) : ($labsUser['role'] ?? 'guest');
        $bodyClass = 'labs-shell labs-section-' . preg_replace('/[^a-z0-9\-]/i', '', $section) . ' labs-role-' . preg_replace('/[^a-z0-9\-]/i', '', (string)$role);
        $topNav = function_exists('tl_product_top_nav') ? tl_product_top_nav($labsUser) : [];
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
  <link rel="stylesheet" href="<?php echo htmlspecialchars(labs_asset('css/product-shell.css'), ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars(labs_asset('css/campaign-experience.css'), ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars(labs_asset('css/task-experience.css'), ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars(labs_asset('css/reward-management.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="<?php echo htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8'); ?>">
  <a class="labs-skip-link" href="#main-content">Skip to main content</a>
  <div class="labs-page">
    <header class="labs-topbar">
      <a class="labs-brand" href="<?php echo htmlspecialchars(labs_url($labsUser ? '/app/index.php' : '/'), ENT_QUOTES, 'UTF-8'); ?>">
        <span class="labs-brand-mark" aria-hidden="true">TL</span>
        <span><strong>Training Lab</strong><small>Proof-based training</small></span>
      </a>
      <button class="labs-menu-toggle" type="button" aria-label="Open menu" aria-controls="labs-primary-nav" aria-expanded="false" data-labs-menu-open><span></span><span></span><span></span></button>
      <nav class="labs-public-nav" id="labs-primary-nav" aria-label="Main navigation">
        <button class="labs-nav-close" type="button" aria-label="Close menu" data-labs-menu-close>&times;</button>
        <?php foreach ($topNav as $key => [$href, $label]): ?>
          <?php labs_nav_link($active, (string)$key, labs_url((string)$href), (string)$label, $key === 'signup' ? 'labs-nav-cta' : ''); ?>
        <?php endforeach; ?>
        <?php if ($labsUser): ?>
          <a class="labs-account-chip<?php echo labs_is_active($active, 'account'); ?>" href="<?php echo htmlspecialchars(labs_url('/account.php'), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $active === 'account' ? ' aria-current="page"' : ''; ?>>
            <span><?php echo labs_e((string)($labsUser['name'] ?? 'Account')); ?></span>
            <small><?php echo labs_e((string)($labsUser['role_label'] ?? (function_exists('tl_product_role_label') ? tl_product_role_label((string)$role) : ucfirst((string)$role)))); ?></small>
          </a>
        <?php endif; ?>
      </nav>
      <div class="labs-nav-overlay" data-labs-menu-close></div>
    </header>
<?php
        if ($section === 'app' || $section === 'admin') labs_workspace_start($section, $active, $labsUser);
        else echo '<main id="main-content" class="labs-main" tabindex="-1">';
    }
}

if (!function_exists('labs_workspace_start')) {
    function labs_workspace_start(string $section, string $active, ?array $user = null): void
    {
        $isAdmin = $section === 'admin';
        $groups = $isAdmin ? labs_core_admin_nav($user) : labs_core_app_nav($user);
        $role = function_exists('tl_product_role') ? tl_product_role($user) : 'participant';
        $roleLabel = function_exists('tl_product_role_label') ? tl_product_role_label($role) : ucfirst($role);
        ?>
    <div class="labs-workspace">
      <button class="labs-workspace-toggle" type="button" aria-controls="labs-workspace-nav" aria-expanded="false" data-labs-workspace-open><?php echo $isAdmin ? 'Manage Menu' : 'Training Menu'; ?></button>
      <aside class="labs-sidebar" id="labs-workspace-nav">
        <div class="labs-sidebar-head">
          <div><div class="labs-sidebar-label"><?php echo $isAdmin ? 'Training Management' : 'My Training'; ?></div><strong><?php echo labs_e($roleLabel); ?></strong></div>
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
        <div class="labs-sidebar-profile">
          <span><?php echo labs_e((string)($user['name'] ?? 'Training account')); ?></span>
          <small><?php echo labs_e($roleLabel); ?> access</small>
          <a href="<?php echo htmlspecialchars(labs_url('/account.php'), ENT_QUOTES, 'UTF-8'); ?>">View account</a>
        </div>
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
    <footer class="labs-footer"><span>Training Lab by Microgifter</span><nav><a href="<?php echo htmlspecialchars(labs_url('/about.php'), ENT_QUOTES, 'UTF-8'); ?>">About</a><a href="<?php echo htmlspecialchars(labs_url('/how-it-works.php'), ENT_QUOTES, 'UTF-8'); ?>">How It Works</a><a href="<?php echo htmlspecialchars(labs_url('/contact.php'), ENT_QUOTES, 'UTF-8'); ?>">Contact</a></nav><span>Campaign → Proof → Reward</span></footer>
  </div>
  <script src="<?php echo htmlspecialchars(labs_asset('js/labs.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
<?php
    }
}
