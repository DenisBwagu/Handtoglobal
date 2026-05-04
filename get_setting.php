<?php
/**
 * Get setting value from database
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default
 */
function get_setting($key, $default = '') {
    static $settings_cache = [];
    
    // Load settings once per request
    if (empty($settings_cache)) {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($settings as $setting) {
                $settings_cache[$setting['setting_key']] = $setting['setting_value'];
            }
        } catch(PDOException $e) {
            // Return default if database fails
            return $default;
        }
    }
    
    return $settings_cache[$key] ?? $default;
}

/**
 * Get all settings as array
 * @return array All settings
 */
function get_all_settings() {
    static $all_settings_cache = null;
    
    if ($all_settings_cache === null) {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $all_settings_cache = [];
            foreach ($settings as $setting) {
                $all_settings_cache[$setting['setting_key']] = $setting['setting_value'];
            }
        } catch(PDOException $e) {
            $all_settings_cache = [];
        }
    }
    
    return $all_settings_cache;
}

/**
 * Update setting value
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @return bool Success status
 */
function update_setting($key, $value) {
    try {
        $conn = getConnection();
        
        // Check if setting exists
        $stmt = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        
        if ($stmt->fetch()) {
            // Update existing setting
            $stmt = $conn->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        } else {
            // Insert new setting
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
        }
        
        return true;
    } catch(PDOException $e) {
        return false;
    }
}
?>
