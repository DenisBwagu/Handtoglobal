<?php
require_once 'config.php';
require_once 'get_setting.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['error' => 'Please login to complete tasks']);
    exit;
}

// Get POST data
$task_id = $_POST['task_id'] ?? '';
$answer = $_POST['answer'] ?? '';
$level = $_POST['level'] ?? '';

if (empty($task_id) || empty($answer) || empty($level)) {
    echo json_encode(['error' => 'Missing required data']);
    exit;
}
$level = normalizeLevelName($level);

// Get database connection
$conn = getConnection();
$user_id = $_SESSION['user_id'];

try {
    if (!isLevelUnlockedForUser($user_id, $level)) {
        echo json_encode(['error' => 'This level is locked. Please contact support to unlock it.']);
        exit;
    }

    // Check if task was already completed by this user
    $stmt = $conn->prepare("SELECT id FROM completed_tasks WHERE user_id = ? AND task_id = ?");
    $stmt->execute([$user_id, $task_id]);
    if ($stmt->fetch()) {
        echo json_encode(['error' => 'Task already completed']);
        exit;
    }
    
    // Get task details
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? AND active = 1");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        echo json_encode(['error' => 'Task not found']);
        exit;
    }

    $taskLevel = normalizeLevelName($task['level'] ?? $level);
    if ($taskLevel !== $level) {
        echo json_encode(['error' => 'Task does not belong to the selected level']);
        exit;
    }
    
    // Get reward amount (default to level reward or task reward)
    $reward = $task['reward'] ?? 0;
    if (empty($reward)) {
        $level_rewards = [
            'Bronze' => 0.10,
            'Sliver' => 0.20,
            'Gold' => 0.30,
            'VIP 1' => 0.50
        ];
        $reward = $level_rewards[$level] ?? 0.10;
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // Insert completed task
    $stmt = $conn->prepare("
        INSERT INTO completed_tasks (user_id, task_id, level, reward, completed_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $task_id, $level, $reward]);
    
    // Update user balance
    $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->execute([$reward, $user_id]);
    
    // Get new balance
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $new_balance = $stmt->fetch()['balance'];
    
    // Update total tasks count
    $stmt = $conn->prepare("UPDATE users SET total_tasks = total_tasks + 1 WHERE id = ?");
    $stmt->execute([$user_id]);
    
    $conn->commit();
    
    // Get task title for activity message
    $stmt = $conn->prepare("SELECT title FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task_title = $stmt->fetch()['title'] ?? 'Task completed';

    // Get updated statistics
    $stats = [];
    
    // Get current level based on completion
    $levels = getAppLevelNames();
    $current_level = $level;
    
    foreach ($levels as $lvl) {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tasks WHERE level = ? AND active = 1");
        $stmt->execute([$lvl]);
        $total_tasks = $stmt->fetch()['total'];
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) as completed FROM completed_tasks ct
            JOIN tasks t ON ct.task_id = t.id
            WHERE ct.user_id = ? AND t.level = ?
        ");
        $stmt->execute([$user_id, $lvl]);
        $completed_tasks = $stmt->fetch()['completed'];
        
        $stats[$lvl] = [
            'total' => $total_tasks,
            'completed' => $completed_tasks,
            'available' => max(0, $total_tasks - $completed_tasks),
            'progress' => $total_tasks > 0 ? ($completed_tasks / $total_tasks) * 100 : 0
        ];
        
        // Determine current level
        if ($completed_tasks < $total_tasks && $current_level === $lvl) {
            $current_level = $lvl;
        }
    }
    
    // Get today's completed tasks
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM completed_tasks WHERE user_id = ? AND DATE(completed_at) = ?");
    $stmt->execute([$user_id, $today]);
    $today_completed = $stmt->fetch()['count'];
    
    // Get daily task limit
    $daily_limit = (int)get_setting('daily_task_limit', '40');
    
    echo json_encode([
        'success' => true,
        'message' => 'Task completed successfully!',
        'task_title' => $task_title,
        'reward' => number_format($reward, 2),
        'balance' => number_format($new_balance, 2),
        'current_level' => $current_level,
        'current_level_completed' => $stats[$current_level]['completed'],
        'current_level_total' => $stats[$current_level]['total'],
        'available_tasks' => $stats[$current_level]['available'],
        'completed_tasks' => $stats[$current_level]['completed'],
        'today_completed' => $today_completed,
        'daily_limit' => $daily_limit,
        'progress_percent' => round($stats[$current_level]['progress'], 1),
        'all_levels' => $stats,
        'live_activity_message' => "You submitted {$task_title}. Reward added: \${reward}. Current level progress: {$stats[$current_level]['completed']}/{$stats[$current_level]['total']}"
    ]);
    
} catch(PDOException $e) {
    $conn->rollBack();
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
