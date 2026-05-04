<?php
require_once '../config.php';
require_once '../get_setting.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();

$msg = "";
$error = "";
if (isset($_GET['saved'])) {
    $msg = "Settings updated successfully!";
}

// Handle settings update
if (isset($_POST['update_settings'])) {
    try {
        // Handle file uploads
        $upload_dir = '../uploads/settings/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Site Logo Upload
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
            $file_info = pathinfo($_FILES['site_logo']['name']);
            $extension = strtolower($file_info['extension']);
            
            if (in_array($extension, $allowed_types)) {
                $filename = 'logo_' . time() . '.' . $extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $upload_path)) {
                    $_POST['site_logo'] = 'uploads/settings/' . $filename;
                } else {
                    $error = "Failed to upload site logo";
                }
            } else {
                $error = "Invalid file type for site logo";
            }
        }
        
        // OG Image Upload
        if (isset($_FILES['og_image']) && $_FILES['og_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
            $file_info = pathinfo($_FILES['og_image']['name']);
            $extension = strtolower($file_info['extension']);
            
            if (in_array($extension, $allowed_types)) {
                $filename = 'og_' . time() . '.' . $extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['og_image']['tmp_name'], $upload_path)) {
                    $_POST['og_image'] = 'uploads/settings/' . $filename;
                } else {
                    $error = "Failed to upload OG image";
                }
            } else {
                $error = "Invalid file type for OG image";
            }
        }
        
        // Favicon Upload
        if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['ico', 'png', 'svg'];
            $file_info = pathinfo($_FILES['favicon']['name']);
            $extension = strtolower($file_info['extension']);
            
            if (in_array($extension, $allowed_types)) {
                $filename = 'favicon_' . time() . '.' . $extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['favicon']['tmp_name'], $upload_path)) {
                    $_POST['favicon'] = 'uploads/settings/' . $filename;
                } else {
                    $error = "Failed to upload favicon";
                }
            } else {
                $error = "Invalid file type for favicon";
            }
        }
        
        if (empty($error)) {
            // Update all settings
            $settings_to_update = [
                'site_name' => $_POST['site_name'] ?? '',
                'support_email' => $_POST['support_email'] ?? '',
                'telegram_link' => $_POST['telegram_link'] ?? '',
                'site_logo' => $_POST['site_logo'] ?? get_setting('site_logo'),
                'admin_locale' => $_POST['admin_locale'] ?? 'english',
                'user_locale' => $_POST['user_locale'] ?? 'english',
                'min_withdrawal_amount' => $_POST['min_withdrawal_amount'] ?? '10',
                'min_withdrawal_level' => $_POST['min_withdrawal_level'] ?? '1',
                'max_levels_per_day' => $_POST['max_levels_per_day'] ?? '3',
                'testimonials_display' => $_POST['testimonials_display'] ?? 'both',
                'meta_title' => $_POST['meta_title'] ?? '',
                'meta_description' => $_POST['meta_description'] ?? '',
                'meta_keywords' => $_POST['meta_keywords'] ?? '',
                'og_image' => $_POST['og_image'] ?? get_setting('og_image'),
                'favicon' => $_POST['favicon'] ?? get_setting('favicon'),
                'meta_robots' => $_POST['meta_robots'] ?? 'index, follow'
            ];
            
            foreach ($settings_to_update as $key => $value) {
                update_setting($key, $value);
            }
            
            // Set language in session for immediate effect
            $_SESSION['admin_language'] = $_POST['admin_locale'] ?? 'english';
            $_SESSION['user_language'] = $_POST['user_locale'] ?? 'english';
            
            redirect('settings.php?saved=1');
        }
        
    } catch(PDOException $e) {
        $error = "Failed to update settings: " . $e->getMessage();
    }
}

