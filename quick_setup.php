<?php
// Quick Database Setup - Fixed for port 3307
echo "<h1>🚀 HandToGlobal Quick Setup</h1>";

try {
    // Connect to MySQL on port 3307
    $dsn = "mysql:host=localhost;port=3307;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);;
    echo "<p style='color: green;'>✅ Connected to MySQL on port 3307</p>";
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS handtoglobal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✅ Database 'handtoglobal' created</p>";
    
    // Switch to database
    $pdo->exec("USE handtoglobal");
    
    // Read and execute SQL file
    $sql = file_get_contents('db.sql');
    
    // Remove comments and split statements
    $sql = preg_replace('/--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $executed = 0;
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^(CREATE|INSERT|ALTER|DROP)/', $statement)) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $executed++;
        } catch (PDOException $e) {
            // Ignore duplicate errors
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "<p style='color: orange;'>⚠️ " . htmlspecialchars(substr($e->getMessage(), 0, 100)) . "</p>";
            }
        }
    }
    
    echo "<p style='color: green;'>✅ Executed $executed SQL statements</p>";
    
    // Verify setup
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p style='color: green;'>✅ Created tables: " . implode(', ', $tables) . "</p>";
    
    // Check admin account
    $stmt = $pdo->prepare("SELECT email FROM admins WHERE email = ?");
    $stmt->execute(['admin@handtoglobal.com']);
    if ($stmt->fetch()) {
        echo "<p style='color: green;'>✅ Admin account ready</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Creating admin account...</p>";
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO admins (email, password) VALUES (?, ?)")->execute(['admin@handtoglobal.com', $hash]);
        echo "<p style='color: green;'>✅ Admin account created</p>";
    }
    
    echo "<h2 style='color: green;'>🎉 Setup Complete!</h2>";
    echo "<div style='background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>📋 Login Information:</h3>";
    echo "<p><strong>Admin Panel:</strong> <a href='admin/' style='color: blue;'>http://localhost/globalhand/admin/</a></p>";
    echo "<p><strong>Email:</strong> admin@handtoglobal.com</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "</div>";
    
    echo "<div style='background: #f0fdf4; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>🔗 Quick Links:</h3>";
    echo "<p><a href='login.php' style='color: blue;'>🔐 User Login</a></p>";
    echo "<p><a href='register.php' style='color: blue;'>👤 User Registration</a></p>";
    echo "<p><a href='admin/' style='color: blue;'>⚙️ Admin Panel</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Setup Failed</h2>";
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>XAMPP MySQL is running on port 3307</li>";
    echo "<li>db.sql file exists in the same directory</li>";
    echo "</ul>";
}
?>

<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f8fafc; }
    h1 { color: #1e40af; border-bottom: 3px solid #3b82f6; padding-bottom: 15px; }
    h2 { color: #059669; margin-top: 30px; }
    h3 { color: #1e40af; margin-bottom: 10px; }
    p { margin: 10px 0; line-height: 1.6; }
    ul { margin: 10px 0; }
    a { color: #3b82f6; text-decoration: none; font-weight: 500; }
    a:hover { text-decoration: underline; }
</style>
