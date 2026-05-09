<?php
require_once '../config.php';
require_once '../includes/settings_helpers.php';
require_once '../includes/admin_helpers.php';
require_once '../includes/admin_helpers.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();
$adminId = $_SESSION['admin_id'] ?? $_SESSION['admin'] ?? null;

// Get withdrawal ID
$withdrawalId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($withdrawalId === 0) {
    redirect('withdrawals.php');
}

// Get withdrawal details
$stmt = $conn->prepare("
    SELECT w.*, u.fullname, u.email, u.balance as current_balance
    FROM withdrawals w 
    JOIN users u ON w.user_id = u.id 
    WHERE w.id = ?
");
$stmt->execute([$withdrawalId]);
$withdrawal = $stmt->fetch();

if (!$withdrawal) {
    redirect('withdrawals.php');
}

// Get user withdrawal statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_withdrawals,
        SUM(CASE WHEN status = 'Approved' THEN amount ELSE 0 END) as approved_amount,
        COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved_count
    FROM withdrawals 
    WHERE user_id = ?
");
$stmt->execute([$withdrawal['user_id']]);
$userStats = $stmt->fetch();

// Handle withdrawal actions
$msg = "";
$error = "";

if (isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'approve' && $withdrawal['status'] === 'Pending') {
            // Check user balance
            $stmt = $conn->prepare("SELECT balance FROM users WHERE id=?");
            $stmt->execute([$withdrawal['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && $user['balance'] >= $withdrawal['amount']) {
                $conn->beginTransaction();
                
                // Update withdrawal status
                $stmt = $conn->prepare("
                    UPDATE withdrawals 
                    SET status='Approved', 
                        approved_by=?, 
                        approved_at=NOW(), 
                        processed_by=?, 
                        processed_at=NOW(), 
                        note=NULL, 
                        updated_at=NOW() 
                    WHERE id=?
                ");
                $stmt->execute([$adminId, $adminId, $withdrawalId]);
                
                // Deduct from user balance
                $new_balance = $user['balance'] - $withdrawal['amount'];
                $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id=?");
                $stmt->execute([$new_balance, $withdrawal['user_id']]);
                
                $conn->commit();
                $msg = "Withdrawal approved successfully!";
                
                // Refresh withdrawal data
                $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE id = ?");
                $stmt->execute([$withdrawalId]);
                $withdrawal = $stmt->fetch();
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$withdrawal['user_id']]);
                $user = $stmt->fetch();
                $withdrawal['current_balance'] = $user['balance'];
                
            } else {
                $error = "Insufficient user balance!";
            }
            
        } elseif ($_POST['action'] === 'reject' && $withdrawal['status'] === 'Pending') {
            $conn->beginTransaction();
            
            $rejection_reason = $_POST['rejection_reason'] ?? '';
            
            // Update withdrawal status
            $stmt = $conn->prepare("
                UPDATE withdrawals 
                SET status='Rejected', 
                    note=?, 
                    processed_by=?, 
                    processed_at=NOW(), 
                    updated_at=NOW() 
                WHERE id=?
            ");
            $stmt->execute([$rejection_reason, $adminId, $withdrawalId]);
            
            // Refund user balance if it was deducted during request
            $stmt = $conn->prepare("SELECT balance FROM users WHERE id=?");
            $stmt->execute([$withdrawal['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $new_balance = $user['balance'] + $withdrawal['amount'];
                $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id=?");
                $stmt->execute([$new_balance, $withdrawal['user_id']]);
            }
            
            $conn->commit();
            $msg = "Withdrawal rejected successfully!";
            
            // Refresh withdrawal data
            $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE id = ?");
            $stmt->execute([$withdrawalId]);
            $withdrawal = $stmt->fetch();
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$withdrawal['user_id']]);
            $user = $stmt->fetch();
            $withdrawal['current_balance'] = $user['balance'];
            
        } elseif ($_POST['action'] === 'delete') {
            // Soft delete if deleted_at column exists, otherwise hard delete
            $stmt = $conn->prepare("SHOW COLUMNS FROM withdrawals LIKE 'deleted_at'");
            $stmt->execute();
            $hasDeletedAt = $stmt->fetch();
            
            if ($hasDeletedAt) {
                // Soft delete
                $stmt = $conn->prepare("UPDATE withdrawals SET deleted_at = NOW() WHERE id = ?");
                $stmt->execute([$withdrawalId]);
            } else {
                // Hard delete
                $stmt = $conn->prepare("DELETE FROM withdrawals WHERE id = ?");
                $stmt->execute([$withdrawalId]);
            }
            
            redirect('withdrawals.php?deleted=1');
        }
        
    } catch(PDOException $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal #<?php echo $withdrawalId; ?> - <?php echo htmlspecialchars(get_site_name()); ?> Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="includes/admin_styles.css">
    <style>
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: #6c757d;
            font-size: 14px;
        }
        .breadcrumb a {
            color: #4f46e5;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .detail-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            padding: 24px;
            margin-bottom: 20px;
        }
        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        .detail-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 500;
            color: #6b7280;
        }
        .detail-value {
            font-weight: 600;
            color: #1f2937;
        }
        .btn-group {
            display: flex;
            gap: 12px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: #4f46e5;
            color: white;
        }
        .btn-primary:hover {
            background: #4338ca;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .copy-btn {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            color: #6b7280;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .copy-btn:hover {
            background: #e5e7eb;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            display: block;
            margin-bottom: 4px;
            font-weight: 500;
            color: #374151;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    
    <!-- Admin Layout -->
    <div class="admin-layout">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <?php admin_back_button('withdrawals.php'); ?>
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="withdrawals.php">Withdrawals</a>
                <span>/</span>
                <span>#<?php echo $withdrawalId; ?></span>
            </div>
            
            <!-- Page Header -->
            <div class="page-header">
                <h1>Withdrawal #<?php echo $withdrawalId; ?></h1>
                <p>Withdrawal details and management</p>
            </div>
            
            <!-- Messages -->
            <?php if ($msg): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Header Card -->
            <div class="detail-card">
                <div class="detail-header">
                    <div>
                        <div class="detail-title">Withdrawal Request</div>
                        <div style="color: #6b7280; font-size: 14px; margin-top: 4px;">
                            <?php echo date('M j, Y H:i', strtotime($withdrawal['created_at'])); ?>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <span class="status-badge status-<?php echo strtolower($withdrawal['status']); ?>">
                            <?php echo htmlspecialchars($withdrawal['status']); ?>
                        </span>
                        <?php if ($withdrawal['status'] === 'Pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return showRejectForm()">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this withdrawal?')">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-secondary">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Reject Form (Hidden by default) -->
                <div id="rejectForm" style="display: none; margin-top: 20px; padding: 20px; background: #fef3c7; border-radius: 8px;">
                    <h4 style="margin-bottom: 12px; color: #92400e;">Rejection Reason</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="reject">
                        <div class="form-group">
                            <label class="form-label">Reason for rejection:</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Enter reason for rejection..." required></textarea>
                        </div>
                        <div class="btn-group">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times"></i> Confirm Reject
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="hideRejectForm()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Details Card -->
            <div class="detail-card">
                <div class="detail-header">
                    <div class="detail-title">Withdrawal Details</div>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Amount</span>
                    <span class="detail-value">$<?php echo number_format($withdrawal['amount'], 2); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Coin Asset</span>
                    <span class="detail-value"><?php echo htmlspecialchars($withdrawal['coin_asset'] ?? 'USDT'); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Network</span>
                    <span class="detail-value"><?php echo htmlspecialchars($withdrawal['network'] ?? 'TRC20'); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Wallet Address</span>
                    <span class="detail-value" style="display: flex; align-items: center; gap: 8px;">
                        <?php echo htmlspecialchars($withdrawal['wallet_address'] ?? 'N/A'); ?>
                        <?php if (!empty($withdrawal['wallet_address'])): ?>
                            <button class="copy-btn" onclick="copyAddress()">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        <?php endif; ?>
                    </span>
                </div>
                
                <?php if (!empty($withdrawal['note'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Note</span>
                    <span class="detail-value"><?php echo htmlspecialchars($withdrawal['note']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- User Card -->
            <div class="detail-card">
                <div class="detail-header">
                    <div class="detail-title">User Information</div>
                    <a href="user_view.php?id=<?php echo $withdrawal['user_id']; ?>" class="btn btn-primary" style="font-size: 14px; padding: 6px 12px;">
                        <i class="fas fa-user"></i> View Profile
                    </a>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Name</span>
                    <span class="detail-value"><?php echo htmlspecialchars($withdrawal['fullname']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Email</span>
                    <span class="detail-value"><?php echo htmlspecialchars($withdrawal['email']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Current Balance</span>
                    <span class="detail-value">$<?php echo number_format($withdrawal['current_balance'], 2); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Total Withdrawals</span>
                    <span class="detail-value"><?php echo $userStats['total_withdrawals']; ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Approved Withdrawals</span>
                    <span class="detail-value"><?php echo $userStats['approved_count']; ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Total Approved Amount</span>
                    <span class="detail-value">$<?php echo number_format($userStats['approved_amount'], 2); ?></span>
                </div>
            </div>
            
            <!-- Timeline Card -->
            <div class="detail-card">
                <div class="detail-header">
                    <div class="detail-title">Timeline</div>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Requested</span>
                    <span class="detail-value"><?php echo date('M j, Y H:i:s', strtotime($withdrawal['created_at'])); ?></span>
                </div>
                
                <?php if ($withdrawal['processed_at']): ?>
                <div class="detail-row">
                    <span class="detail-label">Processed</span>
                    <span class="detail-value"><?php echo date('M j, Y H:i:s', strtotime($withdrawal['processed_at'])); ?></span>
                </div>
                
                <?php if ($withdrawal['processed_by']): ?>
                <div class="detail-row">
                    <span class="detail-label">Processed By</span>
                    <span class="detail-value">Admin ID: <?php echo htmlspecialchars($withdrawal['processed_by']); ?></span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <span class="status-badge status-<?php echo strtolower($withdrawal['status']); ?>">
                            <?php echo htmlspecialchars($withdrawal['status']); ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function copyAddress() {
            const address = '<?php echo addslashes($withdrawal['wallet_address'] ?? ''); ?>';
            if (address) {
                navigator.clipboard.writeText(address).then(() => {
                    const btn = event.target;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    btn.style.background = '#d1fae5';
                    btn.style.color = '#065f46';
                    
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.style.background = '#f3f4f6';
                        btn.style.color = '#6b7280';
                    }, 2000);
                });
            }
        }
        
        function showRejectForm() {
            document.getElementById('rejectForm').style.display = 'block';
            return false;
        }
        
        function hideRejectForm() {
            document.getElementById('rejectForm').style.display = 'none';
        }
    </script>
</body>
</html>
