<?php
session_start();
require '../config/database.php';
require '../config/security.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

/* STATISTICS */
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$totalDeposits = $conn->query("SELECT IFNULL(SUM(amount),0) as total FROM deposits WHERE status='Approved'")->fetch_assoc()['total'];
$totalWithdrawals = $conn->query("SELECT IFNULL(SUM(amount),0) as total FROM withdrawals WHERE status='Approved'")->fetch_assoc()['total'];
$pendingDeposits = $conn->query("SELECT COUNT(*) as total FROM deposits WHERE status='Pending'")->fetch_assoc()['total'];
$pendingWithdrawals = $conn->query("SELECT COUNT(*) as total FROM withdrawals WHERE status='Pending'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard - Globalhand</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<style>

body {
    margin:0;
    font-family:Arial, sans-serif;
    background:#f4f9ff;
}

/* Sidebar */
.sidebar {
    width:230px;
    background:#007bff;
    position:fixed;
    height:100%;
    color:white;
    padding-top:20px;
}

.sidebar h2 {
    text-align:center;
    margin-bottom:30px;
}

.sidebar a {
    display:block;
    padding:12px 20px;
    color:white;
    text-decoration:none;
    transition:0.3s;
}

.sidebar a:hover {
    background:#0056b3;
}

/* Main */
.main {
    margin-left:230px;
    padding:20px;
}

/* Top Bar */
.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.card-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px;
}

.card {
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 2px 8px rgba(0,0,0,0.05);
}

.card h3 {
    margin:0;
    font-size:14px;
    color:#888;
}

.card h2 {
    margin-top:10px;
    color:#007bff;
}

/* Activity table */
.table-box {
    background:white;
    margin-top:30px;
    padding:20px;
    border-radius:10px;
    box-shadow:0 2px 8px rgba(0,0,0,0.05);
}

table {
    width:100%;
    border-collapse:collapse;
}

th, td {
    padding:10px;
    border-bottom:1px solid #eee;
    text-align:left;
}

th {
    background:#f4f9ff;
}

@media(max-width:768px){
    .sidebar {
        width:100%;
        height:auto;
        position:relative;
    }
    .main {
        margin-left:0;
    }
}

</style>
</head>
<body>

<div class="sidebar">
    <h2>Globalhand</h2>
    <a href="dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
    <a href="users.php"><i class="fa fa-users"></i> Users</a>
    <a href="deposits.php"><i class="fa fa-wallet"></i> Deposits</a>
    <a href="withdrawals.php"><i class="fa fa-money-bill"></i> Withdrawals</a>
    <a href="codes.php"><i class="fa fa-key"></i> Invitation Codes</a>
    <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main">

<div class="topbar">
    <h1>Admin Dashboard</h1>
    <div>Welcome, Admin</div>
</div>

<div class="card-grid">
    <div class="card">
        <h3>Total Users</h3>
        <h2><?= $totalUsers ?></h2>
    </div>

    <div class="card">
        <h3>Total Deposits</h3>
        <h2>$<?= number_format($totalDeposits,2) ?></h2>
    </div>

    <div class="card">
        <h3>Total Withdrawals</h3>
        <h2>$<?= number_format($totalWithdrawals,2) ?></h2>
    </div>

    <div class="card">
        <h3>Pending Deposits</h3>
        <h2><?= $pendingDeposits ?></h2>
    </div>

    <div class="card">
        <h3>Pending Withdrawals</h3>
        <h2><?= $pendingWithdrawals ?></h2>
    </div>
</div>

<div class="table-box">
    <h3>Quick Overview</h3>
    <p>Manage users, approve deposits, monitor withdrawals and track platform growth efficiently.</p>
</div>

</div>

</body>
</html>