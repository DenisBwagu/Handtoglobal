<?php
require_once '../config.php';

// Check if impersonating
if (empty($_SESSION['is_impersonating']) || empty($_SESSION['admin_temp_id'])) {
    redirect('../login.php');
}

// Restore admin session
$adminId = $_SESSION['admin_temp_id'];
$adminName = $_SESSION['admin_temp_name'] ?? 'Admin';
$adminEmail = $_SESSION['admin_temp_email'] ?? '';
session_unset();

$_SESSION['admin_id'] = $adminId;
$_SESSION['admin_name'] = $adminName;
$_SESSION['admin_email'] = $adminEmail;
$_SESSION['admin'] = $adminId;
$_SESSION['role'] = 'admin';

// Redirect back to admin dashboard
redirect('/handtoglobal/admin/dashboard.php');
