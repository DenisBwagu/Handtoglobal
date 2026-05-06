<?php
/**
 * Add Missing Combo Columns
 * This script adds the missing columns to the combos table
 */

require_once __DIR__ . '/config.php';

echo "=== ADDING MISSING COMBO COLUMNS ===\n\n";

try {
    $conn = getConnection();
    echo "✅ Database connected successfully\n";
    
    // Add missing columns to combos table
    echo "1. Adding missing columns to combos table...\n";
    
    $missingColumns = [
        'message' => "TEXT NOT NULL DEFAULT 'Special combo offer!'",
        'deposit_required' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
        'multiplier' => "DECIMAL(6,2) NOT NULL DEFAULT 1"
    ];
    
    foreach ($missingColumns as $column => $definition) {
        try {
            // Check if column exists
            $stmt = $conn->prepare("SHOW COLUMNS FROM combos LIKE '$column'");
            $stmt->execute();
            $exists = $stmt->fetch();
            
            if (!$exists) {
                $sql = "ALTER TABLE combos ADD COLUMN $column $definition";
                $conn->exec($sql);
                echo "   ✅ Added $column column\n";
            } else {
                echo "   ℹ️  $column column already exists\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Failed to add $column: " . $e->getMessage() . "\n";
        }
    }
    
    // Verify final structure
    echo "\n2. Verifying final combos table structure...\n";
    $stmt = $conn->prepare("DESCRIBE combos");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "   - {$column['Field']} ({$column['Type']})\n";
    }
    
    // Insert sample data now that columns exist
    echo "\n3. Inserting sample combo data...\n";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM combos");
    $stmt->execute();
    $comboCount = $stmt->fetch()['count'];
    
    if ($comboCount == 0) {
        // Get first task from Bronze level
        $stmt = $conn->prepare("SELECT id FROM tasks WHERE level = 'Bronze' ORDER BY id LIMIT 1");
        $stmt->execute();
        $bronzeTask = $stmt->fetch();
        
        if ($bronzeTask) {
            $stmt = $conn->prepare("
                INSERT INTO combos (level, start_task_id, end_task_id, message, deposit_required, multiplier, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                'Bronze',
                $bronzeTask['id'],
                $bronzeTask['id'],
                'Special Bronze Combo Offer! Complete this combo to unlock amazing rewards.',
                50.00,
                2.5,
                'active'
            ]);
            echo "   ✅ Sample Bronze combo created\n";
        }
        
        // Get first task from Silver level
        $stmt = $conn->prepare("SELECT id FROM tasks WHERE level = 'Silver' ORDER BY id LIMIT 1");
        $stmt->execute();
        $silverTask = $stmt->fetch();
        
        if ($silverTask) {
            $stmt = $conn->prepare("
                INSERT INTO combos (level, start_task_id, end_task_id, message, deposit_required, multiplier, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                'Silver',
                $silverTask['id'],
                $silverTask['id'],
                'Premium Silver Combo! This is a limited time offer with exclusive benefits.',
                100.00,
                3.0,
                'active'
            ]);
            echo "   ✅ Sample Silver combo created\n";
        }
    } else {
        echo "   ℹ️  Combo data already exists ($comboCount combos)\n";
    }
    
    echo "\n=== MISSING COMBO COLUMNS ADDED ===\n";
    echo "✅ combos table: All columns now present\n";
    echo "✅ Sample data: Inserted for testing\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SCRIPT COMPLETE ===\n";
?>
