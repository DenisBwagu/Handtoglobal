<?php
/**
 * Admin Topbar Component
 * Shared topbar for all admin pages
 */

// Get admin data from session
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminEmail = $_SESSION['admin_email'] ?? 'admin@handtoglobal.com';
$adminId = $_SESSION['admin_id'] ?? $_SESSION['admin'] ?? null;

// Get first letter for avatar
$avatarLetter = strtoupper(substr($adminName, 0, 1));
$currentLanguage = $_SESSION['admin_language'] ?? $_SESSION['language'] ?? get_setting('admin_locale', 'english');
$languageOptions = ['english' => 'English', 'chinese' => 'Chinese'];

// Get current page title
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$pageTitle = ucfirst(str_replace('_', ' ', $currentPage));
if ($currentPage === 'dashboard') {
    $pageTitle = get_translation('dashboard', 'Dashboard');
} elseif ($currentPage === 'employees') {
    $pageTitle = get_translation('employees_management', 'Employees Management');
} elseif ($currentPage === 'users') {
    $pageTitle = get_translation('users_management', 'Users Management');
} elseif ($currentPage === 'tasks') {
    $pageTitle = get_translation('tasks_management', 'Tasks Management');
} elseif ($currentPage === 'settings') {
    $pageTitle = get_translation('settings', 'Settings');
} elseif ($currentPage === 'withdrawals') {
    $pageTitle = get_translation('withdrawals_management', 'Withdrawals Management');
}
?>

<link rel="stylesheet" href="../assets/css/global-theme.css">
<script src="../assets/js/theme.js" defer></script>

<!-- Admin Topbar -->
<div class="topbar" id="topbar">
    <div class="topbar-left">
        <div class="menu-icon" id="menuToggle">
            <i class="fas fa-bars"></i>
        </div>
        <div class="topbar-title">
            <span class="page-title"><?php echo htmlspecialchars($pageTitle); ?></span>
        </div>
    </div>
    <div class="topbar-right">
        <div class="admin-badge">ADMIN</div>

        <form class="language-form" method="post" action="../language_action.php">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php'); ?>">
            <input type="hidden" name="context" value="admin">
            <label class="sr-only" for="adminLanguageSelect">Language</label>
            <select id="adminLanguageSelect" name="language" onchange="this.form.submit()">
                <?php foreach ($languageOptions as $code => $label): ?>
                    <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $currentLanguage === $code ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        
        <div class="topbar-icon" id="themeToggle">
            <i class="fas fa-moon" id="themeIcon"></i>
        </div>

        <a href="/handtoglobal/admin/logout.php">Logout</a>
            <i class="fas fa-sign-out-alt"></i>
            <?php echo get_translation('logout', 'Logout'); ?>
        </a>
        
        <div class="profile-dropdown">
            <div class="profile-info" id="profileToggle">
                <div class="profile-avatar">
                    <?php echo $avatarLetter; ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($adminName); ?></div>
                <div class="dropdown-arrow">
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
            
            <div class="dropdown-menu" id="profileDropdown">
                <div class="dropdown-header">
                    <div class="dropdown-avatar">
                        <?php echo $avatarLetter; ?>
                    </div>
                    <div class="dropdown-info">
                        <div class="dropdown-name"><?php echo htmlspecialchars($adminName); ?></div>
                        <div class="dropdown-email"><?php echo htmlspecialchars($adminEmail); ?></div>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <?php echo get_translation('logout', 'Logout'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Admin Topbar Styles */
.topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 56px;
    background: var(--topbar-bg);
    border-bottom: 1px solid var(--topbar-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    z-index: 1000;
    transition: all 0.3s ease;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.menu-icon {
    cursor: pointer;
    color: var(--text-primary);
    font-size: 18px;
    padding: 8px;
    border-radius: 6px;
    transition: background-color 0.2s ease;
}

.menu-icon:hover {
    background: var(--hover);
}

.topbar-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
}

.page-title {
    color: var(--text-primary);
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.admin-badge {
    background: var(--primary);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.topbar-icon {
    cursor: pointer;
    color: var(--text-secondary);
    font-size: 16px;
    padding: 8px;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.language-form select {
    height: 34px;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 6px;
    padding: 0 10px;
    background: var(--dropdown-bg, #ffffff);
    color: var(--text-primary, #1a1a1a);
    font-size: 13px;
    font-weight: 600;
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.topbar-icon:hover {
    background: var(--hover);
    color: var(--text-primary);
}

.topbar-logout {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 34px;
    padding: 0 12px;
    border-radius: 6px;
    background: var(--danger);
    color: #fff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}

.topbar-logout:hover {
    background: var(--danger-hover, #b91c1c);
    color: #fff;
}

.profile-dropdown {
    position: relative;
}

.profile-info {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 6px 8px;
    border-radius: 8px;
    transition: background-color 0.2s ease;
}

.profile-info:hover {
    background: var(--hover);
}

.profile-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.profile-name {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 14px;
}

.dropdown-arrow {
    color: var(--text-muted);
    font-size: 12px;
    transition: transform 0.2s ease;
}

.profile-dropdown.active .dropdown-arrow {
    transform: rotate(180deg);
}

.dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: var(--dropdown-bg);
    border: 1px solid var(--dropdown-border);
    border-radius: 8px;
    box-shadow: var(--shadow-lg);
    min-width: 240px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    z-index: 1001;
}

.profile-dropdown.active .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
}

.dropdown-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
}

.dropdown-info {
    flex: 1;
}

.dropdown-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 14px;
    margin-bottom: 2px;
}

.dropdown-email {
    color: var(--text-muted);
    font-size: 12px;
}

.dropdown-divider {
    height: 1px;
    background: var(--dropdown-border);
    margin: 0;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: var(--text-primary);
    text-decoration: none;
    font-size: 14px;
    transition: background-color 0.2s ease;
}

.dropdown-item:hover {
    background: var(--dropdown-hover);
}

.dropdown-item.logout {
    color: var(--danger);
}

.dropdown-item.logout:hover {
    background: rgba(220, 38, 38, 0.1);
}

.dropdown-item i {
    width: 16px;
    text-align: center;
}
</style>

<script>
// Admin topbar functionality
document.addEventListener('DOMContentLoaded', function() {
    // Profile dropdown
    const profileToggle = document.getElementById('profileToggle');
    const profileDropdown = document.getElementById('profileDropdown');
    const profileDropdownParent = profileToggle.closest('.profile-dropdown');
    
    profileToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        profileDropdownParent.classList.toggle('active');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!profileDropdownParent.contains(e.target)) {
            profileDropdownParent.classList.remove('active');
        }
    });
});
</script>
