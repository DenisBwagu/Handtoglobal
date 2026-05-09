<?php
/**
 * Create User Limits Table Fix
 * This script creates the user_limits table properly
 */

require_once __DIR__ . '/config.php';

echo "=== CREATING USER_LIMITS TABLE ===\n\n";

try {
    $conn = getConnection();
    
    // Drop the table if it exists with issues
    echo "1. Dropping existing user_limits table if it exists...\n";
    try {
        $conn->query("DROP TABLE IF EXISTS user_limits");
        echo "   ✅ Dropped existing user_limits table\n";
    } catch (Exception $e) {
        echo "   ℹ️ No existing table to drop\n";
    }
    
    // Create the table properly
    echo "\n2. Creating user_limits table...\n";
    
    $createTableSQL = "
        CREATE TABLE user_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            daily_task_limit INT DEFAULT 40,
            withdrawal_limit DECIMAL(10,2) DEFAULT 1000.00,
            min_withdrawal DECIMAL(10,2) DEFAULT 10.00,
            max_withdrawal DECIMAL(10,2) DEFAULT 1000.00,
            can_withdraw TINYINT(1) DEFAULT 1,
            can_submit_tasks TINYINT(1) DEFAULT 1,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $conn->query($createTableSQL);
    echo "   ✅ user_limits table created successfully\n";
    
    // Create default user_limits for existing users
    echo "\n3. Creating default user_limits for existing users...\n";
    
    $insertSQL = "
        INSERT INTO user_limits (user_id, daily_task_limit, withdrawal_limit, min_withdrawal, max_withdrawal, can_withdraw, can_submit_tasks, is_active)
        SELECT id, 40, 1000.00, 10.00, 1000.00, 1, 1, 1
        FROM users
        WHERE id NOT IN (SELECT user_id FROM user_limits)
    ";
    
    $conn->query($insertSQL);
    $affected = $conn->rowCount();
    echo "   ✅ Created default user_limits for $affected users\n";
    
    // Verify the table
    echo "\n4. Verifying user_limits table...\n";
    
    $result = $conn->query("DESCRIBE user_limits");
    echo "   Table structure:\n";
    while ($row = $result->fetch()) {
        echo "     - {$row['Field']} ({$row['Type']})\n";
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM user_limits");
    $count = $result->fetch()['count'];
    echo "   Records: $count\n";
    
    echo "\n=== USER_LIMITS TABLE CREATION COMPLETE ===\n";
    echo "✅ user_limits table is now ready\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
