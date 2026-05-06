<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['admin'])) { header("Location: login.php"); exit(); }

if (isset($_GET['approve'])) {
    $wid = (int)$_GET['approve'];

    $stmt = $conn->prepare("SELECT id,user_id,amount,status FROM withdrawals WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $wid);
    $stmt->execute();
    $w = $stmt->get_result()->fetch_assoc();

    if ($w && $w['status'] === 'Pending') {
        $uid = (int)$w['user_id'];
        $amt = (float)$w['amount'];

        // check balance
        $stmt = $conn->prepare("SELECT balance FROM users WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $bal = (float)$stmt->get_result()->fetch_assoc()['balance'];

        if ($bal >= $amt) {
            $stmt = $conn->prepare("UPDATE withdrawals SET status='Approved' WHERE id=?");
            $stmt->bind_param("i", $wid);
            $stmt->execute();

            $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id=?");
            $stmt->bind_param("di", $amt, $uid);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("UPDATE withdrawals SET status='Rejected' WHERE id=?");
            $stmt->bind_param("i", $wid);
            $stmt->execute();
        }
    }

    header("Location: admin_withdrawals.php");
    exit();
}

if (isset($_GET['reject'])) {
    $wid = (int)$_GET['reject'];
    $stmt = $conn->prepare("UPDATE withdrawals SET status='Rejected' WHERE id=? AND status='Pending'");
    $stmt->bind_param("i", $wid);
    $stmt->execute();
    header("Location: admin_withdrawals.php");
    exit();
}

$res = $conn->query("
  SELECT w.id,w.user_id,w.wallet_address,w.amount,w.created_at,u.email
  FROM withdrawals w JOIN users u ON u.id=w.user_id
  WHERE w.status='Pending'
  ORDER BY w.id DESC
");
$rows = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Withdrawals</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Segoe UI;background:#e6f2ff;padding:18px}
    .box{max-width:1050px;margin:auto;background:#fff;padding:18px;border-radius:16px;box-shadow:0 10px 25px rgba(0,0,0,.08)}
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid #eee;padding:10px;text-align:left}
    a.btn{display:inline-block;background:#007bff;color:#fff;padding:8px 10px;border-radius:10px;text-decoration:none;font-weight:700;margin-right:6px}
    a.rej{background:#b00020}
  </style>
</head>
<body>
<div class="box">
  <h2>Pending Withdrawals</h2>
  <p><a href="admin_dashboard.php">Back</a></p>
  <table>
    <tr><th>ID</th><th>User</th><th>Amount</th><th>Wallet</th><th>Date</th><th>Action</th></tr>
    <?php foreach($rows as $r){ ?>
      <tr>
        <td><?php echo (int)$r['id']; ?></td>
        <td><?php echo htmlspecialchars($r['email']); ?> (<?php echo (int)$r['user_id']; ?>)</td>
        <td><?php echo htmlspecialchars($r['amount']); ?></td>
        <td><?php echo htmlspecialchars($r['wallet_address']); ?></td>
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