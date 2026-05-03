<?php
// Start session first
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// handtoglobal/config.php

if (defined('HANDTOGLOBAL_CONFIG_LOADED')) {
    return;
}
define('HANDTOGLOBAL_CONFIG_LOADED', true);

// Database constants
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'handtoglobal');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// App constants
if (!defined('TELEGRAM_SUPPORT')) define('TELEGRAM_SUPPORT', 'https://t.me/chica256');
if (!defined('DAILY_TASK_LIMIT')) define('DAILY_TASK_LIMIT', 40);

if (!function_exists('getConnection')) {
    function getConnection() {
        $configs = [
            ['host' => DB_HOST, 'port' => DB_PORT, 'pass' => DB_PASS],
            ['host' => 'localhost', 'port' => 3307, 'pass' => ''],
            ['host' => 'localhost', 'port' => 3306, 'pass' => ''],
            ['host' => '127.0.0.1', 'port' => 3307, 'pass' => ''],
            ['host' => '127.0.0.1', 'port' => 3306, 'pass' => ''],
            ['host' => 'localhost', 'port' => 3307, 'pass' => 'root'],
            ['host' => 'localhost', 'port' => 3306, 'pass' => 'root'],
        ];

        foreach ($configs as $config) {
            try {
                $dsn = "mysql:host={$config['host']};port={$config['port']};dbname=" . DB_NAME . ";charset=utf8mb4";
                $pdo = new PDO($dsn, DB_USER, $config['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return $pdo;
            } catch (PDOException $e) {
                continue;
            }
        }

        die("Database connection failed. Please check XAMPP MySQL and config.php.");
    }
}
    
// Helper functions


// Helper functions
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        redirect('admin_login.php');
    }
}

function getUserById($id) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getAdminById($id) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

