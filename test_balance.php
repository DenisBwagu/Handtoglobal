<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

echo "=== TESTING BALANCE ADJUSTMENT ===\n\n";

// Test user ID (change as needed)
$userId = 1; // Change this to a real user ID

echo "User ID: $userId\n\n";

$conn = getConnection();

// Check current balance
echo "1. Current balance:\n";
$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$currentBalance = $user['balance'] ?? 0;
echo "   Current balance: $" . number_format($currentBalance, 2) . "\n";

// Test balance addition
echo "\n2. Testing balance addition (+$50.00)...\n";
$amount = 50.00;
try {
    $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $result = $stmt->execute([$amount, $userId]);
    
    if ($result) {
        echo "   Balance update result: SUCCESS\n";
        
        // Verify new balance
        $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $newUser = $stmt->fetch();
        $newBalance = $newUser['balance'] ?? 0;
        echo "   New balance: $" . number_format($newBalance, 2) . "\n";
        echo "   Expected: $" . number_format($currentBalance + $amount, 2) . "\n";
        echo "   Match: " . (abs($newBalance - ($currentBalance + $amount)) < 0.01 ? 'YES' : 'NO') . "\n";
    } else {
        echo "   Balance update result: FAILED\n";
    }
} catch (PDOException $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Test balance subtraction
echo "\n3. Testing balance subtraction (-$20.00)...\n";
$amount = 20.00;
try {
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userBefore = $stmt->fetch();
    $balanceBefore = $userBefore['balance'] ?? 0;
    
    $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
    $result = $stmt->execute([$amount, $userId]);
    
    if ($result) {
        echo "   Balance update result: SUCCESS\n";
        
        // Verify new balance
        $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userAfter = $stmt->fetch();
        $balanceAfter = $userAfter['balance'] ?? 0;
        echo "   Balance before: $" . number_format($balanceBefore, 2) . "\n";
        echo "   Balance after: $" . number_format($balanceAfter, 2) . "\n";
        echo "   Expected: $" . number_format($balanceBefore - $amount, 2) . "\n";
        echo "   Match: " . (abs($balanceAfter - ($balanceBefore - $amount)) < 0.01 ? 'YES' : 'NO') . "\n";
    } else {
        echo "   Balance update result: FAILED\n";
    }
} catch (PDOException $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>
