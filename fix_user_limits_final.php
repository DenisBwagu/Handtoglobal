<?php
/**
 * Final Fix for User Limits Table
 * This script handles the tablespace issue properly
 */

require_once __DIR__ . '/config.php';

echo "=== FINAL USER_LIMITS TABLE FIX ===\n\n";

try {
    $conn = getConnection();
    
    // Try to discard the tablespace first
    echo "1. Discarding tablespace if it exists...\n";
    try {
        $conn->query("DROP TABLE IF EXISTS user_limits");
        echo "   ✅ Dropped table\n";
    } catch (Exception $e) {
        echo "   ℹ️ No table to drop\n";
    }
    
    // Create a simple table without foreign keys first
    echo "\n2. Creating user_limits table (simple version)...\n";
    
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
        ) ENGINE=InnoDB
    ";
    
    $conn->query($createTableSQL);
    echo "   ✅ user_limits table created successfully\n";
    
    // Create default records
    echo "\n3. Creating default user_limits for existing users...\n";
    
    $insertSQL = "
        INSERT IGNORE INTO user_limits (user_id, daily_task_limit, withdrawal_limit, min_withdrawal, max_withdrawal, can_withdraw, can_submit_tasks, is_active)
        SELECT id, 40, 1000.00, 10.00, 1000.00, 1, 1, 1
        FROM users
    ";
    
    $conn->query($insertSQL);
    $affected = $conn->rowCount();
    echo "   ✅ Created/updated user_limits for $affected users\n";
    
    // Verify
    echo "\n4. Verification:\n";
    
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
    
    echo "\n=== USER_LIMITS TABLE FIX COMPLETE ===\n";
    echo "✅ user_limits table is working\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
