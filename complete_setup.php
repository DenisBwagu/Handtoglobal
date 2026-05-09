<?php
// Complete Database Setup Script
echo "Starting complete database setup...\n";

try {
    // Connect to MySQL without specifying database
    $conn = new PDO("mysql:host=localhost;port=3307;charset=utf8mb4", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Connected to MySQL server\n";
    
    // Create database if not exists
    $conn->exec("CREATE DATABASE IF NOT EXISTS globalhand CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database 'globalhand' created\n";
    
    // Switch to the database
    $conn->exec("USE globalhand");
    
    // Read and execute the SQL file
    $sqlFile = __DIR__ . '/db.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                try {
                    $conn->exec($statement);
                } catch (PDOException $e) {
                    echo "Warning: " . $e->getMessage() . "\n";
                }
            }
        }
        echo "✓ Database tables created\n";
    } else {
        echo "✗ db.sql file not found\n";
        exit(1);
    }
    
    // Add test users
    $testUsers = [
        [
            'fullname' => 'Test User',
            'email' => 'test@handtoglobal.com',
            'password' => 'password123',
            'balance' => 100.00
        ],
        [
            'fullname' => 'Demo User',
            'email' => 'demo@handtoglobal.com',
            'password' => 'demo123',
            'balance' => 250.00
        ]
    ];
    
    foreach ($testUsers as $user) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$user['email']]);
        
        if (!$stmt->fetch()) {
            $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, balance) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['fullname'], $user['email'], $hashedPassword, $user['balance']]);
            echo "✓ Added user: {$user['email']}\n";
        }
    }
    
    // Verify admin account
    $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
    $stmt->execute(['admin@handtoglobal.com']);
    
    if (!$stmt->fetch()) {
        $adminPassword = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
        $stmt->execute(['admin@handtoglobal.com', $adminPassword]);
        echo "✓ Added admin account\n";
    }
    
    echo "\n=== SETUP COMPLETE ===\n";
    echo "Database: globalhand\n";
    echo "Port: 3307\n\n";
    
    echo "LOGIN CREDENTIALS:\n";
    echo "==================\n\n";
    
    echo "ADMIN LOGIN:\n";
    echo "  Email: admin@handtoglobal.com\n";
    echo "  Password: password\n";
    echo "  URL: http://localhost/globalhand/login.php\n\n";
    
    echo "USER LOGINS:\n";
    echo "  Email: test@handtoglobal.com\n";
    echo "  Password: password123\n";
    echo "  Email: demo@handtoglobal.com\n";
    echo "  Password: demo123\n";
    echo "  URL: http://localhost/globalhand/login.php\n\n";
    
    echo "You can now test the system!\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
