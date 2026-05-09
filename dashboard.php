<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/settings_helpers.php';
require_once __DIR__ . '/includes/language_helpers.php';

// Get Telegram link from settings
$supportLink = get_telegram_link();

requireLogin();

// Get user data
$conn = getConnection();
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Always read fresh data from database - no session caching

// Get user statistics
$stats = [];
try {
    // Determine current level based on completion
    $levelRecords = getAppLevels();
    $levels = array_column($levelRecords, 'name');
    
    // Get user's stored level or calculate it
    $current_level = $user['level'] ?? 'Bronze';
    
    // Calculate stats for each level using new functions
    $stats['levels'] = [];
    foreach ($levels as $level) {
        $levelProgress = getLevelProgressForUser($_SESSION['user_id'], $level);
        $stats['levels'][$level] = $levelProgress;
    }
    
    // Always calculate current level based on actual progress
    $current_level = null;
    foreach ($levels as $level) {
        $levelProgress = $stats['levels'][$level];
        if ($levelProgress['completed'] < $levelProgress['total']) {
            $current_level = $level;
            break;
        }
    }
    
    // If all levels are completed, use the highest level
    if (!$current_level && !empty($levels)) {
        $current_level = end($levels);
    }
    
    $stats['current_level'] = $current_level;
    $stats['available_tasks'] = isset($stats['levels'][$current_level]) ? $stats['levels'][$current_level]['available'] : 0;
    $stats['completed_tasks'] = isset($stats['levels'][$current_level]) ? $stats['levels'][$current_level]['completed'] : 0;
    
    // Total completed tasks across all levels (for other uses)
    $stats['total_completed_all'] = 0;
    foreach ($stats['levels'] as $level_data) {
        $stats['total_completed_all'] += $level_data['completed'];
    }
    
    // Pending withdrawals
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM withdrawals WHERE user_id = ? AND status = 'Pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['pending_withdrawals'] = $stmt->fetch()['count'];
    
    // Performance score (default 100.00)
    $stats['performance_score'] = $user['rating'] ?? 100.00;
    
    // Today's progress
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM completed_tasks WHERE user_id = ? AND DATE(completed_at) = ?");
    $stmt->execute([$_SESSION['user_id'], $today]);
    $stats['today_completed'] = $stmt->fetch()['count'];
    
    // Tasks per level (default 40)
    $stats['tasks_per_level'] = (int)get_setting('tasks_per_level', '40');

    // Daily task limit from settings, overridden by saved per-user limits when present.
    $stats['daily_task_limit'] = (int)get_setting('daily_task_limit', '40');
    $userLimits = getUserLimitsForUser($_SESSION['user_id'], $conn);
    if (!empty($userLimits['_exists']) && !empty($userLimits['max_levels_per_day'])) {
        $stats['daily_task_limit'] = max(1, (int)$userLimits['max_levels_per_day'] * max(1, $stats['tasks_per_level']));
    }
    
    // Get support link from global function
    $support_link = getSupportLink();
    
} catch(PDOException $e) {
    $stats = [
        'available_tasks' => 0,
        'completed_tasks' => 0,
        'pending_withdrawals' => 0,
        'performance_score' => 100.00,
        'today_completed' => 0,
        'max_levels_per_day' => 3,
        'tasks_per_level' => 40
    ];
}

// Get testimonials
$testimonials = [];
try {
    $stmt = $conn->prepare("SELECT * FROM testimonials WHERE is_active = 1 ORDER BY display_order ASC, created_at DESC LIMIT 3");
    $stmt->execute();
    $testimonials = $stmt->fetchAll();
} catch(PDOException $e) {
    $testimonials = [];
}

// Get active combo for user
$active_combo = [];
try {
    $stmt = $conn->prepare("SELECT * FROM combos WHERE user_id = ? AND status = 'Active'");
    $stmt->execute([$_SESSION['user_id']]);
    $active_combo = $stmt->fetch();
} catch(PDOException $e) {
    $active_combo = null;
}

// Get tasks for current level
$tasks = [];
try {
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE level = ? AND active = 1 ORDER BY id LIMIT 40");
    $stmt->execute([$user['level']]);
    $tasks = $stmt->fetchAll();
} catch(PDOException $e) {
    $tasks = [];
}

