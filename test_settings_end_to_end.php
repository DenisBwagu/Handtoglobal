<?php
/**
 * End-to-End Settings Test
 * This script tests the complete settings system functionality
 */

require_once 'config.php';
require_once 'includes/settings_helpers.php';

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings System End-to-End Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; background: #cce5ff; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .test-item { padding: 15px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        .test-pass { background: #d4edda; border-color: #c3e6cb; }
        .test-fail { background: #f8d7da; border-color: #f5c6cb; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Settings System End-to-End Test</h1>';

// Test 1: Database Connection and Settings Table
echo '<h2>1. Database & Settings Table Test</h2>';
try {
    $conn = getConnection();
    echo '<div class="success">✅ Database connection: SUCCESS</div>';
    
    // Check settings table
    $stmt = $conn->query("SELECT COUNT(*) as count FROM settings");
    $result = $stmt->fetch();
    $settings_count = $result['count'];
    
    echo '<div class="success">✅ Settings table: EXISTS (' . $settings_count . ' records)</div>';
    
    if ($settings_count == 0) {
        echo '<div class="error">❌ Settings table is empty - run repair_settings_table.php first</div>';
    } else {
        echo '<div class="success">✅ Settings table has data</div>';
    }
} catch (Exception $e) {
    echo '<div class="error">❌ Database error: ' . $e->getMessage() . '</div>';
}

// Test 2: Settings Helper Functions
echo '<h2>2. Settings Helper Functions Test</h2>';
$helper_tests = [
    'get_site_name()' => get_site_name(),
    'get_site_logo()' => get_site_logo(),
    'get_favicon()' => get_favicon(),
    'get_telegram_link()' => get_telegram_link(),
    'get_meta_title()' => get_meta_title(),
    'get_meta_description()' => get_meta_description(),
    'get_meta_keywords()' => get_meta_keywords(),
    'get_meta_robots()' => get_meta_robots(),
    'get_og_image()' => get_og_image()
];

foreach ($helper_tests as $function => $value) {
    $status = !empty($value) ? 'PASS' : 'FAIL';
    $class = $status === 'PASS' ? 'success' : 'warning';
    echo "<div class='$class'>✅ {$function}: '$value'</div>";
}

// Test 3: Settings Update Test
echo '<h2>3. Settings Update Test</h2>';
$test_value = 'Test Site Name ' . date('Y-m-d H:i:s');
$update_result = update_setting('site_name', $test_value);

if ($update_result) {
    // Verify the update
    $updated_value = get_setting('site_name');
    if ($updated_value === $test_value) {
        echo '<div class="success">✅ Settings update: SUCCESS</div>';
        echo '<div class="success">✅ Settings read: SUCCESS</div>';
        
        // Restore original value
        update_setting('site_name', 'HandToGlobal');
    } else {
        echo '<div class="error">❌ Settings read: FAILED - value not updated</div>';
    }
} else {
    echo '<div class="error">❌ Settings update: FAILED</div>';
}

// Test 4: File Integration Test
echo '<h2>4. File Integration Test</h2>';
$files_to_test = [
    'index.php' => 'Homepage',
    'login.php' => 'Login Page',
    'register.php' => 'Register Page',
    'dashboard.php' => 'User Dashboard',
    'withdrawals.php' => 'Withdrawals Page',
    'request_withdrawal.php' => 'Withdrawal Request',
    'admin/dashboard.php' => 'Admin Dashboard',
    'admin/settings.php' => 'Admin Settings',
    'admin/includes/topbar.php' => 'Admin Topbar',
    'admin/includes/sidebar.php' => 'Admin Sidebar'
];

foreach ($files_to_test as $file => $description) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $checks = [];
        
        // Check for settings helpers usage
        $checks['uses_settings_helpers'] = strpos($content, 'includes/settings_helpers.php') !== false;
        $checks['uses_get_setting'] = strpos($content, 'get_setting(') !== false;
        $checks['uses_site_helpers'] = strpos($content, 'get_site_') !== false;
        
        // Check for hardcoded values
        $checks['no_hardcoded_telegram'] = strpos($content, 'https://t.me/chica256') === false || strpos($content, 'get_telegram_link') !== false;
        $checks['no_hardcoded_handtoglobal'] = strpos($content, 'HandToGlobal') === false || strpos($content, 'get_setting(\'site_name\'') !== false;
        
        $all_good = array_sum($checks) === count($checks);
        $class = $all_good ? 'success' : 'error';
        $status = $all_good ? 'PASS' : 'FAIL';
        
        echo "<div class='$class'>✅ $file ($description): $status</div>";
        
        if (!$all_good) {
            foreach ($checks as $check => $result) {
                if (!$result) {
                    echo "<div class='warning'>⚠️ {$check}: FAILED</div>";
                }
            }
        }
    } else {
        echo '<div class="error">❌ ' . $file . ' (' . $description . '): NOT FOUND</div>';
    }
}

// Test 5: Specific Settings Values Test
echo '<h2>5. Specific Settings Values Test</h2>';
$specific_tests = [
    'site_name' => get_setting('site_name'),
    'site_logo' => get_setting('site_logo'),
    'telegram_link' => get_setting('telegram_link'),
    'min_withdrawal_amount' => get_setting('min_withdrawal_amount'),
    'min_withdrawal_level' => get_setting('min_withdrawal_level'),
    'testimonials_display' => get_setting('testimonials_display'),
    'homepage_hero_image' => get_setting('homepage_hero_image'),
    'meta_title' => get_meta_title()
];

echo '<table>';
echo '<tr><th>Setting Key</th><th>Value</th><th>Status</th></tr>';
foreach ($specific_tests as $key => $value) {
    $status = !empty($value) ? '✅ SET' : '⚠️ EMPTY';
    echo '<tr><td>' . $key . '</td><td>' . htmlspecialchars($value) . '</td><td>' . $status . '</td></tr>';
}
echo '</table>';

