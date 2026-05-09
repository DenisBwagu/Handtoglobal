<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

echo "=== TESTING UNLOCK FUNCTIONALITY ===\n\n";

// Test user ID (change as needed)
$userId = 1; // Change this to a real user ID
$level = 'Silver';

echo "User ID: $userId\n";
echo "Level: $level\n\n";

// Check current unlock status
echo "1. Current unlock status:\n";
$currentStatus = isLevelUnlockedForUser($userId, $level);
echo "   is_unlocked: " . ($currentStatus ? 'TRUE' : 'FALSE') . "\n\n";

// Check user_levels table directly
echo "2. Direct database check:\n";
$conn = getConnection();
$stmt = $conn->prepare("SELECT * FROM user_levels WHERE user_id = ? AND level = ?");
$stmt->execute([$userId, $level]);
$dbResult = $stmt->fetch();
if ($dbResult) {
    echo "   Found record in user_levels:\n";
    echo "   - id: " . $dbResult['id'] . "\n";
    echo "   - user_id: " . $dbResult['user_id'] . "\n";
    echo "   - level: " . $dbResult['level'] . "\n";
    echo "   - is_unlocked: " . $dbResult['is_unlocked'] . "\n";
    echo "   - unlocked_at: " . $dbResult['unlocked_at'] . "\n";
    echo "   - updated_at: " . $dbResult['updated_at'] . "\n";
} else {
    echo "   No record found in user_levels table\n";
}

// Check users table fallback
echo "\n3. Users table fallback check:\n";
$levelField = strtolower($level) . '_unlocked';
$stmt = $conn->prepare("SELECT id, $levelField as unlocked FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userResult = $stmt->fetch();
if ($userResult) {
    echo "   Found user record:\n";
    echo "   - id: " . $userResult['id'] . "\n";
    echo "   - {$levelField}: " . ($userResult['unlocked'] ?? 'NULL') . "\n";
} else {
    echo "   No user record found\n";
}

// Test unlock function
echo "\n4. Testing unlock function:\n";
$unlockResult = unlockLevelForUser($userId, $level);
echo "   unlockLevelForUser result: " . ($unlockResult ? 'SUCCESS' : 'FAILED') . "\n";

// Verify after unlock
echo "\n5. Verification after unlock:\n";
$newStatus = isLevelUnlockedForUser($userId, $level);
echo "   New is_unlocked: " . ($newStatus ? 'TRUE' : 'FALSE') . "\n";

// Check database again
$stmt = $conn->prepare("SELECT * FROM user_levels WHERE user_id = ? AND level = ?");
$stmt->execute([$userId, $level]);
$verifyResult = $stmt->fetch();
if ($verifyResult) {
    echo "   Database record after unlock:\n";
    echo "   - is_unlocked: " . $verifyResult['is_unlocked'] . "\n";
    echo "   - unlocked_at: " . $verifyResult['unlocked_at'] . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>
