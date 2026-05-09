<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

echo "=== TESTING FLUSH FUNCTIONALITY ===\n\n";

// Test user ID (change as needed)
$userId = 1; // Change this to a real user ID
$level = 'Silver';

echo "User ID: $userId\n";
echo "Level: $level\n\n";

// First, unlock the level to have something to flush
echo "1. First, unlocking the level...\n";
$unlockResult = unlockLevelForUser($userId, $level);
echo "   Unlock result: " . ($unlockResult ? 'SUCCESS' : 'FAILED') . "\n";

// Add some completed tasks for testing
echo "\n2. Adding test completed tasks...\n";
$conn = getConnection();
try {
    // Get a task ID for this level
    $stmt = $conn->prepare("SELECT id FROM tasks WHERE level = ? LIMIT 1");
    $stmt->execute([$level]);
    $task = $stmt->fetch();
    
    if ($task) {
        // Add completed task
        $stmt = $conn->prepare("INSERT IGNORE INTO completed_tasks (user_id, task_id) VALUES (?, ?)");
        $stmt->execute([$userId, $task['id']]);
        echo "   Added completed task ID: " . $task['id'] . "\n";
    } else {
        echo "   No tasks found for level $level\n";
    }
} catch (PDOException $e) {
    echo "   Error adding test tasks: " . $e->getMessage() . "\n";
}

// Check status before flush
echo "\n3. Status before flush:\n";
$beforeStatus = isLevelUnlockedForUser($userId, $level);
echo "   is_unlocked: " . ($beforeStatus ? 'TRUE' : 'FALSE') . "\n";

// Check completed tasks count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM completed_tasks ct
    INNER JOIN tasks t ON ct.task_id = t.id
    WHERE ct.user_id = ? AND t.level = ?
");
$stmt->execute([$userId, $level]);
$beforeCount = $stmt->fetch()['count'];
echo "   completed tasks: $beforeCount\n";

// Test flush function
echo "\n4. Testing flush function:\n";
$flushResult = flushLevelForUser($userId, $level);
echo "   flushLevelForUser result: " . ($flushResult ? 'SUCCESS' : 'FAILED') . "\n";

// Check status after flush
echo "\n5. Status after flush:\n";
$afterStatus = isLevelUnlockedForUser($userId, $level);
echo "   is_unlocked: " . ($afterStatus ? 'TRUE' : 'FALSE') . "\n";

// Check completed tasks count after flush
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM completed_tasks ct
    INNER JOIN tasks t ON ct.task_id = t.id
    WHERE ct.user_id = ? AND t.level = ?
");
$stmt->execute([$userId, $level]);
$afterCount = $stmt->fetch()['count'];
echo "   completed tasks: $afterCount\n";

// Check user_levels table
$stmt = $conn->prepare("SELECT * FROM user_levels WHERE user_id = ? AND level = ?");
$stmt->execute([$userId, $level]);
$dbResult = $stmt->fetch();
if ($dbResult) {
    echo "   Database record after flush:\n";
    echo "   - is_unlocked: " . $dbResult['is_unlocked'] . "\n";
    echo "   - completed_count: " . $dbResult['completed_count'] . "\n";
    echo "   - flushed_at: " . ($dbResult['flushed_at'] ?? 'NULL') . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>
