<?php
/**
 * Test Flushed Level Display
 * This script verifies that flushed levels show as locked on the dashboard
 */

require_once 'config.php';

echo "=== TESTING FLUSHED LEVEL DISPLAY ===\n\n";

try {
    $conn = getConnection();
    echo "✅ Database connected successfully\n";
    
    $testUserId = 3; // Test user
    
    // Test 1: Check current level status
    echo "1. Checking current level status...\n";
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$testUserId]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "   Current level: {$user['level']}\n";
        echo "   Bronze unlocked: " . ($user['bronze_unlocked'] ? '✅' : '❌') . "\n";
        echo "   Silver unlocked: " . ($user['silver_unlocked'] ? '✅' : '❌') . "\n";
        echo "   Gold unlocked: " . ($user['gold_unlocked'] ? '✅' : '❌') . "\n";
        echo "   Platinum unlocked: " . ($user['platinum_unlocked'] ? '✅' : '❌') . "\n";
    }
    
    // Test 2: Simulate dashboard logic for level display
    echo "\n2. Simulating dashboard level display logic...\n";
    
    // Get levels (same as dashboard)
    $levelRecords = getAppLevels();
    $levels = array_column($levelRecords, 'name');
    
    $unlocked_levels = [];
    foreach ($levels as $level) {
        // Bronze is always unlocked, check others normally
        if ($level === 'Bronze') {
            $unlocked_levels[$level] = true;
            echo "   Bronze: ALWAYS UNLOCKED\n";
        } else {
            $isUnlocked = isLevelUnlockedForUser($testUserId, $level);
            $unlocked_levels[$level] = $isUnlocked;
            echo "   {$level}: " . ($isUnlocked ? 'UNLOCKED' : 'LOCKED') . "\n";
        }
    }
    
    // Test 3: Simulate dashboard status calculation
    echo "\n3. Testing dashboard status calculation...\n";
    
    // Simulate current level (from user data)
    $currentLevel = $user['level'];
    echo "   Current level: $currentLevel\n";
    
    foreach ($levels as $level) {
        $is_current = normalizeLevelName($currentLevel) === normalizeLevelName($level);
        $is_unlocked = $unlocked_levels[$level]; // Fixed logic - no OR with current
        
        // New logic: current only if current AND unlocked
        $level_status = $is_current && $is_unlocked ? 'current' : ($is_unlocked ? 'progress' : 'locked');
        
        echo "   {$level}: $level_status";
        if ($is_current) echo " (current)";
        echo "\n";
    }
    
    // Test 4: Test flush Silver and check display
    echo "\n4. Testing flush Silver and check display...\n";
    
    // Flush Silver level
    $flushResult = flushLevelForUser($testUserId, 'Silver');
    echo "   Flush Silver result: " . ($flushResult ? '✅ SUCCESS' : '❌ FAILED') . "\n";
    
    // Check unlock status after flush
    $isSilverUnlocked = isLevelUnlockedForUser($testUserId, 'Silver');
    echo "   Silver unlock status after flush: " . ($isSilverUnlocked ? 'UNLOCKED' : 'LOCKED') . "\n";
    
    // Test 5: Simulate dashboard after flush
    echo "\n5. Simulating dashboard after flush...\n";
    
    // Refresh unlock status
    $unlocked_levels = [];
    foreach ($levels as $level) {
        if ($level === 'Bronze') {
            $unlocked_levels[$level] = true;
        } else {
            $isUnlocked = isLevelUnlockedForUser($testUserId, $level);
            $unlocked_levels[$level] = $isUnlocked;
        }
    }
    
    foreach ($levels as $level) {
        $is_current = normalizeLevelName($currentLevel) === normalizeLevelName($level);
        $is_unlocked = $unlocked_levels[$level];
        
        $level_status = $is_current && $is_unlocked ? 'current' : ($is_unlocked ? 'progress' : 'locked');
        
        echo "   {$level}: $level_status";
        if ($is_current) echo " (current)";
        echo "\n";
    }
    
    // Test 6: Verify expected behavior
    echo "\n6. Verifying expected behavior...\n";
    
    // Check if Silver shows as locked (check both Silver and Sliver)
    $silverStatus = 'unknown';
    foreach ($levels as $level) {
        if (normalizeLevelName($level) === normalizeLevelName('Silver')) {
            $is_current = normalizeLevelName($currentLevel) === normalizeLevelName($level);
            $is_unlocked = $unlocked_levels[$level];
            $silverStatus = $is_current && $is_unlocked ? 'current' : ($is_unlocked ? 'progress' : 'locked');
            echo "   Found Silver level: $level, status: $silverStatus\n";
            break;
        }
    }
    
    echo "   Silver status after flush: $silverStatus\n";
    
    if ($silverStatus === 'locked') {
        echo "   ✅ Silver correctly shows as LOCKED\n";
    } else {
        echo "   ❌ Silver should show as LOCKED but shows: $silverStatus\n";
    }
    
    // Check if Bronze still shows as current (if user's current level is Bronze)
    if ($currentLevel === 'Bronze') {
        $bronzeStatus = 'unknown';
        foreach ($levels as $level) {
            if ($level === 'Bronze') {
                $is_current = normalizeLevelName($currentLevel) === normalizeLevelName($level);
                $is_unlocked = $unlocked_levels[$level];
                $bronzeStatus = $is_current && $is_unlocked ? 'current' : ($is_unlocked ? 'progress' : 'locked');
                break;
            }
        }
        echo "   Bronze status: $bronzeStatus\n";
        if ($bronzeStatus === 'current') {
            echo "   ✅ Bronze correctly shows as CURRENT\n";
        } else {
            echo "   ❌ Bronze should show as CURRENT but shows: $bronzeStatus\n";
        }
    }
    
    echo "\n=== EXPECTED DASHBOARD DISPLAY ===\n";
    echo "After flushing Silver level:\n";
    echo "- Bronze: Should show as CURRENT (if user's current level)\n";
    echo "- Silver: Should show as LOCKED (yellow status)\n";
    echo "- Gold: Should show as PROGRESS or LOCKED based on unlock status\n";
    echo "- Platinum: Should show as PROGRESS or LOCKED based on unlock status\n";
    
    echo "\n=== USER EXPERIENCE ===\n";
    echo "1. Admin flushes Silver level\n";
    echo "2. User refreshes dashboard\n";
    echo "3. Silver card shows LOCKED status (yellow)\n";
    echo "4. Clicking Silver shows locked modal\n";
    echo "5. User cannot access Silver tasks\n";
    
    echo "\n=== FLUSHED LEVEL DISPLAY TEST COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SCRIPT COMPLETE ===\n";
?>