// Get current settings
$current_settings = get_all_settings();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/global-theme.css">
    <script src="../assets/js/theme.js" defer></script>
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
        
        /* Admin Layout */
        .admin-layout {
            display: flex;
            margin-top: 70px;
            min-height: calc(100vh - 70px);
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--border);
            padding: 20px 0;
            position: fixed;
            top: 70px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 900;
        }
        
        .sidebar-header {
            display: flex;
            align-items: center;
            padding: 0 20px;
            margin-bottom: 30px;
        }
        
        .sidebar-header i {
            font-size: 24px;
            margin-right: 12px;
            color: var(--primary);
        }
        
        .sidebar-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        
        .sidebar-section {
            margin-bottom: 25px;
            padding: 0 20px;
        }
        
        .sidebar-section-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            opacity: 0.6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 2px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: var(--text);
            text-decoration: none;
            border-radius: 0;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }
        
        .sidebar-menu a:hover {
            background: var(--bg);
            color: var(--primary);
        }
        
        .sidebar-menu a.active {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border-left: 3px solid var(--success);
            border-radius: 0 8px 8px 0;
        }
        
        .sidebar-menu i {
            margin-right: 12px;
            width: 16px;
            font-size: 14px;
            text-align: center;
        }
        
        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            height: 70px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 999;
        }
        
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .topbar-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .admin-badge {
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .profile-avatar {
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
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        /* Form Card */
        .form-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            padding: 40px;
        }
        
        /* Section Styles */
        .settings-section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text);
            background: var(--white);
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-control-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text);
            background: var(--white);
            cursor: pointer;
        }
        
        .form-control-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text);
            background: var(--white);
            min-height: 80px;
            resize: vertical;
        }
        
        /* File Upload Styles */
        .file-upload-group {
            margin-bottom: 20px;
        }
        
        .file-preview {
            margin-bottom: 10px;
        }
        
        .file-preview img {
            max-width: 100px;
            max-height: 100px;
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 4px;
        }
        
        .file-input {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
        }
        
        /* Button Styles */
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--success);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: #16a34a;
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            width: 100%;
            max-width: 600px;
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
    </style>
