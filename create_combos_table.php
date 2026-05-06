<?php
require_once __DIR__ . '/config.php';

$conn = getConnection();

echo "=== Creating/Updating Combos Table ===\n\n";

try {
    // Drop existing table if it exists to start fresh
    $conn->exec("DROP TABLE IF EXISTS combos");
    echo "Dropped existing combos table (if it existed)\n";
    
    // Create new combos table with exact structure
    $conn->exec("
        CREATE TABLE combos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            level VARCHAR(50) NOT NULL,
            start_task_id INT NOT NULL,
            end_task_id INT NOT NULL,
            multiplier DECIMAL(10,2) DEFAULT 6,
            deposit_amount DECIMAL(10,2) DEFAULT 0,
            message TEXT NULL,
            status ENUM('Pending','Active','Completed','Cancelled') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "Created combos table successfully\n";
    
    // Show table structure
    $stmt = $conn->prepare("DESCRIBE combos");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "\nTable structure:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']}) - Null: {$column['Null']} - Default: " . ($column['Default'] ?: 'NULL') . "\n";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Combos Table Setup Complete ===\n";
?>
