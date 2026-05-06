<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

echo "=== TESTING PASSWORD RESET ===\n\n";

// Test user ID (change as needed)
$userId = 1; // Change this to a real user ID

echo "User ID: $userId\n\n";

$conn = getConnection();

// Check current password hash
echo "1. Current password status:\n";
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$currentPasswordHash = $user['password'] ?? '';
echo "   Current password hash length: " . strlen($currentPasswordHash) . "\n";
echo "   Starts with $2y$: " . (strpos($currentPasswordHash, '$2y$') === 0 ? 'YES' : 'NO') . "\n";

// Test password reset function
echo "\n2. Testing password reset...\n";
$newPassword = 'Test123456'; // Test password
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $result = $stmt->execute([$hashedPassword, $userId]);
    
    if ($result) {
        echo "   Password update result: SUCCESS\n";
        echo "   New password: $newPassword\n";
        echo "   New hash length: " . strlen($hashedPassword) . "\n";
        
        // Verify the password was saved
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $updatedUser = $stmt->fetch();
        $savedHash = $updatedUser['password'] ?? '';
        echo "   Saved hash matches: " . ($savedHash === $hashedPassword ? 'YES' : 'NO') . "\n";
        
        // Test password verification
        echo "   Password verification test:\n";
        $verification = password_verify($newPassword, $savedHash);
        echo "   - Correct password verifies: " . ($verification ? 'YES' : 'NO') . "\n";
        
        $wrongPassword = 'WrongPassword';
        $wrongVerification = password_verify($wrongPassword, $savedHash);
        echo "   - Wrong password verifies: " . ($wrongVerification ? 'YES' : 'NO') . "\n";
        
    } else {
        echo "   Password update result: FAILED\n";
    }
} catch (PDOException $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>