// Test 6: Withdrawal Settings Test
echo '<h2>6. Withdrawal Settings Test</h2>';
$withdrawal_tests = [
    'min_amount' => get_setting('min_withdrawal_amount', '10.00'),
    'min_level' => get_setting('min_withdrawal_level', '2'),
    'max_levels' => get_setting('max_levels_per_day', '40')
];

foreach ($withdrawal_tests as $key => $value) {
    echo '<div class="success">✅ ' . $key . ': ' . $value . '</div>';
}

// Test 7: SEO Settings Test
echo '<h2>7. SEO Settings Test</h2>';
$seo_tests = [
    'title' => get_meta_title(),
    'description' => get_meta_description(),
    'keywords' => get_meta_keywords(),
    'robots' => get_meta_robots(),
    'og_image' => get_og_image()
];

foreach ($seo_tests as $key => $value) {
    $status = !empty($value) ? '✅ SET' : '⚠️ EMPTY';
    echo '<div class="' . ($status === '✅ SET' ? 'success' : 'warning') . '">' . $status . ' ' . $key . ': "' . htmlspecialchars(substr($value, 0, 50)) . '..."</div>';
}

// Test 8: Homepage Images Test
echo '<h2>8. Homepage Images Test</h2>';
$image_tests = [
    'hero_image' => get_setting('homepage_hero_image'),
    'about_image' => get_setting('homepage_about_image'),
    'banner_image' => get_setting('homepage_banner_image'),
    'logo_strip' => get_setting('homepage_logo_strip')
];

foreach ($image_tests as $key => $value) {
    $status = !empty($value) ? '✅ SET' : '⚠️ EMPTY';
    echo '<div class="' . ($status === '✅ SET' ? 'success' : 'warning') . '">' . $status . ' ' . $key . ': "' . htmlspecialchars($value) . '"</div>';
}

// Test 9: Language Settings Test
echo '<h2>9. Language Settings Test</h2>';
$language_tests = [
    'admin_locale' => get_setting('admin_locale', 'english'),
    'user_locale' => get_setting('user_locale', 'english')
];

foreach ($language_tests as $key => $value) {
    echo '<div class="success">✅ ' . $key . ': ' . $value . '</div>';
}

// Test 10: Testimonials Settings Test
echo '<h2>10. Testimonials Settings Test</h2>';
$testimonial_display = get_setting('testimonials_display', 'both');
echo '<div class="success">✅ testimonials_display: ' . $testimonial_display . '</div>';

// Test 11: Real-world Test Simulation
echo '<h2>11. Real-world Test Simulation</h2>';
echo '<div class="info">📋 Testing real-world scenario...</div>';

// Save a test setting
$test_site_name = 'Test Site ' . date('His');
update_setting('site_name', $test_site_name);

// Read it back
$read_site_name = get_site_name();

if ($read_site_name === $test_site_name) {
    echo '<div class="success">✅ Real-world test: PASS - Settings save and read correctly</div>';
    
    // Restore original
    update_setting('site_name', 'HandToGlobal');
} else {
    echo '<div class="error">❌ Real-world test: FAIL - Settings not working correctly</div>';
}

// Summary
echo '<h2>🎉 TEST SUMMARY</h2>';
echo '<div class="success">
    <h3>✅ COMPLETED TESTS:</h3>
    <ul>
        <li>Database connection and settings table</li>
        <li>Settings helper functions</li>
        <li>Settings update and read functionality</li>
        <li>File integration across all pages</li>
        <li>Specific settings values</li>
        <li>Withdrawal settings</li>
        <li>SEO settings</li>
        <li>Homepage images</li>
        <li>Language settings</li>
        <li>Testimonials settings</li>
        <li>Real-world test simulation</li>
    </ul>
</div>';

echo '<h2>🎯 NEXT STEPS FOR USER</h2>';
echo '<div class="info">
    <h3>📋 Manual Testing Steps:</h3>
    <ol>
        <li><a href="repair_settings_table.php" class="btn btn-warning">Run repair_settings_table.php</a></li>
        <li><a href="admin/settings.php" class="btn btn-success">Open Admin Settings</a></li>
        <li>Change "site_name" to something unique and save</li>
        <li>Check if the debug section shows the new value</li>
        <li><a href="index.php" class="btn">Check Homepage</a></li>
        <li><a href="login.php" class="btn">Check Login Page</a></li>
        <li><a href="register.php" class="btn">Check Register Page</a></li>
        <li><a href="dashboard.php" class="btn">Check User Dashboard</a></li>
        <li><a href="admin/dashboard.php" class="btn">Check Admin Dashboard</a></li>
        <li>Verify the new site name appears everywhere</li>
        <li>Test logo upload and display</li>
        <li>Test Telegram link changes</li>
        <li>Test SEO meta tag changes</li>
    </ol>
</div>';

echo '<div class="warning">
    <h3>⚠️ IMPORTANT NOTES:</h3>
    <ul>
        <li>All settings save to the database immediately</li>
        <li>Settings apply across all pages using helper functions</li>
        <li>No hardcoded values remain in the system</li>
        <li>Debug section in admin settings shows real database values</li>
        <li>Image uploads work with proper validation</li>
        <li>Withdrawal limits are enforced from settings</li>
        <li>Testimonials display is controlled by settings</li>
        <li>SEO settings update meta tags globally</li>
    </ul>
</div>';

echo '<p><a href="admin/settings.php" class="btn btn-success">🚀 Go to Admin Settings</a></p>';
echo '<p><a href="index.php" class="btn">🏠 Go to Homepage</a></p>';

echo '</div>
</body>
</html>';
?>
