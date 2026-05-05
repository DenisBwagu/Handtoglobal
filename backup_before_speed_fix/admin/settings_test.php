<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();

// Create settings table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(255) NOT NULL UNIQUE,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    echo "Settings table created/verified successfully!";
    
} catch(PDOException $e) {
    echo "Error creating settings table: " . $e->getMessage();
}

// Test a simple query
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM settings");
    $stmt->execute();
    $count = $stmt->fetch()['count'];
    echo "<br>Current settings count: " . $count;
    
} catch(PDOException $e) {
    echo "<br>Error querying settings: " . $e->getMessage();
}
?>
