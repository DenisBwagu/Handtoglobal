<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
}

// Get database connection
$conn = getConnection();

$msg = "";
$error = "";

// Handle withdrawal operations
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    try {
        // Get withdrawal details
        $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE id=?");
        $stmt->execute([$id]);
        $withdrawal = $stmt->fetch();
        
        if ($withdrawal && $withdrawal['status'] === 'Pending') {
            // Check user balance
            $stmt = $conn->prepare("SELECT balance FROM users WHERE id=?");
            $stmt->execute([$withdrawal['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && $user['balance'] >= $withdrawal['amount']) {
                // Update withdrawal status
                $stmt = $conn->prepare("UPDATE withdrawals SET status='Approved' WHERE id=?");
                $stmt->execute([$id]);
                
                // Deduct from user balance
                $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id=?");
                $stmt->execute([$withdrawal['amount'], $withdrawal['user_id']]);
                
                // Create balance log
                $stmt = $conn->prepare("INSERT INTO balance_logs (user_id, admin_id, amount, action_type, reason) VALUES (?, ?, ?, 'subtract', 'Withdrawal approved')");
                $stmt->execute([$withdrawal['user_id'], $_SESSION['admin'], $withdrawal['amount']]);
                
                $msg = "Withdrawal approved successfully!";
            } else {
                $error = "Insufficient user balance for this withdrawal.";
            }
        } else {
            $error = "Withdrawal not found or already processed.";
        }
    } catch(PDOException $e) {
        $error = "Failed to approve withdrawal: " . $e->getMessage();
    }
}

if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    try {
        $stmt = $conn->prepare("UPDATE withdrawals SET status='Rejected' WHERE id=? AND status='Pending'");
        $stmt->execute([$id]);
        $msg = "Withdrawal rejected successfully!";
    } catch(PDOException $e) {
        $error = "Failed to reject withdrawal: " . $e->getMessage();
    }
}

// Get withdrawal statistics
$stats = [];
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(amount) as total_amount FROM withdrawals");
    $stmt->execute();
    $all_withdrawals = $stmt->fetch();
    $stats['total'] = $all_withdrawals['total'] ?? 0;
    $stats['total_amount'] = $all_withdrawals['total_amount'] ?? 0;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as amount FROM withdrawals WHERE status='Pending'");
    $stmt->execute();
    $pending = $stmt->fetch();
    $stats['pending'] = $pending['count'] ?? 0;
    $stats['pending_amount'] = $pending['amount'] ?? 0;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as amount FROM withdrawals WHERE status='Approved'");
    $stmt->execute();
    $approved = $stmt->fetch();
    $stats['approved'] = $approved['count'] ?? 0;
    $stats['approved_amount'] = $approved['amount'] ?? 0;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as amount FROM withdrawals WHERE status='Rejected'");
    $stmt->execute();
    $rejected = $stmt->fetch();
    $stats['rejected'] = $rejected['count'] ?? 0;
    $stats['rejected_amount'] = $rejected['amount'] ?? 0;
    
} catch(PDOException $e) {
    $error = "Failed to fetch statistics: " . $e->getMessage();
}

