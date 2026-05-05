<?php
/**
 * Add multiplier column to combos table safely
 */

require_once 'config.php';

echo "=== ADDING MULTIPLIER COLUMN TO COMBOS TABLE ===\n\n";

try {
    $conn = getConnection();
    
    // Check if multiplier column already exists
    $stmt = $conn->prepare("DESCRIBE combos");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('multiplier', $columns)) {
        echo "✅ Multiplier column already exists in combos table\n";
    } else {
        echo "Adding multiplier column to combos table...\n";
        
        // Add multiplier column
        $stmt = $conn->prepare("
            ALTER TABLE combos 
            ADD COLUMN multiplier DECIMAL(10,2) DEFAULT 1.00 
            AFTER amount
        ");
        $stmt->execute();
        
        echo "✅ Multiplier column added successfully\n";
        
        // Update existing records to have default multiplier of 1.00
        $stmt = $conn->prepare("
            UPDATE combos 
            SET multiplier = 1.00 
            WHERE multiplier IS NULL OR multiplier = 0
        ");
        $stmt->execute();
        
        echo "✅ Existing records updated with default multiplier\n";
    }
    
    // Verify the column was added
    $stmt = $conn->prepare("DESCRIBE combos");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('multiplier', $columns)) {
        echo "✅ Multiplier column verification successful\n";
        
        // Show current combos with multiplier
        $stmt = $conn->prepare("SELECT id, level, amount, multiplier FROM combos ORDER BY id");
        $stmt->execute();
        $combos = $stmt->fetchAll();
        
        echo "\nCurrent combos with multiplier:\n";
        foreach ($combos as $combo) {
            echo "- ID: {$combo['id']}, Level: {$combo['level']}, Amount: \${$combo['amount']}, Multiplier: {$combo['multiplier']}x\n";
        }
    } else {
        echo "❌ Multiplier column verification failed\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== MULTIPLIER COLUMN ADDITION COMPLETE ===\n";
?>
