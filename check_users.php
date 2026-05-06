<?php
require_once __DIR__ . '/config.php';

echo "=== CHECKING EXISTING USERS ===\n\n";

try {
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT id, fullname, email, username FROM users ORDER BY id LIMIT 10");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "No users found in the database.\n";
        
        // Check if there are any default users to create
        echo "\nCreating default test user...\n";
        $stmt = $conn->prepare("
            INSERT INTO users (fullname, email, username, password, level, balance, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $defaultUser = [
            'fullname' => 'Test User',
            'email' => 'test@handtoglobal.com',
            'username' => 'testuser',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'level' => 'Bronze',
            'balance' => 0.00
        ];
        
        $stmt->execute([
            $defaultUser['fullname'],
            $defaultUser['email'],
            $defaultUser['username'],
            $defaultUser['password'],
            $defaultUser['level'],
            $defaultUser['balance']
        ]);
        
        echo "✅ Default test user created:\n";
        echo "- Username: testuser\n";
        echo "- Password: password123\n";
        echo "- Email: test@handtoglobal.com\n";
        echo "- Level: Bronze\n";
        
    } else {
        echo "Found " . count($users) . " users:\n\n";
        foreach ($users as $user) {
            echo "ID: {$user['id']}\n";
            echo "Name: {$user['fullname']}\n";
            echo "Email: {$user['email']}\n";
            echo "Username: {$user['username']}\n";
            echo "---\n";
        }
        
        echo "\nFor testing, you can use:\n";
        echo "- Username: {$users[0]['username']}\n";
        echo "- Password: (check with admin or use password reset)\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
