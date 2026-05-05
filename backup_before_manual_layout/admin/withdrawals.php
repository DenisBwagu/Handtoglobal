<?php
require_once '../config.php';
require_once '../includes/settings_helpers.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();
$adminId = $_SESSION['admin_id'] ?? $_SESSION['admin'] ?? null;

$msg = "";
$error = "";
if (isset($_GET['approved'])) {
    $msg = "Withdrawal approved successfully!";
} elseif (isset($_GET['rejected'])) {
    $msg = "Withdrawal rejected successfully!";
} elseif (isset($_GET['deleted'])) {
    $msg = "Withdrawal deleted successfully!";
}

// Handle withdrawal operations
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    try {
        // Get withdrawal details
        $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE id=?");
        $stmt->execute([$id]);
        $withdrawal = $stmt->fetch();
        
        if ($withdrawal && $withdrawal['status'] === 'Pending') {
            // Check user balance
            $stmt = $conn->prepare("SELECT balance FROM users WHERE id=?");
            $stmt->execute([$withdrawal['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && $user['balance'] >= $withdrawal['amount']) {
                // Start transaction
                $conn->beginTransaction();
                
                // Update withdrawal status
                $stmt = $conn->prepare("UPDATE withdrawals SET status='Approved', approved_by=?, approved_at=NOW(), processed_by=?, processed_at=NOW() WHERE id=?");
                $stmt->execute([$adminId, $adminId, $id]);
                
                // Deduct from user balance
                $new_balance = $user['balance'] - $withdrawal['amount'];
                $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id=?");
                $stmt->execute([$new_balance, $withdrawal['user_id']]);
                
                // Record finance activity
                require_once '../recordFinanceActivity.php';
                recordFinanceActivity(
                    $withdrawal['user_id'],
                    $adminId,
                    'withdrawal_debit',
                    'withdrawal',
                    $withdrawal['amount'],
                    'Withdrawal Approved',
                    $new_balance,
                    'withdrawals',
                    $id
                );
                
                $conn->commit();
                redirect('withdrawals.php?approved=1');
            } else {
                $error = "Insufficient user balance for this withdrawal.";
            }
        } else {
            $error = "Withdrawal not found or already processed.";
        }
    } catch(PDOException $e) {
        $conn->rollback();
        $error = "Failed to approve withdrawal: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_withdrawal') {
    $id = (int)($_POST['withdrawal_id'] ?? 0);
    $reason = trim($_POST['reject_reason'] ?? '');
    if ($reason === '') {
        $error = "Rejection reason is required.";
    } else {
    try {
        $stmt = $conn->prepare("UPDATE withdrawals SET status='Rejected', rejected_by=?, rejected_at=NOW(), processed_by=?, processed_at=NOW(), admin_note=? WHERE id=? AND status='Pending'");
        $stmt->execute([$adminId, $adminId, $reason, $id]);
        redirect('withdrawals.php?rejected=1');
    } catch(PDOException $e) {
        $error = "Failed to reject withdrawal: " . $e->getMessage();
    }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // Check if withdrawal exists and is not approved
        $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE id=?");
        $stmt->execute([$id]);
        $withdrawal = $stmt->fetch();
        
        if ($withdrawal) {
            if ($withdrawal['status'] === 'Approved') {
                $error = "Cannot delete approved withdrawal.";
            } else {
                $stmt = $conn->prepare("DELETE FROM withdrawals WHERE id=?");
                $stmt->execute([$id]);
                redirect('withdrawals.php?deleted=1');
            }
        } else {
            $error = "Withdrawal not found.";
        }
    } catch(PDOException $e) {
        $error = "Failed to delete withdrawal: " . $e->getMessage();
    }
}

// Pagination setup
$limit = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Status filter
$status_filter = $_GET['status'] ?? 'Pending';

// Get total count for pagination
$total_withdrawals = 0;
try {
    $count_sql = "SELECT COUNT(*) as total FROM withdrawals";
    $params = [];
    
    if ($status_filter !== 'all') {
        $count_sql .= " WHERE status = ?";
        $params[] = $status_filter;
    }
    
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    $total_withdrawals = $result['total'] ?? 0;
} catch(PDOException $e) {
    $error = "Failed to get count: " . $e->getMessage();
}

// Get withdrawals with pagination
$withdrawals = [];
try {
    $sql = "SELECT 
                w.*,
                u.fullname,
                u.email
            FROM withdrawals w 
            JOIN users u ON w.user_id = u.id";
    $params = [];
    
    if ($status_filter !== 'all') {
        $sql .= " WHERE w.status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " ORDER BY w.created_at DESC LIMIT $limit OFFSET $offset";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $withdrawals = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch withdrawals: " . $e->getMessage();
}

// Calculate pagination info
$total_pages = ceil($total_withdrawals / $limit);
$start_record = ($page - 1) * $limit + 1;
$end_record = min($page * $limit, $total_withdrawals);
if ($total_withdrawals == 0) {
    $start_record = 0;
    $end_record = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawals - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/global-theme.css">
    <script src="../assets/js/theme.js" defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --secondary: #7c3aed;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #0284c7;
            --text: #1a1a1a;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #f5f7fb;
            --white: #ffffff;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }
        
        /* Admin Layout */
        .admin-layout {
            display: flex;
            margin-top: 70px;
            min-height: calc(100vh - 70px);
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--border);
            padding: 20px 0;
            position: fixed;
            top: 70px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 900;
        }
        
        .sidebar-header {
            display: flex;
            align-items: center;
            padding: 0 20px;
            margin-bottom: 30px;
        }
        
        .sidebar-header i {
            font-size: 24px;
            margin-right: 12px;
            color: var(--primary);
        }
        
        .sidebar-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        
        .sidebar-section {
            margin-bottom: 25px;
            padding: 0 20px;
        }
        
        .sidebar-section-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            opacity: 0.6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            padding-left: 5px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 2px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: var(--text);
            text-decoration: none;
            border-radius: 0;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }
        
        .sidebar-menu a:hover {
            background: var(--bg);
            color: var(--primary);
        }
        
        .sidebar-menu a.active {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border-left: 3px solid var(--success);
            border-radius: 0 8px 8px 0;
        }
        
        .sidebar-menu i {
            margin-right: 12px;
            width: 16px;
            font-size: 14px;
            text-align: center;
        }
        
        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            height: 70px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 999;
        }
        
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .menu-icon {
            display: none;
            font-size: 20px;
            color: var(--muted);
            cursor: pointer;
        }
        
        .topbar-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .admin-badge {
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .topbar-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .topbar-icon:hover {
            background: var(--border);
            color: var(--text);
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .profile-name {
            font-weight: 500;
            color: var(--text);
        }
        
        .dropdown-arrow {
            font-size: 12px;
            color: var(--muted);
            opacity: 0.6;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            flex: 1;
        }
        
        /* Filter Section */
        .filter-section {
            margin-bottom: 20px;
        }
        
        .status-filter {
            padding: 6px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            background: white;
            color: #374151;
            font-size: 14px;
            min-width: 120px;
        }
        
        /* Full Width Table - No Card Layout */
        .withdrawals-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        .withdrawals-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .withdrawals-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
            vertical-align: middle;
        }
        
        .withdrawals-table tr:hover {
            background: #f9fafb;
        }
        
        /* User Column - Exact Match */
        .user-name {
            font-weight: 600;
            color: #111827;
            font-size: 14px;
            margin-bottom: 2px;
        }
        
        .user-email {
            font-size: 12px;
            color: #6b7280;
        }
        
        /* Amount Column - Exact Green Color */
        .amount {
            font-weight: 600;
            color: #10b981;
            font-size: 14px;
        }
        
        /* Asset/Network Column */
        .asset-name {
            font-weight: 500;
            color: #111827;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .network-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 500;
            background: #fef3c7;
            color: #92400e;
        }
        
        /* Status Badges - Exact Colors */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-pending {
            background: #fbbf24;
            color: #78350f;
        }
        
        .badge-approved {
            background: #10b981;
            color: white;
        }
        
        .badge-rejected {
            background: #ef4444;
            color: white;
        }
        
        /* Wallet Address - Exact Match */
        .wallet-address {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .wallet-input {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 6px 10px;
            font-size: 12px;
            color: #374151;
            font-family: 'Courier New', monospace;
            flex: 1;
            min-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Copy Button - Exact Match */
        .copy-btn {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 12px;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .copy-btn:hover {
            background: #f9fafb;
            color: #374151;
        }
        
        .copy-btn.copied {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }
        
        /* Date Column - Exact Match */
        .date-main {
            font-size: 14px;
            color: #111827;
            font-weight: 400;
            margin-bottom: 2px;
        }
        
        .time-sub {
            font-size: 12px;
            color: #6b7280;
        }
        
        /* Actions - Exact Match */
        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .btn-view {
            background: #fbbf24;
            color: #78350f;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-view:hover {
            background: #f59e0b;
        }
        
        .btn-delete {
            background: none;
            border: none;
            color: #ef4444;
            font-size: 12px;
            cursor: pointer;
            text-decoration: underline;
        }
        
        .btn-delete:hover {
            color: #dc2626;
        }
        
        /* Pagination - Exact Match */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .pagination-info {
            font-size: 14px;
            color: #6b7280;
        }
        
        .pagination-controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .pagination-btn {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 6px 12px;
            font-size: 14px;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .pagination-btn:hover {
            background: #f9fafb;
        }
        
        .pagination-btn.active {
            background: #fbbf24;
            color: #78350f;
            border-color: #fbbf24;
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--text);
        }
        
        .empty-state p {
            font-size: 14px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: var(--white);
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            color: var(--muted);
            cursor: pointer;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #16a34a;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-secondary {
            background: var(--bg);
            color: var(--text);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--border);
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .detail-label {
            font-weight: 500;
            color: var(--muted);
        }
        
        .detail-value {
            font-weight: 500;
            color: var(--text);
        }
        
        .detail-value.amount {
            color: var(--success);
        }
    </style>
</head>
<body>
        
    <!-- Topbar Header -->
    <div class="topbar">
        <div class="topbar-left">
            <div class="menu-icon">
                <i class="fas fa-bars"></i>
            </div>
            <div class="topbar-title">Withdrawals</div>
        </div>
        <div class="topbar-right">
            <div class="admin-badge">ADMIN</div>
            <form class="language-form" method="post" action="../language_action.php">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/withdrawals.php'); ?>">
                <input type="hidden" name="context" value="admin">
                <select name="language" onchange="this.form.submit()">
                    <?php foreach (['english' => 'English', 'chinese' => 'Chinese'] as $code => $label): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($_SESSION['admin_language'] ?? $_SESSION['language'] ?? 'english') === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="topbar-icon theme-toggle" id="themeToggle">
                <i class="fas fa-moon theme-icon" id="themeIcon"></i>
            </div>
            <a href="/handtoglobal/admin/logout.php" style="display:inline-flex;align-items:center;gap:8px;height:34px;padding:0 12px;border-radius:6px;background:#dc2626;color:#fff;text-decoration:none;font-size:13px;font-weight:700;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <div class="profile-info">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></div>
                <div class="dropdown-arrow">
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Admin Layout -->
    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <?php $site_logo = get_setting('site_logo'); ?>
                <?php if ($site_logo): ?>
                    <img src="../<?php echo $site_logo; ?>" alt="<?php echo get_setting('site_name', 'HandToGlobal'); ?>" style="height: 24px; margin-right: 12px;">
                <?php else: ?>
                    <i class="fas fa-hand-holding-usd"></i>
                <?php endif; ?>
                <h2><?php echo get_setting('site_name', 'HandToGlobal'); ?></h2>
            </div>
            
            <!-- MANAGEMENT Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">MANAGEMENT</div>
                <ul class="sidebar-menu">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    <li><a href="employees.php"><i class="fas fa-user-tie"></i> Employees</a></li>
                </ul>
            </div>
            
            <!-- PLATFORM Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">PLATFORM</div>
                <ul class="sidebar-menu">
                    <li><a href="levels.php"><i class="fas fa-layer-group"></i> Levels</a></li>
                    <li><a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="combos.php"><i class="fas fa-link"></i> Combos</a></li>
                    <li><a href="invitation-codes.php"><i class="fas fa-ticket-alt"></i> InvitationCodes</a></li>
                </ul>
            </div>
            
            <!-- FINANCE Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">FINANCE</div>
                <ul class="sidebar-menu">
                    <li><a href="finance_analysis.php"><i class="fas fa-chart-line"></i> FinanceAnalysis</a></li>
                    <li><a href="withdrawals.php" class="active"><i class="fas fa-arrow-up"></i> Withdrawals</a></li>
                </ul>
            </div>
            
            <!-- MONITORING Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">MONITORING</div>
                <ul class="sidebar-menu">
                    <li><a href="contacts.php"><i class="fas fa-address-book"></i> Contacts</a></li>
                    <li><a href="testimonials.php"><i class="fas fa-comments"></i> Testimonials</a></li>
                </ul>
            </div>
            
            <!-- SYSTEM Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">SYSTEM</div>
                <ul class="sidebar-menu">
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="languages.php"><i class="fas fa-language"></i> Languages</a></li>
                    <li><a href="/handtoglobal/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <?php if ($msg): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <select class="status-filter" onchange="window.location.href='?status=' + this.value">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            
            <!-- Full Width Table -->
            <table class="withdrawals-table">
                    <thead>
                        <tr>
                            <th>USER</th>
                            <th>AMOUNT</th>
                            <th>ASSET/NETWORK</th>
                            <th>WALLET ADDRESS</th>
                            <th>MEMO TAG</th>
                            <th>STATUS</th>
                            <th>DATE</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($withdrawals as $withdrawal): ?>
                            <tr>
                                <td>
                                    <div class="user-name"><?php echo htmlspecialchars($withdrawal['fullname'] ?? 'N/A'); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($withdrawal['email'] ?? 'N/A'); ?></div>
                                </td>
                                <td>
                                    <div class="amount">$<?php echo number_format($withdrawal['amount'], 2); ?></div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($withdrawal['asset'] ?? 'USDT'); ?></div>
                                    <div class="network-badge"><?php echo htmlspecialchars($withdrawal['network'] ?? 'TRC20'); ?></div>
                                </td>
                                <td>
                                    <div class="wallet-address">
                                        <div class="wallet-input" title="<?php echo htmlspecialchars($withdrawal['wallet_address'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($withdrawal['wallet_address'] ?? 'Missing wallet address'); ?>
                                        </div>
                                        <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($withdrawal['wallet_address'] ?? ''); ?>', this)">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($withdrawal['memo_tag'])): ?>
                                        <div class="wallet-address">
                                            <div class="wallet-input" style="background: #fef3c7; color: #92400e;">
                                                <?php echo htmlspecialchars($withdrawal['memo_tag']); ?>
                                            </div>
                                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($withdrawal['memo_tag']); ?>', this)">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($withdrawal['status']); ?>">
                                        <?php echo htmlspecialchars($withdrawal['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="date-main"><?php echo date('M j, Y', strtotime($withdrawal['created_at'])); ?></div>
                                    <div class="time-sub"><?php echo date('H:i', strtotime($withdrawal['created_at'])); ?></div>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button class="btn-view" onclick="viewWithdrawal(<?php echo $withdrawal['id']; ?>)">
                                            View
                                        </button>
                                        <?php if ($withdrawal['status'] === 'Pending'): ?>
                                            <button class="btn-view" onclick="approveWithdrawal(<?php echo $withdrawal['id']; ?>)">
                                                Approve
                                            </button>
                                            <button class="btn-delete" onclick="rejectWithdrawal(<?php echo $withdrawal['id']; ?>)">
                                                Reject
                                            </button>
                                        <?php endif; ?>
                                        <a href="?delete=<?php echo $withdrawal['id']; ?>" class="btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this withdrawal?')">
                                            Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            
            <?php if (empty($withdrawals)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No withdrawals found</h3>
                    <p>No withdrawals match the selected criteria.</p>
                </div>
            <?php endif; ?>
            
            <!-- Pagination -->
            <?php if ($total_withdrawals > 0): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_withdrawals; ?>
                    </div>
                    <div class="pagination-controls">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>" class="pagination-btn">
                                Previous
                            </a>
                        <?php else: ?>
                            <button class="pagination-btn" disabled>Previous</button>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>" 
                               class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>" class="pagination-btn">
                                Next
                            </a>
                        <?php else: ?>
                            <button class="pagination-btn" disabled>Next</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Withdrawal Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer" id="modalFooter">
                <!-- Actions will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
        function copyToClipboard(text, button) {
            if (!text) {
                return;
            }
            
            navigator.clipboard.writeText(text).then(function() {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i>';
                button.classList.add('copied');
                
                setTimeout(function() {
                    button.innerHTML = originalHTML;
                    button.classList.remove('copied');
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
            });
        }
        
        function viewWithdrawal(id) {
            fetch('withdrawals.php?action=view&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modalBody = document.getElementById('modalBody');
                        const modalFooter = document.getElementById('modalFooter');
                        
                        modalBody.innerHTML = `
                            <div class="detail-row">
                                <span class="detail-label">User Name</span>
                                <span class="detail-value">${data.withdrawal.fullname}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">User Email</span>
                                <span class="detail-value">${data.withdrawal.email}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Amount</span>
                                <span class="detail-value amount">$${parseFloat(data.withdrawal.amount).toFixed(2)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Asset</span>
                                <span class="detail-value">${data.withdrawal.asset || 'USDT'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Network</span>
                                <span class="detail-value">${data.withdrawal.network || 'TRC20'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Wallet Address</span>
                                <span class="detail-value">${data.withdrawal.wallet_address}</span>
                            </div>
                            ${data.withdrawal.memo_tag ? `
                            <div class="detail-row">
                                <span class="detail-label">Memo Tag</span>
                                <span class="detail-value">${data.withdrawal.memo_tag}</span>
                            </div>
                            ` : ''}
                            <div class="detail-row">
                                <span class="detail-label">Status</span>
                                <span class="detail-value">
                                    <span class="badge badge-${data.withdrawal.status.toLowerCase()}">${data.withdrawal.status}</span>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Date Submitted</span>
                                <span class="detail-value">${new Date(data.withdrawal.created_at).toLocaleString()}</span>
                            </div>
                        `;
                        
                        if (data.withdrawal.status === 'Pending') {
                            modalFooter.innerHTML = `
                                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                                <button class="btn btn-danger" onclick="rejectWithdrawal(${id})">Reject</button>
                                <button class="btn btn-success" onclick="approveWithdrawal(${id})">Approve</button>
                            `;
                        } else {
                            modalFooter.innerHTML = `
                                <button class="btn btn-primary" onclick="closeModal()">Close</button>
                            `;
                        }
                        
                        document.getElementById('viewModal').classList.add('show');
                    } else {
                        alert(data.message || 'Failed to load withdrawal details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load withdrawal details');
                });
        }
        
        function closeModal() {
            document.getElementById('viewModal').classList.remove('show');
        }
        
        function approveWithdrawal(id) {
            if (confirm('Are you sure you want to approve this withdrawal?')) {
                window.location.href = '?approve=' + id;
            }
        }
        
        function rejectWithdrawal(id) {
            const reason = prompt('Enter rejection reason for the user:');
            if (reason && reason.trim()) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reject_withdrawal">
                    <input type="hidden" name="withdrawal_id" value="${id}">
                    <input type="hidden" name="reject_reason" value="${reason.replace(/"/g, '&quot;')}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Handle AJAX view requests
        <?php
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            try {
                $stmt = $conn->prepare("
                    SELECT w.*, u.fullname, u.email 
                    FROM withdrawals w 
                    JOIN users u ON w.user_id = u.id 
                    WHERE w.id = ?
                ");
                $stmt->execute([$id]);
                $withdrawal = $stmt->fetch();
                
                if ($withdrawal) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'withdrawal' => $withdrawal]);
                    exit;
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Withdrawal not found']);
                    exit;
                }
            } catch(PDOException $e) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Database error']);
                exit;
            }
        }
        ?>
    </script>
</body>
</html>
