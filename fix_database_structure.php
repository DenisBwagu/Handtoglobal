<?php
/**
 * Fix Database Structure
 * This script safely adds missing columns and tables
 */

require_once 'config.php';

echo "=== FIXING DATABASE STRUCTURE ===\n\n";

try {
    $conn = getConnection();
    
    // 1. Add missing 'answer' column to completed_tasks table
    echo "1. Adding missing 'answer' column to completed_tasks table...\n";
    
    try {
        $conn->query("ALTER TABLE completed_tasks ADD COLUMN answer VARCHAR(10) DEFAULT NULL AFTER task_id");
        echo "   ✅ Added 'answer' column to completed_tasks table\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "   ✅ 'answer' column already exists\n";
        } else {
            echo "   ❌ Error adding 'answer' column: " . $e->getMessage() . "\n";
        }
    }
    
    // 2. Create user_limits table if it doesn't exist
    echo "\n2. Creating user_limits table...\n";
    
    $createUserLimitsSQL = "
        CREATE TABLE IF NOT EXISTS user_limits (
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
            UNIQUE KEY unique_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    try {
        $conn->query($createUserLimitsSQL);
        echo "   ✅ user_limits table created successfully\n";
    } catch (Exception $e) {
        echo "   ❌ Error creating user_limits table: " . $e->getMessage() . "\n";
    }
    
    // 3. Create default user_limits for existing users
    echo "\n3. Creating default user_limits for existing users...\n";
    
    try {
        $conn->query("
            INSERT INTO user_limits (user_id, daily_task_limit, withdrawal_limit, min_withdrawal, max_withdrawal, can_withdraw, can_submit_tasks, is_active)
            SELECT id, 40, 1000.00, 10.00, 1000.00, 1, 1, 1
            FROM users
            WHERE id NOT IN (SELECT user_id FROM user_limits)
        ");
        $affected = $conn->rowCount();
        echo "   ✅ Created default user_limits for $affected users\n";
    } catch (Exception $e) {
        echo "   ❌ Error creating default user_limits: " . $e->getMessage() . "\n";
    }
    
    // 4. Verify the structure
    echo "\n4. Verifying fixed structure...\n";
    
    // Check completed_tasks structure
    $result = $conn->query("DESCRIBE completed_tasks");
    $completedTasksColumns = [];
    while ($row = $result->fetch()) {
        $completedTasksColumns[] = $row['Field'];
    }
    
    $requiredCompletedTasksColumns = ['id', 'user_id', 'task_id', 'answer', 'level', 'reward', 'completed_at'];
    $missingCompletedTasksColumns = array_diff($requiredCompletedTasksColumns, $completedTasksColumns);
    
    if (empty($missingCompletedTasksColumns)) {
        echo "   ✅ completed_tasks table has all required columns\n";
    } else {
        echo "   ❌ Still missing: " . implode(', ', $missingCompletedTasksColumns) . "\n";
    }
    
    // Check user_limits structure
    try {
        $result = $conn->query("DESCRIBE user_limits");
        $userLimitsColumns = [];
        while ($row = $result->fetch()) {
            $userLimitsColumns[] = $row['Field'];
        }
        
        $requiredUserLimitsColumns = ['id', 'user_id', 'daily_task_limit', 'withdrawal_limit', 'min_withdrawal', 'max_withdrawal', 'can_withdraw', 'can_submit_tasks', 'is_active', 'created_at', 'updated_at'];
        $missingUserLimitsColumns = array_diff($requiredUserLimitsColumns, $userLimitsColumns);
        
        if (empty($missingUserLimitsColumns)) {
            echo "   ✅ user_limits table has all required columns\n";
        } else {
            echo "   ❌ Still missing: " . implode(', ', $missingUserLimitsColumns) . "\n";
        }
        
        // Check user_limits data
        $result = $conn->query("SELECT COUNT(*) as count FROM user_limits");
        $userLimitsCount = $result->fetch()['count'];
        echo "   ✅ user_limits has $userLimitsCount records\n";
        
    } catch (Exception $e) {
        echo "   ❌ user_limits table verification failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== DATABASE STRUCTURE FIX COMPLETE ===\n";
    echo "✅ Database structure is now ready for the task system\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
