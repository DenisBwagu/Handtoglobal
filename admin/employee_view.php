<?php
require_once '../config.php';
require_once '../includes/settings_helpers.php';
require_once '../includes/admin_helpers.php';
require_once '../includes/admin_helpers.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get employee ID from URL
$employeeId = $_GET['id'] ?? null;
if (!$employeeId || !is_numeric($employeeId)) {
    redirect('employees.php');
    exit;
}

// Get database connection
$conn = getConnection();

// Create employees table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch(PDOException $e) {
    // Table creation failed, continue without it
}

// Create invitation_codes table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS invitation_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            max_uses INT DEFAULT 100,
            used_count INT DEFAULT 0,
            bonus_amount DECIMAL(10,2) DEFAULT 0.00,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            is_active TINYINT(1) DEFAULT 1
        )
    ");
} catch(PDOException $e) {
    // Table creation failed, continue without it
}

// Create contacts table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch(PDOException $e) {
    // Table creation failed, continue without it
}

// Get employee details
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee not found");
}

// Get employee invitation codes (check if created_by column exists)
$invitationCodes = [];
try {
    // Check if created_by column exists
    $check_column = $conn->query("SHOW COLUMNS FROM invitation_codes LIKE 'created_by'");
    if ($check_column->rowCount() > 0) {
        $stmt = $conn->prepare("SELECT * FROM invitation_codes WHERE created_by = ? ORDER BY created_at DESC");
        $stmt->execute([$employeeId]);
        $invitationCodes = $stmt->fetchAll();
    }
} catch(PDOException $e) {
    // Query failed, continue with empty array
}

// Get recruited users (users who used invitation codes created by this employee)
$recruitedUsers = [];
try {
    // Check if invite_code_used column exists in users table
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'invite_code_used'");
    if ($check_column->rowCount() > 0) {
        // Check if created_by column exists in invitation_codes
        $check_column = $conn->query("SHOW COLUMNS FROM invitation_codes LIKE 'created_by'");
        if ($check_column->rowCount() > 0) {
            $stmt = $conn->prepare("SELECT u.* FROM users u JOIN invitation_codes ic ON CONVERT(u.invite_code_used USING utf8) = CONVERT(ic.code USING utf8) WHERE ic.created_by = ?");
            $stmt->execute([$employeeId]);
            $recruitedUsers = $stmt->fetchAll();
        }
    }
} catch(PDOException $e) {
    // Query failed, continue with empty array
}

// Get contacts for this employee (check if employee_id column exists)
$contacts = [];
try {
    // Check if employee_id column exists in contacts table
    $check_column = $conn->query("SHOW COLUMNS FROM contacts LIKE 'employee_id'");
    if ($check_column->rowCount() > 0) {
        $stmt = $conn->prepare("SELECT * FROM contacts WHERE employee_id = ? ORDER BY created_at DESC");
        $stmt->execute([$employeeId]);
        $contacts = $stmt->fetchAll();
    } else {
        // If no employee_id column, get all contacts as sample data
        $stmt = $conn->prepare("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 5");
        $stmt->execute();
        $contacts = $stmt->fetchAll();
    }
} catch(PDOException $e) {
    // Query failed, continue with empty array
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Details - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .breadcrumb {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .breadcrumb a {
            color: #6c757d;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            color: #495057;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 24px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #212529;
        }
        
        .card-body {
            padding: 24px;
        }
        
        .employee-profile {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .employee-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #28a745;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 600;
        }
        
        .employee-details h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .employee-details p {
            color: #6c757d;
            font-size: 14px;
        }
        
        .employee-stats {
            display: flex;
            gap: 48px;
            margin-top: 20px;
        }
        
        .stat-item {
            display: flex;
            flex-direction: column;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #212529;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f3f5;
            font-size: 14px;
            color: #495057;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 500;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-active {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .empty-state {
            text-align: center;
            color: #6c757d;
            padding: 40px 20px;
        }
        
        .empty-state-text {
            font-size: 14px;
            color: #6c757d;
        }
    </style>
</head>
<body><?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="employees.php">Employees</a> > <?php echo htmlspecialchars($employee['name']); ?>
        </div>
        
        <!-- Employee Profile Card -->
        <div class="card">
            <div class="card-body">
                <div class="employee-profile">
                    <div class="employee-avatar">
                        <?php echo strtoupper(substr($employee['name'], 0, 1)); ?>
                    </div>
                    <div class="employee-details">
                        <h2><?php echo htmlspecialchars($employee['name']); ?></h2>
                        <p><?php echo htmlspecialchars($employee['email']); ?></p>
                    </div>
                </div>
                
                <div class="employee-stats">
                    <div class="stat-item">
                        <span class="stat-label">Codes</span>
                        <span class="stat-value"><?php echo count($invitationCodes); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Recruited</span>
                        <span class="stat-value"><?php echo count($recruitedUsers); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Contacts</span>
                        <span class="stat-value"><?php echo count($contacts); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Invitation Codes Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">InvitationCodes</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($invitationCodes)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>CODE</th>
                                <th>MAXUSES</th>
                                <th>USED</th>
                                <th>ACTIVE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invitationCodes as $code): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($code['code']); ?></td>
                                    <td><?php echo $code['max_uses'] ?? '100'; ?></td>
                                    <td><?php echo $code['used_count'] ?? '0'; ?></td>
                                    <td>
                                        <span class="badge badge-active">Active</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-text">NoRecruited</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recruited Users Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">RecruitedUsers</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($recruitedUsers)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recruitedUsers as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['fullname'] ?? $user['name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-text">NoRecruited</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Contacts Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Contacts</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($contacts)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Message</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($contact['name']); ?></td>
                                    <td><?php echo htmlspecialchars($contact['email']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($contact['message'], 0, 50)) . '...'; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($contact['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-text">NoContacts</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

