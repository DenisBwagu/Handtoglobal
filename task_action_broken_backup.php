<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once '../includes/settings_helpers.php';

// Wrap entire logic in try/catch to catch any PHP/SQL errors
try {

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
    
    // Check if user has an active combo for this task and apply multiplier
    // Calculate current task number for this level
    $stmt = $conn->prepare("
        SELECT COUNT(*) as completed_count 
        FROM completed_tasks ct
        JOIN tasks t ON ct.task_id = t.id
        WHERE ct.user_id = ? AND t.level = ?
    ");
    $stmt->execute([$user_id, $level]);
    $completed_count = $stmt->fetch()['completed_count'];
    $current_task_number = $completed_count + 1; // Current task being completed
    
    $stmt = $conn->prepare("
        SELECT multiplier 
        FROM combos 
        WHERE level = ? 
            AND status = 'active' 
            AND start_task <= ? 
            AND end_task >= ?
        LIMIT 1
    ");
    $stmt->execute([$level, $current_task_number, $current_task_number]);
    $combo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($combo && $combo['multiplier'] > 1) {
        $original_reward = $reward;
        $reward = $reward * $combo['multiplier'];
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
    
    // Check for combo after task completion
    $current_task_number = $stats[$current_level]['completed'];
    
    // Check if user has reached an active combo for this task
    $stmt = $conn->prepare("
        SELECT c.*
        FROM combos c
        LEFT JOIN user_combo_status ucs 
            ON ucs.combo_id = c.id 
            AND ucs.user_id = ?
        WHERE c.level = ?
            AND c.status = 'active'
            AND c.is_active = 1
            AND c.start_task <= ?
            AND c.end_task >= ?
            AND (c.user_id = ? OR c.user_id IS NULL)
            AND (ucs.status IS NULL OR ucs.status = 'pending')
        LIMIT 1
    ");
    $stmt->execute([$user_id, $current_level, $current_task_number, $current_task_number, $user_id]);
    $combo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($combo) {
        // Insert pending status for this user if not already exists
        $stmt = $conn->prepare("
            INSERT IGNORE INTO user_combo_status (user_id, combo_id, status)
            VALUES (?, ?, 'pending')
        ");
        $stmt->execute([$user_id, $combo['id']]);
        
        // Get task titles for the combo range
        $stmt = $conn->prepare("
            SELECT title FROM tasks 
            WHERE level = ? AND (
                (id = (SELECT id FROM tasks WHERE level = ? ORDER BY id LIMIT 1 OFFSET ? - 1) AND ? = 1) OR
                (id = (SELECT id FROM tasks WHERE level = ? ORDER BY id LIMIT 1 OFFSET ? - 1) AND ? = ?)
            )
        ");
        $stmt->execute([$current_level, $current_level, $combo['start_task'], $combo['start_task'], $current_level, $combo['end_task'], $combo['start_task'], $combo['end_task']]);
        $taskTitles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $startTaskTitle = $taskTitles[0] ?? 'Task ' . $combo['start_task'];
        $endTaskTitle = $taskTitles[1] ?? ($combo['start_task'] == $combo['end_task'] ? $startTaskTitle : 'Task ' . $combo['end_task']);
        
        // Return combo_required response
        echo json_encode([
            'combo_required' => true,
            'combo' => [
                'id' => $combo['id'],
                'level' => $combo['level'],
                'start_task' => $combo['start_task'],
                'end_task' => $combo['end_task'],
                'amount' => $combo['amount'],
                'message' => $combo['message'],
                'multiplier' => $combo['multiplier'] ?? 1,
                'start_task_title' => $combo['start_task'] . '. ' . $startTaskTitle,
                'end_task_title' => $combo['end_task'] . '. ' . $endTaskTitle,
                'task_count' => $combo['end_task'] - $combo['start_task'] + 1
            ],
            'success' => true,
            'message' => 'Task completed but combo required!',
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
        exit;
    }
    
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
    
    // Get next task for continuous flow
    $next_task = null;
    $level_completed = false;
    
    if ($stats[$current_level]['available'] > 0) {
        // Get next available task
        $stmt = $conn->prepare("
            SELECT t.id, t.title, t.description, t.image, t.level, t.instructions
            FROM tasks t
            WHERE t.level = ? AND t.active = 1
            AND t.id NOT IN (
                SELECT ct.task_id 
                FROM completed_tasks ct 
                WHERE ct.user_id = ?
            )
            ORDER BY t.id
            LIMIT 1
        ");
        $stmt->execute([$current_level, $user_id]);
        $next_task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($next_task) {
            // Calculate task number based on completed tasks + 1
            $task_number = $stats[$current_level]['completed'] + 1;
            $next_task['task_number'] = $task_number;
        }
    } else {
        $level_completed = true;
    }

    // Calculate dashboard stats
    $pending_withdrawals = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM withdrawals WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_withdrawals = $stmt->fetch()['count'];
    
    $performance_score = 0.00;
    if (isset($_SESSION['user_rating'])) {
        $performance_score = (float)$_SESSION['user_rating'];
    } else {
        // Get user rating from database
        $stmt = $conn->prepare("SELECT rating FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        $performance_score = $user_data ? (float)$user_data['rating'] : 0.00;
        $_SESSION['user_rating'] = $performance_score;
    }
    
    $response = [
        'success' => true,
        'balance' => (float)$new_balance,
        'completed_tasks' => $stats[$current_level]['completed'],
        'progress' => round($stats[$current_level]['progress'], 1),
        'current_level' => $current_level,
        'level_completed' => $level_completed,
        'dashboard_stats' => [
            'current_level' => $current_level,
            'level_completed_tasks' => $stats[$current_level]['completed'],
            'level_total_tasks' => $stats[$current_level]['total'],
            'available_tasks' => $stats[$current_level]['available'],
            'completed_tasks' => $stats[$current_level]['completed'],
            'pending_withdrawals' => $pending_withdrawals,
            'performance_score' => $performance_score,
            'balance' => (float)$new_balance,
            'all_levels' => $stats
        ]
    ];
    
    if ($level_completed) {
        $response['message'] = 'Level completed!';
        $response['current_level_completed'] = $stats[$current_level]['completed'];
        $response['current_level_total'] = $stats[$current_level]['total'];
    } else if ($next_task) {
        $response['next_task'] = [
            'id' => (int)$next_task['id'],
            'title' => $next_task['title'] ?? 'Task Title',
            'description' => $next_task['description'] ?? 'Task description',
            'image' => $next_task['image'] ? 'uploads/tasks/' . $next_task['image'] : '',
            'instructions' => $next_task['instructions'] ?? 'YES or NO',
            'level' => $next_task['level'] ?? 'Bronze',
            'task_number' => $next_task['task_number'] ?? 1
        ];
    }
    
    // Debug: Log the response before sending
    error_log("Task action response: " . json_encode($response));
    
    echo json_encode($response);
    
} // Close main try block
catch(PDOException $e) {
    $conn->rollBack();
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch(Throwable $e) {
    // Catch any other PHP/SQL errors
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'error' => 'System error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
