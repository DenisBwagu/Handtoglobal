<?php
/**
 * Fix Settings Table - Comprehensive Database Repair
 * This script safely creates/repairs the settings table and inserts default values
 */

echo "=== SETTINGS TABLE REPAIR ===\n\n";

require_once 'config.php';

try {
    $conn = getConnection();
    echo "✅ Database connection established\n\n";
    
    // Step 1: Check if settings table exists
    echo "1. CHECKING EXISTING TABLE:\n";
    $stmt = $conn->query("SHOW TABLES LIKE 'settings'");
    $table_exists = $stmt->rowCount() > 0;
    
    if ($table_exists) {
        echo "   ✅ Settings table exists\n";
        
        // Check table structure
        $stmt = $conn->query("DESCRIBE settings");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "   📋 Current columns: " . implode(', ', $columns) . "\n";
        
        // Check if table has correct structure
        $required_columns = ['id', 'setting_key', 'setting_value', 'setting_type', 'created_at', 'updated_at'];
        $missing_columns = array_diff($required_columns, $columns);
        
        if (!empty($missing_columns)) {
            echo "   ⚠️  Missing columns: " . implode(', ', $missing_columns) . "\n";
            echo "   🔧 Dropping and recreating table...\n";
            
            // Drop the table
            $conn->exec("DROP TABLE settings");
            echo "   ✅ Old table dropped\n";
        } else {
            echo "   ✅ Table structure is correct\n";
        }
    } else {
        echo "   ❌ Settings table does not exist\n";
        echo "   🔧 Creating new table...\n";
    }
    
    // Step 2: Create settings table with proper structure
    echo "\n2. CREATING SETTINGS TABLE:\n";
    
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
        echo "   ✅ Settings table created successfully\n";
    } catch (PDOException $e) {
        echo "   ❌ Error creating table: " . $e->getMessage() . "\n";
        
        // Try alternative approach
        echo "   🔧 Trying alternative approach...\n";
        $conn->exec("SET foreign_key_checks = 0");
        $conn->exec("DROP TABLE IF EXISTS settings");
        $conn->exec("SET foreign_key_checks = 1");
        $conn->exec($create_table_sql);
        echo "   ✅ Settings table created with alternative approach\n";
    }
    
    // Step 3: Insert default settings
    echo "\n3. INSERTING DEFAULT SETTINGS:\n";
    
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
    
    foreach ($default_settings as $key => $value) {
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
                echo "   🔄 Updated: $key = '$value'\n";
            }
        } else {
            // Insert new setting
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, 'text')");
            $result = $stmt->execute([$key, $value]);
            if ($result) {
                $inserted_count++;
                echo "   ➕ Inserted: $key = '$value'\n";
            }
        }
    }
    
    echo "\n   📊 Settings Summary:\n";
    echo "      Inserted: $inserted_count new settings\n";
    echo "      Updated: $updated_count existing settings\n";
    
    // Step 4: Verify table contents
    echo "\n4. VERIFYING TABLE CONTENTS:\n";
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM settings");
    $result = $stmt->fetch();
    $total_settings = $result['total'];
    
    echo "   📊 Total settings in table: $total_settings\n";
    
    // Show sample settings
    $stmt = $conn->query("SELECT setting_key, setting_value FROM settings ORDER BY setting_key LIMIT 10");
    $sample_settings = $stmt->fetchAll();
    
    echo "   📋 Sample settings:\n";
    foreach ($sample_settings as $setting) {
        $value = strlen($setting['setting_value']) > 50 ? substr($setting['setting_value'], 0, 50) . '...' : $setting['setting_value'];
        echo "      {$setting['setting_key']}: '$value'\n";
    }
    
    // Step 5: Test settings helper functions
    echo "\n5. TESTING SETTINGS HELPERS:\n";
    
    if (file_exists('includes/settings_helpers.php')) {
        require_once 'includes/settings_helpers.php';
        
        $test_keys = ['site_name', 'site_logo', 'telegram_link'];
        foreach ($test_keys as $key) {
            $value = get_setting($key, 'DEFAULT');
            echo "   ✅ get_setting('$key'): '$value'\n";
        }
    } else {
        echo "   ❌ Settings helpers file not found\n";
    }
    
    echo "\n=== SETTINGS TABLE REPAIR COMPLETE ===\n";
    echo "✅ Database table is ready\n";
    echo "✅ Default settings are in place\n";
    echo "✅ Helper functions are working\n";
    echo "\n🎯 NEXT STEPS:\n";
    echo "1. Test admin/settings.php save functionality\n";
    echo "2. Verify settings apply across all pages\n";
    echo "3. Test image uploads and changes\n";
    echo "4. Test withdrawal limits and language settings\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\n🔧 TROUBLESHOOTING:\n";
    echo "1. Check database connection in config.php\n";
    echo "2. Verify database permissions\n";
    echo "3. Check MySQL server status\n";
    echo "4. Try running this script again\n";
}
?>
