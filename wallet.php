<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/settings_helpers.php';

// Get Telegram link from settings
$supportLink = get_setting('telegram_link', '<?php echo htmlspecialchars($supportLink); ?>');

requireLogin();

$user = getUserById($_SESSION['user_id']);
$stats = getUserStats($_SESSION['user_id']);

// Get recent transactions
$conn = getConnection();
$stmt = $conn->prepare("SELECT id, user_id, amount, status, created_at FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$transactions = array_map(function ($row) {
    $row['type'] = 'deposit';
    return $row;
}, $stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt = $conn->prepare("SELECT id, user_id, amount, status, created_at FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$withdrawalTransactions = array_map(function ($row) {
    $row['type'] = 'withdrawal';
    return $row;
}, $stmt->fetchAll(PDO::FETCH_ASSOC));

$transactions = array_merge($transactions, $withdrawalTransactions);
usort($transactions, function ($a, $b) {
    return strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01');
});
$transactions = array_slice($transactions, 0, 10);

// Get pending deposits and withdrawals
$stmt = $conn->prepare("SELECT * FROM deposits WHERE user_id = ? AND status = 'Pending'");
$stmt->execute([$_SESSION['user_id']]);
$pendingDeposits = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT * FROM withdrawals WHERE user_id = ? AND status = 'Pending'");
$stmt->execute([$_SESSION['user_id']]);
$pendingWithdrawals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet - GlobalHand</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        <?php include 'includes/theme.php'; ?>
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>
    <div class="layout-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
<nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="starting.php" class="nav-item">
                    <i class="fas fa-tasks"></i>
                    <span>Tasks</span>
                </a>
                <a href="records.php" class="nav-item">
                    <i class="fas fa-history"></i>
                    <span>Records</span>
                </a>
                <a href="wallet.php" class="nav-item active">
                    <i class="fas fa-wallet"></i>
                    <span>Wallet</span>
                </a>
                <a href="deposit.php" class="nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <span>Deposit</span>
                </a>
                <a href="withdraw.php" class="nav-item">
                    <i class="fas fa-minus-circle"></i>
                    <span>Withdraw</span>
                </a>
                <a href="notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Wallet Content -->
            <main class="wallet-content">
                <div class="page-header">
                    <h1>Wallet</h1>
                    <p>Manage your funds and view transaction history</p>
                </div>

                <!-- Balance Card -->
                <div class="balance-card">
                    <div class="balance-header">
                        <h2>Available Balance</h2>
                        <div class="balance-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <div class="balance-amount">
                        <span class="amount"><?php echo formatBalance($user['balance']); ?></span>
                        <small>USDT</small>
                    </div>
                    <div class="balance-actions">
                        <a href="deposit.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Deposit
                        </a>
                        <a href="withdraw.php" class="btn btn-secondary">
                            <i class="fas fa-minus"></i>
                            Withdraw
                        </a>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="quick-stats">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($pendingDeposits); ?></h3>
                            <p>Pending Deposits</p>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($pendingWithdrawals); ?></h3>
                            <p>Pending Withdrawals</p>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatBalance($stats['total_earned']); ?></h3>
                            <p>Total Earned</p>
                        </div>
                    </div>
                </div>

                <!-- Pending Transactions -->
                <?php if (!empty($pendingDeposits) || !empty($pendingWithdrawals)): ?>
                    <div class="pending-section">
                        <h2>Pending Transactions</h2>
                        <div class="pending-list">
                            <?php foreach ($pendingDeposits as $deposit): ?>
                                <div class="pending-item deposit">
                                    <div class="pending-icon">
                                        <i class="fas fa-arrow-up"></i>
                                    </div>
                                    <div class="pending-info">
                                        <h4>Deposit Request</h4>
                                        <p><?php echo formatBalance($deposit['amount']); ?></p>
                                        <small>Submitted: <?php echo date('M j, g:i A', strtotime($deposit['created_at'])); ?></small>
                                    </div>
                                    <div class="pending-status">
                                        <span class="status-badge pending">
                                            <i class="fas fa-clock"></i>
                                            Pending
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php foreach ($pendingWithdrawals as $withdrawal): ?>
                                <div class="pending-item withdrawal">
                                    <div class="pending-icon">
                                        <i class="fas fa-arrow-down"></i>
                                    </div>
                                    <div class="pending-info">
                                        <h4>Withdrawal Request</h4>
                                        <p><?php echo formatBalance($withdrawal['amount']); ?></p>
                                        <small>Submitted: <?php echo date('M j, g:i A', strtotime($withdrawal['created_at'])); ?></small>
                                    </div>
                                    <div class="pending-status">
                                        <span class="status-badge pending">
                                            <i class="fas fa-clock"></i>
                                            Pending
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Transaction History -->
                <div class="transaction-history">
                    <div class="history-header">
                        <h2>Transaction History</h2>
                        <a href="records.php" class="btn btn-outline">View All</a>
                    </div>
                    
                    <?php if (empty($transactions)): ?>
                        <div class="empty-state">
                            <i class="fas fa-exchange-alt"></i>
                            <h3>No transactions yet</h3>
                            <p>Start by making a deposit or completing tasks</p>
                            <div class="empty-actions">
                                <a href="deposit.php" class="btn btn-primary">Make Deposit</a>
                                <a href="starting.php" class="btn btn-secondary">Complete Tasks</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="transaction-list">
                            <?php foreach ($transactions as $transaction): ?>
                                <div class="transaction-item <?php echo $transaction['type']; ?>">
                                    <div class="transaction-icon">
                                        <?php if ($transaction['type'] === 'deposit'): ?>
                                            <i class="fas fa-arrow-up"></i>
                                        <?php else: ?>
                                            <i class="fas fa-arrow-down"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="transaction-info">
                                        <h4>
                                            <?php if ($transaction['type'] === 'deposit'): ?>
                                                Deposit
                                            <?php else: ?>
                                                Withdrawal
                                            <?php endif; ?>
                                        </h4>
                                        <p><?php echo htmlspecialchars($transaction['status']); ?></p>
                                        <small><?php echo date('M j, g:i A', strtotime($transaction['created_at'])); ?></small>
                                    </div>
                                    <div class="transaction-amount">
                                        <span class="amount <?php echo $transaction['type'] === 'deposit' ? 'positive' : 'negative'; ?>">
                                            <?php echo $transaction['type'] === 'deposit' ? '+' : '-'; ?><?php echo formatBalance($transaction['amount']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Level Progress -->
                <div class="wallet-progress">
                    <h2>Level Progress</h2>
                    <div class="progress-info">
                        <div class="current-level">
                            <span class="level-badge level-<?php echo strtolower($user['level']); ?>">
                                <?php echo htmlspecialchars($user['level']); ?>
                            </span>
                            <p>Current Level</p>
                        </div>
                        <div class="next-level">
                            <?php 
                            $levels = ['Bronze', 'Silver', 'Gold', 'Platinum'];
                            $currentLevelIndex = array_search($user['level'], $levels);
                            $nextLevel = $levels[$currentLevelIndex + 1] ?? null;
                            
                            if ($nextLevel):
                                $nextLevelProgress = getLevelProgress($_SESSION['user_id'], $nextLevel);
                                $nextLevelUnlock = constant($nextLevel . '_UNLOCK_AMOUNT');
                            ?>
                                <div class="next-level-info">
                                    <h4>Next: <?php echo $nextLevel; ?></h4>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo ($nextLevelProgress / 40) * 100; ?>%"></div>
                                    </div>
                                    <p><?php echo $nextLevelProgress; ?>/40 tasks completed</p>
                                    <small>Unlock with <?php echo $nextLevelUnlock; ?> USDT deposit</small>
                                </div>
                            <?php else: ?>
                                <div class="max-level">
                                    <h4>Maximum Level</h4>
                                    <p>You've reached the highest level!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
