<?php
/**
 * PHP Syntax Validator and Error Prevention System
 * This file provides functions to validate PHP syntax and prevent common errors
 */

/**
 * Validates PHP syntax by checking for common issues
 * @param string $php_code The PHP code to validate
 * @return array Array of errors found
 */
function validate_php_syntax($php_code) {
    $errors = [];
    
    // Remove comments and strings to avoid false positives
    $clean_code = remove_comments_and_strings($php_code);
    
    // Check for balanced braces (excluding strings and comments)
    $open_braces = substr_count($clean_code, '{');
    $close_braces = substr_count($clean_code, '}');
    if ($open_braces !== $close_braces) {
        $errors[] = "Unbalanced braces: $open_braces opening, $close_braces closing";
    }
    
    // Check for balanced parentheses
    $open_parens = substr_count($clean_code, '(');
    $close_parens = substr_count($clean_code, ')');
    if ($open_parens !== $close_parens) {
        $errors[] = "Unbalanced parentheses: $open_parens opening, $close_parens closing";
    }
    
    // Check for balanced brackets
    $open_brackets = substr_count($clean_code, '[');
    $close_brackets = substr_count($clean_code, ']');
    if ($open_brackets !== $close_brackets) {
        $errors[] = "Unbalanced brackets: $open_brackets opening, $close_brackets closing";
    }
    
    // Check for actual syntax errors (not false positives)
    $lines = explode("\n", $php_code);
    foreach ($lines as $line_num => $line) {
        $trimmed = trim($line);
        
        // Skip comments, empty lines, and HTML
        if (empty($trimmed) || 
            strpos($trimmed, '//') === 0 || 
            strpos($trimmed, '#') === 0 ||
            strpos($trimmed, '<?php') === 0 ||
            strpos($trimmed, '?>') === 0 ||
            strpos($trimmed, '<!DOCTYPE') === 0 ||
            strpos($trimmed, '<html') === 0 ||
            strpos($trimmed, '<head') === 0 ||
            strpos($trimmed, '<body') === 0 ||
            strpos($trimmed, '<div') === 0 ||
            strpos($trimmed, '<style') === 0 ||
            strpos($trimmed, '<script') === 0 ||
            strpos($trimmed, '<link') === 0 ||
            strpos($trimmed, '<meta') === 0) {
            continue;
        }
        
        // Only check for actual syntax errors, not false positives
        if (preg_match('/\$\w+\s*=\s*[^;]*$/', $trimmed) && 
            !preg_match('/\{$/', $trimmed) && 
            !preg_match('/\?>$/', $trimmed) &&
            !preg_match('/\/\*/', $trimmed) &&
            !preg_match('/\*\//', $trimmed)) {
            // This is a real missing semicolon error
            $errors[] = "Line " . ($line_num + 1) . ": Missing semicolon in assignment";
        }
    }
    
    return $errors;
}

/**
 * Removes comments and strings from PHP code for accurate syntax checking
 * @param string $code The PHP code
 * @return string Cleaned code
 */
function remove_comments_and_strings($code) {
    // Remove single line comments
    $code = preg_replace('/\/\/.*$/m', '', $code);
    $code = preg_replace('/#.*$/m', '', $code);
    
    // Remove multi-line comments
    $code = preg_replace('/\/\*.*?\*\//s', '', $code);
    
    // Remove strings (both single and double quoted)
    $code = preg_replace('/\'[^\']*\'/', '', $code);
    $code = preg_replace('/"[^"]*"/', '', $code);
    
    // Remove HTML content
    $code = preg_replace('/<\?php.*?\?>/s', '', $code);
    $code = preg_replace('/<[^>]*>/', '', $code);
    
    return $code;
}

/**
 * Standard PHP file structure template
 * @param string $file_type Type of file (login, register, admin, etc.)
 * @return string Standard PHP structure
 */
function get_php_template($file_type = 'default') {
    $templates = [
        'login' => '<?php
require_once \'config.php\';

// Authentication redirects
if (isLoggedIn()) {
    redirect(\'dashboard.php\');
}
if (isAdminLoggedIn()) {
    redirect(\'admin/index.php\');
}

// Initialize variables
$error = \'\';
$dbError = \'\';
$success = \'\';

// Database connection check (non-POST)
if ($_SERVER[\'REQUEST_METHOD\'] !== \'POST\') {
    try {
        $conn = getConnection();
        $conn = null;
    } catch(PDOException $e) {
        $dbError = \'Database connection failed.\';
    }
}

// Handle POST requests
if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {
    $email = sanitize($_POST[\'email\'] ?? \'\');
    $password = $_POST[\'password\'] ?? \'\';
    
    if (empty($email) || empty($password)) {
        $error = \'Please enter both email and password\';
    } else {
        try {
            $conn = getConnection();
            // Database operations here
        } catch(PDOException $e) {
            $dbError = \'Database connection failed.\';
        }
    }
}
?>',
        
        'admin' => '<?php
require_once \'../config.php\';

// Admin authentication check
if (!isAdminLoggedIn()) {
    redirect(\'../login.php\');
}

// Initialize variables
$error = \'\';
$success = \'\';
$dbError = \'\';

// Database connection check
try {
    $conn = getConnection();
} catch(PDOException $e) {
    $dbError = \'Database connection failed.\';
}

// Handle POST requests
if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {
    // Form processing here
}
?>',
        
        'default' => '<?php
require_once \'config.php\';

// Initialize variables
$error = \'\';
$success = \'\';
$dbError = \'\';

// Database connection check
try {
    $conn = getConnection();
} catch(PDOException $e) {
    $dbError = \'Database connection failed.\';
}

// Handle POST requests
if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {
    // Form processing here
}
?>'
    ];
    
    return $templates[$file_type] ?? $templates['default'];
}

/**
 * Fixes common PHP syntax issues
 * @param string $php_code The PHP code to fix
 * @return string Fixed PHP code
 */
function fix_php_syntax($php_code) {
    // Fix common brace issues
    $php_code = preg_replace('/\}\s*else\s*\{/', '} else {', $php_code);
    $php_code = preg_replace('/\}\s*else\s*if\s*\(/', '} else if (', $php_code);
    
    // Fix spacing around braces
    $php_code = preg_replace('/\{\s*\n\s*\}/', "{}\n", $php_code);
    
    return $php_code;
}

/**
 * Validates a PHP file and returns detailed report
 * @param string $file_path Path to the PHP file
 * @return array Validation report
 */
function validate_php_file($file_path) {
    if (!file_exists($file_path)) {
        return ['error' => "File not found: $file_path"];
    }
    
    $content = file_get_contents($file_path);
    $syntax_errors = validate_php_syntax($content);
    
    // Try to check PHP syntax using php -l (if available)
    $php_syntax_check = [];
    $temp_file = tempnam(sys_get_temp_dir(), 'php_check_');
    file_put_contents($temp_file, $content);
    
    // Try to run PHP syntax check
    $output = [];
    $return_code = 0;
    exec("php -l $temp_file 2>&1", $output, $return_code);
    
    if ($return_code !== 0) {
        $php_syntax_check = $output;
    }
    
    unlink($temp_file);
    
    return [
        'file' => $file_path,
        'syntax_errors' => $syntax_errors,
        'php_check' => $php_syntax_check,
        'valid' => empty($syntax_errors) && empty($php_syntax_check)
    ];
}
?>
