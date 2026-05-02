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

// Handle date range filtering
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Validate dates
if (!strtotime($date_from) || !strtotime($date_to)) {
    $date_from = date('Y-m-01');
    $date_to = date('Y-m-d');
}

// Get financial statistics
$stats = [];
try {
    // Total deposits
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM deposits WHERE status='Approved' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $deposits = $stmt->fetch();
    $stats['deposits_count'] = $deposits['count'] ?? 0;
    $stats['deposits_total'] = $deposits['total'] ?? 0;
    
    // Total withdrawals
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM withdrawals WHERE status='Approved' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $withdrawals = $stmt->fetch();
    $stats['withdrawals_count'] = $withdrawals['count'] ?? 0;
    $stats['withdrawals_total'] = $withdrawals['total'] ?? 0;
    
    // Pending transactions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM deposits WHERE status='Pending'");
    $stmt->execute();
    $stats['pending_deposits'] = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM withdrawals WHERE status='Pending'");
    $stmt->execute();
    $stats['pending_withdrawals'] = $stmt->fetch()['count'] ?? 0;
    
    // Total users and active users
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as active FROM users WHERE balance > 0");
    $stmt->execute();
    $stats['active_users'] = $stmt->fetch()['active'] ?? 0;
    
    // Total task earnings
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(t.reward) as total FROM completed_tasks ct JOIN tasks t ON ct.task_id = t.id WHERE DATE(ct.completed_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $tasks = $stmt->fetch();
    $stats['tasks_completed'] = $tasks['count'] ?? 0;
    $stats['tasks_earnings'] = $tasks['total'] ?? 0;
    
    // Net profit/loss
    $stats['net_profit'] = $stats['deposits_total'] - $stats['withdrawals_total'];
    
} catch(PDOException $e) {
    $error = "Failed to fetch financial statistics: " . $e->getMessage();
}

// Get daily transaction data for charts
$daily_data = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            DATE(created_at) as date,
            SUM(CASE WHEN status='Approved' THEN amount ELSE 0 END) as deposits,
            SUM(CASE WHEN status='Approved' THEN amount ELSE 0 END) as withdrawals
        FROM (
            SELECT created_at, status, amount FROM deposits
            UNION ALL
            SELECT created_at, status, amount FROM withdrawals
        ) as transactions
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([$date_from, $date_to]);
    $daily_data = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch daily data: " . $e->getMessage();
}

