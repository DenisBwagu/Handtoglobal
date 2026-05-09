<?php
/**
 * Check Database Structure
 * This script checks the current database structure for tasks and completed_tasks tables
 */

require_once __DIR__ . '/config.php';

echo "=== CHECKING DATABASE STRUCTURE ===\n\n";

try {
    $conn = getConnection();
    
    // Check tasks table structure
    echo "1. TASKS TABLE STRUCTURE:\n";
    $result = $conn->query("DESCRIBE tasks");
    $tasksColumns = [];
    while ($row = $result->fetch()) {
        $tasksColumns[] = $row['Field'];
        echo "   - {$row['Field']} ({$row['Type']})\n";
    }
    
    // Required columns for tasks table
    $requiredTasksColumns = ['id', 'title', 'description', 'level', 'reward', 'image', 'created_at', 'updated_at'];
    $missingTasksColumns = array_diff($requiredTasksColumns, $tasksColumns);
    
    if (!empty($missingTasksColumns)) {
        echo "\n   MISSING COLUMNS: " . implode(', ', $missingTasksColumns) . "\n";
    } else {
        echo "\n   ✅ All required columns present\n";
    }
    
    // Check completed_tasks table structure
    echo "\n2. COMPLETED_TASKS TABLE STRUCTURE:\n";
    $result = $conn->query("DESCRIBE completed_tasks");
    $completedTasksColumns = [];
    while ($row = $result->fetch()) {
        $completedTasksColumns[] = $row['Field'];
        echo "   - {$row['Field']} ({$row['Type']})\n";
    }
    
    // Required columns for completed_tasks table
    $requiredCompletedTasksColumns = ['id', 'user_id', 'task_id', 'level', 'answer', 'reward', 'completed_at'];
    $missingCompletedTasksColumns = array_diff($requiredCompletedTasksColumns, $completedTasksColumns);
    
    if (!empty($missingCompletedTasksColumns)) {
        echo "\n   MISSING COLUMNS: " . implode(', ', $missingCompletedTasksColumns) . "\n";
    } else {
        echo "\n   ✅ All required columns present\n";
    }
    
    // Check user_limits table structure
    echo "\n3. USER_LIMITS TABLE STRUCTURE:\n";
    try {
        $result = $conn->query("DESCRIBE user_limits");
        $userLimitsColumns = [];
        while ($row = $result->fetch()) {
            $userLimitsColumns[] = $row['Field'];
            echo "   - {$row['Field']} ({$row['Type']})\n";
        }
        
        // Required columns for user_limits table
        $requiredUserLimitsColumns = ['id', 'user_id', 'daily_task_limit', 'withdrawal_limit', 'min_withdrawal', 'max_withdrawal', 'can_withdraw', 'can_submit_tasks', 'is_active', 'created_at', 'updated_at'];
        $missingUserLimitsColumns = array_diff($requiredUserLimitsColumns, $userLimitsColumns);
        
        if (!empty($missingUserLimitsColumns)) {
            echo "\n   MISSING COLUMNS: " . implode(', ', $missingUserLimitsColumns) . "\n";
        } else {
            echo "\n   ✅ All required columns present\n";
        }
    } catch (Exception $e) {
        echo "   ❌ user_limits table does not exist\n";
    }
    
    // Check sample data
    echo "\n4. SAMPLE DATA CHECK:\n";
    
    // Check tasks count
    $result = $conn->query("SELECT COUNT(*) as count FROM tasks");
    $tasksCount = $result->fetch()['count'];
    echo "   - Tasks in database: $tasksCount\n";
    
    // Check completed_tasks count
    $result = $conn->query("SELECT COUNT(*) as count FROM completed_tasks");
    $completedTasksCount = $result->fetch()['count'];
    echo "   - Completed tasks: $completedTasksCount\n";
    
    // Check if tasks have required fields
    if ($tasksCount > 0) {
        $result = $conn->query("SELECT id, title, description, level, reward, image FROM tasks LIMIT 3");
        echo "   - Sample tasks:\n";
        while ($row = $result->fetch()) {
            echo "     ID: {$row['id']}, Title: " . ($row['title'] ?? 'NULL') . 
                 ", Level: " . ($row['level'] ?? 'NULL') . 
                 ", Reward: " . ($row['reward'] ?? 'NULL') . 
                 ", Image: " . ($row['image'] ?? 'NULL') . "\n";
        }
    }
    
    echo "\n=== DATABASE STRUCTURE CHECK COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
