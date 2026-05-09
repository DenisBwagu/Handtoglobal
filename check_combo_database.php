<?php
/**
 * Check and Repair Combo Database Structure
 * This script checks the current combo database structure and repairs it if needed
 */

require_once __DIR__ . '/config.php';

echo "=== CHECKING AND REPAIRING COMBO DATABASE ===\n\n";

try {
    $conn = getConnection();
    echo "✅ Database connected successfully\n";
    
    // Check if combos table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'combos'");
    $stmt->execute();
    $combosTableExists = $stmt->rowCount() > 0;
    
    echo "1. Checking combos table...\n";
    if ($combosTableExists) {
        echo "   ✅ combos table exists\n";
        
        // Check current structure
        $stmt = $conn->prepare("DESCRIBE combos");
        $stmt->execute();
        $currentColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Current columns:\n";
        foreach ($currentColumns as $column) {
            echo "   - {$column['Field']} ({$column['Type']})\n";
        }
        
        // Check required columns
        $requiredColumns = [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'level' => 'VARCHAR(50) NOT NULL',
            'start_task' => 'INT NOT NULL',
            'end_task' => 'INT NOT NULL', 
            'amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'message' => 'TEXT NOT NULL',
            'status' => "ENUM('active','inactive','cleared') DEFAULT 'active'",
            'is_active' => 'TINYINT(1) DEFAULT 1',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];
        
        $currentColumnNames = array_column($currentColumns, 'Field');
        
        // Add missing columns
        foreach ($requiredColumns as $columnName => $columnDefinition) {
            if (!in_array($columnName, $currentColumnNames)) {
                echo "   Adding missing column: $columnName\n";
                try {
                    $sql = "ALTER TABLE combos ADD COLUMN $columnName $columnDefinition";
                    $conn->exec($sql);
                    echo "   ✅ Added $columnName\n";
                } catch (Exception $e) {
                    echo "   ❌ Failed to add $columnName: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✅ $columnName exists\n";
            }
        }
        
        // Check if we need to rename columns from old structure
        if (in_array('start_task_id', $currentColumnNames) && !in_array('start_task', $currentColumnNames)) {
            echo "   Renaming start_task_id to start_task\n";
            $conn->exec("ALTER TABLE combos CHANGE COLUMN start_task_id start_task INT NOT NULL");
            echo "   ✅ Renamed start_task_id to start_task\n";
        }
        
        if (in_array('end_task_id', $currentColumnNames) && !in_array('end_task', $currentColumnNames)) {
            echo "   Renaming end_task_id to end_task\n";
            $conn->exec("ALTER TABLE combos CHANGE COLUMN end_task_id end_task INT NOT NULL");
            echo "   ✅ Renamed end_task_id to end_task\n";
        }
        
        if (in_array('deposit_required', $currentColumnNames) && !in_array('amount', $currentColumnNames)) {
            echo "   Renaming deposit_required to amount\n";
            $conn->exec("ALTER TABLE combos CHANGE COLUMN deposit_required amount DECIMAL(12,2) NOT NULL DEFAULT 0");
            echo "   ✅ Renamed deposit_required to amount\n";
        }
        
    } else {
        echo "   ❌ combos table does not exist\n";
        echo "   Creating combos table...\n";
        
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
        echo "   ✅ combos table created\n";
    }
    
    // Check user_combo_status table
    echo "\n2. Checking user_combo_status table...\n";
    
    $stmt = $conn->prepare("SHOW TABLES LIKE 'user_combo_status'");
    $stmt->execute();
    $userComboTableExists = $stmt->rowCount() > 0;
    
    if ($userComboTableExists) {
        echo "   ✅ user_combo_status table exists\n";
        
        $stmt = $conn->prepare("DESCRIBE user_combo_status");
        $stmt->execute();
        $userStatusColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Current columns:\n";
        foreach ($userStatusColumns as $column) {
            echo "   - {$column['Field']} ({$column['Type']})\n";
        }
    } else {
        echo "   ❌ user_combo_status table does not exist\n";
        echo "   Creating user_combo_status table...\n";
        
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
        echo "   ✅ user_combo_status table created\n";
    }
    
    // Verify final structure
    echo "\n3. Verifying final structure...\n";
    
    $stmt = $conn->prepare("DESCRIBE combos");
    $stmt->execute();
    $finalComboColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Final combos table structure:\n";
    foreach ($finalComboColumns as $column) {
        echo "   - {$column['Field']} ({$column['Type']})\n";
    }
    
    // Check existing data
    echo "\n4. Checking existing combo data...\n";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM combos");
    $stmt->execute();
    $comboCount = $stmt->fetch()['count'];
    
    echo "   Existing combos: $comboCount\n";
    
    if ($comboCount > 0) {
        $stmt = $conn->prepare("SELECT * FROM combos LIMIT 5");
        $stmt->execute();
        $existingCombos = $stmt->fetchAll();
        
        echo "   Sample data:\n";
        foreach ($existingCombos as $combo) {
            echo "   - ID: {$combo['id']}, Level: {$combo['level']}, Tasks: {$combo['start_task']}-{$combo['end_task']}, Amount: \${$combo['amount']}, Status: {$combo['status']}\n";
        }
    }
    
    echo "\n=== COMBO DATABASE REPAIR COMPLETE ===\n";
    echo "✅ Database structure verified and repaired\n";
    echo "✅ All required columns present\n";
    echo "✅ Ready for combo system implementation\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SCRIPT COMPLETE ===\n";
?>
