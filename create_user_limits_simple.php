<?php
/**
 * Create User Limits Table - Simple Approach
 * This script creates the user_limits table with basic functionality
 */

require_once __DIR__ . '/config.php';

echo "=== CREATING USER_LIMITS TABLE (SIMPLE) ===\n\n";

try {
    $conn = getConnection();
    
    // Drop table if it exists to avoid tablespace issues
    echo "1. Removing existing table if needed...\n";
    try {
        $conn->exec("DROP TABLE IF EXISTS user_limits");
        echo "   ✅ Existing table removed\n";
    } catch (Exception $e) {
        echo "   ℹ️ No existing table to remove\n";
    }
    
    // Create table with basic structure
    echo "\n2. Creating user_limits table...\n";
    
    $createTableSQL = "
        CREATE TABLE user_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            daily_task_limit INT DEFAULT 40,
            withdrawal_limit DECIMAL(10,2) DEFAULT 1000.00,
            min_withdrawal DECIMAL(10,2) DEFAULT 10.00,
            max_withdrawal DECIMAL(10,2) DEFAULT 1000.00,
            can_withdraw TINYINT(1) DEFAULT 1,
            can_submit_tasks TINYINT(1) DEFAULT 1,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    $conn->exec($createTableSQL);
    echo "   ✅ Table created successfully\n";
    
    // Create default records for existing users
    echo "\n3. Creating default user limits...\n";
    
    $insertSQL = "
        INSERT INTO user_limits (user_id, daily_task_limit, withdrawal_limit, min_withdrawal, max_withdrawal, can_withdraw, can_submit_tasks, is_active)
        SELECT id, 40, 1000.00, 10.00, 1000.00, 1, 1, 1
        FROM users
        WHERE id NOT IN (SELECT user_id FROM user_limits)
    ";
    
    $conn->exec($insertSQL);
    $affected = $conn->rowCount();
    echo "   ✅ Created default limits for $affected users\n";
    
    // Verify table structure
    echo "\n4. Verifying table structure...\n";
    
    $result = $conn->query("DESCRIBE user_limits");
    echo "   Table columns:\n";
    while ($row = $result->fetch()) {
        echo "     - {$row['Field']} ({$row['Type']})\n";
    }
    
    // Verify data
    $result = $conn->query("SELECT COUNT(*) as count FROM user_limits");
    $count = $result->fetch()['count'];
    echo "   Records: $count\n";
    
    if ($count > 0) {
        $result = $conn->query("SELECT user_id, daily_task_limit, can_submit_tasks FROM user_limits LIMIT 3");
        echo "   Sample records:\n";
        while ($row = $result->fetch()) {
            echo "     User {$row['user_id']}: Daily Limit {$row['daily_task_limit']}, Can Submit: {$row['can_submit_tasks']}\n";
        }
    }
    
    echo "\n=== USER_LIMITS TABLE CREATION COMPLETE ===\n";
    echo "✅ user_limits table is now ready\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
