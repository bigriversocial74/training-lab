<?php
require_once __DIR__ . '/includes/labs-layout.php';
require_once __DIR__ . '/includes/training-lab-pilot-communications.php';
tl_security_headers(false);
tl_security_session_start();
$token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
$error = null; $saved = null; $accountLinkId = 0; $preference = ['reminder_enabled'=>1];
try {
    $accountLinkId = tl_notifications_verify_unsubscribe_token($token);
    $pdo = tl_require_db();
    $check = $pdo->prepare("SELECT id FROM training_account_links WHERE id=? AND link_status='active' LIMIT 1");
    $check->execute([$accountLinkId]);
    if (!(int)$check->fetchColumn()) throw new TlHttpException('The linked account is unavailable.', 410, 'account_link_unavailable');
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        tl_security_validate_origin();
        tl_security_rate_limit('notification_preferences', 10, 300);
        tl_security_verify_csrf($_POST);
        $setting = tl_action_enum($_POST['reminder_setting'] ?? '', ['enabled','disabled'], '');
        if ($setting === '') throw new TlHttpException('Select a valid reminder preference.', 422, 'reminder_preference_invalid');
        $saved = tl_notifications_update_preference($accountLinkId, $setting === 'enabled');
    }
    $preference = tl_notifications_preference($pdo, $accountLinkId);
} catch (Throwable $exception) {
    [$payload] = tl_security_error_payload($exception);
    $error = (string)$payload['error'];
}
labs_page_start(['title'=>'Notification Preferences | Training Lab','section'=>'public','active'=>'notification-preferences']);
?>
<section class="labs-product-hero"><article class="labs-product-hero-main"><span class="labs-product-kicker">Notification preferences</span><h1>Control optional Training Lab reminders.</h1><p>Transactional updates about submitted proof, review decisions, earned rewards, and delivery status remain separate from optional task reminders.</p></article></section>
<?php if ($error !== null): ?>
<section class="labs-product-card"><div class="labs-alert is-error" role="alert"><?php echo labs_e($error); ?></div><p>The preference link may be invalid, expired, or associated with a disconnected account. Open a newer Training Lab reminder or contact support.</p></section>
<?php else: ?>
<section class="labs-product-layout"><article class="labs-product-card"><span class="labs-product-kicker">Reminder setting</span><h2><?php echo !empty($preference['reminder_enabled']) ? 'Task reminders are enabled.' : 'Task reminders are disabled.'; ?></h2><p>Changing this setting affects reminder-class messages only. It does not delete training records, campaign enrollment, proof, reviews, rewards, or account links.</p><?php if (is_array($saved)): ?><div class="labs-alert is-success" role="status">Your reminder preference was saved.</div><?php endif; ?><form method="post" action="<?php echo labs_url('/notification-preferences.php'); ?>" class="labs-template-form"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="token" value="<?php echo labs_e($token); ?>"><fieldset><legend>Optional reminders</legend><label class="labs-check"><input type="radio" name="reminder_setting" value="enabled"<?php echo !empty($preference['reminder_enabled']) ? ' checked' : ''; ?>><span>Send task and progress reminders</span></label><label class="labs-check"><input type="radio" name="reminder_setting" value="disabled"<?php echo empty($preference['reminder_enabled']) ? ' checked' : ''; ?>><span>Stop task and progress reminders</span></label></fieldset><button class="labs-btn labs-btn-primary" type="submit">Save Preference</button></form></article><aside class="labs-product-card"><span class="labs-product-kicker">Privacy boundary</span><h2>No address is displayed.</h2><p>This page uses a time-limited signed link. It does not reveal your email address, account-link ID, session cookie, provider message ID, or private configuration.</p></aside></section>
<?php endif; ?>
<section class="labs-safe-note">Training Lab reminder preferences do not unsubscribe users from Microgifter or modify any Microgifter marketing preference.</section>
<?php labs_page_end(['section'=>'public']); ?>
