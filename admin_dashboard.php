<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['admin'])) { header("Location: login.php"); exit(); }
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard - Globalhand</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />

  <!-- Inter Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root{
      --bg:#f6f7fb;
      --card:#ffffff;
      --text:#1f2937;
      --muted:#6b7280;
      --border:#e5e7eb;
      --shadow:0 14px 40px rgba(16,24,40,.08);
      --primary:#635bff;
      --primary2:#4f46e5;
    }

    *{box-sizing:border-box}

    body{
      margin:0;
      font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
      background:var(--bg);
      color:var(--text);
    }

    .app{
      display:flex;
      min-height:100vh;
    }

    /* Sidebar */
    .sidebar{
      width:240px;
      background:#101828;
      color:#fff;
      padding:20px;
    }

    .brand{
      font-weight:800;
      font-size:18px;
      margin-bottom:25px;
      letter-spacing:-.02em;
    }

    .nav a{
      display:block;
      padding:12px 14px;
      border-radius:14px;
      margin-bottom:10px;
      background:rgba(255,255,255,.05);
      color:#fff;
      text-decoration:none;
      font-weight:600;
      transition:.15s;
    }

    .nav a:hover{
      background:rgba(99,91,255,.35);
      transform:translateY(-2px);
    }

    /* Main Content */
    .content{
      flex:1;
      padding:30px;
    }

    .header{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:25px;
    }

    .title{
      font-size:22px;
      font-weight:800;
      letter-spacing:-.02em;
    }

    /* Cards */
    .cards{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
      gap:20px;
      margin-bottom:30px;
    }

    .card{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:18px;
      padding:20px;
      box-shadow:var(--shadow);
      transition:.15s;
    }

    .card:hover{
      transform:translateY(-4px);
    }

    .card h3{
      margin:0;
      font-size:14px;
      font-weight:600;
      color:var(--muted);
    }

    .card p{
      margin:8px 0 0;
      font-size:22px;
      font-weight:800;
    }

    /* Action Buttons */
    .actions{
      display:grid;
      gap:14px;
      max-width:420px;
    }

    .btn{
      display:block;
      text-align:center;
      padding:14px;
      border-radius:16px;
      text-decoration:none;
      font-weight:700;
      background:var(--primary);
      color:#fff;
      transition:.15s;
      box-shadow:0 8px 20px rgba(99,91,255,.25);
    }

    .btn:hover{
      background:var(--primary2);
      transform:translateY(-2px);
    }

    /* Responsive */
    @media(max-width:900px){
      .sidebar{display:none;}
      .content{padding:20px;}
    }

  </style>
</head>
<body>

<div class="app">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand">Globalhand Admin</div>

    <div class="nav">
      <a href="#">Dashboard</a>
      <a href="admin_deposits.php">Deposits</a>
      <a href="admin_withdrawals.php">Withdrawals</a>
      <a href="/handtoglobal/admin/logout.php">Logout</a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="content">

    <div class="header">
      <div class="title">Admin Dashboard</div>
    </div>

    <!-- Example Summary Cards -->
    <div class="cards">
      <div class="card">
        <h3>Manage Deposits</h3>
        <p>Review & Approve</p>
      </div>

      <div class="card">
        <h3>Manage Withdrawals</h3>
        <p>Process Requests</p>
      </div>

      <div class="card">
        <h3>System Status</h3>
        <p>Active</p>
      </div>
    </div>

    <!-- Main Actions -->
    <div class="actions">
      <a class="btn" href="admin_deposits.php">Approve Deposits</a>
      <a class="btn" href="admin_withdrawals.php">Approve Withdrawals</a>
      <a href="admin_users.php">Manage Users</a>
      <a class="btn" href="/handtoglobal/admin/logout.php">Logout</a>
    </div>

  </main>

</div>

</body>
</html>
