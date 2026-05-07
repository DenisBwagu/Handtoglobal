<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/settings_helpers.php';
require_once __DIR__ . '/includes/language_helpers.php';

$language = normalize_language_code($_POST['language'] ?? 'english');
$context = ($_POST['context'] ?? 'user') === 'admin' ? 'admin' : 'user';
$baseUrl = function_exists('htg_app_base_url') ? htg_app_base_url() : '/';
$redirectTo = $_POST['redirect'] ?? ($context === 'admin' ? $baseUrl . 'admin/dashboard.php' : $baseUrl . 'dashboard.php');

$_SESSION['language'] = $language;
setcookie('htg_language', $language, time() + 31536000, '/');

try {
    if ($context === 'admin') {
        $_SESSION['admin_language'] = $language;
        update_setting('admin_locale', $language);
    } else {
        set_user_language($language);
    }
} catch (Throwable $e) {
    // Session language applies even if persistence fails.
}

if (!is_string($redirectTo) || $redirectTo === '' || preg_match('/^https?:\/\//i', $redirectTo)) {
    $redirectTo = $context === 'admin' ? $baseUrl . 'admin/dashboard.php' : $baseUrl . 'dashboard.php';
}

if ($redirectTo[0] !== '/') {
    $redirectTo = rtrim($baseUrl, '/') . '/' . ltrim($redirectTo, '/');
}

header('Location: ' . $redirectTo);
exit;
