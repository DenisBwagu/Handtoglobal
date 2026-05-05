<?php
require_once '../config.php';

$userId = $_GET['user_id'] ?? null;

if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

if (!$userId || !is_numeric($userId)) {
    redirect('users.php');
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

// Save admin session for later restore.
$originalAdmin = [
    'id' => $_SESSION['admin_id'] ?? null,
    'name' => $_SESSION['admin_name'] ?? 'Admin',
    'email' => $_SESSION['admin_email'] ?? '',
];

session_regenerate_id(true);

$_SESSION['admin_temp_id'] = $originalAdmin['id'];
$_SESSION['admin_temp_name'] = $originalAdmin['name'];
$_SESSION['admin_temp_email'] = $originalAdmin['email'];
$_SESSION['original_admin_id'] = $originalAdmin['id'];

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['fullname'] ?? $user['name'] ?? 'User';
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_fullname'] = $user['fullname'] ?? $user['name'] ?? 'User';
$_SESSION['role'] = 'user';
$_SESSION['is_impersonating'] = true;

// Redirect to user dashboard
header("Location: ../dashboard.php");
exit;
