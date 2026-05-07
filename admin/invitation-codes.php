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

// Update invitation_codes table structure to match requirements
try {
    // Check if table exists and update structure
    $conn->exec("
        CREATE TABLE IF NOT EXISTS invitation_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            employee_id INT NULL,
            starting_balance DECIMAL(10,2) DEFAULT 0.00,
            max_uses INT DEFAULT 1,
            used_count INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee (employee_id),
            INDEX idx_code (code),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Add missing columns if they don't exist
    $columns = $conn->query("SHOW COLUMNS FROM invitation_codes")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('employee_id', $columns)) {
        $conn->exec("ALTER TABLE invitation_codes ADD COLUMN employee_id INT NULL AFTER code");
    }
    if (!in_array('starting_balance', $columns)) {
        $conn->exec("ALTER TABLE invitation_codes ADD COLUMN starting_balance DECIMAL(10,2) DEFAULT 0.00 AFTER employee_id");
    }
    if (!in_array('max_uses', $columns)) {
        $conn->exec("ALTER TABLE invitation_codes ADD COLUMN max_uses INT DEFAULT 1 AFTER starting_balance");
    }
    if (!in_array('used_count', $columns)) {
        $conn->exec("ALTER TABLE invitation_codes ADD COLUMN used_count INT DEFAULT 0 AFTER max_uses");
    }
    if (!in_array('uses_remaining', $columns)) {
        $conn->exec("ALTER TABLE invitation_codes ADD COLUMN uses_remaining INT DEFAULT 1 AFTER used_count");
    }
    if (!in_array('is_active', $columns)) {
        $conn->exec("ALTER TABLE invitation_codes ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER used_count");
    }
    if (!in_array('updated_at', $columns)) {
        $conn->exec("ALTER TABLE invitation_codes ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    }
    
    // Create employees table if it doesn't exist
    $conn->exec("
        CREATE TABLE IF NOT EXISTS employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
} catch(PDOException $e) {
    // Table already exists or other error, continue
}

$msg = "";
$error = "";

// Handle form submissions
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'generate':
            $employee_id = (int)$_POST['employee_id'];
            $number_of_codes = (int)$_POST['number_of_codes'];
            $max_uses_per_code = (int)$_POST['max_uses_per_code'];
            $code_prefix = sanitize($_POST['code_prefix'] ?? '');
            $starting_balance = (float)($_POST['starting_balance'] ?? 0);
            
            if ($number_of_codes <= 0 || $number_of_codes > 100) {
                $error = "Number of codes must be between 1 and 100";
            } elseif ($max_uses_per_code <= 0 || $max_uses_per_code > 1000) {
                $error = "Max uses per code must be between 1 and 1000";
            } else {
                try {
                    $generated_codes = [];
                    for ($i = 0; $i < $number_of_codes; $i++) {
                        $code = generateUniqueCode($code_prefix);
                        
                        $stmt = $conn->prepare("
                            INSERT INTO invitation_codes (code, employee_id, starting_balance, max_uses, used_count, uses_remaining, is_active) 
                            VALUES (?, ?, ?, ?, 0, ?, 1)
                        ");
                        $stmt->execute([$code, $employee_id, $starting_balance, $max_uses_per_code, $max_uses_per_code]);
                        $generated_codes[] = $code;
                    }
                    
                    $msg = "Successfully generated $number_of_codes invitation codes!";
                } catch(PDOException $e) {
                    $error = "Failed to generate codes: " . $e->getMessage();
                }
            }
            break;
            
        case 'edit':
            $code_id = (int)$_POST['code_id'];
            $employee_id = (int)$_POST['employee_id'];
            $max_uses = (int)$_POST['max_uses'];
            $starting_balance = (float)($_POST['starting_balance'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            try {
                $stmt = $conn->prepare("
                    UPDATE invitation_codes 
                    SET employee_id = ?, max_uses = ?, starting_balance = ?, is_active = ?, uses_remaining = GREATEST(? - COALESCE(used_count, 0), 0), updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$employee_id, $max_uses, $starting_balance, $is_active, $max_uses, $code_id]);
                $msg = "Invitation code updated successfully!";
            } catch(PDOException $e) {
                $error = "Failed to update code: " . $e->getMessage();
            }
            break;
    }
}

// Handle delete
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $code_id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM invitation_codes WHERE id = ?");
        $stmt->execute([$code_id]);
        $msg = "Invitation code deleted successfully!";
    } catch(PDOException $e) {
        $error = "Failed to delete code: " . $e->getMessage();
    }
}

// Handle edit mode
$edit_mode = false;
$edit_code = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $code_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM invitation_codes WHERE id = ?");
    $stmt->execute([$code_id]);
    $edit_code = $stmt->fetch();
    if ($edit_code) {
        $edit_mode = true;
    }
}