// Get top users
$top_users = [];
try {
    $stmt = $conn->prepare("
        SELECT u.fullname, u.email, u.balance, 
               COUNT(ct.id) as tasks_completed,
               SUM(t.reward) as total_earned
        FROM users u
        LEFT JOIN completed_tasks ct ON u.id = ct.user_id
        LEFT JOIN tasks t ON ct.task_id = t.id
        GROUP BY u.id
        ORDER BY u.balance DESC
        LIMIT 10
    ");
    $stmt->execute();
    $top_users = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch top users: " . $e->getMessage();
}

// Get recent transactions
$recent_transactions = [];
try {
    $stmt = $conn->prepare("
        SELECT 'deposit' as type, d.amount, d.status, d.created_at, u.email as user_email
        FROM deposits d
        JOIN users u ON d.user_id = u.id
        UNION ALL
        SELECT 'withdrawal' as type, w.amount, w.status, w.created_at, u.email as user_email
        FROM withdrawals w
        JOIN users u ON w.user_id = u.id
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $recent_transactions = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch recent transactions: " . $e->getMessage();
}

// Get monthly trends
$monthly_trends = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(CASE WHEN status='Approved' THEN amount ELSE 0 END) as deposits,
            COUNT(CASE WHEN status='Approved' THEN 1 END) as deposit_count
        FROM deposits
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute();
    $monthly_trends = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch monthly trends: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Analysis - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        
        .badge-info {
            background: #17a2b8;
            color: white;
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
        
        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }
        
        .positive {
            color: #28a745;
        }
        
        .negative {
            color: #dc3545;
        }
        
        .neutral {
            color: #6c757d;
        }
        
        .date-filter {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            height: 8px;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav-menu">
                <h1><i class="fas fa-chart-line"></i> Finance Analysis</h1>
                <div class="nav-links">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
                    <a href="combos.php"><i class="fas fa-layer-group"></i> Combos</a>
                    <a href="invitation-codes.php"><i class="fas fa-ticket-alt"></i> Codes</a>
                    <a href="finance-analysis.php"><i class="fas fa-chart-line"></i> Finance</a>
                    <a href="deposits.php"><i class="fas fa-dollar-sign"></i> Deposits</a>
                    <a href="withdrawals.php"><i class="fas fa-money-bill-wave"></i> Withdrawals</a>
                    <a href="../admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
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

        <!-- Date Filter -->
        <div class="date-filter">
            <form method="GET" class="form-row">
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

        <!-- Financial Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['deposits_total'], 2); ?></div>
                <div class="stat-label">Total Deposits</div>
                <small><?php echo $stats['deposits_count']; ?> transactions</small>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['withdrawals_total'], 2); ?></div>
                <div class="stat-label">Total Withdrawals</div>
                <small><?php echo $stats['withdrawals_count']; ?> transactions</small>
            </div>
            <div class="stat-card">
                <div class="stat-number <?php echo $stats['net_profit'] >= 0 ? 'positive' : 'negative'; ?>">
                    $<?php echo number_format($stats['net_profit'], 2); ?>
                </div>
                <div class="stat-label">Net Profit/Loss</div>
                <small>Deposits - Withdrawals</small>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['tasks_earnings'], 2); ?></div>
                <div class="stat-label">Task Earnings</div>
                <small><?php echo $stats['tasks_completed']; ?> tasks completed</small>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
                <small><?php echo $stats['active_users']; ?> active users</small>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_deposits'] + $stats['pending_withdrawals']; ?></div>
                <div class="stat-label">Pending Transactions</div>
                <small><?php echo $stats['pending_deposits']; ?> deposits, <?php echo $stats['pending_withdrawals']; ?> withdrawals</small>
            </div>
        </div>

        <!-- Charts -->
        <div class="card">
            <div class="card-header">
                <h2>Transaction Trends</h2>
            </div>
            <div class="chart-container">
                <canvas id="transactionChart"></canvas>
            </div>
        </div>

        <!-- Top Users -->
        <div class="card">
            <div class="card-header">
                <h2>Top Users by Balance</h2>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Balance</th>
                        <th>Tasks Completed</th>
                        <th>Total Earned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_users as $index => $user): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>$<?php echo number_format($user['balance'], 2); ?></td>
                            <td><?php echo $user['tasks_completed']; ?></td>
                            <td>$<?php echo number_format($user['total_earned'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($top_users)): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No users found.
                </p>
            <?php endif; ?>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Transactions</h2>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>User</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_transactions as $transaction): ?>
                        <tr>
                            <td>
                                <span class="badge badge-<?php echo $transaction['type'] == 'deposit' ? 'success' : 'info'; ?>">
                                    <?php echo ucfirst($transaction['type']); ?>
                                </span>
                            </td>
                            <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo match($transaction['status']) {
                                        'Approved' => 'success',
                                        'Pending' => 'warning',
                                        'Rejected' => 'danger',
                                        default => 'secondary'
                                    }; 
                                ?>">
                                    <?php echo $transaction['status']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($transaction['user_email']); ?></td>
                            <td><?php echo date('M j, Y H:i', strtotime($transaction['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($recent_transactions)): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No transactions found.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Prepare chart data
        const dailyData = <?php echo json_encode($daily_data); ?>;
        const labels = dailyData.map(item => item.date);
        const depositData = dailyData.map(item => parseFloat(item.deposits) || 0);
        const withdrawalData = dailyData.map(item => parseFloat(item.withdrawals) || 0);

        // Create chart
        const ctx = document.getElementById('transactionChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Deposits',
                    data: depositData,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Withdrawals',
                    data: withdrawalData,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += '$' + context.parsed.y.toFixed(2);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(0);
                            }
                        }
                    }
                }
            }
        });

        // Auto-refresh every 30 seconds
        setInterval(() => {
            // Uncomment to enable auto-refresh
            // window.location.reload();
        }, 30000);
    </script>
</body>
</html>
