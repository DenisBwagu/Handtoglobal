<?php
/**
 * Fix get_setting.php includes - Replace all old includes with new settings_helpers.php
 * This script fixes the function redeclaration error
 */

echo "=== FIXING GET_SETTING.PHP INCLUDES ===\n\n";

// Get all PHP files that include get_setting.php
$files = [
    'admin/combos.php',
    'admin/combo_create.php',
    'admin/combo_edit.php',
    'admin/contact_create.php',
    'admin/contact_edit.php',
    'admin/contact_view.php',
    'admin/contacts.php',
    'admin/employee_create.php',
    'admin/employee_view.php',
    'admin/employees.php',
    'admin/finance_analysis.php',
    'admin/get_level_stats.php',
    'admin/get_tasks_by_level.php',
    'admin/invitation-codes.php',
    'admin/languages.php',
    'admin/levels.php',
    'admin/levels_create.php',
    'admin/levels_edit.php',
    'admin/task_create.php',
    'admin/task_edit.php',
    'admin/tasks.php',
    'admin/testimonial_create.php',
    'admin/testimonial_edit.php',
    'admin/testimonials.php',
    'admin/user_view.php',
    'admin/users.php',
    'admin/view_withdrawal.php',
    'admin/withdrawals.php',
    'admin_level_actions.php'
];

$fixed_count = 0;
$error_count = 0;

foreach ($files as $file) {
    $filepath = __DIR__ . '/' . $file;
    
    if (file_exists($filepath)) {
        $content = file_get_contents($filepath);
        
        // Check if it includes old get_setting.php
        if (strpos($content, "require_once '../get_setting.php';") !== false) {
            // Replace with new settings_helpers.php
            $content = str_replace("require_once '../get_setting.php';", "require_once '../includes/settings_helpers.php';", $content);
            
            // Write back to file
            if (file_put_contents($filepath, $content)) {
                echo "✅ FIXED: $file\n";
                $fixed_count++;
            } else {
                echo "❌ ERROR: Could not write to $file\n";
                $error_count++;
            }
        } else {
            echo "ℹ️  OK: $file (already using new helpers)\n";
        }
    } else {
        echo "⚠️  NOT FOUND: $file\n";
        $error_count++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Fixed: $fixed_count files\n";
echo "Errors: $error_count files\n";
echo "Total: " . ($fixed_count + $error_count) . " files processed\n";

if ($error_count === 0) {
    echo "\n🎉 ALL FILES FIXED SUCCESSFULLY!\n";
    echo "The function redeclaration error should now be resolved.\n";
} else {
    echo "\n⚠️  Some files had errors. Please check manually.\n";
}

echo "\n🎯 NEXT STEPS:\n";
echo "1. Test admin pages to ensure no redeclaration errors\n";
echo "2. Check that settings still work correctly\n";
echo "3. Verify all admin functionality is intact\n";
?>
