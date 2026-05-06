<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/settings_helpers.php';
require_once __DIR__ . '/includes/language_helpers.php';
require_once __DIR__ . '/includes/task_flow_helpers.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => __t('please_login_submit_tasks', 'Please login to submit tasks')]);
    exit;
}

$taskId = (int)($_POST['task_id'] ?? 0);
$answer = trim((string)($_POST['answer'] ?? ''));
$level = normalizeLevelName($_POST['level'] ?? '');

if ($taskId <= 0 || $answer === '' || $level === '') {
    echo json_encode(['error' => __t('missing_required_task_data', 'Missing required task data')]);
    exit;
}

$conn = getConnection();
$userId = (int)$_SESSION['user_id'];

try {
    if (!isLevelUnlockedForUser($userId, $level)) {
        echo json_encode(['error' => __t('level_locked_contact_support', 'Level is locked. Please contact support to unlock this level.')]);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? AND active = 1 LIMIT 1");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        echo json_encode(['error' => __t('task_not_found', 'Task not found')]);
        exit;
    }

    $taskLevel = normalizeLevelName($task['level'] ?? $level);
    if (!in_array($taskLevel, htg_level_aliases($level), true)) {
        echo json_encode(['error' => __t('task_not_belong_level', 'Task does not belong to the selected level')]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM completed_tasks WHERE user_id = ? AND task_id = ? LIMIT 1");
    $stmt->execute([$userId, $taskId]);
    if ($stmt->fetch()) {
        echo json_encode(['error' => __t('task_already_completed', 'Task already completed')]);
        exit;
    }

    $statsBefore = htg_level_stats($conn, $userId, $level);
    if ($statsBefore['completed'] >= 40 || $statsBefore['completed'] >= $statsBefore['total']) {
        echo json_encode([
            'success' => true,
            'level_completed' => true,
            'message' => __t('all_tasks_completed_level', 'All tasks completed for this level!'),
            'dashboard_stats' => htg_dashboard_stats($conn, $userId, $level),
        ]);
        exit;
    }

    $taskNumber = $statsBefore['completed'] + 1;
    $combo = htg_pending_combo_for_task_number($conn, $userId, $level, $taskNumber);
    if ($combo) {
        echo json_encode([
            'success' => true,
            'combo_required' => true,
            'combo' => $combo,
            'next_task' => null,
            'level_completed' => false,
            'dashboard_stats' => htg_dashboard_stats($conn, $userId, $level),
        ]);
        exit;
    }

    $reward = (float)($task['reward'] ?? 0);

$comboMultiplier = 1;

// 🔥 CHECK ACTIVE OR JUST RELEASED COMBO
$stmt = $conn->prepare("
    SELECT c.*, ucs.status as user_status
    FROM combos c
    LEFT JOIN user_combo_status ucs ON ucs.combo_id = c.id AND ucs.user_id = ?
    WHERE c.level = ?
    AND c.start_task = ?
    AND (c.user_id IS NULL OR c.user_id = ?)
    ORDER BY c.id DESC
    LIMIT 1
");

$stmt->execute([$userId, $level, $taskNumber, $userId]);
$combo = $stmt->fetch(PDO::FETCH_ASSOC);

if ($combo) {
    $multiplier = (float)($combo['multiplier'] ?? 1);

    if ($multiplier > 1) {
        $reward = $reward * $multiplier;
        $comboMultiplier = $multiplier;
    }
}
    if ($reward <= 0) {
        $rewardByLevel = [
            'Bronze' => 1.80,
            'Sliver' => 2.50,
            'Silver' => 2.50,
            'Gold' => 3.50,
            'VIP 1' => 5.00,
            'Platinum' => 5.00,
        ];
        $reward = $rewardByLevel[$level] ?? 1.80;
    }
    $baseReward = $reward;

    $conn->beginTransaction();

    $stmt = $conn->prepare("
        INSERT INTO completed_tasks (user_id, task_id, answer, level, reward, completed_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $taskId, $answer, $level, $reward]);

    $stmt = $conn->prepare("
        UPDATE users
        SET balance = balance + ?,
            total_tasks = COALESCE(total_tasks, 0) + 1,
            accuracy = LEAST(100, COALESCE(accuracy, 0) + 1),
            rating = LEAST(5, (LEAST(100, COALESCE(accuracy, 0) + 1) / 20))
        WHERE id = ?
    ");
    $stmt->execute([$reward, $userId]);

    $stmt = $conn->prepare("SELECT balance, rating FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $newBalance = (float)($user['balance'] ?? 0);
    $_SESSION['user_rating'] = (float)($user['rating'] ?? 0);

    $conn->commit();

    $statsAfter = htg_level_stats($conn, $userId, $level);
    $nextTaskNumber = $statsAfter['completed'] + 1;
    $dashboardStats = htg_dashboard_stats($conn, $userId, $level, $newBalance);

    if ($statsAfter['completed'] >= 40 || $statsAfter['completed'] >= $statsAfter['total']) {
        echo json_encode([
            'success' => true,
            'balance' => $newBalance,
            'completed_tasks' => $statsAfter['completed'],
            'progress' => $statsAfter['progress'],
            'current_level' => $level,
            'level_completed' => true,
            'current_level_completed' => $statsAfter['completed'],
            'current_level_total' => $statsAfter['total'],
            'dashboard_stats' => $dashboardStats,
        ]);
        exit;
    }

    $nextTask = htg_next_task($conn, $userId, $level);

    echo json_encode([
        'success' => true,
        'balance' => $newBalance,
        'completed_tasks' => $statsAfter['completed'],
        'available_tasks' => $statsAfter['available'],
        'progress' => $statsAfter['progress'],
        'current_level' => $level,
        'combo_required' => false,
        'combo' => null,
        'combo_applied' => false,
        'base_reward' => $baseReward,
        'reward' => $reward,
        'combo_multiplier' => $comboMultiplier,
        'level_completed' => !$nextTask,
        'next_task' => $nextTask,
        'dashboard_stats' => $dashboardStats,
        'all_tasks' => htg_all_task_progress($conn, $userId, $level),
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('task_action failed: ' . $e->getMessage());
    echo json_encode(['error' => __t('failed_submit_task_try_again', 'Failed to submit task. Please try again.')]);
}
