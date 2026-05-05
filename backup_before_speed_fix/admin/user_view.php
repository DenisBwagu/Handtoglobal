<?php
require_once '../config.php';
require_once '../includes/settings_helpers.php';

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
$adminId = $_SESSION['admin_id'] ?? $_SESSION['admin'] ?? null;

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
    // Use the correct user_levels table structure with is_unlocked field
    require_once '../config.php';
    createUserLevelsTable();
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

// Get user limits data
$userLimits = [];
try {
    $stmt = $conn->prepare("SELECT * FROM user_limits WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $userLimits = $stmt->fetch();
} catch(PDOException $e) {
    // Failed to get user limits, use empty array
}

// Handle admin actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'login_as':
            // Store admin session temporarily
            $_SESSION['admin_temp_id'] = $adminId;
            $_SESSION['admin_temp_email'] = $_SESSION['admin_email'];
            $_SESSION['admin_temp_name'] = $_SESSION['admin_name'] ?? 'Admin';
            
            // Store the current user ID for return to admin functionality
            $_SESSION['last_viewed_user_id'] = $user['id'];
            
            // Set user session
            $_SESSION['user_id'] = $user['id'];
            unset($_SESSION['admin_id'], $_SESSION['admin_email'], $_SESSION['admin_name']);
            
            redirect('../dashboard.php');
            exit;
            
        case 'reset_password':
            // Generate a random password
            $newPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            try {
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $user['id']]);
                $success = "Password reset successfully! New password: <strong>$newPassword</strong>";
            } catch(PDOException $e) {
                $error = 'Failed to reset password: ' . $e->getMessage();
            }
            break;
            
        case 'adjust_balance':
            $amount = (float)$_POST['amount'];
            $operation = $_POST['operation'] ?? 'add';
            $reason = $_POST['reason'] ?? 'Manual Adjustment';
            
            // Validate amount is numeric and greater than 0
            if ($amount <= 0 || !is_numeric($amount)) {
                $error = 'Amount must be a valid number greater than 0';
            } else {
                try {
                    if ($operation === 'add') {
                        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->execute([$amount, $user['id']]);
                        $success = 'Balance credited successfully!';
                        
                        // Update user session data for real-time effect
                        updateUserSessionData($user['id']);
                        refreshUserDashboardCache($user['id']);
                        
                        // Log balance change if balance_logs table exists
                        try {
                            $stmt = $conn->prepare("INSERT INTO balance_logs (user_id, admin_id, amount, action_type, reason) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$user['id'], $adminId, $amount, 'credit', $reason]);
                        } catch(PDOException $e) {
                            // Log table doesn't exist, continue without logging
                        }
                    } else {
                        // Check if user has sufficient balance for subtraction
                        $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        $currentBalance = $stmt->fetch()['balance'];
                        
                        if ($currentBalance >= $amount) {
                            $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                            $stmt->execute([$amount, $user['id']]);
                            $success = 'Balance debited successfully!';
                            
                            // Update user session data for real-time effect
                            updateUserSessionData($user['id']);
                            refreshUserDashboardCache($user['id']);
                            
                            // Log balance change if balance_logs table exists
                            try {
                                $stmt = $conn->prepare("INSERT INTO balance_logs (user_id, admin_id, amount, action_type, reason) VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([$user['id'], $adminId, $amount, 'debit', $reason]);
                            } catch(PDOException $e) {
                                // Log table doesn't exist, continue without logging
                            }
                        } else {
                            $error = 'Insufficient balance for subtraction';
                        }
                    }
                } catch(PDOException $e) {
                    $error = 'Failed to adjust balance: ' . $e->getMessage();
                }
            }
            break;
            
        case 'unlock_level':
            $level = normalizeLevelName($_POST['level'] ?? '');
            
            error_log("DEBUG: Admin unlock attempt - user_id: " . $user['id'] . ", level: $level");
            
            if (empty($level)) {
                $error = 'Please select a level to unlock';
            } else {
                try {
                    // Get all levels from database to ensure we're using real data
                    $dbLevels = getAppLevelNames();
                    
                    if (!in_array($level, $dbLevels)) {
                        $error = 'Invalid level specified';
                    } else {
                        // Add unlocked level to user_levels table using the proper function
                        require_once '../config.php';
                        error_log("DEBUG: Admin calling unlockLevelForUser for user_id: " . $user['id'] . ", level: $level");
                        $unlockResult = unlockLevelForUser($user['id'], $level);
                        error_log("DEBUG: Admin unlock result: " . ($unlockResult ? 'SUCCESS' : 'FAILED'));
                        
                        if (!$unlockResult) {
                            $error = 'Failed to unlock level - please try again';
                        } else {
                            // Update user's current level if this is higher than their current
                            $stmt = $conn->prepare("UPDATE users SET level = ? WHERE id = ?");
                            $stmt->execute([$level, $user['id']]);
                            error_log("DEBUG: Updated user level in users table to: $level");
                            
                            // Update user session data for real-time effect
                            updateUserSessionData($user['id']);
                            refreshUserDashboardCache($user['id']);
                            
                            $success = $level . ' level unlocked successfully!';
                            error_log("DEBUG: Admin unlock completed successfully");
                        }
                    }
                } catch(PDOException $e) {
                    error_log("DEBUG: Admin unlock exception: " . $e->getMessage());
                    $error = 'Failed to unlock level: ' . $e->getMessage();
                }
            }
            break;
            
        case 'user_limits':
            // Validate and update user limits
            $maxLevelsPerDay = !empty($_POST['max_levels_per_day']) ? (int)$_POST['max_levels_per_day'] : 3;
            $minWithdrawalAmount = !empty($_POST['min_withdrawal_amount']) ? (float)$_POST['min_withdrawal_amount'] : 10.00;
            $minWithdrawalLevel = !empty($_POST['min_withdrawal_level']) ? $_POST['min_withdrawal_level'] : 'Bronze';
            $minBalanceFloor = !empty($_POST['min_balance_floor']) ? (float)$_POST['min_balance_floor'] : 0.00;
            $customMessage = !empty($_POST['custom_message']) ? $_POST['custom_message'] : '';
            
            try {
                // Check if user_limits record exists for this user
                $stmt = $conn->prepare("SELECT id FROM user_limits WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                $existingRecord = $stmt->fetch();
                
                if ($existingRecord) {
                    // Update existing record
                    $stmt = $conn->prepare("
                        UPDATE user_limits SET 
                        max_levels_per_day = ?, 
                        min_withdrawal_amount = ?, 
                        min_withdrawal_level = ?, 
                        min_balance = ?, 
                        custom_message = ? 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$maxLevelsPerDay, $minWithdrawalAmount, $minWithdrawalLevel, $minBalanceFloor, $customMessage, $user['id']]);
                } else {
                    // Insert new record
                    $stmt = $conn->prepare("
                        INSERT INTO user_limits 
                        (user_id, max_levels_per_day, min_withdrawal_amount, min_withdrawal_level, min_balance, custom_message) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$user['id'], $maxLevelsPerDay, $minWithdrawalAmount, $minWithdrawalLevel, $minBalanceFloor, $customMessage]);
                }
                
                // Update user session data for real-time effect
                updateUserSessionData($user['id']);
                refreshUserDashboardCache($user['id']);
                
                $success = 'User limits updated successfully!';
            } catch(PDOException $e) {
                $error = 'Failed to update user limits: ' . $e->getMessage();
            }
            break;
            
        case 'flush_levels':
            $selectedLevels = $_POST['flush_levels'] ?? [];
            
            if (empty($selectedLevels)) {
                $error = 'Please select at least one level to flush';
            } else {
                try {
                    require_once '../config.php';
                    foreach ($selectedLevels as $level) {
                        $level = normalizeLevelName($level);
                        $flushResult = flushLevelForUser($user['id'], $level);
                        if (!$flushResult) {
                            $error = 'Failed to flush level: ' . $level;
                            break;
                        }
                    }
                    
                    if (empty($error)) {
                        // Update user session data for real-time effect
                        updateUserSessionData($user['id']);
                        refreshUserDashboardCache($user['id']);
                        
                        $success = 'Selected levels flushed successfully! Progress reset to 0/40.';
                    }
                } catch(PDOException $e) {
                    $error = 'Failed to flush levels: ' . $e->getMessage();
                }
            }
            break;
            
        case 'flush_account':
            try {
                require_once '../config.php';
                
                // Flush all levels using the proper function
                $allLevels = getAppLevelNames();
                foreach ($allLevels as $level) {
                    flushLevelForUser($user['id'], $level);
                }
                
                // Reset user to Bronze level
                $stmt = $conn->prepare("UPDATE users SET level = 'Bronze', accuracy = 0, rating = 0, total_tasks = 0, balance = 0 WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Update user session data for real-time effect
                updateUserSessionData($user['id']);
                refreshUserDashboardCache($user['id']);
                
                $success = 'User account reset successfully! All levels flushed and progress reset.';
            } catch(PDOException $e) {
                $error = 'Failed to flush account: ' . $e->getMessage();
            }
            break;
            
        case 'toggle_status':
            try {
                // Check if is_active column exists, add it if it doesn't
                $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
                if ($check_column->rowCount() == 0) {
                    $conn->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1");
                }
                
                $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Update user session data for real-time effect
                updateUserSessionData($user['id']);
                refreshUserDashboardCache($user['id']);
                
                $success = 'User status updated successfully!';
            } catch(PDOException $e) {
                $error = 'Failed to update user status: ' . $e->getMessage();
            }
            break;
    }
    
    // Redirect to prevent form resubmission
    if ($success) {
        header("Location: user_view.php?id=" . $user['id'] . "&success=" . urlencode($success));
        exit;
    }
}

