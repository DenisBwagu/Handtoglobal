<?php
// Drop and recreate entire database - cleanest approach
echo "Dropping and recreating database...\n";

try {
    // Connect to MySQL without specifying database
    $conn = new PDO("mysql:host=localhost;port=3307;charset=utf8mb4", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Connected to MySQL server\n";
    
    // Drop database if it exists
    $conn->exec("DROP DATABASE IF EXISTS globalhand");
    echo "✓ Dropped database 'globalhand'\n";
    
    // Create fresh database
    $conn->exec("CREATE DATABASE globalhand CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Created fresh database 'globalhand'\n";
    
    // Switch to the database
    $conn->exec("USE globalhand");
    
    // Create users table
    $conn->exec("
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fullname VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            balance DECIMAL(10,2) DEFAULT 0.00,
            level ENUM('Bronze', 'Silver', 'Gold', 'Platinum') DEFAULT 'Bronze',
            bronze_unlocked TINYINT(1) DEFAULT 1,
            silver_unlocked TINYINT(1) DEFAULT 0,
            gold_unlocked TINYINT(1) DEFAULT 0,
            platinum_unlocked TINYINT(1) DEFAULT 0,
            referred_by VARCHAR(255) DEFAULT NULL,
            rating DECIMAL(3,2) DEFAULT 0.00,
            accuracy DECIMAL(5,2) DEFAULT 0.00,
            total_tasks INT DEFAULT 0,
            is_blocked TINYINT(1) DEFAULT 0,
            language VARCHAR(10) DEFAULT 'en',
            daily_task_limit INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Created users table\n";
    
    // Create admins table
    $conn->exec("
        CREATE TABLE admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            language VARCHAR(10) DEFAULT 'en',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Created admins table\n";
    
    // Create tasks table
    $conn->exec("
        CREATE TABLE tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            level ENUM('Bronze', 'Silver', 'Gold', 'Platinum') NOT NULL,
            reward DECIMAL(10,2) NOT NULL,
            image VARCHAR(255) DEFAULT 'task-placeholder.jpg',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Created tasks table\n";
    
    // Create completed_tasks table
    $conn->exec("
        CREATE TABLE completed_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            task_id INT NOT NULL,
            completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
        )
    ");
    echo "✓ Created completed_tasks table\n";
    
    // Create settings table
    $conn->exec("
        CREATE TABLE settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(255) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Created settings table\n";
    
    // Create deposits table
    $conn->exec("
        CREATE TABLE deposits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "✓ Created deposits table\n";
    
    // Create withdrawals table
    $conn->exec("
        CREATE TABLE withdrawals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            wallet_address VARCHAR(255) NOT NULL,
            status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "✓ Created withdrawals table\n";
    
    // Insert admin account
    $adminPassword = password_hash('password', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
    $stmt->execute(['admin@handtoglobal.com', $adminPassword]);
    echo "✓ Added admin account\n";
    
    // Insert test users
    $testUsers = [
        ['Test User', 'test@handtoglobal.com', 'password123', 100.00],
        ['Demo User', 'demo@handtoglobal.com', 'demo123', 250.00]
    ];
    
    foreach ($testUsers as $user) {
        $hashedPassword = password_hash($user[2], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, balance) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user[0], $user[1], $hashedPassword, $user[3]]);
        echo "✓ Added user: {$user[1]}\n";
    }
    
    // Insert sample tasks
    $sampleTasks = [
        ['Review Product Image', 'Check the product image quality and provide feedback', 'Bronze', 1.80],
        ['Verify Product Details', 'Confirm product specifications are accurate', 'Bronze', 1.80],
        ['Check Listing Title', 'Ensure the product title is descriptive and accurate', 'Bronze', 1.80],
        ['Validate Price Information', 'Verify pricing details are correct', 'Bronze', 1.80],
        ['Advanced Product Analysis', 'Conduct comprehensive product evaluation', 'Silver', 2.50],
        ['Market Research Review', 'Analyze market positioning data', 'Silver', 2.50],
        ['Competitor Analysis', 'Evaluate competitor strategies', 'Silver', 2.50],
        ['Customer Experience Audit', 'Review entire customer journey', 'Silver', 2.50],
        ['Strategic Market Analysis', 'Comprehensive market strategy evaluation', 'Gold', 3.50],
        ['Business Model Review', 'Analyze business model effectiveness', 'Gold', 3.50],
        ['Financial Planning Audit', 'Review financial planning strategies', 'Gold', 3.50],
        ['Growth Strategy Assessment', 'Evaluate growth opportunities', 'Gold', 3.50],
        ['Executive Strategy Review', 'C-level strategic assessment', 'Platinum', 5.00],
        ['Board Governance Audit', 'Review governance practices', 'Platinum', 5.00],
        ['Shareholder Value Analysis', 'Assess shareholder returns', 'Platinum', 5.00],
        ['Market Leadership Review', 'Evaluate market dominance', 'Platinum', 5.00]
    ];
    
    foreach ($sampleTasks as $task) {
        $stmt = $conn->prepare("INSERT INTO tasks (title, description, level, reward) VALUES (?, ?, ?, ?)");
        $stmt->execute([$task[0], $task[1], $task[2], $task[3]]);
    }
    echo "✓ Added sample tasks\n";
    
    // Insert settings
    $settings = [
        ['telegram_support_link', 'https://t.me/chica256'],
        ['minimum_withdrawal', '100'],
        ['daily_task_limit', '40'],
        ['bronze_unlock_amount', '100'],
        ['silver_unlock_amount', '150'],
        ['gold_unlock_amount', '250'],
        ['platinum_unlock_amount', '500']
    ];
    
    foreach ($settings as $setting) {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$setting[0], $setting[1]]);
    }
    echo "✓ Added settings\n";
    
    echo "\n===================================\n";
    echo "DATABASE SETUP COMPLETE!\n";
    echo "===================================\n\n";
    
    echo "WORKING LOGIN CREDENTIALS:\n";
    echo "==========================\n\n";
    
    echo "🔐 ADMIN LOGIN:\n";
    echo "   Email: admin@handtoglobal.com\n";
    echo "   Password: password\n";
    echo "   URL: http://localhost/globalhand/login.php\n\n";
    
    echo "👤 USER LOGINS:\n";
    echo "   Email: test@handtoglobal.com\n";
    echo "   Password: password123\n\n";
    echo "   Email: demo@handtoglobal.com\n";
    echo "   Password: demo123\n\n";
    echo "   URL: http://localhost/globalhand/login.php\n\n";
    
    echo "✅ SYSTEM IS READY FOR TESTING!\n";
    echo "   All tables created\n";
    echo "   Admin account added\n";
    echo "   Test users added\n";
    echo "   Sample tasks added\n";
    echo "   Settings configured\n\n";
    
    echo "🚀 You can now test the complete system!\n";
    
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
