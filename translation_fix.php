<?php
/**
 * Translation Fix Tool
 * Scans project PHP files and fixes missing translation keys once and for all languages
 */

echo "=== TRANSLATION FIX TOOL ===\n\n";

// Get all PHP files to scan
$php_files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $php_files[] = $file->getPathname();
    }
}

echo "Scanning " . count($php_files) . " PHP files...\n\n";

$all_translation_keys = [];
$missing_keys_per_language = [];

// Scan all PHP files for translation keys
foreach ($php_files as $file) {
    $content = file_get_contents($file);
    
    // Find all __t() calls
    preg_match_all('/__t\([\'"]([^\'"]+)[\'"]([^\'"]*)[\'"]([^\'"]*)\)/', $content, $matches);
    
    foreach ($matches[0] as $match) {
        $key = $match[2];
        $all_translation_keys[$key] = true;
    }
}

echo "Found " . count($all_translation_keys) . " unique translation keys in use:\n";
foreach ($all_translation_keys as $key => $used) {
    echo "  - $key\n";
}

echo "\nChecking language files...\n\n";

// Language files to check
$language_files = [
    'languages/english.php',
    'languages/chinese.php', 
    'languages/german.php',
    'languages/greek.php',
    'languages/ukrainian.php'
];

foreach ($language_files as $lang_file) {
    if (file_exists($lang_file)) {
        $translations = include $lang_file;
        
        echo "=== $lang_file ===\n";
        echo "Missing keys for " . basename($lang_file) . ":\n";
        
        foreach ($all_translation_keys as $key => $used) {
            if (!isset($translations[$key])) {
                echo "  ❌ $key\n";
                $missing_keys_per_language[basename($lang_file)][] = $key;
            }
        }
        
        echo "\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total translation keys in use: " . count($all_translation_keys) . "\n";

foreach ($missing_keys_per_language as $lang => $keys) {
    if (!empty($keys)) {
        echo "$lang: " . count($keys) . " missing keys\n";
        foreach ($keys as $key) {
            echo "  - $key\n";
        }
        echo "\n";
    }
}

echo "\n=== REPAIR OPTIONS ===\n";
echo "1. Add missing keys to all language files\n";
echo "2. Use English fallback for missing translations\n";
echo "3. Do not change project logic\n";
echo "4. Scan: admin/, includes/, root PHP files\n";

if (isset($_GET['repair']) && $_GET['repair'] === '1') {
    echo "\n=== REPAIRING MISSING KEYS ===\n";
    
    foreach ($language_files as $lang_file) {
        if (file_exists($lang_file) && isset($missing_keys_per_language[basename($lang_file)])) {
            $translations = include $lang_file;
            $content = file_get_contents($lang_file);
            
            // Add missing keys
            $new_content = $content;
            foreach ($missing_keys_per_language[basename($lang_file)] as $key) {
                if (!isset($translations[$key])) {
                    $english_fallback = $all_translation_keys[$key] ? ucfirst(str_replace('_', ' ', $key)) : $key;
                    $new_content .= "    '$key' => '$english_fallback',\n";
                }
            }
            
            // Write back to file
            if ($new_content !== $content) {
                file_put_contents($lang_file, $new_content);
                echo "✅ Updated $lang_file with " . count($missing_keys_per_language[basename($lang_file)]) . " keys\n";
            }
        }
    }
    
    echo "\n=== REPAIR COMPLETE ===\n";
    echo "All missing translation keys have been added to language files.\n";
    echo "English fallbacks will be used until proper translations are added.\n";
    echo "\n";
    echo "<a href=''>Go Back</a>\n";
    echo "</pre>\n";
    exit;
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Translation Fix Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin:20px; }
        .container { max-width:800px; margin:0 auto; }
        .section { margin:20px 0; padding:20px; border:1px solid #ddd; }
        .missing { color: #d32f2f; }
        .found { color: #28a745; }
        .key { font-family: monospace; background: #f8f9fa; padding:2px 4px; margin:2px 0; }
        .back-link { margin-top:20px; display: inline-block; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Translation Fix Tool</h1>
        
        <div class='section'>
            <h2>Translation Keys in Use</h2>
            <div class='found'>";
foreach ($all_translation_keys as $key => $used) {
    echo "<span class='key'>$key</span> ";
}
echo "</div>";
echo "<div class='section'><h2>Missing Keys by Language</h2>";
foreach ($language_files as $lang_file) {
    if (file_exists($lang_file)) {
        $translations = include $lang_file;
        $missing = isset($missing_keys_per_language[basename($lang_file)]) ? $missing_keys_per_language[basename($lang_file)] : [];
        
        echo "<h3>$lang_file</h3>";
        if (!empty($missing)) {
            echo "<div class='missing'>";
            foreach ($missing as $key) {
                echo "<div class='key'>$key</div>";
            }
            echo "</div>";
        } else {
            echo "<div class='found'>✅ All keys present</div>";
        }
        echo "<br><br>";
    }
}
echo "</div>
        <div class='section'>
            <h2>Actions</h2>
            <a href='?repair=1' class='back-link'>Repair Missing Translation Keys</a>
        </div>
    </div>
</body>
</html>";