// Get employees for dropdown
$employees = [];
try {
    $stmt = $conn->prepare("SELECT id, name, email FROM employees ORDER BY name");
    $stmt->execute();
    $employees = $stmt->fetchAll();
} catch(PDOException $e) {
    // No employees yet
}

// Get filter parameters
$employee_filter = isset($_GET['employee_filter']) ? (int)$_GET['employee_filter'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build query
$where_clause = "1=1";
$params = [];

if ($employee_filter > 0) {
    $where_clause .= " AND ic.employee_id = ?";
    $params[] = $employee_filter;
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM invitation_codes ic WHERE $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get invitation codes
$codes = [];
try {
    $sql = "
        SELECT ic.*, e.name as employee_name, e.email as employee_email
        FROM invitation_codes ic
        LEFT JOIN employees e ON ic.employee_id = e.id
        WHERE $where_clause
        ORDER BY ic.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $codes = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch invitation codes: " . $e->getMessage();
}

// Generate unique code function
function generateUniqueCode($prefix = '') {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $length = 8;
    if (!empty($prefix)) {
        $length = 8 - strlen($prefix);
    }
    
    do {
        $code = $prefix;
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        global $conn;
        $stmt = $conn->prepare("SELECT id FROM invitation_codes WHERE code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    
    return $code;
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="invitation_codes_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header
    fputcsv($output, ['CODE', 'EMPLOYEE', 'STARTING BALANCE', 'MAX USES', 'USED', 'REMAINING', 'ACTIVE', 'CREATED AT']);
    
    // Data
    foreach ($codes as $code) {
        $remaining = $code['max_uses'] - $code['used_count'];
        fputcsv($output, [
            $code['code'],
            $code['employee_name'] ?? 'No Employee',
            '$' . number_format($code['starting_balance'], 2),
            $code['max_uses'],
            $code['used_count'],
            $remaining,
            $code['is_active'] ? 'Yes' : 'No',
            date('Y-m-d H:i:s', strtotime($code['created_at']))
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation Codes - <?php echo htmlspecialchars(get_site_name()); ?> Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #101828;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #e5e7eb;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        
        .table th {
            font-weight: 600;
            color: #374151;
            background: #f9fafb;
        }
        
        .table tr:hover {
            background: #f9fafb;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .actions a {
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
        }
        
        .actions a:hover {
            color: #101828;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
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
        
        .filter-dropdown {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
        }
        
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-top: 1px solid #e5e7eb;
            margin-top: 20px;
        }
        
        .pagination-info {
            color: #6b7280;
            font-size: 14px;
        }
        
        .pagination-links {
            display: flex;
            gap: 8px;
        }
        
        .pagination-links a {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            color: #374151;
            text-decoration: none;
            font-size: 14px;
        }
        
        .pagination-links a:hover {
            background: #f3f4f6;
        }
        
        .pagination-links a.active {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 16px;
            height: 16px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        
        .code-summary {
            background: #f9fafb;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .code-summary h3 {
            font-size: 16px;
            margin-bottom: 12px;
            color: #374151;
        }
        
        .code-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
        }
        
        .code-summary-item {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }
        
        .code-summary-label {
            color: #6b7280;
        }
        
        .code-summary-value {
            font-weight: 500;
            color: #101828;
        }
    </style>
</head>
<body><?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    
    <!-- Admin Layout -->
    <div class="admin-layout">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <?php admin_back_button('invitation-codes.php'); ?>
        
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
        
        <?php if ($edit_mode): ?>
            <!-- Edit Form -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Edit Invitation Code</h2>
                </div>
                
                <?php if ($edit_code): ?>
                    <div class="code-summary">
                        <h3>Code Summary</h3>
                        <div class="code-summary-grid">
                            <div class="code-summary-item">
                                <span class="code-summary-label">Type:</span>
                                <span class="code-summary-value">Single</span>
                            </div>
                            <div class="code-summary-item">
                                <span class="code-summary-label">Code:</span>
                                <span class="code-summary-value"><?php echo htmlspecialchars($edit_code['code']); ?></span>
                            </div>
                            <div class="code-summary-item">
                                <span class="code-summary-label">Used:</span>
                                <span class="code-summary-value"><?php echo $edit_code['used_count']; ?> / <?php echo $edit_code['max_uses']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="code_id" value="<?php echo $edit_code['id']; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Employee</label>
                                <select name="employee_id" class="form-control">
                                    <option value="0">No Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>" <?php echo $edit_code['employee_id'] == $employee['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($employee['name'] . ' (' . $employee['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Max Uses</label>
                                <input type="number" name="max_uses" class="form-control" value="<?php echo $edit_code['max_uses']; ?>" min="1" max="1000" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Starting Balance (optional)</label>
                                <input type="number" name="starting_balance" class="form-control" value="<?php echo $edit_code['starting_balance']; ?>" step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="is_active" id="is_active" <?php echo $edit_code['is_active'] ? 'checked' : ''; ?>>
                                <label for="is_active">Codes Active</label>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 12px;">
                            <a href="invitation-codes.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <p>Invitation code not found.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- List Page -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Invitation Codes</h2>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <select name="employee_filter" class="filter-dropdown" onchange="window.location.href='?employee_filter=' + this.value">
                            <option value="0">All Employees</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" <?php echo $employee_filter == $employee['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employee['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="?export=csv" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Export Csv
                        </a>
                        <a href="?generate=1" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Generate
                        </a>
                    </div>
                </div>
                
                <?php if (isset($_GET['generate']) && $_GET['generate'] == '1'): ?>
                    <!-- Generate Form -->
                    <form method="POST" style="margin-bottom: 24px;">
                        <input type="hidden" name="action" value="generate">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Employee</label>
                                <select name="employee_id" class="form-control" required>
                                    <option value="0">No Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee['name'] . ' (' . $employee['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Number Of Codes</label>
                                <input type="number" name="number_of_codes" class="form-control" value="1" min="1" max="100" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Max Uses Per Code</label>
                                <input type="number" name="max_uses_per_code" class="form-control" value="1" min="1" max="1000" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Code Prefix</label>
                                <input type="text" name="code_prefix" class="form-control" placeholder="Optional prefix">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Starting Balance (optional)</label>
                                <input type="number" name="starting_balance" class="form-control" step="0.01" min="0" value="0">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Generate
                        </button>
                    </form>
                <?php endif; ?>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>CODE</th>
                            <th>EMPLOYEE</th>
                            <th>STARTING BALANCE</th>
                            <th>MAX USES</th>
                            <th>USED</th>
                            <th>REMAINING</th>
                            <th>ACTIVE</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($codes as $code): ?>
                            <tr>
                                <td style="font-family: monospace; font-weight: 500;">
                                    <?php echo htmlspecialchars($code['code']); ?>
                                </td>
                                <td>
                                    <?php echo $code['employee_name'] ? htmlspecialchars($code['employee_name']) : 'No Employee'; ?>
                                </td>
                                <td>$<?php echo number_format($code['starting_balance'], 2); ?></td>
                                <td><?php echo $code['max_uses']; ?></td>
                                <td><?php echo $code['used_count']; ?></td>
                                <td><?php echo $code['max_uses'] - $code['used_count']; ?></td>
                                <td>
                                    <span class="badge <?php echo $code['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $code['is_active'] ? 'Yes' : 'No'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="?edit=<?php echo $code['id']; ?>">Edit</a>
                                        <a href="?delete=<?php echo $code['id']; ?>" onclick="return confirm('Are you sure you want to delete this invitation code?');">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($codes)): ?>
                    <p style="text-align: center; padding: 40px; color: #6b7280;">
                        No invitation codes found. Generate your first codes above!
                    </p>
                <?php endif; ?>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Showing <?php echo min($offset + 1, $total_records); ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?>
                        </div>
                        <div class="pagination-links">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&employee_filter=<?php echo $employee_filter; ?>">Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="active"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&employee_filter=<?php echo $employee_filter; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&employee_filter=<?php echo $employee_filter; ?>">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
