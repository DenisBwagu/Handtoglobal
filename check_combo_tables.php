<?php
require_once __DIR__ . '/config.php';

$conn = getConnection();

echo "=== COMBO DATABASE STRUCTURE CHECK ===\n\n";

// Check combos table
echo "1. Checking 'combos' table:\n";
try {
    $stmt = $conn->query("DESCRIBE combos");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ combos table exists\n";
    echo "Columns:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']}\n";
    }
} catch(PDOException $e) {
    echo "❌ combos table doesn't exist: " . $e->getMessage() . "\n";
    
    // Create combos table
    echo "\nCreating combos table...\n";
    $sql = "
        CREATE TABLE combos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            level VARCHAR(50) NOT NULL,
            start_task INT NOT NULL,
            end_task INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_level (level),
            INDEX idx_status (status),
            INDEX idx_task_range (start_task, end_task)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conn->exec($sql);
    echo "✅ combos table created\n";
}

echo "\n";

// Check user_combo_status table
echo "2. Checking 'user_combo_status' table:\n";
try {
    $stmt = $conn->query("DESCRIBE user_combo_status");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ user_combo_status table exists\n";
    echo "Columns:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']}\n";
    }
} catch(PDOException $e) {
    echo "❌ user_combo_status table doesn't exist: " . $e->getMessage() . "\n";
    
    // Create user_combo_status table
    echo "\nCreating user_combo_status table...\n";
    $sql = "
        CREATE TABLE user_combo_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            combo_id INT NOT NULL,
            status ENUM('pending', 'activated', 'cleared') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_combo (user_id, combo_id),
            INDEX idx_user_id (user_id),
            INDEX idx_combo_id (combo_id),
            INDEX idx_status (status),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conn->exec($sql);
    echo "✅ user_combo_status table created\n";
}

echo "\n";

// Check sample data
echo "3. Checking existing combos:\n";
try {
    $stmt = $conn->query("SELECT * FROM combos ORDER BY created_at DESC LIMIT 5");
    $combos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($combos)) {
        echo "No combos found in database\n";
    } else {
        echo "Existing combos:\n";
        foreach ($combos as $combo) {
            echo "  - ID: {$combo['id']}, Level: {$combo['level']}, Tasks: {$combo['start_task']}-{$combo['end_task']}, Amount: {$combo['amount']}, Status: {$combo['status']}\n";
        }
    }
} catch(PDOException $e) {
    echo "❌ Error checking combos: " . $e->getMessage() . "\n";
}

echo "\n";

// Check user_combo_status data
echo "4. Checking user combo status:\n";
try {
    $stmt = $conn->query("SELECT * FROM user_combo_status ORDER BY created_at DESC LIMIT 5");
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($statuses)) {
        echo "No user combo statuses found in database\n";
    } else {
        echo "User combo statuses:\n";
        foreach ($statuses as $status) {
            echo "  - User ID: {$status['user_id']}, Combo ID: {$status['combo_id']}, Status: {$status['status']}\n";
        }
    }
} catch(PDOException $e) {
    echo "❌ Error checking user combo status: " . $e->getMessage() . "\n";
}

echo "\n=== CHECK COMPLETE ===\n";
?>