// Get withdrawals with filters
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$withdrawals = [];
try {
    $sql = "SELECT w.*, u.fullname, u.email FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE 1=1";
    $params = [];
    
    if ($status_filter !== 'all') {
        $sql .= " AND w.status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " AND DATE(w.created_at) BETWEEN ? AND ? ORDER BY w.created_at DESC";
    $params[] = $date_from;
    $params[] = $date_to;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $withdrawals = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch withdrawals: " . $e->getMessage();
}

// Get recent withdrawal activity
$recent_activity = [];
try {
    $stmt = $conn->prepare("
        SELECT w.*, u.fullname, u.email 
        FROM withdrawals w 
        JOIN users u ON w.user_id = u.id 
        ORDER BY w.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activity = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch recent activity: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawals Management - HandToGlobal Admin</title>
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
        
        .badge-pending {
            background: #ffc107;
            color: #212529;
        }
        
        .badge-approved {
            background: #28a745;
            color: white;
        }
        
        .badge-rejected {
            background: #dc3545;
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
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
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
        
        .wallet-address {
            font-family: monospace;
            font-size: 12px;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            word-break: break-all;
        }
        
        .amount {
            font-weight: bold;
            color: #28a745;
        }
        
        .negative {
            color: #dc3545;
        }
        
        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-info {
            flex: 1;
        }
        
        .activity-status {
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav-menu">
                <h1><i class="fas fa-money-bill-wave"></i> Withdrawals Management</h1>
                <div class="nav-links">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
                    <a href="combos.php"><i class="fas fa-layer-group"></i> Combos</a>
                    <a href="invitation_codes.php"><i class="fas fa-ticket-alt"></i> Codes</a>
                    <a href="finance_analysis.php"><i class="fas fa-chart-line"></i> Finance</a>
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
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Withdrawals</div>
                <small>$<?php echo number_format($stats['total_amount'], 2); ?></small>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
                <small>$<?php echo number_format($stats['pending_amount'], 2); ?></small>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['approved']; ?></div>
                <div class="stat-label">Approved</div>
                <small>$<?php echo number_format($stats['approved_amount'], 2); ?></small>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                <div class="stat-label">Rejected</div>
                <small>$<?php echo number_format($stats['rejected_amount'], 2); ?></small>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="form-row">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_from">From Date</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" 
                           value="<?php echo $date_from; ?>">
                </div>
                <div class="form-group">
                    <label for="date_to">To Date</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" 
                           value="<?php echo $date_to; ?>">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Withdrawal Activity</h2>
            </div>
            
            <?php foreach ($recent_activity as $activity): ?>
                <div class="activity-item">
                    <div class="activity-info">
                        <strong><?php echo htmlspecialchars($activity['fullname']); ?></strong>
                        <br>
                        <small><?php echo htmlspecialchars($activity['email']); ?></small>
                        <br>
                        <span class="amount">$<?php echo number_format($activity['amount'], 2); ?></span>
                        <br>
                        <small><?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?></small>
                    </div>
                    <div class="activity-status">
                        <span class="badge badge-<?php echo strtolower($activity['status']); ?>">
                            <?php echo $activity['status']; ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($recent_activity)): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No withdrawal activity found.
                </p>
            <?php endif; ?>
        </div>

        <!-- Withdrawals List -->
        <div class="card">
            <div class="card-header">
                <h2>Withdrawals List</h2>
                <div>
                    <button class="btn btn-success btn-sm" onclick="window.location.reload()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                    <?php if ($stats['pending'] > 0): ?>
                        <span class="alert alert-warning" style="display: inline-block; padding: 8px 12px; margin: 0;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <?php echo $stats['pending']; ?> pending withdrawal(s)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Amount</th>
                        <th>Wallet Address</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($withdrawals as $withdrawal): ?>
                        <tr>
                            <td><?php echo $withdrawal['id']; ?></td>
                            <td><?php echo htmlspecialchars($withdrawal['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($withdrawal['email']); ?></td>
                            <td>
                                <span class="amount">$<?php echo number_format($withdrawal['amount'], 2); ?></span>
                            </td>
                            <td>
                                <div class="wallet-address">
                                    <?php echo htmlspecialchars($withdrawal['wallet_address']); ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($withdrawal['status']); ?>">
                                    <?php echo $withdrawal['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y H:i', strtotime($withdrawal['created_at'])); ?></td>
                            <td>
                                <div class="actions">
                                    <?php if ($withdrawal['status'] === 'Pending'): ?>
                                        <a href="withdrawals.php?approve=<?php echo $withdrawal['id']; ?>" 
                                           class="btn btn-success btn-sm" 
                                           onclick="return confirm('Are you sure you want to approve this withdrawal of $<?php echo number_format($withdrawal['amount'], 2); ?>?')">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                        <a href="withdrawals.php?reject=<?php echo $withdrawal['id']; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Are you sure you want to reject this withdrawal?')">
                                            <i class="fas fa-times"></i> Reject
                                        </a>
                                    <?php else: ?>
                                        <span class="badge badge-<?php echo strtolower($withdrawal['status']); ?>">
                                            <?php echo $withdrawal['status']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($withdrawals)): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No withdrawals found for the selected criteria.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds for pending withdrawals
        <?php if ($stats['pending'] > 0): ?>
        setTimeout(() => {
            window.location.reload();
        }, 30000);
        <?php endif; ?>
        
        // Copy wallet address to clipboard
        function copyWalletAddress(address) {
            navigator.clipboard.writeText(address).then(function() {
                const btn = event.target;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                btn.style.background = '#28a745';
                
                setTimeout(function() {
                    btn.innerHTML = originalText;
                    btn.style.background = '';
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
            });
        }
    </script>
</body>
</html>
