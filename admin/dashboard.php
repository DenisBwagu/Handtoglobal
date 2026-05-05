<?php
require_once '../config.php';
require_once '../includes/settings_helpers.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();

// Get dashboard statistics - enhanced with user-side sync
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
$activeUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_blocked = 0")->fetch()['count'];
$totalEmployees = $conn->query("SELECT COUNT(*) as count FROM employees")->fetch()['count'];

// Tasks Completed - detailed breakdown
$tasksCompleted = $conn->query("SELECT COUNT(*) as count FROM completed_tasks")->fetch()['count'];
$todayTasksCompleted = $conn->query("SELECT COUNT(*) as count FROM completed_tasks WHERE DATE(completed_at) = CURDATE()")->fetch()['count'];

// Level-specific stats (matching user side calculations)
$levels = ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond'];
$levelStats = [];
foreach ($levels as $level) {
    $stmt = $conn->prepare("SELECT COUNT(*) as completed FROM completed_tasks ct JOIN tasks t ON ct.task_id = t.id WHERE t.level = ?");
    $stmt->execute([$level]);
    $completed = $stmt->fetch()['completed'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tasks WHERE level = ? AND active = 1");
    $stmt->execute([$level]);
    $total = $stmt->fetch()['total'];
    
    $levelStats[$level] = [
        'completed' => $completed,
        'total' => $total,
        'available' => $total - $completed,
        'progress' => $total > 0 ? round(($completed / $total) * 100, 1) : 0
    ];
}

// Active combos
$activeCombos = $conn->query("SELECT COUNT(*) as count FROM combos WHERE status = 'active' AND is_active = 1")->fetch()['count'];

// Pending Withdrawals - count only, don't fetch all records
$pendingWithdrawals = $conn->query("SELECT COUNT(*) as count FROM withdrawals WHERE status = 'Pending'")->fetch()['count'];

// Total Paid Out - use SUM(amount) for performance
$totalPaidOut = $conn->query("SELECT SUM(amount) as total FROM withdrawals WHERE status = 'Approved'")->fetch()['total'] ?? 0;

// Recent Users - add LIMIT 5 for performance
$recentUsers = $conn->query("SELECT fullname, email, level, balance, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Recent Activity Feed
$recentActivity = $conn->query("
    SELECT 
        ct.completed_at,
        u.fullname,
        u.email,
        t.title,
        t.level,
        ct.reward
    FROM completed_tasks ct
    JOIN users u ON ct.user_id = u.id
    JOIN tasks t ON ct.task_id = t.id
    ORDER BY ct.completed_at DESC
    LIMIT 10
")->fetchAll();

// Top performers today
$topPerformersToday = $conn->query("
    SELECT 
        u.fullname,
        u.email,
        COUNT(ct.id) as tasks_completed,
        SUM(ct.reward) as total_earned
    FROM completed_tasks ct
    JOIN users u ON ct.user_id = u.id
    WHERE DATE(ct.completed_at) = CURDATE()
    GROUP BY u.id, u.fullname, u.email
    ORDER BY tasks_completed DESC, total_earned DESC
    LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(get_meta_title()); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(get_meta_description()); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars(get_meta_keywords()); ?>">
    <meta name="robots" content="<?php echo htmlspecialchars(get_meta_robots()); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars(get_meta_title()); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(get_meta_description()); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars(get_og_image()); ?>">
    <link rel="icon" href="<?php echo htmlspecialchars(get_favicon()); ?>" type="image/x-icon">
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
                                <div style="font-size: 24px; font-weight: 700; color: #212529;" data-stat="completed_tasks"><?php echo $tasksCompleted; ?></div>
                                <div style="font-size: 11px; color: #6c757d; margin-top: 2px;">Today: <?php echo $todayTasksCompleted; ?></div>
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
                        
                        <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 20px; display: flex; align-items: center; gap: 16px;">
                            <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); display: flex; align-items: center; justify-content: center; color: #ef4444; font-size: 20px;">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6c757d; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Active Combos</div>
                                <div style="font-size: 24px; font-weight: 700; color: #212529;" data-stat="active_combos"><?php echo $activeCombos; ?></div>
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
            
            <!-- Live Activity Section -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <!-- Level Progress -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Level Progress (Live)</h2>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; gap: 15px;">
                            <?php foreach ($levelStats as $level => $stats): ?>
                                <div style="background: #f8f9fa; border-radius: 8px; padding: 15px;" data-level="<?php echo htmlspecialchars($level); ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                        <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($level); ?></div>
                                        <div style="font-size: 14px; color: #666;" class="level-progress"><?php echo $stats['completed']; ?>/<?php echo $stats['total']; ?></div>
                                    </div>
                                    <div style="background: #e9ecef; border-radius: 4px; height: 8px; overflow: hidden;">
                                        <div class="progress-fill" style="background: #4f46e5; height: 100%; width: <?php echo $stats['progress']; ?>%; transition: width 0.3s ease;"></div>
                                    </div>
                                    <div style="font-size: 12px; color: #666; margin-top: 4px;" class="available-tasks">Available: <?php echo $stats['available']; ?> tasks</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity Feed -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Activity Feed</h2>
                    </div>
                    <div class="card-body">
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($recentActivity)): ?>
                                <div style="text-align: center; color: #666; padding: 20px;">No recent activity</div>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div style="border-bottom: 1px solid #e9ecef; padding: 12px 0;">
                                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 4px;">
                                            <div>
                                                <div style="font-weight: 600; color: #333; font-size: 14px;"><?php echo htmlspecialchars($activity['fullname']); ?></div>
                                                <div style="color: #666; font-size: 12px;"><?php echo htmlspecialchars($activity['email']); ?></div>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                                    <?php echo htmlspecialchars($activity['level']); ?>
                                                </div>
                                                <div style="color: #666; font-size: 11px; margin-top: 2px;">
                                                    <?php echo date('H:i', strtotime($activity['completed_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="color: #333; font-size: 13px; margin-bottom: 4px;"><?php echo htmlspecialchars($activity['title']); ?></div>
                                        <div style="color: #22c55e; font-weight: 600; font-size: 12px;">+$<?php echo number_format($activity['reward'], 2); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Performers Today -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h2 class="card-title">Top Performers Today</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($topPerformersToday)): ?>
                        <div style="text-align: center; color: #666; padding: 20px;">No activity today</div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <?php foreach ($topPerformersToday as $performer): ?>
                                <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; text-align: center;">
                                    <div style="font-weight: 600; color: #333; margin-bottom: 4px;"><?php echo htmlspecialchars($performer['fullname']); ?></div>
                                    <div style="color: #666; font-size: 12px; margin-bottom: 8px;"><?php echo htmlspecialchars($performer['email']); ?></div>
                                    <div style="display: flex; justify-content: space-around; align-items: center;">
                                        <div>
                                            <div style="font-size: 24px; font-weight: 700; color: #4f46e5;"><?php echo $performer['tasks_completed']; ?></div>
                                            <div style="font-size: 11px; color: #666;">Tasks</div>
                                        </div>
                                        <div>
                                            <div style="font-size: 20px; font-weight: 700; color: #22c55e;">$<?php echo number_format($performer['total_earned'], 2); ?></div>
                                            <div style="font-size: 11px; color: #666;">Earned</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
