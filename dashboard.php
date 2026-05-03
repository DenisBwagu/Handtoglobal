<?php
require_once 'config.php';
require_once 'get_site_settings.php';

requireLogin();

// Get user data
$conn = getConnection();
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get site settings
$site_settings = getSiteSettings();

// Get user statistics
$stats = [];
try {
    // Available tasks for current level
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tasks WHERE level = ? AND status = 'active'");
    $stmt->execute([$user['level']]);
    $stats['available_tasks'] = $stmt->fetch()['count'];
    
    // Completed tasks
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM completed_tasks WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['completed_tasks'] = $stmt->fetch()['count'];
    
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
    
    // Max levels per day from settings
    $stmt = $conn->prepare("SELECT value FROM settings WHERE setting_key = 'MaxLevelsPerDay'");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['max_levels_per_day'] = $result ? (int)$result['value'] : 3;
    
    // Tasks per level (default 40)
    $stmt = $conn->prepare("SELECT value FROM settings WHERE setting_key = 'TasksPerLevel'");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['tasks_per_level'] = $result ? (int)$result['value'] : 40;
    
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
    $stmt = $conn->prepare("SELECT * FROM testimonials WHERE status = 'active' ORDER BY created_at DESC LIMIT 3");
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
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE level = ? AND status = 'active' ORDER BY id LIMIT 40");
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

// Get available levels
$levels = ['Bronze', 'Silver', 'Gold', 'VIP 1'];
$unlocked_levels = [];
foreach ($levels as $level) {
    $unlocked_levels[$level] = $user[strtolower($level) . '_unlocked'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Hand to Global</title>
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
        
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: white;
            border-right: 1px solid #e5e7eb;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .sidebar-logo {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            text-decoration: none;
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
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-logo">
                    <i class="fas fa-handshake"></i> Hand to Global
                </a>
            </div>
            
            <nav class="sidebar-menu">
                <div class="sidebar-section">
                    <div class="sidebar-section-title">MAIN</div>
                    <a href="dashboard.php" class="sidebar-menu-item active">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="task_history.php" class="sidebar-menu-item">
                        <i class="fas fa-history"></i> Task History
                    </a>
                </div>
                
                <div class="sidebar-section">
                    <div class="sidebar-section-title">ACCOUNT</div>
                    <a href="withdrawals.php" class="sidebar-menu-item">
                        <i class="fas fa-money-bill-wave"></i> Withdrawals
                    </a>
                    <a href="profile.php" class="sidebar-menu-item">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="#" class="sidebar-menu-item" onclick="window.open('<?php echo $site_settings['TelegramLink'] ?? '#'; ?>', '_blank')">
                        <i class="fas fa-headset"></i> Support
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="btn btn-secondary" onclick="toggleSidebar()" style="display: none;">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                <div class="top-bar-right">
                    <div class="user-balance">
                        Balance: $<?php echo number_format($user['balance'], 2); ?>
                    </div>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Welcome Card -->
                <div class="card welcome-card">
                    <div class="welcome-info">
                        <h2>Welcome back, <?php echo htmlspecialchars($user['fullname']); ?></h2>
                        <p><?php echo htmlspecialchars($user['level']); ?> - <?php echo $stats['completed_tasks']; ?> tasks completed</p>
                    </div>
                    <div class="welcome-balance">
                        $<?php echo number_format($user['balance'], 2); ?>
                    </div>
                </div>
                
                <!-- Current Level Card -->
                <div class="card current-level-card">
                    <div class="level-badge">CURRENT LEVEL</div>
                    <div class="level-header">
                        <div class="level-name"><?php echo htmlspecialchars($user['level']); ?></div>
                        <div class="level-progress"><?php echo $stats['completed_tasks']; ?>/<?php echo $stats['tasks_per_level']; ?> tasks</div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo min(($stats['completed_tasks'] / $stats['tasks_per_level']) * 100, 100); ?>%"></div>
                    </div>
                    <div style="text-align: right;">
                        <a href="#" class="level-link" onclick="openTaskModal('<?php echo htmlspecialchars($user['level']); ?>')">Start Tasks →</a>
                    </div>
                </div>
                
                <!-- Today's Progress -->
                <div class="card">
                    <div class="today-progress">
                        <div class="today-label">Today's progress</div>
                        <div class="today-count"><?php echo $stats['today_completed']; ?>/<?php echo $stats['max_levels_per_day']; ?> levels</div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon available">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['available_tasks']; ?></div>
                        <div class="stat-label">Available Tasks</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon completed">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['completed_tasks']; ?></div>
                        <div class="stat-label">Completed Tasks</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['pending_withdrawals']; ?></div>
                        <div class="stat-label">Pending Withdrawals</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon performance">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($stats['performance_score'], 2); ?></div>
                        <div class="stat-label">Performance Score</div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openTaskModal('<?php echo htmlspecialchars($user['level']); ?>')">
                        <i class="fas fa-play"></i> Start Tasks
                    </button>
                    <a href="withdrawals.php" class="btn btn-secondary">
                        <i class="fas fa-money-bill-wave"></i> Request Withdrawal
                    </a>
                    <button class="btn btn-support" onclick="window.open('<?php echo $site_settings['TelegramLink'] ?? '#'; ?>', '_blank')">
                        <i class="fas fa-headset"></i> Customer Support
                    </button>
                </div>
                
                <!-- All Levels -->
                <div class="card">
                    <div class="card-title">All Levels</div>
                    <p style="color: #6b7280; margin-bottom: 20px;">Click a level to start working on tasks</p>
                    
                    <div class="levels-grid">
                        <?php foreach ($levels as $level): ?>
                            <?php 
                            $is_current = $user['level'] === $level;
                            $is_unlocked = $unlocked_levels[$level] || $is_current;
                            $level_status = $is_current ? 'current' : ($is_unlocked ? 'progress' : 'locked');
                            ?>
                            <div class="level-card <?php echo $is_current ? 'current' : ''; ?>" onclick="handleLevelClick('<?php echo htmlspecialchars($level); ?>', '<?php echo $level_status; ?>')">
                                <div class="level-card-header">
                                    <div class="level-card-name"><?php echo htmlspecialchars($level); ?></div>
                                    <div class="level-status <?php echo $level_status; ?>">
                                        <?php echo strtoupper($level_status); ?>
                                    </div>
                                </div>
                                <div class="level-category">Name Items</div>
                                <div class="level-progress-text">Progress</div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $is_current ? min(($stats['completed_tasks'] / $stats['tasks_per_level']) * 100, 100) : 0; ?>%"></div>
                                </div>
                                <div class="level-progress-text"><?php echo $is_current ? $stats['completed_tasks'] : 0; ?>/<?php echo $stats['tasks_per_level']; ?> tasks</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Testimonials -->
                <?php if (!empty($testimonials)): ?>
                    <div class="card">
                        <div class="card-title">What Our Community Says</div>
                        <p style="color: #6b7280; margin-bottom: 20px;">Our clients and users trust us to deliver quality services and reliable earnings. Here's what they have to say.</p>
                        
                        <?php foreach ($testimonials as $testimonial): ?>
                            <div class="testimonial-card">
                                <div class="testimonial-quote">
                                    <i class="fas fa-quote-left" style="color: #667eea; margin-right: 8px;"></i>
                                    <?php echo htmlspecialchars($testimonial['message']); ?>
                                </div>
                                <div class="testimonial-author">
                                    <div>
                                        <div class="testimonial-name"><?php echo htmlspecialchars($testimonial['name']); ?></div>
                                        <div class="testimonial-badge">Client</div>
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
                <h3 class="modal-title" id="taskModalTitle">Tasks</h3>
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
                <h3 class="modal-title" id="lockedModalTitle">Unlock Level</h3>
                <button class="modal-close" onclick="closeLockedModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>This level requires additional setup to proceed. Please contact our customer service for personal assistance to continue with this level.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-support" onclick="window.open('<?php echo $site_settings['TelegramLink'] ?? '#'; ?>', '_blank')">
                    Contact Customer Service
                </button>
                <button class="btn btn-secondary" onclick="closeLockedModal()">Cancel</button>
            </div>
        </div>
    </div>
    
    <!-- Combo Modal -->
    <div class="modal" id="comboModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Combo Available!</h3>
                <button class="modal-close" onclick="closeComboModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <?php if ($active_combo): ?>
                    <div style="text-align: center; margin-bottom: 20px;">
                        <div style="background: #fef3c7; color: #92400e; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; display: inline-block;">
                            <?php echo $active_combo['multiplier']; ?>x Multiplier
                        </div>
                    </div>
                    
                    <div style="background: #f0f4ff; border: 1px solid #667eea; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 12px 0; color: #333;">DEPOSIT</h4>
                        <div style="margin-bottom: 16px;">
                            <strong>Task range:</strong><br>
                            Tasks: <?php echo htmlspecialchars($active_combo['start_task_id']); ?> → <?php echo htmlspecialchars($active_combo['end_task_id']); ?> (<?php echo ($active_combo['end_task_id'] - $active_combo['start_task_id'] + 1); ?> tasks)
                        </div>
                        <div style="margin-bottom: 16px;">
                            <strong>Deposit Required:</strong><br>
                            $<?php echo number_format($active_combo['deposit_amount'], 2); ?>
                        </div>
                        <div>
                            <strong>Earnings Multiplier:</strong><br>
                            <?php echo htmlspecialchars($active_combo['multiplier']); ?>x
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-bottom: 16px;">
                        <p style="color: #6b7280;"><?php echo htmlspecialchars($active_combo['message'] ?? 'Complete the task range to activate your combo multiplier!'); ?></p>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-info-circle" style="font-size: 48px; color: #667eea;"></i>
                        <h4 style="margin: 16px 0 8px 0;">No Active Combos</h4>
                        <p style="color: #6b7280;">You don't have any active combos at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <?php if ($active_combo): ?>
                    <button class="btn btn-support" onclick="window.open('<?php echo $site_settings['TelegramLink'] ?? '#'; ?>', '_blank')">
                        Deposit via Telegram
                    </button>
                <?php endif; ?>
                <button class="btn btn-secondary" onclick="closeComboModal()">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        function openTaskModal(level) {
            const modal = document.getElementById('taskModal');
            const title = document.getElementById('taskModalTitle');
            const body = document.getElementById('taskModalBody');
            
            title.textContent = level + ' Tasks';
            
            // Load tasks via AJAX or show task content
            body.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #667eea;"></i>
                    <p style="margin-top: 16px; color: #6b7280;">Loading tasks...</p>
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
            
            title.textContent = 'Unlock ' + level;
            modal.classList.add('active');
        }
        
        function closeLockedModal() {
            document.getElementById('lockedModal').classList.remove('active');
        }
        
        function openComboModal() {
            document.getElementById('comboModal').classList.add('active');
        }
        
        function closeComboModal() {
            document.getElementById('comboModal').classList.remove('active');
        }
        
        function handleLevelClick(level, status) {
            if (status === 'locked') {
                openLockedModal(level);
            } else {
                openTaskModal(level);
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
                                <h4 style="margin: 16px 0 8px 0;">Error</h4>
                                <p style="color: #6b7280;">${data.error}</p>
                                <button class="btn btn-primary" onclick="closeTaskModal()">Close</button>
                            </div>
                        `;
                        return;
                    }
                    
                    if (!data.tasks || data.tasks.length === 0) {
                        body.innerHTML = `
                            <div style="text-align: center; padding: 40px;">
                                <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981;"></i>
                                <h4 style="margin: 16px 0 8px 0;">All Tasks Completed!</h4>
                                <p style="color: #6b7280;">${data.message || 'No tasks available for this level'}</p>
                                <button class="btn btn-primary" onclick="closeTaskModal()">Close</button>
                            </div>
                        `;
                        return;
                    }
                    
                    // Display first task
                    const task = data.tasks[0];
                    displayTask(task, level, data.tasks);
                    
                })
                .catch(error => {
                    console.error('Error loading tasks:', error);
                    const body = document.getElementById('taskModalBody');
                    body.innerHTML = `
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ef4444;"></i>
                            <h4 style="margin: 16px 0 8px 0;">Error</h4>
                            <p style="color: #6b7280;">Failed to load tasks</p>
                            <button class="btn btn-primary" onclick="closeTaskModal()">Close</button>
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
                    <h5 style="margin: 0 0 8px 0; color: #333;">${taskIndex + 1}. ${task.title || 'Are you familiar with this brand?'}</h5>
                    <p style="margin: 0; color: #6b7280; font-size: 14px;">${task.description || 'Here we are exploring the visibility and popularity of a product'}</p>
                </div>
                
                ${task.image ? `
                    <div style="margin-bottom: 20px; text-align: center;">
                        <img src="admin/uploads/${task.image}" alt="Task Image" style="max-width: 100%; height: auto; border-radius: 8px; border: 1px solid #e5e7eb;">
                    </div>
                ` : ''}
                
                <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <div style="font-weight: 600; color: #92400e; margin-bottom: 8px;">INSTRUCTIONS</div>
                    <div style="color: #92400e; font-size: 14px;">${task.instructions || 'YES or NO'}</div>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-primary" onclick="completeTask(${task.id}, 'yes')" style="flex: 1;">
                        <i class="fas fa-check"></i> I Know This Item
                    </button>
                    <button class="btn" onclick="completeTask(${task.id}, 'no')" style="flex: 1; background: #ef4444; color: white;">
                        <i class="fas fa-times"></i> I Don't Know
                    </button>
                </div>
            `;
        }
        
        function completeTask(taskId, response) {
            fetch('complete_task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    task_id: taskId,
                    response: response
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                
                // Show success message
                const body = document.getElementById('taskModalBody');
                body.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981;"></i>
                        <h4 style="margin: 16px 0 8px 0;">Task Completed!</h4>
                        <p style="color: #6b7280;">${data.message}</p>
                        <p style="color: #10b981; font-weight: 600;">Reward: $${data.reward}</p>
                        <p style="color: #667eea;">New Balance: $${data.new_balance}</p>
                        <button class="btn btn-primary" onclick="closeTaskModal(); location.reload();">Continue</button>
                    </div>
                `;
                
                // Update balance display
                setTimeout(() => {
                    location.reload();
                }, 2000);
                
            })
            .catch(error => {
                console.error('Error completing task:', error);
                alert('Failed to complete task. Please try again.');
            });
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
        
        // Show combo modal if user has active combo
        <?php if ($active_combo): ?>
            setTimeout(() => {
                openComboModal();
            }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>
