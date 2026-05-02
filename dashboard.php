<?php
require_once 'config.php';

requireLogin();

$user = getUserById($_SESSION['user_id']);
$stats = getUserStats($_SESSION['user_id']);
$notifications = getUnreadNotifications($_SESSION['user_id']);

// Get recent activity
$conn = getConnection();
$stmt = $conn->prepare("SELECT * FROM completed_tasks ct 
                       JOIN tasks t ON ct.task_id = t.id 
                       WHERE ct.user_id = ? 
                       ORDER BY ct.completed_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recentTasks = $stmt->fetchAll();

// Get recent deposits
$stmt = $conn->prepare("SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$_SESSION['user_id']]);
$recentDeposits = $stmt->fetchAll();

// Get recent withdrawals
$stmt = $conn->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$_SESSION['user_id']]);
$recentWithdrawals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - HandToGlobal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        <?php include 'includes/theme.php'; ?>
    </style>
</head>
<body>
    <div class="layout-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="logo">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span class="logo-text">HandToGlobal</span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="starting.php" class="nav-item">
                    <i class="fas fa-tasks"></i>
                    <span>Tasks</span>
                </a>
                <a href="records.php" class="nav-item">
                    <i class="fas fa-history"></i>
                    <span>Records</span>
                </a>
                <a href="wallet.php" class="nav-item">
                    <i class="fas fa-wallet"></i>
                    <span>Wallet</span>
                </a>
                <a href="deposit.php" class="nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <span>Deposit</span>
                </a>
                <a href="withdraw.php" class="nav-item">
                    <i class="fas fa-minus-circle"></i>
                    <span>Withdraw</span>
                </a>
                <a href="notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search...">
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="notification-dropdown">
                        <button class="notification-btn">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge"><?php echo count($notifications); ?></span>
                        </button>
                        <div class="notification-menu">
                            <div class="notification-header">
                                <h3>Notifications</h3>
                                <a href="notifications.php">View All</a>
                            </div>
                            <div class="notification-list">
                                <?php if (empty($notifications)): ?>
                                    <div class="notification-item">
                                        <p>No new notifications</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach (array_slice($notifications, 0, 5) as $notif): ?>
                                        <div class="notification-item">
                                            <div class="notification-content">
                                                <h4><?php echo htmlspecialchars($notif['title']); ?></h4>
                                                <p><?php echo htmlspecialchars($notif['message']); ?></p>
                                                <small><?php echo date('M j, g:i A', strtotime($notif['created_at'])); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="profile-dropdown">
                        <button class="profile-btn">
                            <div class="avatar">
                                <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                            </div>
                            <div class="profile-info">
                                <span class="profile-name"><?php echo htmlspecialchars($user['fullname']); ?></span>
                                <span class="profile-level"><?php echo htmlspecialchars($user['level']); ?></span>
                            </div>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="profile-menu">
                            <a href="profile.php">
                                <i class="fas fa-user"></i>
                                <span>Profile</span>
                            </a>
                            <a href="wallet.php">
                                <i class="fas fa-wallet"></i>
                                <span>Wallet</span>
                            </a>
                            <a href="logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="dashboard-content">
                <div class="page-header">
                    <h1>Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($user['fullname']); ?>!</p>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatBalance($stats['balance']); ?></h3>
                            <p>Available Balance</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo htmlspecialchars($stats['level']); ?></h3>
                            <p>Current Level</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['today_tasks']; ?>/<?php echo DAILY_TASK_LIMIT; ?></h3>
                            <p>Tasks Today</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_tasks']; ?></h3>
                            <p>Total Tasks</p>
                        </div>
                    </div>
                </div>

                <!-- Level Progress -->
                <div class="progress-section">
                    <h2>Level Progress</h2>
                    <div class="level-cards">
                        <div class="level-card <?php echo $user['bronze_unlocked'] ? 'unlocked' : 'locked'; ?>">
                            <div class="level-header">
                                <h3>Bronze</h3>
                                <?php if (!$user['bronze_unlocked']): ?>
                                    <i class="fas fa-lock"></i>
                                <?php endif; ?>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo ($stats['bronze_progress'] / 40) * 100; ?>%"></div>
                            </div>
                            <p><?php echo $stats['bronze_progress']; ?>/40 tasks</p>
                        </div>
                        <div class="level-card <?php echo $user['silver_unlocked'] ? 'unlocked' : 'locked'; ?>">
                            <div class="level-header">
                                <h3>Silver</h3>
                                <?php if (!$user['silver_unlocked']): ?>
                                    <i class="fas fa-lock"></i>
                                <?php endif; ?>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo ($stats['silver_progress'] / 40) * 100; ?>%"></div>
                            </div>
                            <p><?php echo $stats['silver_progress']; ?>/40 tasks</p>
                        </div>
                        <div class="level-card <?php echo $user['gold_unlocked'] ? 'unlocked' : 'locked'; ?>">
                            <div class="level-header">
                                <h3>Gold</h3>
                                <?php if (!$user['gold_unlocked']): ?>
                                    <i class="fas fa-lock"></i>
                                <?php endif; ?>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo ($stats['gold_progress'] / 40) * 100; ?>%"></div>
                            </div>
                            <p><?php echo $stats['gold_progress']; ?>/40 tasks</p>
                        </div>
                        <div class="level-card <?php echo $user['platinum_unlocked'] ? 'unlocked' : 'locked'; ?>">
                            <div class="level-header">
                                <h3>Platinum</h3>
                                <?php if (!$user['platinum_unlocked']): ?>
                                    <i class="fas fa-lock"></i>
                                <?php endif; ?>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo ($stats['platinum_progress'] / 40) * 100; ?>%"></div>
                            </div>
                            <p><?php echo $stats['platinum_progress']; ?>/40 tasks</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="activity-section">
                    <div class="recent-tasks">
                        <h2>Recent Tasks</h2>
                        <div class="task-list">
                            <?php if (empty($recentTasks)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-tasks"></i>
                                    <p>No tasks completed yet</p>
                                    <a href="starting.php" class="btn">Start Tasks</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentTasks as $task): ?>
                                    <div class="task-item">
                                        <div class="task-info">
                                            <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($task['level']); ?> Level</p>
                                        </div>
                                        <div class="task-reward">
                                            <span class="reward">+<?php echo formatBalance($task['reward']); ?></span>
                                            <small><?php echo date('M j, g:i A', strtotime($task['completed_at'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="recent-transactions">
                        <h2>Recent Transactions</h2>
                        <div class="transaction-list">
                            <?php if (empty($recentDeposits) && empty($recentWithdrawals)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-exchange-alt"></i>
                                    <p>No transactions yet</p>
                                    <a href="deposit.php" class="btn">Deposit</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentDeposits as $deposit): ?>
                                    <div class="transaction-item deposit">
                                        <div class="transaction-info">
                                            <h4>Deposit</h4>
                                            <p><?php echo htmlspecialchars($deposit['status']); ?></p>
                                        </div>
                                        <div class="transaction-amount">
                                            <span class="amount positive">+<?php echo formatBalance($deposit['amount']); ?></span>
                                            <small><?php echo date('M j, g:i A', strtotime($deposit['created_at'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($recentWithdrawals as $withdrawal): ?>
                                    <div class="transaction-item withdrawal">
                                        <div class="transaction-info">
                                            <h4>Withdrawal</h4>
                                            <p><?php echo htmlspecialchars($withdrawal['status']); ?></p>
                                        </div>
                                        <div class="transaction-amount">
                                            <span class="amount negative">-<?php echo formatBalance($withdrawal['amount']); ?></span>
                                            <small><?php echo date('M j, g:i A', strtotime($withdrawal['created_at'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
