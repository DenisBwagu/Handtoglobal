<?php
require_once '../config.php';

if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

$userId = $_GET['user_id'] ?? null;
if (!$userId || !is_numeric($userId)) {
    redirect('users.php');
}

redirect('login_as_user.php?user_id=' . (int)$userId);
