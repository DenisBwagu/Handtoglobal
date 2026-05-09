<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Settings Save Diagnostic</h2>";

try {
    require_once __DIR__ . '/config.php';

    if (!isset($pdo) || !$pdo) {
        if (function_exists('getConnection')) {
            $pdo = getConnection();
        } else {
            throw new Exception("No PDO connection found and getConnection() does not exist.");
        }
    }

    echo "<p>? config.php loaded</p>";

    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "<p><b>Active Database:</b> " . htmlspecialchars($dbName ?: 'NONE') . "</p>";

    if (!$dbName) {
        throw new Exception("No active database selected.");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            setting_type VARCHAR(50) DEFAULT 'text',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    echo "<p>? settings table exists or was created</p>";

    $sql = "INSERT INTO settings (setting_key, setting_value, setting_type)
            VALUES (:setting_key, :setting_value, :setting_type)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_type = VALUES(setting_type),
                updated_at = CURRENT_TIMESTAMP";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':setting_key' => 'site_name',
        ':setting_value' => 'TEST SETTINGS WORKING',
        ':setting_type' => 'text'
    ]);

    echo "<p>? test setting saved</p>";

    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute(['site_name']);
    $value = $stmt->fetchColumn();

    echo "<p><b>Read Back Value:</b> " . htmlspecialchars($value ?: 'EMPTY') . "</p>";

    if ($value === 'TEST SETTINGS WORKING') {
        echo "<h1 style='color:green;'>SUCCESS: SETTINGS DATABASE SAVING WORKS</h1>";
    } else {
        echo "<h1 style='color:red;'>FAILED: VALUE DID NOT SAVE CORRECTLY</h1>";
    }

} catch (Throwable $e) {
    echo "<h1 style='color:red;'>ERROR</h1>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
