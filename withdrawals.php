<?php
require_once 'config.php';
require_once 'get_setting.php';

requireLogin();

$conn = getConnection();
$userId = $_SESSION['user_id'];
$message = '';
$error = '';

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $asset = trim($_POST['asset'] ?? 'USDT');
    $network = trim($_POST['network'] ?? 'TRC20');
    $walletAddress = trim($_POST['wallet_address'] ?? '');
    $memoTag = trim($_POST['memo_tag'] ?? '');
    $recipientName = trim($_POST['recipient_name'] ?? '');
    $minWithdrawal = (float)get_setting('min_withdrawal_amount', '0');
    $minBalance = (float)($user['min_balance'] ?? get_setting('min_balance', '0'));
    $withdrawableBalance = max(0, (float)$user['balance'] - $minBalance);

    if ($amount <= 0) {
        $error = 'Enter a valid withdrawal amount.';
    } elseif ($amount < $minWithdrawal) {
        $error = 'Minimum withdrawal amount is $' . number_format($minWithdrawal, 2) . '.';
    } elseif ($amount > $withdrawableBalance) {
        $error = 'Insufficient withdrawable balance.';
    } elseif ($walletAddress === '') {
        $error = 'Wallet address is required.';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO withdrawals (user_id, amount, asset, network, wallet_address, memo_tag, recipient_name, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$userId, $amount, $asset, $network, $walletAddress, $memoTag ?: null, $recipientName ?: null]);
        $message = 'Withdrawal request submitted successfully.';
    }
}

$stmt = $conn->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC, id DESC");
$stmt->execute([$userId]);
$withdrawals = $stmt->fetchAll();

$supportLink = getSupportLink();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawals - <?php echo htmlspecialchars(get_setting('site_name', 'HandToGlobal')); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: Inter, Arial, sans-serif; background: #f5f7fb; color: #111827; }
        .page { max-width: 1100px; margin: 0 auto; padding: 28px; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; }
        .card { background: #fff; border-radius: 14px; padding: 22px; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        label { display: block; font-size: 13px; color: #374151; font-weight: 700; margin-bottom: 6px; }
        input, select { width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 10px; padding: 12px; font-size: 14px; }
        .full { grid-column: 1 / -1; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 10px; padding: 12px 18px; font-weight: 800; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .btn-support { background: #0ea5e9; color: #fff; }
        .notice { padding: 12px 14px; border-radius: 10px; margin-bottom: 16px; font-weight: 700; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e5e7eb; font-size: 14px; vertical-align: top; }
        th { color: #6b7280; font-size: 12px; text-transform: uppercase; }
        .badge { padding: 5px 9px; border-radius: 999px; font-size: 12px; font-weight: 800; }
        .pending { background: #fef3c7; color: #92400e; }
        .approved { background: #dcfce7; color: #166534; }
        .rejected { background: #fee2e2; color: #991b1b; }
        @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } .top { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body>
    <main class="page">
        <div class="top">
            <div>
                <h1>Withdrawals</h1>
                <p>Balance: <strong>$<?php echo number_format((float)$user['balance'], 2); ?></strong></p>
            </div>
            <div>
                <a class="btn btn-secondary" href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
                <a class="btn btn-support" href="<?php echo htmlspecialchars($supportLink); ?>"><i class="fas fa-headset"></i> Support</a>
            </div>
        </div>

        <section class="card">
            <h2>Request Withdrawal</h2>
            <?php if ($message): ?><div class="notice success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="notice error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="post" class="grid">
                <div><label>Amount</label><input type="number" step="0.01" name="amount" required></div>
                <div><label>Asset</label><select name="asset"><option>USDT</option><option>BTC</option><option>ETH</option></select></div>
                <div><label>Network</label><select name="network"><option>TRC20</option><option>ERC20</option><option>BEP20</option><option>BTC</option></select></div>
                <div><label>Recipient optional</label><input type="text" name="recipient_name"></div>
                <div class="full"><label>Wallet address</label><input type="text" name="wallet_address" required></div>
                <div class="full"><label>Memo / tag optional</label><input type="text" name="memo_tag"></div>
                <div class="full"><button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i> Submit Withdrawal</button></div>
            </form>
        </section>

        <section class="card">
            <h2>Withdrawal History</h2>
            <table>
                <thead><tr><th>Date</th><th>Amount</th><th>Asset / Network</th><th>Wallet</th><th>Status</th><th>Note</th></tr></thead>
                <tbody>
                    <?php foreach ($withdrawals as $withdrawal): ?>
                        <?php $statusClass = strtolower($withdrawal['status']); ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($withdrawal['created_at']))); ?></td>
                            <td>$<?php echo number_format((float)$withdrawal['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars(($withdrawal['asset'] ?? 'USDT') . ' / ' . ($withdrawal['network'] ?? 'TRC20')); ?></td>
                            <td><?php echo htmlspecialchars($withdrawal['wallet_address']); ?></td>
                            <td><span class="badge <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($withdrawal['status']); ?></span></td>
                            <td><?php echo htmlspecialchars($withdrawal['admin_note'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$withdrawals): ?>
                        <tr><td colspan="6">No withdrawals yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
