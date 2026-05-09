<?php
/**
 * Fix All get_setting.php includes - Replace all old includes with new settings_helpers.php
 * This script fixes the function redeclaration error for ALL files
 */

echo "=== FIXING ALL GET_SETTING.PHP INCLUDES ===\n\n";

// Get all PHP files in the project
$directory = __DIR__;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory),
    RecursiveIteratorIterator::SELF_FIRST
);

$php_files = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $php_files[] = $file->getPathname();
    }
}

$fixed_count = 0;
$error_count = 0;
$skipped_count = 0;

foreach ($php_files as $file) {
    // Skip our fix scripts
    if (strpos($file, 'fix_get_setting_includes.php') !== false || 
        strpos($file, 'fix_all_get_setting_includes.php') !== false) {
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check if it includes old get_setting.php
    if (strpos($content, "require_once '../get_setting.php';") !== false || 
        strpos($content, "require_once 'get_setting.php';") !== false ||
        strpos($content, "require_once './get_setting.php';") !== false ||
        strpos($content, "require_once '../config.php';\nrequire_once '../get_setting.php';") !== false) {
        
        // Replace with new settings_helpers.php
        $new_content = str_replace("require_once '../get_setting.php';", "require_once '../includes/settings_helpers.php';", $content);
        $new_content = str_replace("require_once 'get_setting.php';", "require_once '../includes/settings_helpers.php';", $new_content);
        $new_content = str_replace("require_once './get_setting.php';", "require_once '../includes/settings_helpers.php';", $new_content);
        
        // Handle the case where it's on separate lines
        $new_content = preg_replace(
            '/require_once\s+[\'"]\.\.\/get_setting\.php[\'"];?\s*\n/',
            "require_once '../includes/settings_helpers.php';\n",
            $new_content
        );
        
        // Write back to file
        if (file_put_contents($file, $new_content)) {
            $relative_path = str_replace(__DIR__ . '/', '', $file);
            echo "✅ FIXED: $relative_path\n";
            $fixed_count++;
        } else {
            $relative_path = str_replace(__DIR__ . '/', '', $file);
            echo "❌ ERROR: Could not write to $relative_path\n";
            $error_count++;
        }
    } else {
        $skipped_count++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Fixed: $fixed_count files\n";
echo "Errors: $error_count files\n";
echo "Skipped: $skipped_count files\n";
echo "Total: " . ($fixed_count + $error_count + $skipped_count) . " files processed\n";

if ($error_count === 0) {
    echo "\n🎉 ALL FILES FIXED SUCCESSFULLY!\n";
    echo "The function redeclaration error should now be completely resolved.\n";
} else {
    echo "\n⚠️  Some files had errors. Please check manually.\n";
}

echo "\n🎯 NEXT STEPS:\n";
echo "1. Test admin pages to ensure no redeclaration errors\n";
echo "2. Check that settings still work correctly\n";
echo "3. Verify all admin functionality is intact\n";
echo "4. Test the entire settings system end-to-end\n";
?>
