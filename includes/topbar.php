<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/settings_helpers.php';
require_once __DIR__ . '/language_helpers.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$isAdminArea = strpos($requestPath, '/admin/') !== false;
$baseUrl = '/handtoglobal/';
$assetPrefix = $baseUrl;

$pageTitles = [
    'dashboard.php' => __t('dashboard', 'Dashboard'),
    'users.php' => __t('users', 'Users'),
    'user_view.php' => __t('user_details', 'User Details'),
    'tasks.php' => __t('tasks', 'Tasks'),
    'task_create.php' => __t('add_task', 'Add Task'),
    'task_edit.php' => __t('edit_task', 'Edit Task'),
    'combos.php' => __t('combos', 'Combos'),
    'combo_create.php' => __t('add_combo', 'Add Combo'),
    'edit_combo.php' => __t('edit_combo', 'Edit Combo'),
    'combo_edit.php' => __t('edit_combo', 'Edit Combo'),
    'settings.php' => __t('settings', 'Settings'),
    'withdrawals.php' => __t('withdrawals', 'Withdrawals'),
    'view_withdrawal.php' => __t('withdrawal_details', 'Withdrawal Details'),
    'employees.php' => __t('employees', 'Employees'),
    'employee_create.php' => __t('add_employee', 'Add Employee'),
    'employee_edit.php' => __t('edit_employee', 'Edit Employee'),
    'employee_view.php' => __t('employee_details', 'Employee Details'),
    'levels.php' => __t('levels', 'Levels'),
    'levels_create.php' => __t('add_level', 'Add Level'),
    'levels_edit.php' => __t('edit_level', 'Edit Level'),
    'languages.php' => __t('languages', 'Languages'),
    'testimonials.php' => __t('testimonials', 'Testimonials'),
    'testimonial_create.php' => __t('add_testimonial', 'Add Testimonial'),
    'testimonial_edit.php' => __t('edit_testimonial', 'Edit Testimonial'),
    'contacts.php' => __t('contacts', 'Contacts'),
    'contact_view.php' => __t('contact_details', 'Contact Details'),
    'profile.php' => __t('profile', 'Profile'),
    'task_history.php' => __t('task_history', 'Task History'),
    'request_withdrawal.php' => __t('request_withdrawal', 'Request Withdrawal'),
];

$currentFile = basename($requestPath);
$pageTitle = $pageTitles[$currentFile] ?? ($isAdminArea ? 'Admin Panel' : 'Dashboard');

if ($isAdminArea && isAdminLoggedIn()) {
    $displayName = 'Admin';
    $displayEmail = $_SESSION['admin_email'] ?? 'admin@handtoglobal.com';
    $showAdminBadge = true;
    $logoutUrl = $baseUrl . 'admin/logout.php';
} else {
    $showAdminBadge = false;
    $logoutUrl = $baseUrl . 'logout.php';
    $displayName = $_SESSION['user_name'] ?? $_SESSION['user_fullname'] ?? 'User';
    $displayEmail = $_SESSION['user_email'] ?? '';

    if (!empty($_SESSION['user_id'])) {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT fullname, email FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $displayName = $row['fullname'] ?: $displayName;
                $displayEmail = $row['email'] ?: $displayEmail;
            }
        } catch (Throwable $e) {
            // Session values are enough for the topbar.
        }
    }
}

$siteName = get_setting('site_name', 'HandToGlobal');
$siteLogo = get_setting('site_logo', '');
$faviconUrl = function_exists('get_favicon') ? get_favicon() : 'assets/images/favicon.ico';
if ($faviconUrl !== '' && !preg_match('/^(https?:)?\/\//i', $faviconUrl) && $faviconUrl[0] !== '/') {
    $faviconUrl = $baseUrl . ltrim($faviconUrl, '/');
}
$siteLogoUrl = $siteLogo;
if ($siteLogoUrl !== '' && !preg_match('/^(https?:)?\/\//i', $siteLogoUrl) && $siteLogoUrl[0] !== '/') {
    $siteLogoUrl = $baseUrl . ltrim($siteLogoUrl, '/');
}
$supportTelegram = get_setting('support_telegram', get_setting('telegram_link', ''));
$avatarLetter = strtoupper(substr(trim($displayName) !== '' ? trim($displayName) : 'U', 0, 1));
$currentLanguage = current_language();
$languages = available_languages();
?>

<link rel="stylesheet" href="<?php echo $assetPrefix; ?>assets/css/global-theme.css">
<?php
$favicon = get_setting('site_favicon', 'assets/images/favicon.ico');
?>
<link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>?v=<?php echo time(); ?>" type="image/x-icon">
<script src="<?php echo $assetPrefix; ?>assets/js/theme.js" defer></script>

