<?php
// Start session first
if (session_status() == PHP_SESSION_NONE) {
    $sessionCandidates = [
        sys_get_temp_dir(),
        __DIR__ . DIRECTORY_SEPARATOR . 'tmp_sessions',
        'C:\\xampp\\tmp',
    ];
    foreach ($sessionCandidates as $fallbackSessionPath) {
        if (!is_dir($fallbackSessionPath)) {
            @mkdir($fallbackSessionPath, 0777, true);
        }
        if (is_dir($fallbackSessionPath) && is_writable($fallbackSessionPath)) {
            session_save_path($fallbackSessionPath);
            break;
        }
    }
    session_start();
}

// handtoglobal/config.php

if (defined('HANDTOGLOBAL_CONFIG_LOADED')) {
    return;
}
define('HANDTOGLOBAL_CONFIG_LOADED', true);

// Database constants
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', '3307');
if (!defined('DB_NAME')) define('DB_NAME', 'handtoglobal');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// App constants
if (!defined('TELEGRAM_SUPPORT')) define('TELEGRAM_SUPPORT', 'https://t.me/chica256');
if (!defined('DAILY_TASK_LIMIT')) define('DAILY_TASK_LIMIT', 40);

if (!function_exists('normalizeLevelName')) {
    function normalizeLevelName($level) {
        $value = trim((string)$level);
        $lower = strtolower($value);

        if ($lower === 'silver' || $lower === 'sliver') {
            return 'Sliver';
        }

        if ($lower === 'vip' || $lower === 'vip / platinum' || $lower === 'platinum') {
            return 'VIP 1';
        }

        if ($lower === 'vip 1') {
            return 'VIP 1';
        }

        if ($lower === 'bronze') {
            return 'Bronze';
        }

        if ($lower === 'gold') {
            return 'Gold';
        }

        return $value;
    }
}

