<?php
/**
 * Admin Sidebar Component
 * Shared sidebar for all admin pages
 */

require_once '../includes/settings_helpers.php';

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Helper function to check if menu item is active
function isMenuActive($page, $currentPage) {
    return $page === $currentPage;
}

// Get site logo and name
$siteLogo = get_site_logo();
$siteName = get_site_name();
?>

<!-- Admin Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <?php if ($siteLogo): ?>
            <img src="../<?php echo $siteLogo; ?>" alt="<?php echo htmlspecialchars($siteName); ?>" style="height: 24px; margin-right: 12px;">
        <?php else: ?>
            <i class="fas fa-hand-holding-usd"></i>
        <?php endif; ?>
        <h2><?php echo htmlspecialchars($siteName); ?></h2>
    </div>
    
    <!-- MANAGEMENT Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title">MANAGEMENT</div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="<?php echo isMenuActive('dashboard', $currentPage) ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php" class="<?php echo isMenuActive('users', $currentPage) ? 'active' : ''; ?>"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="employees.php" class="<?php echo isMenuActive('employees', $currentPage) ? 'active' : ''; ?>"><i class="fas fa-user-tie"></i> Employees</a></li>
        </ul>
    </div>
    
    <!-- PLATFORM Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title">PLATFORM</div>
        <ul class="sidebar-menu">
            <li><a href="levels.php" class="<?php echo isMenuActive('levels', $currentPage) ? 'active' : ''; ?>"><i class="fas fa-layer-group"></i> Levels</a></li>
            <li><a href="tasks.php" class="<?php echo isMenuActive('tasks', $currentPage) ? 'active' : ''; ?>"><i class="fas fa-tasks"></i> Tasks</a></li>
            <li><a href="combos.php" class="<?php echo isMenuActive('combos', $currentPage) ? 'active' : ''; ?>"><i class="fas fa-link"></i> Combos</a></li>
            <li><a href="invitation-codes.php" class="<?php echo isMenuActive('invitation-codes', $currentPage) ? 'active' : ''; ?>"><i class="fas fa-ticket-alt"></i> InvitationCodes</a></li>
        </ul>
    </div>
    
    <!-- FINANCE Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title">FINANCE</div>
        <ul class="sidebar-menu">
            <li><a href="finance_analysis.php" class="<?php echo isMenuActive('finance_analysis', $currentPage) ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> FinanceAnalysis</a></li>
            <li><a href="withdrawals.php" class="<?php echo isMenuActive('withdrawals', $currentPage) ? 'active' : ''; ?>"><i class="fas fa-arrow-up"></i> Withdrawals</a></li>
        </ul>
    </div>
    
    <!-- MONITORING Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title">MONITORING</div>
        <ul class="sidebar-menu">
            <li><a href="contacts.php" class="<?php echo isMenuActive('contacts', $currentPage) ? 'active' : ''; ?>"><i class="fas fa-address-book"></i> Contacts</a></li>
            <li><a href="testimonials.php" class="<?php echo isMenuActive('testimonials', $currentPage) ? 'active' : ''; ?>"><i class="fas fa-comments"></i> Testimonials</a></li>
        </ul>
    </div>
    
    <!-- SYSTEM Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title">SYSTEM</div>
        <ul class="sidebar-menu">
            <li><a href="settings.php" class="<?php echo isMenuActive('settings', $currentPage) ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="languages.php" class="<?php echo isMenuActive('languages', $currentPage) ? 'active' : ''; ?>"><i class="fas fa-language"></i> Languages</a></li>
            <li><a href="/handtoglobal/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
</div>

<style>
/* Admin Sidebar Styles - Matching combos.php style */
.sidebar {
    width: 250px;
    background: #343a40;
    color: white;
    overflow-y: auto;
}

.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid #495057;
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar-header h2 {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
}

.sidebar-section {
    padding: 15px 0;
}

.sidebar-section-title {
    padding: 0 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    color: #adb5bd;
    margin-bottom: 10px;
}

.sidebar-menu {
    list-style: none;
    margin: 0;
    padding: 0;
}

.sidebar-menu li {
    margin-bottom: 2px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: #adb5bd;
    text-decoration: none;
    transition: all 0.2s;
}

.sidebar-menu a:hover,
.sidebar-menu a.active {
    background: #495057;
    color: white;
}
</style>
