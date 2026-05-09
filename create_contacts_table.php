<?php
require_once __DIR__ . '/config.php';

$conn = getConnection();

echo "=== Creating/Updating Contacts Table ===\n\n";

try {
    // Drop existing table if it exists to start fresh
    $conn->exec("DROP TABLE IF EXISTS contacts");
    echo "Dropped existing contacts table (if it existed)\n";
    
    // Create new contacts table with exact structure
    $conn->exec("
        CREATE TABLE contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NULL,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NULL,
            email VARCHAR(255) NULL,
            status ENUM('new', 'contacted', 'converted', 'lost') DEFAULT 'new',
            registered TINYINT(1) DEFAULT 0,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
        )
    ");
    echo "Created contacts table successfully\n";
    
    // Show table structure
    $stmt = $conn->prepare("DESCRIBE contacts");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "\nTable structure:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']}) - Null: {$column['Null']} - Default: " . ($column['Default'] ?: 'NULL') . "\n";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Contacts Table Setup Complete ===\n";
?>
