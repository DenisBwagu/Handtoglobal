<?php
session_start();
require 'config.php';
require 'get_setting.php';

// Get Telegram link from settings
$supportLink = get_setting('telegram_link', '<?php echo htmlspecialchars($supportLink); ?>');

if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

$user_id = (int)$_SESSION['user'];

$stmt = $conn->prepare("SELECT id, fullname, email, balance, level FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) { session_destroy(); header("Location: login.php"); exit(); }

/* Earnings */
$stmt = $conn->prepare("
  SELECT t.title, t.level, t.reward, ct.completed_at
  FROM completed_tasks ct
  JOIN tasks t ON t.id = ct.task_id
  WHERE ct.user_id=?
  ORDER BY ct.completed_at DESC
  LIMIT 300
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$earnings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Deposits */
$stmt = $conn->prepare("
  SELECT amount, status, created_at
  FROM deposits
  WHERE user_id=?
  ORDER BY created_at DESC
  LIMIT 300
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$deposits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Withdrawals */
$stmt = $conn->prepare("
  SELECT amount, wallet_address, status, created_at
  FROM withdrawals
  WHERE user_id=?
  ORDER BY created_at DESC
  LIMIT 300
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$withdrawals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function badge($txt){
  return '<span style="display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid #e5e7eb;background:#fafafa;font-weight:900;font-size:12px">'.$txt.'</span>';
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Transactions - Globalhand</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">

  <style>
    :root{
      --bg:#f6f7fb;
      --card:#ffffff;
      --text:#1f2937;
      --muted:#6b7280;
      --border:#e5e7eb;
      --shadow: 0 14px 40px rgba(16,24,40,.08);
      --primary:#635bff;
      --primary2:#4f46e5;
      --teal:#00c2ff;
      --pink:#ff4d8d;
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--text)}
    .app{display:flex;min-height:100vh}
    .sidebar{
      width:270px;background:#101828;color:#e5e7eb;padding:18px;
      position:sticky;top:0;height:100vh;border-right:1px solid rgba(255,255,255,.06)
    }
    .brand{display:flex;align-items:center;gap:12px;padding:10px 10px 16px;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:14px}
    .logo{width:42px;height:42px;border-radius:14px;background:radial-gradient(circle at 30% 20%, var(--teal), var(--primary));display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff}
    .brandTitle{margin:0;font-size:16px;font-weight:900;color:#fff}
    .brandSub{margin:2px 0 0;color:#b7c0d6;font-size:12px;font-weight:700}
    .nav{display:flex;flex-direction:column;gap:10px;margin-top:14px}
    .nav a{display:block;padding:10px 12px;border-radius:14px;background:rgba(255,255,255,.06);font-weight:800;color:#e5e7eb;text-decoration:none}
    .nav a:hover{background:rgba(255,255,255,.10);transform:translateY(-1px)}
    .nav a.active{background:rgba(99,91,255,.32)}
    .content{flex:1;padding:18px}
    .wrap{max-width:1250px;margin:auto}
    @media(max-width:980px){.sidebar{display:none}.content{padding:14px}}

    .top{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:18px;
      box-shadow:var(--shadow);
      padding:16px;
      display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;
      margin-bottom:14px;
    }
    .hTitle{margin:0;font-weight:900;letter-spacing:-.02em}
    .muted{color:var(--muted);font-weight:700;font-size:13px;margin-top:6px}
    code{background:#f3f4f6;padding:4px 8px;border-radius:999px;border:1px solid var(--border)}
    .btn{
      display:inline-flex;align-items:center;gap:8px;
      padding:10px 12px;border-radius:14px;
      font-weight:900;background:var(--primary);color:#fff;text-decoration:none
    }
    .btn:hover{background:var(--primary2)}
    .panel{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:18px;
      box-shadow:var(--shadow);
      padding:16px;
    }

    .tabs{display:flex;gap:10px;flex-wrap:wrap}
    .tab{
      padding:10px 12px;border-radius:14px;border:1px solid var(--border);
      background:#fafafa;font-weight:900;color:#374151;cursor:pointer;
    }
    .tab.active{background:rgba(99,91,255,.10);border-color:rgba(99,91,255,.25);color:#3b37c7}

    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border-bottom:1px solid #f1f5f9;padding:10px;text-align:left;font-size:13px}
    th{color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:.06em}
  </style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <div class="logo">GH</div>
      <div>
        <h2 class="brandTitle"><?php echo __t('globalhand', 'Globalhand'); ?></h2>
        <p class="brandSub"><?php echo __t('transactions', 'Transactions'); ?></p>
      </div>
    </div>
    <div class="nav">
      <a href="dashboard.php"><?php echo __t('dashboard', 'Dashboard'); ?></a>
      <a class="active" href="transactions.php"><?php echo __t('transactions', 'Transactions'); ?></a>
      <a href="<?php echo htmlspecialchars($supportLink); ?>" target="_blank"><?php echo __t('customer_service', 'Customer Service'); ?></a>
      <a href="withdraw.php"><?php echo __t('withdraw', 'Withdraw'); ?></a>
      <a href="logout.php"><?php echo __t('logout', 'Logout'); ?></a>
    </div>
  </aside>

  <main class="content">
    <div class="wrap">
      <div class="top">
        <div>
          <h2 class="hTitle"><?php echo __t('transactions', 'Transactions'); ?></h2>
          <div class="muted">
            <?php echo htmlspecialchars($user['fullname']); ?> •
            <?php echo __t('balance', 'Balance'); ?>: <strong><?php echo htmlspecialchars($user['balance']); ?> USDT</strong> •
            <?php echo __t('level', 'Level'); ?>: <strong><?php echo htmlspecialchars($user['level']); ?></strong>
          </div>
          <div class="muted" style="margin-top:10px">
            <?php echo __t('referral_link', 'Referral link'); ?>: <code><?php echo "http://localhost/globalhand/register.php?ref=".$user['id']; ?></code>
          </div>
        </div>
        <a class="btn" href="dashboard.php"><?php echo __t('back_to_dashboard', 'Back to Dashboard'); ?></a>
      </div>

      <div class="panel">
        <div class="tabs">
          <button class="tab active" onclick="showTab('earn')"><?php echo __t('earnings', 'Earnings'); ?></button>
          <button class="tab" onclick="showTab('dep')"><?php echo __t('deposits', 'Deposits'); ?></button>
          <button class="tab" onclick="showTab('wd')"><?php echo __t('withdrawals', 'Withdrawals'); ?></button>
        </div>

        <div id="earn" style="margin-top:12px">
          <?php echo __t('task_earnings', 'Task Earnings'); ?>
          <?php if(empty($earnings)){ ?>
            <div class="muted"><?php echo __t('no_earnings_yet', 'No earnings yet.'); ?></div>
          <?php } else { ?>
            <table>
              <tr><th><?php echo __t('date', 'Date'); ?></th><th><?php echo __t('level', 'Level'); ?></th><th><?php echo __t('task', 'Task'); ?></th><th><?php echo __t('reward', 'Reward'); ?></th></tr>
              <?php foreach($earnings as $e){ ?>
                <tr>
                  <td><?php echo htmlspecialchars($e['completed_at']); ?></td>
                  <td><?php echo htmlspecialchars($e['level']); ?></td>
                  <td><?php echo htmlspecialchars($e['title']); ?></td>
                  <td><strong><?php echo htmlspecialchars($e['reward']); ?> USDT</strong></td>
                </tr>
              <?php } ?>
            </table>
          <?php } ?>
        </div>

        <div id="dep" style="display:none;margin-top:12px">
          <?php echo badge("Deposits"); ?>
          <?php if(empty($deposits)){ ?>
            <div class="muted">No deposits yet.</div>
          <?php } else { ?>
            <table>
              <tr><th>Date</th><th>Amount</th><th>Status</th></tr>
              <?php foreach($deposits as $d){ ?>
                <tr>
                  <td><?php echo htmlspecialchars($d['created_at']); ?></td>
                  <td><strong><?php echo htmlspecialchars($d['amount']); ?> USDT</strong></td>
                  <td><?php echo htmlspecialchars($d['status']); ?></td>
                </tr>
              <?php } ?>
            </table>
          <?php } ?>
        </div>

        <div id="wd" style="display:none;margin-top:12px">
          <?php echo badge("Withdrawals"); ?>
          <?php if(empty($withdrawals)){ ?>
            <div class="muted">No withdrawals yet.</div>
          <?php } else { ?>
            <table>
              <tr><th>Date</th><th>Amount</th><th>Wallet</th><th>Status</th></tr>
              <?php foreach($withdrawals as $w){ ?>
                <tr>
                  <td><?php echo htmlspecialchars($w['created_at']); ?></td>
                  <td><strong><?php echo htmlspecialchars($w['amount']); ?> USDT</strong></td>
                  <td><?php echo htmlspecialchars($w['wallet_address']); ?></td>
                  <td><?php echo htmlspecialchars($w['status']); ?></td>
                </tr>
              <?php } ?>
            </table>
          <?php } ?>
        </div>

      </div>
    </div>
  </main>
</div>

<script>
  function showTab(id){
    document.querySelectorAll(".tab").forEach(t=>t.classList.remove("active"));
    const tabs = document.querySelectorAll(".tab");
    if(id==="earn") tabs[0].classList.add("active");
    if(id==="dep")  tabs[1].classList.add("active");
    if(id==="wd")   tabs[2].classList.add("active");

    document.getElementById("earn").style.display = (id==="earn") ? "block" : "none";
    document.getElementById("dep").style.display  = (id==="dep")  ? "block" : "none";
    document.getElementById("wd").style.display   = (id==="wd")   ? "block" : "none";
  }
</script>
</body>
</html>