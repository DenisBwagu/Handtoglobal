<?php
require_once 'config.php';
require_once 'get_setting.php';

// Get Telegram link from settings
$supportLink = get_setting('telegram_link', '<?php echo htmlspecialchars($supportLink); ?>');
require_once 'get_translation.php';

requireLogin();

// Get database connection
$conn = getConnection();

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$msg = "";
$error = "";

// Handle language update
if (isset($_POST['update_language'])) {
    $language = $_POST['language'] ?? 'english';
    
    if (is_language_supported($language)) {
        if (set_user_language($language)) {
            $msg = "Language updated successfully!";
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        } else {
            $error = "Failed to update language";
        }
    } else {
        $error = "Invalid language selection";
    }
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($fullname)) {
        $error = "Please enter your full name";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([$fullname, $phone, $address, $_SESSION['user_id']]);
            
            $msg = "Profile updated successfully!";
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            // Update session
            $_SESSION['user_fullname'] = $fullname;
            
        } catch(PDOException $e) {
            $error = "Failed to update profile: " . $e->getMessage();
        }
    }
}

// Get current language
$current_language = get_current_language();
$available_languages = get_available_languages();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('profile'); ?> - <?php echo get_setting('site_name', '<?php echo get_setting('site_name', 'HandToGlobal'); ?>'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Favicon -->
    <?php $favicon = get_setting('favicon'); ?>
    <?php if ($favicon): ?>
        <link rel="icon" type="image/x-icon" href="<?php echo $favicon; ?>">
    <?php endif; ?>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #4f46e5;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text: #1a1a1a;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #f5f7fb;
            --white: #ffffff;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }
        
        /* Header */
        .header {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text);
        }
        
        .logo img {
            height: 40px;
            width: auto;
        }
        
        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .support-btn {
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s ease;
        }
        
        .support-btn:hover {
            background: #4338ca;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 14px;
        }
        
        .user-balance {
            color: var(--success);
            font-weight: 600;
            font-size: 14px;
        }
        
        /* Layout */
        .layout {
            display: flex;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            gap: 20px;
        }
        
        .sidebar {
            width: 280px;
            background: var(--white);
            border-radius: 12px;
            padding: 24px;
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 8px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .sidebar-menu a:hover {
            background: var(--bg);
        }
        
        .sidebar-menu a.active {
            background: var(--primary);
            color: white;
        }
        
        .sidebar-menu i {
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            flex: 1;
            background: var(--white);
            border-radius: 12px;
            padding: 32px;
        }
        
        /* Form Styles */
        .page-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 32px;
            color: var(--text);
        }
        
        .form-section {
            margin-bottom: 32px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: var(--white);
            cursor: pointer;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #4338ca;
        }
        
        .btn-secondary {
            background: var(--border);
            color: var(--text);
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        /* Alert */
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: var(--bg);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }
        
        .stat-label {
            color: var(--muted);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="dashboard.php" class="logo">
                <?php $site_logo = get_setting('site_logo'); ?>
                <?php if ($site_logo): ?>
                    <img src="<?php echo $site_logo; ?>" alt="<?php echo get_setting('site_name', '<?php echo get_setting('site_name', 'HandToGlobal'); ?>'); ?>" style="height: 40px; margin-right: 12px;">
                <?php else: ?>
                    <i class="fas fa-hand-holding-usd" style="font-size: 32px; color: var(--primary); margin-right: 12px;"></i>
                <?php endif; ?>
                <div class="logo-text"><?php echo get_setting('site_name', '<?php echo get_setting('site_name', 'HandToGlobal'); ?>'); ?></div>
            </a>
            
            <div class="header-actions">
                <a href="<?php echo get_setting('telegram_link', '#'); ?>" class="support-btn" target="_blank">
                    <i class="fas fa-headset"></i> <?php echo __('support'); ?>
                </a>
                
                <div class="user-menu">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['fullname'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['fullname'] ?? 'User'); ?></div>
                        <div class="user-balance">$<?php echo number_format($user['balance'] ?? 0, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Layout -->
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <?php echo __('dashboard'); ?></a></li>
                <li><a href="tasks.php"><i class="fas fa-tasks"></i> <?php echo __('tasks'); ?></a></li>
                <li><a href="levels.php"><i class="fas fa-layer-group"></i> <?php echo __('levels'); ?></a></li>
                <li><a href="withdrawals.php"><i class="fas fa-arrow-up"></i> <?php echo __('withdrawals'); ?></a></li>
                <li><a href="profile.php" class="active"><i class="fas fa-user"></i> <?php echo __('profile'); ?></a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> <?php echo __('settings'); ?></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <?php echo __('logout'); ?></a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
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
            
            <h1 class="page-title"><?php echo __('profile'); ?></h1>
            
            <!-- User Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo htmlspecialchars($user['level'] ?? '1'); ?></div>
                    <div class="stat-label"><?php echo __('level'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($user['balance'] ?? 0, 2); ?></div>
                    <div class="stat-label"><?php echo __('balance'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo htmlspecialchars($user['completed_tasks'] ?? '0'); ?></div>
                    <div class="stat-label"><?php echo __('task_completed'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $current_language; ?></div>
                    <div class="stat-label"><?php echo __('language'); ?></div>
                </div>
            </div>
            
            <!-- Profile Information -->
            <div class="form-section">
                <h2 class="section-title"><?php echo __('profile'); ?> <?php echo __('information'); ?></h2>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('email'); ?></label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><?php echo __('name'); ?></label>
                        <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><?php echo __('phone'); ?></label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><?php echo __('address'); ?></label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary"><?php echo __('update'); ?> <?php echo __('profile'); ?></button>
                </form>
            </div>
            
            <!-- Language Settings -->
            <div class="form-section">
                <h2 class="section-title"><?php echo __('language'); ?> <?php echo __('settings'); ?></h2>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('language'); ?></label>
                        <select name="language" class="form-select">
                            <?php foreach ($available_languages as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo $current_language === $code ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="update_language" class="btn btn-secondary"><?php echo __('update'); ?> <?php echo __('language'); ?></button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
