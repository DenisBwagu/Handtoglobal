<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/settings_helpers.php';
require_once __DIR__ . '/includes/language_helpers.php';
require_once __DIR__ . '/includes/task_flow_helpers.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => __t('please_login_access_tasks', 'Please login to access tasks')]);
    exit;
}

$level = normalizeLevelName($_GET['level'] ?? '');
if ($level === '') {
    echo json_encode(['error' => __t('level_not_specified', 'Level not specified')]);
    exit;
}

$conn = getConnection();
$userId = (int)$_SESSION['user_id'];

try {
    if (!isLevelUnlockedForUser($userId, $level)) {
        echo json_encode([
            'locked' => true,
            'error' => __t('level_locked_contact_support', 'Level is locked. Please contact support to unlock this level.')
        ]);
        exit;
    }

    $stats = htg_level_stats($conn, $userId, $level);
    if ($stats['total'] === 0) {
        echo json_encode([
            'completed' => true,
            'message' => __t('no_active_tasks_level', 'No active tasks are available for this level yet.'),
            'progress' => "0/40",
        ]);
        exit;
    }

    if ($stats['completed'] >= $stats['total']) {
        echo json_encode([
            'completed' => true,
            'message' => __t('all_tasks_completed_level', 'All tasks completed for this level!'),
            'progress' => $stats['completed'] . '/' . $stats['total'],
        ]);
        exit;
    }

    $nextTaskNumber = $stats['completed'] + 1;
    $combo = htg_pending_combo_for_task_number($conn, $userId, $level, $nextTaskNumber);
    if ($combo) {
        echo json_encode([
            'success' => true,
            'combo_required' => true,
            'combo' => $combo,
            'task' => null,
            'progress' => $stats['completed'] . '/' . $stats['total'],
            'dashboard_stats' => htg_dashboard_stats($conn, $userId, $level),
        ]);
        exit;
    }

    $currentTask = htg_next_task($conn, $userId, $level);
    if (!$currentTask) {
        echo json_encode([
            'completed' => true,
            'message' => __t('all_tasks_completed_level', 'All tasks completed for this level!'),
            'progress' => $stats['completed'] . '/' . $stats['total'],
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'task' => $currentTask,
        'all_tasks' => htg_all_task_progress($conn, $userId, $level),
        'progress' => $stats['completed'] . '/' . $stats['total'],
        'dashboard_stats' => htg_dashboard_stats($conn, $userId, $level),
    ]);
} catch (Throwable $e) {
    error_log('load_tasks failed: ' . $e->getMessage());
    echo json_encode(['error' => __t('failed_load_tasks_try_again', 'Failed to load tasks. Please try again.')]);
}
