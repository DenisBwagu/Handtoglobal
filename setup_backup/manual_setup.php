<?php
// Manual Database Setup for HandToGlobal
// This script creates the database and tables manually

echo "<h1>HandToGlobal Manual Database Setup</h1>";

// Test different MySQL configurations
$configs = [
    ['host' => 'localhost', 'port' => 3306, 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'port' => 3307, 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 3307, 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'port' => 3308, 'user' => 'root', 'pass' => ''],
];

$working_config = null;

foreach ($configs as $config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $working_config = $config;
        echo "<p style='color: green;'>✅ Connected to MySQL on {$config['host']}:{$config['port']}</p>";
        break;
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>❌ Failed to connect to {$config['host']}:{$config['port']} - {$e->getMessage()}</p>";
    }
}

if (!$working_config) {
    echo "<h2 style='color: red;'>❌ Could not connect to MySQL on any port</h2>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>XAMPP MySQL service is running</li>";
    echo "<li>No other applications are using MySQL ports</li>";
    echo "<li>MySQL configuration is correct</li>";
    echo "</ul>";
    exit;
}

// Update config.php with working configuration
$config_content = "<?php\n";
$config_content .= "// Database configuration\n";
$config_content .= "define('DB_HOST', '{$working_config['host']}');\n";
$config_content .= "define('DB_PORT', {$working_config['port']});\n";
$config_content .= "define('DB_NAME', 'handtoglobal');\n";
$config_content .= "define('DB_USER', '{$working_config['user']}');\n";
$config_content .= "define('DB_PASS', '{$working_config['pass']}');\n";
$config_content .= "\n";
$config_content .= "// Application configuration\n";
$config_content .= "define('APP_NAME', 'HandToGlobal');\n";
$config_content .= "define('APP_URL', 'http://localhost/globalhand');\n";
$config_content .= "define('DAILY_TASK_LIMIT', 40);\n";
$config_content .= "\n";
$config_content .= "// Level configuration\n";
$config_content .= "define('BRONZE_REWARD', 1.80);\n";
$config_content .= "define('SILVER_REWARD', 2.50);\n";
$config_content .= "define('GOLD_REWARD', 3.50);\n";
$config_content .= "define('PLATINUM_REWARD', 5.00);\n";

// Read the rest of the original config file
$original_config = file_get_contents('config.php');
$lines = explode("\n", $original_config);
$start_from = 20; // After the basic defines
$config_content .= "\n" . implode("\n", array_slice($lines, $start_from));

file_put_contents('config.php', $config_content);
echo "<p style='color: green;'>✅ Updated config.php with working database configuration</p>";

try {
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS handtoglobal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✅ Database 'handtoglobal' created successfully</p>";
    
    // Switch to the new database
    $pdo->exec("USE handtoglobal");
    
    // Read and execute SQL file
    $sql_file = 'db.sql';
    if (!file_exists($sql_file)) {
        echo "<p style='color: red;'>❌ db.sql file not found</p>";
        exit;
    }
    
    $sql = file_get_contents($sql_file);
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                echo "<p style='color: orange;'>⚠️ SQL Warning: " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<p style='color: gray;'>Statement: " . htmlspecialchars(substr($statement, 0, 100)) . "...</p>";
            }
        }
    }
    
    echo "<p style='color: green;'>✅ Database tables created successfully</p>";
    
    // Verify admin account
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->execute(['admin@handtoglobal.com']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<p style='color: green;'>✅ Admin account verified: admin@handtoglobal.com</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Admin account not found, creating...</p>";
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
        $stmt->execute(['admin@handtoglobal.com', $hashed_password]);
        echo "<p style='color: green;'>✅ Admin account created</p>";
    }
    
    // Test database connection with new config
    require_once 'config.php';
    $conn = getConnection();
    if ($conn) {
        echo "<p style='color: green;'>✅ Database connection test successful</p>";
    } else {
        echo "<p style='color: red;'>❌ Database connection test failed</p>";
    }
    
    echo "<h2 style='color: green;'>🎉 Setup Complete!</h2>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li><a href='login.php' style='color: blue;'>Go to Login Page</a></li>";
    echo "<li><a href='admin/' style='color: blue;'>Go to Admin Panel</a></li>";
    echo "</ul>";
    echo "<p><strong>Login Credentials:</strong></p>";
    echo "<ul>";
    echo "<li>Email: admin@handtoglobal.com</li>";
    echo "<li>Password: admin123</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>❌ Database Setup Error</h2>";
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
    h1 { color: #333; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; }
    h2 { margin-top: 30px; }
    ul { line-height: 1.6; }
    a { text-decoration: none; }
    a:hover { text-decoration: underline; }
</style>
