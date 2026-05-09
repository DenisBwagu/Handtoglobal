<?php
// Test different MySQL configurations
echo "<h2>Testing MySQL Connection</h2>";

// Test 1: Port 3306 (default)
echo "<h3>Test 1: localhost:3306</h3>";
try {
    $dsn = "mysql:host=localhost;port=3306;charset=utf8mb4";
    $conn = new PDO($dsn, 'root', '');
    echo "✅ Connected successfully to localhost:3306<br>";
    $conn = null;
} catch(PDOException $e) {
    echo "❌ Failed: " . $e->getMessage() . "<br>";
}

// Test 2: Port 3307
echo "<h3>Test 2: localhost:3307</h3>";
try {
    $dsn = "mysql:host=localhost;port=3307;charset=utf8mb4";
    $conn = new PDO($dsn, 'root', '');
    echo "✅ Connected successfully to localhost:3307<br>";
    $conn = null;
} catch(PDOException $e) {
    echo "❌ Failed: " . $e->getMessage() . "<br>";
}

// Test 3: 127.0.0.1:3306
echo "<h3>Test 3: 127.0.0.1:3306</h3>";
try {
    $dsn = "mysql:host=127.0.0.1;port=3306;charset=utf8mb4";
    $conn = new PDO($dsn, 'root', '');
    echo "✅ Connected successfully to 127.0.0.1:3306<br>";
    $conn = null;
} catch(PDOException $e) {
    echo "❌ Failed: " . $e->getMessage() . "<br>";
}

// Test 4: 127.0.0.1:3307
echo "<h3>Test 4: 127.0.0.1:3307</h3>";
try {
    $dsn = "mysql:host=127.0.0.1;port=3307;charset=utf8mb4";
    $conn = new PDO($dsn, 'root', '');
    echo "✅ Connected successfully to 127.0.0.1:3307<br>";
    $conn = null;
} catch(PDOException $e) {
    echo "❌ Failed: " . $e->getMessage() . "<br>";
}

// Test 5: Try with password
echo "<h3>Test 5: localhost:3307 with password 'root'</h3>";
try {
    $dsn = "mysql:host=localhost;port=3307;charset=utf8mb4";
    $conn = new PDO($dsn, 'root', 'root');
    echo "✅ Connected successfully to localhost:3307 with password<br>";
    $conn = null;
} catch(PDOException $e) {
    echo "❌ Failed: " . $e->getMessage() . "<br>";
}

echo "<h3>Recommendation:</h3>";
echo "Based on the tests above, update config.php with the working connection details.";
?>
