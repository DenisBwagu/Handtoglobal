<?php
require_once 'config.php';
require_once 'get_setting.php';
require_once 'get_translation.php';

$context = $_POST['context'] ?? 'user';
$language = $_POST['language'] ?? 'english';
$redirectTo = $_POST['redirect'] ?? 'dashboard.php';

$userLanguages = ['english', 'ukrainian', 'greek', 'german'];
$adminLanguages = ['english', 'chinese'];
$allowed = $context === 'admin' ? $adminLanguages : $userLanguages;

if (!in_array($language, $allowed, true)) {
    $language = 'english';
}

$_SESSION['language'] = $language;

if ($context === 'admin') {
    $_SESSION['admin_language'] = $language;
    try {
        setSetting('admin_locale', $language);
    } catch (Throwable $e) {
        // Session language still applies immediately.
    }
} else {
    set_user_language($language);
}

if (!is_string($redirectTo) || $redirectTo === '' || preg_match('/^https?:\/\//i', $redirectTo)) {
    $redirectTo = $context === 'admin' ? 'admin/dashboard.php' : 'dashboard.php';
}

header('Location: ' . $redirectTo);
exit;
