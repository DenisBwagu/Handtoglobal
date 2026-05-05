<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();

// Add invite_code_used column to users table if it doesn't exist
try {
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'invite_code_used'");
    if ($check_column->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN invite_code_used VARCHAR(50) DEFAULT NULL");
    }
} catch(PDOException $e) {
    // Column addition failed, continue without it
}

$msg = "";
$error = "";

// Handle user operations
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->execute([$id]);
        $msg = "User deleted successfully!";
    } catch(PDOException $e) {
        $error = "Failed to delete user: " . $e->getMessage();
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
    $whereClause = " WHERE (fullname LIKE ? OR email LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

// Get total users count
$countSql = "SELECT COUNT(*) as total FROM users" . $whereClause;
$stmt = $conn->prepare($countSql);
$stmt->execute($params);
$totalUsers = $stmt->fetch()['total'];
$totalPages = ceil($totalUsers / $limit);

// Get users list
$sql = "SELECT * FROM users" . $whereClause . " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Helper function to get user level based on balance
function getUserLevel($balance) {
    if ($balance >= 500) return 'Platinum';
    if ($balance >= 250) return 'Gold';
    if ($balance >= 150) return 'Silver';
    if ($balance >= 100) return 'Bronze';
    return 'Bronze';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Users - HandToGlobal Admin</title>
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
        
        .badge-blocked {
            background: #f8d7da;
            color: #842029;
        }
        
        .badge-completed {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .actions {
            display: flex;
            gap: 12px;
        }
        
        .action-link {
            color: #495057;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.15s ease;
        }
        
        .action-link:hover {
            color: #212529;
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
        
        .balance {
            font-weight: 500;
            color: #198754;
        }
        
        .level {
            font-weight: 500;
        }
        
        .invitation-code {
            font-family: monospace;
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
                <h1 class="card-title">AllUsers</h1>
                <form method="GET" style="display: flex; gap: 8px;">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" name="search" class="search-input" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </form>
            </div>
            
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>InvitationCode</th>
                            <th>Level</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="invitation-code"><?php echo $user['invite_code_used'] ? htmlspecialchars($user['invite_code_used']) : '-'; ?></td>
                                <td class="level"><?php echo getUserLevel($user['balance']); ?></td>
                                <td class="balance">$<?php echo number_format($user['balance'], 2); ?></td>
                                <td>
                                    <?php 
                                    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
                                    if ($check_column->rowCount() > 0) {
                                        $is_active = $user['is_active'] ?? 1;
                                    } else {
                                        $is_active = 1;
                                    }
                                    ?>
                                    <span class="badge <?php echo $is_active ? 'badge-active' : 'badge-blocked'; ?>">
                                        <?php echo $is_active ? 'Active' : 'Blocked'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="user_view.php?id=<?php echo $user['id']; ?>" class="action-link">View</a>
                                        <a href="users.php?delete=<?php echo $user['id']; ?>" class="action-link delete" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($users)): ?>
                    <div style="padding: 40px; text-align: center; color: #6c757d;">
                        No users found for the selected criteria.
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($totalUsers > 0): ?>
                <div class="table-footer">
                    <div class="table-info">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalUsers); ?> of <?php echo $totalUsers; ?>
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
