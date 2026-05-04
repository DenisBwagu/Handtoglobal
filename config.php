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

if (!function_exists('getSiteSettings')) {
    function getSiteSettings() {
        return [
            'site_name' => getSetting('SiteName', 'Hand to Global'),
            'support_email' => getSetting('SupportEmail', 'support@handtoglobal.com'),
            'telegram_link' => getSetting('TelegramLink', ''),
        ];
    }
}

// Language System
if (!function_exists('get_current_language')) {
    function get_current_language() {
        // Try to get language from settings, fallback to session, then to English
        $language = getSetting('user_locale', 'english');
        
        // Override with session if set
        if (isset($_SESSION['language'])) {
            $language = $_SESSION['language'];
        }
        
        return $language;
    }
}

if (!function_exists('set_language')) {
    function set_language($language) {
        // Save to session
        $_SESSION['language'] = $language;
        
        // Also save to database for persistence
        setSetting('user_locale', $language);
    }
}

if (!function_exists('get_translation')) {
    function get_translation($key, $fallback = '') {
        static $translations = [];
        
        $language = get_current_language();
        
        // Load translations for current language if not already loaded
        if (!isset($translations[$language])) {
            $translations[$language] = load_language_translations($language);
        }
        
        // Return translation or fallback
        return $translations[$language][$key] ?? $fallback;
    }
}

