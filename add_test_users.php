<?php
// Create database connection directly
try {
    $conn = new PDO("mysql:host=localhost;port=3307;dbname=globalhand;charset=utf8mb4", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connected successfully\n";
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Add test users with simple passwords
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

echo "Adding test users...\n";

foreach ($testUsers as $user) {
    // Check if user already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$user['email']]);
    $result = $stmt->fetch();
    
    if (!$result) {
        // Hash password and insert user
        $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, balance) VALUES (?, ?, ?, ?)");
        
        if ($stmt->execute([$user['fullname'], $user['email'], $hashedPassword, $user['balance']])) {
            echo "✓ Added user: {$user['email']} (Password: {$user['password']})\n";
        } else {
            echo "✗ Failed to add user: {$user['email']}\n";
        }
    } else {
        echo "- User already exists: {$user['email']}\n";
    }
}

// Verify admin account
$stmt = $conn->prepare("SELECT email FROM admins WHERE email = 'admin@handtoglobal.com'");
$stmt->execute();
$result = $stmt->fetch();

if ($result) {
    echo "✓ Admin account exists: admin@handtoglobal.com (Password: password)\n";
} else {
    // Add admin if not exists
    $adminPassword = password_hash('password', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
    $email = 'admin@handtoglobal.com';
    
    if ($stmt->execute([$email, $adminPassword])) {
        echo "✓ Added admin account: admin@handtoglobal.com (Password: password)\n";
    } else {
        echo "✗ Failed to add admin account\n";
    }
}

echo "\nLogin Credentials:\n";
echo "==================\n";
echo "Admin:\n";
echo "  Email: admin@handtoglobal.com\n";
echo "  Password: password\n";
echo "  URL: http://localhost/globalhand/login.php\n\n";
echo "Test Users:\n";
echo "  Email: test@handtoglobal.com\n";
echo "  Password: password123\n";
echo "  Email: demo@handtoglobal.com\n";
echo "  Password: demo123\n";
echo "  URL: http://localhost/globalhand/login.php\n";
?>
