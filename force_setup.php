<?php
// Force Setup - Handles database directory not empty error
echo "<!DOCTYPE html>
<html>
<head>
    <title>HandToGlobal Force Setup</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; margin: 0; }
        .setup-container { background: white; border-radius: 20px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); padding: 40px; width: 100%; max-width: 800px; }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo i { font-size: 48px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 10px; }
        .logo h1 { font-size: 28px; font-weight: 800; color: #2d3748; margin: 0; }
        .step { margin: 20px 0; padding: 20px; border-left: 4px solid #667eea; background: #f8fafc; border-radius: 8px; }
        .step h3 { color: #667eea; margin: 0 0 10px 0; font-size: 18px; }
        .success { color: #16a34a; font-weight: 700; }
        .error { color: #dc2626; font-weight: 700; }
        .warning { color: #f59e0b; font-weight: 700; }
        .code { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 8px; font-family: 'Monaco', 'Menlo', monospace; font-size: 12px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 10px 5px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3); }
        .danger-btn { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); }
        .danger-btn:hover { box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3); }
    </style>
</head>
<body>
    <div class='setup-container'>
        <div class='logo'>
            <i class='fas fa-hand-holding-usd'></i>
            <h1>HandToGlobal Force Setup</h1>
        </div>";

try {
    // Step 1: Test MySQL connection
    echo "<div class='step'>
        <h3><i class='fas fa-plug'></i> Step 1: Testing MySQL Connection</h3>";
    
    $dsn = "mysql:host=localhost;port=3307;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);;
    echo "<p class='success'>✓ MySQL connection successful on port 3307!</p>";
    echo "</div>";

    // Step 2: Force clean databases
    echo "<div class='step'>
        <h3><i class='fas fa-bomb'></i> Step 2: Force Cleaning Databases</h3>";
    
    $databases = ['globalhand', 'handtoglobal'];
    
    foreach ($databases as $db_name) {
        try {
            // Check if database exists
            $stmt = $pdo->query("SHOW DATABASES LIKE '$db_name'");
            if ($stmt->rowCount() > 0) {
                echo "<p class='warning'>⚠️ Found database: $db_name - Cleaning...</p>";
                
                // Connect to the database and drop all tables
                $pdo->exec("USE `$db_name`");
                
                // Get all tables
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($tables)) {
                    // Disable foreign key checks
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                    
                    // Drop all tables
                    foreach ($tables as $table) {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                        echo "<p class='warning'>🗑️ Dropped table: $table</p>";
                    }
                    
                    // Re-enable foreign key checks
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                }
                
                // Now drop the database
                $pdo->exec("DROP DATABASE IF EXISTS `$db_name`");
                echo "<p class='success'>✓ Cleaned and dropped database: $db_name</p>";
            } else {
                echo "<p class='success'>✓ Database $db_name doesn't exist</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error cleaning $db_name: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    echo "</div>";

    // Step 3: Create fresh database
    echo "<div class='step'>
        <h3><i class='fas fa-database'></i> Step 3: Creating Fresh Database</h3>";
    
    $pdo->exec("CREATE DATABASE handtoglobal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p class='success'>✓ Created fresh handtoglobal database</p>";
    
    // Switch to the new database
    $pdo->exec("USE handtoglobal");
    echo "</div>";

    // Step 4: Import schema
    echo "<div class='step'>
        <h3><i class='fas fa-cogs'></i> Step 4: Importing Database Schema</h3>";
    
    $sqlFile = __DIR__ . '/db.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("db.sql file not found");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Replace database name references
    $sql = str_replace('`globalhand`', '`handtoglobal`', $sql);
    
    // Remove comments and split statements
    $sql = preg_replace('/--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $executed = 0;
    $errors = [];
    
    foreach ($statements as $statement) {
        if (empty($statement) || preg_match('/^(--|\/\*|#)/', $statement)) continue;
        
        try {
            $pdo->exec($statement);
            $executed++;
            
            // Show progress for major operations
            if (preg_match('/CREATE TABLE `?(\w+)/', $statement, $matches)) {
                echo "<p class='success'>✓ Created table: " . $matches[1] . "</p>";
            } elseif (preg_match('/INSERT INTO `?(\w+)/', $statement, $matches)) {
                echo "<p class='success'>📝 Inserted data into: " . $matches[1] . "</p>";
            }
        } catch (PDOException $e) {
            $error_msg = $e->getMessage();
            
            // Ignore common errors
            if (strpos($error_msg, 'already exists') !== false || 
                strpos($error_msg, 'Duplicate entry') !== false) {
                continue;
            }
            
            $errors[] = $error_msg;
            echo "<p class='warning'>⚠️ " . substr($error_msg, 0, 80) . "...</p>";
        }
    }
    
    echo "<p class='success'>✓ Executed $executed SQL statements successfully!</p>";
    echo "</div>";

    // Step 5: Create admin account
    echo "<div class='step'>
        <h3><i class='fas fa-user-shield'></i> Step 5: Creating Admin Account</h3>";
    
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
    $stmt->execute(['admin@handtoglobal.com', $hashedPassword]);
    echo "<p class='success'>✓ Admin account created: admin@handtoglobal.com</p>";
    echo "</div>";

    // Step 6: Verify setup
    echo "<div class='step'>
        <h3><i class='fas fa-check-circle'></i> Step 6: Verifying Installation</h3>";
    
    // Check tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p class='success'>✓ Found " . count($tables) . " tables: " . implode(', ', $tables) . "</p>";
    
    // Check tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks");
    $stmt->execute();
    $taskCount = $stmt->fetch()['count'];
    echo "<p class='success'>✓ Created $taskCount sample tasks</p>";
    
    // Test connection with config
    require_once 'config.php';
    $conn = getConnection();
    if ($conn) {
        echo "<p class='success'>✓ Configuration test passed</p>";
    }
    echo "</div>";

    // Setup complete
    echo "<div class='step' style='background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-left-color: #16a34a;'>
        <h3 style='color: #16a34a;'><i class='fas fa-trophy'></i> Setup Complete!</h3>
        <p class='success' style='font-size: 18px;'>🎉 HandToGlobal has been successfully installed!</p>
        
        <h4>🔐 Login Credentials:</h4>
        <div class='code'>
            <strong>Admin Panel:</strong> <a href='admin/' style='color: #60a5fa;'>http://localhost/globalhand/admin/</a><br><br>
            <strong>Email:</strong> admin@handtoglobal.com<br>
            <strong>Password:</strong> admin123<br><br>
            
            <strong>User Panel:</strong><br>
            <a href='http://localhost/globalhand/'>http://localhost/globalhand/</a><br><br>
            
            <strong>Admin Panel:</strong><br>
            <a href='http://localhost/globalhand/admin/'>http://localhost/globalhand/admin/</a>
        </div>
        
        <h4>✨ What's Included:</h4>
        <ul style='margin: 10px 0;'>
            <li>✅ Complete user authentication system</li>
            <li>✅ Admin panel with user management</li>
            <li>✅ $taskCount sample microtasks across 4 levels</li>
            <li>✅ Theme customization system</li>
            <li>✅ Balance and transaction tracking</li>
            <li>✅ Premium SaaS design</li>
            <li>✅ Admin user detail pages with actions</li>
        </ul>
        
        <div style='text-align: center; margin-top: 30px;'>
            <a href='login.php' class='btn'><i class='fas fa-sign-in-alt'></i> Go to Login</a>
            <a href='admin/index.php' class='btn'><i class='fas fa-cog'></i> Admin Panel</a>
        </div>
    </div>";

} catch (Exception $e) {
    echo "<div class='step'>
        <h3><i class='fas fa-exclamation-triangle'></i> Setup Error</h3>
        <div style='background: #fef2f2; padding: 20px; border-radius: 8px; border-left: 4px solid #dc2626;'>
            <p class='error' style='font-weight: bold;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>
            <h3 style='color: #dc2626; margin-top: 20px;'>Troubleshooting:</h3>
            <ul>
                <li>Ensure XAMPP MySQL is running on port 3307</li>
                <li>Check that db.sql file exists and is readable</li>
                <li>Try restarting MySQL service in XAMPP</li>
                <li>Make sure no other applications are using MySQL</li>
                <li>Check file permissions on the project directory</li>
            </ul>
        </div>
    </div>";
}

echo "</div>
</body>
</html>";
?>
