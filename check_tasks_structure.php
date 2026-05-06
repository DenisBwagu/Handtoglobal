<?php
require_once __DIR__ . '/config.php';

$conn = getConnection();

echo "=== Tasks Table Structure ===\n\n";

try {
    // Get table structure
    $stmt = $conn->prepare("DESCRIBE tasks");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "Current columns in tasks table:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']}) - Null: {$column['Null']} - Default: " . ($column['Default'] ?: 'NULL') . "\n";
    }
    
    echo "\n=== Sample Data ===\n";
    $stmt = $conn->prepare("SELECT * FROM tasks LIMIT 3");
    $stmt->execute();
    $sample_tasks = $stmt->fetchAll();
    
    foreach ($sample_tasks as $task) {
        echo "\nTask ID: {$task['id']}\n";
        foreach ($task as $key => $value) {
            echo "  $key: " . ($value ?: 'NULL') . "\n";
        }
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Check if uploads/tasks directory exists ===\n";
$uploads_dir = __DIR__ . '/uploads/tasks';
if (is_dir($uploads_dir)) {
    echo "Directory exists: $uploads_dir\n";
    if (is_writable($uploads_dir)) {
        echo "Directory is writable\n";
    } else {
        echo "Directory is NOT writable\n";
    }
} else {
    echo "Directory does NOT exist\n";
    echo "Creating directory...\n";
    if (mkdir($uploads_dir, 0755, true)) {
        echo "Directory created successfully\n";
    } else {
        echo "Failed to create directory\n";
    }
}

echo "\n=== Structure Check Complete ===\n";
?>
