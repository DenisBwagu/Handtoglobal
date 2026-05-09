<?php
require_once __DIR__ . '/config.php';

echo "=== FIXING ADMIN PASSWORD ===\n";

try {
    $conn = getConnection();
    
    // Update admin password
    $hashed_password = password_hash('Admin12345', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE email = ?");
    $stmt->execute([$hashed_password, 'admin@handtoglobal.com']);
    
    echo "Admin password updated successfully\n";
    echo "Email: admin@handtoglobal.com\n";
    echo "Password: Admin12345\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== DONE ===\n";
?>
