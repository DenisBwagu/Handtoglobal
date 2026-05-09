<?php
// Fixed HandToGlobal Setup Script - Creates database first then imports schema
echo "<!DOCTYPE html>
<html>
<head>
    <title>HandToGlobal Setup</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; margin: 0; }
        .setup-container { background: white; border-radius: 20px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); padding: 40px; width: 100%; max-width: 700px; }
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
        .progress { width: 100%; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; margin: 10px 0; }
        .progress-bar { height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); transition: width 0.3s ease; }
    </style>
</head>
<body>
    <div class='setup-container'>
        <div class='logo'>
            <i class='fas fa-hand-holding-usd'></i>
            <h1>HandToGlobal Setup</h1>
        </div>";

try {
    // Step 1: Test MySQL connection (without database)
    echo "<div class='step'>
        <h3><i class='fas fa-plug'></i> Step 1: Testing MySQL Connection</h3>";
    
    $dsn = "mysql:host=localhost;port=3307;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<p class='success'>✓ MySQL connection successful on port 3307!</p>";
    echo "</div>";

    // Step 2: Drop and recreate database
    echo "<div class='step'>
        <h3><i class='fas fa-database'></i> Step 2: Creating Fresh Database</h3>";
    
    // Drop existing databases
    $pdo->exec("DROP DATABASE IF EXISTS handtoglobal");
    $pdo->exec("DROP DATABASE IF EXISTS globalhand");
    echo "<p class='warning'>🗑️ Cleared existing databases</p>";
    
    // Create new database
    $pdo->exec("CREATE DATABASE handtoglobal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p class='success'>✓ Created fresh handtoglobal database</p>";
    
    // Switch to the new database
    $pdo->exec("USE handtoglobal");
    echo "</div>";

    // Step 3: Import schema
    echo "<div class='step'>
        <h3><i class='fas fa-cogs'></i> Step 3: Importing Database Schema</h3>";
    
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
    foreach ($statements as $statement) {
        if (empty($statement) || preg_match('/^(--|\/\*|#)/', $statement)) continue;
        
        try {
            $pdo->exec($statement);
            $executed++;
            
            // Show progress for major operations
            if (preg_match('/CREATE TABLE `?(\w+)/', $statement, $matches)) {
                echo "<p class='success'>✓ Created table: " . $matches[1] . "</p>";
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "<p class='warning'>⚠️ " . substr($e->getMessage(), 0, 80) . "...</p>";
            }
        }
    }
    
    echo "<p class='success'>✓ Executed $executed SQL statements successfully!</p>";
    echo "</div>";

    // Step 4: Create admin account
    echo "<div class='step'>
        <h3><i class='fas fa-user-shield'></i> Step 4: Creating Admin Account</h3>";
    
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
    $stmt->execute(['admin@handtoglobal.com', $hashedPassword]);
    echo "<p class='success'>✓ Admin account created: admin@handtoglobal.com</p>";
    echo "</div>";

    // Step 5: Verify setup
    echo "<div class='step'>
        <h3><i class='fas fa-check-circle'></i> Step 5: Verifying Installation</h3>";
    
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
    require_once __DIR__ . '/config.php';
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
        
        <h4>Next Steps:</h4>
        <ol>
            <li>Visit the admin panel to configure settings</li>
            <li>Create user accounts or enable registration</li>
            <li>Customize the theme in admin settings</li>
            <li>Monitor platform activity from the admin dashboard</li>
        </ol>
        
        <div style='text-align: center; margin-top: 30px;'>
            <a href='login.php' class='btn'><i class='fas fa-sign-in-alt'></i> Go to Login</a>
            <a href='admin/index.php' class='btn'><i class='fas fa-cog'></i> Admin Panel</a>
        </div>
    </div>";

} catch (Exception $e) {
    echo "<div class='step'>
        <h3><i class='fas fa-exclamation-triangle'></i> Setup Error</h3>
        <p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>
        <p>Please check your database configuration in config.php and try again.</p>
    </div>";
}

echo "</div>
</body>
</html>";
?>
