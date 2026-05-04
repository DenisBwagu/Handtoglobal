<?php
session_start();
require 'config.php';
require 'get_setting.php';

// Get Telegram link from settings
$supportLink = get_setting('telegram_link', '<?php echo htmlspecialchars($supportLink); ?>');

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user'];

/* Get user balance */
$stmt = $conn->prepare("SELECT balance FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $amount = (float)$_POST['amount'];
    $wallet = trim($_POST['wallet']);

    /* Minimum withdrawal = 100 */
    if ($amount < 100) {
        die("Minimum withdrawal amount is 100 USDT.");
    }

    if ($amount > $user['balance']) {
        die("Insufficient balance.");
    }

    /* Insert withdrawal request */
    $stmt = $conn->prepare("
        INSERT INTO withdrawals (user_id, amount, wallet_address, status)
        VALUES (?,?,?, 'Pending')
    ");
    $stmt->bind_param("ids", $user_id, $amount, $wallet);
    $stmt->execute();

    echo "Withdrawal request submitted successfully.";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Withdraw</title>
<style>
body{font-family:Arial;background:#f6f7fb;padding:40px}
.box{max-width:400px;margin:auto;background:#fff;padding:25px;border-radius:10px}
input{width:100%;padding:10px;margin:10px 0}
button{background:#007bff;color:#fff;padding:10px;border:none;width:100%}
</style>
</head>
<body>

<div class="box">
<h2>Withdraw Funds</h2>

<form method="POST">
<label>Amount (Min 100 USDT)</label>
<input type="number" step="0.01" name="amount" required>

<label>Wallet Address</label>
<input type="text" name="wallet" required>

<button type="submit">Request Withdrawal</button>
</form>

</div>

</body>
</html>