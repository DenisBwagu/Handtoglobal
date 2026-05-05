<?php
require_once '../config.php';
require_once '../includes/settings_helpers.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();


// Initialize variables
$msg = '';
$employees = [];
$totalEmployees = 0;

// Handle pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;
$totalPages = ceil($totalEmployees / $limit);

// Fetch employees data
try {
    $stmt = $conn->query("SELECT id, name, email, role, status, created_at FROM employees ORDER BY created_at DESC");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalEmployees = count($employees);
    $totalPages = ceil($totalEmployees / $limit);
} catch (Exception $e) {
    $msg = "Error loading employees: " . $e->getMessage();
    $employees = [];
    $totalEmployees = 0;
    $totalPages = 0;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Employees - HandToGlobal Admin</title>
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
            margin: 0;
            padding: 0;
        }
        
        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
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
            cursor: pointer;
            color: var(--text);
            font-size: 18px;
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
            cursor: pointer;
            color: var(--muted);
            font-size: 16px;
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
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
            color: var(--muted);
            font-size: 12px;
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
            background: var(--white);
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
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            flex: 1;
        }
        
        /* Alert */
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }
        
        .page-header p {
            color: var(--muted);
            font-size: 14px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
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
        
        .header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .search-container {
            position: relative;
        }
        
        .search-input {
            padding: 8px 12px 8px 36px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 14px;
            width: 250px;
            outline: none;
            transition: border-color 0.15s ease;
        }
        
        .search-input:focus {
            border-color: #86b7fe;
        }
        
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 14px;
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
        
        .actions {
            display: flex;
            gap: 12px;
        }
        
        .action-link {
            text-decoration: none;
            font-size: 14px;
            transition: color 0.15s ease;
        }
        
        .action-link.view {
            color: #556b2f;
        }
        
        .action-link.view:hover {
            color: #3d4a1f;
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
        
        .table-footer {
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }
        
        .table-info {
            font-size: 14px;
            color: #6c757d;
        }
        
        .pagination {
            display: flex;
            gap: 4px;
        }
        
        .pagination a {
            padding: 6px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #495057;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.15s ease;
        }
        
        .pagination a:hover {
            background: #e9ecef;
            color: #212529;
        }
        
        .pagination a.active {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        
        .empty-state {
            padding: 40px;
            text-align: center;
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
            <div class="topbar-title">Employees</div>
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
                    <li><a href="employees.php" class="active"><i class="fas fa-user-tie"></i> Employees</a></li>
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
            
            <div class="page-header">
                <h1><?php echo get_translation('employees_management', 'Employees Management'); ?></h1>
                <p>Manage all employees</p>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">All Employees</h1>
                    <div class="header-actions">
                        <form method="GET" style="display: flex; gap: 8px;">
                            <div class="search-container">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" name="search" placeholder="<?php echo get_translation('search', 'Search'); ?>..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>
                        </form>
                        <a href="employee_create.php" class="btn btn-success"><?php echo get_translation('add', 'Add'); ?></a>
                    </div>
                </div>
                
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th><?php echo get_translation('actions', 'Actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['role'] ?? 'Employee'); ?></td>
                                    <td>
                                        <span class="badge badge-active">
                                            <?php echo htmlspecialchars($employee['status'] ?? 'Active'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($employee['created_at'])); ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="employee_view.php?id=<?php echo $employee['id']; ?>" class="action-link view"><?php echo get_translation('view', 'View'); ?></a>
                                            <a href="employee_edit.php?id=<?php echo $employee['id']; ?>" class="action-link edit"><?php echo get_translation('edit', 'Edit'); ?></a>
                                            <a href="employees.php?delete=<?php echo $employee['id']; ?>" class="action-link delete" onclick="return confirm('Are you sure you want to delete this employee?')"><?php echo get_translation('delete', 'Delete'); ?></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($employees)): ?>
                        <div class="empty-state">
                            No employees found. Click "<?php echo get_translation('add', 'Add'); ?>" to create the first employee.
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($totalEmployees > 0): ?>
                    <div class="table-footer">
                        <div class="table-info">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalEmployees); ?> of <?php echo $totalEmployees; ?>
                        </div>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
