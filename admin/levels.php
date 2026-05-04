<?php
require_once '../config.php';
require_once '../get_setting.php';

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
            task_type VARCHAR(50) DEFAULT 'Name_items',
            deposit_amount DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch(PDOException $e) {
    // Table creation failed, continue without it
}

$msg = "";
$error = "";

// Handle level operations
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM levels WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Level deleted successfully!";
    } catch(PDOException $e) {
        $error = "Failed to delete level: " . $e->getMessage();
    }
}

// Get levels from database
$levels = [];
try {
    $stmt = $conn->prepare("SELECT * FROM levels ORDER BY sort_order ASC, id ASC");
    $stmt->execute();
    $levels = $stmt->fetchAll();
} catch(PDOException $e) {
    // Query failed, continue with empty array
}

// If no levels exist, add sample data matching screenshot
if (empty($levels)) {
    $sampleLevels = [
        ['Bronze', 1, '$1.20', 40, '$20.00'],
        ['Sliver', 2, '$1.50', 40, '$100.00'],
        ['Gold', 3, '$2.50', 40, '$250.00'],
        ['VIP 1', 4, '$4.00', 40, '$1000.00']
    ];
    
    foreach ($sampleLevels as $level) {
        try {
            $stmt = $conn->prepare("INSERT INTO levels (name, sort_order, task_reward, daily_task_limit, deposit_amount, task_type, is_active) VALUES (?, ?, ?, ?, ?, 'Name_items', 1)");
            $stmt->execute([$level[0], $level[1], $level[2], $level[3], $level[4]]);
        } catch(PDOException $e) {
            // Continue if insertion fails
        }
    }
    
    // Refresh the data
    $stmt = $conn->prepare("SELECT * FROM levels ORDER BY sort_order ASC, id ASC");
    $stmt->execute();
    $levels = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levels - HandToGlobal Admin</title>
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
        
        .levels-container {
            display: flex;
            justify-content: center;
            margin-top: 40px;
        }
        
        .card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 800px;
        }
        
        .card-header {
            background: #f8f9fa;
            padding: 20px 24px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #212529;
        }
        
        .btn-add {
            padding: 8px 16px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        
        .btn-add:hover {
            background: #218838;
        }
        
        .card-body {
            padding: 0;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 12px 24px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table td {
            padding: 16px 24px;
            border-bottom: 1px solid #f1f3f5;
            font-size: 14px;
            color: #495057;
        }
        
        .table tr:hover {
            background: #f8f9fa;
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
        
        .actions {
            display: flex;
            gap: 12px;
        }
        
        .action-link {
            text-decoration: none;
            font-size: 14px;
            transition: color 0.15s ease;
        }
        
        .action-link.edit {
            color: #556b2f;
        }
        
        .action-link.edit:hover {
            color: #3d4a1f;
        }
        
        .action-link.delete {
            color: #dc3545;
        }
        
        .action-link.delete:hover {
            color: #c82333;
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
        
        .topbar {
            background: #333;
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .topbar-left {
            display: flex;
            align-items: center;
        }
        
        .menu-icon {
            margin-right: 12px;
        }
        
        .topbar-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .topbar-right {
            display: flex;
            align-items: center;
        }
        
        .admin-badge {
            background: #4CAF50;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            margin-right: 12px;
        }
        
        .topbar-icon {
            margin-right: 12px;
        }
        
        .profile-info {
            display: flex;
            align-items: center;
        }
        
        .profile-avatar {
            background: #4CAF50;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            margin-right: 12px;
        }
        
        .profile-name {
            font-size: 14px;
            font-weight: 500;
            margin-right: 12px;
        }
        
        .dropdown-arrow {
            font-size: 14px;
            font-weight: 500;
        }
        
        .admin-layout {
            display: flex;
        }
        
        .sidebar {
            background: #333;
            color: white;
            padding: 20px;
            width: 250px;
        }
        
        .sidebar-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .sidebar-header img {
            height: 24px;
            margin-right: 12px;
        }
        
        .sidebar-header h2 {
            font-size: 18px;
            font-weight: 600;
        }
        
        .sidebar-section {
            margin-bottom: 20px;
        }
        
        .sidebar-section-title {
            font-size: 14px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 12px;
        }
        
        .sidebar-menu a {
            text-decoration: none;
            color: white;
            transition: color 0.15s ease;
        }
        
        .sidebar-menu a:hover {
            color: #ccc;
        }
        
        .sidebar-menu a.active {
            color: #4CAF50;
        }
        
        .main-content {
            padding: 20px;
            flex: 1;
        }
        
        .page-header {
            margin-bottom: 20px;
        }
        
        .page-header h1 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .page-header p {
            font-size: 14px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Topbar Header -->
    <div class="topbar">
        <div class="topbar-left">
            <div class="menu-icon">
                <i class="fas fa-bars"></i>
            </div>
            <div class="topbar-title">Levels</div>
        </div>
        <div class="topbar-right">
            <div class="admin-badge">ADMIN</div>
            <div class="topbar-icon">
                <i class="fas fa-moon"></i>
            </div>
            <div class="profile-info">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></div>
                <div class="dropdown-arrow">
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Admin Layout -->
    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <?php $site_logo = get_setting('site_logo'); ?>
                <?php if ($site_logo): ?>
                    <img src="../<?php echo $site_logo; ?>" alt="<?php echo get_setting('site_name', 'HandToGlobal'); ?>" style="height: 24px; margin-right: 12px;">
                <?php else: ?>
                    <i class="fas fa-hand-holding-usd"></i>
                <?php endif; ?>
                <h2><?php echo get_setting('site_name', 'HandToGlobal'); ?></h2>
            </div>
            
            <!-- MANAGEMENT Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">MANAGEMENT</div>
                <ul class="sidebar-menu">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    <li><a href="employees.php"><i class="fas fa-user-tie"></i> Employees</a></li>
                </ul>
            </div>
            
            <!-- PLATFORM Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">PLATFORM</div>
                <ul class="sidebar-menu">
                    <li><a href="levels.php" class="active"><i class="fas fa-layer-group"></i> Levels</a></li>
                    <li><a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="combos.php"><i class="fas fa-link"></i> Combos</a></li>
                    <li><a href="invitation-codes.php"><i class="fas fa-ticket-alt"></i> InvitationCodes</a></li>
                </ul>
            </div>
            
            <!-- FINANCE Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">FINANCE</div>
                <ul class="sidebar-menu">
                    <li><a href="finance_analysis.php"><i class="fas fa-chart-line"></i> FinanceAnalysis</a></li>
                    <li><a href="withdrawals.php"><i class="fas fa-arrow-up"></i> Withdrawals</a></li>
                </ul>
            </div>
            
            <!-- MONITORING Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">MONITORING</div>
                <ul class="sidebar-menu">
                    <li><a href="contacts.php"><i class="fas fa-address-book"></i> Contacts</a></li>
                    <li><a href="testimonials.php"><i class="fas fa-comments"></i> Testimonials</a></li>
                </ul>
            </div>
            
            <!-- SYSTEM Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">SYSTEM</div>
                <ul class="sidebar-menu">
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="languages.php"><i class="fas fa-language"></i> Languages</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">Levels</h1>
                    <button class="btn-add" onclick="window.location.href='levels_create.php'">
                        Add
                    </button>
                </div>
                
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ORDER</th>
                                <th>NAME</th>
                                <th>TYPE</th>
                                <th>REWARD</th>
                                <th>TASKS</th>
                                <th>DEPOSIT</th>
                                <th>ACTIVE</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($levels as $level): ?>
                                <tr>
                                    <td><?php echo $level['sort_order']; ?></td>
                                    <td><?php echo htmlspecialchars($level['name']); ?></td>
                                    <td>
                                        <span class="badge" style="background: #e9ecef; color: #495057;">
                                            <?php echo htmlspecialchars($level['task_type'] ?? 'Name_items'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $level['task_reward']; ?></td>
                                    <td><?php echo $level['daily_task_limit']; ?>/<?php echo $level['daily_task_limit']; ?></td>
                                    <td><?php echo $level['deposit_amount']; ?></td>
                                    <td>
                                        <span class="badge badge-active">
                                            Active
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="levels_edit.php?id=<?php echo $level['id']; ?>" class="action-link edit">Edit</a>
                                            <a href="levels.php?delete=<?php echo $level['id']; ?>" class="action-link delete" onclick="return confirm('Are you sure you want to delete this level?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($levels)): ?>
                        <div class="empty-state">
                            No levels found. Click "Add" to create the first level.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
