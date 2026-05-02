<?php
require_once '../config.php';

requireAdminLogin();

// Get user ID from URL
$userId = $_GET['id'] ?? null;
if (!$userId || !is_numeric($userId)) {
    redirect('users.php');
    exit;
}

// Get user details
$conn = getConnection();
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    $error = "User not found";
    $user = null;
}

// Handle admin actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'login_as':
            // Store admin session temporarily
            $_SESSION['admin_temp_id'] = $_SESSION['admin_id'];
            $_SESSION['admin_temp_email'] = $_SESSION['admin_email'];
            
            // Set user session
            $_SESSION['user_id'] = $user['id'];
            unset($_SESSION['admin_id'], $_SESSION['admin_email']);
            
            redirect('../dashboard.php');
            exit;
            
        case 'reset_password':
            $newPassword = $_POST['new_password'] ?? '';
            if (strlen($newPassword) < 6) {
                $error = 'Password must be at least 6 characters';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $user['id']]);
                $success = 'Password reset successfully';
            }
            break;
            
        case 'unlock_level':
            $level = $_POST['unlock_level'] ?? '';
            if (in_array($level, ['bronze', 'silver', 'gold', 'platinum'])) {
                $field = $level . '_unlocked';
                $stmt = $conn->prepare("UPDATE users SET $field = 1 WHERE id = ?");
                $stmt->execute([$user['id']]);
                $success = ucfirst($level) . ' level unlocked successfully';
            }
            break;
            
        case 'adjust_balance':
            $amount = (float)($_POST['balance_amount'] ?? 0);
            $reason = $_POST['balance_reason'] ?? '';
            $adjustType = $_POST['adjust_type'] ?? 'add';
            
            if ($amount <= 0) {
                $error = 'Amount must be greater than 0';
            } elseif (empty($reason)) {
                $error = 'Reason is required';
            } else {
                $conn->beginTransaction();
                try {
                    // Update user balance
                    if ($adjustType === 'add') {
                        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->execute([$amount, $user['id']]);
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                        $stmt->execute([$amount, $user['id']]);
                    }
                    
                    // Log the adjustment
                    $stmt = $conn->prepare("INSERT INTO balance_logs (user_id, admin_id, amount, action_type, reason) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user['id'], $_SESSION['admin_id'], $amount, $adjustType, $reason]);
                    
                    $conn->commit();
                    $success = 'Balance adjusted successfully';
                    
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $user = $stmt->fetch();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Failed to adjust balance';
                }
            }
            break;
            
        case 'set_daily_limit':
            $limit = (int)($_POST['daily_limit'] ?? 0);
            $stmt = $conn->prepare("UPDATE users SET daily_task_limit = ? WHERE id = ?");
            $stmt->execute([$limit > 0 ? $limit : null, $user['id']]);
            $success = 'Daily task limit updated successfully';
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $user = $stmt->fetch();
            break;
            
        case 'toggle_status':
            $newStatus = $user['is_blocked'] ? 0 : 1;
            $stmt = $conn->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
            $stmt->execute([$newStatus, $user['id']]);
            $success = $newStatus ? 'User blocked successfully' : 'User activated successfully';
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $user = $stmt->fetch();
            break;
            
        case 'flush_levels':
            $conn->beginTransaction();
            try {
                // Reset level progress
                $stmt = $conn->prepare("UPDATE users SET bronze_unlocked = 1, silver_unlocked = 0, gold_unlocked = 0, platinum_unlocked = 0 WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Clear completed tasks
                $stmt = $conn->prepare("DELETE FROM completed_tasks WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                
                $conn->commit();
                $success = 'Level progress flushed successfully';
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $user = $stmt->fetch();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Failed to flush levels';
            }
            break;
            
        case 'flush_account':
            $conn->beginTransaction();
            try {
                // Reset user data
                $stmt = $conn->prepare("UPDATE users SET balance = 0, bronze_unlocked = 1, silver_unlocked = 0, gold_unlocked = 0, platinum_unlocked = 0, total_tasks = 0, rating = 5.00, accuracy = 100.00 WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Clear all related data
                $stmt = $conn->prepare("DELETE FROM completed_tasks WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                
                $stmt = $conn->prepare("DELETE FROM deposits WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                
                $stmt = $conn->prepare("DELETE FROM withdrawals WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                
                $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                
                $conn->commit();
                $success = 'Account flushed successfully';
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $user = $stmt->fetch();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Failed to flush account';
            }
            break;
    }
}

// Get user statistics
if ($user) {
    // Task completion counts per level
    $stmt = $conn->prepare("SELECT level, COUNT(*) as completed FROM completed_tasks WHERE user_id = ? GROUP BY level");
    $stmt->execute([$user['id']]);
    $levelStats = [];
    while ($row = $stmt->fetch()) {
        $levelStats[$row['level']] = $row['completed'];
    }
    
    // Latest completed task
    $stmt = $conn->prepare("SELECT ct.*, t.title, t.reward FROM completed_tasks ct JOIN tasks t ON ct.task_id = t.id WHERE ct.user_id = ? ORDER BY ct.completed_at DESC LIMIT 1");
    $stmt->execute([$user['id']]);
    $latestTask = $stmt->fetch();
    
    // All completed tasks
    $stmt = $conn->prepare("SELECT ct.*, t.title, t.level, t.reward FROM completed_tasks ct JOIN tasks t ON ct.task_id = t.id WHERE ct.user_id = ? ORDER BY ct.completed_at DESC");
    $stmt->execute([$user['id']]);
    $completedTasks = $stmt->fetchAll();
    
    // Get level rewards
    $levelRewards = [
        'Bronze' => 1.8,
        'Silver' => 2.5,
        'Gold' => 3.5,
        'Platinum' => 5.0
    ];;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        <?php include '../includes/theme.php'; ?>
        
        /* User view specific styles */
        .user-detail-page {
            padding: 24px;
        }
        
        .user-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .user-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
        }
        
        .user-back-btn {
            padding: 8px 16px;
            background: var(--muted);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }
        
        .user-back-btn:hover {
            background: var(--primary);
        }
        
        .user-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 32px;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }
        
        .user-main-info {
            display: flex;
            gap: 32px;
            align-items: center;
            margin-bottom: 32px;
        }
        
        .user-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 800;
            flex-shrink: 0;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 8px 0;
            color: var(--text);
        }
        
        .user-email {
            font-size: 16px;
            color: var(--muted);
            margin: 0 0 16px 0;
        }
        
        .user-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .user-stat {
            text-align: center;
            padding: 16px;
            background: var(--bg);
            border-radius: var(--radius-sm);
        }
        
        .user-stat-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 4px;
        }
        
        .user-stat-value {
            font-size: 20px;
            font-weight: 800;
            color: var(--text);
        }
        
        .user-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .user-status.active {
            background: var(--success);
            color: white;
        }
        
        .user-status.blocked {
            background: var(--danger);
            color: white;
        }
        
        .admin-actions {
            border-top: 1px solid var(--border);
            padding-top: 24px;
        }
        
        .admin-actions h3 {
            font-size: 16px;
            font-weight: 800;
            margin: 0 0 16px 0;
            text-transform: uppercase;
            color: var(--muted);
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
        
        .action-btn {
            padding: 12px 16px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .action-btn.primary {
            background: var(--primary);
            color: white;
        }
        
        .action-btn.secondary {
            background: var(--secondary);
            color: white;
        }
        
        .action-btn.warning {
            background: var(--warning);
            color: white;
        }
        
        .action-btn.danger {
            background: var(--danger);
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-soft);
        }
        
        .live-activity {
            background: var(--surface);
            border: 2px solid var(--warning);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }
        
        .live-activity-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .live-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--success);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .live-activity-title {
            font-size: 18px;
            font-weight: 800;
            margin: 0;
            color: var(--text);
        }
        
        .live-activity-subtitle {
            font-size: 12px;
            color: var(--muted);
            margin: 0;
        }
        
        .activity-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .activity-card {
            background: var(--bg);
            padding: 16px;
            border-radius: var(--radius-sm);
            text-align: center;
        }
        
        .activity-card-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
        }
        
        .activity-card-value {
            font-size: 16px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 4px;
        }
        
        .activity-card-detail {
            font-size: 12px;
            color: var(--muted);
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--border);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--success);
            transition: width 0.3s ease;
        }
        
        .last-completed {
            padding: 16px;
            background: var(--bg);
            border-radius: var(--radius-sm);
        }
        
        .last-completed-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
        }
        
        .last-completed-task {
            font-size: 14px;
            color: var(--text);
            margin-bottom: 4px;
        }
        
        .last-completed-time {
            font-size: 12px;
            color: var(--muted);
        }
        
        .task-completions {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
        }
        
        .task-completions h3 {
            font-size: 18px;
            font-weight: 800;
            margin: 0 0 20px 0;
            color: var(--text);
        }
        
        .task-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .task-table th {
            background: var(--bg);
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
        }
        
        .task-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            color: var(--text);
        }
        
        .task-table tr:hover {
            background: var(--bg);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .status-badge.completed {
            background: var(--success);
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 24px;
            max-width: 400px;
            width: 90%;
            box-shadow: var(--shadow);
        }
        
        .modal-header {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 16px;
            color: var(--text);
        }
        
        .modal-body {
            margin-bottom: 20px;
            color: var(--text);
        }
        
        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .alert-success {
            background: rgba(22, 163, 74, 0.1);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        .alert-error {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger);
            border: 1px solid var(--danger);
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="logo">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span class="logo-text">HandToGlobal Admin</span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="users.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <a href="deposits.php" class="nav-item">
                    <i class="fas fa-arrow-up"></i>
                    <span>Deposits</span>
                </a>
                <a href="withdrawals.php" class="nav-item">
                    <i class="fas fa-arrow-down"></i>
                    <span>Withdrawals</span>
                </a>
                <a href="tasks.php" class="nav-item">
                    <i class="fas fa-tasks"></i>
                    <span>Tasks</span>
                </a>
                <a href="notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="../logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Top Bar -->
            <header class="admin-topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="user-header">
                        <a href="users.php" class="user-back-btn">
                            <i class="fas fa-arrow-left"></i>
                            Back to Users
                        </a>
                        <h1>User Details</h1>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="admin-info">
                        <span class="admin-badge">Admin</span>
                        <span><?php echo htmlspecialchars($_SESSION['admin_email']); ?></span>
                    </div>
                </div>
            </header>

            <!-- User Detail Content -->
            <main class="user-detail-page">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($user): ?>
                    <!-- User Card -->
                    <div class="user-card">
                        <div class="user-main-info">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                            </div>
                            <div class="user-details">
                                <h2 class="user-name"><?php echo htmlspecialchars($user['fullname']); ?></h2>
                                <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                                <span class="user-status <?php echo $user['is_blocked'] ? 'blocked' : 'active'; ?>">
                                    <?php echo $user['is_blocked'] ? 'Blocked' : 'Active'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="user-stats">
                            <div class="user-stat">
                                <div class="user-stat-label">Balance</div>
                                <div class="user-stat-value"><?php echo formatBalance($user['balance']); ?></div>
                            </div>
                            <div class="user-stat">
                                <div class="user-stat-label">Current Level</div>
                                <div class="user-stat-value"><?php echo htmlspecialchars($user['level']); ?></div>
                            </div>
                            <div class="user-stat">
                                <div class="user-stat-label">Rating</div>
                                <div class="user-stat-value"><?php echo number_format($user['rating'], 2); ?></div>
                            </div>
                            <div class="user-stat">
                                <div class="user-stat-label">Total Tasks</div>
                                <div class="user-stat-value"><?php echo $user['total_tasks']; ?></div>
                            </div>
                        </div>

                        <div class="admin-actions">
                            <h3>Admin Actions</h3>
                            <div class="action-buttons">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="login_as">
                                    <button type="submit" class="action-btn primary">
                                        <i class="fas fa-sign-in-alt"></i>
                                        Login As
                                    </button>
                                </form>
                                
                                <button class="action-btn secondary" onclick="openModal('resetPasswordModal')">
                                    <i class="fas fa-key"></i>
                                    Reset Password
                                </button>
                                
                                <button class="action-btn secondary" onclick="openModal('unlockLevelModal')">
                                    <i class="fas fa-unlock"></i>
                                    Unlock Level
                                </button>
                                
                                <button class="action-btn secondary" onclick="openModal('adjustBalanceModal')">
                                    <i class="fas fa-balance-scale"></i>
                                    Adjust Balance
                                </button>
                                
                                <button class="action-btn secondary" onclick="openModal('userLimitsModal')">
                                    <i class="fas fa-sliders-h"></i>
                                    User Limits
                                </button>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <button type="submit" class="action-btn warning">
                                        <i class="fas fa-<?php echo $user['is_blocked'] ? 'unlock' : 'lock'; ?>"></i>
                                        <?php echo $user['is_blocked'] ? 'Activate' : 'Deactivate'; ?>
                                    </button>
                                </form>
                                
                                <button class="action-btn warning" onclick="openModal('flushLevelsModal')">
                                    <i class="fas fa-trash-alt"></i>
                                    Flush Levels
                                </button>
                                
                                <button class="action-btn danger" onclick="openModal('flushAccountModal')">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Flush Account
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Live Activity -->
                    <div class="live-activity">
                        <div class="live-activity-header">
                            <div class="live-dot"></div>
                            <div>
                                <h3 class="live-activity-title">Live Activity</h3>
                                <p class="live-activity-subtitle">Updating Live</p>
                            </div>
                        </div>
                        
                        <div class="activity-cards">
                            <div class="activity-card">
                                <div class="activity-card-label">Level</div>
                                <div class="activity-card-value"><?php echo htmlspecialchars($user['level']); ?></div>
                                <div class="activity-card-detail">
                                    #<?php echo array_search($user['level'], ['Bronze', 'Silver', 'Gold', 'Platinum']) + 1; ?> · 
                                    $<?php echo $levelRewards[$user['level']]; ?>/task
                                </div>
                            </div>
                            
                            <div class="activity-card">
                                <div class="activity-card-label">Working On</div>
                                <div class="activity-card-value">
                                    <?php 
                                    $currentLevel = $user['level'];
                                    $completed = $levelStats[$currentLevel] ?? 0;
                                    echo $completed >= 40 ? 'Level Complete' : 'Tasks';
                                    ?>
                                </div>
                                <div class="activity-card-detail">
                                    <?php echo $currentLevel; ?> Level
                                </div>
                            </div>
                            
                            <div class="activity-card">
                                <div class="activity-card-label">Progress</div>
                                <div class="activity-card-value">
                                    <?php echo $completed; ?> / 40
                                </div>
                                <div class="activity-card-detail">
                                    <?php echo round(($completed / 40) * 100); ?>%
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo min(100, ($completed / 40) * 100); ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($latestTask): ?>
                            <div class="last-completed">
                                <div class="last-completed-label">Last Completed</div>
                                <div class="last-completed-task">
                                    Task #<?php echo $latestTask['task_id']; ?> · <?php echo htmlspecialchars($latestTask['title']); ?> · 
                                    <span style="color: var(--success);">+$<?php echo $latestTask['reward']; ?></span>
                                </div>
                                <div class="last-completed-time">
                                    <?php echo timeAgo($latestTask['completed_at']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Task Completions -->
                    <div class="task-completions">
                        <h3>Task Completions</h3>
                        <?php if (!empty($completedTasks)): ?>
                            <table class="task-table">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Level</th>
                                        <th>Reward</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completedTasks as $task): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($task['title']); ?></td>
                                            <td><?php echo htmlspecialchars($task['level']); ?></td>
                                            <td>$<?php echo $task['reward']; ?></td>
                                            <td>
                                                <span class="status-badge completed">Completed</span>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($task['completed_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="color: var(--muted); text-align: center; padding: 40px;">
                                No completed tasks found
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px;">
                        <i class="fas fa-user-slash" style="font-size: 48px; color: var(--muted); margin-bottom: 16px;"></i>
                        <h2 style="color: var(--text); margin-bottom: 8px;">User Not Found</h2>
                        <p style="color: var(--muted); margin-bottom: 24px;">The user you're looking for doesn't exist.</p>
                        <a href="users.php" class="action-btn primary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Users
                        </a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Modals -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Reset Password</div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn secondary" onclick="closeModal('resetPasswordModal')">Cancel</button>
                        <button type="submit" class="action-btn primary">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="unlockLevelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Unlock Level</div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="unlock_level">
                    <div class="form-group">
                        <label>Select Level</label>
                        <select name="unlock_level" required>
                            <option value="bronze">Bronze</option>
                            <option value="silver">Silver</option>
                            <option value="gold">Gold</option>
                            <option value="platinum">Platinum</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn secondary" onclick="closeModal('unlockLevelModal')">Cancel</button>
                        <button type="submit" class="action-btn primary">Unlock Level</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="adjustBalanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Adjust Balance</div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="adjust_balance">
                    <div class="form-group">
                        <label>Adjustment Type</label>
                        <select name="adjust_type" required>
                            <option value="add">Add Balance</option>
                            <option value="subtract">Subtract Balance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount (USDT)</label>
                        <input type="number" name="balance_amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Reason</label>
                        <textarea name="balance_reason" rows="3" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn secondary" onclick="closeModal('adjustBalanceModal')">Cancel</button>
                        <button type="submit" class="action-btn primary">Adjust Balance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="userLimitsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">User Limits</div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="set_daily_limit">
                    <div class="form-group">
                        <label>Daily Task Limit (Leave empty for default)</label>
                        <input type="number" name="daily_limit" min="1" placeholder="Default: 40">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn secondary" onclick="closeModal('userLimitsModal')">Cancel</button>
                        <button type="submit" class="action-btn primary">Set Limit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="flushLevelsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Flush Levels</div>
            <div class="modal-body">
                <p>Are you sure you want to flush this user's level progress?</p>
                <p style="color: var(--danger);">This will:</p>
                <ul style="color: var(--text);">
                    <li>Reset level progress to Bronze only</li>
                    <li>Clear all completed tasks</li>
                    <li>Keep balance and account data</li>
                </ul>
                <form method="POST" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="flush_levels">
                    <div class="modal-footer">
                        <button type="button" class="action-btn secondary" onclick="closeModal('flushLevelsModal')">Cancel</button>
                        <button type="submit" class="action-btn warning">Flush Levels</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="flushAccountModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Flush Account</div>
            <div class="modal-body">
                <p style="color: var(--danger); font-weight: 800;">⚠️ DANGER: This action cannot be undone!</p>
                <p>Are you sure you want to completely flush this user's account?</p>
                <p style="color: var(--danger);">This will:</p>
                <ul style="color: var(--text);">
                    <li>Reset balance to 0</li>
                    <li>Delete all deposits and withdrawals</li>
                    <li>Delete all completed tasks</li>
                    <li>Delete all notifications</li>
                    <li>Reset level progress</li>
                    <li>Keep user account (email, password)</li>
                </ul>
                <form method="POST" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="flush_account">
                    <div class="modal-footer">
                        <button type="button" class="action-btn secondary" onclick="closeModal('flushAccountModal')">Cancel</button>
                        <button type="submit" class="action-btn danger">Flush Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
