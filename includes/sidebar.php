<?php
/**
 * User Sidebar Component
 * Shared sidebar for all user pages
 */

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Helper function to check if menu item is active
function isUserMenuActive($page, $currentPage) {
    return $page === $currentPage;
}

// Get user data
$userId = $_SESSION['user_id'] ?? null;
$userBalance = 0;
$userLevel = 'Bronze';

if ($userId) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT balance, level FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            $userBalance = $user['balance'] ?? 0;
            $userLevel = $user['level'] ?? 'Bronze';
        }
    } catch (Exception $e) {
        // Fallback values
        $userBalance = 0;
        $userLevel = 'Bronze';
    }
}

// Get site logo and name
$siteLogo = get_setting('site_logo');
$siteName = get_setting('site_name', 'HandToGlobal');
?>

<!-- User Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php if ($siteLogo): ?>
            <img src="<?php echo $siteLogo; ?>" alt="<?php echo htmlspecialchars($siteName); ?>" style="height: 24px; margin-right: 12px;">
        <?php else: ?>
            <i class="fas fa-hand-holding-usd" style="font-size: 24px; margin-right: 12px; color: var(--primary);"></i>
        <?php endif; ?>
        <h2><?php echo htmlspecialchars($siteName); ?></h2>
    </div>
    
    <!-- User Balance Card -->
    <div class="user-balance-card">
        <div class="balance-label"><?php echo get_translation('current_balance', 'Current Balance'); ?></div>
        <div class="balance-amount">$<?php echo number_format($userBalance, 2); ?></div>
        <div class="user-level">
            <span class="level-badge level-<?php echo strtolower($userLevel); ?>"><?php echo htmlspecialchars($userLevel); ?></span>
        </div>
    </div>
    
    <!-- MAIN Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title"><?php echo get_translation('main', 'MAIN'); ?></div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php" class="sidebar-menu-item <?php echo isUserMenuActive('dashboard', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> 
                    <?php echo get_translation('dashboard', 'Dashboard'); ?>
                </a>
            </li>
            <li>
                <a href="tasks.php" class="sidebar-menu-item <?php echo isUserMenuActive('tasks', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-tasks"></i> 
                    <?php echo get_translation('tasks', 'Tasks'); ?>
                </a>
            </li>
            <li>
                <a href="combos.php" class="sidebar-menu-item <?php echo isUserMenuActive('combos', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-link"></i> 
                    <?php echo get_translation('combos', 'Combos'); ?>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- ACCOUNT Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title"><?php echo get_translation('account', 'ACCOUNT'); ?></div>
        <ul class="sidebar-menu">
            <li>
                <a href="withdrawals.php" class="sidebar-menu-item <?php echo isUserMenuActive('withdrawals', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-arrow-up"></i> 
                    <?php echo get_translation('withdrawals', 'Withdrawals'); ?>
                </a>
            </li>
            <li>
                <a href="deposits.php" class="sidebar-menu-item <?php echo isUserMenuActive('deposits', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-arrow-down"></i> 
                    <?php echo get_translation('deposits', 'Deposits'); ?>
                </a>
            </li>
            <li>
                <a href="profile.php" class="sidebar-menu-item <?php echo isUserMenuActive('profile', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i> 
                    <?php echo get_translation('profile', 'Profile'); ?>
                </a>
            </li>
            <li>
                <a href="history.php" class="sidebar-menu-item <?php echo isUserMenuActive('history', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> 
                    <?php echo get_translation('history', 'History'); ?>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- SUPPORT Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title"><?php echo get_translation('support', 'SUPPORT'); ?></div>
        <ul class="sidebar-menu">
            <li>
                <a href="#" class="sidebar-menu-item" onclick="window.open('<?php echo get_setting('telegram_link', '#'); ?>', '_blank')">
                    <i class="fas fa-headset"></i> 
                    <?php echo get_translation('support', 'Support'); ?>
                </a>
            </li>
            <li>
                <a href="help.php" class="sidebar-menu-item <?php echo isUserMenuActive('help', $currentPage) ? 'active' : ''; ?>">
                    <i class="fas fa-question-circle"></i> 
                    <?php echo get_translation('help', 'Help'); ?>
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

.user-balance-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 8px;
    padding: 16px;
    margin: 0 20px 20px 20px;
    text-align: center;
}

.balance-label {
    font-size: 12px;
    color: var(--text-muted);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.balance-amount {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 12px;
}

.user-level {
    margin-top: 8px;
}

.level-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.level-bronze {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.level-silver {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}

.level-gold {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
}

.level-platinum {
    background: rgba(124, 58, 237, 0.1);
    color: var(--primary);
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
