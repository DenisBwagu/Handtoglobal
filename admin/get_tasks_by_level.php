<?php
/**
 * Get Tasks by Level API
 * This script returns tasks for a specific level in JSON format
 */

require_once '../config.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$level = $_GET['level'] ?? null;

if (!$level) {
    echo json_encode(['error' => 'Level parameter required']);
    exit;
}

try {
    $conn = getConnection();
    
    // Get tasks for the specified level
    $stmt = $conn->prepare("
        SELECT id, title
        FROM tasks
        WHERE level = ? AND active = 1
        ORDER BY id ASC
    ");
    $stmt->execute([$level]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format tasks as requested
    $formattedTasks = [];
    foreach ($tasks as $task) {
        $formattedTasks[] = [
            'id' => $task['id'],
            'title' => "Task {$task['id']} - {$task['title']}"
        ];
    }
    
    echo json_encode($formattedTasks);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
