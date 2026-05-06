<?php
/**
 * Fix Bronze Level Access for All Users
 * This script ensures Bronze level is unlocked for all users
 */

require_once __DIR__ . '/config.php';

echo "=== FIXING BRONZE LEVEL ACCESS ===\n\n";

try {
    $conn = getConnection();
    echo "✅ Database connected successfully\n";
    
    // Fix existing users - set Bronze as unlocked for everyone
    $sql = "UPDATE users 
            SET bronze_unlocked = 1, 
                level = COALESCE(NULLIF(level, ''), 'Bronze')
            WHERE bronze_unlocked = 0 OR bronze_unlocked IS NULL OR level = ''";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute();
    
    if ($result) {
        $affected = $stmt->rowCount();
        echo "✅ Updated $affected users to have Bronze unlocked\n";
    } else {
        echo "❌ Failed to update users\n";
    }
    
    // Verify the fix
    $checkSql = "SELECT COUNT(*) as total, 
                       SUM(CASE WHEN bronze_unlocked = 1 THEN 1 ELSE 0 END) as bronze_unlocked,
                       SUM(CASE WHEN level = 'Bronze' THEN 1 ELSE 0 END) as bronze_level
                FROM users";
    
    $stmt = $conn->query($checkSql);
    $stats = $stmt->fetch();
    
    echo "\n=== CURRENT USER STATISTICS ===\n";
    echo "👥 Total users: " . $stats['total'] . "\n";
    echo "🟡 Bronze unlocked: " . $stats['bronze_unlocked'] . "\n";
    echo "🟡 Bronze level: " . $stats['bronze_level'] . "\n";
    
    // Show sample users
    $sampleSql = "SELECT id, fullname, email, level, bronze_unlocked, silver_unlocked, gold_unlocked, platinum_unlocked 
                  FROM users 
                  ORDER BY id 
                  LIMIT 5";
    
    $stmt = $conn->query($sampleSql);
    $users = $stmt->fetchAll();
    
    echo "\n=== SAMPLE USER DATA ===\n";
    foreach ($users as $user) {
        echo "ID: {$user['id']} | {$user['fullname']} | Level: {$user['level']} | Bronze: " . ($user['bronze_unlocked'] ? '✅' : '❌') . "\n";
    }
    
    echo "\n✅ Bronze level access fix completed!\n";
    echo "All users now have Bronze unlocked by default.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SCRIPT COMPLETE ===\n";
?>
