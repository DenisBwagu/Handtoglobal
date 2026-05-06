<?php
require_once '../config.php';
$conn = getConnection();
$pdo = $conn;
require_once '../includes/settings_helpers.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}


$msg = "";
$error = "";
if (isset($_GET['deleted'])) {
    $msg = "User deleted successfully!";
}

// Handle user operations
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->execute([$id]);
        redirect('users.php?deleted=1');
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Users - <?php echo htmlspecialchars(get_site_name()); ?> Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="includes/admin_styles.css">
</head>
        </head>
<body>
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    
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
                <h1>Users Management</h1>
                <p>Manage all registered users</p>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">All Users</h1>
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
        </div>
    </div>
</body>
</html>