if (!function_exists('load_language_translations')) {
    function load_language_translations($language) {
        // Default translations (English)
        $default_translations = [
            // Navigation
            'dashboard' => 'Dashboard',
            'tasks' => 'Tasks',
            'levels' => 'Levels',
            'combos' => 'Combos',
            'profile' => 'Profile',
            'wallet' => 'Wallet',
            'settings' => 'Settings',
            'logout' => 'Logout',
            
            // Common
            'save' => 'Save',
            'cancel' => 'Cancel',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'view' => 'View',
            'add' => 'Add',
            'submit' => 'Submit',
            'search' => 'Search',
            'loading' => 'Loading...',
            'error' => 'Error',
            'success' => 'Success',
            
            // User Dashboard
            'welcome_back' => 'Welcome Back',
            'total_earned' => 'Total Earned',
            'today_earned' => 'Today Earned',
            'current_level' => 'Current Level',
            'tasks_completed' => 'Tasks Completed',
            'balance' => 'Balance',
            'withdraw' => 'Withdraw',
            'start_earning' => 'Start Earning',
            
            // Login/Register
            'login' => 'Login',
            'register' => 'Register',
            'email' => 'Email',
            'password' => 'Password',
            'confirm_password' => 'Confirm Password',
            'full_name' => 'Full Name',
            'invitation_code' => 'Invitation Code',
            'already_have_account' => 'Already have an account?',
            'dont_have_account' => "Don't have an account?",
            
            // Tasks
            'complete_task' => 'Complete Task',
            'task_reward' => 'Task Reward',
            'task_description' => 'Task Description',
            'no_tasks_available' => 'No tasks available',
            'task_completed_successfully' => 'Task completed successfully',
            
            // Levels
            'unlock_level' => 'Unlock Level',
            'level_requirements' => 'Level Requirements',
            'level_reward' => 'Level Reward',
            'locked' => 'Locked',
            'unlocked' => 'Unlocked',
            
            // Wallet
            'withdrawal_amount' => 'Withdrawal Amount',
            'wallet_address' => 'Wallet Address',
            'request_withdrawal' => 'Request Withdrawal',
            'withdrawal_history' => 'Withdrawal History',
            'pending_withdrawals' => 'Pending Withdrawals',
            'completed_withdrawals' => 'Completed Withdrawals',
            
            // Admin
            'admin_panel' => 'Admin Panel',
            'users_management' => 'Users Management',
            'employees_management' => 'Employees Management',
            'tasks_management' => 'Tasks Management',
            'levels_management' => 'Levels Management',
            'combos_management' => 'Combos Management',
            'withdrawals_management' => 'Withdrawals Management',
            'settings_management' => 'Settings Management',
            
            // Messages
            'login_successful' => 'Login successful',
            'login_failed' => 'Login failed',
            'registration_successful' => 'Registration successful',
            'settings_saved' => 'Settings saved successfully',
            'profile_updated' => 'Profile updated successfully',
            'withdrawal_requested' => 'Withdrawal requested successfully'
        ];
        
        // Language-specific translations
        $language_translations = [
            'greek' => [
                // Navigation
                'dashboard' => 'Πίνακας Ελέγχου',
                'tasks' => 'Εργασίες',
                'levels' => 'Επίπεδα',
                'combos' => 'Συνδυασμοί',
                'profile' => 'Προφίλ',
                'wallet' => 'Πορτοφόλι',
                'settings' => 'Ρυθμίσεις',
                'logout' => 'Αποσύνδεση',
                
                // Common
                'save' => 'Αποθήκευση',
                'cancel' => 'Ακύρωση',
                'edit' => 'Επεξεργασία',
                'delete' => 'Διαγραφή',
                'view' => 'Προβολή',
                'add' => 'Προσθήκη',
                'submit' => 'Υποβολή',
                'search' => 'Αναζήτηση',
                'loading' => 'Φόρτωση...',
                'error' => 'Σφάλμα',
                'success' => 'Επιτυχία',
                
                // User Dashboard
                'welcome_back' => 'Καλώς ήρθατε πίσω',
                'total_earned' => 'Συνολικά Κερδισμένα',
                'today_earned' => 'Σήμερα Κερδισμένα',
                'current_level' => 'Τρέχον Επίπεδο',
                'tasks_completed' => 'Ολοκληρωμένες Εργασίες',
                'balance' => 'Ισοζύγιο',
                'withdraw' => 'Ανάληψη',
                'start_earning' => 'Ξεκινήστε να κερδίζετε',
                
                // Login/Register
                'login' => 'Σύνδεση',
                'register' => 'Εγγραφή',
                'email' => 'Email',
                'password' => 'Κωδικός',
                'confirm_password' => 'Επιβεβαίωση Κωδικού',
                'full_name' => 'Πλήρες Όνομα',
                'invitation_code' => 'Κωδικός Πρόσκλησης',
                'already_have_account' => 'Έχετε ήδη λογαριασμό;',
                'dont_have_account' => 'Δεν έχετε λογαριασμό;',
                
                // Tasks
                'complete_task' => 'Ολοκλήρωση Εργασίας',
                'task_reward' => 'Αμοιβή Εργασίας',
                'task_description' => 'Περιγραφή Εργασίας',
                'no_tasks_available' => 'Δεν υπάρχουν διαθέσιμες εργασίες',
                'task_completed_successfully' => 'Η εργασία ολοκληρώθηκε με επιτυχία',
                
                // Levels
                'unlock_level' => 'Ξεκλειδώστε Επίπεδο',
                'level_requirements' => 'Απαιτήσεις Επιπέδου',
                'level_reward' => 'Αμοιβή Επιπέδου',
                'locked' => 'Κλειδωμένο',
                'unlocked' => 'Ξεκλειδωμένο',
                
                // Wallet
                'withdrawal_amount' => 'Ποσό Ανάληψης',
                'wallet_address' => 'Διεύθυνση Πορτοφολιού',
                'request_withdrawal' => 'Αίτηση Ανάληψης',
                'withdrawal_history' => 'Ιστορικό Αναλήψεων',
                'pending_withdrawals' => 'Εκκρεμείς Αναλήψεις',
                'completed_withdrawals' => 'Ολοκληρωμένες Αναλήψεις',
                
                // Admin
                'admin_panel' => 'Πίνακας Διαχείρισης',
                'users_management' => 'Διαχείριση Χρηστών',
                'employees_management' => 'Διαχείριση Υπαλλήλων',
                'tasks_management' => 'Διαχείριση Εργασιών',
                'levels_management' => 'Διαχείριση Επιπέδων',
                'combos_management' => 'Διαχείριση Συνδυασμών',
                'withdrawals_management' => 'Διαχείριση Αναλήψεων',
                'settings_management' => 'Διαχείριση Ρυθμίσεων',
                
                // Messages
                'login_successful' => 'Επιτυχής σύνδεση',
                'login_failed' => 'Αποτυχία σύνδεσης',
                'registration_successful' => 'Επιτυχής εγγραφή',
                'settings_saved' => 'Οι ρυθμίσεις αποθηκεύτηκαν με επιτυχία',
                'profile_updated' => 'Το προφίλ ενημερώθηκε με επιτυχία',
                'withdrawal_requested' => 'Η αίτηση ανάληψης υποβλήθηκε με επιτυχία'
            ],
            
            'german' => [
                // Navigation
                'dashboard' => 'Dashboard',
                'tasks' => 'Aufgaben',
                'levels' => 'Stufen',
                'combos' => 'Kombinationen',
                'profile' => 'Profil',
                'wallet' => 'Brieftasche',
                'settings' => 'Einstellungen',
                'logout' => 'Abmelden',
                
                // Common
                'save' => 'Speichern',
                'cancel' => 'Abbrechen',
                'edit' => 'Bearbeiten',
                'delete' => 'Löschen',
                'view' => 'Anzeigen',
                'add' => 'Hinzufügen',
                'submit' => 'Senden',
                'search' => 'Suchen',
                'loading' => 'Laden...',
                'error' => 'Fehler',
                'success' => 'Erfolg',
                
                // User Dashboard
                'welcome_back' => 'Willkommen zurück',
                'total_earned' => 'Insgesamt verdient',
                'today_earned' => 'Heute verdient',
                'current_level' => 'Aktuelle Stufe',
                'tasks_completed' => 'Abgeschlossene Aufgaben',
                'balance' => 'Guthaben',
                'withdraw' => 'Abheben',
                'start_earning' => 'Verdienen starten',
                
                // Login/Register
                'login' => 'Anmelden',
                'register' => 'Registrieren',
                'email' => 'E-Mail',
                'password' => 'Passwort',
                'confirm_password' => 'Passwort bestätigen',
                'full_name' => 'Vollständiger Name',
                'invitation_code' => 'Einladungscode',
                'already_have_account' => 'Haben Sie bereits ein Konto?',
                'dont_have_account' => 'Haben Sie kein Konto?',
                
                // Tasks
                'complete_task' => 'Aufgabe abschließen',
                'task_reward' => 'Aufgabenbelohnung',
                'task_description' => 'Aufgabenbeschreibung',
                'no_tasks_available' => 'Keine Aufgaben verfügbar',
                'task_completed_successfully' => 'Aufgabe erfolgreich abgeschlossen',
                
                // Levels
                'unlock_level' => 'Stufe freischalten',
                'level_requirements' => 'Stufenanforderungen',
                'level_reward' => 'Stufenbelohnung',
                'locked' => 'Gesperrt',
                'unlocked' => 'Freigeschaltet',
                
                // Wallet
                'withdrawal_amount' => 'Abhebungsbetrag',
                'wallet_address' => 'Brieftaschenadresse',
                'request_withdrawal' => 'Abhebung anfordern',
                'withdrawal_history' => 'Abhebungshistorie',
                'pending_withdrawals' => 'Ausstehende Abhebungen',
                'completed_withdrawals' => 'Abgeschlossene Abhebungen',
                
                // Admin
                'admin_panel' => 'Admin-Panel',
                'users_management' => 'Benutzerverwaltung',
                'employees_management' => 'Mitarbeiterverwaltung',
                'tasks_management' => 'Aufgabenverwaltung',
                'levels_management' => 'Stufenverwaltung',
                'combos_management' => 'Kombinationsverwaltung',
                'withdrawals_management' => 'Abhebungsverwaltung',
                'settings_management' => 'Einstellungsverwaltung',
                
                // Messages
                'login_successful' => 'Anmeldung erfolgreich',
                'login_failed' => 'Anmeldung fehlgeschlagen',
                'registration_successful' => 'Registrierung erfolgreich',
                'settings_saved' => 'Einstellungen erfolgreich gespeichert',
                'profile_updated' => 'Profil erfolgreich aktualisiert',
                'withdrawal_requested' => 'Abhebung erfolgreich angefordert'
            ],
            
            'ukrainian' => [
                // Navigation
                'dashboard' => 'Панель',
                'tasks' => 'Завдання',
                'levels' => 'Рівні',
                'combos' => 'Комбінації',
                'profile' => 'Профіль',
                'wallet' => 'Гаманець',
                'settings' => 'Налаштування',
                'logout' => 'Вийти',
                
                // Common
                'save' => 'Зберегти',
                'cancel' => 'Скасувати',
                'edit' => 'Редагувати',
                'delete' => 'Видалити',
                'view' => 'Переглянути',
                'add' => 'Додати',
                'submit' => 'Надіслати',
                'search' => 'Пошук',
                'loading' => 'Завантаження...',
                'error' => 'Помилка',
                'success' => 'Успіх',
                
                // User Dashboard
                'welcome_back' => 'Ласкаво просимо назад',
                'total_earned' => 'Загалом зароблено',
                'today_earned' => 'Сьогодні зароблено',
                'current_level' => 'Поточний рівень',
                'tasks_completed' => 'Завдань виконано',
                'balance' => 'Баланс',
                'withdraw' => 'Вивести',
                'start_earning' => 'Почати заробляти',
                
                // Login/Register
                'login' => 'Увійти',
                'register' => 'Зареєструватися',
                'email' => 'Email',
                'password' => 'Пароль',
                'confirm_password' => 'Підтвердити пароль',
                'full_name' => 'Повне ім\'я',
                'invitation_code' => 'Код запрошення',
                'already_have_account' => 'Вже є акаунт?',
                'dont_have_account' => 'Немає акаунту?',
                
                // Tasks
                'complete_task' => 'Виконати завдання',
                'task_reward' => 'Нагорода за завдання',
                'task_description' => 'Опис завдання',
                'no_tasks_available' => 'Немає доступних завдань',
                'task_completed_successfully' => 'Завдання успішно виконано',
                
                // Levels
                'unlock_level' => 'Розблокувати рівень',
                'level_requirements' => 'Вимоги рівня',
                'level_reward' => 'Нагорода рівня',
                'locked' => 'Заблоковано',
                'unlocked' => 'Розблоковано',
                
                // Wallet
                'withdrawal_amount' => 'Сума виведення',
                'wallet_address' => 'Адреса гаманця',
                'request_withdrawal' => 'Запросити виведення',
                'withdrawal_history' => 'Історія виведень',
                'pending_withdrawals' => 'Очікуючі виведення',
                'completed_withdrawals' => 'Завершені виведення',
                
                // Admin
                'admin_panel' => 'Адмін-панель',
                'users_management' => 'Управління користувачами',
                'employees_management' => 'Управління співробітниками',
                'tasks_management' => 'Управління завданнями',
                'levels_management' => 'Управління рівнями',
                'combos_management' => 'Управління комбінаціями',
                'withdrawals_management' => 'Управління виведеннями',
                'settings_management' => 'Управління налаштуваннями',
                
                // Messages
                'login_successful' => 'Вхід успішний',
                'login_failed' => 'Вхід не вдався',
                'registration_successful' => 'Реєстрація успішна',
                'settings_saved' => 'Налаштування успішно збережено',
                'profile_updated' => 'Профіль успішно оновлено',
                'withdrawal_requested' => 'Запит на виведення успішно надіслано'
            ]
        ];
        
        // Merge with default translations
        return array_merge($default_translations, $language_translations[$language] ?? []);
    }
}

if (!function_exists('t')) {
    function t($key, $fallback = '') {
        echo get_translation($key, $fallback);
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

// Global support link function
if (!function_exists('getSupportLink')) {
    function getSupportLink() {
        static $support_link = null;
        
        if ($support_link === null) {
            try {
                $conn = getConnection();
                $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'telegram_link' LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetch();
                $support_link = $result['setting_value'] ?? '#';
            } catch (PDOException $e) {
                $support_link = '#';
            }
        }
        
        return $support_link;
    }
}