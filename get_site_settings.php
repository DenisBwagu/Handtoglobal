<?php
require_once 'config.php';

function getSiteSettings() {
    static $settings = null;
    
    if ($settings !== null) {
        return $settings;
    }
    
    try {
        $conn = getConnection();
        
        // Create settings table if it doesn't exist
        $conn->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(255) NOT NULL UNIQUE,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Get all settings
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Default settings
        $default_settings = [
            'site_name' => 'HandToGlobal',
            'site_description' => 'Professional Task Platform',
            'admin_email' => 'admin@handtoglobal.com',
            'support_email' => 'support@handtoglobal.com',
            'TelegramLink' => 'https://t.me/handtoglobal_support',
            'whatsapp_support_link' => 'https://wa.me/1234567890',
            'MaxLevelsPerDay' => '3',
            'TasksPerLevel' => '40',
            'DailyTaskLimit' => '40',
            'appearance_mode' => 'light',
            'theme_primary' => '#4f46e5',
            'theme_secondary' => '#7c3aed',
            'theme_sidebar' => '#101828',
            'theme_background' => '#0f172a',
            'theme_surface' => '#111827',
            'theme_text' => '#f8fafc',
            'theme_radius' => '16px',
            'theme_shadow' => '0 10px 30px rgba(0,0,0,.5)'
        ];
        
        // Merge with database settings
        $settings = array_merge($default_settings, $results);
        
        return $settings;
        
    } catch(PDOException $e) {
        // Return default settings on database error
        return [
            'site_name' => 'HandToGlobal',
            'site_description' => 'Professional Task Platform',
            'admin_email' => 'admin@handtoglobal.com',
            'support_email' => 'support@handtoglobal.com',
            'TelegramLink' => 'https://t.me/handtoglobal_support',
            'whatsapp_support_link' => 'https://wa.me/1234567890',
            'MaxLevelsPerDay' => '3',
            'TasksPerLevel' => '40',
            'DailyTaskLimit' => '40',
            'appearance_mode' => 'light',
            'theme_primary' => '#4f46e5',
            'theme_secondary' => '#7c3aed',
            'theme_sidebar' => '#101828',
            'theme_background' => '#0f172a',
            'theme_surface' => '#111827',
            'theme_text' => '#f8fafc',
            'theme_radius' => '16px',
            'theme_shadow' => '0 10px 30px rgba(0,0,0,.5)'
        ];
    }
}

function updateSetting($key, $value) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$key, $value]);
    } catch(PDOException $e) {
        return false;
    }
}
?>
