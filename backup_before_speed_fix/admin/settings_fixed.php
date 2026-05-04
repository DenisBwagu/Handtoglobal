<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
}

// Get database connection
$conn = getConnection();

$msg = "";
$error = "";

// Handle settings update
if (isset($_POST['update_settings'])) {
    try {
        // Update general settings
        $general_settings = [
            'site_name' => $_POST['site_name'] ?? 'HandToGlobal',
            'site_description' => $_POST['site_description'] ?? '',
            'admin_email' => $_POST['admin_email'] ?? '',
            'support_email' => $_POST['support_email'] ?? '',
            'telegram_support_link' => $_POST['telegram_support_link'] ?? '',
            'whatsapp_support_link' => $_POST['whatsapp_support_link'] ?? ''
        ];
        
        foreach ($general_settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        
        // Update financial settings
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
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
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
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
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

// Helper function to get setting value
function getSetting($key, $default = '') {
    global $current_settings;
    return $current_settings[$key] ?? $default;
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
                    <a href="/handtoglobal/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
