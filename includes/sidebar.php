<?php
/**
 * User Sidebar Component
 * Shared sidebar for all user pages
 */

require_once __DIR__ . '/language_helpers.php';

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Helper function to check if menu item is active
function isUserMenuActive($page, $currentPage) {
    return $page === $currentPage;
}

$supportLink = getSupportLink();
?>

<!-- User Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- MAIN Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title"><?php echo __t('main', 'MAIN'); ?></div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php" class="sidebar-menu-item <?php echo isUserMenuActive('dashboard', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> 
                    <?php echo __t('dashboard', 'Dashboard'); ?>
                </a>
            </li>
            <li>
                <a href="task_history.php" class="sidebar-menu-item <?php echo (isUserMenuActive('task_history', $currentPage) || isUserMenuActive('records', $currentPage)) ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> 
                    <?php echo __t('task_history', 'Task History'); ?>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- ACCOUNT Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title"><?php echo __t('account', 'ACCOUNT'); ?></div>
        <ul class="sidebar-menu">
            <li>
                <a href="withdrawals.php" class="sidebar-menu-item <?php echo isUserMenuActive('withdrawals', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-arrow-up"></i> 
                    <?php echo __t('withdrawals', 'Withdrawals'); ?>
                </a>
            </li>
            <li>
                <a href="profile.php" class="sidebar-menu-item <?php echo isUserMenuActive('profile', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i> 
                    <?php echo __t('profile', 'Profile'); ?>
                </a>
            </li>
            <li>
                <a href="logout.php" class="sidebar-menu-item">
                    <i class="fas fa-sign-out-alt"></i> 
                    <?php echo __t('logout', 'Logout'); ?>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- SUPPORT Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title"><?php echo __t('support', 'SUPPORT'); ?></div>
        <ul class="sidebar-menu">
            <li>
                <a href="<?php echo htmlspecialchars($supportLink); ?>" class="sidebar-menu-item" target="_blank" rel="noopener">
                    <i class="fas fa-headset"></i> 
                    <?php echo __t('support', 'Support'); ?>
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
/* User Sidebar Styles */
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
