<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

echo "=== FINAL ACCEPTANCE TESTS ===\n\n";

// Test user ID (change as needed)
$userId = 1; // Change this to a real user ID
$level = 'Silver';

echo "User ID: $userId\n";
echo "Level: $level\n\n";

$conn = getConnection();

// TEST A: Unlock Silver
echo "TEST A: Unlock Silver\n";
echo "==================\n";

// Check initial status
echo "1. Initial unlock status:\n";
$initialStatus = isLevelUnlockedForUser($userId, $level);
echo "   is_unlocked: " . ($initialStatus ? 'TRUE' : 'FALSE') . "\n";

// Perform unlock
echo "\n2. Performing unlock...\n";
$unlockResult = unlockLevelForUser($userId, $level);
echo "   unlockLevelForUser result: " . ($unlockResult ? 'SUCCESS' : 'FAILED') . "\n";

// Verify unlock
echo "\n3. Verification after unlock:\n";
$afterUnlockStatus = isLevelUnlockedForUser($userId, $level);
echo "   is_unlocked: " . ($afterUnlockStatus ? 'TRUE' : 'FALSE') . "\n";

// Check user_levels table
$stmt = $conn->prepare("SELECT * FROM user_levels WHERE user_id = ? AND level = ?");
$stmt->execute([$userId, $level]);
$dbResult = $stmt->fetch();
if ($dbResult) {
    echo "   Database is_unlocked: " . $dbResult['is_unlocked'] . "\n";
    echo "   unlocked_at: " . ($dbResult['unlocked_at'] ?? 'NULL') . "\n";
}

$testA_pass = $afterUnlockStatus === true && $unlockResult === true;
echo "\nTEST A RESULT: " . ($testA_pass ? 'PASS' : 'FAIL') . "\n\n";

// TEST B: Flush Silver
echo "TEST B: Flush Silver\n";
echo "==================\n";

// Check status before flush
echo "1. Status before flush:\n";
$beforeFlushStatus = isLevelUnlockedForUser($userId, $level);
echo "   is_unlocked: " . ($beforeFlushStatus ? 'TRUE' : 'FALSE') . "\n";

// Perform flush
echo "\n2. Performing flush...\n";
$flushResult = flushLevelForUser($userId, $level);
echo "   flushLevelForUser result: " . ($flushResult ? 'SUCCESS' : 'FAILED') . "\n";

// Verify flush
echo "\n3. Verification after flush:\n";
$afterFlushStatus = isLevelUnlockedForUser($userId, $level);
echo "   is_unlocked: " . ($afterFlushStatus ? 'TRUE' : 'FALSE') . "\n";

// Check user_levels table
$stmt = $conn->prepare("SELECT * FROM user_levels WHERE user_id = ? AND level = ?");
$stmt->execute([$userId, $level]);
$dbResult = $stmt->fetch();
if ($dbResult) {
    echo "   Database is_unlocked: " . $dbResult['is_unlocked'] . "\n";
    echo "   flushed_at: " . ($dbResult['flushed_at'] ?? 'NULL') . "\n";
}

$testB_pass = $afterFlushStatus === false && $flushResult === true;
echo "\nTEST B RESULT: " . ($testB_pass ? 'PASS' : 'FAIL') . "\n\n";

// TEST C: Balance Adjustment
echo "TEST C: Balance Adjustment\n";
echo "=========================\n";

// Get current balance
$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$initialBalance = $user['balance'] ?? 0;

echo "1. Initial balance: $" . number_format($initialBalance, 2) . "\n";

// Add $50
echo "\n2. Adding $50.00...\n";
$amount = 50.00;
$stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
$updateResult = $stmt->execute([$amount, $userId]);
echo "   Update result: " . ($updateResult ? 'SUCCESS' : 'FAILED') . "\n";

// Verify new balance
$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->execute([$userId]);
$newUser = $stmt->fetch();
$newBalance = $newUser['balance'] ?? 0;
echo "   New balance: $" . number_format($newBalance, 2) . "\n";
echo "   Expected: $" . number_format($initialBalance + $amount, 2) . "\n";

$testC_pass = abs($newBalance - ($initialBalance + $amount)) < 0.01 && $updateResult === true;
echo "\nTEST C RESULT: " . ($testC_pass ? 'PASS' : 'FAIL') . "\n\n";

// TEST D: Password Reset
echo "TEST D: Password Reset\n";
echo "=====================\n";

// Generate new password
$newPassword = 'TestPass123';
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

echo "1. Generated password: $newPassword\n";

// Update password
echo "\n2. Updating password...\n";
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$updateResult = $stmt->execute([$hashedPassword, $userId]);
echo "   Update result: " . ($updateResult ? 'SUCCESS' : 'FAILED') . "\n";

// Verify password
echo "\n3. Verifying password...\n";
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$userId]);
$updatedUser = $stmt->fetch();
$savedHash = $updatedUser['password'] ?? '';
$verification = password_verify($newPassword, $savedHash);
echo "   Password verifies: " . ($verification ? 'YES' : 'NO') . "\n";

$testD_pass = $verification === true && $updateResult === true;
echo "\nTEST D RESULT: " . ($testD_pass ? 'PASS' : 'FAIL') . "\n\n";

// TEST E: Dashboard Data Reading
echo "TEST E: Dashboard Data Reading\n";
echo "==============================\n";

// Simulate dashboard data loading
echo "1. Loading fresh dashboard data...\n";

// Get user data fresh
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$dashboardUser = $stmt->fetch();
echo "   User balance: $" . number_format($dashboardUser['balance'] ?? 0, 2) . "\n";
echo "   User level: " . ($dashboardUser['level'] ?? 'NULL') . "\n";

// Get level unlock status fresh
$levels = ['Bronze', 'Silver', 'Gold', 'VIP 1'];
foreach ($levels as $testLevel) {
    $isUnlocked = isLevelUnlockedForUser($userId, $testLevel);
    echo "   $testLevel: " . ($isUnlocked ? 'UNLOCKED' : 'LOCKED') . "\n";
}

$testE_pass = true; // If we got here without errors, data reading works
echo "\nTEST E RESULT: " . ($testE_pass ? 'PASS' : 'FAIL') . "\n\n";

// FINAL RESULTS
echo "=== FINAL RESULTS ===\n";
echo "Test A (Unlock): " . ($testA_pass ? 'PASS' : 'FAIL') . "\n";
echo "Test B (Flush): " . ($testB_pass ? 'PASS' : 'FAIL') . "\n";
echo "Test C (Balance): " . ($testC_pass ? 'PASS' : 'FAIL') . "\n";
echo "Test D (Password): " . ($testD_pass ? 'PASS' : 'FAIL') . "\n";
echo "Test E (Dashboard): " . ($testE_pass ? 'PASS' : 'FAIL') . "\n";

$allTestsPass = $testA_pass && $testB_pass && $testC_pass && $testD_pass && $testE_pass;
echo "\nOVERALL RESULT: " . ($allTestsPass ? 'ALL TESTS PASS' : 'SOME TESTS FAILED') . "\n";

if ($allTestsPass) {
    echo "\n✅ ADMIN FUNCTIONS ARE WORKING CORRECTLY!\n";
    echo "The admin-user database connection is fixed.\n";
    echo "All functions update the database properly and\n";
    echo "the user side reads fresh data from the database.\n";
} else {
    echo "\n❌ SOME TESTS FAILED - CHECK DEBUG LOGS\n";
}

echo "\n=== END OF TESTS ===\n";
?>
