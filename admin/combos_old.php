<?php
require_once '../config.php';
require_once '../includes/settings_helpers.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();

$msg = "";
$error = "";

// Handle combo operations
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $combo_id = (int)($_GET['id'] ?? 0);
    
    if ($combo_id > 0) {
        try {
            switch ($action) {
                case 'activate':
                    $stmt = $conn->prepare("UPDATE combos SET status = 'Active' WHERE id = ?");
                    $stmt->execute([$combo_id]);
                    $msg = "Combo activated successfully!";
                    break;
                    
                case 'cancel':
                    $stmt = $conn->prepare("UPDATE combos SET status = 'Cancelled' WHERE id = ?");
                    $stmt->execute([$combo_id]);
                    $msg = "Combo cancelled successfully!";
                    break;
                    
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM combos WHERE id = ?");
                    $stmt->execute([$combo_id]);
                    $msg = "Combo deleted successfully!";
                    break;
            }
        } catch(PDOException $e) {
            $error = "Failed to update combo: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'AllStatus';
$search_user = $_GET['search_user'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter !== 'AllStatus') {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

if (!empty($search_user)) {
    $where_conditions[] = "(u.fullname LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search_user%";
    $params[] = "%$search_user%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get combos with user and task information
$combos = [];
try {
    $sql = "
        SELECT 
            c.*,
            u.fullname,
            u.email,
            start_task.title as start_task_title,
            end_task.title as end_task_title,
            (SELECT COUNT(*) FROM tasks WHERE level = c.level) as total_tasks_in_level,
            (SELECT COUNT(*) FROM tasks WHERE level = c.level AND id BETWEEN c.start_task_id AND c.end_task_id) as tasks_in_combo
        FROM combos c
        JOIN users u ON c.user_id = u.id
        JOIN tasks start_task ON c.start_task_id = start_task.id
        JOIN tasks end_task ON c.end_task_id = end_task.id
        $where_clause
        ORDER BY c.created_at DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $combos = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch combos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Combos - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #4f46e5;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text: #1a1a1a;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #f5f7fb;
            --white: #ffffff;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            flex: 1;
        }
        
        /* Controls Section */
        .controls-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 16px;
        }
        
        .controls-left {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .status-filter, .search-input {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background: var(--white);
        }
        
        .search-input {
            width: 200px;
        }
        
        .add-btn {
            background: var(--success);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .add-btn:hover {
            background: #16a34a;
        }
        
        /* Table Card */
        .table-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .combos-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .combos-table th {
            background: var(--bg);
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }
        
        .combos-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        
        .combos-table tr:hover {
            background: var(--bg);
        }
        
        /* User Column */
        .user-name {
            font-weight: 600;
            color: var(--text);
        }
        
        .user-email {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }
        
        /* Badges */
        .multiplier-badge {
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Actions */
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .btn-edit {
            background: var(--primary);
            color: white;
        }
        
        .btn-edit:hover {
            background: #4338ca;
        }
        
        .btn-activate {
            background: var(--success);
            color: white;
        }
        
        .btn-activate:hover {
            background: #16a34a;
        }
        
        .btn-cancel {
            background: var(--warning);
            color: white;
        }
        
        .btn-cancel:hover {
            background: #d97706;
        }
        
        .btn-delete {
            background: var(--danger);
            color: white;
        }
        
        .btn-delete:hover {
            background: #dc2626;
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
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
            <div class="topbar-title">Combos</div>
        </div>
        <div class="topbar-right">
            <div class="admin-badge">ADMIN</div>
            <div class="profile-info">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></div>
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
                    <li><a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="combos.php" class="active"><i class="fas fa-link"></i> Combos</a></li>
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
                <div class="controls-left">
                    <select class="status-filter" onchange="window.location.href='?status=' + this.value + '&search_user=' + encodeURIComponent('<?php echo $search_user; ?>')">
                        <option value="AllStatus" <?php echo $status_filter === 'AllStatus' ? 'selected' : ''; ?>>AllStatus</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <input type="text" class="search-input" placeholder="SearchUser" value="<?php echo htmlspecialchars($search_user); ?>" 
                           onkeypress="if(event.key === 'Enter') window.location.href='?search_user=' + encodeURIComponent(this.value) + '&status=<?php echo $status_filter; ?>'">
                </div>
                <div>
                    <a href="combo_create.php" class="add-btn">New</a>
                </div>
            </div>
            
            <!-- Table Card -->
            <div class="table-card">
                <?php if (!empty($combos)): ?>
                    <table class="combos-table">
                        <thead>
                            <tr>
                                <th>USER</th>
                                <th>LEVEL</th>
                                <th>TASKRANGE</th>
                                <th>MULTIPLIER</th>
                                <th>DEPOSIT</th>
                                <th>STATUS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($combos as $combo): ?>
                                <tr>
                                    <td>
                                        <div class="user-name"><?php echo htmlspecialchars($combo['fullname']); ?></div>
                                        <div class="user-email"><?php echo htmlspecialchars($combo['email']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($combo['level']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($combo['start_task_title']); ?> → <?php echo htmlspecialchars($combo['end_task_title']); ?>
                                        <div class="user-email"><?php echo $combo['tasks_in_combo']; ?> NTasks</div>
                                    </td>
                                    <td>
                                        <span class="multiplier-badge"><?php echo $combo['multiplier']; ?>x</span>
                                    </td>
                                    <td>$<?php echo number_format($combo['deposit_amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($combo['status']); ?>">
                                            <?php echo htmlspecialchars($combo['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="combo_edit.php?id=<?php echo $combo['id']; ?>" class="action-btn btn-edit">Edit</a>
                                            <?php if ($combo['status'] !== 'Active'): ?>
                                                <a href="?action=activate&id=<?php echo $combo['id']; ?>" class="action-btn btn-activate">Activate</a>
                                            <?php endif; ?>
                                            <?php if ($combo['status'] !== 'Cancelled' && $combo['status'] !== 'Completed'): ?>
                                                <a href="?action=cancel&id=<?php echo $combo['id']; ?>" class="action-btn btn-cancel">Cancel</a>
                                            <?php endif; ?>
                                            <a href="?action=delete&id=<?php echo $combo['id']; ?>" class="action-btn btn-delete" 
                                               onclick="return confirm('Are you sure you want to delete this combo?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-link"></i>
                        <h3>No combos found</h3>
                        <p>Create your first combo to get started.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Combos - HandToGlobal Admin</title>
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
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 8px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .checkbox-item:hover {
            background: #f8f9fa;
        }
        
        .checkbox-item input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .checkbox-item label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
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
        
        .combo-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .task-list {
            margin-top: 10px;
            font-size: 12px;
            color: #666;
        }
        
        .task-item {
            display: inline-block;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            margin: 2px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav-menu">
                <h1><i class="fas fa-layer-group"></i> Task Combos</h1>
                <div class="nav-links">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
                    <a href="combos.php"><i class="fas fa-layer-group"></i> Combos</a>
                    <a href="deposits.php"><i class="fas fa-dollar-sign"></i> Deposits</a>
                    <a href="withdrawals.php"><i class="fas fa-money-bill-wave"></i> Withdrawals</a>
                    <a href="/handtoglobal/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
                <div class="stat-number"><?php echo count($combos); ?></div>
                <div class="stat-label">Total Combos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $active_count = array_filter($combos, fn($c) => $c['is_active']);
                    echo count($active_count);
                    ?>
                </div>
                <div class="stat-label">Active Combos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($tasks); ?></div>
                <div class="stat-label">Available Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $total_combo_value = array_sum(array_column($combos, 'total_reward'));
                    echo '$' . number_format($total_combo_value, 2);
                    ?>
                </div>
                <div class="stat-label">Total Combo Value</div>
            </div>
        </div>

        <!-- Add/Edit Combo Form -->
        <div class="card">
            <div class="card-header">
                <h2><?php echo $edit_combo ? 'Edit Combo' : 'Create New Combo'; ?></h2>
                <?php if ($edit_combo): ?>
                    <a href="combos.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
            
            <form method="POST">
                <?php if ($edit_combo): ?>
                    <input type="hidden" name="edit_combo" value="1">
                    <input type="hidden" name="combo_id" value="<?php echo $edit_combo['id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="add_combo" value="1">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">Combo Name *</label>
                    <input type="text" id="name" name="name" class="form-control" 
                           value="<?php echo $edit_combo ? htmlspecialchars($edit_combo['name']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control"><?php 
                        echo $edit_combo ? htmlspecialchars($edit_combo['description']) : ''; 
                    ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="discount">Discount Percentage (%)</label>
                    <input type="number" id="discount" name="discount" class="form-control" 
                           step="0.01" min="0" max="100" 
                           value="<?php echo $edit_combo ? $edit_combo['discount_percentage'] : '0'; ?>">
                    <small style="color: #666;">Discount applied to total task reward</small>
                </div>
                
                <div class="form-group">
                    <label>Select Tasks *</label>
                    <div class="checkbox-group">
                        <?php 
                        $selected_tasks = $edit_combo ? explode(',', $edit_combo['task_ids']) : [];
                        foreach ($tasks as $task): 
                        ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="task_ids[]" value="<?php echo $task['id']; ?>" 
                                       id="task_<?php echo $task['id']; ?>"
                                       <?php echo in_array($task['id'], $selected_tasks) ? 'checked' : ''; ?>>
                                <label for="task_<?php echo $task['id']; ?>">
                                    <?php echo htmlspecialchars($task['title']); ?> 
                                    (<?php echo $task['level']; ?> - $<?php echo $task['reward']; ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if ($edit_combo): ?>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php echo $edit_combo['is_active'] ? 'checked' : ''; ?>>
                            Active Combo
                        </label>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $edit_combo ? 'Update Combo' : 'Create Combo'; ?>
                </button>
            </form>
        </div>

        <!-- Combos List -->
        <div class="card">
            <div class="card-header">
                <h2>All Combos</h2>
                <button class="btn btn-success btn-sm" onclick="window.location.reload()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Tasks</th>
                        <th>Discount</th>
                        <th>Total Reward</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($combos as $combo): ?>
                        <tr>
                            <td><?php echo $combo['id']; ?></td>
                            <td><?php echo htmlspecialchars($combo['name']); ?></td>
                            <td><?php echo htmlspecialchars(substr($combo['description'] ?? '', 0, 50)) . '...'; ?></td>
                            <td>
                                <?php 
                                $task_ids = explode(',', $combo['task_ids']);
                                $task_count = count($task_ids);
                                echo $task_count . ' task' . ($task_count > 1 ? 's' : '');
                                ?>
                            </td>
                            <td><?php echo $combo['discount_percentage']; ?>%</td>
                            <td>$<?php echo number_format($combo['total_reward'], 2); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $combo['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $combo['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($combo['created_at'])); ?></td>
                            <td>
                                <div class="actions">
                                    <a href="combos.php?edit=<?php echo $combo['id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="combos.php?toggle=<?php echo $combo['id']; ?>" 
                                       class="btn btn-secondary btn-sm" 
                                       title="<?php echo $combo['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas fa-<?php echo $combo['is_active'] ? 'pause' : 'play'; ?>"></i>
                                    </a>
                                    <a href="combos.php?delete=<?php echo $combo['id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this combo?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($combos)): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No combos found. Create your first combo above!
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Calculate total reward when tasks are selected
        function calculateTotalReward() {
            const checkboxes = document.querySelectorAll('input[name="task_ids[]"]:checked');
            const discount = parseFloat(document.getElementById('discount').value) || 0;
            let total = 0;
            
            checkboxes.forEach(checkbox => {
                const label = document.querySelector(`label[for="${checkbox.id}"]`);
                const rewardText = label.textContent.match(/\$([\d.]+)/);
                if (rewardText) {
                    total += parseFloat(rewardText[1]);
                }
            });
            
            const finalTotal = total * (1 - (discount / 100));
            console.log(`Tasks: ${checkboxes.length}, Total: $${total.toFixed(2)}, Discount: ${discount}%, Final: $${finalTotal.toFixed(2)}`);
        }
        
        // Add event listeners
        document.querySelectorAll('input[name="task_ids[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', calculateTotalReward);
        });
        
        document.getElementById('discount').addEventListener('input', calculateTotalReward);
    </script>
</body>
</html>
