<?php
// Start session first
if (session_status() == PHP_SESSION_NONE) {
    $sessionCandidates = [
        sys_get_temp_dir(),
        __DIR__ . DIRECTORY_SEPARATOR . 'tmp_sessions',
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

if (!function_exists('htg_env')) {
    function htg_env($key, $default = '') {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }
}

if (!defined('APP_ENV')) define('APP_ENV', htg_env('APP_ENV', 'production'));
if (!defined('HTG_DEBUG')) define('HTG_DEBUG', APP_ENV !== 'production' && htg_env('HTG_DEBUG', '0') === '1');

error_reporting(E_ALL);
ini_set('display_errors', HTG_DEBUG ? '1' : '0');
ini_set('display_startup_errors', HTG_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

if (!function_exists('htg_debug_log')) {
    function htg_debug_log($message) {
        if (defined('HTG_DEBUG') && HTG_DEBUG) {
            error_log($message);
        }
    }
}

if (!function_exists('htg_app_base_url')) {
    function htg_app_base_url() {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $root = rtrim(str_replace('\\', '/', dirname(__FILE__)), '/');
        $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

        if ($docRoot !== '' && strpos($root, $docRoot) === 0) {
            $base = substr($root, strlen($docRoot));
            return rtrim('/' . trim($base, '/'), '/') . '/';
        }

        $parts = explode('/', trim($scriptName, '/'));
        if (isset($parts[0]) && $parts[0] !== '' && $parts[0] !== 'admin') {
            return '/' . $parts[0] . '/';
        }

        return '/';
    }
}

// Database constants
if (!defined('DB_HOST')) define('DB_HOST', htg_env('DB_HOST', 'localhost'));
if (!defined('DB_PORT')) define('DB_PORT', htg_env('DB_PORT', '3306'));
if (!defined('DB_NAME')) define('DB_NAME', htg_env('DB_NAME', 'handtoglobal'));
if (!defined('DB_USER')) define('DB_USER', htg_env('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', htg_env('DB_PASS', ''));

// App constants
if (!defined('TELEGRAM_SUPPORT')) define('TELEGRAM_SUPPORT', 'https://t.me/chica256');
if (!defined('DAILY_TASK_LIMIT')) define('DAILY_TASK_LIMIT', 40);

if (!function_exists('normalizeLevelName')) {
    function normalizeLevelName($level) {
        $value = trim((string)$level);
        $lower = strtolower($value);

        if ($lower === 'silver' || $lower === 'sliver') {
            return 'Silver';
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
            ['id' => null, 'name' => 'Silver', 'sort_order' => 2, 'task_reward' => 1.50, 'number_of_tasks' => 40, 'task_type' => 'Name_items'],
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

if (!function_exists('getConnection')) {
    function getConnection() {
        static $conn = null;

        if ($conn !== null) {
            return $conn;
        }

        try {
            $conn = new PDO(
                "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS
            );
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $conn;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            exit('Service temporarily unavailable.');
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
            $stmt = $conn->prepare("UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$value, $key]);
            if ($stmt->rowCount() > 0) {
                return true;
            }

            $check = $conn->prepare("SELECT setting_key FROM settings WHERE setting_key = ? LIMIT 1");
            $check->execute([$key]);
            if ($check->fetchColumn()) {
                return true;
            }

            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, created_at, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            return $stmt->execute([$key, $value]);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('getSiteSettings')) {
    function getSiteSettings() {
        return [
            'site_name' => getSetting('site_name', getSetting('SiteName', 'Hand to Global')),
            'support_email' => getSetting('SupportEmail', 'support@handtoglobal.com'),
            'telegram_link' => getSetting('TelegramLink', ''),
        ];
    }
}

// Language System
if (!function_exists('get_current_language')) {
    function get_current_language() {
        if (function_exists('current_language')) {
            return current_language();
        }

        if (!empty($_SESSION['language'])) {
            return $_SESSION['language'];
        }

        if (!empty($_COOKIE['htg_language'])) {
            return $_COOKIE['htg_language'];
        }

        $contextKey = !empty($_SESSION['admin_id']) && ($_SESSION['role'] ?? '') === 'admin' ? 'admin_locale' : 'user_locale';
        return getSetting($contextKey, 'english');
    }
}

if (!function_exists('set_language')) {
    function set_language($language) {
        if (function_exists('normalize_language_code')) {
            $language = normalize_language_code($language);
        }

        $_SESSION['language'] = $language;
        setcookie('htg_language', $language, time() + 31536000, '/');

        $contextKey = !empty($_SESSION['admin_id']) && ($_SESSION['role'] ?? '') === 'admin' ? 'admin_locale' : 'user_locale';
        setSetting($contextKey, $language);
    }
}

if (!function_exists('get_translation')) {
    function get_translation($key, $fallback = '') {
        static $translations = [];
        $language = get_current_language();

        if (!isset($translations[$language])) {
            $translations[$language] = load_language_translations($language);
        }

        return $translations[$language][$key] ?? ($fallback !== '' ? $fallback : $key);
    }
}

if (!function_exists('__t')) {
    function __t($key, $fallback = '') {
        return get_translation($key, $fallback);
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

if (!function_exists('htg_table_is_usable')) {
    function htg_table_is_usable($table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$table)) {
            return false;
        }

        try {
            $conn = getConnection();
            $stmt = $conn->prepare('
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ');
            $stmt->execute([DB_NAME, $table]);

            if ((int)$stmt->fetchColumn() === 0) {
                return false;
            }

            $conn->query("SELECT 1 FROM `$table` LIMIT 1");
            return true;
        } catch (Throwable $e) {
            error_log("Database table check failed for $table: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return getSetting($key, $default);
    }
}

if (!function_exists('update_setting')) {
    function update_setting($key, $value, $type = 'text') {
        return setSetting($key, $value);
    }
}

function createNotification($userId, $title, $message) {
    if (!htg_table_is_usable('notifications')) {
        return false;
    }

    $conn = getConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    return $stmt->execute([$userId, $title, $message]);
}

function getUnreadNotifications($userId) {
    if (!htg_table_is_usable('notifications')) {
        return [];
    }

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
            htg_debug_log("DEBUG: Attempting to unlock level $level for user_id: $userId");
            
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
                htg_debug_log("DEBUG: Successfully unlocked level $level in user_levels table for user_id: $userId");
            } else {
                htg_debug_log("DEBUG: Failed to unlock level $level in user_levels table for user_id: $userId");
                $result = false;
            }
            
            // 2. Update users table columns for backward compatibility
            if ($userLevelsResult) {
                $levelField = strtolower($level) . '_unlocked';
                if ($level === 'Silver') {
                    $levelField = 'silver_unlocked';
                } elseif ($level === 'VIP 1') {
                    $levelField = 'platinum_unlocked';
                }
                
                $stmt = $conn->prepare("UPDATE users SET $levelField = 1 WHERE id = ?");
                $usersResult = $stmt->execute([$userId]);
                
                if ($usersResult) {
                    htg_debug_log("DEBUG: Successfully unlocked level $level in users table ($levelField) for user_id: $userId");
                } else {
                    htg_debug_log("DEBUG: Failed to unlock level $level in users table ($levelField) for user_id: $userId");
                    // Don't fail the whole operation if users table update fails
                }
            }
            
            // 3. Update user's current level if this is higher than their current
            if ($result && $userLevelsResult) {
                $stmt = $conn->prepare("UPDATE users SET level = ? WHERE id = ?");
                $stmt->execute([$level, $userId]);
                htg_debug_log("DEBUG: Updated user's current level to $level for user_id: $userId");
            }
            
            htg_debug_log("DEBUG: Final unlock result for level $level, user_id: $userId: " . ($result ? 'SUCCESS' : 'FAILED'));
            return $result;
            
        } catch (PDOException $e) {
            htg_debug_log("DEBUG: Error unlocking level: " . $e->getMessage());
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
            
            // 1. Delete completed tasks for this user and level only
            $stmt = $conn->prepare("
                DELETE ct FROM completed_tasks ct
                INNER JOIN tasks t ON ct.task_id = t.id
                WHERE ct.user_id = ? AND t.level = ?
            ");
            $stmt->execute([$userId, $level]);
            
            // 2. Lock the level in user_levels table
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
            
            // 3. Lock the level in users table (specific column)
            $levelField = strtolower($level) . '_unlocked';
            if ($level === 'Silver') {
                $levelField = 'silver_unlocked';
            } elseif ($level === 'VIP 1') {
                $levelField = 'platinum_unlocked';
            }
            
            $stmt = $conn->prepare("UPDATE users SET $levelField = 0 WHERE id = ?");
            $stmt->execute([$userId]);
            
            // 4. DO NOT change user balance or affect other levels
            // DO NOT reset user to Bronze level
            // Only the specified level is affected
            
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
                htg_debug_log("DEBUG: Bronze level - always unlocked for user_id: $userId");
                return true;
            }
            
            $conn = getConnection();
            
            // DEBUG: Log the check
            htg_debug_log("DEBUG: Checking unlock status for user_id: $userId, level: $level");
            
            $isUnlocked = false;
            
            $levelAliases = [$level];
            if ($level === 'Silver') {
                $levelAliases[] = 'Sliver';
            } elseif ($level === 'VIP 1') {
                $levelAliases[] = 'VIP';
                $levelAliases[] = 'Platinum';
                $levelAliases[] = 'VIP / Platinum';
            }
            $levelAliases = array_values(array_unique($levelAliases));
            $levelPlaceholders = implode(',', array_fill(0, count($levelAliases), '?'));

            // Check user_levels table first
            $stmt = $conn->prepare("
                SELECT is_unlocked FROM user_levels 
                WHERE user_id = ? AND level IN ($levelPlaceholders)
                LIMIT 1
            ");
            $stmt->execute(array_merge([$userId], $levelAliases));
            $userLevelResult = $stmt->fetch();
            
            if ($userLevelResult !== false && (int)$userLevelResult['is_unlocked'] === 1) {
                $isUnlocked = true;
                htg_debug_log("DEBUG: Found unlocked in user_levels table for $level");
            }
            
            // Also check users table for backward compatibility
            if (!$isUnlocked) {
                $levelField = strtolower($level) . '_unlocked';
                if ($level === 'Silver') {
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
                    htg_debug_log("DEBUG: Found unlocked in users table - $levelField: 1");
                }
            }
            
            htg_debug_log("DEBUG: Final unlock status for $level: " . ($isUnlocked ? 'UNLOCKED' : 'LOCKED'));
            return $isUnlocked;
            
        } catch (PDOException $e) {
            htg_debug_log("DEBUG: Error checking level unlock status: " . $e->getMessage());
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
            $levelAliases = [$level];
            if ($level === 'Silver') {
                $levelAliases[] = 'Sliver';
            } elseif ($level === 'VIP 1') {
                $levelAliases[] = 'VIP';
                $levelAliases[] = 'Platinum';
                $levelAliases[] = 'VIP / Platinum';
            }
            $levelAliases = array_values(array_unique($levelAliases));
            $levelPlaceholders = implode(',', array_fill(0, count($levelAliases), '?'));
            
            // Get completed tasks count
            $stmt = $conn->prepare("
                SELECT COUNT(*) as completed
                FROM completed_tasks ct
                INNER JOIN tasks t ON ct.task_id = t.id
                WHERE ct.user_id = ? AND (t.level IN ($levelPlaceholders) OR ct.level IN ($levelPlaceholders))
            ");
            $stmt->execute(array_merge([$userId], $levelAliases, $levelAliases));
            $result = $stmt->fetch();
            $completed = $result['completed'] ?? 0;
            
            // Get total tasks for level
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total FROM tasks WHERE level IN ($levelPlaceholders) AND active = 1
            ");
            $stmt->execute($levelAliases);
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

    function htgTableColumns(PDO $conn, $table) {
        $stmt = $conn->query("SHOW COLUMNS FROM `$table`");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    function ensureUserLimitsSchema(PDO $conn = null) {
        $conn = $conn ?: getConnection();

        $conn->exec("
            CREATE TABLE IF NOT EXISTS user_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                max_levels_per_day INT DEFAULT 3,
                min_withdrawal_amount DECIMAL(10,2) DEFAULT 10.00,
                min_withdrawal_level VARCHAR(50) DEFAULT 'Bronze',
                min_balance DECIMAL(10,2) DEFAULT 0.00,
                min_balance_floor DECIMAL(10,2) DEFAULT 0.00,
                custom_message TEXT NULL,
                daily_task_limit INT DEFAULT 40,
                withdrawal_limit DECIMAL(10,2) DEFAULT 1000.00,
                min_withdrawal DECIMAL(10,2) DEFAULT 10.00,
                max_withdrawal DECIMAL(10,2) DEFAULT 1000.00,
                can_withdraw TINYINT(1) DEFAULT 1,
                can_submit_tasks TINYINT(1) DEFAULT 1,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_limits (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        ensureColumnExists($conn, 'user_limits', 'max_levels_per_day', 'INT DEFAULT 3');
        ensureColumnExists($conn, 'user_limits', 'min_withdrawal_amount', 'DECIMAL(10,2) DEFAULT 10.00');
        ensureColumnExists($conn, 'user_limits', 'min_withdrawal_level', "VARCHAR(50) DEFAULT 'Bronze'");
        ensureColumnExists($conn, 'user_limits', 'min_balance', 'DECIMAL(10,2) DEFAULT 0.00');
        ensureColumnExists($conn, 'user_limits', 'min_balance_floor', 'DECIMAL(10,2) DEFAULT 0.00');
        ensureColumnExists($conn, 'user_limits', 'custom_message', 'TEXT NULL');
        ensureColumnExists($conn, 'user_limits', 'daily_task_limit', 'INT DEFAULT 40');
        ensureColumnExists($conn, 'user_limits', 'withdrawal_limit', 'DECIMAL(10,2) DEFAULT 1000.00');
        ensureColumnExists($conn, 'user_limits', 'min_withdrawal', 'DECIMAL(10,2) DEFAULT 10.00');
        ensureColumnExists($conn, 'user_limits', 'max_withdrawal', 'DECIMAL(10,2) DEFAULT 1000.00');
        ensureColumnExists($conn, 'user_limits', 'can_withdraw', 'TINYINT(1) DEFAULT 1');
        ensureColumnExists($conn, 'user_limits', 'can_submit_tasks', 'TINYINT(1) DEFAULT 1');
        ensureColumnExists($conn, 'user_limits', 'is_active', 'TINYINT(1) DEFAULT 1');
        ensureColumnExists($conn, 'user_limits', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
        ensureColumnExists($conn, 'user_limits', 'updated_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }

    function getUserLimitsForUser($userId, PDO $conn = null) {
        $conn = $conn ?: getConnection();
        ensureUserLimitsSchema($conn);

        $stmt = $conn->prepare("SELECT * FROM user_limits WHERE user_id = ? LIMIT 1");
        $stmt->execute([(int)$userId]);
        $limits = $stmt->fetch(PDO::FETCH_ASSOC);
        $exists = (bool)$limits;
        $limits = $limits ?: [];

        $limits['_exists'] = $exists;
        $limits['max_levels_per_day'] = (int)($limits['max_levels_per_day'] ?? 3);
        $limits['min_withdrawal_amount'] = (float)($limits['min_withdrawal_amount'] ?? ($limits['min_withdrawal'] ?? 10.00));
        $limits['min_withdrawal_level'] = $limits['min_withdrawal_level'] ?? 'Bronze';
        $limits['min_balance'] = (float)($limits['min_balance'] ?? ($limits['min_balance_floor'] ?? 0.00));
        $limits['custom_message'] = $limits['custom_message'] ?? '';
        $limits['can_withdraw'] = (int)($limits['can_withdraw'] ?? 1);
        $limits['can_submit_tasks'] = (int)($limits['can_submit_tasks'] ?? 1);

        return $limits;
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
        $conn->exec("ALTER TABLE admins MODIFY COLUMN password VARCHAR(255) NOT NULL");

        $stmt = $conn->prepare("SELECT id, password FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute(['admin@handtoglobal.com']);
        $admin = $stmt->fetch();
        if (!$admin) {
            $allowDefaultAdmin = APP_ENV !== 'production' || htg_env('HTG_CREATE_DEFAULT_ADMIN', '0') === '1';
            if ($allowDefaultAdmin) {
                $stmt = $conn->prepare("INSERT INTO admins (name, email, password) VALUES (?, ?, ?)");
                $stmt->execute(['Admin', 'admin@handtoglobal.com', password_hash('admin123', PASSWORD_DEFAULT)]);
            }
        } else {
            $stmt = $conn->prepare("UPDATE admins SET name = COALESCE(NULLIF(name, ''), 'Admin') WHERE email = ?");
            $stmt->execute(['admin@handtoglobal.com']);
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
        $conn->exec("ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NOT NULL");
        ensureColumnExists($conn, 'users', 'balance', 'DECIMAL(10,2) DEFAULT 0.00');
        ensureColumnExists($conn, 'users', 'level', "VARCHAR(50) DEFAULT 'Bronze'");
        ensureColumnExists($conn, 'users', 'rating', 'DECIMAL(5,2) DEFAULT 0.00');
        ensureColumnExists($conn, 'users', 'accuracy', 'DECIMAL(5,2) DEFAULT 0.00');
        ensureColumnExists($conn, 'users', 'total_tasks', 'INT DEFAULT 0');
        ensureColumnExists($conn, 'users', 'bronze_unlocked', 'TINYINT(1) DEFAULT 0');
        ensureColumnExists($conn, 'users', 'silver_unlocked', 'TINYINT(1) DEFAULT 0');
        ensureColumnExists($conn, 'users', 'gold_unlocked', 'TINYINT(1) DEFAULT 0');
        ensureColumnExists($conn, 'users', 'platinum_unlocked', 'TINYINT(1) DEFAULT 0');
        ensureColumnExists($conn, 'users', 'vip1_unlocked', 'TINYINT(1) DEFAULT 0');
        ensureColumnExists($conn, 'users', 'vip2_unlocked', 'TINYINT(1) DEFAULT 0');
        ensureColumnExists($conn, 'users', 'vip3_unlocked', 'TINYINT(1) DEFAULT 0');
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

            $conn->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) NOT NULL UNIQUE,
                    setting_value TEXT NULL,
                    setting_type VARCHAR(50) DEFAULT 'text',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $conn->exec("
                CREATE TABLE IF NOT EXISTS levels (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(50) NOT NULL,
                    description TEXT NULL,
                    reward DECIMAL(10,2) DEFAULT 0.00,
                    task_reward DECIMAL(10,2) DEFAULT 0.00,
                    tasks INT DEFAULT 40,
                    daily_task_limit INT DEFAULT 40,
                    deposit_amount DECIMAL(10,2) DEFAULT 0.00,
                    task_type VARCHAR(100) DEFAULT 'Name_items',
                    icon VARCHAR(100) NULL,
                    sort_order INT DEFAULT 0,
                    active TINYINT(1) DEFAULT 1,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $conn->exec("
                CREATE TABLE IF NOT EXISTS tasks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    description TEXT NULL,
                    instructions TEXT NULL,
                    image VARCHAR(255) NULL,
                    level VARCHAR(50) DEFAULT 'Bronze',
                    reward DECIMAL(10,2) DEFAULT 0.00,
                    active TINYINT(1) DEFAULT 1,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $conn->exec("
                CREATE TABLE IF NOT EXISTS completed_tasks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    task_id INT NOT NULL,
                    level VARCHAR(50) NULL,
                    reward DECIMAL(10,2) DEFAULT 0.00,
                    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_task (user_id, task_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $conn->exec("
                CREATE TABLE IF NOT EXISTS deposits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    status VARCHAR(30) DEFAULT 'Pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $conn->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NULL,
                    is_read TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $conn->exec("
                CREATE TABLE IF NOT EXISTS combos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    title VARCHAR(255) NULL,
                    message TEXT NULL,
                    level VARCHAR(50) DEFAULT 'Bronze',
                    amount DECIMAL(10,2) DEFAULT 0.00,
                    multiplier DECIMAL(10,2) DEFAULT 1.00,
                    start_task INT DEFAULT 1,
                    end_task INT DEFAULT 1,
                    status VARCHAR(30) DEFAULT 'Active',
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $conn->exec("
                CREATE TABLE IF NOT EXISTS user_combo_status (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    combo_id INT NOT NULL,
                    status VARCHAR(30) DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_combo (user_id, combo_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            ensureUserLimitsSchema($conn);

            ensureColumnExists($conn, 'settings', 'setting_type', "VARCHAR(50) DEFAULT 'text'");
            ensureColumnExists($conn, 'levels', 'reward', 'DECIMAL(10,2) DEFAULT 0.00');
            ensureColumnExists($conn, 'levels', 'task_reward', 'DECIMAL(10,2) DEFAULT 0.00');
            ensureColumnExists($conn, 'levels', 'tasks', 'INT DEFAULT 40');
            ensureColumnExists($conn, 'levels', 'daily_task_limit', 'INT DEFAULT 40');
            ensureColumnExists($conn, 'levels', 'deposit_amount', 'DECIMAL(10,2) DEFAULT 0.00');
            ensureColumnExists($conn, 'levels', 'task_type', "VARCHAR(100) DEFAULT 'Name_items'");
            ensureColumnExists($conn, 'levels', 'icon', 'VARCHAR(100) NULL');
            ensureColumnExists($conn, 'levels', 'sort_order', 'INT DEFAULT 0');
            ensureColumnExists($conn, 'levels', 'active', 'TINYINT(1) DEFAULT 1');
            ensureColumnExists($conn, 'levels', 'is_active', 'TINYINT(1) DEFAULT 1');
            ensureColumnExists($conn, 'tasks', 'instructions', 'TEXT NULL');
            ensureColumnExists($conn, 'tasks', 'image', 'VARCHAR(255) NULL');
            ensureColumnExists($conn, 'tasks', 'level', "VARCHAR(50) DEFAULT 'Bronze'");
            ensureColumnExists($conn, 'tasks', 'type', "VARCHAR(100) DEFAULT 'Name_items'");
            ensureColumnExists($conn, 'tasks', 'external_link', 'VARCHAR(500) NULL');
            ensureColumnExists($conn, 'tasks', 'correct_answer', 'VARCHAR(255) NULL');
            ensureColumnExists($conn, 'tasks', 'reward', 'DECIMAL(10,2) DEFAULT 0.00');
            ensureColumnExists($conn, 'tasks', 'active', 'TINYINT(1) DEFAULT 1');
            ensureColumnExists($conn, 'tasks', 'is_active', 'TINYINT(1) DEFAULT 1');
            ensureColumnExists($conn, 'tasks', 'updated_at', 'TIMESTAMP NULL DEFAULT NULL');
            ensureColumnExists($conn, 'completed_tasks', 'level', 'VARCHAR(50) NULL');
            ensureColumnExists($conn, 'completed_tasks', 'answer', 'TEXT NULL');
            ensureColumnExists($conn, 'completed_tasks', 'reward', 'DECIMAL(10,2) DEFAULT 0.00');
            ensureColumnExists($conn, 'deposits', 'status', "VARCHAR(30) DEFAULT 'Pending'");
            ensureColumnExists($conn, 'notifications', 'is_read', 'TINYINT(1) DEFAULT 0');
            ensureColumnExists($conn, 'combos', 'user_id', 'INT NULL');
            ensureColumnExists($conn, 'combos', 'message', 'TEXT NULL');
            ensureColumnExists($conn, 'combos', 'level', "VARCHAR(50) DEFAULT 'Bronze'");
            ensureColumnExists($conn, 'combos', 'amount', 'DECIMAL(10,2) DEFAULT 0.00');
            ensureColumnExists($conn, 'combos', 'multiplier', 'DECIMAL(10,2) DEFAULT 1.00');
            ensureColumnExists($conn, 'combos', 'start_task', 'INT DEFAULT 1');
            ensureColumnExists($conn, 'combos', 'end_task', 'INT DEFAULT 1');
            ensureColumnExists($conn, 'combos', 'start_task_id', 'INT NULL');
            ensureColumnExists($conn, 'combos', 'end_task_id', 'INT NULL');
            ensureColumnExists($conn, 'combos', 'deposit_amount', 'DECIMAL(10,2) DEFAULT 0.00');
            ensureColumnExists($conn, 'combos', 'status', "VARCHAR(30) DEFAULT 'Active'");
            ensureColumnExists($conn, 'combos', 'is_active', 'TINYINT(1) DEFAULT 1');

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
            $addColumn('note', 'TEXT NULL');
            $addColumn('coin_asset', "VARCHAR(20) DEFAULT 'USDT'");
            $addColumn('recipient_name', 'VARCHAR(255) NULL');
            $addColumn('processed_at', 'DATETIME NULL');
            $addColumn('processed_by', 'INT NULL');

            $conn->exec("
                CREATE TABLE IF NOT EXISTS finance_activities (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    admin_id INT NULL,
                    type VARCHAR(50) NOT NULL,
                    category VARCHAR(100) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    reason TEXT NULL,
                    balance_after DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    source_table VARCHAR(100) NULL,
                    source_id INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_finance_user (user_id),
                    INDEX idx_finance_type (type),
                    INDEX idx_finance_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $conn->exec("
                CREATE TABLE IF NOT EXISTS languages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(10) NOT NULL UNIQUE,
                    name VARCHAR(100) NOT NULL,
                    native_name VARCHAR(100) NOT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    is_default TINYINT(1) DEFAULT 0,
                    flag_icon VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $defaultLanguages = [
                ['english', 'English', 'English', 1],
                ['greek', 'Greek', 'Ελληνικά', 0],
                ['german', 'German', 'Deutsch', 0],
                ['ukrainian', 'Ukrainian', 'Українська', 0],
                ['chinese', 'Chinese', '中文', 0],
            ];
            $stmt = $conn->prepare("
                INSERT INTO languages (code, name, native_name, is_default, is_active)
                VALUES (?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE name = VALUES(name), native_name = VALUES(native_name)
            ");
            foreach ($defaultLanguages as $language) {
                $stmt->execute($language);
            }

            $conn->exec("
                CREATE TABLE IF NOT EXISTS translations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    language_code VARCHAR(10) NOT NULL,
                    translation_key VARCHAR(255) NOT NULL,
                    translation_value TEXT NOT NULL,
                    module VARCHAR(50) DEFAULT 'general',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_translation (language_code, translation_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $conn->exec("
                CREATE TABLE IF NOT EXISTS employees (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NULL,
                    fullname VARCHAR(100) NULL,
                    email VARCHAR(150) NULL UNIQUE,
                    phone VARCHAR(20) NULL,
                    role VARCHAR(50) DEFAULT 'Employee',
                    status ENUM('Active','Inactive') DEFAULT 'Active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            ensureColumnExists($conn, 'employees', 'fullname', 'VARCHAR(100) NULL');
            ensureColumnExists($conn, 'employees', 'phone', 'VARCHAR(20) NULL');
            ensureColumnExists($conn, 'employees', 'role', "VARCHAR(50) DEFAULT 'Employee'");
            ensureColumnExists($conn, 'employees', 'status', "ENUM('Active','Inactive') DEFAULT 'Active'");

            $conn->exec("
                CREATE TABLE IF NOT EXISTS contacts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    phone VARCHAR(50) NULL,
                    email VARCHAR(150) NULL,
                    employee_id INT NULL,
                    status VARCHAR(50) DEFAULT 'new',
                    registered TINYINT(1) DEFAULT 0,
                    notes TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_contacts_employee (employee_id),
                    INDEX idx_contacts_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $conn->exec("
                CREATE TABLE IF NOT EXISTS testimonials (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    role VARCHAR(100) NULL,
                    content TEXT NOT NULL,
                    image VARCHAR(255) NULL,
                    type VARCHAR(50) DEFAULT 'homepage',
                    rating INT DEFAULT 5,
                    display_order INT DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_testimonials_active (is_active),
                    INDEX idx_testimonials_type (type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Throwable $e) {
            error_log('Runtime schema check failed: ' . $e->getMessage());
        }
    }
}

ensureHandToGlobalRuntimeSchema();
