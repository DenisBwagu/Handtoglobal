<?php
require_once __DIR__ . '/config.php';

$conn = getConnection();

echo "=== Creating user_limits table ===\n\n";

try {
    // Check if user_limits table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'user_limits'");
    $stmt->execute();
    $exists = $stmt->fetch();
    
    if (!$exists) {
        // Create user_limits table
        $conn->exec("
            CREATE TABLE user_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                max_levels_per_day INT DEFAULT 3,
                min_withdrawal_amount DECIMAL(10,2) DEFAULT 10.00,
                min_withdrawal_level VARCHAR(50) DEFAULT 'Bronze',
                min_balance_floor DECIMAL(10,2) DEFAULT 0.00,
                custom_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_limits (user_id)
            )
        ");
        echo "user_limits table created successfully\n";
    } else {
        echo "user_limits table already exists\n";
        
        // Check table structure
        $stmt = $conn->prepare("DESCRIBE user_limits");
        $stmt->execute();
        $columns = $stmt->fetchAll();
        
        echo "\nCurrent table structure:\n";
        foreach ($columns as $column) {
            echo "- {$column['Field']} ({$column['Type']}) - Null: {$column['Null']} - Default: " . ($column['Default'] ?: 'NULL') . "\n";
        }
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Table setup complete ===\n";
?>
