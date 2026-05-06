<?php
/**
 * Global Settings Helper Functions
 * This file contains all functions to manage and retrieve settings
 */

if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        global $pdo;

        try {
            if (!$pdo) {
                $pdo = getConnection();
            }

            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();

            if ($value !== false && $value !== null && $value !== '') {
                return $value;
            }

            return $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('update_setting')) {
    function update_setting($key, $value, $type = 'text') {
        global $pdo;

        if (!$pdo) {
            $pdo = getConnection();
        }

        $stmt = $pdo->prepare("
            UPDATE settings
            SET setting_value = :setting_value,
                setting_type = :setting_type,
                updated_at = CURRENT_TIMESTAMP
            WHERE setting_key = :setting_key
            LIMIT 1
        ");
        $stmt->execute([
            ':setting_key' => $key,
            ':setting_value' => $value,
            ':setting_type' => $type
        ]);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        $check = $pdo->prepare("SELECT setting_key FROM settings WHERE setting_key = ? LIMIT 1");
        $check->execute([$key]);
        if ($check->fetchColumn()) {
            return true;
        }

        $insert = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_type, created_at, updated_at)
            VALUES (:setting_key, :setting_value, :setting_type, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        return $insert->execute([
            ':setting_key' => $key,
            ':setting_value' => $value,
            ':setting_type' => $type
        ]);
    }
}
            
if (!function_exists('htg_asset_url')) {
    function htg_asset_url($path) {
        $path = trim((string)$path);
        if ($path === '') {
            return '';
        }

        if (preg_match('/^(https?:)?\/\//i', $path) || $path[0] === '/') {
            return $path;
        }

        return '/handtoglobal/' . ltrim($path, '/');
    }
}
if (!function_exists('setting_url')) {
    /**
     * Get a setting value as a URL
     * @param string $key The setting key
     * @param string $default Default value if setting doesn't exist
     * @return string The setting value as URL
     */
    function setting_url($key, $default = '') {
        $value = get_setting($key, $default);
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : $default;
    }
}

if (!function_exists('get_site_logo')) {
    /**
     * Get the site logo browser URL.
     * @return string The site logo URL
     */
    function get_site_logo() {
        return htg_asset_url(get_setting('site_logo', 'assets/images/logo.png'));
    }
}

if (!function_exists('get_site_name')) {
    /**
     * Get the site name
     * @return string The site name
     */
    function get_site_name() {
        return get_setting('site_name', 'HandToGlobal');
    }
}

if (!function_exists('get_favicon')) {
    function get_favicon() {
        $favicon = get_setting('site_favicon', get_setting('favicon', 'assets/images/favicon.ico'));
        if ($favicon === '') {
            $favicon = 'assets/images/favicon.ico';
        }

        $url = htg_asset_url($favicon);
        if (preg_match('/^(https?:)?\/\//i', $favicon)) {
            return $url;
        }

        $path = strtok($favicon, '?');
        $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/'));
        $version = is_file($absolutePath) ? filemtime($absolutePath) : time();
        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url . $separator . 'v=' . $version;
    }
}
if (!function_exists('get_telegram_link')) {
    function get_telegram_link() {
        return get_setting('telegram_link', 'https://t.me/chica256');
    }
}

if (!function_exists('get_telegram_link')) {
    /**
     * Get the Telegram support link
     * @return string The Telegram link
     */
    function get_telegram_link() {
        return get_setting('telegram_link', 'https://t.me/chica256');
    }
}

if (!function_exists('get_support_link')) {
    /**
     * Get the support link (Telegram fallback)
     * @return string The support link
     */
    function get_support_link() {
        return get_telegram_link();
    }
}

if (!function_exists('get_default_settings')) {
    /**
     * Get default settings array
     * @return array Default settings
     */
    function get_default_settings() {
        return [
            'site_name' => 'HandToGlobal',
            'support_email' => 'support@handtoglobal.com',
            'telegram_link' => 'https://t.me/chica256',
            'site_logo' => 'assets/images/logo.png',
            'favicon' => 'assets/images/favicon.ico',
            'site_favicon' => 'assets/images/favicon.ico',
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
    }
}

if (!function_exists('get_meta_title')) {
    /**
     * Get the meta title for pages
     * @return string The meta title
     */
    function get_meta_title() {
        return get_setting('meta_title', get_site_name());
    }
}

if (!function_exists('get_meta_description')) {
    /**
     * Get the meta description
     * @return string The meta description
     */
    function get_meta_description() {
        return get_setting('meta_description', 'Join HandToGlobal and earn money by completing simple tasks.');
    }
}

if (!function_exists('get_meta_keywords')) {
    /**
     * Get the meta keywords
     * @return string The meta keywords
     */
    function get_meta_keywords() {
        return get_setting('meta_keywords', 'earn money online, tasks, get paid, handtoglobal');
    }
}

if (!function_exists('get_meta_robots')) {
    /**
     * Get the meta robots tag
     * @return string The meta robots
     */
    function get_meta_robots() {
        return get_setting('meta_robots', 'index, follow');
    }
}

if (!function_exists('get_og_image')) {
    /**
     * Get the OG image URL
     * @return string The OG image URL
     */
    function get_og_image() {
        return get_setting('og_image', 'assets/images/og-image.jpg');
    }
}
?>
