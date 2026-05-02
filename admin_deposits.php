<?php
session_start();
require 'config.php';
if (empty($_SESSION['admin'])) { header("Location: admin_login.php"); exit(); }

if (isset($_GET['approve'])) {
    $dep_id = (int)$_GET['approve'];

    $stmt = $conn->prepare("SELECT id,user_id,amount,status FROM deposits WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $dep_id);
    $stmt->execute();
    $dep = $stmt->get_result()->fetch_assoc();

    if ($dep && $dep['status'] === 'Pending') {
        $uid = (int)$dep['user_id'];
        $amount = (float)$dep['amount'];

        // Approve deposit
$stmt = $conn->prepare("UPDATE deposits SET status='Approved' WHERE id=?");
$stmt->bind_param("i", $deposit_id);
$stmt->execute();

// Get deposit details
$stmt = $conn->prepare("SELECT user_id, amount FROM deposits WHERE id=?");
$stmt->bind_param("i", $deposit_id);
$stmt->execute();
$deposit = $stmt->get_result()->fetch_assoc();

$user_id = $deposit['user_id'];
$amount  = (float)$deposit['amount'];

// VIP AUTO UNLOCK LOGIC
if ($amount >= 2500 && $amount < 5000) {

    $stmt = $conn->prepare("UPDATE users SET vip1_unlocked=1 WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

}

if ($amount >= 5000 && $amount < 7000) {

    $stmt = $conn->prepare("UPDATE users SET vip2_unlocked=1 WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

}

if ($amount >= 7000) {

    $stmt = $conn->prepare("UPDATE users SET vip3_unlocked=1 WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

}
        }
    }

    header("Location: admin_deposits.php");
    exit();


if (isset($_GET['reject'])) {
    $dep_id = (int)$_GET['reject'];
    $stmt = $conn->prepare("UPDATE deposits SET status='Rejected' WHERE id=? AND status='Pending'");
    $stmt->bind_param("i", $dep_id);
    $stmt->execute();
    header("Location: admin_deposits.php");
    exit();
}

$res = $conn->query("
  SELECT d.id,d.user_id,d.amount,d.created_at,u.email
  FROM deposits d JOIN users u ON u.id=d.user_id
  WHERE d.status='Pending'
  ORDER BY d.id DESC
");
$rows = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Deposits</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Segoe UI;background:#e6f2ff;padding:18px}
    .box{max-width:980px;margin:auto;background:#fff;padding:18px;border-radius:16px;box-shadow:0 10px 25px rgba(0,0,0,.08)}
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid #eee;padding:10px;text-align:left}
    a.btn{display:inline-block;background:#007bff;color:#fff;padding:8px 10px;border-radius:10px;text-decoration:none;font-weight:700;margin-right:6px}
    a.rej{background:#b00020}
  </style>
</head>
<body>
<div class="box">
  <h2>Pending Deposits</h2>
  <p><a href="admin_dashboard.php">Back</a></p>
  <table>
    <tr><th>ID</th><th>User</th><th>Amount</th><th>Date</th><th>Action</th></tr>
    <?php foreach($rows as $r){ ?>
      <tr>
        <td><?php echo (int)$r['id']; ?></td>
        <td><?php echo htmlspecialchars($r['email']); ?> (<?php echo (int)$r['user_id']; ?>)</td>
        <td><?php echo htmlspecialchars($r['amount']); ?></td>
        <td><?php echo htmlspecialchars($r['created_at']); ?></td>
        <td>
          <a class="btn" href="?approve=<?php echo (int)$r['id']; ?>">Approve</a>
          <a class="btn rej" href="?reject=<?php echo (int)$r['id']; ?>">Reject</a>
        </td>
      </tr>
    <?php } ?>
  </table>
</div>
</body>
</html>