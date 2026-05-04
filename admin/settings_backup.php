<?php
require_once '../config.php';
require_once '../get_setting.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('admin_login.php');
}

// Get database connection
$conn = getConnection();

$msg = "";
$error = "";

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
            
            $msg = "Settings updated successfully!";
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
                <i class="fas fa-hand-holding-usd"></i>
                <h2>Hand to Global</h2>
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
        $financial_settings = [
            'minimum_withdrawal' => $_POST['minimum_withdrawal'] ?? '100',
            'maximum_withdrawal' => $_POST['maximum_withdrawal'] ?? '10000',
            'withdrawal_fee' => $_POST['withdrawal_fee'] ?? '0',
            'daily_task_limit' => $_POST['daily_task_limit'] ?? '40',
            'bronze_unlock_amount' => $_POST['bronze_unlock_amount'] ?? '100',
            'silver_unlock_amount' => $_POST['silver_unlock_amount'] ?? '150',
            'gold_unlock_amount' => $_POST['gold_unlock_amount'] ?? '250',
            'platinum_unlock_amount' => $_POST['platinum_unlock_amount'] ?? '500',
            'bronze_reward' => $_POST['bronze_reward'] ?? '1.80',
            'silver_reward' => $_POST['silver_reward'] ?? '2.50',
            'gold_reward' => $_POST['gold_reward'] ?? '3.50',
            'platinum_reward' => $_POST['platinum_reward'] ?? '5.00'
        ];
        
        foreach ($financial_settings as $key => $value) {
            // Check if setting exists
            $stmt = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            
            if ($stmt->fetch()) {
                // Update existing setting
                $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            } else {
                // Insert new setting
                $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }
        
        // Update system settings
        $system_settings = [
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
            'registration_enabled' => isset($_POST['registration_enabled']) ? '1' : '0',
            'email_verification_required' => isset($_POST['email_verification_required']) ? '1' : '0',
            'referral_bonus_enabled' => isset($_POST['referral_bonus_enabled']) ? '1' : '0',
            'referral_bonus_amount' => $_POST['referral_bonus_amount'] ?? '5',
            'max_upload_size' => $_POST['max_upload_size'] ?? '5',
            'allowed_file_types' => $_POST['allowed_file_types'] ?? 'jpg,jpeg,png,pdf,doc,docx',
            'session_timeout' => $_POST['session_timeout'] ?? '24',
            'password_min_length' => $_POST['password_min_length'] ?? '8',
            'login_attempts_limit' => $_POST['login_attempts_limit'] ?? '5'
        ];
        
        foreach ($system_settings as $key => $value) {
            // Check if setting exists
            $stmt = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            
            if ($stmt->fetch()) {
                // Update existing setting
                $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            } else {
                // Insert new setting
                $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }
        
        $msg = "Settings updated successfully!";
        
    } catch(PDOException $e) {
        $error = "Failed to update settings: " . $e->getMessage();
    }
}

