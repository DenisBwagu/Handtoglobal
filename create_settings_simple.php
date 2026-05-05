<?php
/**
 * Create Settings Table - Simple
 * This script creates the settings table with proper error handling
 */

require_once 'config.php';

try {
    $conn = getConnection();
    
    // Check if settings table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'settings'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "ℹ️ Settings table already exists. Dropping and recreating...\n";
        $conn->exec("DROP TABLE settings");
    }
    
    // Create settings table
    $createTableSQL = "
        CREATE TABLE settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT NULL,
            setting_type VARCHAR(50) DEFAULT 'text',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $conn->exec($createTableSQL);
    
    // Insert default settings
    $defaultSettings = [
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
    
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?)");
    
    foreach ($defaultSettings as $key => $value) {
        $stmt->execute([$key, $value, 'text']);
    }
    
    echo "✅ Settings table created successfully\n";
    echo "✅ " . count($defaultSettings) . " default settings inserted\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
