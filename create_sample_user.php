<?php
/**
 * Create Sample User Account
 * This script creates a test user account for the HandToGlobal system
 */

require_once 'config.php';

echo "=== CREATING SAMPLE USER ACCOUNT ===\n\n";

try {
    $conn = getConnection();
    echo "✅ Database connected successfully\n";
    
    // Check if user already exists
    $checkEmail = 'testuser@handtoglobal.com';
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$checkEmail]);
    
    if ($stmt->fetch()) {
        echo "⚠️  User with email $checkEmail already exists\n";
        echo "Updating password instead...\n";
        
        // Update existing user
        $password = 'Test123456';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ?, is_blocked = 0, is_active = 1 WHERE email = ?");
        $stmt->execute([$hashedPassword, $checkEmail]);
        
        echo "✅ User password updated successfully\n";
    } else {
        // Create new user
        $password = 'Test123456';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (fullname, name, email, password, balance, level, rating, accuracy, total_tasks, bronze_unlocked, silver_unlocked, gold_unlocked, platinum_unlocked, is_blocked, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            'Test User',
            'TestUser',
            $checkEmail,
            $hashedPassword,
            0.00,
            'Bronze',
            0.00,
            0.00,
            0,
            1,
            0,
            0,
            0,
            0,
            1
        ]);
        
        echo "✅ Sample user created successfully\n";
    }
    
    // Get user details for confirmation
    $stmt = $conn->prepare("SELECT id, fullname, email, level, balance, created_at FROM users WHERE email = ?");
    $stmt->execute([$checkEmail]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "\n=== USER LOGIN DETAILS ===\n";
        echo "📧 Email: " . $user['email'] . "\n";
        echo "🔑 Password: Test123456\n";
        echo "👤 Name: " . $user['fullname'] . "\n";
        echo "📊 Level: " . $user['level'] . "\n";
        echo "💰 Balance: $" . number_format($user['balance'], 2) . "\n";
        echo "📅 Created: " . $user['created_at'] . "\n";
        echo "🆔 User ID: " . $user['id'] . "\n";
        echo "\n=== LOGIN URL ===\n";
        echo "🌐 http://localhost/handtoglobal/login.php\n";
        echo "\n=== ADMIN LOGIN URL ===\n";
        echo "🌐 http://localhost/handtoglobal/admin_login.php\n";
        echo "\n✅ Sample user account is ready for testing!\n";
    } else {
        echo "❌ Failed to retrieve user details\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SCRIPT COMPLETE ===\n";
?>