if (!function_exists('getSetting')) {
    function getSetting($key, $default = '') {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();

            return ($value !== false && $value !== null) ? $value : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('setSetting')) {
    function setSetting($key, $value) {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            return $stmt->execute([$key, $value]);
        } catch (Throwable $e) {
            return false;
        }
    }
}
if (!function_exists('getSetting')) {
    function getSetting($key, $default = '') {
        try {
            $conn = getConnection();

            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);

            $value = $stmt->fetchColumn();

            return ($value !== false && $value !== null) ? $value : $default;

        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('setSetting')) {
    function setSetting($key, $value) {
        global $conn;

        if (!$conn) return false;

        try {
            $stmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->bind_param("ss", $key, $value);
            return $stmt->execute();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('getSiteSettings')) {
    function getSiteSettings() {
        return [
            'site_name' => getSetting('SiteName', 'Hand to Global'),
            'support_email' => getSetting('SupportEmail', 'support@handtoglobal.com'),
            'telegram_link' => getSetting('TelegramLink', ''),
        ];
    }
}


function getThemeCSS() {
    $appearance = getSetting('appearance_mode', 'light');
    
    if ($appearance === 'dark') {
        $primary = getSetting('theme_primary', '#4f46e5');
        $secondary = getSetting('theme_secondary', '#7c3aed');
        $sidebar = getSetting('theme_sidebar', '#020617');
        $background = getSetting('theme_background', '#0f172a');
        $surface = getSetting('theme_surface', '#111827');
        $text = getSetting('theme_text', '#f8fafc');
        $border = '#334155';
        $radius = getSetting('theme_radius', '16px');
        $shadow = getSetting('theme_shadow', '0 10px 30px rgba(0,0,0,.5)');
    } else {
        $primary = getSetting('theme_primary', '#4f46e5');
        $secondary = getSetting('theme_secondary', '#7c3aed');
        $sidebar = getSetting('theme_sidebar', '#101828');
        $background = getSetting('theme_background', '#f5f7fb');
        $surface = getSetting('theme_surface', '#ffffff');
        $text = getSetting('theme_text', '#101828');
        $border = '#e5e7eb';
        $radius = getSetting('theme_radius', '16px');
        $shadow = getSetting('theme_shadow', '0 10px 30px rgba(16,24,40,.08)');
    }
    
    return "
        :root {
            --primary: {$primary};
            --primary-dark: " . adjustColor($primary, -20) . ";
            --secondary: {$secondary};
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #dc2626;
            --info: #0284c7;
            
            --bg: {$background};
            --surface: {$surface};
            --sidebar: {$sidebar};
            --sidebar-soft: " . adjustColor($sidebar, 10) . ";
            --text: {$text};
            --muted: " . adjustColor($text, -30) . ";
            --border: {$border};
            
            --radius: {$radius};
            --radius-sm: " . adjustSize($radius, -6) . ";
            --shadow: {$shadow};
            --shadow-soft: 0 4px 14px rgba(16,24,40,.06);
            --transition: .22s ease;
        }
        
        " . ($appearance === 'dark' ? 'body { background: var(--bg); color: var(--text); }' : '') . "
    ";
}

function adjustColor($color, $percent) {
    $color = ltrim($color, '#');
    $num = hexdec($color);
    $amt = round(2.55 * $percent);
    $r = max(0, min(255, ($num >> 16) + $amt));
    $g = max(0, min(255, (($num >> 8) & 0x00FF) + $amt));
    $b = max(0, min(255, ($num & 0x0000FF) + $amt));
    return '#' . sprintf('%02X%02X%02X', $r, $g, $b);
}

function adjustSize($size, $pixels) {
    $value = (int) $size;
    $new_value = max(4, $value + $pixels);
    return $new_value . 'px';
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes == 1 ? '1 minute ago' : $minutes . ' minutes ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours == 1 ? '1 hour ago' : $hours . ' hours ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days == 1 ? '1 day ago' : $days . ' days ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months == 1 ? '1 month ago' : $months . ' months ago';
    } else {
        $years = floor($diff / 31536000);
        return $years == 1 ? '1 year ago' : $years . ' years ago';
    }
}

function formatBalance($amount) {
    return number_format($amount, 2) . ' USDT';
}

function getLevelProgress($userId, $level) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as completed FROM completed_tasks WHERE user_id = ? AND level = ?");
    $stmt->execute([$userId, $level]);
    $result = $stmt->fetch();
    return $result['completed'];
}

function canAccessLevel($userId, $level) {
    $user = getUserById($userId);
    $conn = getConnection();
    
    // Check if level is unlocked
    $unlockField = strtolower($level) . '_unlocked';
    if ($user[$unlockField] != 1) {
        return false;
    }
    
    // Check if previous level is completed
    $levels = ['Bronze', 'Silver', 'Gold', 'Platinum'];
    $currentIndex = array_search($level, $levels);
    
    if ($currentIndex > 0) {
        $previousLevel = $levels[$currentIndex - 1];
        $completed = getLevelProgress($userId, $previousLevel);
        if ($completed < 40) {
            return false;
        }
    }
    
    return true;
}

function getNextUncompletedTask($userId, $level) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT t.* FROM tasks t 
                           LEFT JOIN completed_tasks ct ON t.id = ct.task_id AND ct.user_id = ?
                           WHERE t.level = ? AND ct.id IS NULL 
                           ORDER BY t.id LIMIT 1");
    $stmt->execute([$userId, $level]);
    return $stmt->fetch();
}

function createNotification($userId, $title, $message) {
    $conn = getConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $title, $message]);
}

function getUnreadNotifications($userId) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getTodayTaskCount($userId) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM completed_tasks 
                           WHERE user_id = ? AND DATE(completed_at) = CURDATE()");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['count'];
}

function getUserStats($userId) {
    $conn = getConnection();
    
    // Total tasks completed
    $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(reward) as total_earned FROM completed_tasks WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalStats = $stmt->fetch();
    
    // Today's tasks
    $todayCount = getTodayTaskCount($userId);
    
    // Level progress
    $user = getUserById($userId);
    $bronzeProgress = getLevelProgress($userId, 'Bronze');
    $silverProgress = getLevelProgress($userId, 'Silver');
    $goldProgress = getLevelProgress($userId, 'Gold');
    $platinumProgress = getLevelProgress($userId, 'Platinum');
    
    return [
        'total_tasks' => $totalStats['total'],
        'total_earned' => $totalStats['total_earned'],
        'today_tasks' => $todayCount,
        'balance' => $user['balance'],
        'level' => $user['level'],
        'rating' => $user['rating'],
        'accuracy' => $user['accuracy'],
        'bronze_progress' => $bronzeProgress,
        'silver_progress' => $silverProgress,
        'gold_progress' => $goldProgress,
        'platinum_progress' => $platinumProgress
    ];
}