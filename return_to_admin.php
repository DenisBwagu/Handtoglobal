<?php
require_once 'config.php';

// Check if admin is impersonating a user
if (!isset($_SESSION['admin_temp_id'])) {
    redirect('dashboard.php');
    exit;
}

// Restore admin session
$_SESSION['admin_id'] = $_SESSION['admin_temp_id'];
$_SESSION['admin_email'] = $_SESSION['admin_temp_email'];
$_SESSION['admin_name'] = $_SESSION['admin_temp_name'] ?? 'Admin';

// Clear temporary admin session data
unset($_SESSION['admin_temp_id']);
unset($_SESSION['admin_temp_email']);
unset($_SESSION['admin_temp_name']);

// Clear user session
unset($_SESSION['user_id']);

// Redirect back to admin user view page
// Try to get the last user ID that was being viewed
if (isset($_SESSION['last_viewed_user_id'])) {
    redirect('admin/user_view.php?id=' . $_SESSION['last_viewed_user_id']);
} else {
    redirect('admin/users.php');
}
?>
