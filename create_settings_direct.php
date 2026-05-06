<?php
/**
 * Create Settings Table - Direct MySQL
 * This script uses raw MySQL to bypass tablespace issues
 */

require_once __DIR__ . '/config.php';

try {
    $conn = getConnection();
    
    // Disable foreign key checks temporarily
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Drop table if exists
    $conn->exec("DROP TABLE IF EXISTS settings");
    
    // Create settings table
    $conn->exec("
        CREATE TABLE settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Re-enable foreign key checks
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    
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
        'meta_description' => 'Join HandToGlobal and earn money by completing simple tasks.',
        'meta_keywords' => 'earn money online, tasks, get paid',
        'meta_robots' => 'index, follow',
        'homepage_hero_image' => 'assets/images/hero-bg.jpg',
        'homepage_about_image' => 'assets/images/about-image.jpg',
        'homepage_banner_image' => 'assets/images/banner.jpg',
        'homepage_logo_strip' => ''
    ];
    
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
    
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    
    echo "✅ " . count($defaults) . " default settings inserted\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
