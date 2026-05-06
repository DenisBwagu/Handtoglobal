<?php
require_once __DIR__ . '/config.php';

echo "=== FIXING USER_LEVELS TABLE ===\n\n";

$conn = getConnection();

// Drop the existing table to recreate with correct structure
echo "1. Dropping existing user_levels table...\n";
try {
    $conn->exec("DROP TABLE IF EXISTS user_levels");
    echo "   Table dropped successfully\n";
} catch (PDOException $e) {
    echo "   Error dropping table: " . $e->getMessage() . "\n";
}

// Create table with correct structure
echo "\n2. Creating user_levels table with correct structure...\n";
try {
    $sql = "
        CREATE TABLE user_levels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            level VARCHAR(50) NOT NULL,
            is_unlocked TINYINT(1) DEFAULT 0,
            completed_count INT DEFAULT 0,
            flushed_at TIMESTAMP NULL,
            unlocked_at TIMESTAMP NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_level (user_id, level),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $conn->exec($sql);
    echo "   Table created successfully\n";
} catch (PDOException $e) {
    echo "   Error creating table: " . $e->getMessage() . "\n";
}

// Verify table structure
echo "\n3. Verifying table structure...\n";
try {
    $stmt = $conn->query("DESCRIBE user_levels");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Columns in user_levels table:\n";
    foreach ($columns as $column) {
        echo "   - " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
} catch (PDOException $e) {
    echo "   Error verifying table: " . $e->getMessage() . "\n";
}

echo "\n=== FIX COMPLETE ===\n";
?>
