<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
}

// Get database connection
$conn = getConnection();

// Create levels table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS levels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            min_balance DECIMAL(10,2) NOT NULL,
            max_balance DECIMAL(10,2),
            task_reward DECIMAL(10,2) NOT NULL,
            daily_task_limit INT DEFAULT 40,
            withdrawal_limit DECIMAL(10,2) DEFAULT 10000,
            referral_bonus DECIMAL(10,2) DEFAULT 0,
            color VARCHAR(7) DEFAULT '#667eea',
            icon VARCHAR(50),
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch(PDOException $e) {
    die("Failed to create levels table: " . $e->getMessage());
}

// Create user_levels table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS user_levels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            level_id INT NOT NULL,
            unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (level_id) REFERENCES levels(id),
            UNIQUE KEY unique_user_level (user_id, level_id)
        )
    ");
} catch(PDOException $e) {
    die("Failed to create user_levels table: " . $e->getMessage());
}

$msg = "";
$error = "";

// Handle level operations
if (isset($_POST['add_level'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $min_balance = (float)$_POST['min_balance'];
    $max_balance = !empty($_POST['max_balance']) ? (float)$_POST['max_balance'] : null;
    $task_reward = (float)$_POST['task_reward'];
    $daily_task_limit = (int)$_POST['daily_task_limit'];
    $withdrawal_limit = (float)$_POST['withdrawal_limit'];
    $referral_bonus = (float)$_POST['referral_bonus'];
    $color = trim($_POST['color']);
    $icon = trim($_POST['icon']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = (int)$_POST['sort_order'];
    
    if (empty($name) || $min_balance < 0 || $task_reward < 0) {
        $error = "Please fill all required fields with valid values";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO levels (name, description, min_balance, max_balance, task_reward, daily_task_limit, withdrawal_limit, referral_bonus, color, icon, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $min_balance, $max_balance, $task_reward, $daily_task_limit, $withdrawal_limit, $referral_bonus, $color, $icon, $is_active, $sort_order]);
            $msg = "Level added successfully!";
        } catch(PDOException $e) {
            $error = "Failed to add level: " . $e->getMessage();
        }
    }
}

if (isset($_POST['edit_level'])) {
    $id = (int)$_POST['level_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $min_balance = (float)$_POST['min_balance'];
    $max_balance = !empty($_POST['max_balance']) ? (float)$_POST['max_balance'] : null;
    $task_reward = (float)$_POST['task_reward'];
    $daily_task_limit = (int)$_POST['daily_task_limit'];
    $withdrawal_limit = (float)$_POST['withdrawal_limit'];
    $referral_bonus = (float)$_POST['referral_bonus'];
    $color = trim($_POST['color']);
    $icon = trim($_POST['icon']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = (int)$_POST['sort_order'];
    
    if (empty($name) || $min_balance < 0 || $task_reward < 0) {
        $error = "Please fill all required fields with valid values";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE levels SET name=?, description=?, min_balance=?, max_balance=?, task_reward=?, daily_task_limit=?, withdrawal_limit=?, referral_bonus=?, color=?, icon=?, is_active=?, sort_order=? WHERE id=?");
            $stmt->execute([$name, $description, $min_balance, $max_balance, $task_reward, $daily_task_limit, $withdrawal_limit, $referral_bonus, $color, $icon, $is_active, $sort_order, $id]);
            $msg = "Level updated successfully!";
        } catch(PDOException $e) {
            $error = "Failed to update level: " . $e->getMessage();
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // Check if any users are at this level
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_levels WHERE level_id=?");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            $error = "Cannot delete level. $count users are currently at this level.";
        } else {
            $stmt = $conn->prepare("DELETE FROM levels WHERE id=?");
            $stmt->execute([$id]);
            $msg = "Level deleted successfully!";
        }
    } catch(PDOException $e) {
        $error = "Failed to delete level: " . $e->getMessage();
    }
}

if (isset($_GET['toggle_active'])) {
    $id = (int)$_GET['toggle_active'];
    try {
        $stmt = $conn->prepare("UPDATE levels SET is_active = NOT is_active WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Level status updated successfully!";
    } catch(PDOException $e) {
        $error = "Failed to update level status: " . $e->getMessage();
    }
}

if (isset($_GET['recalculate_levels'])) {
    try {
        // Get all users and their current balance
        $stmt = $conn->prepare("SELECT id, balance FROM users ORDER BY balance DESC");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        // Get all levels ordered by min_balance
        $stmt = $conn->prepare("SELECT * FROM levels WHERE is_active=1 ORDER BY min_balance DESC");
        $stmt->execute();
        $levels = $stmt->fetchAll();
        
        foreach ($users as $user) {
            // Find the highest level the user qualifies for
            $user_level_id = null;
            foreach ($levels as $level) {
                if ($user['balance'] >= $level['min_balance']) {
                    if (!$level['max_balance'] || $user['balance'] <= $level['max_balance']) {
                        $user_level_id = $level['id'];
                        break;
                    }
                }
            }
            
            if ($user_level_id) {
                // Check if user already has this level
                $stmt = $conn->prepare("SELECT id FROM user_levels WHERE user_id=? AND level_id=?");
                $stmt->execute([$user['id'], $user_level_id]);
                
                if (!$stmt->fetch()) {
                    // Remove all existing level assignments for this user
                    $stmt = $conn->prepare("DELETE FROM user_levels WHERE user_id=?");
                    $stmt->execute([$user['id']]);
                    
                    // Assign new level
                    $stmt = $conn->prepare("INSERT INTO user_levels (user_id, level_id) VALUES (?, ?)");
                    $stmt->execute([$user['id'], $user_level_id]);
                }
            }
        }
        
        $msg = "User levels recalculated successfully!";
    } catch(PDOException $e) {
        $error = "Failed to recalculate levels: " . $e->getMessage();
    }
}

// Get levels
$levels = [];
try {
    $stmt = $conn->prepare("SELECT l.*, 
                                  (SELECT COUNT(*) FROM user_levels WHERE level_id = l.id) as user_count
                           FROM levels l 
                           ORDER BY l.sort_order ASC, l.min_balance ASC");
    $stmt->execute();
    $levels = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch levels: " . $e->getMessage();
}

// Get level statistics
$stats = [];
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM levels");
    $stmt->execute();
    $stats['total_levels'] = $stmt->fetch()['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as active FROM levels WHERE is_active=1");
    $stmt->execute();
    $stats['active_levels'] = $stmt->fetch()['active'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as users_with_levels FROM user_levels");
    $stmt->execute();
    $stats['users_with_levels'] = $stmt->fetch()['users_with_levels'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch()['total_users'];
    
} catch(PDOException $e) {
    $error = "Failed to fetch statistics: " . $e->getMessage();
}

// Get level for editing
$edit_level = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    try {
        $stmt = $conn->prepare("SELECT * FROM levels WHERE id=?");
        $stmt->execute([$id]);
        $edit_level = $stmt->fetch();
    } catch(PDOException $e) {
        $error = "Failed to fetch level for editing: " . $e->getMessage();
    }
}

// Get users at each level
$level_users = [];
try {
    $stmt = $conn->prepare("
        SELECT l.name as level_name, u.fullname, u.email, u.balance, ul.unlocked_at
        FROM user_levels ul
        JOIN levels l ON ul.level_id = l.id
        JOIN users u ON ul.user_id = u.id
        ORDER BY l.min_balance DESC, u.balance DESC
    ");
    $stmt->execute();
    $level_users = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch level users: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levels Management - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .nav-menu {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102,126,234,0.2);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-active {
            background: #28a745;
            color: white;
        }
        
        .badge-inactive {
            background: #6c757d;
            color: white;
        }
        
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .level-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .level-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .level-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .level-title h3 {
            margin: 0;
            color: #333;
        }
        
        .level-title p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }
        
        .level-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .level-stat {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .level-stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
        }
        
        .level-stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .color-input {
            width: 50px;
            height: 40px;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            height: 8px;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav-menu">
                <h1><i class="fas fa-layer-group"></i> Levels Management</h1>
                <div class="nav-links">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a href="employees.php"><i class="fas fa-users-cog"></i> Employees</a>
                    <a href="levels.php"><i class="fas fa-layer-group"></i> Levels</a>
                    <a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
                    <a href="combos.php"><i class="fas fa-layer-group"></i> Combos</a>
                    <a href="invitation-codes.php"><i class="fas fa-ticket-alt"></i> Codes</a>
                    <a href="finance-analysis.php"><i class="fas fa-chart-line"></i> Finance</a>
                    <a href="../admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($msg): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_levels']; ?></div>
                <div class="stat-label">Total Levels</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_levels']; ?></div>
                <div class="stat-label">Active Levels</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['users_with_levels']; ?></div>
                <div class="stat-label">Users with Levels</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>

        <!-- Add/Edit Level Form -->
        <div class="card">
            <div class="card-header">
                <h2><?php echo $edit_level ? 'Edit Level' : 'Add New Level'; ?></h2>
                <?php if ($edit_level): ?>
                    <a href="levels.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
            
            <form method="POST">
                <?php if ($edit_level): ?>
                    <input type="hidden" name="edit_level" value="1">
                    <input type="hidden" name="level_id" value="<?php echo $edit_level['id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="add_level" value="1">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Level Name *</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo $edit_level ? htmlspecialchars($edit_level['name']) : ''; ?>" 
                               placeholder="e.g., Bronze, Silver, Gold" required>
                    </div>
                    <div class="form-group">
                        <label for="color">Level Color</label>
                        <input type="color" id="color" name="color" class="color-input" 
                               value="<?php echo $edit_level ? htmlspecialchars($edit_level['color']) : '#667eea'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="icon">Icon (FontAwesome class)</label>
                        <input type="text" id="icon" name="icon" class="form-control" 
                               value="<?php echo $edit_level ? htmlspecialchars($edit_level['icon']) : 'fas fa-medal'; ?>" 
                               placeholder="e.g., fas fa-medal">
                    </div>
                    <div class="form-group">
                        <label for="sort_order">Sort Order</label>
                        <input type="number" id="sort_order" name="sort_order" class="form-control" 
                               value="<?php echo $edit_level ? htmlspecialchars($edit_level['sort_order']) : '0'; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control"><?php 
                        echo $edit_level ? htmlspecialchars($edit_level['description']) : ''; 
                    ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="min_balance">Minimum Balance (USDT) *</label>
                        <input type="number" id="min_balance" name="min_balance" class="form-control" 
                               step="0.01" min="0" 
                               value="<?php echo $edit_level ? htmlspecialchars($edit_level['min_balance']) : ''; ?>" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="max_balance">Maximum Balance (USDT)</label>
                        <input type="number" id="max_balance" name="max_balance" class="form-control" 
                               step="0.01" min="0" 
                               value="<?php echo $edit_level ? htmlspecialchars($edit_level['max_balance']) : ''; ?>">
                        <small style="color: #666;">Leave empty for no maximum</small>
                    </div>
                    <div class="form-group">
                        <label for="task_reward">Task Reward (USDT) *</label>
                        <input type="number" id="task_reward" name="task_reward" class="form-control" 
                               step="0.01" min="0" 
                               value="<?php echo $edit_level ? htmlspecialchars($edit_level['task_reward']) : ''; ?>" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="daily_task_limit">Daily Task Limit</label>
                        <input type="number" id="daily_task_limit" name="daily_task_limit" class="form-control" 
                               min="1" value="<?php echo $edit_level ? htmlspecialchars($edit_level['daily_task_limit']) : '40'; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="withdrawal_limit">Withdrawal Limit (USDT)</label>
                        <input type="number" id="withdrawal_limit" name="withdrawal_limit" class="form-control" 
                               step="0.01" min="0" 
                               value="<?php echo $edit_level ? htmlspecialchars($edit_level['withdrawal_limit']) : '10000'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="referral_bonus">Referral Bonus (USDT)</label>
                        <input type="number" id="referral_bonus" name="referral_bonus" class="form-control" 
                               step="0.01" min="0" 
                               value="<?php echo $edit_level ? htmlspecialchars($edit_level['referral_bonus']) : '0'; ?>">
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" value="1" 
                                   <?php echo $edit_level ? ($edit_level['is_active'] ? 'checked' : '') : 'checked'; ?>>
                            <label for="is_active">Active</label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $edit_level ? 'Update Level' : 'Add Level'; ?>
                </button>
            </form>
        </div>

        <!-- Levels List -->
        <div class="card">
            <div class="card-header">
                <h2>All Levels</h2>
                <div>
                    <a href="levels.php?recalculate_levels=1" class="btn btn-warning btn-sm" 
                       onclick="return confirm('Recalculate all user levels based on current balances?')">
                        <i class="fas fa-sync"></i> Recalculate Levels
                    </a>
                    <button class="btn btn-success btn-sm" onclick="window.location.reload()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </div>
            
            <?php foreach ($levels as $level): ?>
                <div class="level-card">
                    <div class="level-header">
                        <div class="level-icon" style="background: <?php echo htmlspecialchars($level['color']); ?>;">
                            <i class="<?php echo htmlspecialchars($level['icon'] ?? 'fas fa-medal'); ?>"></i>
                        </div>
                        <div class="level-title">
                            <h3><?php echo htmlspecialchars($level['name']); ?></h3>
                            <p><?php echo htmlspecialchars($level['description']); ?></p>
                        </div>
                        <div style="margin-left: auto;">
                            <span class="badge badge-<?php echo $level['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $level['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="level-stats">
                        <div class="level-stat">
                            <div class="level-stat-value">$<?php echo number_format($level['min_balance'], 2); ?></div>
                            <div class="level-stat-label">Min Balance</div>
                        </div>
                        <div class="level-stat">
                            <div class="level-stat-value">$<?php echo number_format($level['task_reward'], 2); ?></div>
                            <div class="level-stat-label">Task Reward</div>
                        </div>
                        <div class="level-stat">
                            <div class="level-stat-value"><?php echo $level['daily_task_limit']; ?></div>
                            <div class="level-stat-label">Daily Tasks</div>
                        </div>
                        <div class="level-stat">
                            <div class="level-stat-value">$<?php echo number_format($level['withdrawal_limit'], 2); ?></div>
                            <div class="level-stat-label">Withdrawal Limit</div>
                        </div>
                        <div class="level-stat">
                            <div class="level-stat-value">$<?php echo number_format($level['referral_bonus'], 2); ?></div>
                            <div class="level-stat-label">Referral Bonus</div>
                        </div>
                        <div class="level-stat">
                            <div class="level-stat-value"><?php echo $level['user_count']; ?></div>
                            <div class="level-stat-label">Users at Level</div>
                        </div>
                    </div>
                    
                    <div class="actions" style="margin-top: 15px;">
                        <a href="levels.php?edit=<?php echo $level['id']; ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="levels.php?toggle_active=<?php echo $level['id']; ?>" 
                           class="btn btn-secondary btn-sm" 
                           onclick="return confirm('Are you sure you want to toggle this level status?')">
                            <i class="fas fa-<?php echo $level['is_active'] ? 'eye-slash' : 'eye'; ?>"></i> 
                            <?php echo $level['is_active'] ? 'Deactivate' : 'Activate'; ?>
                        </a>
                        <a href="levels.php?delete=<?php echo $level['id']; ?>" 
                           class="btn btn-danger btn-sm" 
                           onclick="return confirm('Are you sure you want to delete this level?')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($levels)): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No levels found. Add your first level above!
                </p>
            <?php endif; ?>
        </div>

        <!-- Users by Level -->
        <?php if (!empty($level_users)): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Users by Level</h2>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Balance</th>
                            <th>Unlocked At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($level_users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['level_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>$<?php echo number_format($user['balance'], 2); ?></td>
                                <td><?php echo date('M j, Y H:i', strtotime($user['unlocked_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
