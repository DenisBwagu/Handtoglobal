<?php
// Clean Database Setup - Removes old databases and creates fresh setup
echo "<h1>🧹 HandToGlobal Clean Setup</h1>";

try {
    // Connect to MySQL on port 3307
    $dsn = "mysql:host=localhost;port=3307;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<p style='color: green;'>✅ Connected to MySQL on port 3307</p>";
    
    // Drop existing databases if they exist
    $databases_to_drop = ['globalhand', 'handtoglobal'];
    
    foreach ($databases_to_drop as $db_name) {
        try {
            $pdo->exec("DROP DATABASE IF EXISTS `$db_name`");
            echo "<p style='color: orange;'>🗑️ Dropped existing database: $db_name</p>";
        } catch (PDOException $e) {
            echo "<p style='color: gray;'>ℹ️ Database $db_name doesn't exist or couldn't be dropped</p>";
        }
    }
    
    // Create fresh handtoglobal database
    $pdo->exec("CREATE DATABASE handtoglobal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✅ Created fresh database: handtoglobal</p>";
    
    // Switch to the new database
    $pdo->exec("USE handtoglobal");
    
    // Read and execute SQL file
    $sql_file = 'db.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("db.sql file not found");
    }
    
    $sql = file_get_contents($sql_file);
    
    // Clean up SQL - remove comments and extra whitespace
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/^\s*$/m', '', $sql);
    
    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $executed = 0;
    $errors = [];
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        // Skip comment lines
        if (preg_match('/^(--|\/\*|#)/', $statement)) continue;
        
        try {
            $pdo->exec($statement);
            $executed++;
            
            // Show progress for major operations
            if (preg_match('/CREATE TABLE|INSERT INTO.*VALUES/', $statement)) {
                $table_name = '';
                if (preg_match('/CREATE TABLE `?(\w+)/', $statement, $matches)) {
                    $table_name = $matches[1];
                    echo "<p style='color: blue;'>📋 Created table: $table_name</p>";
                } elseif (preg_match('/INSERT INTO `?(\w+)/', $statement, $matches)) {
                    $table_name = $matches[1];
                    echo "<p style='color: blue;'>📝 Inserted data into: $table_name</p>";
                }
            }
        } catch (PDOException $e) {
            $error_msg = $e->getMessage();
            
            // Ignore common errors
            if (strpos($error_msg, 'already exists') !== false || 
                strpos($error_msg, 'Duplicate entry') !== false) {
                continue;
            }
            
            $errors[] = $error_msg;
            echo "<p style='color: orange;'>⚠️ SQL Warning: " . htmlspecialchars(substr($error_msg, 0, 100)) . "</p>";
        }
    }
    
    echo "<p style='color: green;'>✅ Successfully executed $executed SQL statements</p>";
    
    // Verify database structure
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p style='color: green;'>✅ Created " . count($tables) . " tables: " . implode(', ', $tables) . "</p>";
    
    // Verify admin account
    $stmt = $pdo->prepare("SELECT email, created_at FROM admins WHERE email = ?");
    $stmt->execute(['admin@handtoglobal.com']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<p style='color: green;'>✅ Admin account verified: admin@handtoglobal.com</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Creating admin account...</p>";
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
        $stmt->execute(['admin@handtoglobal.com', $hashed_password]);
        echo "<p style='color: green;'>✅ Admin account created</p>";
    }
    
    // Verify sample data
    $stmt = $pdo->query("SELECT COUNT(*) as task_count FROM tasks");
    $task_count = $stmt->fetch()['task_count'];
    echo "<p style='color: green;'>✅ Created $task_count sample tasks</p>";
    
    // Test database connection with updated config
    require_once 'config.php';
    $conn = getConnection();
    if ($conn) {
        echo "<p style='color: green;'>✅ Database connection test successful</p>";
    } else {
        echo "<p style='color: red;'>❌ Database connection test failed</p>";
    }
    
    if (!empty($errors)) {
        echo "<h3 style='color: orange;'>⚠️ Warnings encountered:</h3>";
        foreach (array_slice($errors, 0, 5) as $error) {
            echo "<p style='color: orange; font-size: 12px;'>" . htmlspecialchars($error) . "</p>";
        }
        if (count($errors) > 5) {
            echo "<p style='color: gray;'>... and " . (count($errors) - 5) . " more warnings</p>";
        }
    }
    
    echo "<h2 style='color: green;'>🎉 Setup Complete!</h2>";
    echo "<div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin: 20px 0;'>";
    echo "<h2 style='color: white; margin-top: 0;'>🚀 HandToGlobal is Ready!</h2>";
    echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;'>";
    echo "<div>";
    echo "<h3 style='color: white;'>🔐 Admin Login</h3>";
    echo "<p><strong>Email:</strong> admin@handtoglobal.com</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "<p><a href='admin/' style='color: #ffd700; font-weight: bold;'>→ Go to Admin Panel</a></p>";
    echo "</div>";
    echo "<div>";
    echo "<h3 style='color: white;'>👤 User Access</h3>";
    echo "<p><a href='login.php' style='color: #ffd700; font-weight: bold;'>→ User Login</a></p>";
    echo "<p><a href='register.php' style='color: #ffd700; font-weight: bold;'>→ User Registration</a></p>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    echo "<div style='background: #f0fdf4; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #16a34a;'>";
    echo "<h3 style='color: #16a34a;'>✨ What's Included:</h3>";
    echo "<ul style='margin: 10px 0;'>";
    echo "<li>✅ Complete user authentication system</li>";
    echo "<li>✅ Admin panel with user management</li>";
    echo "<li>✅ $task_count sample microtasks across 4 levels</li>";
    echo "<li>✅ Theme customization system</li>";
    echo "<li>✅ Balance and transaction tracking</li>";
    echo "<li>✅ Premium SaaS design</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Setup Failed</h2>";
    echo "<div style='background: #fef2f2; padding: 20px; border-radius: 8px; border-left: 4px solid #dc2626;'>";
    echo "<p style='color: #dc2626; font-weight: bold;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3 style='color: #dc2626;'>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Ensure XAMPP MySQL is running on port 3307</li>";
    echo "<li>Check that db.sql file exists and is readable</li>";
    echo "<li>Verify MySQL user 'root' has sufficient privileges</li>";
    echo "<li>Try restarting MySQL service in XAMPP</li>";
    echo "</ul>";
    echo "</div>";
}
?>

<style>
    body { 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        max-width: 1000px; 
        margin: 40px auto; 
        padding: 20px; 
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
    }
    h1 { 
        color: #1e40af; 
        border-bottom: 3px solid #3b82f6; 
        padding-bottom: 15px; 
        text-align: center;
        font-size: 2.5em;
        margin-bottom: 30px;
    }
    h2 { 
        color: #059669; 
        margin-top: 30px; 
        border-left: 4px solid #10b981;
        padding-left: 15px;
    }
    h3 { 
        color: #1e40af; 
        margin-bottom: 10px; 
    }
    p { 
        margin: 10px 0; 
        line-height: 1.6; 
    }
    ul { 
        margin: 10px 0; 
        padding-left: 20px;
    }
    a { 
        color: #3b82f6; 
        text-decoration: none; 
        font-weight: 500; 
        transition: color 0.3s;
    }
    a:hover { 
        color: #1d4ed8; 
        text-decoration: underline; 
    }
    .success { color: #16a34a; font-weight: bold; }
    .warning { color: #f59e0b; font-weight: bold; }
    .error { color: #dc2626; font-weight: bold; }
    .info { color: #0284c7; font-weight: bold; }
</style>
