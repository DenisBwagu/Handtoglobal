<?php
/**
 * Admin Sidebar Component
 * Shared sidebar for all admin pages
 */

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Helper function to check if menu item is active
function isMenuActive($page, $currentPage) {
    return $page === $currentPage;
}

// Get site logo and name
$siteLogo = get_setting('site_logo');
$siteName = get_setting('site_name', 'HandToGlobal');
?>

<!-- Admin Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php if ($siteLogo): ?>
            <img src="../<?php echo $siteLogo; ?>" alt="<?php echo htmlspecialchars($siteName); ?>" style="height: 24px; margin-right: 12px;">
        <?php else: ?>
            <i class="fas fa-hand-holding-usd" style="font-size: 24px; margin-right: 12px; color: var(--primary);"></i>
        <?php endif; ?>
        <h2><?php echo htmlspecialchars($siteName); ?></h2>
    </div>
    
    <!-- MANAGEMENT Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title"><?php echo get_translation('management', 'MANAGEMENT'); ?></div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php" class="sidebar-menu-item <?php echo isMenuActive('dashboard', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> 
                    <?php echo get_translation('dashboard', 'Dashboard'); ?>
                </a>
            </li>
            <li>
                <a href="users.php" class="sidebar-menu-item <?php echo isMenuActive('users', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> 
                    <?php echo get_translation('users', 'Users'); ?>
                </a>
            </li>
            <li>
                <a href="employees.php" class="sidebar-menu-item <?php echo isMenuActive('employees', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-user-tie"></i> 
                    <?php echo get_translation('employees', 'Employees'); ?>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- PLATFORM Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title"><?php echo get_translation('platform', 'PLATFORM'); ?></div>
        <ul class="sidebar-menu">
            <li>
                <a href="levels.php" class="sidebar-menu-item <?php echo isMenuActive('levels', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-layer-group"></i> 
                    <?php echo get_translation('levels', 'Levels'); ?>
                </a>
            </li>
            <li>
                <a href="tasks.php" class="sidebar-menu-item <?php echo isMenuActive('tasks', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-tasks"></i> 
                    <?php echo get_translation('tasks', 'Tasks'); ?>
                </a>
            </li>
            <li>
                <a href="combos.php" class="sidebar-menu-item <?php echo isMenuActive('combos', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-link"></i> 
                    <?php echo get_translation('combos', 'Combos'); ?>
                </a>
            </li>
            <li>
                <a href="invitation_codes.php" class="sidebar-menu-item <?php echo isMenuActive('invitation_codes', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-ticket-alt"></i> 
                    <?php echo get_translation('invitation_codes', 'Invitation Codes'); ?>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- FINANCE Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title"><?php echo get_translation('finance', 'FINANCE'); ?></div>
        <ul class="sidebar-menu">
            <li>
                <a href="finance_analysis.php" class="sidebar-menu-item <?php echo isMenuActive('finance_analysis', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i> 
                    <?php echo get_translation('finance_analysis', 'Finance Analysis'); ?>
                </a>
            </li>
            <li>
                <a href="withdrawals.php" class="sidebar-menu-item <?php echo isMenuActive('withdrawals', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-arrow-up"></i> 
                    <?php echo get_translation('withdrawals', 'Withdrawals'); ?>
                </a>
            </li>
            <li>
                <a href="deposits.php" class="sidebar-menu-item <?php echo isMenuActive('deposits', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-arrow-down"></i> 
                    <?php echo get_translation('deposits', 'Deposits'); ?>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- MONITORING Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title"><?php echo get_translation('monitoring', 'MONITORING'); ?></div>
        <ul class="sidebar-menu">
            <li>
                <a href="contacts.php" class="sidebar-menu-item <?php echo isMenuActive('contacts', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-address-book"></i> 
                    <?php echo get_translation('contacts', 'Contacts'); ?>
                </a>
            </li>
            <li>
                <a href="testimonials.php" class="sidebar-menu-item <?php echo isMenuActive('testimonials', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-comments"></i> 
                    <?php echo get_translation('testimonials', 'Testimonials'); ?>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- SYSTEM Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title"><?php echo get_translation('system', 'SYSTEM'); ?></div>
        <ul class="sidebar-menu">
            <li>
                <a href="settings.php" class="sidebar-menu-item <?php echo isMenuActive('settings', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i> 
                    <?php echo get_translation('settings', 'Settings'); ?>
                </a>
            </li>
            <li>
                <a href="languages.php" class="sidebar-menu-item <?php echo isMenuActive('languages', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-language"></i> 
                    <?php echo get_translation('languages', 'Languages'); ?>
                </a>
            </li>
            <li>
                <a href="../admin_logout.php" class="sidebar-menu-item">
                    <i class="fas fa-sign-out-alt"></i> 
                    <?php echo get_translation('logout', 'Logout'); ?>
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
/* Admin Sidebar Styles */
.sidebar {
    position: fixed;
    top: 56px;
    left: 0;
    width: 260px;
    height: calc(100vh - 56px);
    background: var(--sidebar-bg);
    border-right: 1px solid var(--sidebar-border);
    overflow-y: auto;
    z-index: 999;
    transition: all 0.3s ease;
}

.sidebar-header {
    display: flex;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid var(--sidebar-border);
    margin-bottom: 20px;
}

.sidebar-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
}

.sidebar-section {
    margin-bottom: 30px;
}

.sidebar-section-title {
    padding: 0 20px;
    margin-bottom: 10px;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.sidebar-menu {
    list-style: none;
    margin: 0;
    padding: 0;
}

.sidebar-menu-item {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: var(--text-primary);
    text-decoration: none;
    border-left: 3px solid transparent;
    transition: all 0.2s ease;
    font-size: 14px;
}

.sidebar-menu-item:hover {
    background: var(--hover);
    color: var(--primary);
}

.sidebar-menu-item.active {
    background: var(--hover);
    color: var(--primary);
    border-left-color: var(--primary);
}

.sidebar-menu-item i {
    width: 20px;
    margin-right: 12px;
    text-align: center;
}

/* Sidebar collapsed state */
.sidebar.sidebar-collapsed {
    margin-left: -260px;
}

.sidebar.sidebar-expanded {
    margin-left: 0;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.sidebar-expanded {
        transform: translateX(0);
    }
    
    .sidebar.sidebar-collapsed {
        transform: translateX(-100%);
    }
}
</style>
