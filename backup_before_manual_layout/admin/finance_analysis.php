<?php
require_once '../config.php';
require_once '../includes/settings_helpers.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
}

// Get database connection
$conn = getConnection();

$msg = "";
$error = "";

// Handle date range filtering
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Validate dates
if (!strtotime($date_from) || !strtotime($date_to)) {
    $date_from = date('Y-m-01');
    $date_to = date('Y-m-d');
}

// Get financial statistics
$stats = [];
try {
    // Total deposits (approved deposits only)
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM deposits WHERE status='Approved' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $deposits = $stmt->fetch();
    $stats['deposits_count'] = $deposits['count'] ?? 0;
    $stats['deposits_total'] = $deposits['total'] ?? 0;
    
    // Total withdrawals (approved withdrawals only)
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM withdrawals WHERE status='Approved' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $withdrawals = $stmt->fetch();
    $stats['withdrawals_count'] = $withdrawals['count'] ?? 0;
    $stats['withdrawals_total'] = $withdrawals['total'] ?? 0;
    
    // Total bonuses paid (bonus_credit from balance_adjustments)
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM balance_adjustments WHERE type='bonus_credit' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $bonuses = $stmt->fetch();
    $stats['bonuses_count'] = $bonuses['count'] ?? 0;
    $stats['bonuses_total'] = $bonuses['total'] ?? 0;
    
    // Total deductions (manual_debit from balance_adjustments)
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM balance_adjustments WHERE type='manual_debit' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $deductions = $stmt->fetch();
    $stats['deductions_count'] = $deductions['count'] ?? 0;
    $stats['deductions_total'] = $deductions['total'] ?? 0;
    
    // Total task rewards (task_reward_credit + combo_credit from balance_adjustments)
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM balance_adjustments WHERE type IN ('task_reward_credit', 'combo_credit') AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $tasks = $stmt->fetch();
    $stats['tasks_completed'] = $tasks['count'] ?? 0;
    $stats['tasks_earnings'] = $tasks['total'] ?? 0;
    
    // Platform net calculation: TotalDeposits + TotalDeductions - TotalWithdrawals - TotalBonusesPaid - TotalTaskRewards
    $stats['platform_net'] = $stats['deposits_total'] + $stats['deductions_total'] - $stats['withdrawals_total'] - $stats['bonuses_total'] - $stats['tasks_earnings'];
    
    // Outstanding balances (sum of all active users' current balances)
    $stmt = $conn->prepare("SELECT SUM(balance) as total FROM users WHERE is_active = 1");
    $stmt->execute();
    $outstanding = $stmt->fetch();
    $stats['outstanding_balances'] = $outstanding['total'] ?? 0;
    
    // Additional stats for reporting
    // Pending deposits
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM deposits WHERE status='Pending'");
    $stmt->execute();
    $pending_deposits = $stmt->fetch();
    $stats['pending_deposits_count'] = $pending_deposits['count'] ?? 0;
    $stats['pending_deposits_total'] = $pending_deposits['total'] ?? 0;
    
    // Pending withdrawals
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM withdrawals WHERE status='Pending'");
    $stmt->execute();
    $pending_withdrawals = $stmt->fetch();
    $stats['pending_withdrawals_count'] = $pending_withdrawals['count'] ?? 0;
    $stats['pending_withdrawals_total'] = $pending_withdrawals['total'] ?? 0;
    
} catch(PDOException $e) {
    $error = "Failed to fetch financial statistics: " . $e->getMessage();
}

// Add default values to prevent undefined array key warnings
$stats = array_merge([
    'deposits_total' => 0,
    'withdrawals_total' => 0,
    'bonuses_total' => 0,
    'deductions_total' => 0,
    'tasks_earnings' => 0,
    'platform_net' => 0,
    'outstanding_balances' => 0,
    'deposits_count' => 0,
    'withdrawals_count' => 0,
    'bonuses_count' => 0,
    'deductions_count' => 0,
    'tasks_completed' => 0,
    'pending_deposits_count' => 0,
    'pending_deposits_total' => 0,
    'pending_withdrawals_count' => 0,
    'pending_withdrawals_total' => 0
], $stats);

