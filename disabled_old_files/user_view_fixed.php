<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get user ID from URL
$userId = $_GET['id'] ?? null;
if (!$userId || !is_numeric($userId)) {
    redirect('users.php');
    exit;
}

// Get database connection
$conn = getConnection();

// Create necessary tables if they don't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS completed_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            task_id INT NOT NULL,
            completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (task_id) REFERENCES tasks(id)
        )
    ");
} catch(PDOException $e) {
    // Table creation failed, continue without it
}

try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            level VARCHAR(50) DEFAULT 'Bronze',
            reward DECIMAL(10,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch(PDOException $e) {
    // Table creation failed, continue without it
}

try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS user_levels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            level VARCHAR(50) DEFAULT 'Bronze',
            score DECIMAL(10,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
} catch(PDOException $e) {
    // Table creation failed, continue without it
}

// Add invite_code_used column to users table if it doesn't exist
try {
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'invite_code_used'");
    if ($check_column->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN invite_code_used VARCHAR(50) DEFAULT NULL");
    }
} catch(PDOException $e) {
    // Column addition failed, continue without it
}

// Add level column to completed_tasks if it doesn't exist
try {
    $check_column = $conn->query("SHOW COLUMNS FROM completed_tasks LIKE 'level'");
    if ($check_column->rowCount() == 0) {
        $conn->exec("ALTER TABLE completed_tasks ADD COLUMN level VARCHAR(50) DEFAULT 'Bronze'");
    }
} catch(PDOException $e) {
    // Column addition failed, continue without it
}

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found");
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
                $success = 'Password reset successfully!';
            }
            break;
            
        case 'adjust_balance':
            $amount = (float)$_POST['amount'];
            $operation = $_POST['operation'] ?? 'add';
            
            if ($amount <= 0) {
                $error = 'Amount must be greater than 0';
            } else {
                try {
                    if ($operation === 'add') {
                        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->execute([$amount, $user['id']]);
                        $success = 'Balance added successfully!';
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?");
                        $stmt->execute([$amount, $user['id'], $amount]);
                        $success = 'Balance deducted successfully!';
                    }
                } catch(PDOException $e) {
                    $error = 'Failed to adjust balance';
                }
            }
            break;
    }
}

// Get user statistics with error handling
$levelStats = [];
$latestTask = null;
$taskCount = 0;
$userScore = 0;

