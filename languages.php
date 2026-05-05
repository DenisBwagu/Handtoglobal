<?php
/**
 * Language Translation System
 */

if (!function_exists('get_current_language')) {
function get_current_language() {
    // Try to get language from settings, fallback to session, then to English
    $language = get_setting('user_locale', 'english');
    
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
        $translations[$language] = load_language_file($language);
    }
    
    // Return translation or fallback
    return $translations[$language][$key] ?? $fallback;
}
}

if (!function_exists('load_language_file')) {
function load_language_file($language) {
    $base_path = __DIR__ . '/translations/';
    
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

/**
 * Helper function to echo translation
 */
if (!function_exists('t')) {
function t($key, $fallback = '') {
    echo get_translation($key, $fallback);
}
}
?>
