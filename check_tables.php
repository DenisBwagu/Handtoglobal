<?php
require_once __DIR__ . '/config.php';
try {
    $conn = getConnection();
    $stmt = $conn->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo 'Tables found: ' . implode(', ', $tables) . PHP_EOL;
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>
