<?php
require_once '../config.php';
require_once '../includes/settings_helpers.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection

// Initialize variables
$msg = '';
$error = '';

// Handle success message from redirect
if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $msg = 'Settings updated successfully!';
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // TEXT SETTINGS
    $fields = [
        'site_name',
        'support_email',
        'telegram_link',
        'admin_locale',
        'user_locale',
        'min_withdrawal_amount',
        'min_withdrawal_level',
        'max_levels_per_day',
        'testimonials_display',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'meta_robots',
        'privacy_policy_content',
        'terms_content'
    ];

    foreach ($fields as $field) {
        update_setting($field, $_POST[$field] ?? '');
    }

    // IMAGE UPLOADS
    $uploadDir = __DIR__ . '/../uploads/settings/';
    $uploadUrl = 'uploads/settings/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $imageFields = [
        'site_logo',
        'favicon',
        'og_image',
        'homepage_hero_image',
        'homepage_about_image',
        'homepage_banner_image'
    ];

    foreach ($imageFields as $field) {

        if (!empty($_FILES[$field]['name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {

            $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
            $allowed = $field === 'favicon'
                ? ['ico', 'png', 'jpg', 'jpeg', 'webp']
                : ['jpg','jpeg','png','webp','gif','ico'];

            if (in_array($ext, $allowed)) {

                $filename = $field . '_' . time() . '.' . $ext;
                $target = $uploadDir . $filename;

                if (move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
                    update_setting($field, $uploadUrl . $filename);
                    if ($field === 'favicon') {
                        update_setting('site_favicon', $uploadUrl . $filename);
                    }
                }
            }
        }
    }

    header("Location: settings.php?saved=1");
    exit;
}
        
          

// Get current settings using new helper functions
$current_settings = [
    'site_name' => get_setting('site_name', 'HandToGlobal'),
    'support_email' => get_setting('support_email', 'support@handtoglobal.com'),
    'telegram_link' => get_setting('telegram_link', 'https://t.me/chica256'),
    'site_logo' => get_setting('site_logo', 'assets/images/logo.png'),
    'favicon' => get_setting('site_favicon', get_setting('favicon', 'assets/images/favicon.ico')),
    'og_image' => get_setting('og_image', 'assets/images/og-image.jpg'),
    'admin_locale' => get_setting('admin_locale', 'english'),
    'user_locale' => get_setting('user_locale', 'english'),
    'min_withdrawal_amount' => get_setting('min_withdrawal_amount', '10.00'),
    'min_withdrawal_level' => get_setting('min_withdrawal_level', '2'),
    'max_levels_per_day' => get_setting('max_levels_per_day', '40'),
    'testimonials_display' => get_setting('testimonials_display', 'both'),
    'meta_title' => get_setting('meta_title', 'HandToGlobal - Earn Money Online'),
    'meta_description' => get_setting('meta_description', 'Join HandToGlobal and earn money by completing simple tasks.'),
    'meta_keywords' => get_setting('meta_keywords', 'earn money online, tasks, get paid'),
    'meta_robots' => get_setting('meta_robots', 'index, follow'),
    'homepage_hero_image' => get_setting('homepage_hero_image', 'assets/images/hero-bg.jpg'),
    'homepage_about_image' => get_setting('homepage_about_image', 'assets/images/about-image.jpg'),
    'homepage_banner_image' => get_setting('homepage_banner_image', 'assets/images/banner.jpg'),
    'homepage_logo_strip' => get_setting('homepage_logo_strip', ''),
    'privacy_policy_content' => get_setting('privacy_policy_content', ''),
    'terms_content' => get_setting('terms_content', '')
];
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
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    
    <!-- Admin Layout -->
    <div class="admin-layout">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <?php if (!empty($msg)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
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
                            <input type="file" name="favicon" class="file-input" accept="image/x-icon,image/vnd.microsoft.icon,image/png,image/jpeg,image/webp,.ico,.png,.jpg,.jpeg,.webp">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">MetaRobots</label>
                            <input type="text" name="meta_robots" class="form-control" value="<?php echo htmlspecialchars($current_settings['meta_robots'] ?? 'index, follow'); ?>">
                        </div>
                    </div>
                    
                    <!-- HOMEPAGE IMAGES Section -->
                    <div class="settings-section">
                        <div class="section-title">HOMEPAGE IMAGES</div>
                        
                        <div class="form-group">
                            <label class="form-label">HomepageHeroImage</label>
                            <?php if (!empty($current_settings['homepage_hero_image'])): ?>
                                <div class="file-preview">
                                    <img src="../<?php echo htmlspecialchars($current_settings['homepage_hero_image']); ?>" alt="Current Homepage Hero Image">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="homepage_hero_image" class="file-input" accept="image/jpg,image/jpeg,image/png,image/webp,image/gif">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">HomepageAboutImage</label>
                            <?php if (!empty($current_settings['homepage_about_image'])): ?>
                                <div class="file-preview">
                                    <img src="../<?php echo htmlspecialchars($current_settings['homepage_about_image']); ?>" alt="Current Homepage About Image">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="homepage_about_image" class="file-input" accept="image/jpg,image/jpeg,image/png,image/webp,image/gif">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">HomepageBannerImage</label>
                            <?php if (!empty($current_settings['homepage_banner_image'])): ?>
                                <div class="file-preview">
                                    <img src="../<?php echo htmlspecialchars($current_settings['homepage_banner_image']); ?>" alt="Current Homepage Banner Image">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="homepage_banner_image" class="file-input" accept="image/jpg,image/jpeg,image/png,image/webp,image/gif">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">HomepageLogoStripImages</label>
                            <textarea name="homepage_logo_strip" class="form-control-textarea" placeholder="Enter logo strip URLs or descriptions, one per line"><?php echo htmlspecialchars($current_settings['homepage_logo_strip'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- LEGAL Section -->
                    <div class="settings-section">
                        <div class="section-title">LEGAL PAGES</div>
                        
                        <div class="form-group">
                            <label class="form-label">Privacy Policy Content</label>
                            <textarea name="privacy_policy_content" class="form-control-textarea" placeholder="Leave blank to use the default Privacy Policy"><?php echo htmlspecialchars($current_settings['privacy_policy_content'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Terms of Service Content</label>
                            <textarea name="terms_content" class="form-control-textarea" placeholder="Leave blank to use the default Terms of Service"><?php echo htmlspecialchars($current_settings['terms_content'] ?? ''); ?></textarea>
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
