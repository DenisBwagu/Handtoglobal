<?php
require_once 'config.php';

echo "=== CHECKING USERS TABLE STRUCTURE ===\n\n";

try {
    $conn = getConnection();
    
    // Check table structure
    $stmt = $conn->prepare("DESCRIBE users");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Users table columns:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n=== CHECKING EXISTING USERS ===\n\n";
    
    // Get users with available columns
    $stmt = $conn->prepare("SELECT * FROM users ORDER BY id LIMIT 5");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "No users found in the database.\n";
    } else {
        echo "Found " . count($users) . " users:\n\n";
        foreach ($users as $user) {
            echo "ID: {$user['id']}\n";
            if (isset($user['fullname'])) echo "Name: {$user['fullname']}\n";
            if (isset($user['email'])) echo "Email: {$user['email']}\n";
            if (isset($user['username'])) echo "Username: {$user['username']}\n";
            if (isset($user['level'])) echo "Level: {$user['level']}\n";
            if (isset($user['balance'])) echo "Balance: \${$user['balance']}\n";
            echo "---\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
