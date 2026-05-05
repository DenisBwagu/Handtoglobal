<?php
session_start();
require_once 'config.php';
require_once '../includes/settings_helpers.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Get action and parameters
$action = $_POST['action'] ?? '';
$userId = $_POST['user_id'] ?? '';
$level = $_POST['level'] ?? '';
$level = normalizeLevelName($level);

if (empty($action) || empty($userId) || empty($level)) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

// Validate level
$valid_levels = getAppLevelNames();
if (!in_array($level, $valid_levels)) {
    echo json_encode(['success' => false, 'error' => 'Invalid level']);
    exit;
}

try {
    switch ($action) {
        case 'unlock':
            $result = unlockLevelForUser($userId, $level);
            if ($result) {
                echo json_encode(['success' => true, 'message' => "Level {$level} unlocked for user"]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to unlock level']);
            }
            break;
            
        case 'flush':
            $result = flushLevelForUser($userId, $level);
            if ($result) {
                echo json_encode(['success' => true, 'message' => "Level {$level} flushed for user"]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to flush level']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>