// Handle success message from redirect
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
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
    if ($balance >= 500) return 'VIP 1';
    if ($balance >= 250) return 'Gold';
    if ($balance >= 150) return 'Sliver';
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
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            position: relative;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
            font-size: 18px;
        }
        
        .close {
            font-size: 24px;
            cursor: pointer;
            color: #999;
            background: none;
            border: none;
            padding: 0;
        }
        
        .close:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 2px rgba(13,110,253,0.2);
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: normal;
        }
        
        .btn-primary {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        
        .btn-primary:hover {
            background: #0b5ed7;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            border-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5c636a;
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
                    <button class="btn btn-green" onclick="showLoginAsModal()">
                        <i class="fas fa-sign-in-alt"></i> LoginAs
                    </button>
                    <button class="btn" onclick="showPasswordModal()">
                        <i class="fas fa-key"></i> ResetPassword
                    </button>
                    <button class="btn" onclick="showUnlockModal()">
                        <i class="fas fa-unlock"></i> UnlockLevel
                    </button>
                    <button class="btn" onclick="showBalanceModal()">
                        <i class="fas fa-dollar-sign"></i> AdjustBalance
                    </button>
                    <button class="btn" onclick="showLimitsModal()">
                        <i class="fas fa-sliders-h"></i> UserLimits
                    </button>
                    <button class="btn btn-red" onclick="showToggleStatusModal()">
                        <i class="fas fa-ban"></i> <?php echo $isActive ? 'Deactivate' : 'Activate'; ?>
                    </button>
                    <button class="btn btn-orange-outline" onclick="showFlushLevelsModal()">
                        <i class="fas fa-trash"></i> FlushLevels
                    </button>
                    <button class="btn btn-red" onclick="showFlushAccountModal()">
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

    <!-- Login As Modal -->
    <div id="loginAsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Login as User</h3>
                <span class="close" onclick="closeModal('loginAsModal')">&times;</span>
            </div>
            <p>Are you sure you want to login as <strong><?php echo htmlspecialchars($user['fullname']); ?></strong>?</p>
            <p style="color: #dc3545; font-size: 12px;">You will be logged out of admin and logged in as this user.</p>
            <form method="POST">
                <input type="hidden" name="action" value="login_as">
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-green">
                        <i class="fas fa-sign-in-alt"></i> Login As User
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('loginAsModal')">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reset Password</h3>
                <span class="close" onclick="closeModal('passwordModal')">&times;</span>
            </div>
            <p style="color: #dc3545;">This will generate a new temporary password for the user.</p>
            <p><strong><?php echo htmlspecialchars($user['fullname']); ?></strong></p>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i> Confirm
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('passwordModal')">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Unlock Level Modal -->
    <div id="unlockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Unlock Level</h3>
                <span class="close" onclick="closeModal('unlockModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="unlock_level">
                <div class="form-group">
                    <label>Select Level to Unlock</label>
                    <select name="level" class="form-control" required>
                        <option value="">Select Level</option>
                        <?php
                        try {
                            $levels = getAppLevelNames();
                            foreach ($levels as $level) {
                                echo '<option value="' . htmlspecialchars($level) . '">' . htmlspecialchars($level) . '</option>';
                            }
                        } catch(PDOException $e) {
                            // Fallback to hardcoded levels if query fails
                            $defaultLevels = ['Bronze', 'Sliver', 'Gold', 'VIP 1'];
                            foreach ($defaultLevels as $level) {
                                echo '<option value="' . htmlspecialchars($level) . '">' . htmlspecialchars($level) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-unlock"></i> Confirm
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('unlockModal')">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Adjust Balance Modal -->
    <div id="balanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Adjust Balance</h3>
                <span class="close" onclick="closeModal('balanceModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="adjust_balance">
                <div class="form-group">
                    <label>Current Balance: <strong>$<?php echo number_format($user['balance'], 2); ?></strong></label>
                </div>
                <div class="form-group">
                    <label>Amount (USDT)</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label>Operation</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="operation" value="add" checked>
                            Credit
                        </label>
                        <label>
                            <input type="radio" name="operation" value="subtract">
                            Debit
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Reason</label>
                    <select name="reason" class="form-control" required>
                        <option value="">Select Reason</option>
                        <option value="Manual Adjustment">Manual Adjustment</option>
                        <option value="Bonus">Bonus</option>
                        <option value="Penalty">Penalty</option>
                        <option value="Correction">Correction</option>
                        <option value="Refund">Refund</option>
                        <option value="Reward">Reward</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-dollar-sign"></i> Confirm
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('balanceModal')">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- User Limits Modal -->
    <div id="limitsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>User Limits</h3>
                <span class="close" onclick="closeModal('limitsModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="user_limits">
                <div class="form-group">
                    <label>Max Levels Per Day</label>
                    <input type="number" name="max_levels_per_day" class="form-control" min="1" value="<?php echo htmlspecialchars($userLimits['max_levels_per_day'] ?? 3); ?>">
                </div>
                <div class="form-group">
                    <label>Min Withdrawal Amount ($)</label>
                    <input type="number" name="min_withdrawal_amount" class="form-control" step="0.01" min="0" value="<?php echo htmlspecialchars($userLimits['min_withdrawal_amount'] ?? 10.00); ?>">
                </div>
                <div class="form-group">
                    <label>Min Withdrawal Level</label>
                    <select name="min_withdrawal_level" class="form-control">
                        <option value="Bronze" <?php echo ($userLimits['min_withdrawal_level'] ?? 'Bronze') === 'Bronze' ? 'selected' : ''; ?>>Bronze</option>
                        <option value="Silver" <?php echo ($userLimits['min_withdrawal_level'] ?? 'Bronze') === 'Silver' ? 'selected' : ''; ?>>Silver</option>
                        <option value="Gold" <?php echo ($userLimits['min_withdrawal_level'] ?? 'Bronze') === 'Gold' ? 'selected' : ''; ?>>Gold</option>
                        <option value="Platinum" <?php echo ($userLimits['min_withdrawal_level'] ?? 'Bronze') === 'Platinum' ? 'selected' : ''; ?>>Platinum</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Min Balance Floor ($)</label>
                    <input type="number" name="min_balance_floor" class="form-control" step="0.01" min="0" value="<?php echo htmlspecialchars($userLimits['min_balance'] ?? 0.00); ?>">
                </div>
                <div class="form-group">
                    <label>Custom Message</label>
                    <textarea name="custom_message" class="form-control" rows="3"><?php echo htmlspecialchars($userLimits['custom_message'] ?? ''); ?></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sliders-h"></i> Save Limits
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('limitsModal')">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toggle Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Toggle User Status</h3>
                <span class="close" onclick="closeModal('statusModal')">&times;</span>
            </div>
            <p>Are you sure you want to <?php echo $isActive ? 'deactivate' : 'activate'; ?> this user?</p>
            <p><strong><?php echo htmlspecialchars($user['fullname']); ?></strong></p>
            <form method="POST">
                <input type="hidden" name="action" value="toggle_status">
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-<?php echo $isActive ? 'danger' : 'success'; ?>">
                        <i class="fas fa-<?php echo $isActive ? 'ban' : 'check'; ?>"></i> <?php echo $isActive ? 'Deactivate' : 'Activate'; ?> User
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Flush Levels Modal -->
    <div id="flushLevelsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Flush Levels</h3>
                <span class="close" onclick="closeModal('flushLevelsModal')">&times;</span>
            </div>
            <p style="color: #dc3545;">This will reset the user's level progress only.</p>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Clear completed tasks for selected levels</li>
                <li>Reset progress to 0/40 for selected levels</li>
                <li>Keep balance, deposits, withdrawals</li>
                <li>Keep login details</li>
            </ul>
            <p><strong><?php echo htmlspecialchars($user['fullname']); ?></strong></p>
            <form method="POST">
                <input type="hidden" name="action" value="flush_levels">
                <div class="form-group">
                    <div style="margin-bottom: 10px;">
                        <label>
                            <input type="checkbox" id="selectAllLevels" onchange="toggleAllLevelCheckboxes()">
                            Select All
                        </label>
                    </div>
                    <?php
                    try {
                            $levels = getAppLevelNames();
                        
                        foreach ($levels as $level) {
                            // Get completed tasks count for this level
                            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM completed_tasks ct JOIN tasks t ON ct.task_id = t.id WHERE ct.user_id = ? AND t.level = ?");
                            $stmt->execute([$user['id'], $level]);
                            $completedCount = $stmt->fetch()['count'];
                            
                            // Get total tasks count for this level
                            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tasks WHERE level = ?");
                            $stmt->execute([$level]);
                            $totalCount = $stmt->fetch()['count'];
                            
                            // Determine status
                            if ($completedCount == 0) {
                                $status = 'Not Started';
                                $statusColor = '#6c757d';
                            } elseif ($completedCount >= $totalCount) {
                                $status = 'Completed';
                                $statusColor = '#28a745';
                            } else {
                                $status = 'In Progress';
                                $statusColor = '#ffc107';
                            }
                            
                            echo '<div style="margin-bottom: 8px;">';
                            echo '<label style="display: flex; align-items: center; gap: 8px;">';
                            echo '<input type="checkbox" name="flush_levels[]" value="' . htmlspecialchars($level) . '" class="level-checkbox">';
                            echo '<span>' . htmlspecialchars($level) . '</span>';
                            echo '<span style="color: ' . $statusColor . '; font-size: 12px;">(' . $status . ')</span>';
                            echo '</label>';
                            echo '</div>';
                        }
                    } catch(PDOException $e) {
                        echo '<p style="color: #dc3545;">Unable to load levels</p>';
                    }
                    ?>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-orange-outline" id="flushLevelsBtn">
                        <i class="fas fa-trash"></i> FlushNLevels
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('flushLevelsModal')">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Flush Account Modal -->
    <div id="flushAccountModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Flush Account</h3>
                <span class="close" onclick="closeModal('flushAccountModal')">&times;</span>
            </div>
            <p style="color: #dc3545;">This will fully reset the user account activity.</p>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Clear completed tasks</li>
                <li>Reset level unlocks and progress</li>
                <li>Reset balance to 0</li>
                <li>Reset accuracy and rating</li>
                <li>Keep login details (name, email, password)</li>
                <li>Keep registration date</li>
            </ul>
            <p><strong><?php echo htmlspecialchars($user['fullname']); ?></strong></p>
            <form method="POST">
                <input type="hidden" name="action" value="flush_account">
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-red">
                        <i class="fas fa-user-times"></i> Flush Account
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('flushAccountModal')">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showLoginAsModal() {
            document.getElementById('loginAsModal').style.display = 'block';
        }
        
        function showPasswordModal() {
            document.getElementById('passwordModal').style.display = 'block';
        }
        
        function showUnlockModal() {
            document.getElementById('unlockModal').style.display = 'block';
        }
        
        function showBalanceModal() {
            document.getElementById('balanceModal').style.display = 'block';
        }
        
        function showLimitsModal() {
            document.getElementById('limitsModal').style.display = 'block';
        }
        
        function showToggleStatusModal() {
            document.getElementById('statusModal').style.display = 'block';
        }
        
        function showFlushLevelsModal() {
            document.getElementById('flushLevelsModal').style.display = 'block';
        }
        
        function showFlushAccountModal() {
            document.getElementById('flushAccountModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Form validation for balance adjustment
        document.querySelector('#balanceModal form').addEventListener('submit', function(e) {
            const amount = parseFloat(this.querySelector('[name="amount"]').value);
            const operation = this.querySelector('[name="operation"]:checked').value;
            const currentBalance = <?php echo $user['balance']; ?>;
            
            if (operation === 'subtract' && amount > currentBalance) {
                e.preventDefault();
                alert('Cannot subtract more than the current balance');
            }
        });
        
        // Flush Levels checkbox functionality
        function toggleAllLevelCheckboxes() {
            const selectAll = document.getElementById('selectAllLevels');
            const checkboxes = document.querySelectorAll('.level-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            updateFlushLevelsButton();
        }
        
        function updateFlushLevelsButton() {
            const checkboxes = document.querySelectorAll('.level-checkbox:checked');
            const button = document.getElementById('flushLevelsBtn');
            const count = checkboxes.length;
            
            if (count === 0) {
                button.innerHTML = '<i class="fas fa-trash"></i> FlushNLevels';
            } else if (count === 1) {
                button.innerHTML = '<i class="fas fa-trash"></i> Flush 1 Level';
            } else {
                button.innerHTML = '<i class="fas fa-trash"></i> Flush ' + count + ' Levels';
            }
        }
        
        // Add event listeners to level checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.level-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateFlushLevelsButton);
            });
        });
    </script>
</body>
</html>
