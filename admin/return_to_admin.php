<?php
session_start();

// Check if impersonating
if (empty($_SESSION['is_impersonating']) || empty($_SESSION['original_admin_id'])) {
    header("Location: ../login.php");
    exit;
}

// Restore admin session
$adminId = $_SESSION['original_admin_id'];

session_unset();

$_SESSION['admin_id'] = $adminId;

// Redirect back to admin dashboard
header("Location: /handtoglobal/admin/dashboard.php");
exit;
?>
