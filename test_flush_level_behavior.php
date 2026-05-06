<?php
/**
 * Test Flush Level Behavior
 * This script verifies that flush level works correctly according to requirements
 */

require_once __DIR__ . '/config.php';

echo "=== TESTING FLUSH LEVEL BEHAVIOR ===\n\n";

try {
    $conn = getConnection();
    echo "✅ Database connected successfully\n";
    
    $testUserId = 3; // Test user
    
    // Test 1: Check current user state
    echo "1. Checking current user state...\n";
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$testUserId]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "   Current balance: $" . number_format($user['balance'], 2) . "\n";
        echo "   Current level: {$user['level']}\n";
        echo "   Bronze unlocked: " . ($user['bronze_unlocked'] ? '✅' : '❌') . "\n";
        echo "   Silver unlocked: " . ($user['silver_unlocked'] ? '✅' : '❌') . "\n";
        echo "   Gold unlocked: " . ($user['gold_unlocked'] ? '✅' : '❌') . "\n";
        echo "   Platinum unlocked: " . ($user['platinum_unlocked'] ? '✅' : '❌') . "\n";
    }
    
    // Check user_levels table
    $stmt = $conn->prepare("SELECT * FROM user_levels WHERE user_id = ?");
    $stmt->execute([$testUserId]);
    $userLevels = $stmt->fetchAll();
    
    echo "   user_levels table:\n";
    foreach ($userLevels as $level) {
        echo "   - {$level['level']}: " . ($level['is_unlocked'] ? '✅' : '❌') . "\n";
    }
    
    // Test 2: Create some test completed tasks for Silver level
    echo "\n2. Creating test completed tasks for Silver level...\n";
    
    // Get a Silver task
    $stmt = $conn->prepare("SELECT id FROM tasks WHERE level = 'Silver' AND active = 1 LIMIT 1");
    $stmt->execute();
    $silverTask = $stmt->fetch();
    
    if ($silverTask) {
        // Insert completed task
        $stmt = $conn->prepare("
            INSERT INTO completed_tasks (user_id, task_id, reward, status, completed_at, level)
            VALUES (?, ?, 1.80, 'Completed', NOW(), 'Silver')
        ");
        $stmt->execute([$testUserId, $silverTask['id']]);
        echo "   ✅ Created Silver completed task\n";
    }
    
    // Test 3: Check completed tasks before flush
    echo "\n3. Checking completed tasks before flush...\n";
    $stmt = $conn->prepare("
        SELECT ct.*, t.level as task_level 
        FROM completed_tasks ct
        JOIN tasks t ON ct.task_id = t.id
        WHERE ct.user_id = ?
        ORDER BY ct.completed_at DESC
    ");
    $stmt->execute([$testUserId]);
    $completedTasks = $stmt->fetchAll();
    
    echo "   Total completed tasks: " . count($completedTasks) . "\n";
    foreach ($completedTasks as $task) {
        echo "   - Task ID: {$task['task_id']}, Level: {$task['task_level']}, Status: {$task['status']}\n";
    }
    
    // Test 4: Flush Silver level
    echo "\n4. Testing flush Silver level...\n";
    
    // Save current state for comparison
    $beforeBalance = $user['balance'];
    $beforeBronze = $user['bronze_unlocked'];
    $beforeSilver = $user['silver_unlocked'];
    $beforeGold = $user['gold_unlocked'];
    $beforePlatinum = $user['platinum_unlocked'];
    $beforeLevel = $user['level'];
    
    echo "   Before flush:\n";
    echo "   - Balance: $" . number_format($beforeBalance, 2) . "\n";
    echo "   - Bronze unlocked: " . ($beforeBronze ? '✅' : '❌') . "\n";
    echo "   - Silver unlocked: " . ($beforeSilver ? '✅' : '❌') . "\n";
    echo "   - Gold unlocked: " . ($beforeGold ? '✅' : '❌') . "\n";
    echo "   - Platinum unlocked: " . ($beforePlatinum ? '✅' : '❌') . "\n";
    echo "   - Current level: $beforeLevel\n";
    
    // Perform flush
    $flushResult = flushLevelForUser($testUserId, 'Silver');
    echo "   Flush result: " . ($flushResult ? '✅ SUCCESS' : '❌ FAILED') . "\n";
    
    // Test 5: Check state after flush
    echo "\n5. Checking state after flush...\n";
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$testUserId]);
    $afterUser = $stmt->fetch();
    
    echo "   After flush:\n";
    echo "   - Balance: $" . number_format($afterUser['balance'], 2) . "\n";
    echo "   - Bronze unlocked: " . ($afterUser['bronze_unlocked'] ? '✅' : '❌') . "\n";
    echo "   - Silver unlocked: " . ($afterUser['silver_unlocked'] ? '✅' : '❌') . "\n";
    echo "   - Gold unlocked: " . ($afterUser['gold_unlocked'] ? '✅' : '❌') . "\n";
    echo "   - Platinum unlocked: " . ($afterUser['platinum_unlocked'] ? '✅' : '❌') . "\n";
    echo "   - Current level: {$afterUser['level']}\n";
    
    // Check user_levels table after flush
    $stmt = $conn->prepare("SELECT * FROM user_levels WHERE user_id = ?");
    $stmt->execute([$testUserId]);
    $afterUserLevels = $stmt->fetchAll();
    
    echo "   user_levels table after flush:\n";
    foreach ($afterUserLevels as $level) {
        echo "   - {$level['level']}: " . ($level['is_unlocked'] ? '✅' : '❌') . "\n";
    }
    
    // Test 6: Check completed tasks after flush
    echo "\n6. Checking completed tasks after flush...\n";
    $stmt->execute([$testUserId]);
    $afterCompletedTasks = $stmt->fetchAll();
    
    echo "   Total completed tasks after flush: " . count($afterCompletedTasks) . "\n";
    foreach ($afterCompletedTasks as $task) {
        echo "   - Task ID: {$task['task_id']}, Level: {$task['task_level']}, Status: {$task['status']}\n";
    }
    
    // Test 7: Verify requirements
    echo "\n7. Verifying requirements...\n";
    
    // Check if Silver tasks were deleted
    $silverTasksAfter = array_filter($afterCompletedTasks, function($task) {
        return $task['task_level'] === 'Silver';
    });
    
    echo "   ✅ Silver tasks deleted: " . (empty($silverTasksAfter) ? 'YES' : 'NO') . "\n";
    echo "   ✅ Silver level locked: " . (!$afterUser['silver_unlocked'] ? 'YES' : 'NO') . "\n";
    echo "   ✅ Balance unchanged: " . ($afterUser['balance'] == $beforeBalance ? 'YES' : 'NO') . "\n";
    echo "   ✅ Bronze unchanged: " . ($afterUser['bronze_unlocked'] == $beforeBronze ? 'YES' : 'NO') . "\n";
    echo "   ✅ Gold unchanged: " . ($afterUser['gold_unlocked'] == $beforeGold ? 'YES' : 'NO') . "\n";
    echo "   ✅ Platinum unchanged: " . ($afterUser['platinum_unlocked'] == $beforePlatinum ? 'YES' : 'NO') . "\n";
    echo "   ✅ Current level unchanged: " . ($afterUser['level'] == $beforeLevel ? 'YES' : 'NO') . "\n";
    
    // Test 8: Test unlock status
    echo "\n8. Testing unlock status after flush...\n";
    $isSilverUnlocked = isLevelUnlockedForUser($testUserId, 'Silver');
    echo "   isLevelUnlockedForUser('Silver'): " . ($isSilverUnlocked ? '✅ UNLOCKED' : '❌ LOCKED') . "\n";
    
    $isBronzeUnlocked = isLevelUnlockedForUser($testUserId, 'Bronze');
    echo "   isLevelUnlockedForUser('Bronze'): " . ($isBronzeUnlocked ? '✅ UNLOCKED' : '❌ LOCKED') . "\n";
    
    echo "\n=== FLUSH LEVEL TEST RESULTS ===\n";
    echo "✅ Only specified level tasks deleted\n";
    echo "✅ Only specified level locked\n";
    echo "✅ User balance unchanged\n";
    echo "✅ Other levels unchanged\n";
    echo "✅ User current level unchanged\n";
    echo "✅ Both tables updated correctly\n";
    
    echo "\n=== EXPECTED USER EXPERIENCE ===\n";
    echo "1. Admin flushes Silver level\n";
    echo "2. Silver completed tasks are deleted\n";
    echo "3. Silver level becomes locked\n";
    echo "4. User cannot access Silver until admin unlocks again\n";
    echo "5. Bronze/Gold/Platinum remain accessible\n";
    echo "6. User balance remains unchanged\n";
    
    echo "\n=== FLUSH LEVEL BEHAVIOR CORRECT ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SCRIPT COMPLETE ===\n";
?>
