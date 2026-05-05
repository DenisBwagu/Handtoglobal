<?php
require_once 'config.php';
require_once '../includes/settings_helpers.php';

// Test language system
echo "<h2>Language System Test</h2>";

// Test Greek
$_SESSION['language'] = 'greek';
echo "<h3>Greek Language Test:</h3>";
echo "Dashboard: " . get_translation('dashboard', 'Dashboard') . "<br>";
echo "Tasks: " . get_translation('tasks', 'Tasks') . "<br>";
echo "Settings: " . get_translation('settings', 'Settings') . "<br>";
echo "Save: " . get_translation('save', 'Save') . "<br>";

// Test German
$_SESSION['language'] = 'german';
echo "<h3>German Language Test:</h3>";
echo "Dashboard: " . get_translation('dashboard', 'Dashboard') . "<br>";
echo "Tasks: " . get_translation('tasks', 'Tasks') . "<br>";
echo "Settings: " . get_translation('settings', 'Settings') . "<br>";
echo "Save: " . get_translation('save', 'Save') . "<br>";

// Test Ukrainian
$_SESSION['language'] = 'ukrainian';
echo "<h3>Ukrainian Language Test:</h3>";
echo "Dashboard: " . get_translation('dashboard', 'Dashboard') . "<br>";
echo "Tasks: " . get_translation('tasks', 'Tasks') . "<br>";
echo "Settings: " . get_translation('settings', 'Settings') . "<br>";
echo "Save: " . get_translation('save', 'Save') . "<br>";

// Test English (default)
$_SESSION['language'] = 'english';
echo "<h3>English Language Test:</h3>";
echo "Dashboard: " . get_translation('dashboard', 'Dashboard') . "<br>";
echo "Tasks: " . get_translation('tasks', 'Tasks') . "<br>";
echo "Settings: " . get_translation('settings', 'Settings') . "<br>";
echo "Save: " . get_translation('save', 'Save') . "<br>";

echo "<h3>Settings from Database:</h3>";
echo "User Locale: " . getSetting('user_locale', 'english') . "<br>";
echo "Admin Locale: " . getSetting('admin_locale', 'english') . "<br>";
?>
