<?php
require_once '../config.php';
require_once '../get_setting.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();

$msg = "";
$error = "";
if (isset($_GET['deleted'])) {
    $msg = "Task deleted successfully!";
}

// Handle task operations
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id=?");
        $stmt->execute([$id]);
        redirect('tasks.php?deleted=1');
    } catch(PDOException $e) {
        $error = "Failed to delete task: " . $e->getMessage();
    }
}

// Pagination setup
$limit = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Level filter
$level_filter = $_GET['level'] ?? 'AllLevels';

// Get total count for pagination
$total_tasks = 0;
try {
    $count_sql = "SELECT COUNT(*) as total FROM tasks";
    $params = [];
    
    if ($level_filter !== 'AllLevels') {
        $count_sql .= " WHERE level = ?";
        $params[] = $level_filter;
    }
    
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    $total_tasks = $result['total'] ?? 0;
} catch(PDOException $e) {
    $error = "Failed to get count: " . $e->getMessage();
}

// Get tasks with pagination and filtering
$tasks = [];
try {
    $sql = "SELECT * FROM tasks";
    $params = [];
    
    if ($level_filter !== 'AllLevels') {
        $sql .= " WHERE level = ?";
        $params[] = $level_filter;
    }
    
    $sql .= " ORDER BY level, id LIMIT $limit OFFSET $offset";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch tasks: " . $e->getMessage();
}

// Calculate pagination info
$total_pages = ceil($total_tasks / $limit);
$start_record = ($page - 1) * $limit + 1;
$end_record = min($page * $limit, $total_tasks);
if ($total_tasks == 0) {
    $start_record = 0;
    $end_record = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/global-theme.css">
    <script src="../assets/js/theme.js" defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --secondary: #7c3aed;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #0284c7;
            --text: #1a1a1a;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #f5f7fb;
            --white: #ffffff;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
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
        
        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            height: 70px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 999;
        }
        
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .menu-icon {
            display: none;
            font-size: 20px;
            color: var(--muted);
            cursor: pointer;
        }
        
        .topbar-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .admin-badge {
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
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
            color: var(--muted);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .topbar-icon:hover {
            background: var(--border);
            color: var(--text);
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 12px;
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
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            flex: 1;
        }
        
        /* Tasks Container */
        .tasks-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Controls Section */
        .controls-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .level-filter {
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--white);
            color: var(--text);
            font-size: 14px;
            min-width: 120px;
        }
        
        .add-btn {
            background: #059669;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .add-btn:hover {
            background: #047857;
        }
        
        /* Table Card */
        .table-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        /* Table Styles */
        .tasks-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tasks-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
            background: var(--bg);
        }
        
        .tasks-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            vertical-align: middle;
        }
        
        .tasks-table tr:hover {
            background: var(--bg);
        }
        
        /* Type Badge */
        .type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            background: #f3f4f6;
            color: #374151;
        }
        
        /* Active Badge */
        .active-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            background: #d1fae5;
            color: #065f46;
        }
        
        /* Actions */
        .actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .edit-link {
            color: #059669;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .edit-link:hover {
            color: #047857;
            text-decoration: underline;
        }
        
        .delete-link {
            color: var(--danger);
            text-decoration: none;
            font-size: 14px;
        }
        
        .delete-link:hover {
            color: #dc2626;
            text-decoration: underline;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            background: var(--bg);
        }
        
        .pagination-info {
            font-size: 14px;
            color: var(--muted);
        }
        
        .pagination-controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .pagination-btn {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 6px 12px;
            font-size: 14px;
            color: var(--text);
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .pagination-btn:hover {
            background: var(--bg);
        }
        
        .pagination-btn.active {
            background: var(--warning);
            color: white;
            border-color: var(--warning);
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--text);
        }
        
        .empty-state p {
            font-size: 14px;
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
            <div class="topbar-title">Tasks</div>
        </div>
        <div class="topbar-right">
            <div class="admin-badge">ADMIN</div>
            <form class="language-form" method="post" action="../language_action.php">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/tasks.php'); ?>">
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
                    <li><a href="levels.php"><i class="fas fa-layer-group"></i> Levels</a></li>
                    <li><a href="tasks.php" class="active"><i class="fas fa-tasks"></i> Tasks</a></li>
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
            <div class="tasks-container">
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
                
                <!-- Controls Section -->
                <div class="controls-section">
                    <div>
                        <select class="level-filter" onchange="window.location.href='?level=' + this.value">
                            <option value="AllLevels" <?php echo $level_filter === 'AllLevels' ? 'selected' : ''; ?>>AllLevels</option>
                            <option value="Bronze" <?php echo $level_filter === 'Bronze' ? 'selected' : ''; ?>>Bronze</option>
                            <option value="Sliver" <?php echo $level_filter === 'Sliver' ? 'selected' : ''; ?>>Sliver</option>
                            <option value="Gold" <?php echo $level_filter === 'Gold' ? 'selected' : ''; ?>>Gold</option>
                            <option value="VIP 1" <?php echo $level_filter === 'VIP 1' ? 'selected' : ''; ?>>VIP 1</option>
                        </select>
                    </div>
                    <div>
                        <a href="task_create.php" class="add-btn">Add</a>
                    </div>
                </div>
                
                <!-- Table Card -->
                <div class="table-card">
                    <table class="tasks-table">
                        <thead>
                            <tr>
                                <th>TITLE</th>
                                <th>TYPE</th>
                                <th>LEVEL</th>
                                <th>ACTIVE</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                                    <td>
                                        <span class="type-badge"><?php echo htmlspecialchars($task['type']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($task['level']); ?></td>
                                    <td>
                                        <?php if ($task['active']): ?>
                                            <span class="active-badge">Active</span>
                                        <?php else: ?>
                                            <span class="type-badge">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="task_edit.php?id=<?= $task['id'] ?>" class="edit-link">Edit</a>
                                            <a href="?delete=<?php echo $task['id']; ?>" class="delete-link" 
                                               onclick="return confirm('Are you sure you want to delete this task?')">
                                                Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($tasks)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No tasks found</h3>
                            <p>No tasks match the selected criteria.</p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Pagination -->
                    <?php if ($total_tasks > 0): ?>
                        <div class="pagination">
                            <div class="pagination-info">
                                Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_tasks; ?>
                            </div>
                            <div class="pagination-controls">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&level=<?php echo urlencode($level_filter); ?>" class="pagination-btn">
                                        « Previous
                                    </a>
                                <?php else: ?>
                                    <button class="pagination-btn" disabled>« Previous</button>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 5);
                                $end_page = min($total_pages, $page + 5);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <a href="?page=<?php echo $i; ?>&level=<?php echo urlencode($level_filter); ?>" 
                                       class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&level=<?php echo urlencode($level_filter); ?>" class="pagination-btn">
                                        Next »
                                    </a>
                                <?php else: ?>
                                    <button class="pagination-btn" disabled>Next »</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
