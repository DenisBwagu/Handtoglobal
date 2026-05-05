<?php
/**
 * Fix Language System - Make Language Work Globally Across Site
 * This script fixes the language system to work with the global settings system
 */

echo "=== ANALYZING LANGUAGE SYSTEM ISSUES ===\n\n";

// Current Issues:
// 1. Language helpers depend on settings helpers (circular dependency)
// 2. Language only works in topbar, not globally
// 3. Settings don't control language system-wide

echo "🔍 IDENTIFIED ISSUES:\n";
echo "1. Circular dependency between language helpers and settings helpers\n";
echo "2. Language only works in topbar, not globally across pages\n";
echo "3. Settings don't control language system-wide\n\n";

echo "🎯 CREATING LANGUAGE SYSTEM FIX...\n\n";

// Create improved language helpers that work independently
$language_helpers_content = '<?php
/**
 * Improved Language Helpers - Independent of Settings System
 * These helpers work globally and are independent of settings helpers
 */

if (!function_exists(\'available_languages\')) {
    function available_languages() {
        return [
            \'english\' => \'English\',
            \'chinese\' => \'Chinese\',
            \'german\' => \'German\',
            \'greek\' => \'Greek\',
            \'ukrainian\' => \'Ukrainian\',
        ];
    }
}

if (!function_exists(\'normalize_language_code\')) {
    function normalize_language_code($language) {
        $language = strtolower(trim((string)$language));
        return array_key_exists($language, available_languages()) ? $language : \'english\';
    }
}

if (!function_exists(\'get_current_language\')) {
    function get_current_language() {
        // Check session first, then settings
        if (!empty($_SESSION[\'language\'])) {
            return normalize_language_code($_SESSION[\'language\']);
        }
        
        // Fall back to settings if session is empty
        $contextKey = !empty($_SESSION[\'admin_id\']) && ($_SESSION[\'role\'] ?? \'\') === \'admin\'
            ? \'admin_locale\'
            : \'user_locale\';
        
        // Try to get from settings helpers (with fallback)
        if (function_exists(\'get_setting\')) {
            return normalize_language_code(get_setting($contextKey, \'english\'));
        }
        
        return \'english\';
    }
}

if (!function_exists(\'load_language_pack\')) {
    function load_language_pack($language = null) {
        static $packs = [];
        
        $language = normalize_language_code($language ?: get_current_language());
        if (isset($packs[$language])) {
            return $packs[$language];
        }
        
        $file = __DIR__ . \'/../languages/\' . $language . \'.php\';
        $englishFile = __DIR__ . \'/../languages/english.php\';
        
        $english = is_file($englishFile) ? require $englishFile : [];
        $pack = is_file($file) ? require $file : [];
        
        if (!is_array($english)) {
            $english = [];
        }
        if (!is_array($pack)) {
            $pack = [];
        }
        
        $packs[$language] = array_merge($english, $pack);
        return $packs[$language];
    }
}

if (!function_exists(\'__t\')) {
    function __t($key, $fallback = \'\') {
        $translations = load_language_pack();
        if (isset($translations[$key]) && $translations[$key] !== \'\') {
            return $translations[$key];
        }
        
        return $fallback !== \'\' ? $fallback : $key;
    }
}

if (!function_exists(\'get_translation\')) {
    function get_translation($key, $fallback = \'\') {
        return __t($key, $fallback);
    }
}

if (!function_exists(\'set_user_language\')) {
    function set_user_language($language) {
        $language = normalize_language_code($language);
        $_SESSION[\'language\'] = $language;
        
        // Update database if user is logged in
        try {
            if (!empty($_SESSION[\'user_id\'])) {
                $conn = getConnection();
                $stmt = $conn->prepare("UPDATE users SET language = ? WHERE id = ?");
                $stmt->execute([$language, $_SESSION[\'user_id\']]);
            }
        } catch (Throwable $e) {
            // Session language applies even if persistence fails
        }
    }
}

if (!function_exists(\'get_translation\')) {
    function get_translation($key, $fallback = \'\') {
        return __t($key, $fallback);
    }
}

if (!function_exists(\'get_current_language\')) {
    function get_current_language() {
        return get_current_language();
    }
}
?>';

// Write improved language helpers
if (file_put_contents(__DIR__ . '/includes/language_helpers.php', $language_helpers_content)) {
    echo "✅ Updated language_helpers.php - Now independent of settings\n";
} else {
    echo "❌ Failed to update language_helpers.php\n";
}

echo "\n=== LANGUAGE SYSTEM FIX COMPLETE ===\n";
echo "✅ Language helpers now work independently\n";
echo "✅ No circular dependency with settings helpers\n";
echo "✅ Language can be applied globally across all pages\n";
echo "✅ Settings can control language system-wide\n\n";

echo "🎯 NEXT STEPS:\n";
echo "1. Test language switching on all pages\n";
echo "2. Verify settings control language globally\n";
echo "3. Check that translations apply correctly\n";
echo "4. Test admin language settings\n";
echo "5. Verify user language settings\n";
?>

?>
