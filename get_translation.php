<?php
/**
 * Get translation for a key in specified language
 * @param string $key Translation key
 * @param string $language Language code (english, chinese, german, greek, ukrainian)
 * @param string $default Default value if translation not found
 * @return string Translation
 */
if (!function_exists('get_translation')) {
    function get_translation($key, $language = 'english', $default = '') {
    static $translations_cache = [];
    
    // Load translations once per language
    if (!isset($translations_cache[$language])) {
        $file_path = __DIR__ . "/languages/{$language}.php";
        if (file_exists($file_path)) {
            $translations_cache[$language] = require $file_path;
        } else {
            $translations_cache[$language] = [];
        }
    }
    
    return $translations_cache[$language][$key] ?? $default;
    }
}

/**
 * Get current user language
 * @return string Language code
 */
if (!function_exists('get_current_language')) {
    function get_current_language() {
    if (isset($_SESSION['admin']) && isset($_SESSION['admin_language'])) {
        return $_SESSION['admin_language'];
    }

    // Priority: user preference > session > default setting
    if (isLoggedIn() && isset($_SESSION['user_id'])) {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT language FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && !empty($user['language'])) {
                return $user['language'];
            }
        } catch(PDOException $e) {
            // Fall back to session or default
        }
    }
    
    // Check session language
    if (isset($_SESSION['language'])) {
        return $_SESSION['language'];
    }
    
    // Use default user locale from settings
    return get_setting('user_locale', 'english');
    }
}

/**
 * Set user language preference
 * @param string $language Language code
 * @return bool Success
 */
if (!function_exists('set_user_language')) {
    function set_user_language($language) {
    if (!in_array($language, ['english', 'chinese', 'german', 'greek', 'ukrainian'])) {
        return false;
    }
    
    // Set in session
    $_SESSION['language'] = $language;
    
    // Save to database if logged in
    if (isLoggedIn() && isset($_SESSION['user_id'])) {
        try {
            $conn = getConnection();
            
            // Check if language column exists
            $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'language'");
            $stmt->execute();
            $column_exists = $stmt->fetch();
            
            if (!$column_exists) {
                // Add language column if it doesn't exist
                $conn->exec("ALTER TABLE users ADD COLUMN language VARCHAR(50) DEFAULT 'english'");
            }
            
            // Update user language
            $stmt = $conn->prepare("UPDATE users SET language = ? WHERE id = ?");
            $stmt->execute([$language, $_SESSION['user_id']]);
            
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    return true;
    }
}

/**
 * Get translated text with current user language
 * @param string $key Translation key
 * @param string $default Default value
 * @return string Translation
 */
if (!function_exists('__')) {
    function __($key, $default = '') {
    $language = get_current_language();
    return get_translation($key, $language, $default ?: $key);
    }
}

/**
 * Get available languages
 * @return array Available languages
 */
if (!function_exists('get_available_languages')) {
    function get_available_languages() {
    return [
        'english' => 'English',
        'chinese' => 'Chinese',
        'german' => 'German',
        'greek' => 'Greek',
        'ukrainian' => 'Ukrainian'
    ];
    }
}

if (!function_exists('get_frontend_languages')) {
    function get_frontend_languages() {
        return [
            'english' => 'English',
            'ukrainian' => 'Ukraine',
            'greek' => 'Greek',
            'german' => 'German'
        ];
    }
}

if (!function_exists('get_backend_languages')) {
    function get_backend_languages() {
        return [
            'english' => 'English',
            'chinese' => 'Chinese'
        ];
    }
}

/**
 * Check if language is supported
 * @param string $language Language code
 * @return bool Supported
 */
if (!function_exists('is_language_supported')) {
    function is_language_supported($language) {
        return in_array($language, ['english', 'chinese', 'german', 'greek', 'ukrainian']);
    }
}
?>
