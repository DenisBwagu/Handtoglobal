<?php
session_start();
require_once 'config.php';
require_once 'get_setting.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unlocked' => false, 'error' => 'Not logged in']);
    exit;
}

// Get level from request
$level = $_GET['level'] ?? '';
if (empty($level)) {
    echo json_encode(['unlocked' => false, 'error' => 'Level not specified']);
    exit;
}
$level = normalizeLevelName($level);

// Validate level
$valid_levels = getAppLevelNames();
if (!in_array($level, $valid_levels)) {
    echo json_encode(['unlocked' => false, 'error' => 'Invalid level']);
    exit;
}

// Check unlock status
$userId = $_SESSION['user_id'];
$isUnlocked = isLevelUnlockedForUser($userId, $level);

echo json_encode(['unlocked' => $isUnlocked]);
?>
