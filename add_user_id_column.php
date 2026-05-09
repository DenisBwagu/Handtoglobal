<?php
/**
 * Add user_id column to combos table safely
 */

require_once __DIR__ . '/config.php';

echo "=== ADDING USER_ID COLUMN TO COMBOS TABLE ===\n\n";

try {
    $conn = getConnection();
    
    // Check if user_id column already exists
    $stmt = $conn->prepare("DESCRIBE combos");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('user_id', $columns)) {
        echo "✅ user_id column already exists in combos table\n";
    } else {
        echo "Adding user_id column to combos table...\n";
        
        // Add user_id column
        $stmt = $conn->prepare("
            ALTER TABLE combos 
            ADD COLUMN user_id INT NULL 
            AFTER multiplier
        ");
        $stmt->execute();
        
        echo "✅ user_id column added successfully\n";
    }
    
    // Verify the column was added
    $stmt = $conn->prepare("DESCRIBE combos");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('user_id', $columns)) {
        echo "✅ user_id column verification successful\n";
        
        // Show current combos with user_id
        $stmt = $conn->prepare("SELECT id, level, user_id FROM combos ORDER BY id");
        $stmt->execute();
        $combos = $stmt->fetchAll();
        
        echo "\nCurrent combos with user_id:\n";
        foreach ($combos as $combo) {
            $userDisplay = $combo['user_id'] ? "User ID: {$combo['user_id']}" : "All Users";
            echo "- ID: {$combo['id']}, Level: {$combo['level']}, User: $userDisplay\n";
        }
    } else {
        echo "❌ user_id column verification failed\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== USER_ID COLUMN ADDITION COMPLETE ===\n";
?>
