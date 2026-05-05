<?php
require_once 'config.php';
require_once 'includes/settings_helpers.php';
require_once 'includes/language_helpers.php';

requireLogin();

// Hide balance card from Withdrawals page
$hideBalanceCard = true;

$conn = getConnection();
$userId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect('login.php');
}

// Get withdrawal history from database
$stmt = $conn->prepare("
    SELECT 
        id,
        amount,
        COALESCE(coin_asset, asset, 'USDT') as coin_asset,
        network,
        wallet_address,
        memo_tag,
        status,
        note,
        created_at
    FROM withdrawals
    WHERE user_id = ?
    ORDER BY created_at DESC, id DESC
");
$stmt->execute([$userId]);
$withdrawals = $stmt->fetchAll();

$supportLink = get_telegram_link();
$siteName = get_site_name();
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
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main class="main-content">
        <div class="content-area">
            <div class="page-header">
                <div>
                    <h1><?php echo __t('withdrawals', 'Withdrawals'); ?></h1>
                    <p><?php echo __t('view_withdrawal_requests_status', 'View your withdrawal requests and their approval status.'); ?></p>
                </div>
                <div class="actions" style="margin-top:0">
                    <a class="btn btn-primary" href="request_withdrawal.php"><i class="fas fa-plus"></i> <?php echo __t('request_withdrawal', 'Request Withdrawal'); ?></a>
                    <a class="btn btn-secondary" href="dashboard.php"><i class="fas fa-arrow-left"></i> <?php echo __t('dashboard', 'Dashboard'); ?></a>
                    <a class="btn btn-support" href="<?php echo htmlspecialchars($supportLink); ?>" target="_blank" rel="noopener"><i class="fas fa-headset"></i> <?php echo __t('support', 'Support'); ?></a>
                </div>
            </div>

            <section class="panel">
                <h2><?php echo __t('withdrawal_history', 'Withdrawal History'); ?></h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><?php echo __t('amount', 'AMOUNT'); ?></th>
                                <th><?php echo __t('asset_network', 'ASSET/NETWORK'); ?></th>
                                <th><?php echo __t('wallet', 'WALLET'); ?></th>
                                <th><?php echo __t('memo', 'MEMO'); ?></th>
                                <th><?php echo __t('status', 'STATUS'); ?></th>
                                <th><?php echo __t('note', 'NOTE'); ?></th>
                                <th><?php echo __t('date', 'DATE'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($withdrawals as $withdrawal): ?>
                                <?php 
                                $statusClass = strtolower($withdrawal['status'] ?? 'pending');
                                if ($statusClass === 'pending') {
                                    $badgeClass = 'pending';
                                } elseif ($statusClass === 'approved') {
                                    $badgeClass = 'approved';
                                } elseif ($statusClass === 'rejected') {
                                    $badgeClass = 'rejected';
                                } else {
                                    $badgeClass = 'pending';
                                }
                                ?>
                                <tr>
                                    <td><strong>$<?php echo number_format((float)$withdrawal['amount'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($withdrawal['coin_asset'] ?? 'USDT'); ?> / <?php echo htmlspecialchars($withdrawal['network'] ?? 'TRC20'); ?></td>
                                    <td><div class="wallet"><?php echo htmlspecialchars($withdrawal['wallet_address'] ?? ''); ?></div></td>
                                    <td><?php echo htmlspecialchars($withdrawal['memo_tag'] ?? ''); ?></td>
                                    <td><span class="badge <?php echo htmlspecialchars($badgeClass); ?>"><?php echo htmlspecialchars($withdrawal['status'] ?? 'Pending'); ?></span></td>
                                    <td><?php echo htmlspecialchars($withdrawal['note'] ?? ''); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars(date('M j, Y', strtotime($withdrawal['created_at']))); ?>
                                        <div class="muted"><?php echo htmlspecialchars(date('g:i A', strtotime($withdrawal['created_at']))); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$withdrawals): ?>
                                <tr><td colspan="7" class="muted">No withdrawals yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
