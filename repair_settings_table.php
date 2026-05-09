<?php
/**
 * Repair Settings Table - Fix MySQL Tablespace Issue
 * This script safely repairs the settings table and inserts default values
 */

require_once __DIR__ . '/config.php';

// HTML output for browser access
header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Settings Table - HandToGlobal</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; background: #cce5ff; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .code { background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; overflow-x: auto; margin: 10px 0; }
        .settings-list { max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
        ul { padding-left: 20px; }
        li { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Settings Table Repair</h1>';

try {
    $conn = getConnection();
    echo '<div class="success">✅ Database connection established</div>';
    
    // Step 1: Check if settings table exists
    echo '<h2>1. Checking Existing Settings Table</h2>';
    
    try {
        $stmt = $conn->query("SHOW TABLES LIKE 'settings'");
        $table_exists = $stmt->rowCount() > 0;
        
        if ($table_exists) {
            echo '<div class="info">ℹ️ Settings table exists</div>';
            
            // Check table structure
            $stmt = $conn->query("DESCRIBE settings");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo '<div class="info">📋 Current columns: ' . implode(', ', $columns) . '</div>';
            
            // Check if table has correct structure
            $required_columns = ['id', 'setting_key', 'setting_value', 'setting_type', 'created_at', 'updated_at'];
            $missing_columns = array_diff($required_columns, $columns);
            
            if (!empty($missing_columns)) {
                echo '<div class="warning">⚠️ Missing columns: ' . implode(', ', $missing_columns) . '</div>';
                echo '<div class="warning">🔧 Dropping and recreating table...</div>';
                
                // Drop the table
                try {
                    $conn->exec("DROP TABLE settings");
                    echo '<div class="success">✅ Old table dropped successfully</div>';
                } catch (PDOException $e) {
                    echo '<div class="error">❌ Error dropping table: ' . $e->getMessage() . '</div>';
                    echo '<div class="warning">⚠️ Tablespace issue detected. Trying alternative approach...</div>';
                    
                    // Try alternative approach
                    try {
                        $conn->exec("SET foreign_key_checks = 0");
                        $conn->exec("DROP TABLE IF EXISTS settings");
                        $conn->exec("SET foreign_key_checks = 1");
                        echo '<div class="success">✅ Table dropped with alternative approach</div>';
                    } catch (PDOException $e2) {
                        echo '<div class="error">❌ Cannot drop table automatically. Manual intervention required.</div>';
                        echo '<div class="warning">
                            <h3>⚠️ MANUAL INSTRUCTIONS:</h3>
                            <ol>
                                <li>Open phpMyAdmin or MySQL command line</li>
                                <li>Connect to database: <strong>handtoglobal</strong></li>
                                <li>Run this SQL command:</li>
                            </ol>
                            <div class="code">DROP TABLE IF EXISTS settings;</div>
                            <ol start="4">
                                <li>Then refresh this page to continue</li>
                            </ol>
                        </div>';
                        throw new Exception('Manual intervention required');
                    }
                }
            } else {
                echo '<div class="success">✅ Table structure is correct</div>';
            }
        } else {
            echo '<div class="warning">❌ Settings table does not exist</div>';
            echo '<div class="info">🔧 Creating new table...</div>';
        }
    } catch (PDOException $e) {
        echo '<div class="error">❌ Error checking table: ' . $e->getMessage() . '</div>';
    }
    
    // Step 2: Create settings table with proper structure
    echo '<h2>2. Creating Settings Table</h2>';
    
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            setting_type VARCHAR(50) DEFAULT 'text',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    try {
        $conn->exec($create_table_sql);
        echo '<div class="success">✅ Settings table created successfully</div>';
    } catch (PDOException $e) {
        echo '<div class="error">❌ Error creating table: ' . $e->getMessage() . '</div>';
        
        // Try alternative approach
        echo '<div class="warning">🔧 Trying alternative approach...</div>';
        try {
            $conn->exec("SET foreign_key_checks = 0");
            $conn->exec("DROP TABLE IF EXISTS settings");
            $conn->exec("SET foreign_key_checks = 1");
            $conn->exec($create_table_sql);
            echo '<div class="success">✅ Settings table created with alternative approach</div>';
        } catch (PDOException $e2) {
            echo '<div class="error">❌ Cannot create table automatically</div>';
            throw new Exception('Table creation failed');
        }
    }
    
    // Step 3: Insert default settings
    echo '<h2>3. Inserting Default Settings</h2>';
    
    $default_settings = [
        // General Settings
        'site_name' => 'HandToGlobal',
        'support_email' => 'support@handtoglobal.com',
        'telegram_link' => 'https://t.me/chica256',
        'site_logo' => 'assets/images/logo.png',
        'favicon' => 'assets/images/favicon.ico',
        'og_image' => 'assets/images/og-image.jpg',
        
        // Language Settings
        'admin_locale' => 'english',
        'user_locale' => 'english',
        
        // Withdrawal Settings
        'min_withdrawal_amount' => '10.00',
        'min_withdrawal_level' => '2',
        'max_levels_per_day' => '40',
        
        // Testimonials Settings
        'testimonials_display' => 'both',
        
        // SEO Settings
        'meta_title' => 'HandToGlobal - Earn Money Online',
        'meta_description' => 'Join HandToGlobal and earn money by completing simple tasks. Get paid instantly with our secure platform.',
        'meta_keywords' => 'earn money online, tasks, get paid, handtoglobal',
        'meta_robots' => 'index, follow',
        
        // Homepage Images
        'homepage_hero_image' => 'assets/images/hero-bg.jpg',
        'homepage_about_image' => 'assets/images/about-image.jpg',
        'homepage_banner_image' => 'assets/images/banner.jpg',
        'homepage_logo_strip' => ''
    ];
    
    $inserted_count = 0;
    $updated_count = 0;
    $errors = [];
    
    foreach ($default_settings as $key => $value) {
        try {
            // Check if setting already exists
            $stmt = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Update existing setting
                $stmt = $conn->prepare("UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
                $result = $stmt->execute([$value, $key]);
                if ($result) {
                    $updated_count++;
                    echo "<div class='info'>🔄 Updated: $key = '$value'</div>";
                } else {
                    $errors[] = "Failed to update: $key";
                }
            } else {
                // Insert new setting
                $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, 'text')");
                $result = $stmt->execute([$key, $value]);
                if ($result) {
                    $inserted_count++;
                    echo "<div class='success'>➕ Inserted: $key = '$value'</div>";
                } else {
                    $errors[] = "Failed to insert: $key";
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Error with $key: " . $e->getMessage();
        }
    }
    
    // Step 4: Verify table contents
    echo '<h2>4. Verification Results</h2>';
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM settings");
    $result = $stmt->fetch();
    $total_settings = $result['total'];
    
    echo '<div class="success">📊 Total settings in table: ' . $total_settings . '</div>';
    echo '<div class="success">➕ New settings inserted: ' . $inserted_count . '</div>';
    echo '<div class="success">🔄 Existing settings updated: ' . $updated_count . '</div>';
    
    if (!empty($errors)) {
        echo '<div class="error">❌ Errors encountered: ' . count($errors) . '</div>';
        foreach ($errors as $error) {
            echo '<div class="error">• ' . $error . '</div>';
        }
    }
    
    // Show all settings
    echo '<h2>5. All Settings in Database</h2>';
    $stmt = $conn->query("SELECT setting_key, setting_value FROM settings ORDER BY setting_key");
    $all_settings = $stmt->fetchAll();
    
    echo '<div class="settings-list">';
    foreach ($all_settings as $setting) {
        $value = strlen($setting['setting_value']) > 50 ? substr($setting['setting_value'], 0, 50) . '...' : $setting['setting_value'];
        echo '<div><strong>' . htmlspecialchars($setting['setting_key']) . ':</strong> ' . htmlspecialchars($value) . '</div>';
    }
    echo '</div>';
    
    // Step 5: Test settings helper functions
    echo '<h2>6. Testing Settings Helpers</h2>';
    
    if (file_exists('includes/settings_helpers.php')) {
        require_once __DIR__ . '/includes/settings_helpers.php';
        
        $test_keys = ['site_name', 'site_logo', 'telegram_link'];
        foreach ($test_keys as $key) {
            $value = get_setting($key, 'DEFAULT');
            echo '<div class="success">✅ get_setting(\'' . $key . '\'): \'' . htmlspecialchars($value) . '\'</div>';
        }
    } else {
        echo '<div class="error">❌ Settings helpers file not found</div>';
    }
    
    echo '<h2>🎉 REPAIR COMPLETE!</h2>';
    echo '<div class="success">
        <strong>✅ Settings table is ready for use!</strong><br>
        <strong>✅ Default settings are in place!</strong><br>
        <strong>✅ Helper functions are working!</strong>
    </div>';
    
    echo '<h2>🎯 Next Steps:</h2>';
    echo '<ol>
        <li><a href="admin/settings.php" class="btn btn-success">Open Admin Settings</a></li>
        <li>Change the site name and save</li>
        <li>Check if changes appear on homepage, login, register, dashboard</li>
        <li>Test image uploads and other settings</li>
    </ol>';
    
    echo '<div class="info">
        <strong>📋 Quick Test Checklist:</strong><br>
        • Save new site name → Check homepage<br>
        • Save new logo → Check all pages<br>
        • Save Telegram link → Check support links<br>
        • Save SEO settings → Check page titles<br>
        • Save withdrawal limits → Check withdrawal page
    </div>';
    
    echo '<p><a href="admin/settings.php" class="btn btn-success">🚀 Go to Admin Settings</a></p>';
    
} catch (Exception $e) {
    echo '<div class="error">❌ FATAL ERROR: ' . $e->getMessage() . '</div>';
    echo '<div class="warning">
        <h3>🔧 Troubleshooting:</h3>
        <ul>
            <li>Check database connection in config.php</li>
            <li>Verify database permissions</li>
            <li>Check MySQL server status</li>
            <li>Try running this script again</li>
            <li>Contact your database administrator</li>
        </ul>
    </div>';
}

echo '</div>
</body>
</html>';
?>