</head>
<body>
    <!-- Topbar Header -->
    <div class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Settings</div>
        </div>
        <div class="topbar-right">
            <div class="admin-badge">ADMIN</div>
            <form class="language-form" method="post" action="../language_action.php">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/settings.php'); ?>">
                <input type="hidden" name="context" value="admin">
                <select name="language" onchange="this.form.submit()">
                    <?php foreach (['english' => 'English', 'chinese' => 'Chinese'] as $code => $label): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($_SESSION['admin_language'] ?? $_SESSION['language'] ?? 'english') === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="topbar-icon theme-toggle" id="themeToggle">
                <i class="fas fa-moon theme-icon" id="themeIcon"></i>
            </div>
            <a href="/handtoglobal/admin/logout.php" style="display:inline-flex;align-items:center;gap:8px;height:34px;padding:0 12px;border-radius:6px;background:#dc2626;color:#fff;text-decoration:none;font-size:13px;font-weight:700;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <div class="profile-info">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Admin Layout -->
    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <?php $site_logo = get_setting('site_logo'); ?>
                <?php if ($site_logo): ?>
                    <img src="../<?php echo $site_logo; ?>" alt="<?php echo get_setting('site_name', 'HandToGlobal'); ?>" style="height: 24px; margin-right: 12px;">
                <?php else: ?>
                    <i class="fas fa-hand-holding-usd"></i>
                <?php endif; ?>
                <h2><?php echo get_setting('site_name', 'HandToGlobal'); ?></h2>
            </div>
            
            <!-- MANAGEMENT Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">MANAGEMENT</div>
                <ul class="sidebar-menu">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    <li><a href="employees.php"><i class="fas fa-user-tie"></i> Employees</a></li>
                </ul>
            </div>
            
            <!-- PLATFORM Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">PLATFORM</div>
                <ul class="sidebar-menu">
                    <li><a href="levels.php"><i class="fas fa-layer-group"></i> Levels</a></li>
                    <li><a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="combos.php"><i class="fas fa-link"></i> Combos</a></li>
                    <li><a href="invitation-codes.php"><i class="fas fa-ticket-alt"></i> InvitationCodes</a></li>
                </ul>
            </div>
            
            <!-- FINANCE Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">FINANCE</div>
                <ul class="sidebar-menu">
                    <li><a href="finance_analysis.php"><i class="fas fa-chart-line"></i> FinanceAnalysis</a></li>
                    <li><a href="withdrawals.php"><i class="fas fa-arrow-up"></i> Withdrawals</a></li>
                </ul>
            </div>
            
            <!-- MONITORING Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">MONITORING</div>
                <ul class="sidebar-menu">
                    <li><a href="contacts.php"><i class="fas fa-address-book"></i> Contacts</a></li>
                    <li><a href="testimonials.php"><i class="fas fa-comments"></i> Testimonials</a></li>
                </ul>
            </div>
            
            <!-- SYSTEM Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">SYSTEM</div>
                <ul class="sidebar-menu">
                    <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="languages.php"><i class="fas fa-language"></i> Languages</a></li>
                    <li><a href="/handtoglobal/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
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
            
            <!-- Form Card -->
            <div class="form-card">
                <form method="POST" enctype="multipart/form-data">
                    
                    <!-- GENERAL Section -->
                    <div class="settings-section">
                        <div class="section-title">GENERAL</div>
                        
                        <div class="form-group">
                            <label class="form-label">SiteName</label>
                            <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($current_settings['site_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">SupportEmail</label>
                            <input type="email" name="support_email" class="form-control" value="<?php echo htmlspecialchars($current_settings['support_email'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">TelegramLink</label>
                            <input type="url" name="telegram_link" class="form-control" value="<?php echo htmlspecialchars($current_settings['telegram_link'] ?? ''); ?>">
                        </div>
                        
                        <div class="file-upload-group">
                            <label class="form-label">SiteLogo</label>
                            <?php if (!empty($current_settings['site_logo'])): ?>
                                <div class="file-preview">
                                    <img src="../<?php echo htmlspecialchars($current_settings['site_logo']); ?>" alt="Current Logo">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="site_logo" class="file-input" accept="image/jpg,image/jpeg,image/png,image/webp,image/svg">
                        </div>
                    </div>
                    
                    <!-- LANGUAGES Section -->
                    <div class="settings-section">
                        <div class="section-title">LANGUAGES</div>
                        
                        <div class="form-group">
                            <label class="form-label">AdminLocale</label>
                            <select name="admin_locale" class="form-control-select">
                                <option value="english" <?php echo ($current_settings['admin_locale'] ?? '') === 'english' ? 'selected' : ''; ?>>English</option>
                                <option value="chinese" <?php echo ($current_settings['admin_locale'] ?? '') === 'chinese' ? 'selected' : ''; ?>>Chinese</option>
                                <option value="german" <?php echo ($current_settings['admin_locale'] ?? '') === 'german' ? 'selected' : ''; ?>>German</option>
                                <option value="greek" <?php echo ($current_settings['admin_locale'] ?? '') === 'greek' ? 'selected' : ''; ?>>Greek</option>
                                <option value="ukrainian" <?php echo ($current_settings['admin_locale'] ?? '') === 'ukrainian' ? 'selected' : ''; ?>>Ukraine</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">UserLocale</label>
                            <select name="user_locale" class="form-control-select">
                                <option value="english" <?php echo ($current_settings['user_locale'] ?? '') === 'english' ? 'selected' : ''; ?>>English</option>
                                <option value="chinese" <?php echo ($current_settings['user_locale'] ?? '') === 'chinese' ? 'selected' : ''; ?>>Chinese</option>
                                <option value="german" <?php echo ($current_settings['user_locale'] ?? '') === 'german' ? 'selected' : ''; ?>>German</option>
                                <option value="greek" <?php echo ($current_settings['user_locale'] ?? '') === 'greek' ? 'selected' : ''; ?>>Greek</option>
                                <option value="ukrainian" <?php echo ($current_settings['user_locale'] ?? '') === 'ukrainian' ? 'selected' : ''; ?>>Ukraine</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- LIMITS Section -->
                    <div class="settings-section">
                        <div class="section-title">LIMITS</div>
                        
                        <div class="form-group">
                            <label class="form-label">MinWithdrawalAmount</label>
                            <input type="number" name="min_withdrawal_amount" class="form-control" value="<?php echo htmlspecialchars($current_settings['min_withdrawal_amount'] ?? '10'); ?>" step="0.01">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">MinWithdrawalLevel</label>
                            <input type="number" name="min_withdrawal_level" class="form-control" value="<?php echo htmlspecialchars($current_settings['min_withdrawal_level'] ?? '1'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">MaxLevelsPerDay</label>
                            <input type="number" name="max_levels_per_day" class="form-control" value="<?php echo htmlspecialchars($current_settings['max_levels_per_day'] ?? '3'); ?>">
                        </div>
                    </div>
                    
                    <!-- TESTIMONIALS Section -->
                    <div class="settings-section">
                        <div class="section-title">TESTIMONIALS</div>
                        
                        <div class="form-group">
                            <label class="form-label">SettingsTestimonialsDisplay</label>
                            <select name="testimonials_display" class="form-control-select">
                                <option value="both" <?php echo ($current_settings['testimonials_display'] ?? '') === 'both' ? 'selected' : ''; ?>>both</option>
                                <option value="homepage" <?php echo ($current_settings['testimonials_display'] ?? '') === 'homepage' ? 'selected' : ''; ?>>homepage</option>
                                <option value="dashboard" <?php echo ($current_settings['testimonials_display'] ?? '') === 'dashboard' ? 'selected' : ''; ?>>dashboard</option>
                                <option value="hidden" <?php echo ($current_settings['testimonials_display'] ?? '') === 'hidden' ? 'selected' : ''; ?>>hidden</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- SEO Section -->
                    <div class="settings-section">
                        <div class="section-title">SEO</div>
                        
                        <div class="form-group">
                            <label class="form-label">MetaTitle</label>
                            <input type="text" name="meta_title" class="form-control" value="<?php echo htmlspecialchars($current_settings['meta_title'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">MetaDescription</label>
                            <textarea name="meta_description" class="form-control-textarea"><?php echo htmlspecialchars($current_settings['meta_description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">MetaKeywords</label>
                            <input type="text" name="meta_keywords" class="form-control" value="<?php echo htmlspecialchars($current_settings['meta_keywords'] ?? ''); ?>">
                        </div>
                        
                        <div class="file-upload-group">
                            <label class="form-label">OgImage</label>
                            <?php if (!empty($current_settings['og_image'])): ?>
                                <div class="file-preview">
                                    <img src="../<?php echo htmlspecialchars($current_settings['og_image']); ?>" alt="Current OG Image">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="og_image" class="file-input" accept="image/jpg,image/jpeg,image/png,image/webp">
                        </div>
                        
                        <div class="file-upload-group">
                            <label class="form-label">Favicon</label>
                            <?php if (!empty($current_settings['favicon'])): ?>
                                <div class="file-preview">
                                    <img src="../<?php echo htmlspecialchars($current_settings['favicon']); ?>" alt="Current Favicon">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="favicon" class="file-input" accept="image/ico,image/png,image/svg">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">MetaRobots</label>
                            <input type="text" name="meta_robots" class="form-control" value="<?php echo htmlspecialchars($current_settings['meta_robots'] ?? 'index, follow'); ?>">
                        </div>
                    </div>
                    
                    <!-- Save Button -->
                    <div class="form-group">
                        <button type="submit" name="update_settings" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
