<?php
require_once __DIR__ . '/config.php';

$conn = getConnection();
$stmt = $conn->prepare("SELECT DISTINCT level FROM tasks WHERE active = 1 ORDER BY level");
$stmt->execute();
$levels = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Database Levels:\n";
foreach ($levels as $level) {
    echo "- " . htmlspecialchars($level) . "\n";
}

echo "\nExisting Combos:\n";
$stmt = $conn->prepare("SELECT DISTINCT level FROM combos ORDER BY level");
$stmt->execute();
$comboLevels = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($comboLevels as $level) {
    echo "- " . htmlspecialchars($level) . "\n";
}
?>
