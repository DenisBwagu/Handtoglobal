<?php
session_start();
require_once __DIR__ . '/config.php';
require 'get_setting.php';

// Get Telegram link from settings
$supportLink = get_setting('telegram_link', '<?php echo htmlspecialchars($supportLink); ?>');

requireLogin();

$user = getUserById($_SESSION['user_id']);
$success = '';
$error = '';

// Handle deposit request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    
    if ($amount <= 0) {
        $error = 'Please enter a valid amount';
    } elseif ($amount < 10) {
        $error = 'Minimum deposit amount is 10 USDT';
    } else {
        $conn = getConnection();
        $stmt = $conn->prepare("INSERT INTO deposits (user_id, amount, status) VALUES (?, ?, 'Pending')");
        if ($stmt->execute([$_SESSION['user_id'], $amount])) {
            $success = 'Deposit request submitted successfully! Your deposit will be reviewed and approved shortly.';
            createNotification($_SESSION['user_id'], 'Deposit Request', "Your deposit request of {$amount} USDT has been submitted and is pending approval.");
        } else {
            $error = 'Failed to submit deposit request. Please try again.';
        }
    }
}

// Get recent deposits
$conn = getConnection();
$stmt = $conn->prepare("SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recentDeposits = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit - GlobalHand</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        <?php include 'includes/theme.php'; ?>
    </style>
</head>
<body>
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
                <a href="wallet.php" class="nav-item">
                    <i class="fas fa-wallet"></i>
                    <span>Wallet</span>
                </a>
                <a href="deposit.php" class="nav-item active">
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
            <!-- Top Bar -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search...">
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="notification-dropdown">
                        <button class="notification-btn">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge"><?php echo count(getUnreadNotifications($_SESSION['user_id'])); ?></span>
                        </button>
                    </div>
                    <div class="profile-dropdown">
                        <button class="profile-btn">
                            <div class="avatar">
                                <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                            </div>
                            <div class="profile-info">
                                <span class="profile-name"><?php echo htmlspecialchars($user['fullname']); ?></span>
                                <span class="profile-level"><?php echo htmlspecialchars($user['level']); ?></span>
                            </div>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Deposit Content -->
            <main class="deposit-content">
                <div class="page-header">
                    <h1>Deposit Funds</h1>
                    <p>Add USDT to your account to unlock new levels and increase your earning potential</p>
                </div>

                <!-- Current Balance -->
                <div class="balance-display">
                    <div class="balance-info">
                        <h3>Current Balance</h3>
                        <span class="balance-amount"><?php echo formatBalance($user['balance']); ?></span>
                    </div>
                </div>

                <!-- Deposit Form -->
                <div class="deposit-form">
                    <h2>Make a Deposit</h2>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label for="amount">Deposit Amount (USDT)</label>
                            <div class="input-group">
                                <i class="fas fa-dollar-sign"></i>
                                <input type="number" id="amount" name="amount" 
                                       placeholder="Enter amount" 
                                       min="10" 
                                       step="0.01" 
                                       required>
                            </div>
                            <small>Minimum deposit: 10 USDT</small>
                        </div>

                        <div class="quick-amounts">
                            <p>Quick amounts:</p>
                            <div class="amount-buttons">
                                <button type="button" class="amount-btn" data-amount="50">50 USDT</button>
                                <button type="button" class="amount-btn" data-amount="100">100 USDT</button>
                                <button type="button" class="amount-btn" data-amount="150">150 USDT</button>
                                <button type="button" class="amount-btn" data-amount="250">250 USDT</button>
                                <button type="button" class="amount-btn" data-amount="500">500 USDT</button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i>
                            Submit Deposit Request
                        </button>
                    </form>
                </div>

                <!-- Level Unlock Information -->
                <div class="unlock-info">
                    <h2>Unlock New Levels</h2>
                    <p>Deposit funds to unlock higher levels with better rewards:</p>
                    
                    <div class="level-requirements">
                        <div class="level-req">
                            <div class="level-badge level-bronze">Bronze</div>
                            <div class="req-info">
                                <span class="req-amount">100 USDT</span>
                                <small>1.8 USDT per task</small>
                            </div>
                        </div>
                        <div class="level-req">
                            <div class="level-badge level-silver">Silver</div>
                            <div class="req-info">
                                <span class="req-amount">150 USDT</span>
                                <small>2.5 USDT per task</small>
                            </div>
                        </div>
                        <div class="level-req">
                            <div class="level-badge level-gold">Gold</div>
                            <div class="req-info">
                                <span class="req-amount">250 USDT</span>
                                <small>3.5 USDT per task</small>
                            </div>
                        </div>
                        <div class="level-req">
                            <div class="level-badge level-platinum">Platinum</div>
                            <div class="req-info">
                                <span class="req-amount">500 USDT</span>
                                <small>5.0 USDT per task</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Deposits -->
                <?php if (!empty($recentDeposits)): ?>
                    <div class="recent-deposits">
                        <h2>Recent Deposits</h2>
                        <div class="deposit-list">
                            <?php foreach ($recentDeposits as $deposit): ?>
                                <div class="deposit-item">
                                    <div class="deposit-icon">
                                        <i class="fas fa-arrow-up"></i>
                                    </div>
                                    <div class="deposit-info">
                                        <h4><?php echo formatBalance($deposit['amount']); ?></h4>
                                        <p><?php echo htmlspecialchars($deposit['status']); ?></p>
                                        <small><?php echo date('M j, g:i A', strtotime($deposit['created_at'])); ?></small>
                                    </div>
                                    <div class="deposit-status">
                                        <span class="status-badge <?php echo strtolower($deposit['status']); ?>">
                                            <?php echo htmlspecialchars($deposit['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Support Information -->
                <div class="support-info">
                    <h2>Need Help?</h2>
                    <p>For deposit assistance or questions, contact our support team:</p>
                    <a href="<?php echo htmlspecialchars($supportLink); ?>" target="_blank" class="btn btn-outline">
                        <i class="fab fa-telegram"></i>
                        Contact Support
                    </a>
                </div>
            </main>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        // Quick amount buttons
        document.querySelectorAll('.amount-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('amount').value = this.dataset.amount;
            });
        });
    </script>
</body>
</html>