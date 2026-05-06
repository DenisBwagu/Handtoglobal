<?php
require_once __DIR__ . '/config.php';

requireLogin();

$user = getUserById($_SESSION['user_id']);
$stats = getUserStats($_SESSION['user_id']);
$notifications = getUnreadNotifications($_SESSION['user_id']);

// Get recent activity
$conn = getConnection();
$stmt = $conn->prepare("SELECT * FROM completed_tasks ct 
                       JOIN tasks t ON ct.task_id = t.id 
                       WHERE ct.user_id = ? 
                       ORDER BY ct.completed_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recentTasks = $stmt->fetchAll();

// Get recent deposits
$stmt = $conn->prepare("SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$_SESSION['user_id']]);
$recentDeposits = $stmt->fetchAll();

// Get recent withdrawals
$stmt = $conn->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$_SESSION['user_id']]);
$recentWithdrawals = $stmt->fetchAll();

/* AUTO REDIRECT to Telegram if insufficient */
if ((float)$user['balance'] < 0) {
  header("Location: " . TELEGRAM_SUPPORT);
  exit();
}
/* ===== BRONZE UNLOCK FEE (One-time deduction) ===== */

if (isset($_GET['level']) && $_GET['level'] === "Bronze") {

    // Check if bronze already unlocked
    $stmt = $conn->prepare("SELECT bronze_unlocked FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $unlockCheck = $stmt->get_result()->fetch_assoc();

    if ($unlockCheck && $unlockCheck['bronze_unlocked'] == 0) {

        // Deduct full balance (remove 100 USDT)
        $stmt = $conn->prepare("UPDATE users SET balance=0, bronze_unlocked=1 WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        // Refresh user data
        $stmt = $conn->prepare("
    SELECT id, fullname, email, balance, level, rating, accuracy, total_tasks 
    FROM users 
    WHERE id=? 
    LIMIT 1
");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    }
}

/* Bronze completion count */
$stmt = $conn->prepare("
  SELECT COUNT(*) c
  FROM completed_tasks ct
  JOIN tasks t ON t.id = ct.task_id
  WHERE ct.user_id=? AND t.level='Bronze'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$completedBronze = (int)$stmt->get_result()->fetch_assoc()['c'];

$canAccessHigherLevels = ($completedBronze >= 40);

/* Selected category */
$allowedLevels = ["Bronze","Silver","Gold","Platinum","VIP1","VIP2","VIP3"];
$selectedLevel = isset($_GET['level']) ? $_GET['level'] : null;
/* ===============================
   CLEAN LEVEL UNLOCK SYSTEM
================================= */

if ($selectedLevel === "Bronze") {

    if ((int)$user['bronze_unlocked'] === 0) {

        if ($user['balance'] >= 100) {

            $stmt = $conn->prepare("
                UPDATE users 
                SET balance = balance - 100,
                    bronze_unlocked = 1
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            // Refresh user data
            $stmt = $conn->prepare("
                SELECT id, fullname, email, balance, level, 
                       bronze_unlocked, silver_unlocked, 
                       gold_unlocked, platinum_unlocked 
                FROM users WHERE id=?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

        } else {
            header("Location: " . TELEGRAM_SUPPORT);
            exit();
        }
    }
}


/* ===== SILVER ===== */
if ($selectedLevel === "Silver") {

    if ($completedBronze < 40) {
        header("Location: dashboard.php?level=Bronze");
        exit();
    }

    if ((int)$user['silver_unlocked'] === 0) {

        if ($user['balance'] >= 150) {

            $stmt = $conn->prepare("
                UPDATE users 
                SET silver_unlocked = 1 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

        } else {
            header("Location: " . TELEGRAM_SUPPORT);
            exit();
        }
    }
}


/* ===== GOLD ===== */
if ($selectedLevel === "Gold") {

    if ((int)$user['gold_unlocked'] === 0) {

        if ($user['balance'] >= 500) {

            $stmt = $conn->prepare("
                UPDATE users 
                SET gold_unlocked = 1 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

        } else {
            header("Location: " . TELEGRAM_SUPPORT);
            exit();
        }
    }
}


/* ===== PLATINUM ===== */
if ($selectedLevel === "Platinum") {

    if ((int)$user['platinum_unlocked'] === 0) {

        if ($user['balance'] >= 1500) {

            $stmt = $conn->prepare("
                UPDATE users 
                SET platinum_unlocked = 1 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

        } else {
            header("Location: " . TELEGRAM_SUPPORT);
            exit();
        }
    }
}
if ($selectedLevel === "VIP1") {

    if ((int)$user['vip1_unlocked'] === 0) {

        if ($user['balance'] >= 2500) {

            $stmt = $conn->prepare("
                UPDATE users 
                SET balance = balance - 2500,
                    vip1_unlocked = 1
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

        } else {
            header("Location: " . TELEGRAM_SUPPORT);
            exit();
        }
    }
}
if ($selectedLevel === "VIP2") {

    if ((int)$user['vip1_unlocked'] !== 1) {
        header("Location: dashboard.php?level=VIP1");
        exit();
    }

    if ((int)$user['vip2_unlocked'] === 0) {

        if ($user['balance'] >= 5000) {

            $stmt = $conn->prepare("
                UPDATE users 
                SET balance = balance - 5000,
                    vip2_unlocked = 1
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

        } else {
            header("Location: " . TELEGRAM_SUPPORT);
            exit();
        }
    }
}
if ($selectedLevel === "VIP3") {

    if ((int)$user['vip2_unlocked'] !== 1) {
        header("Location: dashboard.php?level=VIP2");
        exit();
    }

    if ((int)$user['vip3_unlocked'] === 0) {

        if ($user['balance'] >= 7000) {

            $stmt = $conn->prepare("
                UPDATE users 
                SET balance = balance - 7000,
                    vip3_unlocked = 1
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

        } else {
            header("Location: " . TELEGRAM_SUPPORT);
            exit();
        }
    }
}
/* ===============================
   BRONZE UNLOCK LOGIC
================================= */

if ($selectedLevel === "Bronze") {

    // Check if user already unlocked
    if ((int)$user['bronze_unlocked'] === 0) {

        // Only unlock if balance >= 100
        if ((float)$user['balance'] >= 100) {

            // Remove 100 USDT
            $stmt = $conn->prepare("
                UPDATE users 
                SET balance = balance - 100,
                    bronze_unlocked = 1
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            // Refresh user data
            $stmt = $conn->prepare("SELECT id, fullname, email, balance, level, bronze_unlocked FROM users WHERE id=?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

        } else {

            // Not enough balance → redirect to Telegram
            header("Location: " . TELEGRAM_SUPPORT);
            exit();

        }
    }
}
if ($selectedLevel !== null && !in_array($selectedLevel, $allowedLevels, true)) $selectedLevel = null;

$lockMsg = "";
if (!$canAccessHigherLevels && in_array($selectedLevel, ["Silver","Gold","Platinum"], true)) {
  $selectedLevel = "Bronze";
  $lockMsg = "Complete Bronze 40/40 to unlock Silver, Gold, and Platinum.";
}

/* Daily count */
$stmt = $conn->prepare("SELECT COUNT(*) c FROM completed_tasks WHERE user_id=? AND DATE(completed_at)=CURDATE()");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$todayDone = (int)$stmt->get_result()->fetch_assoc()['c'];

/* Tasks hidden until category selected */
$tasks = [];

if ($selectedLevel !== null) {

    // Count completed tasks in this level
    $stmt = $conn->prepare("
        SELECT COUNT(*) c
        FROM completed_tasks ct
        JOIN tasks t ON t.id = ct.task_id
        WHERE ct.user_id=? AND t.level=?
    ");
    $stmt->bind_param("is", $user_id, $selectedLevel);
    $stmt->execute();
    $completedCount = (int)$stmt->get_result()->fetch_assoc()['c'];

    // Only allow up to 40 per level
    if ($completedCount < 40) {

    $stmt = $conn->prepare("
        SELECT t.*
        FROM tasks t
        LEFT JOIN completed_tasks ct 
            ON ct.task_id = t.id AND ct.user_id = ?
        WHERE t.level = ? 
          AND ct.id IS NULL
        ORDER BY t.id ASC
        LIMIT 1
    ");
    $stmt->bind_param("is", $user_id, $selectedLevel);
    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
      
}
/* Recent Earnings */
$stmt = $conn->prepare("
  SELECT u.fullname, t.reward, ct.completed_at
  FROM completed_tasks ct
  JOIN users u ON u.id = ct.user_id
  JOIN tasks t ON t.id = ct.task_id
  ORDER BY ct.completed_at DESC
  LIMIT 8
");
$stmt->execute();
$recentEarnings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt = $conn->prepare("
  SELECT u.fullname, d.amount, d.created_at
  FROM deposits d
  JOIN users u ON u.id = d.user_id
  WHERE d.status='Approved'
  ORDER BY d.created_at DESC
  LIMIT 8
");
$stmt->execute();
$recentDeposits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("
  SELECT u.fullname, w.amount, w.created_at
  FROM withdrawals w
  JOIN users u ON u.id = w.user_id
  WHERE w.status='Approved'
  ORDER BY w.created_at DESC
  LIMIT 8
");
$stmt->execute();
$recentWithdrawals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* UI helpers */
$initial = strtoupper(substr($user['fullname'], 0, 1));

function levelBadgeClass($level){
  switch($level){
    case "Bronze": return "badge bronze";
    case "Silver": return "badge silver";
    case "Gold": return "badge gold";
    case "Platinum": return "badge plat";
    default: return "badge";
  }
}

/* Pre-escaped Telegram URL for safe HTML attributes */
$tg = htmlspecialchars(TELEGRAM_SUPPORT, ENT_QUOTES, 'UTF-8');

/* REAL notifications list (for bell dropdown + toast popups) */
$notifs = [];
foreach ($recentEarnings as $e) {
  $notifs[] = ["type"=>"EARN", "text"=>$e["fullname"]." earned ".$e["reward"]." USDT", "time"=>$e["completed_at"]];
}
foreach ($recentDeposits as $d) {
  $notifs[] = ["type"=>"DEPOSIT", "text"=>$d["fullname"]." deposited ".$d["amount"]." USDT", "time"=>$d["created_at"]];
}
foreach ($recentWithdrawals as $w) {
  $notifs[] = ["type"=>"WITHDRAW", "text"=>$w["fullname"]." withdrew ".$w["amount"]." USDT", "time"=>$w["created_at"]];
}
$notifs = array_slice($notifs, 0, 15);
?>
<!DOCTYPE html>
<html>
<head>
  <title>My Account - Globalhand</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Inter font -->
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
      --green:#00d647;
      --orange:#ffad33;
      --pink:#ff4d8d;
      --red:#ef4444;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
    }
    a{color:inherit;text-decoration:none}
    .app{display:flex;min-height:100vh}
    .selectBtn {
    padding: 10px 18px;
    border-radius: 25px;
    border: 1px solid #f5b041;
    background: #fff3e0;
    color: #8a5a00;
    font-weight: 700;
    font-size: 14px;
    cursor: not-allowed;
}
.smallBtn {
    padding: 6px 14px;
    font-size: 13px;
    border-radius: 18px;
}
.miniNotice{
    padding: 8px 16px;
    border-radius: 25px;
    border: 1px solid #d1d5db;
    background: #f9fafb;
    color: #6b7280;
    font-weight: 700;
    font-size: 13px;
    cursor: not-allowed;
}
    /* ===== Sidebar (desktop) ===== */
    .sidebar{
      width:270px;
      background:#101828;
      color:#e5e7eb;
      padding:18px;
      position:sticky; top:0; height:100vh;
      border-right:1px solid rgba(255,255,255,.06);
    }
    .brand{
      display:flex; align-items:center; gap:12px;
      padding:10px 10px 16px;
      border-bottom:1px solid rgba(255,255,255,.08);
      margin-bottom:14px;
    }
    .logo{
      width:42px;height:42px;border-radius:14px;
      background: radial-gradient(circle at 30% 20%, var(--teal), var(--primary));
      display:flex;align-items:center;justify-content:center;
      font-weight:900;color:#fff;letter-spacing:-.02em;
      box-shadow: 0 10px 22px rgba(99,91,255,.25);
    }
    .brandTitle{margin:0;font-size:16px;font-weight:900;color:#fff}
    .brandSub{margin:2px 0 0;color:#b7c0d6;font-size:12px;font-weight:700}

    .nav{display:flex;flex-direction:column;gap:10px;margin-top:14px}
    .nav a{
      display:flex; align-items:center; justify-content:space-between;
      padding:10px 12px;
      border-radius:14px;
      background:rgba(255,255,255,.06);
      font-weight:800;
      transition:.15s;
    }
    .nav a:hover{background:rgba(255,255,255,.10); transform: translateY(-1px);}
    .nav a.active{background:rgba(99,91,255,.32)}
    .nav small{display:block;color:#b7c0d6;font-weight:700;margin-top:2px}

    .sideCard{
      margin-top:14px;
      padding:12px;
      border-radius:16px;
      background:rgba(255,255,255,.06);
      border:1px solid rgba(255,255,255,.08);
    }
    .sideCardTitle{font-weight:900;color:#fff;margin:0 0 8px}
    .sideRow{display:flex;justify-content:space-between;color:#b7c0d6;font-weight:700;font-size:13px;margin:6px 0}

    /* ===== Content ===== */
    .content{flex:1; padding:18px;}
    .wrap{max-width:1250px; margin:auto;}

    /* ===== Topbar ===== */
    .topbar{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:14px;
      margin-bottom:14px;
    }
    .leftTop{
      display:flex;
      align-items:center;
      gap:10px;
      flex:1;
      min-width:0;
    }

    /* Hamburger (mobile) */
    .hamburger{
      display:none;
      width:44px;height:44px;border-radius:14px;
      background:var(--card);border:1px solid var(--border);box-shadow:var(--shadow);
      align-items:center;justify-content:center;cursor:pointer;
      user-select:none;
    }

    .headline{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:18px;
      box-shadow: var(--shadow);
      padding:14px 16px;
      flex:1;
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:12px;
      min-width:0;
    }
    .hTitle{margin:0;font-weight:900;letter-spacing:-.02em}
    .hMeta{margin-top:6px;color:var(--muted);font-weight:700;font-size:13px}
    .chips{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    .chip{
      padding:8px 10px;border-radius:999px;border:1px solid var(--border);
      background:#fafafa;font-weight:800;font-size:12px;color:#374151;
    }

    .rightTop{display:flex;align-items:center;gap:10px}

    /* Bell / icon button */
    .iconBtn{
      width:44px;height:44px;border-radius:14px;
      background:var(--card);border:1px solid var(--border);box-shadow:var(--shadow);
      display:flex;align-items:center;justify-content:center;
      cursor:pointer;position:relative;user-select:none;
    }
    .dot{
      position:absolute;top:10px;right:10px;width:10px;height:10px;border-radius:999px;background:var(--pink);
      border:2px solid #fff;
    }

    /* Notifications dropdown */
    .notifWrap{position:relative}
    .notifDrop{
      position:absolute;right:0;top:54px;width:340px;max-height:420px;overflow:auto;
      background:var(--card);border:1px solid var(--border);border-radius:18px;
      box-shadow:0 22px 60px rgba(16,24,40,.22);
      display:none;z-index:60;
    }
    .notifDrop.show{display:block}
    .notifHead{
      padding:14px;border-bottom:1px solid var(--border);font-weight:900;
      background: linear-gradient(135deg, rgba(99,91,255,.12), rgba(0,194,255,.10));
    }
    .notifItem{padding:12px 14px;border-bottom:1px solid #f1f5f9}
    .notifItem:last-child{border-bottom:none}
    .nType{
      display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:900;border:1px solid var(--border);margin-right:8px
    }
    .nType.earn{background:rgba(0,214,71,.12);border-color:rgba(0,214,71,.25);color:#056f2c}
    .nType.dep{background:rgba(0,194,255,.12);border-color:rgba(0,194,255,.25);color:#006b8b}
    .nType.wd{background:rgba(255,77,141,.12);border-color:rgba(255,77,141,.25);color:#8a1b45}
    .nText{font-weight:800;font-size:13px}
    .nTime{margin-top:4px;color:var(--muted);font-weight:700;font-size:12px}

    /* Profile dropdown */
    .profile{position:relative;}
    .profileBtn{
      display:flex; align-items:center; gap:10px;
      background:var(--card);
      border:1px solid var(--border);
      border-radius:999px;
      box-shadow: var(--shadow);
      padding:10px 12px;
      cursor:pointer;
      user-select:none;
    }
    .avatar{
      width:40px;height:40px;border-radius:999px;
      background: radial-gradient(circle at 30% 20%, var(--pink), var(--primary));
      color:#fff;font-weight:900;
      display:flex;align-items:center;justify-content:center;
    }
    .pName{font-weight:900}
    .pSub{font-size:12px;color:var(--muted);font-weight:700;margin-top:2px}

    .dropdown{
      position:absolute;right:0;top:58px;width:290px;background:var(--card);border:1px solid var(--border);
      border-radius:18px;box-shadow: 0 22px 60px rgba(16,24,40,.22);
      overflow:hidden;display:none;z-index:50;
    }
    .dropdown.show{display:block;}
    .dropHead{
      padding:14px;
      background: linear-gradient(135deg, rgba(99,91,255,.12), rgba(0,194,255,.10));
      border-bottom:1px solid var(--border);
    }
    .dropEmail{color:var(--muted);font-weight:700;font-size:12px;margin-top:4px}
    .dropLink{display:block;padding:12px 14px;font-weight:900;border-bottom:1px solid var(--border);background:#fff}
    .dropLink:hover{background:#f8fafc}
    .dropLink:last-child{border-bottom:none}

    /* ===== Layout panels ===== */
    .grid2{display:grid; grid-template-columns: 1.35fr 1fr; gap:14px;}
    .panel{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:18px;
      box-shadow: var(--shadow);
      padding:16px;
    }

    @media(max-width:980px){
      .sidebar{display:none;}
      .hamburger{display:flex;}
      .grid2{grid-template-columns:1fr;}
      .content{padding:14px;}
      .sidebarMobile{display:block;}
    }

    /* Alerts */
    .alert{
      margin-top:10px;
      padding:12px 14px;
      border-radius:16px;
      border:1px solid rgba(255,173,51,.35);
      background: rgba(255,173,51,.12);
      color:#7c4a00;
      font-weight:800;
    }

    /* Categories */
    .cats{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(190px,1fr));
      gap:12px;
      margin-top:12px;
    }
    .cat{
      position:relative;
      border:1px solid var(--border);
      border-radius:18px;
      padding:14px;
      background: linear-gradient(180deg,#ffffff,#fbfbff);
      box-shadow: 0 10px 26px rgba(16,24,40,.06);
      transition:.15s;
    }
    .cat:hover{transform:translateY(-2px);}
    .catTitle{display:flex;align-items:center;justify-content:space-between;gap:10px}
    .catName{margin:0;font-weight:900;letter-spacing:-.02em}
    .catInfo{margin:8px 0 0;color:var(--muted);font-weight:700;font-size:13px}
    .lock{
      font-size:12px;font-weight:900;padding:6px 10px;border-radius:999px;
      background: rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);color: var(--red);
    }

    /* Badges */
    .badge{padding:6px 10px;border-radius:999px;font-weight:900;font-size:12px;border:1px solid var(--border);background:#fff}
    .badge.bronze{border-color: rgba(255,173,51,.35); background: rgba(255,173,51,.12); color:#7c4a00;}
    .badge.silver{border-color: rgba(156,163,175,.35); background: rgba(156,163,175,.12); color:#374151;}
    .badge.gold{border-color: rgba(245,158,11,.35); background: rgba(245,158,11,.12); color:#7c4a00;}
    .badge.plat{border-color: rgba(99,91,255,.35); background: rgba(99,91,255,.12); color: var(--primary2);}
    .badge.vip{
  border-color: rgba(168,85,247,.35);
  background: rgba(168,85,247,.12);
  color: #7c3aed;
}

    /* Toggle bar (Board/Table) */
    .viewBar{
      margin-top:14px;
      display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;
    }
    .toggle{display:flex;gap:8px;align-items:center}
    .tBtn{
      padding:10px 12px;border-radius:14px;border:1px solid var(--border);background:#fafafa;
      font-weight:900;cursor:pointer;
    }
    .tBtn.active{background:rgba(99,91,255,.10);border-color:rgba(99,91,255,.25);color:#3b37c7}

    /* Tasks cards (Board view) */
    .taskGrid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
  gap:14px;
}
    
    .task{
  border:1px solid var(--border);
  border-radius:20px;
  background:#fff;
  box-shadow: 0 20px 45px rgba(16,24,40,.08);
  overflow:hidden;
  transition:.2s;
}

.task:hover{
  transform: translateY(-4px);
  box-shadow: 0 28px 60px rgba(16,24,40,.12);
}
 .taskImg{
  width:100%;
  height:auto;
  max-height:320px;
  object-fit:contain;
  background:#f8fafc;
  padding:12px;
}
    .taskTitle{margin:8px 0 6px;font-weight:900;letter-spacing:-.02em}
    .taskDesc{margin:0 0 12px;color:var(--muted);font-weight:600;font-size:13px;line-height:1.4}
    .taskRow{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}

    .btn{
      display:inline-flex;align-items:center;gap:8px;
      padding:10px 12px;border-radius:14px;font-weight:900;border:1px solid transparent;
      background: var(--primary);color:#fff;transition:.12s;
    }
    .btn:hover{background: var(--primary2); transform: translateY(-1px);}
    .btn.ghost{background:#fff;border-color:var(--border);color:#374151;}
    .btn.ghost:hover{background:linear-gradient(135deg,#f8fafc,#eef2ff);transform:none;}

    /* Tasks Table view */
    .tableWrap{
      margin-top:12px;
      overflow:auto;
      border:1px solid var(--border);
      border-radius:18px;
      background:#fff;
    }
    table{width:100%;border-collapse:collapse}
    th,td{padding:12px;border-bottom:1px solid #f1f5f9;text-align:left;font-size:13px}
    th{color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:.06em;background:#fbfbff}
    tr:hover td{background:#fbfdff}

    /* Activity feed */
    .feedTitle{margin:0 0 8px;font-weight:900}
    .feedSub{color:var(--muted);font-weight:700;font-size:13px;margin:0 0 12px}
    .feedItem{padding:10px 0;border-bottom:1px solid #f1f5f9;font-size:13px}
    .feedItem:last-child{border-bottom:none}
    .tag{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:900;margin-right:8px;border:1px solid var(--border)}
    .tag.earn{background: rgba(0,214,71,.12); border-color: rgba(0,214,71,.25); color:#056f2c;}
    .tag.dep{background: rgba(0,194,255,.12); border-color: rgba(0,194,255,.25); color:#006b8b;}
    .tag.wd{background: rgba(255,77,141,.12); border-color: rgba(255,77,141,.25); color:#8a1b45;}
    .time{color:var(--muted);font-size:12px;font-weight:700;margin-top:4px}
    code{background:#f3f4f6;padding:4px 8px;border-radius:999px;border:1px solid var(--border)}

    /* ===== Mobile Sidebar overlay ===== */
    .overlay{
      display:none;
      position:fixed; inset:0;
      background:rgba(0,0,0,.45);
      z-index:80;
    }
    .overlay.show{display:block;}
    .sidebarMobile{
      display:none;
      position:fixed; top:0; left:0;
      width:290px; height:100vh;
      z-index:90;
      transform:translateX(-110%);
      transition: transform .18s ease;
    }
    .sidebarMobile.open{transform:translateX(0);}

    /* Toasts */
    .toast{
      position:fixed; right:18px; bottom:18px;
      width:min(360px, 92vw);
      background:#fff;border:1px solid var(--border);
      border-radius:18px;box-shadow:0 22px 60px rgba(16,24,40,.22);
      padding:12px 14px;display:none;z-index:200;
      animation:toastIn .25s ease;
    }
    @keyframes toastIn{from{transform:translateY(10px);opacity:0}to{transform:translateY(0);opacity:1}}
    .toastTop{display:flex;justify-content:space-between;align-items:center;gap:10px}
    .toastClose{cursor:pointer;font-weight:900;color:var(--muted)}
    .toastText{margin-top:6px;font-weight:900}
    .toastTime{margin-top:4px;color:var(--muted);font-weight:700;font-size:12px}
    /* ===== DARK MODE ===== */
body.dark{
  --bg:#0f172a;
  --card:#111827;
  --text:#e5e7eb;
  --muted:#94a3b8;
  --border:#1f2937;
}

body.dark .sidebar{
  background:#0b1220;
  box-shadow:none;
}

body.dark .panel,
body.dark .headline,
body.dark .task,
body.dark .cat,
body.dark .dropdown,
body.dark .notifDrop,
body.dark .tableWrap{
  background:#111827;
  border-color:#1f2937;
  box-shadow:none;
}

body.dark .nav a{
  background:rgba(255,255,255,.05);
}

body.dark .nav a.active{
  background:rgba(99,91,255,.45);
}

body.dark .btn.ghost{
  background:#0f172a;
  color:#e5e7eb;
  border-color:#1f2937;
}

body.dark table th{
  background:#0f172a;
}
/* ======================================================
   GLOBALHAND MOBILE OPTIMIZATION (SAFE ADD-ON)
   Does NOT change your structure
   Only improves mobile layout
====================================================== */

/* Improve grid spacing */
.taskGrid{
  grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
  gap:14px;
}

/* Improve image scaling */
.taskImg{
  width:100%;
  height:auto;
  max-height:320px;
  object-fit:contain;
  background:#f8fafc;
  padding:12px;
}

/* Smooth mobile sidebar */
.sidebarMobile{
  transition: transform .25s ease;
}

/* ================= MOBILE ONLY ================= */
@media(max-width:600px){

  /* Stack task buttons */
  .taskRow{
    flex-direction:column;
    align-items:stretch;
    gap:10px;
  }

  .taskRow .btn{
    width:100%;
    text-align:center;
    justify-content:center;
  }

  /* Stack category cards */
  .cats{
    grid-template-columns:1fr;
  }

  /* Improve header stacking */
  .headline{
    flex-direction:column;
    align-items:flex-start;
  }

  .chips{
    justify-content:flex-start;
  }

  /* Reduce table spacing */
  .tableWrap{
    font-size:12px;
  }

  th, td{
    padding:8px;
  }

  /* Adjust panel spacing */
  .panel{
    padding:14px;
  }

  .content{
    padding:10px;
  }

}
  </style>
</head>

<body>
<div class="app">

  <!-- Desktop Sidebar -->
  <aside class="sidebar">
    <div class="brand">
      <div class="logo">GH</div>
      <div>
        <h2 class="brandTitle">Globalhand</h2>
        <p class="brandSub">Workspace</p>
      </div>
    </div>

    <div class="nav">
      <a class="active" href="dashboard.php">My Account <small>Tasks & Activity</small></a>
      <a href="transactions.php">Transactions <small>History</small></a>
      <a href="<?php echo $tg; ?>" target="_blank" rel="noopener">Customer Service <small>Telegram support</small></a>
      <a href="withdraw.php">Withdraw <small>Request payout</small></a>
      <a href="logout.php">Logout <small>Sign out</small></a>
    </div>

    <div class="sideCard">
      <p class="sideCardTitle">Progress</p>
      <div class="sideRow"><span>Bronze</span><span><?php echo $completedBronze; ?>/40</span></div>
      <div class="sideRow"><span>Today</span><span><?php echo $todayDone; ?>/<?php echo DAILY_TASK_LIMIT; ?></span></div>
      <div class="sideRow"><span>Balance</span><span><?php echo htmlspecialchars($user['balance']); ?> USDT</span></div>
    </div>
  </aside>

  <!-- Mobile Sidebar -->
  <div id="overlay" class="overlay"></div>
  <aside id="sidebarMobile" class="sidebar sidebarMobile">
    <div class="brand">
      <div class="logo">GH</div>
      <div>
        <h2 class="brandTitle">Globalhand</h2>
        <p class="brandSub">Menu</p>
      </div>
    </div>

    <div class="nav">
      <a class="active" href="dashboard.php">Dashboard <small>Tasks & Activity</small></a>
      <a href="transactions.php">Transactions <small>History</small></a>
      <a href="<?php echo $tg; ?>" target="_blank" rel="noopener">Customer Service <small>Telegram support</small></a>
      <a href="withdraw.php">Withdraw <small>Request payout</small></a>
      <a href="logout.php">Logout <small>Sign out</small></a>
    </div>

    <div class="sideCard">
      <p class="sideCardTitle">Progress</p>
      <div class="sideRow"><span>Bronze</span><span><?php echo $completedBronze; ?>/40</span></div>
      <div class="sideRow"><span>Today</span><span><?php echo $todayDone; ?>/<?php echo DAILY_TASK_LIMIT; ?></span></div>
      <div class="sideRow"><span>Balance</span><span><?php echo htmlspecialchars($user['balance']); ?> USDT</span></div>
    </div>
  </aside>

  <!-- Main -->
  <main class="content">
    <div class="wrap">

      <div class="topbar">

        <div class="leftTop">
          <!-- Hamburger -->
          <div id="hamburger" class="hamburger" title="Menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path d="M4 7h16M4 12h16M4 17h16" stroke="#111827" stroke-width="2.2" stroke-linecap="round"/>
            </svg>
          </div>

          <div class="headline">
            <div>
              <h2 class="hTitle">My Account</h2>
              <div class="hMeta">
                Welcome back, <strong><?php echo htmlspecialchars($user['fullname']); ?></strong> •
                Level: <strong><?php echo htmlspecialchars($user['level']); ?></strong> •
                Bronze: <strong><?php echo $completedBronze; ?>/40</strong>
              </div>
              <div class="hMeta" style="margin-top:10px">
                Referral link: <code><?php echo "http://localhost/globalhand/register.php?ref=".$user['id']; ?></code>
              </div>
            </div>

            <div class="chips">
              <span class="chip">Balance: <?php echo htmlspecialchars($user['balance']); ?> USDT</span>
              <span class="chip">
⭐ Rating: <?php echo number_format($user['rating'],2); ?>/5
</span>

<span class="chip">
📊 Accuracy: <?php echo number_format($user['accuracy'],2); ?>%
</span>
              <span class="chip">Today: <?php echo $todayDone; ?>/<?php echo DAILY_TASK_LIMIT; ?></span>
              <span class="<?php echo levelBadgeClass($user['level']); ?>"><?php echo htmlspecialchars($user['level']); ?></span>
            </div>
          </div>
        </div>

        <div class="rightTop">
           <!-- Dark Mode Toggle -->
  <div id="darkToggle" class="iconBtn" title="Toggle dark mode">
    🌙
  </div>
          <!-- Bell -->
          <div class="notifWrap">
            <div id="bellBtn" class="iconBtn" title="Notifications">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                <path d="M15 17H9" stroke="#111827" stroke-width="2" stroke-linecap="round"/>
                <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 7h18s-3 0-3-7" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <?php if (!empty($notifs)) { ?><span class="dot"></span><?php } ?>
            </div>

            <div id="notifDrop" class="notifDrop">
              <div class="notifHead">Notifications</div>
              <?php if (empty($notifs)) { ?>
                <div class="notifItem">
                  <div class="nText" style="color:var(--muted)">No recent notifications.</div>
                </div>
              <?php } else { ?>
                <?php foreach ($notifs as $n) { ?>
                  <div class="notifItem">
                    <?php
                      $cls = "earn";
                      if ($n["type"] === "DEPOSIT") $cls = "dep";
                      if ($n["type"] === "WITHDRAW") $cls = "wd";
                    ?>
                    <span class="nType <?php echo $cls; ?>"><?php echo htmlspecialchars($n["type"]); ?></span>
                    <div class="nText"><?php echo htmlspecialchars($n["text"]); ?></div>
                    <div class="nTime"><?php echo htmlspecialchars($n["time"]); ?></div>
                  </div>
                <?php } ?>
              <?php } ?>
            </div>
          </div>

          <!-- Profile -->
          <div class="profile">
            <div class="profileBtn" id="profileBtn">
              <div class="avatar"><?php echo $initial; ?></div>
              <div>
                <div class="pName"><?php echo htmlspecialchars($user['fullname']); ?></div>
                <div class="pSub"><?php echo htmlspecialchars($user['email']); ?></div>
              </div>
            </div>

            <div class="dropdown" id="dropdown">
              <div class="dropHead">
                <div style="font-weight:900;font-size:14px"><?php echo htmlspecialchars($user['fullname']); ?></div>
                <div class="dropEmail"><?php echo htmlspecialchars($user['email']); ?></div>
                <div class="dropEmail" style="margin-top:8px">Level: <strong><?php echo htmlspecialchars($user['level']); ?></strong></div>
              </div>
              <a class="dropLink" href="transactions.php">View Transactions</a>
              <a class="dropLink" href="<?php echo $tg; ?>" target="_blank" rel="noopener">Customer Service (Telegram)</a>
              <a class="dropLink" href="withdraw.php">Withdraw</a>
              <a class="dropLink" href="logout.php">Logout</a>
            </div>
          </div>
        </div>

      </div>

      <?php if (!empty($lockMsg)) { ?>
        <div class="panel">
          <div class="alert"><?php echo htmlspecialchars($lockMsg); ?></div>
        </div>
        <div style="height:12px"></div>
      <?php } ?>

      <div class="grid2">

        <!-- Left: Categories + Tasks -->
        <section class="panel">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
            <div>
              <h3 style="margin:0;font-weight:900">Task Categories</h3>
              <div style="margin-top:6px;color:var(--muted);font-weight:700;font-size:13px">
                Tasks are hidden until you choose a category.
              </div>
            </div>
            <a class="btn ghost" href="transactions.php">View history</a>
          </div>
<?php
function levelCompleted($conn, $user_id, $level){
    $stmt = $conn->prepare("
        SELECT COUNT(*) c
        FROM completed_tasks ct
        JOIN tasks t ON t.id = ct.task_id
        WHERE ct.user_id=? AND t.level=?
    ");
    $stmt->bind_param("is", $user_id, $level);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

function unlockStatus($currentBalance, $requiredAmount, $levelName){
    $missing = $requiredAmount - $currentBalance;

    if ($missing > 0) {
        return "<p class='catInfo'>Deposit <strong>".number_format($missing,2)." USDT</strong> to unlock {$levelName}</p>
                <a class='btn ghost' href='https://t.me/chica256' target='_blank'>Deposit to Unlock</a>";
    } else {
        return "<form method='POST' action='unlock_level.php'>
                    <input type='hidden' name='level' value='{$levelName}'>
                    <button class='btn'>Unlock {$levelName}</button>
                </form>";
    }
}

$completedBronze   = levelCompleted($conn, $user_id, "Bronze");
$completedSilver   = levelCompleted($conn, $user_id, "Silver");
$completedGold     = levelCompleted($conn, $user_id, "Gold");
$completedPlatinum = levelCompleted($conn, $user_id, "Platinum");
?>

         <div class="cats">

  <!-- Bronze -->
  <a class="cat" href="?level=Bronze">
    <div class="catTitle">
      <h4 class="catName">Bronze</h4>
      <span class="badge bronze"><?php echo $completedBronze; ?>/40</span>
    </div>
    <p class="catInfo">Complete 40 tasks to unlock Silver</p>
  </a>


  <!-- Silver -->
  <div class="cat">
    <div class="catTitle">
      <h4 class="catName">Silver</h4>
    </div>

    <?php if ($completedBronze < 40) { ?>
        <span class="lock">Finish Bronze 40/40</span>
    <?php } elseif ((int)$user['silver_unlocked'] === 1) { ?>
        <span class="badge silver">Unlocked</span>
    <?php } else { ?>
        <?php echo unlockStatus($user['balance'], 200, "Silver"); ?>
    <?php } ?>
  </div>


  <!-- Gold -->
  <!-- Gold -->
<div class="cat">
  <div class="catTitle">
    <h4 class="catName">Gold</h4>
  </div>

  <?php if ((int)$user['silver_unlocked'] !== 1) { ?>
      <span class="lock">Unlock Silver First</span>

  <?php } elseif ($completedSilver < 40) { ?>
      <span class="lock">Finish Silver 40/40</span>

  <?php } elseif ((int)$user['gold_unlocked'] === 1) { ?>
      <span class="badge gold">Unlocked</span>

  <?php } else { ?>

      <p class="catInfo">Required: <strong>800 USDT</strong></p>
      <p class="catInfo">Your Balance: <strong><?php echo number_format($user['balance'],2); ?> USDT</strong></p>

      <?php
          $missingGold = 500 - (float)$user['balance'];
          if ($missingGold > 0) {
      ?>
          <p class="catInfo" style="color:red;">
              Deposit <?php echo number_format($missingGold,2); ?> USDT to unlock Gold
          </p>
          <a class="btn ghost" href="https://t.me/chica256" target="_blank">Deposit to Unlock</a>

      <?php } else { ?>
          <form method="POST" action="unlock_level.php">
              <input type="hidden" name="level" value="Gold">
              <button class="btn">Unlock Gold</button>
          </form>
      <?php } ?>

  <?php } ?>
</div>


  <!-- Platinum -->
  <!-- Platinum -->
<div class="cat">
  <div class="catTitle">
    <h4 class="catName">Platinum</h4>
  </div>

  <?php if ((int)$user['gold_unlocked'] !== 1) { ?>
      <span class="lock">Unlock Gold First</span>

  <?php } elseif ($completedGold < 40) { ?>
      <span class="lock">Finish Gold 40/40</span>

  <?php } elseif ((int)$user['platinum_unlocked'] === 1) { ?>
      <span class="badge plat">Unlocked</span>

  <?php } else { ?>

      <p class="catInfo">Required: <strong>1200 USDT</strong></p>
      <p class="catInfo">Your Balance: <strong><?php echo number_format($user['balance'],2); ?> USDT</strong></p>

      <?php
          $missingPlatinum = 800 - (float)$user['balance'];
          if ($missingPlatinum > 0) {
      ?>
          <p class="catInfo" style="color:red;">
              Deposit <?php echo number_format($missingPlatinum,2); ?> USDT to unlock Platinum
          </p>
          <a class="btn ghost" href="https://t.me/chica256" target="_blank">Deposit to Unlock</a>

      <?php } else { ?>
          <form method="POST" action="unlock_level.php">
              <input type="hidden" name="level" value="Platinum">
              <a class="btn smallBtn" href="vip_task.php?level=Platinum">Start Platinum</a>
          </form>
      <?php } ?>

  <?php } ?>
  <!-- VIP 1 -->
<div class="cat">
  <div class="catTitle">
    <h4 class="catName">VIP 1</h4>
  </div>

  <?php if ((int)$user['vip1_unlocked'] === 1) { ?>
    <a class="btn" href="vip_task.php?level=VIP1">Start VIP 1</a>
<?php } else { ?>
      <p class="catInfo">Required: <strong>2500 USDT</strong></p>
      <p class="catInfo">Reward: <strong>100 USDT per task</strong></p>

      <?php
          $missing = 2500 - (float)$user['balance'];
          if ($missing > 0) {
      ?>
          <p class="catInfo" style="color:red;">
              Deposit <?php echo number_format($missing,2); ?> USDT to unlock VIP 1
          </p>
      <?php } else { ?>
          <a class="btn" href="?level=VIP1">Unlock VIP 1</a>
      <?php } ?>
  <?php } ?>
</div>


<!-- VIP 2 -->
<div class="cat">
  <div class="catTitle">
    <h4 class="catName">VIP 2</h4>
  </div>

  <?php if ((int)$user['vip2_unlocked'] === 1) { ?>
    <a class="btn" href="vip_task.php?level=VIP2">Start VIP 2</a>
<?php } else { ?>
      <p class="catInfo">Required: <strong>5000 USDT</strong></p>
      <p class="catInfo">Reward: <strong>200 USDT per task</strong></p>

      <?php
          $missing = 5000 - (float)$user['balance'];
          if ($missing > 0) {
      ?>
          <p class="catInfo" style="color:red;">
              Deposit <?php echo number_format($missing,2); ?> USDT to unlock VIP 2
          </p>
      <?php } else { ?>
          <a class="btn" href="?level=VIP2">Unlock VIP 2</a>
      <?php } ?>
  <?php } ?>
</div>


<!-- VIP 3 -->
<div class="cat">
  <div class="catTitle">
    <h4 class="catName">VIP 3</h4>
  </div>

  <?php if ((int)$user['vip3_unlocked'] === 1) { ?>
      <span class="badge gold">Unlocked</span>
  <?php } else { ?>
      <p class="catInfo">Required: <strong>7000 USDT</strong></p>
      <p class="catInfo">Reward: <strong>350+ USDT per task</strong></p>

      <?php
          $missing = 7000 - (float)$user['balance'];
          if ($missing > 0) {
      ?>
          <p class="catInfo" style="color:red;">
              Deposit <?php echo number_format($missing,2); ?> USDT to unlock VIP 3
          </p>
      <?php } else { ?>
          <a class="btn" href="?level=VIP3">Unlock VIP 3</a>
      <?php } ?>
  <?php } ?>
</div>

</div>
          <?php if ($selectedLevel === null) { ?>
            <div style="text-align:center;margin-top:20px;">
    <button class="selectBtn" disabled>
        Select a category above to view tasks
    </button>
</div>
          <?php } else { ?>

            <!-- View Toggle -->
            <div class="viewBar">
              <div>
                <h3 style="margin:0;font-weight:900"><?php echo htmlspecialchars($selectedLevel); ?> Tasks</h3>
                <div style="margin-top:6px;color:var(--muted);font-weight:700;font-size:13px">
                  Choose view: Board or Table
                </div>
              </div>
              <div class="toggle">
                <button id="boardBtn" class="tBtn active" type="button">Board</button>
                <button id="tableBtn" class="tBtn" type="button">Table</button>
              </div>
            </div>

            <?php if (empty($tasks)) { ?>
              <div style="text-align:center;margin-top:20px;">
    <button class="miniNotice" disabled>
        No tasks available in this category
    </button>
</div>
            <?php } else { ?>

              <!-- BOARD -->
              <div id="boardView">
                <div class="taskGrid">
                  <?php foreach($tasks as $task){ ?>
                    <div class="task">
                      <?php if(!in_array($task['level'], ['VIP1','VIP2','VIP3']) && !empty($task['image'])){ ?>
    <img class="taskImg" src="<?php echo htmlspecialchars($task['image']); ?>">
<?php } ?>
                        <div class="taskImg"></div>
                      <?php } ?>
                      <div class="taskBody">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
                          <span class="<?php echo levelBadgeClass($task['level']); ?>"><?php echo htmlspecialchars($task['level']); ?></span>
                          
                        </div>

                        <h4 class="taskTitle"><?php echo htmlspecialchars($task['title']); ?></h4>
                        <p class="taskDesc"><?php echo htmlspecialchars($task['description']); ?></p>

                        <div class="taskRow">
                          <?php if (in_array($task['level'], ['VIP1','VIP2','VIP3'])) { ?>

<form method="POST" action="complete_task.php?id=<?php echo (int)$task['id']; ?>">
    <div style="margin:10px 0;font-weight:800">
        <?php echo htmlspecialchars($task['description']); ?>
    </div>

    <div style="display:flex;gap:20px;margin-top:10px">
        <label>
            <input type="radio" name="answer" value="Yes" required>
            Yes
        </label>

        <label>
            <input type="radio" name="answer" value="No" required>
            No
        </label>
    </div>

    <div style="margin-top:15px">
        <button class="btn" type="submit">Submit</button>
    </div>
</form>

<?php } else { ?>

<a class="btn" href="complete_task.php?id=<?php echo (int)$task['id']; ?>">Submit</a>

<?php } ?>
                          <a class="btn ghost" href="<?php echo $tg; ?>" target="_blank" rel="noopener">Support</a>
                        </div>
                      </div>
                    </div>
                  <?php } ?>
                </div>
              </div>

              <!-- TABLE -->
              <div id="tableView" style="display:none">
                <div class="tableWrap">
                  <table>
                    <tr>
                      <th>Task</th>
                      <th>Description</th>
                      <th>Reward</th>
                      <th>Action</th>
                    </tr>
                    <?php foreach($tasks as $task){ ?>
                      <tr>
                        <td><strong><?php echo htmlspecialchars($task['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars($task['description']); ?></td>
                        <td><strong><?php echo htmlspecialchars($task['reward']); ?> USDT</strong></td>
                        <td><a class="btn" href="complete_task.php?id=<?php echo (int)$task['id']; ?>">Complete</a></td>
                      </tr>
                    <?php } ?>
                  </table>
                </div>
              </div>

            <?php } ?>
          <?php ?>
        </section>

        <!-- Right: Activity -->
        <aside class="panel">
          <h3 class="feedTitle">Live Activity</h3>
          <p class="feedSub">Real recent actions from your platform.</p>

          <h4 style="margin:14px 0 8px;font-weight:900">Recent Earnings</h4>
          <?php if(empty($recentEarnings)) { ?><div class="feedItem" style="color:var(--muted);font-weight:700">No earnings yet.</div><?php } ?>
          <?php foreach($recentEarnings as $e){ ?>
            <div class="feedItem">
              <span class="tag earn">EARN</span>
              <strong><?php echo htmlspecialchars($e['fullname']); ?></strong>
              earned <strong><?php echo htmlspecialchars($e['reward']); ?> USDT</strong>
              <div class="time"><?php echo htmlspecialchars($e['completed_at']); ?></div>
            </div>
          <?php } ?>

          <h4 style="margin:14px 0 8px;font-weight:900">Approved Deposits</h4>
          <?php if(empty($recentDeposits)) { ?><div class="feedItem" style="color:var(--muted);font-weight:700">No approved deposits yet.</div><?php } ?>
          <?php foreach($recentDeposits as $d){ ?>
            <div class="feedItem">
              <span class="tag dep">DEPOSIT</span>
              <strong><?php echo htmlspecialchars($d['fullname']); ?></strong>
              deposited <strong><?php echo htmlspecialchars($d['amount']); ?> USDT</strong>
              <div class="time"><?php echo htmlspecialchars($d['created_at']); ?></div>
            </div>
          <?php } ?>

          <h4 style="margin:14px 0 8px;font-weight:900">Approved Withdrawals</h4>
          <?php if(empty($recentWithdrawals)) { ?><div class="feedItem" style="color:var(--muted);font-weight:700">No approved withdrawals yet.</div><?php } ?>
          <?php foreach($recentWithdrawals as $w){ ?>
            <div class="feedItem">
              <span class="tag wd">WITHDRAW</span>
              <strong><?php echo htmlspecialchars($w['fullname']); ?></strong>
              withdrew <strong><?php echo htmlspecialchars($w['amount']); ?> USDT</strong>
              <div class="time"><?php echo htmlspecialchars($w['created_at']); ?></div>
            </div>
          <?php } ?>
        </aside>

      </div>
    </div>
  </main>
</div>

<!-- Toast -->
<div id="toast" class="toast">
  <div class="toastTop">
    <div id="toastType" class="nType earn">EARN</div>
    <div id="toastClose" class="toastClose">✕</div>
  </div>
  <div id="toastText" class="toastText"></div>
  <div id="toastTime" class="toastTime"></div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){

  // ===== Profile dropdown =====
  const profileBtn = document.getElementById("profileBtn");
  const dropdown = document.getElementById("dropdown");

  const bellBtn = document.getElementById("bellBtn");
  const notifDrop = document.getElementById("notifDrop");

  const hamburger = document.getElementById("hamburger");
  const sidebarMobile = document.getElementById("sidebarMobile");
  const overlay = document.getElementById("overlay");

  function closeMobile(){
    if(sidebarMobile) sidebarMobile.classList.remove("open");
    if(overlay) overlay.classList.remove("show");
  }

  if(profileBtn && dropdown){
    profileBtn.addEventListener("click", function(e){
      e.stopPropagation();
      if(notifDrop) notifDrop.classList.remove("show");
      dropdown.classList.toggle("show");
    });
  }

  if(bellBtn && notifDrop){
    bellBtn.addEventListener("click", function(e){
      e.stopPropagation();
      if(dropdown) dropdown.classList.remove("show");
      notifDrop.classList.toggle("show");
    });
  }

  document.addEventListener("click", function(){
    if(dropdown) dropdown.classList.remove("show");
    if(notifDrop) notifDrop.classList.remove("show");
  });

  if(hamburger){
    hamburger.addEventListener("click", function(){
      sidebarMobile.classList.add("open");
      overlay.classList.add("show");
    });
  }

  if(overlay){
    overlay.addEventListener("click", closeMobile);
  }

  // ===== Board / Table Toggle =====
  const boardBtn = document.getElementById("boardBtn");
  const tableBtn = document.getElementById("tableBtn");
  const boardView = document.getElementById("boardView");
  const tableView = document.getElementById("tableView");

  if(boardBtn && tableBtn && boardView && tableView){
    boardBtn.addEventListener("click", function(){
      boardBtn.classList.add("active");
      tableBtn.classList.remove("active");
      boardView.style.display = "block";
      tableView.style.display = "none";
    });

    tableBtn.addEventListener("click", function(){
      tableBtn.classList.add("active");
      boardBtn.classList.remove("active");
      boardView.style.display = "none";
      tableView.style.display = "block";
    });
  }

  // ===== Notifications Toast =====
  const notifs = <?php echo json_encode($notifs, JSON_UNESCAPED_SLASHES); ?>;
  const toast = document.getElementById("toast");
  const toastType = document.getElementById("toastType");
  const toastText = document.getElementById("toastText");
  const toastTime = document.getElementById("toastTime");
  const toastClose = document.getElementById("toastClose");

  function typeClass(t){
    if(t === "DEPOSIT") return "dep";
    if(t === "WITHDRAW") return "wd";
    return "earn";
  }

  let toastIdx = 0;

  function showToast(item){
    if(!item || !toast) return;

    toastType.className = "nType " + typeClass(item.type);
    toastType.textContent = item.type;
    toastText.textContent = item.text;
    toastTime.textContent = item.time;

    toast.style.display = "block";
    setTimeout(() => { toast.style.display = "none"; }, 5000);
  }

  if(toastClose){
    toastClose.addEventListener("click", function(){
      toast.style.display = "none";
    });
  }

  if(notifs.length > 0){
    setInterval(function(){
      showToast(notifs[toastIdx % notifs.length]);
      toastIdx++;
    }, 10000);
  }

  // ===== DARK MODE =====
  const darkToggle = document.getElementById("darkToggle");

  if(darkToggle){
    if(localStorage.getItem("darkMode") === "on"){
      document.body.classList.add("dark");
      darkToggle.textContent = "☀️";
    }

    darkToggle.addEventListener("click", function(){
      document.body.classList.toggle("dark");

      if(document.body.classList.contains("dark")){
        localStorage.setItem("darkMode", "on");
        darkToggle.textContent = "☀️";
      } else {
        localStorage.setItem("darkMode", "off");
        darkToggle.textContent = "🌙";
      }
    });
  }

});
</script>
</body>
</html>