// Get completed task IDs for this user
$completed_task_ids = [];
try {
    $stmt = $conn->prepare("SELECT task_id FROM completed_tasks WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $results = $stmt->fetchAll();
    $completed_task_ids = array_column($results, 'task_id');
} catch(PDOException $e) {
    $completed_task_ids = [];
}

// Get available levels with unlock status from database
$levelRecords = getAppLevels();
$levels = array_column($levelRecords, 'name');
$unlocked_levels = [];
foreach ($levels as $level) {
    // Bronze is always unlocked, check others normally
    if ($level === 'Bronze') {
        $unlocked_levels[$level] = true;
        htg_debug_log("DEBUG: Dashboard - Bronze level: ALWAYS UNLOCKED");
    } else {
        $unlocked_levels[$level] = isLevelUnlockedForUser($_SESSION['user_id'], $level);
        htg_debug_log("DEBUG: Dashboard - Level $level unlock status: " . ($unlocked_levels[$level] ? 'UNLOCKED' : 'LOCKED'));
    }
}

// DEBUG: Log user balance from database
htg_debug_log("DEBUG: Dashboard - User balance from database: " . $user['balance']);
htg_debug_log("DEBUG: Dashboard - User level from database: " . ($user['level'] ?? 'null'));
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
    <?php
$favicon = get_setting('site_favicon', 'assets/images/favicon.ico');
?>
<link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>?v=<?php echo time(); ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global-theme.css">
    <script src="assets/js/theme.js" defer></script>
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
        
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
            padding-top: 62px;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: white;
            border-right: 1px solid #e5e7eb;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
.sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-section {
            margin-bottom: 30px;
        }
        
        .sidebar-section-title {
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 20px;
            margin-bottom: 10px;
        }
        
        .sidebar-menu-item {
            display: block;
            padding: 12px 20px;
            color: #374151;
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu-item:hover {
            background: #f8f9fa;
            color: #667eea;
        }
        
        .sidebar-menu-item.active {
            background: #f0f4ff;
            color: #667eea;
            border-left-color: #667eea;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        /* Top Bar */
        .top-bar {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 0 20px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .user-balance {
            background: #f0f4ff;
            color: #667eea;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        /* Content Area */
        .content-area {
            flex: 1;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            border: 1px solid #e5e7eb;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 16px;
        }
        
        /* Welcome Card */
        .welcome-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .welcome-info h2 {
            font-size: 24px;
            color: #333;
            margin-bottom: 4px;
        }
        
        .welcome-info p {
            color: #6b7280;
            font-size: 14px;
        }
        
        .welcome-balance {
            font-size: 24px;
            font-weight: 700;
            color: #10b981;
        }
        
        /* Current Level Card */
        .current-level-card {
            border: 2px solid #667eea;
            position: relative;
        }
        
        .level-badge {
            position: absolute;
            top: -10px;
            left: 20px;
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .level-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        
        .level-name {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        
        .level-progress {
            font-size: 14px;
            color: #6b7280;
        }
        
        .progress-bar {
            background: #e5e7eb;
            height: 8px;
            border-radius: 4px;
            margin: 16px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            background: #667eea;
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .level-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        
        /* Today's Progress */
        .today-progress {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .today-label {
            font-size: 14px;
            color: #333;
        }
        
        .today-count {
            font-size: 14px;
            font-weight: 600;
            color: #667eea;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            border: 1px solid #e5e7eb;
            padding: 20px;
            text-align: center;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 20px;
            color: white;
        }
        
        .stat-icon.available { background: #10b981; }
        .stat-icon.completed { background: #667eea; }
        .stat-icon.pending { background: #f59e0b; }
        .stat-icon.performance { background: #8b5cf6; }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }
        
        .btn-support {
            background: #f59e0b;
            color: white;
        }
        
        .btn-support:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        
        /* Levels Grid */
        .levels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .level-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            border: 2px solid #e5e7eb;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .level-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.1);
        }
        
        .level-card.current {
            border-color: #667eea;
        }
        
        .level-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .level-card-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .level-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .level-status.current {
            background: #f0f4ff;
            color: #667eea;
        }
        
        .level-status.progress {
            background: #f0fdf4;
            color: #10b981;
        }
        
        .level-status.locked {
            background: #fef3c7;
            color: #d97706;
        }
        
        .level-category {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .level-progress-text {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        /* Testimonials */
        .testimonial-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        
        .testimonial-quote {
            font-size: 14px;
            color: #374151;
            margin-bottom: 12px;
            font-style: italic;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .testimonial-name {
            font-weight: 600;
            color: #333;
        }
        
        .testimonial-badge {
            background: #e5e7eb;
            color: #6b7280;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
        }
        
                
        /* Support Button */
        .support-completed-btn {
            margin-top: 12px;
            background: #0ea5e9;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 22px;
            font-weight: 700;
            cursor: pointer;
        }
        
        .support-completed-btn:hover {
            background: #0c4a6e;
        }
        
        /* Modals */
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
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            color: #6b7280;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        /* Level Completed Modal */
        .completed-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .completed-modal {
            background: #fff;
            width: 420px;
            max-width: 92%;
            border-radius: 14px;
            padding: 28px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -250px;
                z-index: 999;
                transition: left 0.3s ease;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .levels-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="dashboard-layout">
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Content Area -->
            <div class="content-area">
                <!-- Welcome Card -->
                <div class="card welcome-card">
                    <div class="welcome-info">
                        <h2><?php echo __t('welcome_back', 'Welcome back'); ?>, <?php echo htmlspecialchars($user['fullname'] ?? 'User'); ?></h2>
                        <p id="welcomeLevelText"><?php echo htmlspecialchars($stats['current_level']); ?> - <?php echo $stats['completed_tasks']; ?> <?php echo __t('tasks_completed', 'tasks completed'); ?></p>
                    </div>
                    <div class="welcome-balance balance" id="balanceText">
                        $<?php echo number_format($user['balance'], 2); ?>
                    </div>
                </div>
                
                <!-- Current Level Card -->
                <div class="card current-level-card">
                    <div class="level-badge"><?php echo __t('current_level', 'CURRENT LEVEL'); ?></div>
                    <div class="level-header">
                        <div class="level-name current-level" id="currentLevelName"><?php echo htmlspecialchars($stats['current_level']); ?></div>
                        <div class="level-category"><?php echo __t('name_items', 'Name Items'); ?></div>
                    </div>
                    <div class="progress-container">
                        <div class="progress-bar">
                            <div class="progress-fill" id="currentLevelProgressBar" style="width: <?php 
                                $current_level_data = $stats['levels'][$stats['current_level']] ?? ['completed' => 0, 'total' => 40, 'progress' => 0];
                                echo min(($current_level_data['completed'] / max($current_level_data['total'], 1)) * 100, 100); 
                            ?>%"></div>
                        </div>
                        <div style="text-align: right;">
                            <a href="#" class="level-link" onclick="startLevel('<?php echo htmlspecialchars($stats['current_level']); ?>', '1')"><?php echo __t('start_tasks', 'Start Tasks'); ?> →</a>
                        </div>
                    </div>
                </div>
                
                <!-- Today's Progress -->
                <div class="card">
                    <div class="today-progress">
                        <div class="today-label"><?php echo __t('today_progress', "Today's progress"); ?></div>
                        <div class="today-count" id="todayProgressText"><?php echo $stats['today_completed']; ?>/<?php echo $stats['daily_task_limit']; ?> <?php echo __t('tasks', 'tasks'); ?></div>
                    </div>
                </div>
                
                                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon available">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-number" id="availableTasksCount"><?php echo $stats['available_tasks']; ?></div>
                        <div class="stat-label"><?php echo __t('available_tasks', 'Available Tasks'); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon completed">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number" id="completedTasksCount"><?php echo $stats['completed_tasks']; ?></div>
                        <div class="stat-label"><?php echo __t('completed_tasks', 'Completed Tasks'); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['pending_withdrawals']; ?></div>
                        <div class="stat-label"><?php echo __t('pending_withdrawals', 'Pending Withdrawals'); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon performance">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($stats['performance_score'], 2); ?></div>
                        <div class="stat-label"><?php echo __t('performance_score', 'Performance Score'); ?></div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="startLevel('<?php echo htmlspecialchars($stats['current_level']); ?>', '1')">
                        <i class="fas fa-play"></i> <?php echo __t('start_tasks', 'Start Tasks'); ?>
                    </button>
                    <a href="withdrawals.php" class="btn btn-secondary">
                        <i class="fas fa-money-bill-wave"></i> <?php echo __t('request_withdrawal', 'Request Withdrawal'); ?>
                    </a>
                    <button class="btn btn-support" onclick="window.open('<?php echo htmlspecialchars(getSupportLink()); ?>', '_blank')">
                        <i class="fas fa-headset"></i> <?php echo __t('customer_support', 'Customer Support'); ?>
                    </button>
                </div>
                
                <!-- All Levels -->
                <div class="card">
                    <div class="card-title"><?php echo __t('all_levels', 'All Levels'); ?></div>
                    <p style="color: #6b7280; margin-bottom: 20px;"><?php echo __t('click_level_start_tasks', 'Click a level to start working on tasks'); ?></p>
                    
                    <div class="levels-grid">
                        <?php foreach ($levels as $level): ?>
                            <?php 
                            $is_current = normalizeLevelName($stats['current_level']) === normalizeLevelName($level);
                            // Check if level is actually unlocked (flushed levels should show as locked even if current)
                            $is_unlocked = $unlocked_levels[$level];
                            $level_status = $is_current && $is_unlocked ? 'current' : ($is_unlocked ? 'progress' : 'locked');
                            $level_data = $stats['levels'][$level] ?? ['completed' => 0, 'total' => 40, 'available' => 40, 'progress' => 0];
                            ?>
                            <div class="level-card <?php echo $is_current ? 'current' : ''; ?>" data-level="<?php echo htmlspecialchars($level); ?>" data-unlocked="<?php echo $is_unlocked ? '1' : '0'; ?>" onclick="startLevel(this.dataset.level, this.dataset.unlocked)">
                                <div class="level-card-header">
                                    <div class="level-card-name"><?php echo htmlspecialchars($level); ?></div>
                                    <div class="level-status <?php echo $level_status; ?>">
                                        <?php echo strtoupper($level_status); ?>
                                    </div>
                                </div>
                                <div class="level-category"><?php echo __t('name_items', 'Name Items'); ?></div>
                                <div class="level-progress-text"><?php echo __t('progress', 'Progress'); ?></div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo min(($level_data['completed'] / max($level_data['total'], 1)) * 100, 100); ?>%"></div>
                                </div>
                                <div class="level-progress-text level-progress"><?php echo $level_data['completed']; ?>/<?php echo $level_data['total']; ?> tasks</div>
                                <div class="available-tasks"><?php echo __t('available', 'Available'); ?>: <?php echo $level_data['available']; ?> <?php echo __t('tasks', 'tasks'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Testimonials -->
                <?php if (!empty($testimonials)): ?>
                    <div class="card">
                        <div class="card-title"><?php echo __t('what_community_says', 'What Our Community Says'); ?></div>
                        <p style="color: #6b7280; margin-bottom: 20px;"><?php echo __t('community_trust_text', 'Our clients and users Trust us to deliver quality services and reliable earnings. Here\'s what They Have to say.'); ?></p>
                        
                        <?php foreach ($testimonials as $testimonial): ?>
                            <div class="testimonial-card">
                                <div class="testimonial-quote">
                                    <i class="fas fa-quote-left" style="color: #667eea; margin-right: 8px;"></i>
                                    <?php echo htmlspecialchars($testimonial['content']); ?>
                                </div>
                                <div class="testimonial-author">
                                    <div>
                                        <div class="testimonial-name"><?php echo htmlspecialchars($testimonial['name']); ?></div>
                                        <div class="testimonial-badge"><?php echo htmlspecialchars(ucfirst($testimonial['type'])); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Task Modal -->
    <div class="modal" id="taskModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="taskModalTitle"><?php echo __t('tasks', 'Tasks'); ?></h3>
                <button class="modal-close" onclick="closeTaskModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="taskModalBody">
                <!-- Task content will be loaded here -->
            </div>
        </div>
    </div>
    
    <!-- Locked Level Modal -->
    <div class="modal" id="lockedModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="lockedModalTitle"><?php echo __t('unlock_level', 'Unlock Level'); ?></h3>
                <button class="modal-close" onclick="closeLockedModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p><?php echo __t('level_requires_setup', 'This level requires additional setup to proceed. Please contact our customer service for personal assistance to continue with this level.'); ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-support" onclick="window.open('<?php echo htmlspecialchars(getSupportLink()); ?>', '_blank')">
                    <?php echo __t('contact_customer_service', 'Contact Customer Service'); ?>
                </button>
                <button class="btn btn-secondary" onclick="closeLockedModal()"><?php echo __t('cancel', 'Cancel'); ?></button>
            </div>
        </div>
    </div>

    <div class="modal" id="comboModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><?php echo __t('combo_required', 'Combo Required'); ?></h3>
                <button class="modal-close" onclick="closeComboModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="comboModalBody"></div>
            <div class="modal-footer" id="comboModalFooter">
                <button class="btn btn-secondary" onclick="closeComboModal()"><?php echo __t('close', 'Close'); ?></button>
            </div>
        </div>
    </div>
    
    <!-- Level Completed Modal -->
    <div class="completed-modal-overlay" id="completedModal" style="display: none;">
        <div class="completed-modal">
            <div style="text-align: center; margin-bottom: 20px;">
                <i class="fas fa-trophy" style="font-size: 48px; color: #f59e0b;"></i>
    <script>
        const HTG_CLIENT_DEBUG = false;
        function htgDebug() {
            if (HTG_CLIENT_DEBUG && window.console) {
                console.log.apply(console, arguments);
            }
        }

        // Support link from global function
        window.SUPPORT_LINK = "<?php echo htmlspecialchars(getSupportLink(), ENT_QUOTES); ?>";
        
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        function openTaskModal(level) {
            htgDebug("ACTIVE OPEN TASK MODAL FUNCTION CALLED WITH LEVEL:", level);
            
            const modal = document.getElementById('taskModal');
            const title = document.getElementById('taskModalTitle');
            const body = document.getElementById('taskModalBody');
            
            title.textContent = level + ' <?php echo addslashes(__t('tasks', 'Tasks')); ?>';
            
            // Load tasks via AJAX or show task content
            body.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #667eea;"></i>
                    <p style="margin-top: 16px; color: #6b7280;"><?php echo __t('loading_tasks', 'Loading tasks...'); ?></p>
                </div>
            `;
            
            modal.classList.add('active');
            
            // Load tasks (this would be implemented with actual task loading)
            setTimeout(() => {
                loadTasks(level);
            }, 1000);
        }
        
        function closeTaskModal() {
            document.getElementById('taskModal').classList.remove('active');
        }
        
        function openLockedModal(level) {
            const modal = document.getElementById('lockedModal');
            const title = document.getElementById('lockedModalTitle');
            
            title.textContent = '<?php echo __t('unlock', 'Unlock'); ?> ' + level;
            modal.classList.add('active');
        }
        
        function closeLockedModal() {
            document.getElementById('lockedModal').classList.remove('active');
        }
        
        function openCompletedModal(levelName) {
            const modal = document.getElementById('completedModal');
            const title = document.getElementById('completedModalTitle');
            const supportBtn = document.getElementById('completedSupportBtn');
            
            // Update title with dynamic level name
            title.textContent = `<?php echo __t('all_tasks_completed_in_level', 'All tasks completed in'); ?> ${levelName} <?php echo __t('level_exclamation', 'level!'); ?>`;
            
            // Update support button link
            supportBtn.onclick = function() {
                window.open(window.SUPPORT_LINK || 'support.php', '_blank');
            };
            
            // Show modal
            modal.style.display = 'flex';
        }
        
        function closeCompletedModal() {
            document.getElementById('completedModal').style.display = 'none';
        }
        
        function startLevel(levelName, unlocked) {
            unlocked = String(unlocked);

            if (
                levelName === 'Bronze' ||
                unlocked === '1' ||
                unlocked === 'true'
            ) {
                openTaskModal(levelName);
                return;
            }

            showUnlockModal(levelName);
        }
        
        function showUnlockModal(levelName) {
            openLockedModal(levelName);
        }
        
        function handleLevelClick(level, status) {
            // Bronze is always unlocked - allow immediate access
            if (level === 'Bronze') {
                openTaskModal(level);
                return;
            }
            
            if (status === 'locked') {
                openLockedModal(level);
            } else {
                // Double-check unlock status in database before opening tasks
                fetch('check_level_unlock.php?level=' + encodeURIComponent(level))
                    .then(response => response.json())
                    .then(data => {
                        if (data.unlocked) {
                            openTaskModal(level);
                        } else {
                            openLockedModal(level);
                        }
                    })
                    .catch(error => {
                        console.error('Error checking level unlock status:', error);
                        openLockedModal(level);
                    });
            }
        }
        
        function loadTasks(level) {
            fetch('load_tasks.php?level=' + encodeURIComponent(level))
                .then(response => response.json())
                .then(data => {
                    const body = document.getElementById('taskModalBody');
                    
                    if (data.error) {
                        body.innerHTML = `
                            <div style="text-align: center; padding: 40px;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ef4444;"></i>
                                <h4 style="margin: 16px 0 8px 0;"><?php echo __t('error', 'Error'); ?></h4>
                                <p style="color: #6b7280;">${data.error}</p>
                                <button class="btn btn-primary" onclick="closeTaskModal()"><?php echo __t('close', 'Close'); ?></button>
                            </div>
                        `;
                        return;
                    }

                    if (data.combo_required && data.combo) {
                        if (data.dashboard_stats) {
                            updateDashboardElements(data.dashboard_stats);
                        }
                        body.innerHTML = `
                            <div style="text-align: center; padding: 40px;">
                                <i class="fas fa-bolt" style="font-size: 48px; color: #f59e0b;"></i>
                                <h4 style="margin: 16px 0 8px 0;"><?php echo __t('combo_required', 'Combo Required'); ?></h4>
                                <p style="color: #6b7280;"><?php echo __t('combo_flow_paused', 'Tasks are paused at this combo point. Please contact support or wait for admin confirmation.'); ?></p>
                            </div>
                        `;
                        showComboModal(data.combo);
                        return;
                    }
                    
                    if (data.completed || !data.task) {
                        body.innerHTML = `
                            <div style="text-align: center; padding: 40px;">
                                <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981;"></i>
                                <h4 style="margin: 16px 0 8px 0;"><?php echo __t('all_tasks_completed', 'All Tasks Completed!'); ?></h4>
                                <p style="color: #6b7280;"><?php echo __t('no_tasks_available_level', 'No tasks available for this level'); ?></p>
                                <button class="btn btn-primary" onclick="closeTaskModal()"><?php echo __t('close', 'Close'); ?></button>
                                <button type="button" onclick="window.location.href=window.SUPPORT_LINK || 'support.php'" style="
    margin-top:12px;
    background:#0ea5e9;
    color:white;
    border:none;
    border-radius:10px;
    padding:12px 22px;
    font-weight:700;
    cursor:pointer;
">
    <?php echo __t('contact_customer_support', 'Contact Customer Support'); ?>
</button>
                            </div>
                        `;
                        return;
                    }
                    
                    // Store all_tasks data for progress calculation
                    window.currentAllTasks = data.all_tasks || [];
                    htgDebug("STORED ALL_TASKS:", window.currentAllTasks);
                    
                    // Display current task using renderTask for consistency
                    renderTask(data.task);
                    
                })
                .catch(error => {
                    console.error('Error loading tasks:', error);
                    const body = document.getElementById('taskModalBody');
                    body.innerHTML = `
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ef4444;"></i>
                            <h4 style="margin: 16px 0 8px 0;"><?php echo __t('error', 'Error'); ?></h4>
                            <p style="color: #6b7280;"><?php echo __t('failed_load_tasks', 'Failed to load tasks'); ?></p>
                            <button class="btn btn-primary" onclick="closeTaskModal()"><?php echo __t('close', 'Close'); ?></button>
                        </div>
                    `;
                });
        }
        
        function displayTask(task, level, allTasks) {
            const body = document.getElementById('taskModalBody');
            const taskIndex = allTasks.findIndex(t => t.id === task.id);
            
            body.innerHTML = `
                <div style="border-bottom: 1px solid #e5e7eb; padding-bottom: 16px; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <div>
                            <h4 style="margin: 0; color: #333;">${level}</h4>
                            <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 14px;">Name Items</p>
                        </div>
                        <div style="text-align: right;">
                            <span style="background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                ${taskIndex + 1}/${allTasks.length} done
                            </span>
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <div style="background: #f0f4ff; color: #667eea; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; margin-bottom: 12px;">
                        TASK ${taskIndex + 1} OF ${allTasks.length}
                    </div>
                    <div style="background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; margin-left: 8px;">
                        Name Items
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h5 style="margin: 0 0 8px 0; color: #333;"><?php echo __t('task_number_title', 'Task'); ?> ${taskIndex + 1}. ${task.title || '<?php echo __t('familiar_brand', 'Are you familiar with this brand?'); ?>'}</h5>
                    <p style="margin: 0; color: #6b7280; font-size: 14px;"><?php echo __t('exploring_visibility_popularity', 'Here we are exploring the visibility and popularity of a product'); ?></p>
                </div>
                
                ${task.image ? `
                    <div style="margin-bottom: 20px; text-align: center;">
                        <img src="uploads/tasks/${task.image}" alt="<?php echo __t('task_image', 'Task Image'); ?>" style="max-width: 100%; height: auto; border-radius: 8px; border: 1px solid #e5e7eb;">
                    </div>
                ` : ''}
                
                <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <div style="font-weight: 600; color: #92400e; margin-bottom: 8px;"><?php echo __t('instructions', 'INSTRUCTIONS'); ?></div>
                    <div style="color: #92400e; font-size: 14px;">${task.instructions || 'YES or NO'}</div>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-primary" onclick="completeTask(${task.id}, 'yes', '${level}')" style="flex: 1;">
                        <i class="fas fa-check"></i> <?php echo __t('i_know_this_item', 'I Know This Item'); ?>
                    </button>
                    <button class="btn" onclick="completeTask(${task.id}, 'no', '${level}')" style="flex: 1; background: #ef4444; color: white;">
                        <i class="fas fa-times"></i> <?php echo __t('i_dont_know', 'I Don\'t Know'); ?>
                    </button>
                </div>
            `;
        }
        
        function completeTask(taskId, response, level) {
            htgDebug("ACTIVE COMPLETE TASK FUNCTION RUNNING");
            htgDebug("SUBMIT RESPONSE", {taskId, response, level});
            
            // Disable buttons to prevent double clicking
            disableTaskButtons();
            
            fetch('task_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    task_id: taskId,
                    answer: response,
                    level: level
                })
            })
            .then(response => response.json())
            .then(data => {
                htgDebug("SUBMIT RESPONSE", data);
                htgDebug("NEXT TASK", data.next_task);
                
                if (data.error) {
                    console.error('Task error:', data.error);
                    // Re-enable buttons on error
                    enableTaskButtons();
                    return;
                }
                
                // Update dashboard stats immediately
                if (data.dashboard_stats) {
                    updateDashboardElements(data.dashboard_stats);
                } else {
                    updateDashboardStats(data);
                }
                
                // Update live dashboard activity
                updateLiveActivity(data);
                
                // AUTO NEXT CHECK
                htgDebug("AUTO NEXT CHECK:");
                htgDebug("success:", data.success);
                htgDebug("next_task:", data.next_task);
                htgDebug("combo_required:", data.combo_required);
                htgDebug("level_completed:", data.level_completed);
                
                if (data.success && data.combo_required && data.combo) {
                    htgDebug("COMBO REQUIRED - Showing combo modal");
                    if (data.dashboard_stats) {
                        updateDashboardElements(data.dashboard_stats);
                    }
                    showComboModal(data.combo);
                    return;
                }
                
                if (data.success && data.next_task) {
                    htgDebug("RENDERING NEXT TASK NOW");
                    if (data.all_tasks) {
                        window.currentAllTasks = data.all_tasks;
                    } else if (window.currentAllTasks) {
                        const completedIndex = window.currentAllTasks.findIndex(t => parseInt(t.id, 10) === parseInt(taskId, 10));
                        if (completedIndex !== -1) {
                            window.currentAllTasks[completedIndex].completed = true;
                        }
                    }
                    renderTask(data.next_task);
                    return;
                }
                
                if (data.success && data.level_completed) {
                    htgDebug("LEVEL COMPLETE");
                    showLevelCompletionInModal();
                    return;
                }
                
            })
            .catch(error => {
                console.error('Error completing task:', error);
                console.error('Response:', error.response);
                // Re-enable buttons on error
                enableTaskButtons();
            });
        }
        
        function updateDashboardStats(data) {
            // Store latest data to prevent reverting to old values
            window.latestDashboardData = data;
            
            // Update balance
            const balanceText = document.getElementById('balanceText');
            if (balanceText) {
                balanceText.textContent = '$' + data.balance;
            }
            
            // Update welcome card level text
            const welcomeLevelText = document.getElementById('welcomeLevelText');
            if (welcomeLevelText) {
                welcomeLevelText.textContent = data.current_level + ' - ' + data.completed_tasks + ' tasks completed';
            }
            
            // Update current level name
            const currentLevelName = document.getElementById('currentLevelName');
            if (currentLevelName) {
                currentLevelName.textContent = data.current_level;
            }
            
            // Update current level progress
            const currentLevelProgressBar = document.getElementById('currentLevelProgressBar');
            if (currentLevelProgressBar) {
                currentLevelProgressBar.style.width = data.progress_percent + '%';
            }
            
            // Update available tasks
            const availableTasksCount = document.getElementById('availableTasksCount');
            if (availableTasksCount) {
                availableTasksCount.textContent = data.available_tasks;
            }
            
            // Update completed tasks
            const completedTasksCount = document.getElementById('completedTasksCount');
            if (completedTasksCount) {
                completedTasksCount.textContent = data.completed_tasks;
            }
            
            // Update today's progress
            const todayProgressText = document.getElementById('todayProgressText');
            if (todayProgressText) {
                todayProgressText.textContent = data.today_completed + '/' + data.daily_limit + ' tasks';
            }
            
            // Update level cards
            if (data.all_levels) {
                Object.keys(data.all_levels).forEach(level => {
                    const levelCard = document.querySelector(`[data-level="${level}"]`);
                    if (levelCard) {
                        const completedEl = levelCard.querySelector('.level-progress');
                        const progressEl = levelCard.querySelector('.progress-fill');
                        const availableEl = levelCard.querySelector('.available-tasks');
                        
                        if (completedEl) {
                            completedEl.textContent = data.all_levels[level].completed + '/' + data.all_levels[level].total;
                        }
                        if (progressEl) {
                            progressEl.style.width = data.all_levels[level].progress + '%';
                        }
                        if (availableEl) {
                            availableEl.textContent = 'Available: ' + data.all_levels[level].available;
                        }
                    }
                });
            }
        }
        
        function showTaskInModal(task) {
            const modal = document.getElementById('taskModal');
            const modalBody = document.getElementById('taskModalBody');
            
            if (!modal || !modalBody) {
                console.error('Task modal not found');
                return;
            }
            
            // Update modal content with new task
            modalBody.innerHTML = `
                <div class="task-header">
                    <h3>Task ${task.task_number} - ${task.title}</h3>
                    <span class="task-badge">${task.level}</span>
                </div>
                
                <div class="task-content">
                    <div class="task-image">
                        <img src="${task.image || 'https://via.placeholder.com/300x200'}" alt="${task.title}"
                    </div>
                    
                    <div class="task-description">
                        <p>${task.description}</p>
                    </div>
                </div>
                
                <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <div style="font-weight: 600; color: #92400e; margin-bottom: 8px;"><?php echo __t('instructions', 'INSTRUCTIONS'); ?></div>
                    <div style="color: #92400e; font-size: 14px;"><?php echo __t('yes_or_no', 'YES or NO'); ?></div>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-primary" onclick="completeTask(${task.id}, 'yes', '${task.level}')" style="flex: 1;">
                        <i class="fas fa-check"></i> <?php echo __t('i_know_this_item', 'I Know This Item'); ?>
                    </button>
                    <button class="btn" onclick="completeTask(${task.id}, 'no', '${task.level}')" style="flex: 1; background: #ef4444; color: white;">
                        <i class="fas fa-times"></i> <?php echo __t('i_dont_know', 'I Don\'t Know'); ?>
                    </button>
                </div>
            `;
            
            // Ensure modal is open
            modal.classList.add('active');
        }
        
        function showLevelCompletionInModal() {
            const modalBody = document.getElementById('taskModalBody');
            
            if (!modalBody) {
                console.error('Task modal body not found');
                return;
            }
            
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div style="background: linear-gradient(135deg, #10b981, #059669); color: white; border-radius: 50%; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-check" style="font-size: 32px;"></i>
                    </div>
                    <h3 style="color: #10b981; margin-bottom: 12px;"> <?php echo __t('level_completed', 'Level Completed!'); ?></h3>
                    <p style="color: #6b7280; margin-bottom: 20px;"><?php echo __t('completed_all_tasks_level', 'You\'ve completed all tasks in this level!'); ?></p>
                    <div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                        <div style="font-weight: 600; color: #166534; margin-bottom: 8px;"><?php echo __t('level_statistics', 'Level Statistics'); ?></div>
                        <div style="color: #166534; font-size: 14px;">
                            40/40 tasks completed
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="closeTaskModal()">
                        <i class="fas fa-check"></i> <?php echo __t('continue', 'Continue'); ?>
                    </button>
                </div>
            `;
        }
        
        function updateLiveActivity(data) {
            htgDebug("UPDATING LIVE ACTIVITY:", data);
            
            // Update current level if provided
            if (data.current_level) {
                const currentLevelElements = document.querySelectorAll('.current-level, #currentLevelName, #welcomeLevelText');
                currentLevelElements.forEach(el => {
                    if (el.id === 'welcomeLevelText') {
                        el.textContent = `${data.current_level} - ${data.completed_tasks || 0} tasks completed`;
                    } else if (el.id === 'currentLevelName') {
                        el.textContent = data.current_level;
                    }
                });
            }
            
            // Update completed tasks count
            if (data.completed_tasks !== undefined) {
                const completedElements = document.querySelectorAll('.completed-count, #completedTasksCount');
                completedElements.forEach(el => {
                    el.textContent = data.completed_tasks;
                });
                
                // Update progress text
                const progressElements = document.querySelectorAll('.level-progress, .progress-text');
                progressElements.forEach(el => {
                    el.textContent = `${data.completed_tasks}/40`;
                });
            }
            
            // Update available tasks
            if (data.available_tasks !== undefined) {
                const availableElements = document.querySelectorAll('.available-tasks');
                availableElements.forEach(el => {
                    el.textContent = 'Available: ' + data.available_tasks;
                });
            }
            
            // Update balance
            if (data.balance !== undefined) {
                const balanceElements = document.querySelectorAll('#balanceText, .balance');
                balanceElements.forEach(el => {
                    el.textContent = '$' + parseFloat(data.balance).toFixed(2);
                });
            }
            
            // Update performance score
            if (data.performance_score !== undefined) {
                const scoreElements = document.querySelectorAll('.performance-score');
                scoreElements.forEach(el => {
                    el.textContent = parseFloat(data.performance_score).toFixed(2);
                });
            }
            
            // Update today's progress
            if (data.today_completed !== undefined) {
                const todayElements = document.querySelectorAll('#todayProgressText');
                todayElements.forEach(el => {
                    el.textContent = `${data.today_completed}/${data.daily_limit || 40} tasks`;
                });
            }
            
            // Update level cards progress bars
            if (data.current_level && data.completed_tasks !== undefined) {
                const levelCard = document.querySelector(`[data-level="${data.current_level}"]`);
                if (levelCard) {
                    const progressFill = levelCard.querySelector('.progress-fill');
                    const progressText = levelCard.querySelector('.level-progress');
                    const availableTasks = levelCard.querySelector('.available-tasks');
                    
                    if (progressFill) {
                        const percentage = (data.completed_tasks / 40) * 100;
                        progressFill.style.width = Math.min(percentage, 100) + '%';
                    }
                    
                    if (progressText) {
                        progressText.textContent = `${data.completed_tasks}/40`;
                    }
                    
                    if (availableTasks) {
                        availableTasks.textContent = 'Available: ' + (40 - data.completed_tasks);
                    }
                }
            }
            
            htgDebug("LIVE ACTIVITY UPDATED");
        }
        
        function showLevelCompletion(data) {
            const modal = document.getElementById('taskModal');
            const modalBody = document.getElementById('taskModalBody');
            
            if (!modal || !modalBody) {
                console.error('Task modal not found');
                return;
            }
            
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div style="background: linear-gradient(135deg, #10b981, #059669); color: white; border-radius: 50%; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-check" style="font-size: 32px;"></i>
                    </div>
                    <h3 style="color: #10b981; margin-bottom: 12px;"> <?php echo __t('level_completed', 'Level Completed!'); ?></h3>
                    <p style="color: #6b7280; margin-bottom: 20px;"><?php echo __t('completed_all_tasks_level', 'You\'ve completed all tasks in this level!'); ?></p>
                    <div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                        <div style="font-weight: 600; color: #166534; margin-bottom: 8px;"><?php echo __t('level_statistics', 'Level Statistics'); ?></div>
                        <div style="color: #166534; font-size: 14px;">
                            ${data.current_level_completed || 0} / ${data.current_level_total || 0} tasks completed
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="closeTaskModal()">
                        <i class="fas fa-check"></i> <?php echo __t('continue', 'Continue'); ?>
                    </button>
                </div>
            `;
        }    
            
        function updateDashboardElements(stats) {
            // Update current level text
            const currentLevelElements = document.querySelectorAll('.current-level, #currentLevelName, #welcomeLevelText');
            currentLevelElements.forEach(el => {
                if (el.id === 'welcomeLevelText') {
                    el.textContent = `${stats.current_level} - ${stats.completed_tasks} tasks completed`;
                } else {
                    el.textContent = stats.current_level;
                }
            });
            
            // Update balance
            const balanceElements = document.querySelectorAll('#balanceText, .balance');
            balanceElements.forEach(el => {
                el.textContent = '$' + stats.balance.toFixed(2);
            });
            
            // Update progress bar and text
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(el => {
                el.style.width = stats.progress + '%';
            });
            
            const progressTexts = document.querySelectorAll('.progress-text, .level-progress');
            progressTexts.forEach(el => {
                el.textContent = `${stats.level_completed_tasks}/${stats.level_total_tasks}`;
            });
            
            // Update available tasks
            const availableElements = document.querySelectorAll('.available-tasks');
            availableElements.forEach(el => {
                el.textContent = 'Available: ' + stats.available_tasks;
            });
            
            // Update completed tasks
            const completedElements = document.querySelectorAll('.completed-count');
            completedElements.forEach(el => {
                el.textContent = stats.completed_tasks;
            });
            
            // Update completed tasks by ID
            const completedTasksCount = document.getElementById('completedTasksCount');
            if (completedTasksCount) {
                completedTasksCount.textContent = stats.completed_tasks;
            }
            
            // Update pending withdrawals
            const pendingElements = document.querySelectorAll('.pending-withdrawals');
            pendingElements.forEach(el => {
                el.textContent = stats.pending_withdrawals;
            });
            
            // Update performance score
            const scoreElements = document.querySelectorAll('.performance-score');
            scoreElements.forEach(el => {
                el.textContent = stats.performance_score.toFixed(2);
            });
            
            // Update today's progress
            const todayProgressElements = document.querySelectorAll('#todayProgressText');
            todayProgressElements.forEach(el => {
                // Calculate today's progress (this would need to be returned from backend)
                el.textContent = `${stats.completed_tasks}/40 tasks`;
            });
            
            // Update level cards
            if (stats.all_levels) {
                Object.keys(stats.all_levels).forEach(level => {
                    const levelCard = document.querySelector(`[data-level="${level}"]`);
                    if (levelCard) {
                        const completedEl = levelCard.querySelector('.level-progress');
                        const progressEl = levelCard.querySelector('.progress-fill');
                        const availableEl = levelCard.querySelector('.available-tasks');
                        
                        if (completedEl) {
                            completedEl.textContent = stats.all_levels[level].completed + '/' + stats.all_levels[level].total;
                        }
                        if (progressEl) {
                            progressEl.style.width = stats.all_levels[level].progress + '%';
                        }
                        if (availableEl) {
                            availableEl.textContent = 'Available: ' + stats.all_levels[level].available;
                        }
                        
                        // Update current level indicator
                        if (level === stats.current_level) {
                            levelCard.classList.add('current');
                        } else {
                            levelCard.classList.remove('current');
                        }
                    }
                });
            }
            
            htgDebug('Dashboard elements updated with stats:', stats);
        }
        
        function renderTask(task) {
            htgDebug("ACTIVE RENDER TASK FUNCTION CALLED WITH:", task);
            htgDebug("TASK RECEIVED", task);
            
            // Check if task data is valid
            if (!task || !task.id) {
                console.error("INVALID TASK DATA:", task);
                return;
            }
            
            // Update the modal content with the new task
            const modalBody = document.getElementById('taskModalBody');
            
            if (!modalBody) {
                console.error('Modal body not found');
                return;
            }
            
            // Calculate task number and progress from all_tasks data
            const allTasks = window.currentAllTasks || [];
            const completedTasks = allTasks.length ? allTasks.filter(t => parseInt(t.completed, 10) === 1 || t.completed === true).length : (parseInt(task.completed_count, 10) || 0);
            const totalTasks = allTasks.length || parseInt(task.total_tasks, 10) || 40;
            const foundTaskIndex = allTasks.findIndex(t => parseInt(t.id, 10) === parseInt(task.id, 10));
            const currentTaskNumber = foundTaskIndex >= 0 ? foundTaskIndex + 1 : (parseInt(task.task_number, 10) || completedTasks + 1);
            
            htgDebug("CALCULATED VALUES:", {
                currentTaskNumber,
                completedTasks,
                totalTasks,
                allTasksLength: allTasks.length
            });
            
            modalBody.innerHTML = `
                <!-- Header with level and progress -->
                <div style="border-bottom: 1px solid #e5e7eb; padding-bottom: 16px; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <div>
                            <h4 style="margin: 0; color: #333;">${task.level || 'Bronze'}</h4>
                            <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 14px;">Name Items</p>
                        </div>
                        <div style="text-align: right;">
                            <span style="background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                ${completedTasks}/${totalTasks} done
                            </span>
                        </div>
                    </div>
                    
                    <!-- Progress stepper -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        ${Array.from({length: Math.min(5, totalTasks)}, (_, i) => `
                            <div style="display: flex; flex-direction: column; align-items: center;">
                                <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; 
                                    ${i < completedTasks ? 'background: #10b981; color: white;' : 
                                      i === currentTaskNumber - 1 ? 'background: #667eea; color: white;' : 
                                      'background: #e5e7eb; color: #6b7280;'}">
                                    ${i < completedTasks ? '✓' : i + 1}
                                </div>
                                ${i === 0 || i === Math.min(5, totalTasks) - 1 ? `<span style="font-size: 10px; color: #6b7280; margin-top: 2px;">${i === 0 ? '1' : totalTasks}</span>` : ''}
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <!-- Task info -->
                <div style="margin-bottom: 20px;">
                    <div style="background: #f0f4ff; color: #667eea; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; margin-bottom: 12px;">
                        TASK ${currentTaskNumber} OF ${totalTasks}
                    </div>
                    <div style="background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; margin-left: 8px;">
                        Name Items
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h5 style="margin: 0 0 8px 0; color: #333;">${currentTaskNumber}. ${task.title || 'Task Title'}</h5>
                    <p style="margin: 0; color: #6b7280; font-size: 14px;">${task.description || 'Task description'}</p>
                </div>
                
                <!-- Task image with grey side panels -->
                ${task.image ? `
                    <div style="margin-bottom: 20px; display: flex; justify-content: center; align-items: stretch;">
                        <div style="background: #f3f4f6; width: 20%; border-radius: 8px 0 0 8px;"></div>
                        <div style="flex: 1; text-align: center;">
                            <img src="uploads/tasks/${task.image}" alt="<?php echo __t('task_image', 'Task Image'); ?>" style="max-width: 100%; height: auto; border-radius: 8px; border: 1px solid #e5e7eb;">
                        </div>
                        <div style="background: #f3f4f6; width: 20%; border-radius: 0 8px 8px 0;"></div>
                    </div>
                ` : ''}
                
                <!-- Instructions box -->
                <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <div style="font-weight: 600; color: #92400e; margin-bottom: 8px;"><?php echo __t('instructions', 'INSTRUCTIONS'); ?></div>
                    <div style="color: #92400e; font-size: 14px;">${task.instructions || 'YES or NO'}</div>
                </div>
                
                <!-- Fixed buttons at bottom -->
                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-primary" onclick="completeTask(${task.id}, 'yes', '${task.level}')" style="flex: 1;">
                        <i class="fas fa-check"></i> <?php echo __t('i_know_this_item', 'I Know This Item'); ?>
                    </button>
                    <button class="btn" onclick="completeTask(${task.id}, 'no', '${task.level}')" style="flex: 1; background: #ef4444; color: white;">
                        <i class="fas fa-times"></i> <?php echo __t('i_dont_know', 'I Don\'t Know'); ?>
                    </button>
                </div>
            `;
            
            // Re-enable buttons after new task loads
            htgDebug('New task rendered, re-enabling buttons');
            enableTaskButtons();
        }
        
        function disableTaskButtons() {
            const buttons = document.querySelectorAll('#taskModalBody button');
            buttons.forEach(button => {
                button.disabled = true;
                button.style.opacity = '0.6';
                button.style.cursor = 'not-allowed';
            });
        }
        
        function enableTaskButtons() {
            const buttons = document.querySelectorAll('#taskModalBody button');
            buttons.forEach(button => {
                button.disabled = false;
                button.style.opacity = '1';
                button.style.cursor = 'pointer';
            });
        }
        
        // Handle support button click
        document.addEventListener('click', function(e) {
            if (e.target && e.target.id === 'completedSupportBtn') {
                window.location.href = window.SUPPORT_LINK || 'support.php';
            }
        });
        
                
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        function showComboModal(combo) {
            if (!combo) {
                return;
            }

            const modal = document.getElementById('comboModal');
            const body = document.getElementById('comboModalBody');
            const footer = document.getElementById('comboModalFooter');
            if (!modal || !body || !footer) {
                return;
            }

            body.innerHTML = `
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fas fa-bolt" style="font-size: 48px; color: #f59e0b; margin-bottom: 16px;"></i>
                    <div style="background: #fef3c7; color: #92400e; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; display: inline-block; margin-bottom: 8px;">
                        <?php echo __t('combo_required', 'Combo Required'); ?>
                    </div>
                </div>
                <div style="background: #f0f4ff; border: 1px solid #667eea; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <div style="margin-bottom: 16px;">
                        <strong><?php echo __t('message', 'Message'); ?>:</strong><br>
                        ${combo.message || ''}
                    </div>
                    <div style="margin-bottom: 16px;">
                        <strong><?php echo __t('combo_amount', 'Combo Amount'); ?>:</strong><br>
                        $${parseFloat(combo.amount || 0).toFixed(2)}
                    </div>
                    <div>
                        <strong><?php echo __t('current_task', 'Current Task'); ?>:</strong><br>
                        <?php echo __t('task', 'Task'); ?> ${combo.task_number} - ${combo.level}
                    </div>
                </div>
            `;

            footer.innerHTML = `
                <button class="btn btn-support" onclick="window.open(window.SUPPORT_LINK || '#', '_blank')" style="background: #f59e0b; color: white;">
                    <?php echo __t('contact_support', 'Contact Support'); ?>
                </button>
                <button class="btn btn-secondary" onclick="closeComboModal()"><?php echo __t('close', 'Close'); ?></button>
            `;

            modal.classList.add('active');
        }

        function closeComboModal() {
            const modal = document.getElementById('comboModal');
            if (modal) {
                modal.classList.remove('active');
            }
        }
            </script>
</body>
</html>
