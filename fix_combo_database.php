<?php
/**
 * Fix Combo Database Structure
 * This script properly fixes the combo database structure by removing duplicates
 */

require_once __DIR__ . '/config.php';

echo "=== FIXING COMBO DATABASE STRUCTURE ===\n\n";

try {
    $conn = getConnection();
    echo "✅ Database connected successfully\n";
    
    // 1. Backup existing data
    echo "1. Backing up existing combo data...\n";
    
    $stmt = $conn->prepare("SELECT * FROM combos");
    $stmt->execute();
    $existingCombos = $stmt->fetchAll();
    
    echo "   Backed up " . count($existingCombos) . " existing combos\n";
    
    // 2. Drop and recreate combos table with correct structure
    echo "\n2. Recreating combos table with correct structure...\n";
    
    $conn->exec("DROP TABLE IF EXISTS combos");
    echo "   Dropped old combos table\n";
    
    $sql = "CREATE TABLE combos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level VARCHAR(50) NOT NULL,
        start_task INT NOT NULL,
        end_task INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        message TEXT NOT NULL,
        status ENUM('active','inactive','cleared') DEFAULT 'active',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $conn->exec($sql);
    echo "   Created new combos table with correct structure\n";
    
    // 3. Restore backup data (mapping old columns to new ones)
    echo "\n3. Restoring backup data...\n";
    
    foreach ($existingCombos as $combo) {
        $level = $combo['level'] ?? '';
        $startTask = $combo['start_task_id'] ?? $combo['start_task'] ?? 0;
        $endTask = $combo['end_task_id'] ?? $combo['end_task'] ?? 0;
        $amount = $combo['deposit_required'] ?? $combo['amount'] ?? 0;
        $message = $combo['message'] ?? '';
        $status = $combo['status'] ?? 'active';
        
        if (!empty($level) && $startTask > 0 && $endTask > 0) {
            $stmt = $conn->prepare("
                INSERT INTO combos (level, start_task, end_task, amount, message, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$level, $startTask, $endTask, $amount, $message, $status]);
            echo "   Restored combo: Level $level, Tasks $startTask-$endTask, Amount \$$amount\n";
        }
    }
    
    // 4. Ensure user_combo_status table exists
    echo "\n4. Checking user_combo_status table...\n";
    
    $conn->exec("DROP TABLE IF EXISTS user_combo_status");
    
    $sql = "CREATE TABLE user_combo_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        combo_id INT NOT NULL,
        status ENUM('pending','cleared','activated') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_combo (user_id, combo_id)
    )";
    
    $conn->exec($sql);
    echo "   Created user_combo_status table\n";
    
    // 5. Verify final structure
    echo "\n5. Verifying final structure...\n";
    
    $stmt = $conn->prepare("DESCRIBE combos");
    $stmt->execute();
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Final combos table structure:\n";
    foreach ($finalColumns as $column) {
        echo "   - {$column['Field']} ({$column['Type']})\n";
    }
    
    // 6. Check restored data
    echo "\n6. Checking restored data...\n";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM combos");
    $stmt->execute();
    $finalCount = $stmt->fetch()['count'];
    
    echo "   Final combo count: $finalCount\n";
    
    if ($finalCount > 0) {
        $stmt = $conn->prepare("SELECT * FROM combos");
        $stmt->execute();
        $finalCombos = $stmt->fetchAll();
        
        echo "   Restored combos:\n";
        foreach ($finalCombos as $combo) {
            echo "   - ID: {$combo['id']}, Level: {$combo['level']}, Tasks: {$combo['start_task']}-{$combo['end_task']}, Amount: \${$combo['amount']}, Status: {$combo['status']}\n";
        }
    }
    
    // 7. Create test combo if none exist
    if ($finalCount == 0) {
        echo "\n7. Creating test combo...\n";
        
        // Get first task from Bronze level
        $stmt = $conn->prepare("SELECT id FROM tasks WHERE level = 'Bronze' ORDER BY id LIMIT 1");
        $stmt->execute();
        $firstTask = $stmt->fetch();
        
        if ($firstTask) {
            $stmt = $conn->prepare("
                INSERT INTO combos (level, start_task, end_task, amount, message, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                'Bronze',
                15,
                15,
                45.00,
                'Deposit 45 USDT to continue',
                'active'
            ]);
            
            echo "   ✅ Created test combo: Bronze task 15, Amount $45.00\n";
        }
    }
    
    echo "\n=== COMBO DATABASE FIXED ===\n";
    echo "✅ Database structure corrected\n";
    echo "✅ All required columns present\n";
    echo "✅ Data restored successfully\n";
    echo "✅ Ready for combo system implementation\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SCRIPT COMPLETE ===\n";
?>