if (!function_exists('getAppLevels')) {
    function getAppLevels() {
        try {
            $conn = getConnection();
            $stmt = $conn->query("
                SELECT id, name, sort_order, task_reward, reward, tasks, daily_task_limit, deposit_amount, task_type, icon
                FROM levels
                WHERE COALESCE(is_active, active, 1) = 1
                ORDER BY sort_order ASC, id ASC
            ");
            $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($levels) {
                return array_map(function ($level) {
                    $level['name'] = normalizeLevelName($level['name']);
                    $level['number_of_tasks'] = (int)($level['tasks'] ?? 40);
                    $level['task_reward'] = (float)($level['task_reward'] ?: ($level['reward'] ?? 0));
                    return $level;
                }, $levels);
            }
        } catch (Throwable $e) {
            // Fall through to defaults.
        }

        return [
            ['id' => null, 'name' => 'Bronze', 'sort_order' => 1, 'task_reward' => 1.20, 'number_of_tasks' => 40, 'task_type' => 'Name_items'],
            ['id' => null, 'name' => 'Sliver', 'sort_order' => 2, 'task_reward' => 1.50, 'number_of_tasks' => 40, 'task_type' => 'Name_items'],
            ['id' => null, 'name' => 'Gold', 'sort_order' => 3, 'task_reward' => 2.50, 'number_of_tasks' => 40, 'task_type' => 'Name_items'],
            ['id' => null, 'name' => 'VIP 1', 'sort_order' => 4, 'task_reward' => 4.00, 'number_of_tasks' => 40, 'task_type' => 'Name_items'],
        ];
    }
}

if (!function_exists('getAppLevelNames')) {
    function getAppLevelNames() {
        return array_values(array_map(function ($level) {
            return $level['name'];
        }, getAppLevels()));
    }
}

if (!function_exists('getConnection')) 
    if (!function_exists('getConnection')) {
    function getConnection() {
        static $conn = null;

        if ($conn !== null) {
            return $conn;
        }

        try {
            $conn = new PDO(
                "mysql:host=127.0.0.1;port=3306;dbname=handtoglobal;charset=utf8mb4",
                "root",
                ""
            );
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $conn;
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
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
    return isset($_SESSION['user_id']) && ($_SESSION['role'] ?? 'user') === 'user';
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) || isset($_SESSION['admin_logged_in']);
}

function requireLogin() {
    // Allow bypass if impersonating
    if (!empty($_SESSION['bypass_login']) && !empty($_SESSION['is_impersonating'])) {
        return; // Skip login check for admin impersonation
    }
    
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        redirect('../login.php');
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
    $level = normalizeLevelName($level);
    $stmt = $conn->prepare("SELECT COUNT(*) as completed FROM completed_tasks WHERE user_id = ? AND level = ?");
    $stmt->execute([$userId, $level]);
    $result = $stmt->fetch();
    return $result['completed'];
}

function canAccessLevel($userId, $level) {
    $user = getUserById($userId);
    $conn = getConnection();
    $level = normalizeLevelName($level);
    
    // Check if level is unlocked
    if (!isLevelUnlockedForUser($userId, $level)) {
        return false;
    }
    
    // Check if previous level is completed
    $levels = getAppLevelNames();
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
    $level = normalizeLevelName($level);
    
    $stmt = $conn->prepare("SELECT t.* FROM tasks t 
                           LEFT JOIN completed_tasks ct ON t.id = ct.task_id AND ct.user_id = ?
                           WHERE t.level = ? AND t.active = 1 AND ct.id IS NULL 
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
    $silverProgress = getLevelProgress($userId, 'Sliver');
    $goldProgress = getLevelProgress($userId, 'Gold');
    $platinumProgress = getLevelProgress($userId, 'VIP 1');
    
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

// Level management functions
if (!function_exists('createUserLevelsTable')) {
    function createUserLevelsTable() {
        try {
            $conn = getConnection();
            $sql = "
                CREATE TABLE IF NOT EXISTS user_levels (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    level VARCHAR(50) NOT NULL,
                    is_unlocked TINYINT(1) DEFAULT 0,
                    completed_count INT DEFAULT 0,
                    flushed_at TIMESTAMP NULL,
                    unlocked_at TIMESTAMP NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_level (user_id, level),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $tableExists = (bool)$conn->query("SHOW TABLES LIKE 'user_levels'")->fetchColumn();
            if ($tableExists) {
                try {
                    $conn->query("SELECT id FROM user_levels LIMIT 1");
                } catch (Throwable $brokenUserLevelsTable) {
                    $conn->exec("DROP TABLE IF EXISTS user_levels");
                }
            }
            $conn->exec($sql);
            $conn->exec("
                DELETE ul1 FROM user_levels ul1
                INNER JOIN user_levels ul2
                    ON ul1.user_id = ul2.user_id
                    AND ul1.level = ul2.level
                    AND ul1.id < ul2.id
            ");
            $indexes = $conn->query("SHOW INDEX FROM user_levels WHERE Key_name = 'unique_user_level'")->fetchAll();
            if (!$indexes) {
                $conn->exec("ALTER TABLE user_levels ADD UNIQUE KEY unique_user_level (user_id, level)");
            }
            return true;
        } catch (PDOException $e) {
            error_log("Error creating user_levels table: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('unlockLevelForUser')) {
    function unlockLevelForUser($userId, $level) {
        try {
            $conn = getConnection();
            $level = normalizeLevelName($level);
            
            // DEBUG: Log the unlock attempt
            error_log("DEBUG: Attempting to unlock level $level for user_id: $userId");
            
            // Ensure table exists
            createUserLevelsTable();
            
            $result = true;
            
            // 1. Update user_levels table
            $stmt = $conn->prepare("
                INSERT INTO user_levels (user_id, level, is_unlocked, unlocked_at, updated_at)
                VALUES (?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                is_unlocked = 1,
                unlocked_at = NOW(),
                updated_at = NOW()
            ");
            $userLevelsResult = $stmt->execute([$userId, $level]);
            
            if ($userLevelsResult) {
                error_log("DEBUG: Successfully unlocked level $level in user_levels table for user_id: $userId");
            } else {
                error_log("DEBUG: Failed to unlock level $level in user_levels table for user_id: $userId");
                $result = false;
            }
            
            // 2. Update users table columns for backward compatibility
            if ($userLevelsResult) {
                $levelField = strtolower($level) . '_unlocked';
                if ($level === 'Sliver') {
                    $levelField = 'silver_unlocked';
                } elseif ($level === 'VIP 1') {
                    $levelField = 'platinum_unlocked';
                }
                
                $stmt = $conn->prepare("UPDATE users SET $levelField = 1 WHERE id = ?");
                $usersResult = $stmt->execute([$userId]);
                
                if ($usersResult) {
                    error_log("DEBUG: Successfully unlocked level $level in users table ($levelField) for user_id: $userId");
                } else {
                    error_log("DEBUG: Failed to unlock level $level in users table ($levelField) for user_id: $userId");
                    // Don't fail the whole operation if users table update fails
                }
            }
            
            // 3. Update user's current level if this is higher than their current
            if ($result && $userLevelsResult) {
                $stmt = $conn->prepare("UPDATE users SET level = ? WHERE id = ?");
                $stmt->execute([$level, $userId]);
                error_log("DEBUG: Updated user's current level to $level for user_id: $userId");
            }
            
            error_log("DEBUG: Final unlock result for level $level, user_id: $userId: " . ($result ? 'SUCCESS' : 'FAILED'));
            return $result;
            
        } catch (PDOException $e) {
            error_log("DEBUG: Error unlocking level: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('flushLevelForUser')) {
    function flushLevelForUser($userId, $level) {
        try {
            $conn = getConnection();
            $level = normalizeLevelName($level);
            
            // Ensure table exists
            createUserLevelsTable();
            
            // Delete completed tasks for this user and level
            $stmt = $conn->prepare("
                DELETE ct FROM completed_tasks ct
                INNER JOIN tasks t ON ct.task_id = t.id
                WHERE ct.user_id = ? AND (t.level = ? OR ct.level = ?)
            ");
            $stmt->execute([$userId, $level, $level]);
            
            // Reset user level record
            $stmt = $conn->prepare("
                INSERT INTO user_levels (user_id, level, is_unlocked, completed_count, flushed_at, updated_at)
                VALUES (?, ?, 0, 0, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                is_unlocked = 0,
                completed_count = 0,
                flushed_at = NOW(),
                updated_at = NOW()
            ");
            $stmt->execute([$userId, $level]);
            
            $stmt = $conn->prepare("UPDATE users SET level = 'Bronze' WHERE id = ? AND level = ?");
            $stmt->execute([$userId, $level]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Error flushing level: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('isLevelUnlockedForUser')) {
    function isLevelUnlockedForUser($userId, $level) {
        try {
            $level = normalizeLevelName($level);
            
            // Bronze is always unlocked for all users - check first
            if ($level === 'Bronze') {
                error_log("DEBUG: Bronze level - always unlocked for user_id: $userId");
                return true;
            }
            
            $conn = getConnection();
            
            // DEBUG: Log the check
            error_log("DEBUG: Checking unlock status for user_id: $userId, level: $level");
            
            $isUnlocked = false;
            
            // Check user_levels table first
            $stmt = $conn->prepare("
                SELECT is_unlocked FROM user_levels 
                WHERE user_id = ? AND level = ?
                LIMIT 1
            ");
            $stmt->execute([$userId, $level]);
            $userLevelResult = $stmt->fetch();
            
            if ($userLevelResult !== false && (int)$userLevelResult['is_unlocked'] === 1) {
                $isUnlocked = true;
                error_log("DEBUG: Found unlocked in user_levels table for $level");
            }
            
            // Also check users table for backward compatibility
            if (!$isUnlocked) {
                $levelField = strtolower($level) . '_unlocked';
                if ($level === 'Sliver') {
                    $levelField = 'silver_unlocked';
                } elseif ($level === 'VIP 1') {
                    $levelField = 'platinum_unlocked';
                }
                
                $stmt = $conn->prepare("
                    SELECT {$levelField} as unlocked FROM users 
                    WHERE id = ? LIMIT 1
                ");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if ($user && isset($user['unlocked']) && (int)$user['unlocked'] === 1) {
                    $isUnlocked = true;
                    error_log("DEBUG: Found unlocked in users table - $levelField: 1");
                }
            }
            
            error_log("DEBUG: Final unlock status for $level: " . ($isUnlocked ? 'UNLOCKED' : 'LOCKED'));
            return $isUnlocked;
            
        } catch (PDOException $e) {
            error_log("DEBUG: Error checking level unlock status: " . $e->getMessage());
            // Default to Bronze unlocked on error, others locked
            return $level === 'Bronze';
        }
    }
}

if (!function_exists('getLevelProgressForUser')) {
    function getLevelProgressForUser($userId, $level) {
        try {
            $conn = getConnection();
            $level = normalizeLevelName($level);
            
            // Get completed tasks count
            $stmt = $conn->prepare("
                SELECT COUNT(*) as completed
                FROM completed_tasks ct
                INNER JOIN tasks t ON ct.task_id = t.id
                WHERE ct.user_id = ? AND (t.level = ? OR ct.level = ?)
            ");
            $stmt->execute([$userId, $level, $level]);
            $result = $stmt->fetch();
            $completed = $result['completed'] ?? 0;
            
            // Get total tasks for level
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total FROM tasks WHERE level = ? AND active = 1
            ");
            $stmt->execute([$level]);
            $result = $stmt->fetch();
            $total = $result['total'] ?? 40;
            
            // Calculate progress
            $progress = $total > 0 ? ($completed / $total) * 100 : 0;
            $available = max(0, $total - $completed);
            
            return [
                'completed' => $completed,
                'total' => $total,
                'available' => $available,
                'progress' => $progress,
                'is_unlocked' => isLevelUnlockedForUser($userId, $level)
            ];
            
        } catch (PDOException $e) {
            error_log("Error getting level progress: " . $e->getMessage());
            return [
                'completed' => 0,
                'total' => 40,
                'available' => 40,
                'progress' => 0,
                'is_unlocked' => $level === 'Bronze'
            ];
        }
    }
}

if (!function_exists('updateUserSessionData')) {
    function updateUserSessionData($userId) {
        try {
            $conn = getConnection();
            
            // Get fresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();
            
            if ($userData && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
                // Update session with fresh data
                $_SESSION['user_balance'] = $userData['balance'];
                $_SESSION['user_level'] = $userData['level'];
                $_SESSION['user_rating'] = $userData['rating'];
                $_SESSION['user_accuracy'] = $userData['accuracy'];
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error updating user session data: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('refreshUserDashboardCache')) {
    function refreshUserDashboardCache($userId) {
        try {
            // Clear any cached dashboard data for this user
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
                unset($_SESSION['dashboard_cache']);
                unset($_SESSION['level_stats_cache']);
                
                // Force fresh data on next dashboard load
                $_SESSION['force_refresh'] = true;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error refreshing dashboard cache: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('ensureHandToGlobalRuntimeSchema')) {
    function ensureColumnExists(PDO $conn, $table, $column, $definition) {
        $quotedColumn = $conn->quote($column);
        $stmt = $conn->query("SHOW COLUMNS FROM `$table` LIKE {$quotedColumn}");
        if (!$stmt->fetch()) {
            $conn->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }

    function ensureAuthSchema() {
        static $done = false;

        if ($done) {
            return;
        }

        $done = true;
        $conn = getConnection();

        $createAdminsSql = "
            CREATE TABLE IF NOT EXISTS admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NULL,
                email VARCHAR(150) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        $conn->exec($createAdminsSql);

        try {
            $conn->query("SELECT id FROM admins LIMIT 1");
        } catch (Throwable $brokenAdminsTable) {
            $conn->exec("DROP TABLE IF EXISTS admins");
            $conn->exec($createAdminsSql);
        }

        ensureColumnExists($conn, 'admins', 'name', 'VARCHAR(100) NULL');
        ensureColumnExists($conn, 'admins', 'email', 'VARCHAR(150) NOT NULL UNIQUE');
        ensureColumnExists($conn, 'admins', 'password', 'VARCHAR(255) NOT NULL');
        ensureColumnExists($conn, 'admins', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');

        $stmt = $conn->prepare("SELECT id, password FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute(['admin@handtoglobal.com']);
        $admin = $stmt->fetch();
        if (!$admin) {
            $stmt = $conn->prepare("INSERT INTO admins (name, email, password) VALUES (?, ?, ?)");
            $stmt->execute(['Admin', 'admin@handtoglobal.com', password_hash('admin123', PASSWORD_DEFAULT)]);
        } elseif (!password_verify('admin123', $admin['password'])) {
            $stmt = $conn->prepare("UPDATE admins SET name = COALESCE(NULLIF(name, ''), 'Admin'), password = ? WHERE email = ?");
            $stmt->execute([password_hash('admin123', PASSWORD_DEFAULT), 'admin@handtoglobal.com']);
        }

        $createUsersSql = "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fullname VARCHAR(255) NOT NULL,
                email VARCHAR(150) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                balance DECIMAL(10,2) DEFAULT 0.00,
                level VARCHAR(50) DEFAULT 'Bronze',
                rating DECIMAL(5,2) DEFAULT 0.00,
                accuracy DECIMAL(5,2) DEFAULT 0.00,
                total_tasks INT DEFAULT 0,
                bronze_unlocked TINYINT(1) DEFAULT 0,
                silver_unlocked TINYINT(1) DEFAULT 0,
                gold_unlocked TINYINT(1) DEFAULT 0,
                platinum_unlocked TINYINT(1) DEFAULT 0,
                invite_code_used VARCHAR(50) NULL,
                referred_by INT NULL,
                is_blocked TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        $usersExists = (bool)$conn->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if ($usersExists) {
            try {
                $conn->query("SELECT id FROM users LIMIT 1");
            } catch (Throwable $brokenUsersTable) {
                $conn->exec("DROP TABLE IF EXISTS users");
            }
        }
        $conn->exec($createUsersSql);

        ensureColumnExists($conn, 'users', 'fullname', 'VARCHAR(255) NOT NULL');
        ensureColumnExists($conn, 'users', 'email', 'VARCHAR(150) NOT NULL UNIQUE');
        ensureColumnExists($conn, 'users', 'password', 'VARCHAR(255) NOT NULL');
        ensureColumnExists($conn, 'users', 'balance', 'DECIMAL(10,2) DEFAULT 0.00');
        ensureColumnExists($conn, 'users', 'level', "VARCHAR(50) DEFAULT 'Bronze'");
        ensureColumnExists($conn, 'users', 'rating', 'DECIMAL(5,2) DEFAULT 0.00');
        ensureColumnExists($conn, 'users', 'accuracy', 'DECIMAL(5,2) DEFAULT 0.00');
        ensureColumnExists($conn, 'users', 'total_tasks', 'INT DEFAULT 0');
        ensureColumnExists($conn, 'users', 'bronze_unlocked', 'TINYINT(1) DEFAULT 0');
        ensureColumnExists($conn, 'users', 'silver_unlocked', 'TINYINT(1) DEFAULT 0');
        ensureColumnExists($conn, 'users', 'gold_unlocked', 'TINYINT(1) DEFAULT 0');
        ensureColumnExists($conn, 'users', 'platinum_unlocked', 'TINYINT(1) DEFAULT 0');
        ensureColumnExists($conn, 'users', 'invite_code_used', 'VARCHAR(50) NULL');
        ensureColumnExists($conn, 'users', 'referred_by', 'INT NULL');
        ensureColumnExists($conn, 'users', 'is_blocked', 'TINYINT(1) DEFAULT 0');
        ensureColumnExists($conn, 'users', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
        ensureColumnExists($conn, 'users', 'is_active', 'TINYINT(1) DEFAULT 1');
        ensureColumnExists($conn, 'users', 'status', "VARCHAR(20) DEFAULT 'active'");
        ensureColumnExists($conn, 'users', 'role', "VARCHAR(30) DEFAULT 'user'");
        ensureColumnExists($conn, 'users', 'invitation_code', 'VARCHAR(50) NULL');
        ensureColumnExists($conn, 'users', 'invitation_code_used', 'VARCHAR(50) NULL');
        ensureColumnExists($conn, 'users', 'employee_id', 'INT NULL');

        $createInvitationCodesSql = "
            CREATE TABLE IF NOT EXISTS invitation_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL UNIQUE,
                reward DECIMAL(10,2) DEFAULT 0.00,
                starting_balance DECIMAL(10,2) DEFAULT 0.00,
                used_count INT DEFAULT 0,
                max_uses INT DEFAULT 1,
                uses_remaining INT DEFAULT 1,
                is_active TINYINT(1) DEFAULT 1,
                active TINYINT(1) DEFAULT 1,
                employee_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        $invitationExists = (bool)$conn->query("SHOW TABLES LIKE 'invitation_codes'")->fetchColumn();
        if ($invitationExists) {
            try {
                $conn->query("SELECT id FROM invitation_codes LIMIT 1");
            } catch (Throwable $brokenInvitationTable) {
                $conn->exec("DROP TABLE IF EXISTS invitation_codes");
            }
        }
        $conn->exec($createInvitationCodesSql);

        ensureColumnExists($conn, 'invitation_codes', 'starting_balance', 'DECIMAL(10,2) DEFAULT 0.00');
        ensureColumnExists($conn, 'invitation_codes', 'used_count', 'INT DEFAULT 0');
        ensureColumnExists($conn, 'invitation_codes', 'max_uses', 'INT DEFAULT 1');
        ensureColumnExists($conn, 'invitation_codes', 'uses_remaining', 'INT DEFAULT 1');
        ensureColumnExists($conn, 'invitation_codes', 'is_active', 'TINYINT(1) DEFAULT 1');
        ensureColumnExists($conn, 'invitation_codes', 'active', 'TINYINT(1) DEFAULT 1');
        ensureColumnExists($conn, 'invitation_codes', 'employee_id', 'INT NULL');
    }

    function ensureHandToGlobalRuntimeSchema() {
        static $done = false;

        if ($done) {
            return;
        }

        $done = true;

        try {
            $conn = getConnection();
            ensureAuthSchema();
            createUserLevelsTable();

            $withdrawalsExists = (bool)$conn->query("SHOW TABLES LIKE 'withdrawals'")->fetchColumn();
            if ($withdrawalsExists) {
                try {
                    $conn->query("SELECT id FROM withdrawals LIMIT 1");
                } catch (Throwable $brokenWithdrawalsTable) {
                    $conn->exec("DROP TABLE IF EXISTS withdrawals");
                }
            }

            $conn->exec("
                CREATE TABLE IF NOT EXISTS withdrawals (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    wallet_address TEXT NOT NULL,
                    status ENUM('Pending','Approved','Rejected','Completed') DEFAULT 'Pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    asset VARCHAR(20) DEFAULT 'USDT',
                    network VARCHAR(20) DEFAULT 'TRC20',
                    memo_tag VARCHAR(255) NULL,
                    approved_by INT NULL,
                    approved_at DATETIME NULL,
                    rejected_by INT NULL,
                    rejected_at DATETIME NULL,
                    deleted_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $columns = $conn->query("SHOW COLUMNS FROM withdrawals")->fetchAll(PDO::FETCH_COLUMN);
            $addColumn = function ($name, $definition) use ($conn, $columns) {
                if (!in_array($name, $columns, true)) {
                    $conn->exec("ALTER TABLE withdrawals ADD COLUMN {$name} {$definition}");
                }
            };

            $addColumn('admin_note', 'TEXT NULL');
            $addColumn('recipient_name', 'VARCHAR(255) NULL');
            $addColumn('processed_at', 'DATETIME NULL');
            $addColumn('processed_by', 'INT NULL');
        } catch (Throwable $e) {
            error_log('Runtime schema check failed: ' . $e->getMessage());
        }
    }
}

ensureHandToGlobalRuntimeSchema();