if ($user) {
    try {
        // Check if level column exists in completed_tasks
        $check_column = $conn->query("SHOW COLUMNS FROM completed_tasks LIKE 'level'");
        
        if ($check_column->rowCount() > 0) {
            // Task completion counts per level
            $stmt = $conn->prepare("SELECT level, COUNT(*) as completed FROM completed_tasks WHERE user_id = ? GROUP BY level");
            $stmt->execute([$user['id']]);
            while ($row = $stmt->fetch()) {
                $levelStats[$row['level']] = $row['completed'];
            }
        }
        
        // Latest completed task
        $stmt = $conn->prepare("SELECT ct.*, t.title, t.reward FROM completed_tasks ct JOIN tasks t ON ct.task_id = t.id WHERE ct.user_id = ? ORDER BY ct.completed_at DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $latestTask = $stmt->fetch();
        
        // Total task count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM completed_tasks WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $taskCount = $stmt->fetch()['count'];
        
        // User score (calculate from completed tasks)
        $stmt = $conn->prepare("SELECT COALESCE(SUM(t.reward), 0) as total_reward FROM completed_tasks ct JOIN tasks t ON ct.task_id = t.id WHERE ct.user_id = ?");
        $stmt->execute([$user['id']]);
        $userScore = $stmt->fetch()['total_reward'];
        
    } catch(PDOException $e) {
        // Query failed, use default values
        $taskCount = 0;
        $userScore = 0;
    }
}

// Helper function to get user level based on balance
function getUserLevel($balance) {
    if ($balance >= 500) return 'Platinum';
    if ($balance >= 250) return 'Gold';
    if ($balance >= 150) return 'Silver';
    if ($balance >= 100) return 'Bronze';
    return 'Bronze';
}

// Helper function to get level reward
function getLevelReward($balance) {
    if ($balance >= 500) return '$5.00';
    if ($balance >= 250) return '$3.50';
    if ($balance >= 150) return '$2.50';
    if ($balance >= 100) return '$1.80';
    return '$1.80';
}

// Check if user is active
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($check_column->rowCount() > 0) {
    $isActive = $user['is_active'] ?? 1;
} else {
    $isActive = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card-header {
            padding: 24px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .card-body {
            padding: 24px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #0d6efd;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 600;
        }
        
        .user-details h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .user-details p {
            color: #6c757d;
            font-size: 14px;
        }
        
        .user-stats {
            display: flex;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .stat-item {
            display: flex;
            flex-direction: column;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .stat-value {
            font-size: 16px;
            font-weight: 600;
            color: #212529;
        }
        
        .balance {
            color: #198754 !important;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 500;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-active {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 6px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: white;
            color: #495057;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        
        .btn:hover {
            background: #f8f9fa;
        }
        
        .btn-green {
            background: #198754;
            color: white;
            border-color: #198754;
        }
        
        .btn-green:hover {
            background: #157347;
        }
        
        .btn-red {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        
        .btn-red:hover {
            background: #c82333;
        }
        
        .btn-orange-outline {
            background: transparent;
            color: #fd7e14;
            border-color: #fd7e14;
        }
        
        .btn-orange-outline:hover {
            background: #fd7e14;
            color: white;
        }
        
        .live-activity {
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .live-activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .live-activity-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #212529;
        }
        
        .live-indicator {
            width: 8px;
            height: 8px;
            background: #28a745;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .live-status {
            color: #6c757d;
            font-size: 12px;
        }
        
        .activity-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .activity-card {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }
        
        .activity-card-title {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .activity-card-value {
            font-size: 18px;
            font-weight: 600;
            color: #212529;
            margin-bottom: 4px;
        }
        
        .activity-card-subtitle {
            font-size: 12px;
            color: #6c757d;
        }
        
        .activity-card-subtitle.green {
            color: #28a745;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .progress-fill {
            height: 100%;
            background: #28a745;
            transition: width 0.3s ease;
        }
        
        .last-completed {
            font-size: 14px;
            color: #495057;
        }
        
        .last-completed strong {
            color: #212529;
        }
        
        .last-completed time {
            color: #6c757d;
            float: right;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f3f5;
            font-size: 14px;
            color: #495057;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .badge-completed {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .empty-state {
            padding: 40px;
            text-align: center;
            color: #6c757d;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h2><?php echo htmlspecialchars($user['fullname']); ?></h2>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                </div>
                
                <div class="user-stats">
                    <div class="stat-item">
                        <span class="stat-label">Balance</span>
                        <span class="stat-value balance">$<?php echo number_format($user['balance'], 2); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Level</span>
                        <span class="stat-value"><?php echo getUserLevel($user['balance']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Status</span>
                        <span class="stat-value">
                            <span class="badge <?php echo $isActive ? 'badge-active' : 'badge-blocked'; ?>">
                                <?php echo $isActive ? 'Active' : 'Blocked'; ?>
                            </span>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Score</span>
                        <span class="stat-value"><?php echo number_format($userScore, 2); ?></span>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-green">
                        <i class="fas fa-sign-in-alt"></i> LoginAs
                    </button>
                    <button class="btn">
                        <i class="fas fa-key"></i> ResetPassword
                    </button>
                    <button class="btn">
                        <i class="fas fa-unlock"></i> UnlockLevel
                    </button>
                    <button class="btn">
                        <i class="fas fa-dollar-sign"></i> AdjustBalance
                    </button>
                    <button class="btn">
                        <i class="fas fa-sliders-h"></i> UserLimits
                    </button>
                    <button class="btn btn-red">
                        <i class="fas fa-ban"></i> Deactivate
                    </button>
                    <button class="btn btn-orange-outline">
                        <i class="fas fa-trash"></i> FlushLevels
                    </button>
                    <button class="btn btn-red">
                        <i class="fas fa-user-times"></i> FlushAccount
                    </button>
                </div>
            </div>
        </div>
        
        <div class="live-activity">
            <div class="live-activity-header">
                <div class="live-activity-title">
                    <span class="live-indicator"></span>
                    LiveActivity
                </div>
                <div class="live-status">UpdatingLive</div>
            </div>
            
            <div class="activity-cards">
                <div class="activity-card">
                    <div class="activity-card-title">LEVEL</div>
                    <div class="activity-card-value"><?php echo getUserLevel($user['balance']); ?></div>
                    <div class="activity-card-subtitle">#1 - <?php echo getLevelReward($user['balance']); ?>/task</div>
                </div>
                
                <div class="activity-card">
                    <div class="activity-card-title">WORKINGON</div>
                    <div class="activity-card-value">LevelComplete</div>
                    <div class="activity-card-subtitle green">LevelComplete</div>
                </div>
                
                <div class="activity-card">
                    <div class="activity-card-title">PROGRESS</div>
                    <div class="activity-card-value"><?php echo $taskCount; ?> / 40</div>
                    <div class="activity-card-subtitle">
                        <?php $progress = min(100, ($taskCount / 40) * 100); ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                        <?php echo round($progress); ?>%
                    </div>
                </div>
            </div>
            
            <?php if ($latestTask): ?>
                <div class="last-completed">
                    <strong>LastCompleted:</strong>
                    <?php echo htmlspecialchars($latestTask['title']); ?> +<?php echo getLevelReward($user['balance']); ?>
                    <time><?php echo date('g\h \a\g\o', strtotime($latestTask['completed_at'])); ?></time>
                </div>
            <?php else: ?>
                <div class="last-completed">
                    <strong>LastCompleted:</strong>
                    No tasks completed yet
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 style="font-size: 18px; font-weight: 600;">TaskCompletions</h3>
            </div>
            <div class="card-body">
                <?php if ($taskCount > 0): ?>
                    <table class="table">
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
                            <?php
                            // Get completed tasks with error handling
                            try {
                                $stmt = $conn->prepare("SELECT ct.*, t.title, t.level as task_level, t.reward FROM completed_tasks ct JOIN tasks t ON ct.task_id = t.id WHERE ct.user_id = ? ORDER BY ct.completed_at DESC LIMIT 10");
                                $stmt->execute([$user['id']]);
                                $completedTasks = $stmt->fetchAll();
                                
                                foreach ($completedTasks as $task):
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                                    <td><?php echo htmlspecialchars($task['task_level'] ?? 'Bronze'); ?></td>
                                    <td>$<?php echo number_format($task['reward'], 2); ?></td>
                                    <td>
                                        <span class="badge badge-completed">Completed</span>
                                    </td>
                                    <td><?php echo date('m/d/Y', strtotime($task['completed_at'])); ?></td>
                                </tr>
                            <?php 
                                endforeach;
                            } catch(PDOException $e) {
                                // Query failed, show error message
                                echo '<tr><td colspan="5" style="text-align: center; color: #6c757d;">Unable to load task completions</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        No task completions found for this user.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
