<?php
/**
 * Simple PHP Syntax Checker for HandToGlobal Project
 * Focuses on real syntax errors, not false positives
 */

echo "<h2>HandToGlobal PHP Syntax Checker (Simple)</h2>";

// Get all PHP files
$php_files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('.'),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $php_files[] = $file->getPathname();
    }
}

echo "<h3>Found " . count($php_files) . " PHP files</h3>";

$valid_files = 0;
$invalid_files = 0;
$error_files = [];

foreach ($php_files as $file) {
    $content = file_get_contents($file);
    $errors = [];
    
    // Check for real syntax errors only
    $lines = explode("\n", $content);
    $brace_count = 0;
    $parens_count = 0;
    $in_php = false;
    
    foreach ($lines as $line_num => $line) {
        $trimmed = trim($line);
        
        // Track PHP blocks
        if (strpos($trimmed, '<?php') !== false) {
            $in_php = true;
            continue;
        }
        if (strpos($trimmed, '?>') !== false) {
            $in_php = false;
            continue;
        }
        
        if (!$in_php) continue;
        
        // Skip comments
        if (empty($trimmed) || strpos($trimmed, '//') === 0 || strpos($trimmed, '#') === 0) {
            continue;
        }
        
        // Count braces
        $brace_count += substr_count($line, '{') - substr_count($line, '}');
        $parens_count += substr_count($line, '(') - substr_count($line, ')');
        
        // Check for actual syntax errors
        if (preg_match('/\$\w+\s*=\s*[^;]*\s*$/', $trimmed) && 
            !preg_match('/\{$/', $trimmed) && 
            !preg_match('/\?>$/', $trimmed) &&
            !preg_match('/\/\*/', $trimmed) &&
            !preg_match('/\*\//', $trimmed) &&
            !preg_match('/\s*\/\//', $trimmed)) {
            $errors[] = "Line " . ($line_num + 1) . ": Missing semicolon";
        }
    }
    
    // Check for unbalanced braces at end
    if ($brace_count !== 0) {
        $errors[] = "Unbalanced braces: " . ($brace_count > 0 ? "missing closing brace" : "extra closing brace");
    }
    
    if ($parens_count !== 0) {
        $errors[] = "Unbalanced parentheses: " . ($parens_count > 0 ? "missing closing parenthesis" : "extra closing parenthesis");
    }
    
    if (empty($errors)) {
        $valid_files++;
        echo "<div style='color: green;'>✅ $file - Valid</div>";
    } else {
        $invalid_files++;
        $error_files[] = $file;
        echo "<div style='color: red; margin-bottom: 10px;'>";
        echo "❌ <strong>$file</strong><br>";
        foreach ($errors as $error) {
            echo "• $error<br>";
        }
        echo "</div>";
    }
}

echo "<h3>Summary:</h3>";
echo "<div style='color: green;'>✅ Valid files: $valid_files</div>";
echo "<div style='color: red;'>❌ Invalid files: $invalid_files</div>";

if (!empty($error_files)) {
    echo "<h3>Files with errors:</h3>";
    echo "<ul>";
    foreach ($error_files as $file) {
        echo "<li>$file</li>";
    }
    echo "</ul>";
}

echo "<p><a href='admin/'>Go to Admin Panel</a> | <a href='login.php'>Go to Login</a></p>";
?>
