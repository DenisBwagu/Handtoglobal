<?php
require_once __DIR__ . '/config.php';

echo "=== CHECKING SAMPLE ACCOUNTS ===\n";

try {
    $conn = getConnection();
    
    // Check admin account
    $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->execute(['admin@handtoglobal.com']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "Admin account found: admin@handtoglobal.com\n";
        if (password_verify('Admin12345', $admin['password'])) {
            echo "Admin password is correct\n";
        } else {
            echo "Admin password is INCORRECT\n";
            // Update admin password
            $hashed_password = password_hash('Admin12345', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE email = ?");
            $stmt->execute([$hashed_password, 'admin@handtoglobal.com']);
            echo "Admin password updated to: Admin12345\n";
        }
    } else {
        echo "Admin account NOT found\n";
        // Create admin account
        $hashed_password = password_hash('Admin12345', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admins (email, password, name, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute(['admin@handtoglobal.com', $hashed_password, 'Admin', 'super_admin']);
        echo "Admin account created: admin@handtoglobal.com / Admin12345\n";
    }
    
    echo "\n";
    
    // Check user account
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute(['user@handtoglobal.com']);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "User account found: user@handtoglobal.com\n";
        if (password_verify('User12345', $user['password'])) {
            echo "User password is correct\n";
        } else {
            echo "User password is INCORRECT\n";
            // Update user password
            $hashed_password = password_hash('User12345', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashed_password, 'user@handtoglobal.com']);
            echo "User password updated to: User12345\n";
        }
    } else {
        echo "User account NOT found\n";
        // Create user account
        $hashed_password = password_hash('User12345', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, balance, level, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute(['Test User', 'user@handtoglobal.com', $hashed_password, 20.00, 'Bronze', 1]);
        echo "User account created: user@handtoglobal.com / User12345\n";
    }
    
    echo "\n=== END CHECK ===\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
