<?php
require_once __DIR__ . '/labs-layout.php';
require_once __DIR__ . '/training-lab-design-assets.php';

if (!function_exists('tl_public_e')) {
    function tl_public_e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('tl_public_asset')) {
    function tl_public_asset(string $key): string { return tl_design_asset_url($key); }
}
if (!function_exists('tl_public_img')) {
    function tl_public_img(string $key, string $class = '', string $alt = ''): string
    {
        $url = tl_public_asset($key);
        if ($url === '') return '';
        $flat = function_exists('tl_design_flat_assets') ? tl_design_flat_assets() : [];
        $label = $alt !== '' ? $alt : (string)($flat[$key]['label'] ?? 'Training Lab visual');
        return '<img class="' . tl_public_e($class) . '" src="' . tl_public_e($url) . '" alt="' . tl_public_e($label) . '" loading="lazy" decoding="async">';
    }
}
if (!function_exists('tl_public_site_header')) {
    function tl_public_site_header(string $title, string $description = '', string $active = '', string $authLabel = 'Sign In', string $authHref = '/signin.php'): void
    {
        tl_security_headers(false);
        $nav = [
            'product'=>['/how-it-works.php','Product'],
            'pricing'=>['/pricing.php','Pricing'],
            'about'=>['/about.php','About'],
            'blog'=>['/blog.php','Blog'],
        ];
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="<?php echo tl_public_e(tl_security_csrf_token()); ?>"><title><?php echo tl_public_e($title); ?></title><meta name="description" content="<?php echo tl_public_e($description); ?>"><link rel="stylesheet" href="<?php echo tl_public_e(labs_asset('css/public-template.css')); ?>"><link rel="stylesheet" href="<?php echo tl_public_e(labs_asset('css/security-accessibility.css')); ?>"></head><body><a class="tl-skip-link" href="#main-content">Skip to main content</a><div class="tl-shell tl-template-shell"><header class="tl-container tl-header tl-template-header"><a class="tl-brand tl-template-brand" href="<?php echo tl_public_e(labs_url('/')); ?>" aria-label="Training Lab home"><span class="tl-logo-flask"><?php echo tl_public_img('flask', '', 'Training Lab'); ?></span><span class="tl-brand-text"><span class="tl-brand-title">Training Lab</span><span class="tl-brand-sub">by Microgifter</span></span></a><button class="tl-menu-toggle" type="button" aria-label="Open menu" aria-controls="tl-primary-nav" aria-expanded="false" data-tl-menu-open><span></span><span></span><span></span></button><nav class="tl-nav tl-template-nav" id="tl-primary-nav" aria-label="Primary navigation"><button class="tl-nav-close" type="button" aria-label="Close menu" data-tl-menu-close>&times;</button><?php foreach ($nav as $key => [$href,$label]): $isActive=$active===$key; ?><a class="<?php echo $isActive ? 'is-active' : ''; ?>" href="<?php echo tl_public_e(labs_url($href)); ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo tl_public_e($label); ?><?php echo $key === 'product' ? ' <span aria-hidden="true">⌄</span>' : ''; ?></a><?php endforeach; ?><a class="tl-btn tl-btn-outline" href="<?php echo tl_public_e(labs_url($authHref)); ?>"><?php echo tl_public_e($authLabel); ?></a></nav><div class="tl-nav-overlay" data-tl-menu-close></div></header><?php
    }
}
if (!function_exists('tl_public_site_footer')) {
    function tl_public_site_footer(): void
    {
        ?><footer class="tl-container tl-template-footer"><a class="tl-brand tl-template-brand" href="<?php echo tl_public_e(labs_url('/')); ?>"><span class="tl-logo-flask tl-logo-flask-small"><?php echo tl_public_img('flask', '', 'Training Lab'); ?></span><span class="tl-brand-text"><span class="tl-brand-title">Training Lab</span><span class="tl-brand-sub">by Microgifter</span></span></a><nav><a href="<?php echo tl_public_e(labs_url('/about.php')); ?>">About</a><a href="<?php echo tl_public_e(labs_url('/pricing.php')); ?>">Pricing</a><a href="<?php echo tl_public_e(labs_url('/blog.php')); ?>">Blog</a><a href="<?php echo tl_public_e(labs_url('/contact.php')); ?>">Contact</a></nav><span>© <?php echo date('Y'); ?> Microgifter. All rights reserved.</span></footer><script src="<?php echo tl_public_e(labs_asset('js/public-template.js')); ?>"></script></div></body></html><?php
    }
}
if (!function_exists('tl_public_feature_item')) {
    function tl_public_feature_item(string $icon, string $title, string $copy): string
    {
        return '<div class="tl-feature-item"><span>' . tl_public_img($icon) . '</span><div><strong>' . tl_public_e($title) . '</strong><p>' . tl_public_e($copy) . '</p></div></div>';
    }
}
if (!function_exists('tl_public_auth_status')) {
    function tl_public_auth_status(?string $error, ?array $result): void
    {
        if ($error) echo '<div class="tl-form-alert tl-form-error" role="alert"><strong>Account issue</strong><p>' . tl_public_e($error) . '</p></div>';
        if ($result) echo '<div class="tl-form-alert tl-form-success" role="status"><strong>Account action complete</strong><p>' . tl_public_e((string)($result['message'] ?? $result['sync_status'] ?? 'Your account session was updated.')) . '</p></div>';
    }
}
if (!function_exists('tl_public_page')) {
    function tl_public_page(array $cfg): void
    {
        $active = (string)($cfg['active'] ?? '');
        tl_public_site_header((string)($cfg['title'] ?? 'Training Lab'), (string)($cfg['description'] ?? 'Training Lab by Microgifter'), $active, (string)($cfg['auth_label'] ?? 'Sign In'), (string)($cfg['auth_href'] ?? '/signin.php'));
        $hero = (string)($cfg['hero_asset'] ?? 'hero_task_reward');
        $support = (string)($cfg['support_asset'] ?? 'participant_dashboard');
        ?><main id="main-content" tabindex="-1"><section class="tl-container tl-template-hero"><div class="tl-template-copy"><p class="tl-kicker"><?php echo tl_public_e((string)($cfg['eyebrow'] ?? 'Training Lab')); ?></p><h1><?php echo tl_public_e((string)($cfg['headline'] ?? 'Proof-based training with rewards.')); ?></h1><p><?php echo tl_public_e((string)($cfg['copy'] ?? 'Create training challenges, collect proof, review work, and issue rewards through a safe standalone app.')); ?></p><div class="tl-actions"><a class="tl-btn tl-btn-primary" href="<?php echo tl_public_e(labs_url((string)($cfg['primary_href'] ?? '/signup.php'))); ?>"><?php echo tl_public_e((string)($cfg['primary_label'] ?? 'Start Training')); ?></a><a class="tl-btn tl-btn-secondary" href="<?php echo tl_public_e(labs_url((string)($cfg['secondary_href'] ?? '/how-it-works.php'))); ?>"><?php echo tl_public_e((string)($cfg['secondary_label'] ?? 'See How It Works')); ?></a></div></div><div class="tl-template-art"><?php echo tl_public_img($hero, 'tl-template-main-art'); ?></div></section><?php
        if (!empty($cfg['stats'])) { echo '<section class="tl-container tl-template-stat-band">'; foreach ($cfg['stats'] as $stat) { echo '<div><span>' . tl_public_e((string)($stat[0] ?? '')) . '</span><strong>' . tl_public_e((string)($stat[1] ?? '')) . '</strong><small>' . tl_public_e((string)($stat[2] ?? '')) . '</small></div>'; } echo '</section>'; }
        if (!empty($cfg['steps'])) { echo '<section class="tl-section"><div class="tl-container"><div class="tl-section-head"><h2>' . tl_public_e((string)($cfg['section_title'] ?? 'How it works')) . '</h2><p>' . tl_public_e((string)($cfg['section_copy'] ?? 'A simple workflow for action, evidence, review, and reward.')) . '</p></div><div class="tl-mock-steps">'; foreach ($cfg['steps'] as $step) { echo '<article>' . tl_public_img((string)($step['icon'] ?? 'check_list')) . '<h3>' . tl_public_e((string)($step['title'] ?? 'Step')) . '</h3><p>' . tl_public_e((string)($step['copy'] ?? '')) . '</p></article>'; } echo '</div></div></section>'; }
        if (!empty($cfg['cards'])) { echo '<section class="tl-section tl-section-soft"><div class="tl-container"><div class="tl-template-cards">'; foreach ($cfg['cards'] as $card) { $asset=(string)($card['image'] ?? $card['icon'] ?? 'verified'); echo '<article><div class="tl-card-visual">' . tl_public_img($asset) . '</div><h3>' . tl_public_e((string)($card['title'] ?? 'Feature')) . '</h3><p>' . tl_public_e((string)($card['copy'] ?? '')) . '</p>'; if (!empty($card['href'])) echo '<a class="tl-text-link" href="' . tl_public_e(labs_url((string)$card['href'])) . '">' . tl_public_e((string)($card['label'] ?? 'Read more')) . '</a>'; echo '</article>'; } echo '</div></div></section>'; }
        if (!empty($cfg['split'])) { $s=$cfg['split']; echo '<section class="tl-section"><div class="tl-container tl-template-split"><div>' . tl_public_img((string)($s['asset'] ?? $support), 'tl-template-split-art') . '</div><div><p class="tl-kicker">' . tl_public_e((string)($s['eyebrow'] ?? 'Connected workflow')) . '</p><h2>' . tl_public_e((string)($s['title'] ?? 'Built for teams')) . '</h2><p>' . tl_public_e((string)($s['copy'] ?? '')) . '</p><a class="tl-btn tl-btn-primary" href="' . tl_public_e(labs_url((string)($s['href'] ?? '/app/index.php'))) . '">' . tl_public_e((string)($s['label'] ?? 'Open App')) . '</a></div></div></section>'; }
        ?><section class="tl-section"><div class="tl-container"><div class="tl-newsletter-cta"><?php echo tl_public_img((string)($cfg['cta_asset'] ?? 'success_visual')); ?><div><h2><?php echo tl_public_e((string)($cfg['cta_title'] ?? 'Ready to run the Training Lab?')); ?></h2><p><?php echo tl_public_e((string)($cfg['cta_copy'] ?? 'Use the same Microgifter account on labs.microgifter.com and microgifter.com.')); ?></p></div><a class="tl-btn tl-btn-primary" href="<?php echo tl_public_e(labs_url((string)($cfg['cta_href'] ?? '/signup.php'))); ?>"><?php echo tl_public_e((string)($cfg['cta_label'] ?? 'Start Training')); ?></a></div></div></section></main><?php
        tl_public_site_footer();
    }
}
