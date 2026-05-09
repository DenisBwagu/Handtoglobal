<?php
/**
 * Create New Combo Tables
 * This script creates the correct combo system tables
 */

require_once __DIR__ . '/config.php';

echo "=== CREATING NEW COMBO TABLES ===\n\n";

try {
    $conn = getConnection();
    echo "✅ Database connected successfully\n";
    
    // Drop existing tables if they exist with wrong structure
    echo "1. Dropping existing tables with wrong structure...\n";
    
    $conn->exec("DROP TABLE IF EXISTS combos");
    echo "   ✅ Dropped old combos table\n";
    
    $conn->exec("DROP TABLE IF EXISTS user_combo_status");
    echo "   ✅ Dropped old user_combo_status table\n";
    
    // 2. Create new combos table with correct structure
    echo "\n2. Creating new combos table with correct structure...\n";
    
    $sql = "CREATE TABLE combos (
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
    echo "   ✅ New combos table created\n";
    
    // 3. Create user_combo_status table
    echo "\n3. Creating user_combo_status table...\n";
    
    $sql = "CREATE TABLE user_combo_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        combo_id INT NOT NULL,
        status ENUM('pending','resolved') DEFAULT 'pending',
        triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY unique_user_combo (user_id, combo_id)
    )";
    
    $conn->exec($sql);
    echo "   ✅ user_combo_status table created\n";
    
    // 4. Insert sample combo data
    echo "\n4. Inserting sample combo data...\n";
    
    // Get tasks from Bronze level
    $stmt = $conn->prepare("SELECT id, title FROM tasks WHERE level = 'Bronze' ORDER BY id LIMIT 3");
    $stmt->execute();
    $bronzeTasks = $stmt->fetchAll();
    
    if (count($bronzeTasks) >= 2) {
        $stmt = $conn->prepare("
            INSERT INTO combos (level, start_task_id, end_task_id, message, deposit_required, multiplier, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'Bronze',
            $bronzeTasks[0]['id'],
            $bronzeTasks[1]['id'],
            'Special Bronze Combo Offer! Complete tasks ' . $bronzeTasks[0]['id'] . ' to ' . $bronzeTasks[1]['id'] . ' to unlock amazing rewards.',
            50.00,
            2.5,
            'active'
        ]);
        echo "   ✅ Sample Bronze combo created (tasks {$bronzeTasks[0]['id']} to {$bronzeTasks[1]['id']})\n";
    }
    
    // Get tasks from Silver level
    $stmt = $conn->prepare("SELECT id, title FROM tasks WHERE level = 'Silver' ORDER BY id LIMIT 3");
    $stmt->execute();
    $silverTasks = $stmt->fetchAll();
    
    if (count($silverTasks) >= 2) {
        $stmt = $conn->prepare("
            INSERT INTO combos (level, start_task_id, end_task_id, message, deposit_required, multiplier, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'Silver',
            $silverTasks[0]['id'],
            $silverTasks[1]['id'],
            'Premium Silver Combo! Complete tasks ' . $silverTasks[0]['id'] . ' to ' . $silverTasks[1]['id'] . ' for exclusive benefits.',
            100.00,
            3.0,
            'active'
        ]);
        echo "   ✅ Sample Silver combo created (tasks {$silverTasks[0]['id']} to {$silverTasks[1]['id']})\n";
    }
    
    // 5. Verify table structure
    echo "\n5. Verifying table structure...\n";
    
    echo "   combos table:\n";
    $stmt = $conn->prepare("DESCRIBE combos");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "     - {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n   Sample combo data:\n";
    $stmt = $conn->prepare("SELECT * FROM combos");
    $stmt->execute();
    $combos = $stmt->fetchAll();
    foreach ($combos as $combo) {
        echo "     - ID: {$combo['id']}, Level: {$combo['level']}, Tasks: {$combo['start_task_id']}-{$combo['end_task_id']}, Status: {$combo['status']}\n";
    }
    
    echo "\n=== NEW COMBO TABLES CREATED ===\n";
    echo "✅ combos table: Created with correct structure\n";
    echo "✅ user_combo_status table: Created for tracking\n";
    echo "✅ Sample data: Inserted for testing\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SCRIPT COMPLETE ===\n";
?>
