<?php
require_once '../config.php';
require_once '../get_setting.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
}

// Get database connection
$conn = getConnection();

// Create employees table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch(PDOException $e) {
    // Table creation failed, continue without it
}

$msg = "";
$error = "";

// Handle employee operations
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM employees WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Employee deleted successfully!";
    } catch(PDOException $e) {
        $error = "Failed to delete employee: " . $e->getMessage();
    }
}

// Handle search and pagination
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Build query
$whereClause = '';
$params = [];

if (!empty($search)) {
    $whereClause = " WHERE (name LIKE ? OR email LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

// Get total employees count
$countSql = "SELECT COUNT(*) as total FROM employees" . $whereClause;
$stmt = $conn->prepare($countSql);
$stmt->execute($params);
$totalEmployees = $stmt->fetch()['total'];
$totalPages = ceil($totalEmployees / $limit);

// Get employees list
$sql = "SELECT * FROM employees" . $whereClause . " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// If no employees exist, add sample data
if ($totalEmployees == 0) {
    $sampleEmployees = [
        ['Denis', 'denis@globalhand.com'],
        ['aerba', 'admgrin@globalhand.com'],
        ['aliali', 'adm44in@globalhand.com'],
        ['Okk', 'okk@gmail.com'],
        ['==', 'ad1122min@globalhand.com'],
        ['11223344', 'kissgm@gmail.com'],
        ['Fred', 'Fred@globalhand.com'],
        ['Lina Vanessa', 'lina@globalhand.com'],
        ['Sanny', 'sanny@gmail.com'],
        ['March', 'march@gmail.com'],
        ['Regan Rumanzi', 'employee@globalhand.com']
    ];
    
    foreach ($sampleEmployees as $emp) {
        try {
            $stmt = $conn->prepare("INSERT INTO employees (name, email) VALUES (?, ?)");
            $stmt->execute($emp);
        } catch(PDOException $e) {
            // Continue if insertion fails
        }
    }
    
    // Refresh the data
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
    $totalEmployees = count($employees);
    $totalPages = ceil($totalEmployees / $limit);
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
    <div class="container">
        <?php if ($msg): ?>
            <div style="background: #d1e7dd; color: #0f5132; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #badbcc;">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div style="background: #f8d7da; color: #842029; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c2c7;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h1 class="card-title">AllEmployees</h1>
                <div class="header-actions">
                    <form method="GET" style="display: flex; gap: 8px;">
                        <div class="search-container">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" name="search" class="search-input" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </form>
                    <button class="btn-add" onclick="window.location.href='employee_add.php'">
                        Add
                    </button>
                </div>
            </div>
            
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>NAME</th>
                            <th>EMAIL</th>
                            <th>CODES</th>
                            <th>RECRUITED</th>
                            <th>CONTACTS</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                <td>1</td>
                                <td>0</td>
                                <td>0</td>
                                <td>
                                    <div class="actions">
                                        <a href="employee_view.php?id=<?php echo $employee['id']; ?>" class="action-link view">View</a>
                                        <a href="employees.php?delete=<?php echo $employee['id']; ?>" class="action-link delete" onclick="return confirm('Are you sure you want to delete this employee?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($employees)): ?>
                    <div class="empty-state">
                        No employees found for the selected criteria.
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
</body>
</html>
