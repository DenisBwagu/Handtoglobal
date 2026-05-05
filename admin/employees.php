<?php
require_once '../config.php';
require_once '../get_setting.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
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
    <title>Employee Management - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="includes/admin_styles.css">
</head>
        </head>
<body>
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>
    
    <!-- Admin Layout -->
    <div class="admin-layout">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
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
