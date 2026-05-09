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

// Get employee invitation codes from live database data.
$invitationCodes = [];
try {
    $columns = htgTableColumns($conn, 'invitation_codes');
    $conditions = [];
    $params = [];

    if (in_array('employee_id', $columns, true)) {
        $conditions[] = 'employee_id = ?';
        $params[] = $employeeId;
    }
    if (in_array('created_by', $columns, true)) {
        $conditions[] = 'created_by = ?';
        $params[] = $employeeId;
    }

    if ($conditions) {
        $stmt = $conn->prepare("SELECT * FROM invitation_codes WHERE (" . implode(' OR ', $conditions) . ") ORDER BY created_at DESC");
        $stmt->execute($params);
        $invitationCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    // Query failed, continue with empty array
}

// Get recruited users connected to this employee or their invitation codes.
$recruitedUsers = [];
try {
    $userColumns = htgTableColumns($conn, 'users');
    $conditions = [];
    $params = [];

    if (in_array('referred_by', $userColumns, true)) {
        $conditions[] = 'u.referred_by = ?';
        $params[] = $employeeId;
    }
    if (in_array('employee_id', $userColumns, true)) {
        $conditions[] = 'u.employee_id = ?';
        $params[] = $employeeId;
    }

    $employeeCodes = array_values(array_filter(array_map(function ($code) {
        return $code['code'] ?? null;
    }, $invitationCodes)));

    if ($employeeCodes && in_array('invite_code_used', $userColumns, true)) {
        $conditions[] = 'u.invite_code_used IN (' . implode(',', array_fill(0, count($employeeCodes), '?')) . ')';
        $params = array_merge($params, $employeeCodes);
    }
    if ($employeeCodes && in_array('invitation_code_used', $userColumns, true)) {
        $conditions[] = 'u.invitation_code_used IN (' . implode(',', array_fill(0, count($employeeCodes), '?')) . ')';
        $params = array_merge($params, $employeeCodes);
    }

    if ($conditions) {
        $stmt = $conn->prepare("SELECT DISTINCT u.* FROM users u WHERE " . implode(' OR ', $conditions) . " ORDER BY u.created_at DESC");
        $stmt->execute($params);
        $recruitedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    // Query failed, continue with empty array
}

// Get contacts assigned to this employee only; never show sample/static contacts.
$contacts = [];
try {
    $contactColumns = htgTableColumns($conn, 'contacts');
    if (in_array('employee_id', $contactColumns, true)) {
        $stmt = $conn->prepare("SELECT * FROM contacts WHERE employee_id = ? ORDER BY created_at DESC");
        $stmt->execute([$employeeId]);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    // Query failed, continue with empty array
}

$usedInvitationCodes = array_values(array_filter($invitationCodes, function ($code) {
    return (int)($code['used_count'] ?? ($code['total_used'] ?? 0)) > 0;
}));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Details - <?php echo htmlspecialchars(get_site_name()); ?> Admin</title>
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
            <a href="employees.php"><?php echo __t('employees', 'Employees'); ?></a> > <?php echo htmlspecialchars($employee['name']); ?>
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
                        <span class="stat-label"><?php echo __t('codes', 'Codes'); ?></span>
                        <span class="stat-value"><?php echo count($invitationCodes); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php echo __t('recruited', 'Recruited'); ?></span>
                        <span class="stat-value"><?php echo count($recruitedUsers); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php echo __t('contacts', 'Contacts'); ?></span>
                        <span class="stat-value"><?php echo count($contacts); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Invitation Codes Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo __t('used_invitation_codes', 'Used Invitation Codes'); ?></h3>
            </div>
            <div class="card-body">
                <?php if (!empty($usedInvitationCodes)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php echo __t('code', 'CODE'); ?></th>
                                <th><?php echo __t('max_uses', 'MAX USES'); ?></th>
                                <th><?php echo __t('used', 'USED'); ?></th>
                                <th><?php echo __t('active', 'ACTIVE'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usedInvitationCodes as $code): ?>
                                <tr>
                                    <td><a href="invitation-codes.php?employee_filter=<?php echo (int)$employeeId; ?>"><?php echo htmlspecialchars($code['code']); ?></a></td>
                                    <td><?php echo htmlspecialchars($code['usage_limit'] ?? $code['max_users'] ?? $code['max_uses'] ?? '100'); ?></td>
                                    <td><?php echo htmlspecialchars($code['used_count'] ?? $code['total_used'] ?? '0'); ?></td>
                                    <td>
                                        <span class="badge badge-active"><?php echo !empty($code['is_active']) || !empty($code['active']) ? 'Active' : 'Inactive'; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-text"><?php echo __t('no_recruited', 'No Recruited Users'); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recruited Users Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo __t('recruited_users', 'Recruited Users'); ?></h3>
            </div>
            <div class="card-body">
                <?php if (!empty($recruitedUsers)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php echo __t('name', 'Name'); ?></th>
                                <th><?php echo __t('email', 'Email'); ?></th>
                                <th><?php echo __t('code', 'Code'); ?></th>
                                <th><?php echo __t('joined', 'Joined'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recruitedUsers as $user): ?>
                                <tr>
                                    <td><a href="user_view.php?id=<?php echo (int)$user['id']; ?>"><?php echo htmlspecialchars($user['fullname'] ?? $user['name'] ?? 'Unknown'); ?></a></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['invite_code_used'] ?? $user['invitation_code_used'] ?? '-'); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-text"><?php echo __t('no_recruited', 'No Recruited Users'); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Contacts Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo __t('contacts', 'Contacts'); ?></h3>
            </div>
            <div class="card-body">
                <?php if (!empty($contacts)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php echo __t('name', 'Name'); ?></th>
                                <th><?php echo __t('email', 'Email'); ?></th>
                                <th><?php echo __t('phone', 'Phone'); ?></th>
                                <th><?php echo __t('status', 'Status'); ?></th>
                                <th><?php echo __t('message', 'Message'); ?></th>
                                <th><?php echo __t('date', 'Date'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td><a href="contact_view.php?id=<?php echo (int)$contact['id']; ?>"><?php echo htmlspecialchars($contact['name']); ?></a></td>
                                    <td><?php echo htmlspecialchars($contact['email']); ?></td>
                                    <td><?php echo htmlspecialchars($contact['phone'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($contact['status'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars(substr($contact['message'] ?? $contact['notes'] ?? '', 0, 50)) . '...'; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($contact['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-text"><?php echo __t('no_contacts', 'No Contacts'); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

