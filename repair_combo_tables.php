<?php
/**
 * Repair Combo Tables
 * This script creates/repairs the combo system database tables
 */

require_once 'config.php';

echo "=== REPAIRING COMBO TABLES ===\n\n";

try {
    $conn = getConnection();
    echo "✅ Database connected successfully\n";
    
    // 1. Create/repair combos table
    echo "1. Creating/repairing combos table...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS combos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level VARCHAR(50) NOT NULL,
        start_task_id INT NOT NULL,
        end_task_id INT NOT NULL,
        message TEXT NOT NULL,
        deposit_required DECIMAL(12,2) NOT NULL DEFAULT 0,
        multiplier DECIMAL(6,2) NOT NULL DEFAULT 1,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($sql);
    echo "   ✅ combos table created/verified\n";
    
    // Check existing columns
    $stmt = $conn->prepare("DESCRIBE combos");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = ['id', 'level', 'start_task_id', 'end_task_id', 'message', 'deposit_required', 'multiplier', 'status', 'created_at'];
    foreach ($requiredColumns as $column) {
        if (in_array($column, $columns)) {
            echo "   ✅ $column exists\n";
        } else {
            echo "   ❌ $column missing\n";
        }
    }
    
    // 2. Create/repair user_combo_status table
    echo "\n2. Creating/repairing user_combo_status table...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS user_combo_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        combo_id INT NOT NULL,
        status ENUM('pending','resolved') DEFAULT 'pending',
        triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY unique_user_combo (user_id, combo_id)
    )";
    
    $conn->exec($sql);
    echo "   ✅ user_combo_status table created/verified\n";
    
    // Check existing columns
    $stmt = $conn->prepare("DESCRIBE user_combo_status");
    $stmt->execute();
    $userComboColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredUserComboColumns = ['id', 'user_id', 'combo_id', 'status', 'triggered_at', 'resolved_at'];
    foreach ($requiredUserComboColumns as $column) {
        if (in_array($column, $userComboColumns)) {
            echo "   ✅ $column exists\n";
        } else {
            echo "   ❌ $column missing\n";
        }
    }
    
    // 3. Insert sample combo data for testing
    echo "\n3. Inserting sample combo data...\n";
    
    // Check if sample data exists
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
    
    // 4. Verify table structure
    echo "\n4. Verifying final table structure...\n";
    
    echo "   combos table:\n";
    $stmt = $conn->prepare("SELECT * FROM combos LIMIT 1");
    $stmt->execute();
    $sampleCombo = $stmt->fetch();
    if ($sampleCombo) {
        foreach ($sampleCombo as $key => $value) {
            echo "     - $key: " . (is_string($value) ? substr($value, 0, 30) : $value) . "\n";
        }
    }
    
    echo "\n   user_combo_status table:\n";
    $stmt = $conn->prepare("SELECT * FROM user_combo_status LIMIT 1");
    $stmt->execute();
    $sampleStatus = $stmt->fetch();
    if ($sampleStatus) {
        foreach ($sampleStatus as $key => $value) {
            echo "     - $key: " . (is_string($value) ? substr($value, 0, 30) : $value) . "\n";
        }
    }
    
    echo "\n=== COMBO TABLES REPAIR COMPLETE ===\n";
    echo "✅ combos table: Ready for admin combo management\n";
    echo "✅ user_combo_status table: Ready for user combo tracking\n";
    echo "✅ Sample data: Inserted for testing\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SCRIPT COMPLETE ===\n";
?>
