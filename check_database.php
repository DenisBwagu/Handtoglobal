<?php
/**
 * Check Database Status
 * This script checks what tables exist and handles tablespace issues
 */

require_once __DIR__ . '/config.php';

try {
    $conn = getConnection();
    
    // Show all tables
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Current tables in database:\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    
    // Check if settings table exists
    if (in_array('settings', $tables)) {
        echo "\nℹ️ Settings table already exists. Checking structure...\n";
        
        // Show settings table structure
        $stmt = $conn->query("DESCRIBE settings");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Settings table columns:\n";
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']})\n";
        }
        
        // Show current settings
        $stmt = $conn->query("SELECT * FROM settings");
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nCurrent settings:\n";
        foreach ($settings as $setting) {
            echo "  - {$setting['setting_key']}: {$setting['setting_value']}\n";
        }
    } else {
        echo "\nℹ️ Settings table does not exist. Creating it...\n";
        
        // Try creating with minimal structure first
        $conn->exec("
            CREATE TABLE settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        echo "✅ Basic settings table created\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
