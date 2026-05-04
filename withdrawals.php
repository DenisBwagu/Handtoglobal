<?php
require_once 'config.php';
require_once 'get_setting.php';
require_once 'get_translation.php';

requireLogin();

$conn = getConnection();
$userId = (int)$_SESSION['user_id'];
$message = '';
$error = '';

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect('login.php');
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'POST') {
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
        $stmt->execute([$userId, $amount, $asset ?: 'USDT', $network ?: 'TRC20', $walletAddress, $memoTag ?: null, $recipientName ?: null]);
        $message = 'Withdrawal request submitted successfully.';
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
}

$stmt = $conn->prepare("
    SELECT *
    FROM withdrawals
    WHERE user_id = ?
    ORDER BY created_at DESC, id DESC
");
$stmt->execute([$userId]);
$withdrawals = $stmt->fetchAll();

$summaryStmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_count,
        COALESCE(SUM(CASE WHEN status = 'Approved' THEN amount ELSE 0 END), 0) AS approved_amount
    FROM withdrawals
    WHERE user_id = ?
");
$summaryStmt->execute([$userId]);
$summary = $summaryStmt->fetch();

$minBalance = (float)($user['min_balance'] ?? get_setting('min_balance', '0'));
$withdrawableBalance = max(0, (float)$user['balance'] - $minBalance);
$supportLink = getSupportLink();
$siteName = get_setting('site_name', 'HandToGlobal');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawals - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: var(--body-bg, #f4f6f9); color: var(--text-primary, #111827); }
        .main-content { margin-left: 260px; min-height: 100vh; padding-top: 56px; transition: margin-left .3s ease; }
        .main-content.expanded { margin-left: 0; }
        .content-area { padding: 24px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 22px; }
        .page-header h1 { margin: 0; font-size: 28px; font-weight: 800; }
        .page-header p { margin: 6px 0 0; color: var(--text-secondary, #6b7280); }
        .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
        .stat-card, .panel { background: var(--card-bg, #fff); border: 1px solid var(--card-border, #e5e7eb); border-radius: 8px; box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,.05)); }
        .stat-card { padding: 18px; }
        .stat-label { color: var(--text-secondary, #6b7280); font-size: 12px; text-transform: uppercase; font-weight: 800; }
        .stat-value { margin-top: 8px; font-size: 24px; font-weight: 800; color: var(--primary, #0d6efd); }
        .panel { padding: 22px; margin-bottom: 20px; }
        .panel h2 { margin: 0 0 18px; font-size: 18px; font-weight: 800; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .form-group.full { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 7px; font-size: 13px; color: var(--text-secondary, #374151); font-weight: 800; }
        input, select { width: 100%; border: 1px solid var(--input-border, #d1d5db); border-radius: 8px; padding: 12px 13px; font-size: 14px; background: var(--input-bg, #fff); color: var(--input-color, #111827); }
        input:focus, select:focus { outline: none; border-color: var(--primary, #0d6efd); box-shadow: 0 0 0 3px rgba(13,110,253,.12); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 8px; padding: 11px 16px; font-weight: 800; cursor: pointer; text-decoration: none; }
        .btn-primary { background: var(--primary, #0d6efd); color: #fff; }
        .btn-secondary { background: var(--hover, #f3f4f6); color: var(--text-primary, #111827); border: 1px solid var(--border, #e5e7eb); }
        .btn-support { background: #0ea5e9; color: #fff; }
        .notice { padding: 13px 15px; border-radius: 8px; margin-bottom: 18px; font-weight: 700; }
        .notice.success { background: #dcfce7; color: #166534; }
        .notice.error { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 13px 12px; border-bottom: 1px solid var(--table-border, #e5e7eb); text-align: left; vertical-align: top; }
        th { font-size: 12px; color: var(--text-secondary, #6b7280); text-transform: uppercase; letter-spacing: .03em; background: var(--table-header-bg, #f9fafb); }
        .table-wrap { overflow-x: auto; }
        .badge { display: inline-flex; border-radius: 999px; padding: 5px 9px; font-size: 12px; font-weight: 800; }
        .badge.pending { background: #fef3c7; color: #92400e; }
        .badge.approved { background: #dcfce7; color: #166534; }
        .badge.rejected { background: #fee2e2; color: #991b1b; }
        .wallet { max-width: 260px; word-break: break-all; font-family: Consolas, monospace; font-size: 13px; }
        .muted { color: var(--text-secondary, #6b7280); }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
        @media (max-width: 960px) { .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .main-content { margin-left: 0; } }
        @media (max-width: 640px) { .content-area { padding: 16px; } .stats-grid, .form-grid { grid-template-columns: 1fr; } .form-group.full { grid-column: auto; } .page-header { flex-direction: column; } }
    </style>
</head>
<body>
    <?php require 'includes/sidebar.php'; ?>
    <?php require 'includes/topbar.php'; ?>

    <main class="main-content">
        <div class="content-area">
            <div class="page-header">
                <div>
                    <h1>Withdrawals</h1>
                    <p>Requests are saved as pending and stay visible here after approval or rejection.</p>
                </div>
                <div class="actions" style="margin-top:0">
                    <a class="btn btn-secondary" href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
                    <a class="btn btn-support" href="<?php echo htmlspecialchars($supportLink); ?>" target="_blank" rel="noopener"><i class="fas fa-headset"></i> Support</a>
                </div>
            </div>

            <?php if ($message): ?><div class="notice success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="notice error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <section class="stats-grid">
                <div class="stat-card"><div class="stat-label">Balance</div><div class="stat-value">$<?php echo number_format((float)$user['balance'], 2); ?></div></div>
                <div class="stat-card"><div class="stat-label">Withdrawable</div><div class="stat-value">$<?php echo number_format($withdrawableBalance, 2); ?></div></div>
                <div class="stat-card"><div class="stat-label">Pending</div><div class="stat-value"><?php echo (int)($summary['pending_count'] ?? 0); ?></div></div>
                <div class="stat-card"><div class="stat-label">Approved Paid</div><div class="stat-value">$<?php echo number_format((float)($summary['approved_amount'] ?? 0), 2); ?></div></div>
            </section>

            <section class="panel">
                <h2>Request Withdrawal</h2>
                <form method="post" class="form-grid">
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" step="0.01" min="0" name="amount" required>
                    </div>
                    <div class="form-group">
                        <label>Asset</label>
                        <select name="asset">
                            <option>USDT</option>
                            <option>BTC</option>
                            <option>ETH</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Network</label>
                        <select name="network">
                            <option>TRC20</option>
                            <option>ERC20</option>
                            <option>BEP20</option>
                            <option>BTC</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Recipient optional</label>
                        <input type="text" name="recipient_name">
                    </div>
                    <div class="form-group full">
                        <label>Wallet Address</label>
                        <input type="text" name="wallet_address" required>
                    </div>
                    <div class="form-group full">
                        <label>Memo / Tag optional</label>
                        <input type="text" name="memo_tag">
                    </div>
                    <div class="form-group full actions">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i> Submit Withdrawal</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <h2>Withdrawal History</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Asset / Network</th>
                                <th>Wallet</th>
                                <th>Status</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($withdrawals as $withdrawal): ?>
                                <?php $statusClass = strtolower(preg_replace('/[^a-z]+/i', '', $withdrawal['status'] ?? 'Pending')); ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars(date('M j, Y', strtotime($withdrawal['created_at']))); ?>
                                        <div class="muted"><?php echo htmlspecialchars(date('g:i A', strtotime($withdrawal['created_at']))); ?></div>
                                    </td>
                                    <td><strong>$<?php echo number_format((float)$withdrawal['amount'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars(($withdrawal['asset'] ?? 'USDT') . ' / ' . ($withdrawal['network'] ?? 'TRC20')); ?></td>
                                    <td><div class="wallet"><?php echo htmlspecialchars($withdrawal['wallet_address'] ?? ''); ?></div></td>
                                    <td><span class="badge <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($withdrawal['status'] ?? 'Pending'); ?></span></td>
                                    <td><?php echo htmlspecialchars($withdrawal['admin_note'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$withdrawals): ?>
                                <tr><td colspan="6" class="muted">No withdrawals yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
