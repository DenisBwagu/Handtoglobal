<?php
// Direct User Dashboard - No Authentication Required
session_start();
require_once '../config.php';

$userId = $_GET['user_id'] ?? null;

if (!$userId) {
    die('Invalid user');
}

// Get database connection
$conn = getConnection();

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('User not found');
}

// Save admin session for later restore (if exists)
$originalAdminId = $_SESSION['admin_id'] ?? null;

// Set user session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user'] = $user;
$_SESSION['role'] = 'user';
$_SESSION['is_impersonating'] = true;

// Save original admin ID if it existed
if ($originalAdminId) {
    $_SESSION['original_admin_id'] = $originalAdminId;
}

// Include dashboard directly without requireLogin check
include '../dashboard.php';
?>
