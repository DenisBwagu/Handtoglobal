<?php
require_once '../config.php';
require_once '../get_setting.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();

// Create missing tables if they don't exist
try {
    // Create employees table if it doesn't exist
    $conn->exec("CREATE TABLE IF NOT EXISTS `employees` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `email` varchar(255) NOT NULL,
        `role` varchar(100) DEFAULT 'Employee',
        `status` enum('active','inactive') DEFAULT 'active',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Create invitation_codes table if it doesn't exist
    $conn->exec("CREATE TABLE IF NOT EXISTS `invitation_codes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `code` varchar(20) NOT NULL,
        `reward` decimal(10,2) DEFAULT 0.00,
        `uses_remaining` int(11) DEFAULT 1,
        `is_active` tinyint(1) DEFAULT 1,
        `created_by` int(11) DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`),
        KEY `is_active` (`is_active`),
        FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Insert sample employee if table is empty
    $employeeCount = $conn->query("SELECT COUNT(*) as count FROM employees")->fetch()['count'];
    if ($employeeCount == 0) {
        $conn->exec("INSERT INTO `employees` (`name`, `email`, `role`, `status`) VALUES 
            ('John Doe', 'john@handtoglobal.com', 'Manager', 'active'),
            ('Jane Smith', 'jane@handtoglobal.com', 'Employee', 'active')");
    }
    
    // Insert sample invitation code if table is empty
    $codeCount = $conn->query("SELECT COUNT(*) as count FROM invitation_codes")->fetch()['count'];
    if ($codeCount == 0) {
        $conn->exec("INSERT INTO `invitation_codes` (`code`, `reward`, `uses_remaining`) VALUES 
            ('WELCOME2024', 5.00, 10)");
    }
    
} catch (PDOException $e) {
    // Table creation might have failed, continue with existing data
}

// Get dashboard statistics
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
$activeUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_blocked = 0")->fetch()['count'];
$totalEmployees = $conn->query("SELECT COUNT(*) as count FROM employees")->fetch()['count'];

// Tasks Completed
$tasksCompleted = $conn->query("SELECT COUNT(*) as count FROM completed_tasks")->fetch()['count'];

// Pending Withdrawals
$pendingWithdrawals = $conn->query("SELECT COUNT(*) as count FROM withdrawals WHERE status = 'pending'")->fetch()['count'];

// Total Paid Out
$totalPaidOut = $conn->query("SELECT SUM(amount) as total FROM withdrawals WHERE status = 'completed'")->fetch()['total'] ?? 0;

// Recent Users (latest 5)
$recentUsers = $conn->query("SELECT fullname, email, level, balance, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/global-theme.css">
    <script src="../assets/js/theme.js" defer></script>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #1C13B2;
            --secondary: #7c3aed;
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #dc2626;
            --info: #0284c7;
            
            --bg: #f5f7fb;
            --surface: #ffffff;
            --sidebar: #101828;
            --sidebar-soft: #2A3242;
            --text: #101828;
            --muted: #000000;
            --border: #e5e7eb;
            
            --radius: 16px;
            --radius-sm: 10px;
            --shadow: 0 10px 30px rgba(16,24,40,.08);
            --shadow-soft: 0 4px 14px rgba(16,24,40,.06);
            --transition: .22s ease;
        }
        
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            margin: 0;
            padding: 0;
            color: var(--text);
        }
        
        /* Topbar Header */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            background: white;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 1000;
            box-shadow: var(--shadow-soft);
        }
        
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .menu-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .menu-icon:hover {
            background: var(--primary);
            color: white;
        }
        
        .topbar-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text);
        }
        
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .admin-badge {
            background: var(--success);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .topbar-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .topbar-icon:hover {
            background: var(--primary);
            color: white;
        }
        
        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            cursor: pointer;
            position: relative;
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .profile-name {
            font-weight: 500;
            color: var(--text);
        }
        
        .dropdown-arrow {
            font-size: 12px;
            color: var(--muted);
            opacity: 0.6;
        }
        
        /* Admin Layout */
        .admin-layout {
            display: flex;
            margin-top: 70px;
            min-height: calc(100vh - 70px);
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--border);
            padding: 20px 0;
            position: fixed;
            top: 70px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 900;
        }
        
        .sidebar-header {
            display: flex;
            align-items: center;
            padding: 0 20px;
            margin-bottom: 30px;
        }
        
        .sidebar-header i {
            font-size: 24px;
            margin-right: 12px;
            color: var(--primary);
        }
        
        .sidebar-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        
        .sidebar-section {
            margin-bottom: 25px;
            padding: 0 20px;
        }
        
        .sidebar-section-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            opacity: 0.6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            padding-left: 5px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 2px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: var(--text);
            text-decoration: none;
            border-radius: 0;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }
        
        .sidebar-menu a:hover {
            background: var(--bg);
            color: var(--primary);
        }
        
        .sidebar-menu a.active {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border-left: 3px solid var(--success);
            border-radius: 0 8px 8px 0;
        }
        
        .sidebar-menu i {
            margin-right: 12px;
            width: 16px;
            font-size: 14px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            flex: 1;
            max-width: 1200px;
        }
        
        .dashboard-header {
            margin-bottom: 30px;
        }
        
        .dashboard-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            color: var(--text);
        }
        
        /* Stat Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-soft);
            display: flex;
            align-items: center;
            height: 85px;
        }
        
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 18px;
        }
        
        .stat-icon.users {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
        }
        
        .stat-icon.active {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
        }
        
        .stat-icon.employees {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .stat-icon.tasks {
            background: rgba(124, 58, 237, 0.1);
            color: var(--secondary);
        }
        
        .stat-icon.pending {
            background: rgba(251, 146, 60, 0.1);
            color: #fb923c;
        }
        
        .stat-icon.paid {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-label {
            font-size: 12px;
            font-weight: 500;
            color: var(--muted);
            opacity: 0.7;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            line-height: 1;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--success);
            color: white;
        }
        
        .btn-primary:hover {
            background: #15803d;
            transform: translateY(-1px);
        }
        
        .btn-outline {
            background: white;
            color: var(--text);
            border: 1px solid var(--border);
        }
        
        .btn-outline:hover {
            background: var(--bg);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* Recent Users Table */
        .recent-users-card {
            background: white;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            margin: 0;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: var(--bg);
            padding: 12px 20px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }
        
        .data-table td {
            padding: 12px 20px;
            font-size: 14px;
            color: var(--text);
            border-bottom: 1px solid rgba(229, 231, 235, 0.5);
        }
        
        .data-table tr:hover {
            background: var(--bg);
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .level-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
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
            color: var(--secondary);
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
            <div class="topbar-title">Dashboard</div>
        </div>
        <div class="topbar-right">
            <div class="admin-badge">ADMIN</div>
            <form class="language-form" method="post" action="../language_action.php">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php'); ?>">
                <input type="hidden" name="context" value="admin">
                <select name="language" onchange="this.form.submit()">
                    <?php foreach (['english' => 'English', 'chinese' => 'Chinese'] as $code => $label): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($_SESSION['admin_language'] ?? $_SESSION['language'] ?? 'english') === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="topbar-icon theme-toggle" id="themeToggle">
                <i class="fas fa-moon theme-icon" id="themeIcon"></i>
            </div>
            <a href="../admin_logout.php" style="display:inline-flex;align-items:center;gap:8px;height:34px;padding:0 12px;border-radius:6px;background:#dc2626;color:#fff;text-decoration:none;font-size:13px;font-weight:700;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <div class="profile-info">
                <div class="profile-avatar">A</div>
                <div class="profile-name">Admin</div>
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
                    <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    <li><a href="employees.php"><i class="fas fa-user-tie"></i> Employees</a></li>
                </ul>
            </div>
            
            <!-- PLATFORM Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">PLATFORM</div>
                <ul class="sidebar-menu">
                    <li><a href="levels.php"><i class="fas fa-layer-group"></i> Levels</a></li>
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
                    <li><a href="../admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="dashboard-header">
                <h1>Dashboard</h1>
            </div>
            
            <!-- Stat Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">TotalUsers</div>
                        <div class="stat-value"><?php echo $totalUsers; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon active">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">ActiveUsers</div>
                        <div class="stat-value"><?php echo $activeUsers; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon employees">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Employees</div>
                        <div class="stat-value"><?php echo $totalEmployees; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon tasks">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">TasksCompleted</div>
                        <div class="stat-value"><?php echo $tasksCompleted; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">PendingWithdrawals</div>
                        <div class="stat-value"><?php echo $pendingWithdrawals; ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon paid">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">TotalPaidOut</div>
                        <div class="stat-value">$<?php echo number_format($totalPaidOut, 2); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="employees.php?action=create" class="btn btn-primary">
                    CreateEmployee
                </a>
                <a href="invitation-codes.php" class="btn btn-outline">
                    GenerateCodes
                </a>
                <a href="withdrawals.php" class="btn btn-outline">
                    ViewWithdrawals
                </a>
            </div>
            
            <!-- Recent Users Table -->
            <div class="recent-users-card">
                <div class="card-header">
                    <h2 class="card-title">RecentUsers</h2>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Level</th>
                                <th>Balance</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="level-badge level-<?php echo strtolower($user['level']); ?>">
                                            <?php echo htmlspecialchars($user['level']); ?>
                                        </span>
                                    </td>
                                    <td>$<?php echo number_format($user['balance'], 2); ?></td>
                                    <td><?php echo date('n/j/Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($recentUsers)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--muted); opacity: 0.6;">
                                        No users found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
