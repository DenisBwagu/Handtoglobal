<?php
echo "<h1>Server Test</h1>";
echo "<p>PHP is working!</p>";
echo "<p>Current directory: " . __DIR__ . "</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";

// Test MySQL connection
try {
    $dsn = "mysql:host=localhost;port=3307;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<p style='color: green;'>✓ MySQL connection successful on port 3307!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ MySQL connection failed: " . $e->getMessage() . "</p>";
}

// Check if db.sql exists
if (file_exists(__DIR__ . '/db.sql')) {
    echo "<p style='color: green;'>✓ db.sql file found</p>";
} else {
    echo "<p style='color: red;'>✗ db.sql file not found</p>";
}

echo "<hr>";
echo "<h3>Setup Options:</h3>";
echo "<p><a href='force_setup.php'>🚀 Run Force Setup</a></p>";
echo "<p><a href='setup.php'>📋 Run Regular Setup</a></p>";
echo "<p><a href='clean_setup.php'>🧹 Run Clean Setup</a></p>";
echo "<p><a href='quick_setup.php'>⚡ Run Quick Setup</a></p>";
?>