// Get balance adjustments (all admin/manual balance changes)
$balance_adjustments = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            fa.*,
            u.email as user_email,
            a.email as admin_email,
            CASE 
                WHEN fa.type IN ('deposit_credit', 'bonus_credit', 'invitation_credit', 'manual_credit', 'task_reward_credit', 'combo_credit') THEN 'Credit'
                WHEN fa.type IN ('deposit_debit', 'withdrawal_debit', 'bonus_debit', 'invitation_debit', 'manual_debit', 'task_reward_debit', 'combo_debit') THEN 'Debit'
                ELSE 'Unknown'
            END as transaction_type
        FROM balance_adjustments fa
        LEFT JOIN users u ON fa.user_id = u.id
        LEFT JOIN admins a ON fa.admin_id = a.id
        WHERE DATE(fa.created_at) BETWEEN ? AND ?
        ORDER BY fa.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$date_from, $date_to]);
    $balance_adjustments = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch balance adjustments: " . $e->getMessage();
}

// Get withdrawal records with complete information
$withdrawal_records = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            w.*,
            u.email as user_email,
            admin_approved.email as approved_by_email
        FROM withdrawals w
        LEFT JOIN users u ON w.user_id = u.id
        LEFT JOIN admins admin_approved ON w.approved_by = admin_approved.id
        WHERE DATE(w.created_at) BETWEEN ? AND ?
        ORDER BY w.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$date_from, $date_to]);
    $withdrawal_records = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch withdrawal records: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinanceAnalysis - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css?v=<?php echo time(); ?>">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --secondary: #7c3aed;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #0284c7;
            
            --bg: #f5f7fb;
            --surface: #ffffff;
            --sidebar: #ffffff;
            --text: #101828;
            --muted: #6b7280;
            --border: #e5e7eb;
            
            --radius: 12px;
            --radius-sm: 8px;
            --shadow: 0 10px 30px rgba(16,24,40,.08);
            --shadow-soft: 0 4px 14px rgba(16,24,40,.06);
            --transition: .22s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
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
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            flex: 1;
            max-width: 1200px;
        }
        
        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            background: white;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 1000;
        }
        
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .menu-icon {
            font-size: 18px;
            color: var(--text);
            cursor: pointer;
        }
        
        .topbar-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text);
        }
        
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .admin-badge {
            background: var(--success);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .topbar-icon {
            font-size: 18px;
            color: var(--muted);
            cursor: pointer;
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 10px;
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
            cursor: pointer;
            position: relative;
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
        
        /* Finance V2 Styles */
        .finance-v2-page {
            margin-left: 260px;
            padding: 24px;
            min-height: calc(100vh - 70px);
            background: #f5f7fb;
        }
        
        .finance-v2-container {
            max-width: 1100px;
            margin: 0 auto;
        }
        
        .finance-v2-title {
            font-size: 16px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 24px;
            text-align: left;
        }
        
        .finance-v2-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .finance-v2-stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            height: 72px;
        }
        
        .finance-v2-icon {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
            flex-shrink: 0;
        }
        
        .finance-v2-icon.deposits { background: #22c55e; }
        .finance-v2-icon.withdrawals { background: #ef4444; }
        .finance-v2-icon.bonuses { background: #f59e0b; }
        .finance-v2-icon.deductions { background: #0284c7; }
        .finance-v2-icon.tasks { background: #4f46e5; }
        .finance-v2-icon.net { background: #7c3aed; }
        
        .finance-v2-content {
            flex: 1;
            min-width: 0;
        }
        
        .finance-v2-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
            font-weight: 400;
        }
        
        .finance-v2-amount {
            font-size: 18px;
            font-weight: 600;
            color: #000000;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .finance-v2-analysis-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .finance-v2-analysis-title {
            font-size: 16px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 20px;
        }
        
        .finance-v2-money-section {
            margin-bottom: 24px;
        }
        
        .finance-v2-money-title {
            font-size: 14px;
            font-weight: 500;
            color: #000000;
            margin-bottom: 12px;
        }
        
        .finance-v2-money-bar {
            display: flex;
            height: 32px;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        .finance-v2-money-segment {
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 500;
        }
        
        .finance-v2-money-segment.client-deposits { background: #22c55e; }
        .finance-v2-money-segment.combo-deposits { background: #4f46e5; }
        .finance-v2-money-segment.deductions { background: #0284c7; }
        .finance-v2-money-segment.withdrawals { background: #ef4444; }
        .finance-v2-money-segment.task-rewards { background: #f59e0b; }
        .finance-v2-money-segment.bonuses { background: #7c3aed; }
        
        .finance-v2-money-legend {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #6b7280;
        }
        
        .finance-v2-money-total {
            text-align: right;
            font-size: 14px;
            font-weight: 600;
            color: #000000;
        }
        
        .finance-v2-profit-summary {
            display: flex;
            justify-content: space-between;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }
        
        .finance-v2-profit-item {
            text-align: center;
        }
        
        .finance-v2-profit-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        .finance-v2-profit-value {
            font-size: 16px;
            font-weight: 600;
        }
        
        .finance-v2-profit-value.positive { color: #22c55e; }
        .finance-v2-profit-value.negative { color: #ef4444; }
        
        .finance-v2-expenses-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .finance-v2-expenses-title {
            font-size: 16px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 20px;
        }
        
        .finance-v2-expense-row {
            margin-bottom: 16px;
        }
        
        .finance-v2-expense-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .finance-v2-expense-label {
            font-size: 14px;
            color: #000000;
        }
        
        .finance-v2-expense-amount {
            font-size: 14px;
            font-weight: 600;
            color: #000000;
        }
        
        .finance-v2-expense-progress {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .finance-v2-expense-progress-fill {
            height: 100%;
            background: #4f46e5;
            transition: width 0.3s ease;
        }
        
        .finance-v2-expense-percentage {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }
        
        .finance-v2-table-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .finance-v2-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .finance-v2-tab {
            padding: 12px 16px;
            background: none;
            border: none;
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        
        .finance-v2-tab:hover {
            color: #000000;
        }
        
        .finance-v2-tab.active {
            color: #4f46e5;
            border-bottom-color: #4f46e5;
        }
        
        .finance-v2-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .finance-v2-table th,
        .finance-v2-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        
        .finance-v2-table th {
            font-weight: 600;
            color: #000000;
            background: #f5f7fb;
        }
        
        .finance-v2-table tr:hover {
            background: #f5f7fb;
        }
        
        .finance-v2-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .finance-v2-badge-credit {
            background: #d1fae5;
            color: #065f46;
        }
        
        .finance-v2-badge-debit {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .finance-v2-amount-positive {
            color: #22c55e;
        }
        
        .finance-v2-amount-negative {
            color: #ef4444;
        }
        
        .finance-v2-tab-content {
            display: none;
        }
        
        .finance-v2-tab-content.active {
            display: block;
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
            <div class="topbar-title">FinanceAnalysis</div>
        </div>
        <div class="topbar-right">
            <div class="admin-badge">ADMIN</div>
            <div class="topbar-icon">
                <i class="fas fa-moon"></i>
            </div>
            <div class="profile-info">
                <div class="profile-avatar">A</div>
                <div class="profile-name">Admin</div>
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
                    <li><a href="finance_analysis.php" class="active"><i class="fas fa-chart-line"></i> FinanceAnalysis</a></li>
                    <li><a href="withdrawals.php"><i class="fas fa-arrow-up"></i> Withdrawals</a></li>
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
                </ul>
            </div>
        </div>
        
        <!-- Finance V2 Page -->
        <div class="finance-v2-page">
            <div class="finance-v2-container">
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
                
                <h1 class="finance-v2-title">FinanceAnalysis</h1>
                
                <!-- Summary Cards -->
                <div class="finance-v2-grid">
                    <div class="finance-v2-stat-card">
                        <div class="finance-v2-icon deposits">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="finance-v2-content">
                            <div class="finance-v2-label">TotalDeposits</div>
                            <div class="finance-v2-amount">$<?php echo number_format($stats['deposits_total'], 2); ?></div>
                        </div>
                    </div>
                    
                    <div class="finance-v2-stat-card">
                        <div class="finance-v2-icon withdrawals">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="finance-v2-content">
                            <div class="finance-v2-label">TotalWithdrawals</div>
                            <div class="finance-v2-amount">$<?php echo number_format($stats['withdrawals_total'], 2); ?></div>
                        </div>
                    </div>
                    
                    <div class="finance-v2-stat-card">
                        <div class="finance-v2-icon bonuses">
                            <i class="fas fa-gift"></i>
                        </div>
                        <div class="finance-v2-content">
                            <div class="finance-v2-label">TotalBonusesPaid</div>
                            <div class="finance-v2-amount">$<?php echo number_format($stats['bonuses_total'], 2); ?></div>
                        </div>
                    </div>
                    
                    <div class="finance-v2-stat-card">
                        <div class="finance-v2-icon deductions">
                            <i class="fas fa-minus-circle"></i>
                        </div>
                        <div class="finance-v2-content">
                            <div class="finance-v2-label">TotalDeductions</div>
                            <div class="finance-v2-amount">$<?php echo number_format($stats['deductions_total'], 2); ?></div>
                        </div>
                    </div>
                    
                    <div class="finance-v2-stat-card">
                        <div class="finance-v2-icon tasks">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="finance-v2-content">
                            <div class="finance-v2-label">TotalTaskRewards</div>
                            <div class="finance-v2-amount">$<?php echo number_format($stats['tasks_earnings'], 2); ?></div>
                        </div>
                    </div>
                    
                    <div class="finance-v2-stat-card">
                        <div class="finance-v2-icon net">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="finance-v2-content">
                            <div class="finance-v2-label">PlatformNet</div>
                            <div class="finance-v2-amount <?php echo $stats['platform_net'] >= 0 ? 'finance-v2-amount-positive' : 'finance-v2-amount-negative'; ?>">
                                $<?php echo number_format($stats['platform_net'], 2); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Profit Analysis -->
                <div class="finance-v2-analysis-card">
                    <h2 class="finance-v2-analysis-title">ProfitAnalysis</h2>
                    
                    <!-- Money In -->
                    <div class="finance-v2-money-section">
                        <div class="finance-v2-money-title">MoneyIn</div>
                        <div class="finance-v2-money-bar">
                            <?php
                            $total_money_in = $stats['deposits_total'];
                            $client_deposits = $stats['deposits_total'] * 0.8;
                            $combo_deposits = $stats['deposits_total'] * 0.15;
                            $deductions = $stats['deductions_total'];
                            
                            $client_percent = $total_money_in > 0 ? ($client_deposits / $total_money_in) * 100 : 0;
                            $combo_percent = $total_money_in > 0 ? ($combo_deposits / $total_money_in) * 100 : 0;
                            ?>
                            <div class="finance-v2-money-segment client-deposits" style="width: <?php echo $client_percent; ?>%">
                                Client Deposits
                            </div>
                            <div class="finance-v2-money-segment combo-deposits" style="width: <?php echo $combo_percent; ?>%">
                                Combo Deposits
                            </div>
                            <div class="finance-v2-money-segment deductions" style="width: <?php echo $total_money_in > 0 ? ($deductions / $total_money_in) * 100 : 0; ?>%">
                                Deductions
                            </div>
                        </div>
                        <div class="finance-v2-money-legend">
                            <span>Client Deposits: $<?php echo number_format($client_deposits, 2); ?></span>
                            <span>Combo Deposits: $<?php echo number_format($combo_deposits, 2); ?></span>
                            <span>Deductions: $<?php echo number_format($deductions, 2); ?></span>
                            <span class="finance-v2-money-total">Total: $<?php echo number_format($total_money_in, 2); ?></span>
                        </div>
                    </div>
                    
                    <!-- Money Out -->
                    <div class="finance-v2-money-section">
                        <div class="finance-v2-money-title">MoneyOut</div>
                        <div class="finance-v2-money-bar">
                            <?php
                            $total_money_out = $stats['withdrawals_total'] + $stats['tasks_earnings'] + $stats['bonuses_total'];
                            $withdrawals = $stats['withdrawals_total'];
                            $task_rewards = $stats['tasks_earnings'];
                            $bonuses = $stats['bonuses_total'];
                            
                            $withdrawals_percent = $total_money_out > 0 ? ($withdrawals / $total_money_out) * 100 : 0;
                            $tasks_percent = $total_money_out > 0 ? ($task_rewards / $total_money_out) * 100 : 0;
                            $bonuses_percent = $total_money_out > 0 ? ($bonuses / $total_money_out) * 100 : 0;
                            ?>
                            <div class="finance-v2-money-segment withdrawals" style="width: <?php echo $withdrawals_percent; ?>%">
                                Withdrawals
                            </div>
                            <div class="finance-v2-money-segment task-rewards" style="width: <?php echo $tasks_percent; ?>%">
                                Task Rewards
                            </div>
                            <div class="finance-v2-money-segment bonuses" style="width: <?php echo $bonuses_percent; ?>%">
                                Bonuses
                            </div>
                        </div>
                        <div class="finance-v2-money-legend">
                            <span>Withdrawals: $<?php echo number_format($withdrawals, 2); ?></span>
                            <span>Task Rewards: $<?php echo number_format($task_rewards, 2); ?></span>
                            <span>Bonuses: $<?php echo number_format($bonuses, 2); ?></span>
                            <span class="finance-v2-money-total">Total: $<?php echo number_format($total_money_out, 2); ?></span>
                        </div>
                    </div>
                    
                    <!-- Profit Summary -->
                    <div class="finance-v2-profit-summary">
                        <div class="finance-v2-profit-item">
                            <div class="finance-v2-profit-label">NETPROFITLOSS</div>
                            <div class="finance-v2-profit-value <?php echo $stats['platform_net'] >= 0 ? 'positive' : 'negative'; ?>">
                                $<?php echo number_format($stats['platform_net'], 2); ?>
                            </div>
                        </div>
                        <div class="finance-v2-profit-item">
                            <div class="finance-v2-profit-label">OUTSTANDINGBALANCES</div>
                            <div class="finance-v2-profit-value">
                                $<?php echo number_format($stats['outstanding_balances'], 2); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Where Money Going -->
                <div class="finance-v2-expenses-card">
                    <h2 class="finance-v2-expenses-title">WhereMoneyGoing</h2>
                    
                    <?php
                    $total_expenses = $stats['tasks_earnings'] + $stats['bonuses_total'] + $stats['withdrawals_total'];
                    $tasks_percent = $total_expenses > 0 ? ($stats['tasks_earnings'] / $total_expenses) * 100 : 0;
                    $bonuses_percent = $total_expenses > 0 ? ($stats['bonuses_total'] / $total_expenses) * 100 : 0;
                    $withdrawals_percent = $total_expenses > 0 ? ($stats['withdrawals_total'] / $total_expenses) * 100 : 0;
                    ?>
                    
                    <div class="finance-v2-expense-row">
                        <div class="finance-v2-expense-header">
                            <span class="finance-v2-expense-label">Task Rewards</span>
                            <span class="finance-v2-expense-amount">$<?php echo number_format($stats['tasks_earnings'], 2); ?></span>
                        </div>
                        <div class="finance-v2-expense-progress">
                            <div class="finance-v2-expense-progress-fill" style="width: <?php echo $tasks_percent; ?>%"></div>
                        </div>
                        <div class="finance-v2-expense-percentage"><?php echo round($tasks_percent, 1); ?>%</div>
                    </div>
                    
                    <div class="finance-v2-expense-row">
                        <div class="finance-v2-expense-header">
                            <span class="finance-v2-expense-label">Bonuses Paid</span>
                            <span class="finance-v2-expense-amount">$<?php echo number_format($stats['bonuses_total'], 2); ?></span>
                        </div>
                        <div class="finance-v2-expense-progress">
                            <div class="finance-v2-expense-progress-fill" style="width: <?php echo $bonuses_percent; ?>%"></div>
                        </div>
                        <div class="finance-v2-expense-percentage"><?php echo round($bonuses_percent, 1); ?>%</div>
                    </div>
                    
                    <div class="finance-v2-expense-row">
                        <div class="finance-v2-expense-header">
                            <span class="finance-v2-expense-label">Approved Withdrawals</span>
                            <span class="finance-v2-expense-amount">$<?php echo number_format($stats['withdrawals_total'], 2); ?></span>
                        </div>
                        <div class="finance-v2-expense-progress">
                            <div class="finance-v2-expense-progress-fill" style="width: <?php echo $withdrawals_percent; ?>%"></div>
                        </div>
                        <div class="finance-v2-expense-percentage"><?php echo round($withdrawals_percent, 1); ?>%</div>
                    </div>
                </div>
                
                <!-- Tabbed Table -->
                <div class="finance-v2-table-card">
                    <div class="finance-v2-tabs">
                        <button class="finance-v2-tab active" onclick="switchTab('balance-adjustments')">Balance Adjustments</button>
                        <button class="finance-v2-tab" onclick="switchTab('withdrawals')">Withdrawals</button>
                    </div>
                    
                    <!-- Balance Adjustments Tab -->
                    <div id="balance-adjustments" class="finance-v2-tab-content active">
                        <table class="finance-v2-table">
                            <thead>
                                <tr>
                                    <th>DATE</th>
                                    <th>ADMIN</th>
                                    <th>USER</th>
                                    <th>TYPE</th>
                                    <th>REASON</th>
                                    <th>AMOUNT</th>
                                    <th>BALANCE AFTER</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($balance_adjustments as $adjustment): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($adjustment['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($adjustment['admin_email'] ?? 'System'); ?></td>
                                        <td><?php echo htmlspecialchars($adjustment['user_email'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="finance-v2-badge <?php echo $adjustment['transaction_type'] == 'Credit' ? 'finance-v2-badge-credit' : 'finance-v2-badge-debit'; ?>">
                                                <?php echo htmlspecialchars($adjustment['transaction_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($adjustment['reason'] ?? 'N/A'); ?></td>
                                        <td class="<?php echo $adjustment['transaction_type'] == 'Credit' ? 'finance-v2-amount-positive' : 'finance-v2-amount-negative'; ?>">
                                            $<?php echo number_format(abs($adjustment['amount']), 2); ?>
                                        </td>
                                        <td>$<?php echo number_format($adjustment['balance_after'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($balance_adjustments)): ?>
                            <p style="text-align: center; padding: 40px; color: #6b7280;">
                                No balance adjustments found.
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Withdrawals Tab -->
                    <div id="withdrawals" class="finance-v2-tab-content">
                        <table class="finance-v2-table">
                            <thead>
                                <tr>
                                    <th>DATE</th>
                                    <th>USER</th>
                                    <th>AMOUNT</th>
                                    <th>STATUS</th>
                                    <th>METHOD</th>
                                    <th>ACCOUNT DETAILS</th>
                                    <th>APPROVED BY</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($withdrawal_records as $withdrawal): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($withdrawal['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($withdrawal['user_email']); ?></td>
                                        <td class="finance-v2-amount-negative">$<?php echo number_format($withdrawal['amount'], 2); ?></td>
                                        <td>
                                            <span class="finance-v2-badge <?php 
                                                echo match($withdrawal['status']) {
                                                    'Approved' => 'finance-v2-badge-credit',
                                                    'Pending' => 'finance-v2-badge-warning',
                                                    'Rejected' => 'finance-v2-badge-debit',
                                                    'Completed' => 'finance-v2-badge-credit',
                                                    default => 'finance-v2-badge-secondary'
                                                }; 
                                            ?>">
                                                <?php echo $withdrawal['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($withdrawal['network'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($withdrawal['wallet_address'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($withdrawal['approved_by_email'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($withdrawal_records)): ?>
                            <p style="text-align: center; padding: 40px; color: #6b7280;">
                                No withdrawals found.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.finance-v2-tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.finance-v2-tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
