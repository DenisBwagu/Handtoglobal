<?php
require_once '../config.php';
require_once '../get_setting.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();

// Get dashboard statistics - optimized queries
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
$activeUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_blocked = 0")->fetch()['count'];
$totalEmployees = $conn->query("SELECT COUNT(*) as count FROM employees")->fetch()['count'];

// Tasks Completed - use COUNT(*) for performance
$tasksCompleted = $conn->query("SELECT COUNT(*) as count FROM completed_tasks")->fetch()['count'];

// Pending Withdrawals - count only, don't fetch all records
$pendingWithdrawals = $conn->query("SELECT COUNT(*) as count FROM withdrawals WHERE status = 'Pending'")->fetch()['count'];

// Total Paid Out - use SUM(amount) for performance
$totalPaidOut = $conn->query("SELECT SUM(amount) as total FROM withdrawals WHERE status = 'Approved'")->fetch()['total'] ?? 0;

// Recent Users - add LIMIT 5 for performance
$recentUsers = $conn->query("SELECT fullname, email, level, balance, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - HandToGlobal Admin</title>
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
            <div class="page-header">
                <h1>Dashboard</h1>
                <p>Admin dashboard overview</p>
            </div>
            
            <!-- Stats Cards Grid -->
            <div class="card">
                <div class="card-body" style="padding: 25px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                        <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 20px; display: flex; align-items: center; gap: 16px;">
                            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(79, 70, 229, 0.1); display: flex; align-items: center; justify-content: center; color: #4f46e5; font-size: 20px;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6c757d; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Total Users</div>
                                <div style="font-size: 24px; font-weight: 700; color: #212529;"><?php echo $totalUsers; ?></div>
                            </div>
                        </div>
                        
                        <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 20px; display: flex; align-items: center; gap: 16px;">
                            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(34, 197, 94, 0.1); display: flex; align-items: center; justify-content: center; color: #22c55e; font-size: 20px;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6c757d; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Active Users</div>
                                <div style="font-size: 24px; font-weight: 700; color: #212529;"><?php echo $activeUsers; ?></div>
                            </div>
                        </div>
                        
                        <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 20px; display: flex; align-items: center; gap: 16px;">
                            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(245, 158, 11, 0.1); display: flex; align-items: center; justify-content: center; color: #f59e0b; font-size: 20px;">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6c757d; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Employees</div>
                                <div style="font-size: 24px; font-weight: 700; color: #212529;"><?php echo $totalEmployees; ?></div>
                            </div>
                        </div>
                        
                        <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 20px; display: flex; align-items: center; gap: 16px;">
                            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(124, 58, 237, 0.1); display: flex; align-items: center; justify-content: center; color: #7c3aed; font-size: 20px;">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6c757d; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Tasks Completed</div>
                                <div style="font-size: 24px; font-weight: 700; color: #212529;"><?php echo $tasksCompleted; ?></div>
                            </div>
                        </div>
                        
                        <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 20px; display: flex; align-items: center; gap: 16px;">
                            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(251, 146, 60, 0.1); display: flex; align-items: center; justify-content: center; color: #fb923c; font-size: 20px;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6c757d; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Pending Withdrawals</div>
                                <div style="font-size: 24px; font-weight: 700; color: #212529;"><?php echo $pendingWithdrawals; ?></div>
                            </div>
                        </div>
                        
                        <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 20px; display: flex; align-items: center; gap: 16px;">
                            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(34, 197, 94, 0.1); display: flex; align-items: center; justify-content: center; color: #22c55e; font-size: 20px;">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6c757d; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Total Paid Out</div>
                                <div style="font-size: 24px; font-weight: 700; color: #212529;">$<?php echo number_format($totalPaidOut, 2); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 15px; margin-bottom: 30px;">
                        <a href="employees.php?action=create" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Employee
                        </a>
                        <a href="invitation-codes.php" class="btn btn-secondary">
                            <i class="fas fa-ticket-alt"></i> Generate Codes
                        </a>
                        <a href="withdrawals.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-up"></i> View Withdrawals
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Recent Users Table -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Users</h2>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Level</th>
                                <th>Balance</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge"><?php echo htmlspecialchars($user['level']); ?></span>
                                    </td>
                                    <td>$<?php echo number_format($user['balance'], 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($recentUsers)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px; color: #6c757d;">
                                        No users found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
