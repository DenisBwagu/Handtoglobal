<?php
/**
 * Create Settings Table - Final Approach
 * This script handles the tablespace issue by using a different method
 */

require_once 'config.php';

try {
    $conn = getConnection();
    
    // First, try to drop the table completely
    try {
        $conn->exec("DROP TABLE IF EXISTS settings");
        echo "ℹ️ Attempted to drop settings table\n";
    } catch (Exception $e) {
        echo "ℹ️ Could not drop table: " . $e->getMessage() . "\n";
    }
    
    // Wait a moment for MySQL to process
    usleep(100000); // 0.1 second
    
    // Create table with minimal structure first
    $createSQL = "
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    $conn->exec($createSQL);
    echo "✅ Settings table created successfully\n";
    
    // Insert default settings
    $defaults = [
        'site_name' => 'HandToGlobal',
        'support_email' => 'support@handtoglobal.com',
        'telegram_link' => 'https://t.me/chica256',
        'site_logo' => 'assets/images/logo.png',
        'favicon' => 'assets/images/favicon.ico',
        'og_image' => 'assets/images/og-image.jpg',
        'admin_locale' => 'english',
        'user_locale' => 'english',
        'min_withdrawal_amount' => '10.00',
        'min_withdrawal_level' => '2',
        'max_levels_per_day' => '40',
        'testimonials_display' => 'both',
        'meta_title' => 'HandToGlobal - Earn Money Online',
        'meta_description' => 'Join HandToGlobal and earn money by completing simple tasks. Get paid instantly with our secure platform.',
        'meta_keywords' => 'earn money online, tasks, get paid, handtoglobal',
        'meta_robots' => 'index, follow',
        'homepage_hero_image' => 'assets/images/hero-bg.jpg',
        'homepage_about_image' => 'assets/images/about-image.jpg',
        'homepage_banner_image' => 'assets/images/banner.jpg',
        'homepage_logo_strip' => ''
    ];
    
    $stmt = $conn->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    
    echo "✅ " . count($defaults) . " default settings inserted\n";
    echo "✅ Settings system is ready\n";
    
    // Test the settings helpers
    require_once 'includes/settings_helpers.php';
    
    echo "\n🧪 Testing settings helpers:\n";
    echo "Site name: " . get_site_name() . "\n";
    echo "Telegram link: " . get_telegram_link() . "\n";
    echo "Site logo: " . get_site_logo() . "\n";
    echo "Meta title: " . get_meta_title() . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