// Get current settings
$current_settings = [];
try {
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings");
    $stmt->execute();
    $settings_data = $stmt->fetchAll();
    
    foreach ($settings_data as $setting) {
        $current_settings[$setting['setting_key']] = $setting['setting_value'];
    }
} catch(PDOException $e) {
    $error = "Failed to fetch settings: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - HandToGlobal Admin</title>
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
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
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
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .tab:hover {
            color: #667eea;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .setting-section {
            margin-bottom: 30px;
        }
        
        .setting-section h3 {
            color: #667eea;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .number-input {
            display: flex;
            align-items: center;
        }
        
        .number-input input {
            border-radius: 5px 0 0 5px;
            border-right: none;
        }
        
        .number-input .suffix {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-left: none;
            border-radius: 0 5px 5px 0;
            padding: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav-menu">
                <h1><i class="fas fa-cog"></i> System Settings</h1>
                <div class="nav-links">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
                    <a href="combos.php"><i class="fas fa-layer-group"></i> Combos</a>
                    <a href="invitation-codes.php"><i class="fas fa-ticket-alt"></i> Codes</a>
                    <a href="finance-analysis.php"><i class="fas fa-chart-line"></i> Finance</a>
                    <a href="deposits.php"><i class="fas fa-dollar-sign"></i> Deposits</a>
                    <a href="withdrawals.php"><i class="fas fa-money-bill-wave"></i> Withdrawals</a>
                    <a href="contacts.php"><i class="fas fa-envelope"></i> Contacts</a>
                    <a href="testimonials.php"><i class="fas fa-quote-left"></i> Testimonials</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="languages.php"><i class="fas fa-language"></i> Languages</a>
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

        <div class="card">
            <div class="card-header">
                <h2>System Configuration</h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="update_settings" value="1">
                
                <!-- Tabs -->
                <div class="tabs">
                    <button type="button" class="tab active" onclick="showTab('general')">
                        <i class="fas fa-info-circle"></i> General
                    </button>
                    <button type="button" class="tab" onclick="showTab('financial')">
                        <i class="fas fa-dollar-sign"></i> Financial
                    </button>
                    <button type="button" class="tab" onclick="showTab('system')">
                        <i class="fas fa-cogs"></i> System
                    </button>
                    <button type="button" class="tab" onclick="showTab('security')">
                        <i class="fas fa-shield-alt"></i> Security
                    </button>
                </div>
                
                <!-- General Settings Tab -->
                <div id="general" class="tab-content active">
                    <div class="setting-section">
                        <h3>Site Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="site_name">Site Name</label>
                                <input type="text" id="site_name" name="site_name" class="form-control" 
                                       value="<?php echo getSetting('site_name', 'HandToGlobal'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="admin_email">Admin Email</label>
                                <input type="email" id="admin_email" name="admin_email" class="form-control" 
                                       value="<?php echo getSetting('admin_email'); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="site_description">Site Description</label>
                            <textarea id="site_description" name="site_description" class="form-control"><?php 
                                echo getSetting('site_description'); 
                            ?></textarea>
                        </div>
                    </div>
                    
                    <div class="setting-section">
                        <h3>Support Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="support_email">Support Email</label>
                                <input type="email" id="support_email" name="support_email" class="form-control" 
                                       value="<?php echo getSetting('support_email'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="telegram_support_link">Telegram Support Link</label>
                                <input type="url" id="telegram_support_link" name="telegram_support_link" class="form-control" 
                                       value="<?php echo getSetting('telegram_support_link'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="whatsapp_support_link">WhatsApp Support Link</label>
                                <input type="url" id="whatsapp_support_link" name="whatsapp_support_link" class="form-control" 
                                       value="<?php echo getSetting('whatsapp_support_link'); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Financial Settings Tab -->
                <div id="financial" class="tab-content">
                    <div class="setting-section">
                        <h3>Withdrawal Settings</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="minimum_withdrawal">Minimum Withdrawal</label>
                                <div class="number-input">
                                    <input type="number" id="minimum_withdrawal" name="minimum_withdrawal" class="form-control" 
                                           step="0.01" min="0" value="<?php echo getSetting('minimum_withdrawal', '100'); ?>">
                                    <span class="suffix">USDT</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="maximum_withdrawal">Maximum Withdrawal</label>
                                <div class="number-input">
                                    <input type="number" id="maximum_withdrawal" name="maximum_withdrawal" class="form-control" 
                                           step="0.01" min="0" value="<?php echo getSetting('maximum_withdrawal', '10000'); ?>">
                                    <span class="suffix">USDT</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="withdrawal_fee">Withdrawal Fee</label>
                                <div class="number-input">
                                    <input type="number" id="withdrawal_fee" name="withdrawal_fee" class="form-control" 
                                           step="0.01" min="0" value="<?php echo getSetting('withdrawal_fee', '0'); ?>">
                                    <span class="suffix">USDT</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="setting-section">
                        <h3>Task Settings</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="daily_task_limit">Daily Task Limit</label>
                                <input type="number" id="daily_task_limit" name="daily_task_limit" class="form-control" 
                                       min="1" value="<?php echo getSetting('daily_task_limit', '40'); ?>">
                                <small class="help-text">Maximum tasks a user can complete per day</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="setting-section">
                        <h3>Level Rewards</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="bronze_reward">Bronze Reward</label>
                                <div class="number-input">
                                    <input type="number" id="bronze_reward" name="bronze_reward" class="form-control" 
                                           step="0.01" min="0" value="<?php echo getSetting('bronze_reward', '1.80'); ?>">
                                    <span class="suffix">USDT</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="silver_reward">Silver Reward</label>
                                <div class="number-input">
                                    <input type="number" id="silver_reward" name="silver_reward" class="form-control" 
                                           step="0.01" min="0" value="<?php echo getSetting('silver_reward', '2.50'); ?>">
                                    <span class="suffix">USDT</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="gold_reward">Gold Reward</label>
                                <div class="number-input">
                                    <input type="number" id="gold_reward" name="gold_reward" class="form-control" 
                                           step="0.01" min="0" value="<?php echo getSetting('gold_reward', '3.50'); ?>">
                                    <span class="suffix">USDT</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="platinum_reward">Platinum Reward</label>
                                <div class="number-input">
                                    <input type="number" id="platinum_reward" name="platinum_reward" class="form-control" 
                                           step="0.01" min="0" value="<?php echo getSetting('platinum_reward', '5.00'); ?>">
                                    <span class="suffix">USDT</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="setting-section">
                        <h3>Level Unlock Amounts</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="bronze_unlock_amount">Bronze Unlock Amount</label>
                                <div class="number-input">
                                    <input type="number" id="bronze_unlock_amount" name="bronze_unlock_amount" class="form-control" 
                                           step="0.01" min="0" value="<?php echo getSetting('bronze_unlock_amount', '100'); ?>">
                                    <span class="suffix">USDT</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="silver_unlock_amount">Silver Unlock Amount</label>
                                <div class="number-input">
                                    <input type="number" id="silver_unlock_amount" name="silver_unlock_amount" class="form-control" 
                                           step="0.01" min="0" value="<?php echo getSetting('silver_unlock_amount', '150'); ?>">
                                    <span class="suffix">USDT</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="gold_unlock_amount">Gold Unlock Amount</label>
                                <div class="number-input">
                                    <input type="number" id="gold_unlock_amount" name="gold_unlock_amount" class="form-control" 
                                           step="0.01" min="0" value="<?php echo getSetting('gold_unlock_amount', '250'); ?>">
                                    <span class="suffix">USDT</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="platinum_unlock_amount">Platinum Unlock Amount</label>
                                <div class="number-input">
                                    <input type="number" id="platinum_unlock_amount" name="platinum_unlock_amount" class="form-control" 
                                           step="0.01" min="0" value="<?php echo getSetting('platinum_unlock_amount', '500'); ?>">
                                    <span class="suffix">USDT</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System Settings Tab -->
                <div id="system" class="tab-content">
                    <div class="setting-section">
                        <h3>System Status</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                           <?php echo getSetting('maintenance_mode') == '1' ? 'checked' : ''; ?>>
                                    <label for="maintenance_mode">Maintenance Mode</label>
                                </div>
                                <small class="help-text">Disable user access to the site</small>
                            </div>
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="registration_enabled" name="registration_enabled" 
                                           <?php echo getSetting('registration_enabled') != '0' ? 'checked' : ''; ?>>
                                    <label for="registration_enabled">Enable Registration</label>
                                </div>
                                <small class="help-text">Allow new user registrations</small>
                            </div>
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="email_verification_required" name="email_verification_required" 
                                           <?php echo getSetting('email_verification_required') == '1' ? 'checked' : ''; ?>>
                                    <label for="email_verification_required">Email Verification Required</label>
                                </div>
                                <small class="help-text">Require email verification for new accounts</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="setting-section">
                        <h3>Referral System</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="referral_bonus_enabled" name="referral_bonus_enabled" 
                                           <?php echo getSetting('referral_bonus_enabled') == '1' ? 'checked' : ''; ?>>
                                    <label for="referral_bonus_enabled">Enable Referral Bonus</label>
                                </div>
                                <small class="help-text">Give bonus to users who refer others</small>
                            </div>
                            <div class="form-group">
                                <label for="referral_bonus_amount">Referral Bonus Amount</label>
                                <div class="number-input">
                                    <input type="number" id="referral_bonus_amount" name="referral_bonus_amount" class="form-control" 
                                           step="0.01" min="0" value="<?php echo getSetting('referral_bonus_amount', '5'); ?>">
                                    <span class="suffix">USDT</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="setting-section">
                        <h3>File Upload Settings</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="max_upload_size">Maximum Upload Size</label>
                                <div class="number-input">
                                    <input type="number" id="max_upload_size" name="max_upload_size" class="form-control" 
                                           min="1" value="<?php echo getSetting('max_upload_size', '5'); ?>">
                                    <span class="suffix">MB</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="allowed_file_types">Allowed File Types</label>
                                <input type="text" id="allowed_file_types" name="allowed_file_types" class="form-control" 
                                       value="<?php echo getSetting('allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx'); ?>">
                                <small class="help-text">Comma-separated list of file extensions</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security Settings Tab -->
                <div id="security" class="tab-content">
                    <div class="setting-section">
                        <h3>Session Security</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="session_timeout">Session Timeout</label>
                                <div class="number-input">
                                    <input type="number" id="session_timeout" name="session_timeout" class="form-control" 
                                           min="1" value="<?php echo getSetting('session_timeout', '24'); ?>">
                                    <span class="suffix">hours</span>
                                </div>
                                <small class="help-text">How long users stay logged in</small>
                            </div>
                            <div class="form-group">
                                <label for="password_min_length">Minimum Password Length</label>
                                <input type="number" id="password_min_length" name="password_min_length" class="form-control" 
                                       min="4" value="<?php echo getSetting('password_min_length', '8'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="login_attempts_limit">Login Attempts Limit</label>
                                <input type="number" id="login_attempts_limit" name="login_attempts_limit" class="form-control" 
                                       min="1" value="<?php echo getSetting('login_attempts_limit', '5'); ?>">
                                <small class="help-text">Maximum failed login attempts before lockout</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #f0f0f0;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.reload()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = [
                'site_name',
                'minimum_withdrawal',
                'maximum_withdrawal',
                'daily_task_limit'
            ];
            
            let isValid = true;
            
            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    isValid = false;
                } else {
                    field.style.borderColor = '';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill all required fields');
            }
        });
        
        // Number input validation
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
            });
        });
    </script>
</body>
</html>