<div class="topbar htg-topbar" id="topbar" data-support-link="<?php echo htmlspecialchars($supportTelegram); ?>">
    <div class="topbar-left htg-topbar-left">
        <button type="button" class="menu-icon htg-menu-btn" id="menuToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="htg-brand">
            <?php if ($siteLogoUrl !== ''): ?>
                <img src="<?php echo htmlspecialchars($siteLogoUrl); ?>" alt="<?php echo htmlspecialchars($siteName); ?>">
            <?php endif; ?>
            <span><?php echo htmlspecialchars($siteName); ?></span>
        </div>
        <div class="topbar-title htg-page-title"><?php echo htmlspecialchars($pageTitle); ?></div>
    </div>

    <div class="topbar-right htg-topbar-right">
        <?php if ($showAdminBadge): ?>
            <span class="admin-badge htg-admin-badge">ADMIN</span>
        <?php endif; ?>

        <form class="language-form htg-language-form" method="post" action="<?php echo $baseUrl; ?>language_action.php">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? $baseUrl); ?>">
            <input type="hidden" name="context" value="<?php echo $isAdminArea ? 'admin' : 'user'; ?>">
            <select name="language" onchange="this.form.submit()" aria-label="Language">
                <?php foreach ($languages as $code => $label): ?>
                    <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $currentLanguage === $code ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <button type="button" class="topbar-icon htg-theme-btn" id="themeToggle" aria-label="Toggle dark mode">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>

        <a href="<?php echo htmlspecialchars($logoutUrl); ?>" class="topbar-logout htg-logout">
            <i class="fas fa-sign-out-alt"></i>
            <span><?php echo htmlspecialchars(__t('logout', 'Logout')); ?></span>
        </a>

        <div class="profile-info htg-profile-info">
            <div class="profile-avatar htg-avatar"><?php echo htmlspecialchars($avatarLetter); ?></div>
            <div class="profile-name htg-profile-name"><?php echo htmlspecialchars($displayName); ?></div>
        </div>
    </div>
</div>

<style>
.htg-topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 62px;
    background: var(--topbar-bg, #fff);
    border-bottom: 1px solid var(--border, #e5e7eb);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    z-index: 1000;
    box-shadow: 0 1px 2px rgba(0,0,0,.03);
}
.htg-topbar-left,
.htg-topbar-right {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}
.htg-menu-btn,
.htg-theme-btn {
    border: 0;
    background: transparent;
    color: var(--text-primary, #374151);
    cursor: pointer;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 16px;
}
.htg-menu-btn:hover,
.htg-theme-btn:hover {
    background: var(--hover, #f3f4f6);
}
.htg-page-title {
    color: var(--text-primary, #111827);
    font-size: 16px;
    font-weight: 700;
    white-space: nowrap;
}
.htg-brand {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary, #374151);
    font-size: 14px;
    font-weight: 700;
    min-width: 0;
}
.htg-brand img {
    width: 26px;
    height: 26px;
    object-fit: contain;
}
.htg-admin-badge {
    background: #16a34a;
    color: #fff;
    border-radius: 4px;
    padding: 5px 9px;
    font-size: 11px;
    font-weight: 800;
}
.htg-language-form select {
    height: 34px;
    border: 1px solid var(--input-border, #d1d5db);
    border-radius: 5px;
    background: var(--input-bg, #fff);
    color: var(--input-color, #111827);
    padding: 0 28px 0 10px;
    font-weight: 600;
}
.htg-logout {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    height: 34px;
    padding: 0 13px;
    border-radius: 6px;
    background: #dc2626;
    color: #fff !important;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
}
.htg-logout:hover {
    background: #b91c1c;
}
.htg-profile-info {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-primary, #111827);
    font-weight: 700;
}
.htg-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #0d6efd;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 800;
}
.htg-profile-name {
    font-size: 14px;
    white-space: nowrap;
}
.htg-impersonation-bar {
    position: fixed;
    top: 62px;
    left: 0;
    right: 0;
    background: #f59e0b;
    color: #fff;
    text-align: center;
    padding: 7px;
    z-index: 999;
    font-weight: 700;
}
.htg-impersonation-bar a {
    color: #fff;
    margin-left: 10px;
}
body.dark-mode,
[data-theme="dark"] body {
    background: var(--body-bg, #0f172a);
    color: var(--body-color, #f8fafc);
}
body.dark-mode .sidebar,
[data-theme="dark"] .sidebar {
    background: var(--sidebar-bg, #1e293b) !important;
    border-color: var(--sidebar-border, #334155) !important;
}
body.dark-mode .card,
body.dark-mode table,
body.dark-mode .modal-content,
body.dark-mode .panel,
[data-theme="dark"] .card,
[data-theme="dark"] table,
[data-theme="dark"] .modal-content,
[data-theme="dark"] .panel {
    background: var(--card-bg, #1e293b) !important;
    color: var(--text-primary, #f8fafc) !important;
    border-color: var(--card-border, #334155) !important;
}
body.dark-mode input,
body.dark-mode select,
body.dark-mode textarea,
[data-theme="dark"] input,
[data-theme="dark"] select,
[data-theme="dark"] textarea {
    background: var(--input-bg, #0f172a) !important;
    color: var(--input-color, #f8fafc) !important;
    border-color: var(--input-border, #475569) !important;
}
.sidebar {
    transition: transform .25s ease, margin-left .25s ease, width .25s ease;
}
.sidebar.htg-sidebar-collapsed {
    transform: translateX(-105%);
}
.main-content.htg-main-expanded {
    margin-left: 0 !important;
}
@media (max-width: 900px) {
    .htg-brand span,
    .htg-profile-name,
    .htg-logout span {
        display: none;
    }
    .htg-topbar {
        padding: 0 10px;
    }
    .htg-topbar-left,
    .htg-topbar-right {
        gap: 8px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar, #sidebar');
    const mainContent = document.querySelector('.main-content, main');
    const collapsedKey = 'htgSidebarCollapsed';

    function applySidebarState() {
        if (!sidebar) return;
        const collapsed = localStorage.getItem(collapsedKey) === 'true';
        sidebar.classList.toggle('htg-sidebar-collapsed', collapsed);
        if (mainContent) {
            mainContent.classList.toggle('htg-main-expanded', collapsed);
            mainContent.classList.toggle('expanded', collapsed);
        }
    }

    applySidebarState();
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function () {
            const collapsed = !sidebar.classList.contains('htg-sidebar-collapsed');
            localStorage.setItem(collapsedKey, collapsed ? 'true' : 'false');
            applySidebarState();
        });
    }
});
</script>
