<?php
require_once 'config.php';

requireLogin();

$user = getUserById($_SESSION['user_id']);
$stats = getUserStats($_SESSION['user_id']);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get completed tasks with pagination
$conn = getConnection();
$stmt = $conn->prepare("SELECT ct.*, t.title, t.level 
                       FROM completed_tasks ct 
                       JOIN tasks t ON ct.task_id = t.id 
                       WHERE ct.user_id = ? 
                       ORDER BY ct.completed_at DESC 
                       LIMIT ? OFFSET ?");
$stmt->execute([$_SESSION['user_id'], $limit, $offset]);
$completedTasks = $stmt->fetchAll();

// Get total count for pagination
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM completed_tasks WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalTasks = $stmt->fetch()['total'];
$totalPages = ceil($totalTasks / $limit);

// Filter by level if specified
$filterLevel = $_GET['level'] ?? '';
if ($filterLevel && in_array($filterLevel, ['Bronze', 'Silver', 'Gold', 'Platinum'])) {
    $stmt = $conn->prepare("SELECT ct.*, t.title, t.level 
                           FROM completed_tasks ct 
                           JOIN tasks t ON ct.task_id = t.id 
                           WHERE ct.user_id = ? AND ct.level = ? 
                           ORDER BY ct.completed_at DESC 
                           LIMIT ? OFFSET ?");
    $stmt->execute([$_SESSION['user_id'], $filterLevel, $limit, $offset]);
    $completedTasks = $stmt->fetchAll();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM completed_tasks WHERE user_id = ? AND level = ?");
    $stmt->execute([$_SESSION['user_id'], $filterLevel]);
    $totalTasks = $stmt->fetch()['total'];
    $totalPages = ceil($totalTasks / $limit);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Records - GlobalHand</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        <?php include 'includes/theme.php'; ?>
    </style>
</head>
<body>
    <div class="layout-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="logo">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span class="logo-text">GlobalHand</span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="starting.php" class="nav-item">
                    <i class="fas fa-tasks"></i>
                    <span>Tasks</span>
                </a>
                <a href="records.php" class="nav-item active">
                    <i class="fas fa-history"></i>
                    <span>Records</span>
                </a>
                <a href="wallet.php" class="nav-item">
                    <i class="fas fa-wallet"></i>
                    <span>Wallet</span>
                </a>
                <a href="deposit.php" class="nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <span>Deposit</span>
                </a>
                <a href="withdraw.php" class="nav-item">
                    <i class="fas fa-minus-circle"></i>
                    <span>Withdraw</span>
                </a>
                <a href="notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search...">
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="notification-dropdown">
                        <button class="notification-btn">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge"><?php echo count(getUnreadNotifications($_SESSION['user_id'])); ?></span>
                        </button>
                    </div>
                    <div class="profile-dropdown">
                        <button class="profile-btn">
                            <div class="avatar">
                                <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                            </div>
                            <div class="profile-info">
                                <span class="profile-name"><?php echo htmlspecialchars($user['fullname']); ?></span>
                                <span class="profile-level"><?php echo htmlspecialchars($user['level']); ?></span>
                            </div>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Records Content -->
            <main class="records-content">
                <div class="page-header">
                    <h1>Task Records</h1>
                    <p>Your complete task completion history</p>
                </div>

                <!-- Stats Summary -->
                <div class="stats-summary">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_tasks']; ?></h3>
                            <p>Total Tasks</p>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatBalance($stats['total_earned']); ?></h3>
                            <p>Total Earned</p>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['today_tasks']; ?></h3>
                            <p>Today's Tasks</p>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo htmlspecialchars($user['level']); ?></h3>
                            <p>Current Level</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-section">
                    <h3>Filter by Level</h3>
                    <div class="filter-buttons">
                        <a href="records.php" class="filter-btn <?php echo !$filterLevel ? 'active' : ''; ?>">
                            All Levels
                        </a>
                        <a href="records.php?level=Bronze" class="filter-btn <?php echo $filterLevel === 'Bronze' ? 'active' : ''; ?>">
                            Bronze
                        </a>
                        <a href="records.php?level=Silver" class="filter-btn <?php echo $filterLevel === 'Silver' ? 'active' : ''; ?>">
                            Silver
                        </a>
                        <a href="records.php?level=Gold" class="filter-btn <?php echo $filterLevel === 'Gold' ? 'active' : ''; ?>">
                            Gold
                        </a>
                        <a href="records.php?level=Platinum" class="filter-btn <?php echo $filterLevel === 'Platinum' ? 'active' : ''; ?>">
                            Platinum
                        </a>
                    </div>
                </div>

                <!-- Records Table -->
                <div class="records-table">
                    <div class="table-header">
                        <h3>Task History</h3>
                        <div class="table-info">
                            <span>Showing <?php echo min($limit, $totalTasks - $offset); ?> of <?php echo $totalTasks; ?> tasks</span>
                        </div>
                    </div>
                    
                    <?php if (empty($completedTasks)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No tasks completed yet</h3>
                            <p>Start completing tasks to see your history here</p>
                            <a href="starting.php" class="btn btn-primary">Start Tasks</a>
                        </div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Level</th>
                                        <th>Task Title</th>
                                        <th>Reward</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completedTasks as $task): ?>
                                        <tr>
                                            <td>
                                                <div class="date-cell">
                                                    <i class="fas fa-calendar"></i>
                                                    <span><?php echo date('M j, Y', strtotime($task['completed_at'])); ?></span>
                                                    <small><?php echo date('g:i A', strtotime($task['completed_at'])); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="level-badge level-<?php echo strtolower($task['level']); ?>">
                                                    <?php echo htmlspecialchars($task['level']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="task-title">
                                                    <?php echo htmlspecialchars($task['title']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="reward positive">
                                                    +<?php echo formatBalance($task['reward']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge completed">
                                                    <i class="fas fa-check"></i>
                                                    Completed
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?><?php echo $filterLevel ? '&level=' . $filterLevel : ''; ?>" class="page-btn">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <a href="?page=<?php echo $i; ?><?php echo $filterLevel ? '&level=' . $filterLevel : ''; ?>" 
                                       class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo $filterLevel ? '&level=' . $filterLevel : ''; ?>" class="page-btn">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
