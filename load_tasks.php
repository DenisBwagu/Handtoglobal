<?php
require_once 'config.php';
require_once '../includes/settings_helpers.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['error' => 'Please login to access tasks']);
    exit;
}

// Get level from request
$level = $_GET['level'] ?? '';
if (empty($level)) {
    echo json_encode(['error' => 'Level not specified']);
    exit;
}
$level = normalizeLevelName($level);

// Get database connection
$conn = getConnection();
$user_id = $_SESSION['user_id'];

try {
    if (!isLevelUnlockedForUser($user_id, $level)) {
        echo json_encode(['locked' => true, 'error' => 'Level is locked. Please contact support to unlock this level.']);
        exit;
    }

    // Get available tasks for this level that user hasn't completed
    $stmt = $conn->prepare("
        SELECT t.*, t.image as task_image FROM tasks t 
        LEFT JOIN completed_tasks ct ON t.id = ct.task_id AND ct.user_id = ?
        WHERE t.level = ? AND t.active = 1 
        AND ct.id IS NULL
        ORDER BY t.id ASC
        LIMIT 1
    ");
    $stmt->execute([$user_id, $level]);
    $current_task = $stmt->fetch();
    
    if (!$current_task) {
        // Check if all tasks are completed
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total FROM tasks 
            WHERE level = ? AND active = 1
        ");
        $stmt->execute([$level]);
        $total_tasks = $stmt->fetch()['total'];

        if ((int)$total_tasks === 0) {
            echo json_encode(['completed' => true, 'message' => 'No active tasks are available for this level yet.']);
            exit;
        }
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) as completed FROM completed_tasks ct
            JOIN tasks t ON ct.task_id = t.id
            WHERE ct.user_id = ? AND t.level = ?
        ");
        $stmt->execute([$user_id, $level]);
        $completed_tasks = $stmt->fetch()['completed'];
        
        if ($completed_tasks >= $total_tasks) {
            echo json_encode(['completed' => true, 'message' => 'All tasks completed for this level!']);
        } else {
            echo json_encode(['error' => 'No tasks available for this level']);
        }
        exit;
    }
    
    // Get all tasks for progress display
    $stmt = $conn->prepare("
        SELECT t.id, ct.completed_at IS NOT NULL as completed 
        FROM tasks t 
        LEFT JOIN completed_tasks ct ON t.id = ct.task_id AND ct.user_id = ?
        WHERE t.level = ? AND t.active = 1
        ORDER BY t.id ASC
    ");
    $stmt->execute([$user_id, $level]);
    $all_tasks = $stmt->fetchAll();
    
    echo json_encode([
        'task' => $current_task,
        'all_tasks' => $all_tasks,
        'progress' => count(array_filter($all_tasks, fn($t) => $t['completed'])) . '/' . count($all_tasks)
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
