<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
}

// Get database connection
$conn = getConnection();

// Create balance_logs table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS balance_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            admin_id INT,
            amount DECIMAL(10,2) NOT NULL,
            action_type ENUM('add', 'subtract', 'withdrawal', 'deposit', 'task_reward') NOT NULL,
            reason VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (admin_id) REFERENCES admins(id)
        )
    ");
} catch(PDOException $e) {
    // Table creation failed, continue without it
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

if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    try {
        // Check if is_active column exists, if not add it
        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
        if ($check_column->rowCount() == 0) {
            $conn->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        }
        
        $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id=?");
        $stmt->execute([$id]);
        $msg = "User status updated successfully!";
    } catch(PDOException $e) {
        $error = "Failed to update user status: " . $e->getMessage();
    }
}

if (isset($_POST['update_balance'])) {
    $user_id = (int)$_POST['user_id'];
    $amount = (float)$_POST['amount'];
    $action = $_POST['balance_action'];
    
    if ($amount <= 0) {
        $error = "Amount must be greater than 0";
    } else {
        try {
            if ($action === 'add') {
                $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id=?");
                $stmt->execute([$amount, $user_id]);
                $msg = "Balance added successfully!";
            } elseif ($action === 'subtract') {
                $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id=? AND balance >= ?");
                $stmt->execute([$amount, $user_id, $amount]);
                if ($stmt->rowCount() > 0) {
                    $msg = "Balance deducted successfully!";
                } else {
                    $error = "Insufficient balance";
                }
            }
            
            // Log balance change
            if ($msg) {
                try {
                    $stmt = $conn->prepare("INSERT INTO balance_logs (user_id, admin_id, amount, action_type, reason) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $_SESSION['admin'], $amount, $action, 'Admin manual adjustment']);
                } catch(PDOException $e) {
                    // Log table doesn't exist, continue without logging
                }
            }
        } catch(PDOException $e) {
            $error = "Failed to update balance: " . $e->getMessage();
        }
    }
}

// Handle search and pagination
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$whereClause = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (u.fullname LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

if ($status_filter !== 'all') {
    // Check if is_active column exists
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    if ($check_column->rowCount() > 0) {
        $whereClause .= " AND u.is_active = ?";
        $params[] = $status_filter === 'active' ? '1' : '0';
    }
}

// Get total users count
$countSql = "SELECT COUNT(*) as total FROM users u" . $whereClause;
$stmt = $conn->prepare($countSql);
$stmt->execute($params);
$totalUsers = $stmt->fetch()['total'];
$totalPages = ceil($totalUsers / $limit);

// Get users list with additional stats
$sql = "SELECT u.*, 
               (SELECT COUNT(*) FROM completed_tasks WHERE user_id = u.id) as tasks_completed,
               (SELECT COUNT(*) FROM deposits WHERE user_id = u.id AND status = 'Approved') as deposits_count,
               (SELECT COUNT(*) FROM withdrawals WHERE user_id = u.id AND status = 'Approved') as withdrawals_count
        FROM users u" . $whereClause . " 
        ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get statistics
$stats = [];
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $stats['total'] = $stmt->fetch()['total'];
    
    // Check if is_active column exists
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    if ($check_column->rowCount() > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) as active FROM users WHERE is_active = 1");
        $stmt->execute();
        $stats['active'] = $stmt->fetch()['active'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as inactive FROM users WHERE is_active = 0");
        $stmt->execute();
        $stats['inactive'] = $stmt->fetch()['inactive'];
    } else {
        $stats['active'] = $stats['total'];
        $stats['inactive'] = 0;
    }
    
    $stmt = $conn->prepare("SELECT SUM(balance) as total_balance FROM users");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_balance'] = $result['total_balance'] ?? 0;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as new_users FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $stats['new_users'] = $stmt->fetch()['new_users'];
    
} catch(PDOException $e) {
    $error = "Failed to fetch statistics: " . $e->getMessage();
}

// Get user details for balance update
$user_details = null;
if (isset($_GET['edit_balance'])) {
    $id = (int)$_GET['edit_balance'];
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$id]);
        $user_details = $stmt->fetch();
    } catch(PDOException $e) {
        $error = "Failed to fetch user details: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - HandToGlobal Admin</title>
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
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .badge-new {
            background: #17a2b8;
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
        
        .filter-bar {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .balance {
            font-weight: bold;
            color: #28a745;
        }
        
        .negative {
            color: #dc3545;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        
        .pagination a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #667eea;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        
        .pagination .active {
            background: #667eea;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close {
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav-menu">
                <h1><i class="fas fa-users"></i> Users Management</h1>
                <div class="nav-links">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
                    <a href="combos.php"><i class="fas fa-layer-group"></i> Combos</a>
                    <a href="invitation_codes.php"><i class="fas fa-ticket-alt"></i> Codes</a>
                    <a href="finance_analysis.php"><i class="fas fa-chart-line"></i> Finance</a>
                    <a href="deposits.php"><i class="fas fa-dollar-sign"></i> Deposits</a>
                    <a href="withdrawals.php"><i class="fas fa-money-bill-wave"></i> Withdrawals</a>
                    <a href="contacts.php"><i class="fas fa-envelope"></i> Contacts</a>
                    <a href="testimonials.php"><i class="fas fa-quote-left"></i> Testimonials</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="languages.php"><i class="fas fa-language"></i> Languages</a>
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
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['inactive']; ?></div>
                <div class="stat-label">Inactive Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['total_balance'], 2); ?></div>
                <div class="stat-label">Total Balance</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['new_users']; ?></div>
                <div class="stat-label">New Users (7 days)</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="form-row">
                <div class="form-group">
                    <label for="search">Search Users</label>
                    <input type="text" id="search" name="search" class="form-control" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Name, Email, or Phone">
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Users List -->
        <div class="card">
            <div class="card-header">
                <h2>Users List</h2>
                <button class="btn btn-success btn-sm" onclick="window.location.reload()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Balance</th>
                        <th>Tasks</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($user['fullname']); ?></strong>
                                <?php 
                                $created_date = new DateTime($user['created_at']);
                                $now = new DateTime();
                                $days_diff = $now->diff($created_date)->days;
                                if ($days_diff <= 7): 
                                ?>
                                    <span class="badge badge-new">New</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo $user['phone'] ? htmlspecialchars($user['phone']) : '-'; ?></td>
                            <td>
                                <span class="balance">$<?php echo number_format($user['balance'], 2); ?></span>
                            </td>
                            <td><?php echo $user['tasks_completed']; ?></td>
                            <td>
                                <?php 
                                // Check if is_active column exists
                                $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
                                if ($check_column->rowCount() > 0) {
                                    $is_active = $user['is_active'] ?? 1;
                                ?>
                                    <span class="badge badge-<?php echo $is_active ? 'active' : 'inactive'; ?>">
                                        <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                                    </span>
                                <?php } else { ?>
                                    <span class="badge badge-active">Active</span>
                                <?php } ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="actions">
                                    <a href="users.php?edit_balance=<?php echo $user['id']; ?>" 
                                       class="btn btn-warning btn-sm" 
                                       onclick="showBalanceModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['fullname']); ?>', <?php echo $user['balance']; ?>); return false;">
                                        <i class="fas fa-dollar-sign"></i> Balance
                                    </a>
                                    <?php 
                                    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
                                    if ($check_column->rowCount() > 0) {
                                        $is_active = $user['is_active'] ?? 1;
                                    ?>
                                        <a href="users.php?toggle_status=<?php echo $user['id']; ?>" 
                                           class="btn btn-secondary btn-sm" 
                                           onclick="return confirm('Are you sure you want to <?php echo $is_active ? 'deactivate' : 'activate'; ?> this user?')">
                                            <i class="fas fa-<?php echo $is_active ? 'pause' : 'play'; ?>"></i>
                                        </a>
                                    <?php } ?>
                                    <a href="users.php?delete=<?php echo $user['id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($users)): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No users found for the selected criteria.
                </p>
            <?php endif; ?>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" 
                           class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Balance Update Modal -->
    <div id="balanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update User Balance</h3>
                <span class="close" onclick="closeBalanceModal()">&times;</span>
            </div>
            
            <form method="POST">
                <input type="hidden" id="modal_user_id" name="user_id">
                
                <div class="form-group">
                    <label>User: <strong id="modal_user_name"></strong></label>
                    <small>Current Balance: $<span id="modal_current_balance"></span></small>
                </div>
                
                <div class="form-group">
                    <label>Amount (USDT)</label>
                    <input type="number" id="modal_amount" name="amount" class="form-control" 
                           step="0.01" min="0.01" required>
                </div>
                
                <div class="form-group">
                    <label>Action</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="balance_action" value="add" checked>
                            Add to Balance
                        </label>
                        <label>
                            <input type="radio" name="balance_action" value="subtract">
                            Subtract from Balance
                        </label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="update_balance" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Balance
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeBalanceModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showBalanceModal(userId, userName, currentBalance) {
            document.getElementById('modal_user_id').value = userId;
            document.getElementById('modal_user_name').textContent = userName;
            document.getElementById('modal_current_balance').textContent = currentBalance.toFixed(2);
            document.getElementById('balanceModal').style.display = 'block';
        }
        
        function closeBalanceModal() {
            document.getElementById('balanceModal').style.display = 'none';
            document.getElementById('modal_amount').value = '';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('balanceModal');
            if (event.target === modal) {
                closeBalanceModal();
            }
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (e.target.querySelector('[name="update_balance"]')) {
                const amount = parseFloat(document.getElementById('modal_amount').value);
                const action = document.querySelector('input[name="balance_action"]:checked').value;
                const currentBalance = parseFloat(document.getElementById('modal_current_balance').textContent);
                
                if (action === 'subtract' && amount > currentBalance) {
                    e.preventDefault();
                    alert('Cannot subtract more than the current balance');
                }
            }
        });
    </script>
</body>
</html>
