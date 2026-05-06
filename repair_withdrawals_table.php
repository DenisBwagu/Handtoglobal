<?php
/**
 * Repair withdrawals table structure
 */

require_once __DIR__ . '/config.php';

echo "=== REPAIRING WITHDRAWALS TABLE ===\n\n";

try {
    $conn = getConnection();
    echo "✅ Database connected successfully\n";
    
    // Check if table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'withdrawals'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✅ withdrawals table exists\n";
        
        // Get current structure
        $stmt = $conn->prepare("DESCRIBE withdrawals");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Current columns:\n";
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']})\n";
        }
        
        // Check for required columns
        $requiredColumns = [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'user_id' => 'INT NOT NULL',
            'amount' => 'DECIMAL(12,2) NOT NULL',
            'coin_asset' => "VARCHAR(50) DEFAULT 'USDT'",
            'network' => "VARCHAR(100) DEFAULT 'Tron (TRC20)'",
            'wallet_address' => 'VARCHAR(255) NOT NULL',
            'memo_tag' => 'VARCHAR(255) NULL',
            'recipient_name' => 'VARCHAR(255) NULL',
            'status' => "ENUM('Pending','Approved','Rejected') DEFAULT 'Pending'",
            'note' => 'TEXT NULL',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP NULL DEFAULT NULL'
        ];
        
        $existingColumns = array_column($columns, 'Field');
        
        // Add missing columns
        foreach ($requiredColumns as $column => $definition) {
            if (!in_array($column, $existingColumns)) {
                echo "Adding column: $column\n";
                $sql = "ALTER TABLE withdrawals ADD COLUMN $column $definition";
                $conn->exec($sql);
                echo "  ✅ Added $column\n";
            } else {
                echo "  ✅ $column already exists\n";
            }
        }
        
        // Update existing data if needed
        echo "\nUpdating existing data...\n";
        
        // Convert asset to coin_asset if needed
        if (in_array('asset', $existingColumns) && !in_array('coin_asset', $existingColumns)) {
            $conn->exec("ALTER TABLE withdrawals ADD COLUMN coin_asset VARCHAR(50) DEFAULT 'USDT'");
            $conn->exec("UPDATE withdrawals SET coin_asset = asset WHERE coin_asset IS NULL OR coin_asset = ''");
            echo "  ✅ Converted asset to coin_asset\n";
        }
        
        // Convert admin_note to note if needed
        if (in_array('admin_note', $existingColumns) && !in_array('note', $existingColumns)) {
            $conn->exec("ALTER TABLE withdrawals ADD COLUMN note TEXT NULL");
            $conn->exec("UPDATE withdrawals SET note = admin_note WHERE note IS NULL");
            echo "  ✅ Converted admin_note to note\n";
        }
        
    } else {
        echo "❌ withdrawals table does not exist, creating...\n";
        
        $sql = "CREATE TABLE withdrawals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            coin_asset VARCHAR(50) DEFAULT 'USDT',
            network VARCHAR(100) DEFAULT 'Tron (TRC20)',
            wallet_address VARCHAR(255) NOT NULL,
            memo_tag VARCHAR(255) NULL,
            recipient_name VARCHAR(255) NULL,
            status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
            note TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL
        )";
        
        $conn->exec($sql);
        echo "✅ withdrawals table created\n";
    }
    
    // Verify final structure
    echo "\nFinal table structure:\n";
    $stmt = $conn->prepare("DESCRIBE withdrawals");
    $stmt->execute();
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($finalColumns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n=== WITHDRAWALS TABLE REPAIR COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SCRIPT COMPLETE ===\n";
?>
