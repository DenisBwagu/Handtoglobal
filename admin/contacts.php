<?php
require_once '../config.php';
require_once '../includes/settings_helpers.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();

$msg = "";
$error = "";

// Handle contact operations
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $contact_id = (int)($_GET['id'] ?? 0);
    
    if ($contact_id > 0) {
        try {
            switch ($action) {
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM contacts WHERE id = ?");
                    $stmt->execute([$contact_id]);
                    $msg = "Contact deleted successfully!";
                    break;
            }
        } catch(PDOException $e) {
            $error = "Failed to delete contact: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$employee_filter = $_GET['employee'] ?? 'AllEmployees';
$status_filter = $_GET['status'] ?? 'AllStatus';

// Build query
$where_conditions = [];
$params = [];

if ($employee_filter !== 'AllEmployees') {
    $where_conditions[] = "c.employee_id = ?";
    $params[] = $employee_filter;
}

if ($status_filter !== 'AllStatus') {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get contacts with employee information
$contacts = [];
try {
    $sql = "
        SELECT c.*, e.name as employee_name
        FROM contacts c
        LEFT JOIN employees e ON c.employee_id = e.id
        $where_clause
        ORDER BY c.created_at DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $contacts = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch contacts: " . $e->getMessage();
}

// Get summary data for cards
$summary_data = [];
try {
    // Get employees with contact counts
    $stmt = $conn->prepare("
        SELECT 
            e.id,
            e.name,
            COUNT(c.id) as total_contacts,
            SUM(CASE WHEN c.registered = 1 THEN 1 ELSE 0 END) as registered_count,
            ROUND(
                CASE 
                    WHEN COUNT(c.id) > 0 
                    THEN (SUM(CASE WHEN c.registered = 1 THEN 1 ELSE 0 END) / COUNT(c.id)) * 100 
                    ELSE 0 
                END, 1
            ) as registration_percentage
        FROM employees e
        LEFT JOIN contacts c ON e.id = c.employee_id
        GROUP BY e.id, e.name
        ORDER BY total_contacts DESC
        LIMIT 4
    ");
    $stmt->execute();
    $summary_data = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch summary data: " . $e->getMessage();
}

// Get employees for dropdown
$employees = [];
try {
    $stmt = $conn->prepare("SELECT id, name FROM employees ORDER BY name");
    $stmt->execute();
    $employees = $stmt->fetchAll();
} catch(PDOException $e) {
    // Employees table might not exist, continue without it
    $employees = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #4f46e5;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text: #1a1a1a;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #f5f7fb;
            --white: #ffffff;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            flex: 1;
        }
        
        /* Header Section */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text);
        }
        
        .add-contact-btn {
            background: var(--success);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .add-contact-btn:hover {
            background: #16a34a;
        }
        
        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .summary-card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 12px;
        }
        
        .summary-stats {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: var(--muted);
        }
        
        .summary-stat {
            display: flex;
            flex-direction: column;
        }
        
        .summary-stat-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        
        .summary-stat-label {
            font-size: 12px;
            color: var(--muted);
        }
        
        /* Filters Section */
        .filters-section {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background: var(--white);
            min-width: 150px;
        }
        
        /* Table Card */
        .table-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .contacts-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .contacts-table th {
            background: var(--bg);
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }
        
        .contacts-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        
        .contacts-table tr:hover {
            background: var(--bg);
        }
        
        /* Status Badge */
        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-new {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-contacted {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-converted {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-lost {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Registered Badge */
        .registered-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .registered-yes {
            background: #d1fae5;
            color: #065f46;
        }
        
        .registered-no {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        /* Actions */
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .btn-view {
            background: var(--primary);
            color: white;
        }
        
        .btn-view:hover {
            background: #4338ca;
        }
        
        .btn-edit {
            background: var(--warning);
            color: white;
        }
        
        .btn-edit:hover {
            background: #d97706;
        }
        
        .btn-delete {
            background: var(--danger);
            color: white;
        }
        
        .btn-delete:hover {
            background: #dc2626;
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
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
    </style>
</head>
<body>
    <!-- Topbar Header -->
    <div class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Contacts</div>
        </div>
        <div class="topbar-right">
            <div class="admin-badge">ADMIN</div>
            <div class="profile-info">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Admin Layout -->
    <div class="admin-layout">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
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
            
            <!-- Header Section -->
            <div class="header-section">
                <div class="page-title">Contacts</div>
                <a href="contact_create.php" class="add-contact-btn">Add Contact</a>
            </div>
            
            <!-- Summary Cards -->
            <div class="summary-cards">
                <?php if (!empty($summary_data)): ?>
                    <?php foreach ($summary_data as $data): ?>
                        <div class="summary-card">
                            <div class="summary-card-title"><?php echo htmlspecialchars($data['name'] ?? 'Unassigned'); ?></div>
                            <div class="summary-stats">
                                <div class="summary-stat">
                                    <div class="summary-stat-value"><?php echo $data['total_contacts']; ?></div>
                                    <div class="summary-stat-label">Contacts</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="summary-stat-value"><?php echo $data['registered_count']; ?></div>
                                    <div class="summary-stat-label">Registered</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="summary-stat-value"><?php echo $data['registration_percentage']; ?>%</div>
                                    <div class="summary-stat-label">Percentage</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Show empty summary cards -->
                    <div class="summary-card">
                        <div class="summary-card-title">No Data</div>
                        <div class="summary-stats">
                            <div class="summary-stat">
                                <div class="summary-stat-value">0</div>
                                <div class="summary-stat-label">Contacts</div>
                            </div>
                            <div class="summary-stat">
                                <div class="summary-stat-value">0</div>
                                <div class="summary-stat-label">Registered</div>
                            </div>
                            <div class="summary-stat">
                                <div class="summary-stat-value">0%</div>
                                <div class="summary-stat-label">Percentage</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Filters Section -->
            <div class="filters-section">
                <select class="filter-select" onchange="window.location.href='?employee=' + this.value + '&status=<?php echo $status_filter; ?>'">
                    <option value="AllEmployees" <?php echo $employee_filter === 'AllEmployees' ? 'selected' : ''; ?>>AllEmployees</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee['id']; ?>" <?php echo $employee_filter == $employee['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select class="filter-select" onchange="window.location.href='?employee=<?php echo $employee_filter; ?>&status=' + this.value">
                    <option value="AllStatus" <?php echo $status_filter === 'AllStatus' ? 'selected' : ''; ?>>AllStatus</option>
                    <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="contacted" <?php echo $status_filter === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                    <option value="converted" <?php echo $status_filter === 'converted' ? 'selected' : ''; ?>>Converted</option>
                    <option value="lost" <?php echo $status_filter === 'lost' ? 'selected' : ''; ?>>Lost</option>
                </select>
            </div>
            
            <!-- Table Card -->
            <div class="table-card">
                <?php if (!empty($contacts)): ?>
                    <table class="contacts-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Employee</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($contact['name']); ?></td>
                                    <td><?php echo htmlspecialchars($contact['phone'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($contact['email'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($contact['employee_name'] ?? 'Unassigned'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($contact['status']); ?>">
                                            <?php echo htmlspecialchars($contact['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="registered-badge registered-<?php echo $contact['registered'] ? 'yes' : 'no'; ?>">
                                            <?php echo $contact['registered'] ? 'Yes' : 'No'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($contact['created_at'])); ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="contact_view.php?id=<?php echo $contact['id']; ?>" class="action-btn btn-view">View</a>
                                            <a href="contact_edit.php?id=<?php echo $contact['id']; ?>" class="action-btn btn-edit">Edit</a>
                                            <a href="?action=delete&id=<?php echo $contact['id']; ?>" class="action-btn btn-delete" 
                                               onclick="return confirm('Are you sure you want to delete this contact?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <h3>No records found</h3>
                        <p>There are no contacts to display.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
