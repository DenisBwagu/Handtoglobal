<?php
/**
 * Admin Topbar Component
 * Shared topbar for all admin pages - matches combos.php exactly
 */

// Get current page for title
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Map page names to titles
$pageTitles = [
    'dashboard' => 'Dashboard',
    'users' => 'Users',
    'employees' => 'Employees',
    'levels' => 'Levels',
    'tasks' => 'Tasks',
    'combos' => 'Combos',
    'invitation-codes' => 'Invitation Codes',
    'finance_analysis' => 'Finance Analysis',
    'withdrawals' => 'Withdrawals',
    'contacts' => 'Contacts',
    'testimonials' => 'Testimonials',
    'settings' => 'Settings',
    'languages' => 'Languages'
];

$topbarTitle = $pageTitles[$currentPage] ?? ucfirst($currentPage);
?>

<!-- Topbar Header -->
<div class="topbar">
    <div class="topbar-left">
        <div class="menu-icon">
            <i class="fas fa-bars"></i>
        </div>
        <div class="topbar-title"><?php echo htmlspecialchars($topbarTitle); ?></div>
    </div>
    <div class="topbar-right">
        <div class="admin-badge">ADMIN</div>
        <form class="language-form" method="post" action="../language_action.php">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/' . $currentPage . '.php'); ?>">
            <input type="hidden" name="context" value="admin">
            <select name="language" onchange="this.form.submit()">
                <?php foreach (['english' => 'English', 'chinese' => 'Chinese'] as $code => $label): ?>
                    <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($_SESSION['admin_language'] ?? $_SESSION['language'] ?? 'english') === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="theme-toggle" id="themeToggle">
            <i class="fas fa-moon"></i>
        </div>
        <a href="/handtoglobal/admin/logout.php" style="display:inline-flex;align-items:center;gap:8px;height:34px;padding:0 12px;border-radius:6px;background:#dc2626;color:#fff;text-decoration:none;font-size:13px;font-weight:700;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        <div class="profile-info">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
            </div>
            <div class="profile-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></div>
            <div>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </div>
</div>
