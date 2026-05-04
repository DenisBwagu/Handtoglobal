<?php
// Start session and get config
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

// Completely destroy and restart session
session_destroy();
session_start();
session_regenerate_id(true);

// Set fresh user session - NO admin data
$_SESSION['user_id'] = $user['id'];
$_SESSION['user'] = $user;
$_SESSION['role'] = 'user';
$_SESSION['is_impersonating'] = true;

// Save original admin ID if it existed
if ($originalAdminId) {
    $_SESSION['original_admin_id'] = $originalAdminId;
}

// Debug: Let's see what we're setting
error_log("DEBUG: Login As User - Setting session for user_id: " . $user['id'] . ", role: user, impersonating: true");

// Set a flag to bypass login check
$_SESSION['bypass_login'] = true;

// Redirect to user dashboard
header("Location: ../dashboard.php");
exit;
?>
