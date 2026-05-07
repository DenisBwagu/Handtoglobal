<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/settings_helpers.php';
require_once __DIR__ . '/includes/language_helpers.php';

requireLogin();

$conn = getConnection();
$message = '';
$error = '';
$availableBalance = 0.00;
$minWithdrawal = 0.00;

$userId = (int)($_SESSION['user_id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect('login.php');
}

// Get settings, then apply any per-user limits saved by admin.
$minWithdrawal = (float)get_setting('min_withdrawal_amount', '10.00');
$minWithdrawalLevel = (int)get_setting('min_withdrawal_level', '2');
$userLimits = getUserLimitsForUser($userId, $conn);
if (!empty($userLimits['_exists'])) {
    $minWithdrawal = (float)($userLimits['min_withdrawal_amount'] ?? $minWithdrawal);
    $minWithdrawalLevelValue = $userLimits['min_withdrawal_level'] ?? $minWithdrawalLevel;
    $minWithdrawalLevel = is_numeric($minWithdrawalLevelValue) ? (int)$minWithdrawalLevelValue : $minWithdrawalLevelValue;
}
$availableBalance = (float)($user['balance'] ?? 0);
$levelRanks = [
    'Bronze' => 1,
    'Sliver' => 2,
    'Silver' => 2,
    'Gold' => 3,
    'VIP 1' => 4,
    'VIP' => 4,
    'Platinum' => 4,
];
$effectiveMinWithdrawalLevel = is_numeric($minWithdrawalLevel)
    ? (int)$minWithdrawalLevel
    : ($levelRanks[normalizeLevelName($minWithdrawalLevel)] ?? 1);
$effectiveMinWithdrawalLevel = ($effectiveMinWithdrawalLevel >= 1 && $effectiveMinWithdrawalLevel <= 4) ? $effectiveMinWithdrawalLevel : 1;
$userLevelRank = $levelRanks[normalizeLevelName($user['level'] ?? 'Bronze')] ?? 1;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $coinAsset = trim($_POST['coin_asset'] ?? 'USDT');
    $network = trim($_POST['network'] ?? 'Tron (TRC20)');
    $walletAddress = trim($_POST['wallet_address'] ?? '');
    $memoTag = trim($_POST['memo_tag'] ?? '');
    $recipientName = trim($_POST['recipient_name'] ?? '');
    
    // Validation
    if ($amount <= 0) {
        $error = __t('amount_required_greater_zero', 'Amount is required and must be greater than 0.') . '';
    } elseif ($amount > $availableBalance) {
        $error = __t('amount_exceed_balance', 'Amount cannot exceed your available balance.') . '';
    } elseif ($amount < $minWithdrawal) {
        $error = __t('minimum_withdrawal_amount', 'Minimum withdrawal amount is $') . number_format($minWithdrawal, 2) . '. Please enter a higher amount.';
    } elseif ($walletAddress === '') {
        $error = __t('wallet_address_required', 'Wallet address is required.') . '';
    } elseif ($coinAsset === '') {
        $error = __t('coin_asset_required', 'Coin asset is required.') . '';
    } elseif ($network === '') {
        $error = __t('network_required', 'Network is required.') . '';
    } elseif ($userLevelRank < $effectiveMinWithdrawalLevel) {
        $error = __t('must_reach_level', 'You must reach at least level') . ' ' . $effectiveMinWithdrawalLevel . ' ' . __t('to_request_withdrawals', 'to request withdrawals.') . '';
    } else {
        // Insert into withdrawals table
        $stmt = $conn->prepare("
            INSERT INTO withdrawals (user_id, amount, coin_asset, network, wallet_address, memo_tag, recipient_name, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
        ");
        $result = $stmt->execute([$userId, $amount, $coinAsset, $network, $walletAddress, $memoTag ?: null, $recipientName ?: null]);
        
        if ($result) {
            // Redirect to withdrawals page to show the new request
            redirect('withdrawals.php');
        } else {
            $error = __t('failed_submit_withdrawal', 'Failed to submit withdrawal request. Please try again.') . '';
        }
    }
}

$supportLink = get_telegram_link();
$siteName = get_site_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('request_withdrawal', 'Request Withdrawal'); ?> - <?php echo htmlspecialchars($siteName); ?></title>
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
        .panel { background: var(--card-bg, #fff); border: 1px solid var(--card-border, #e5e7eb); border-radius: 12px; box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,.05)); padding: 32px; max-width: 600px; margin: 0 auto; }
        .panel h2 { margin: 0 0 24px; font-size: 20px; font-weight: 800; text-align: center; }
        .balance-display { background: #e0f2fe; border: 1px solid #0ea5e9; border-radius: 8px; padding: 16px; margin-bottom: 20px; text-align: center; }
        .balance-display .label { font-size: 14px; color: #0369a1; font-weight: 600; margin-bottom: 4px; }
        .balance-display .amount { font-size: 24px; font-weight: 800; color: #0c4a6e; }
        .info-row { display: flex; gap: 16px; margin-bottom: 20px; }
        .info-box { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center; }
        .info-box .label { font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px; }
        .info-box .value { font-size: 16px; font-weight: 800; color: #1e293b; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-size: 14px; color: var(--text-secondary, #374151); font-weight: 600; }
        input, select { width: 100%; border: 1px solid var(--input-border, #d1d5db); border-radius: 8px; padding: 12px 16px; font-size: 14px; background: var(--input-bg, #fff); color: var(--input-color, #111827); }
        input:focus, select:focus { outline: none; border-color: var(--primary, #0d6efd); box-shadow: 0 0 0 3px rgba(13,110,253,.12); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 8px; padding: 12px 24px; font-weight: 800; cursor: pointer; text-decoration: none; width: 100%; font-size: 16px; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: var(--hover, #f3f4f6); color: var(--text-primary, #111827); border: 1px solid var(--border, #e5e7eb); }
        .warning-box { background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
        .warning-box .title { font-weight: 800; color: #92400e; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .warning-box .text { font-size: 14px; color: #78350f; line-height: 1.5; }
        .notice { padding: 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 700; }
        .notice.success { background: #dcfce7; color: #166534; }
        .notice.error { background: #fee2e2; color: #991b1b; }
        .actions { display: flex; gap: 12px; margin-top: 24px; }
        .actions .btn { width: auto; }
        @media (max-width: 960px) { .main-content { margin-left: 0; } }
        @media (max-width: 640px) { .content-area { padding: 16px; } .panel { padding: 20px; } .info-row { flex-direction: column; } .actions { flex-direction: column; } }
    </style>
</head>
<body>
    <?php require 'includes/sidebar.php'; ?>
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main class="main-content">
        <div class="content-area">
            <div class="page-header">
                <div>
                    <h1><?php echo __t('request_withdrawal', 'Request Withdrawal'); ?></h1>
                    <p><?php echo __t('submit_withdrawal_request', 'Submit a withdrawal request to your preferred wallet.'); ?></p>
                </div>
                <div class="actions" style="margin-top:0">
                    <a class="btn btn-secondary" href="withdrawals.php"><i class="fas fa-arrow-left"></i> <?php echo __t('back_to_withdrawals', 'Back to Withdrawals'); ?></a>
                    <a class="btn btn-support" href="<?php echo htmlspecialchars($supportLink); ?>" target="_blank" rel="noopener"><i class="fas fa-headset"></i> <?php echo __t('support', 'Support'); ?></a>
                </div>
            </div>

            <?php if (!empty($message)): ?><div class="notice success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="notice error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <section class="panel">
                <h2><?php echo __t('request_withdrawal', 'Request Withdrawal'); ?></h2>
                
                <div class="balance-display">
                    <div class="label"><?php echo __t('available_balance', 'Available Balance'); ?></div>
                    <div class="amount">$<?php echo number_format($availableBalance, 2); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-box">
                        <div class="label"><?php echo __t('min_amount', 'Min Amount'); ?></div>
                        <div class="value">$<?php echo number_format($minWithdrawal, 2); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="label"><?php echo __t('amount_usd', 'Amount USD'); ?></div>
                        <div class="value">-</div>
                    </div>
                </div>

                <form method="post">
                    <div class="form-group">
                        <label for="amount"><?php echo __t('amount_usd', 'Amount USD'); ?></label>
                        <input type="number" id="amount" name="amount" step="0.01" min="<?php echo $minWithdrawal; ?>" max="<?php echo $availableBalance; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="coin_asset"><?php echo __t('coin_asset', 'Coin Asset'); ?></label>
                        <select id="coin_asset" name="coin_asset" required>
                            <option value="USDT" selected>USDT</option>
                            <option value="BTC">BTC</option>
                            <option value="ETH">ETH</option>
                            <option value="USDC">USDC</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="network"><?php echo __t('network', 'Network'); ?></label>
                        <select id="network" name="network" required>
                            <option value="Tron (TRC20)" selected>Tron (TRC20)</option>
                            <option value="Ethereum (ERC20)">Ethereum (ERC20)</option>
                            <option value="Binance Smart Chain (BEP20)">Binance Smart Chain (BEP20)</option>
                            <option value="Bitcoin">Bitcoin</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="wallet_address"><?php echo __t('wallet_address', 'Wallet Address'); ?></label>
                        <input type="text" id="wallet_address" name="wallet_address" required placeholder="Enter your wallet address">
                    </div>

                    <div class="form-group">
                        <label for="memo_tag"><?php echo __t('memo_tag_optional', 'Memo Tag (Optional)'); ?></label>
                        <input type="text" id="memo_tag" name="memo_tag" placeholder="Enter memo tag if required">
                    </div>

                    <div class="form-group">
                        <label for="recipient_name"><?php echo __t('recipient_name_optional', 'Recipient Name (Optional)'); ?></label>
                        <input type="text" id="recipient_name" name="recipient_name" placeholder="Enter recipient name">
                    </div>

                    <div class="warning-box">
                        <div class="title">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo __t('warning', 'Warning'); ?>
                        </div>
                        <div class="text">
                            Please double-check your wallet address before submitting. Withdrawal requests are processed manually and cannot be cancelled once submitted. Make sure your wallet supports the selected network.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        <?php echo __t('request_withdrawal', 'Request Withdrawal'); ?>
                    </button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
