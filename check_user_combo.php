<?php
/**
 * Check User Combo Status
 * This script checks if a user has reached an active combo
 */

require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$taskId = (int)($_GET['task_id'] ?? 0);

if ($taskId <= 0) {
    echo json_encode(['error' => 'Task ID required']);
    exit;
}

try {
    $conn = getConnection();
    
    // Get user's current level
    $stmt = $conn->prepare("SELECT level FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $userLevel = $user['level'];
    
    // Check if user has reached an active combo for this task
    // Check if task number is between start_task and end_task for any active combo
    $stmt = $conn->prepare("
        SELECT c.*
        FROM combos c
        LEFT JOIN user_combo_status ucs 
            ON ucs.combo_id = c.id 
            AND ucs.user_id = ?
        WHERE c.level = ?
            AND c.status = 'active'
            AND c.start_task <= ?
            AND c.end_task >= ?
            AND (c.user_id = ? OR c.user_id IS NULL)
            AND (ucs.status IS NULL OR ucs.status = 'pending')
        LIMIT 1
    ");
    $stmt->execute([$userId, $userLevel, $taskId, $taskId, $userId]);
    $combo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($combo) {
        // Insert pending status for this user if not already exists
        $stmt = $conn->prepare("
            INSERT IGNORE INTO user_combo_status (user_id, combo_id, status)
            VALUES (?, ?, 'pending')
        ");
        $stmt->execute([$userId, $combo['id']]);
        
        echo json_encode([
            'combo_found' => true,
            'combo' => [
                'id' => $combo['id'],
                'level' => $combo['level'],
                'start_task' => $combo['start_task'],
                'end_task' => $combo['end_task'],
                'message' => $combo['message'],
                'amount' => $combo['amount']
            ]
        ]);
    } else {
        echo json_encode(['combo_found' => false]);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
