<?php
require_once __DIR__ . '/config.php';

echo "=== SETTING TEST PASSWORD ===\n\n";

try {
    $conn = getConnection();
    
    // Set a known password for the test user
    $email = 'testuser@handtoglobal.com';
    $password = 'password123'; // Simple test password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hashedPassword, $email]);
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Password updated successfully for test user\n\n";
        echo "Login Details:\n";
        echo "Email: $email\n";
        echo "Password: $password\n";
        echo "Level: Gold\n";
        echo "Balance: \$114.40\n";
    } else {
        echo "❌ User not found with email: $email\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
