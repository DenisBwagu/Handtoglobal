<?php
session_start();
require 'config.php';
require 'get_setting.php';

// Get Telegram link from settings
$supportLink = get_setting('telegram_link', '<?php echo htmlspecialchars($supportLink); ?>');

requireLogin();

$user = getUserById($_SESSION['user_id']);
$stats = getUserStats($_SESSION['user_id']);

// Handle task completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'])) {
    $taskId = (int)$_POST['task_id'];
    
    // Check if task already completed
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM completed_tasks WHERE user_id = ? AND task_id = ?");
    $stmt->execute([$_SESSION['user_id'], $taskId]);
    $alreadyCompleted = $stmt->fetch()['count'] > 0;
    
    if (!$alreadyCompleted) {
        // Get task details
        $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        
        if ($task && canAccessLevel($_SESSION['user_id'], $task['level'])) {
            // Check daily task limit
            $todayCount = getTodayTaskCount($_SESSION['user_id']);
            if ($todayCount < DAILY_TASK_LIMIT) {
                // Complete the task
                $stmt = $conn->prepare("INSERT INTO completed_tasks (user_id, task_id, level, reward) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $taskId, $task['level'], $task['reward']]);
                
                // Update user balance and stats
                $stmt = $conn->prepare("UPDATE users SET balance = balance + ?, total_tasks = total_tasks + 1 WHERE id = ?");
                $stmt->execute([$task['reward'], $_SESSION['user_id']]);
                
                // Create notification
                createNotification($_SESSION['user_id'], 'Task Completed', "You earned {$task['reward']} USDT for completing: {$task['title']}");
                
                // Refresh stats
                $stats = getUserStats($_SESSION['user_id']);
                $user = getUserById($_SESSION['user_id']);
            }
        }
    }
    
    // Redirect to prevent form resubmission
    redirect('starting.php' . (isset($_GET['level']) ? '?level=' . $_GET['level'] : ''));
}

// Get selected level
$selectedLevel = $_GET['level'] ?? 'Bronze';
$levels = ['Bronze', 'Silver', 'Gold', 'Platinum'];
if (!in_array($selectedLevel, $levels)) {
    $selectedLevel = 'Bronze';
}

// Check if user can access this level
if (!canAccessLevel($_SESSION['user_id'], $selectedLevel)) {
    // Find the highest unlocked level
    foreach (array_reverse($levels) as $level) {
        if (canAccessLevel($_SESSION['user_id'], $level)) {
            $selectedLevel = $level;
            break;
        }
    }
}

// Get next uncompleted task for this level
$currentTask = getNextUncompletedTask($_SESSION['user_id'], $selectedLevel);

// Check if level is completed
$levelProgress = getLevelProgress($_SESSION['user_id'], $selectedLevel);
$levelCompleted = $levelProgress >= 40;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks - GlobalHand</title>
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
<nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="starting.php" class="nav-item active">
                    <i class="fas fa-tasks"></i>
                    <span>Tasks</span>
                </a>
                <a href="records.php" class="nav-item">
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

            <!-- Tasks Content -->
            <main class="tasks-content">
                <div class="page-header">
                    <h1>Tasks</h1>
                    <p>Complete tasks to earn USDT rewards</p>
                </div>

                <!-- Level Selection -->
                <div class="level-selection">
                    <?php foreach ($levels as $level): ?>
                        <?php 
                        $isUnlocked = canAccessLevel($_SESSION['user_id'], $level);
                        $progress = getLevelProgress($_SESSION['user_id'], $level);
                        $isSelected = $level === $selectedLevel;
                        ?>
                        <a href="?level=<?php echo $level; ?>" class="level-card <?php echo $isUnlocked ? 'unlocked' : 'locked'; ?> <?php echo $isSelected ? 'selected' : ''; ?>">
                            <div class="level-icon">
                                <?php if (!$isUnlocked): ?>
                                    <i class="fas fa-lock"></i>
                                <?php else: ?>
                                    <i class="fas fa-<?php echo strtolower($level); ?>"></i>
                                <?php endif; ?>
                            </div>
                            <div class="level-info">
                                <h3><?php echo $level; ?></h3>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo ($progress / 40) * 100; ?>%"></div>
                                </div>
                                <p><?php echo $progress; ?>/40 tasks</p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Task Display -->
                <div class="task-display">
                    <?php if ($levelCompleted): ?>
                        <div class="level-completed">
                            <div class="completed-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h2>Level Completed!</h2>
                            <p>Congratulations! You've completed all <?php echo $selectedLevel; ?> level tasks.</p>
                            
                            <?php 
                            $nextLevelIndex = array_search($selectedLevel, $levels) + 1;
                            $nextLevel = $levels[$nextLevelIndex] ?? null;
                            $canAccessNext = $nextLevel && canAccessLevel($_SESSION['user_id'], $nextLevel);
                            ?>
                            
                            <?php if ($nextLevel && !$canAccessNext): ?>
                                <div class="unlock-requirement">
                                    <h3>Unlock <?php echo $nextLevel; ?> Level</h3>
                                    <p>Deposit required to unlock the next level</p>
                                    <div class="requirement-info">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Minimum deposit: <?php echo constant($nextLevel . '_UNLOCK_AMOUNT'); ?> USDT</span>
                                    </div>
                                    <div class="unlock-actions">
                                        <a href="deposit.php" class="btn btn-primary">Deposit Now</a>
                                        <a href="<?php echo htmlspecialchars($supportLink); ?>" target="_blank" class="btn btn-secondary">Contact Support</a>
                                    </div>
                                </div>
                            <?php elseif ($nextLevel && $canAccessNext): ?>
                                <div class="next-level-available">
                                    <h3><?php echo $nextLevel; ?> Level Available!</h3>
                                    <a href="?level=<?php echo $nextLevel; ?>" class="btn btn-primary">Continue to <?php echo $nextLevel; ?></a>
                                </div>
                            <?php else: ?>
                                <p>You've reached the highest level!</p>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($currentTask): ?>
                        <div class="task-card">
                            <div class="task-image">
                                <img src="assets/images/<?php echo htmlspecialchars($currentTask['image']); ?>" alt="Task Image">
                            </div>
                            <div class="task-content">
                                <div class="task-header">
                                    <h2><?php echo htmlspecialchars($currentTask['title']); ?></h2>
                                    <span class="task-level"><?php echo htmlspecialchars($currentTask['level']); ?></span>
                                </div>
                                <div class="task-description">
                                    <p><?php echo htmlspecialchars($currentTask['description']); ?></p>
                                </div>
                                <div class="task-quality">
                                    <div class="quality-score">
                                        <i class="fas fa-star"></i>
                                        <span>Quality Score: Excellent</span>
                                    </div>
                                    <p>This task has been verified for quality and accuracy.</p>
                                </div>
                                <form method="POST" class="task-form">
                                    <input type="hidden" name="task_id" value="<?php echo $currentTask['id']; ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-check"></i>
                                        Complete Task
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-tasks">
                            <i class="fas fa-tasks"></i>
                            <h2>No Tasks Available</h2>
                            <p>All tasks for this level have been completed or you've reached your daily limit.</p>
                            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Daily Progress -->
                <div class="daily-progress">
                    <h3>Daily Progress</h3>
                    <div class="progress-info">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($stats['today_tasks'] / DAILY_TASK_LIMIT) * 100; ?>%"></div>
                        </div>
                        <span><?php echo $stats['today_tasks']; ?>/<?php echo DAILY_TASK_LIMIT; ?> tasks completed today</span>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
