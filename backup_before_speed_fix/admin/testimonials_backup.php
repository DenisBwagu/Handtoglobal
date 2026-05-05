<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('login.php');
}

// Get database connection
$conn = getConnection();

$msg = "";
$error = "";

// Handle testimonial operations
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $testimonial_id = (int)($_GET['id'] ?? 0);
    
    if ($testimonial_id > 0) {
        try {
            switch ($action) {
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM testimonials WHERE id = ?");
                    $stmt->execute([$testimonial_id]);
                    $msg = "Testimonial deleted successfully!";
                    break;
            }
        } catch(PDOException $e) {
            $error = "Failed to delete testimonial: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$type_filter = $_GET['type'] ?? 'AllTypes';

// Build query
$where_conditions = [];
$params = [];

if ($type_filter !== 'AllTypes') {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Get total count
$total_testimonials = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM testimonials $where_clause");
    $stmt->execute($params);
    $total_testimonials = $stmt->fetch()['total'];
} catch(PDOException $e) {
    $error = "Failed to count testimonials: " . $e->getMessage();
}

// Get testimonials
$testimonials = [];
try {
    $sql = "
        SELECT * FROM testimonials 
        $where_clause
        ORDER BY display_order ASC, created_at DESC 
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $testimonials = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch testimonials: " . $e->getMessage();
}

// Calculate pagination info
$total_pages = ceil($total_testimonials / $per_page);
$start_item = $total_testimonials > 0 ? $offset + 1 : 0;
$end_item = min($offset + $per_page, $total_testimonials);

// Get unique types for filter
$types = [];
try {
    $stmt = $conn->prepare("SELECT DISTINCT type FROM testimonials ORDER BY type");
    $stmt->execute();
    $types = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $types = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials - HandToGlobal Admin</title>
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
        
        .add-testimonial-btn {
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
        
        .add-testimonial-btn:hover {
            background: #16a34a;
        }
        
        /* Filter Section */
        .filter-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        .testimonials-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .testimonials-table th {
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
        
        .testimonials-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        
        .testimonials-table tr:hover {
            background: var(--bg);
        }
        
        /* Type Badge */
        .type-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #dbeafe;
            color: #1e40af;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-inactive {
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
        
        /* Pagination */
        .pagination-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: var(--bg);
            font-size: 14px;
            color: var(--muted);
        }
        
        .pagination-controls {
            display: flex;
            gap: 8px;
        }
        
        .pagination-btn {
            padding: 6px 12px;
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--text);
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .pagination-btn:hover {
            background: var(--bg);
        }
        
        .pagination-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
            <div class="topbar-title">Testimonials</div>
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
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-hand-holding-usd"></i>
                <h2>Hand to Global</h2>
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
                    <li><a href="withdrawals.php"><i class="fas fa-arrow-up"></i> Withdrawals</a></li>
                </ul>
            </div>
            
            <!-- MONITORING Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">MONITORING</div>
                <ul class="sidebar-menu">
                    <li><a href="contacts.php"><i class="fas fa-address-book"></i> Contacts</a></li>
                    <li><a href="testimonials.php" class="active"><i class="fas fa-comments"></i> Testimonials</a></li>
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
                <div class="page-title">Testimonials</div>
                <a href="testimonial_create.php" class="add-testimonial-btn">Add</a>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <select class="filter-select" onchange="window.location.href='?type=' + this.value">
                    <option value="AllTypes" <?php echo $type_filter === 'AllTypes' ? 'selected' : ''; ?>>AllTypes</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $type_filter === $type ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($type)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Table Card -->
            <div class="table-card">
                <?php if (!empty($testimonials)): ?>
                    <table class="testimonials-table">
                        <thead>
                            <tr>
                                <th>NAME</th>
                                <th>TYPE</th>
                                <th>CONTENT</th>
                                <th>IMAGE</th>
                                <th>STATUS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($testimonials as $testimonial): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($testimonial['name']); ?></td>
                                    <td>
                                        <span class="type-badge">
                                            <?php echo htmlspecialchars(ucfirst($testimonial['type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $content = $testimonial['content'];
                                        echo strlen($content) > 50 ? htmlspecialchars(substr($content, 0, 50)) . '...' : htmlspecialchars($content);
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo $testimonial['image'] ? '✓' : '–'; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $testimonial['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $testimonial['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="testimonial_edit.php?id=<?php echo $testimonial['id']; ?>" class="action-btn btn-edit">Edit</a>
                                            <a href="?action=delete&id=<?php echo $testimonial['id']; ?>" class="action-btn btn-delete" 
                                               onclick="return confirm('Are you sure you want to delete this testimonial?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>No testimonials found</h3>
                        <p>There are no testimonials to display.</p>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <div class="pagination-section">
                    <div>
                        Showing <?php echo $start_item; ?> to <?php echo $end_item; ?> of <?php echo $total_testimonials; ?>
                    </div>
                    <div class="pagination-controls">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $type_filter; ?>" class="pagination-btn">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="pagination-btn active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&type=<?php echo $type_filter; ?>" class="pagination-btn"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $type_filter; ?>" class="pagination-btn">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($user_name) || empty($testimonial_text) || $rating < 1 || $rating > 5) {
        $error = "Please fill all required fields with valid values";
    } elseif (!empty($user_email) && !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE testimonials SET user_name=?, user_email=?, user_title=?, company=?, rating=?, testimonial_text=?, is_featured=?, is_active=? WHERE id=?");
            $stmt->execute([$user_name, $user_email, $user_title, $company, $rating, $testimonial_text, $is_featured, $is_active, $id]);
            $msg = "Testimonial updated successfully!";
        } catch(PDOException $e) {
            $error = "Failed to update testimonial: " . $e->getMessage();
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM testimonials WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Testimonial deleted successfully!";
    } catch(PDOException $e) {
        $error = "Failed to delete testimonial: " . $e->getMessage();
    }
}

if (isset($_GET['toggle_featured'])) {
    $id = (int)$_GET['toggle_featured'];
    try {
        $stmt = $conn->prepare("UPDATE testimonials SET is_featured = NOT is_featured WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Testimonial featured status updated successfully!";
    } catch(PDOException $e) {
        $error = "Failed to update featured status: " . $e->getMessage();
    }
}

if (isset($_GET['toggle_active'])) {
    $id = (int)$_GET['toggle_active'];
    try {
        $stmt = $conn->prepare("UPDATE testimonials SET is_active = NOT is_active WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Testimonial active status updated successfully!";
    } catch(PDOException $e) {
        $error = "Failed to update active status: " . $e->getMessage();
    }
}

// Get testimonials for display
$testimonials = [];
try {
    $stmt = $conn->prepare("SELECT * FROM testimonials ORDER BY is_featured DESC, created_at DESC");
    $stmt->execute();
    $testimonials = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch testimonials: " . $e->getMessage();
}

// Get statistics
$stats = [];
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM testimonials");
    $stmt->execute();
    $stats['total'] = $stmt->fetch()['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM testimonials WHERE is_active=1");
    $stmt->execute();
    $stats['active'] = $stmt->fetch()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM testimonials WHERE is_featured=1");
    $stmt->execute();
    $stats['featured'] = $stmt->fetch()['count'];
    
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM testimonials WHERE is_active=1");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['avg_rating'] = $result['avg_rating'] ? round($result['avg_rating'], 1) : 0;
    
} catch(PDOException $e) {
    $error = "Failed to fetch statistics: " . $e->getMessage();
}

// Get testimonial for editing
$edit_testimonial = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    try {
        $stmt = $conn->prepare("SELECT * FROM testimonials WHERE id=?");
        $stmt->execute([$id]);
        $edit_testimonial = $stmt->fetch();
    } catch(PDOException $e) {
        $error = "Failed to fetch testimonial for editing: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials Management - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .nav-menu {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102,126,234,0.2);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-active {
            background: #28a745;
            color: white;
        }
        
        .badge-inactive {
            background: #6c757d;
            color: white;
        }
        
        .badge-featured {
            background: #ffc107;
            color: #212529;
        }
        
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .testimonial-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 4px solid #667eea;
        }
        
        .testimonial-text {
            font-style: italic;
            color: #555;
            line-height: 1.6;
        }
        
        .testimonial-meta {
            margin-top: 10px;
            font-size: 12px;
            color: #666;
        }
        
        .rating {
            color: #ffc107;
            font-size: 14px;
        }
        
        .rating .empty {
            color: #ddd;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .testimonial-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .testimonial-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .testimonial-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .testimonial-info h4 {
            margin: 0;
            color: #333;
        }
        
        .testimonial-info p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .text-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav-menu">
                <h1><i class="fas fa-quote-left"></i> Testimonials Management</h1>
                <div class="nav-links">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
                    <a href="combos.php"><i class="fas fa-layer-group"></i> Combos</a>
                    <a href="invitation_codes.php"><i class="fas fa-ticket-alt"></i> Codes</a>
                    <a href="finance_analysis.php"><i class="fas fa-chart-line"></i> Finance</a>
                    <a href="deposits.php"><i class="fas fa-dollar-sign"></i> Deposits</a>
                    <a href="withdrawals.php"><i class="fas fa-money-bill-wave"></i> Withdrawals</a>
                    <a href="contacts.php"><i class="fas fa-envelope"></i> Contacts</a>
                    <a href="testimonials.php"><i class="fas fa-quote-left"></i> Testimonials</a>
                    <a href="/handtoglobal/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
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

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Testimonials</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['featured']; ?></div>
                <div class="stat-label">Featured</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    for ($i = 1; $i <= 5; $i++) {
                        echo $i <= $stats['avg_rating'] ? '<i class="fas fa-star rating"></i>' : '<i class="fas fa-star rating empty"></i>';
                    }
                    ?>
                </div>
                <div class="stat-label">Average Rating (<?php echo $stats['avg_rating']; ?>)</div>
            </div>
        </div>

        <!-- Add/Edit Testimonial Form -->
        <div class="card">
            <div class="card-header">
                <h2><?php echo $edit_testimonial ? 'Edit Testimonial' : 'Add New Testimonial'; ?></h2>
                <?php if ($edit_testimonial): ?>
                    <a href="testimonials.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
            
            <form method="POST">
                <?php if ($edit_testimonial): ?>
                    <input type="hidden" name="edit_testimonial" value="1">
                    <input type="hidden" name="testimonial_id" value="<?php echo $edit_testimonial['id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="add_testimonial" value="1">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="user_name">User Name *</label>
                        <input type="text" id="user_name" name="user_name" class="form-control" 
                               value="<?php echo $edit_testimonial ? htmlspecialchars($edit_testimonial['user_name']) : ''; ?>" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="user_email">User Email</label>
                        <input type="email" id="user_email" name="user_email" class="form-control" 
                               value="<?php echo $edit_testimonial ? htmlspecialchars($edit_testimonial['user_email']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="user_title">User Title</label>
                        <input type="text" id="user_title" name="user_title" class="form-control" 
                               value="<?php echo $edit_testimonial ? htmlspecialchars($edit_testimonial['user_title']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="company">Company</label>
                        <input type="text" id="company" name="company" class="form-control" 
                               value="<?php echo $edit_testimonial ? htmlspecialchars($edit_testimonial['company']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="rating">Rating *</label>
                    <select id="rating" name="rating" class="form-control" style="width: 200px;" required>
                        <option value="">Select Rating</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $edit_testimonial && $edit_testimonial['rating'] == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="testimonial_text">Testimonial Text *</label>
                    <textarea id="testimonial_text" name="testimonial_text" class="form-control" required><?php 
                        echo $edit_testimonial ? htmlspecialchars($edit_testimonial['testimonial_text']) : ''; 
                    ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_featured" name="is_featured" value="1" 
                                   <?php echo $edit_testimonial && $edit_testimonial['is_featured'] ? 'checked' : ''; ?>>
                            <label for="is_featured">Featured Testimonial</label>
                        </div>
                    </div>
                    <?php if ($edit_testimonial): ?>
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_active" name="is_active" value="1" 
                                       <?php echo $edit_testimonial['is_active'] ? 'checked' : ''; ?>>
                                <label for="is_active">Active</label>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $edit_testimonial ? 'Update Testimonial' : 'Add Testimonial'; ?>
                </button>
            </form>
        </div>

        <!-- Testimonials List -->
        <div class="card">
            <div class="card-header">
                <h2>All Testimonials</h2>
                <button class="btn btn-success btn-sm" onclick="window.location.reload()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Rating</th>
                        <th>Testimonial</th>
                        <th>Status</th>
                        <th>Featured</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($testimonials as $testimonial): ?>
                        <tr>
                            <td><?php echo $testimonial['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($testimonial['user_name']); ?></strong>
                                <?php if ($testimonial['user_title']): ?>
                                    <br><small><?php echo htmlspecialchars($testimonial['user_title']); ?></small>
                                <?php endif; ?>
                                <?php if ($testimonial['company']): ?>
                                    <br><small><?php echo htmlspecialchars($testimonial['company']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $testimonial['rating'] ? '' : 'empty'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-preview">
                                    <?php echo htmlspecialchars(substr($testimonial['testimonial_text'], 0, 100)) . '...'; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $testimonial['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $testimonial['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $testimonial['is_featured'] ? 'featured' : 'inactive'; ?>">
                                    <?php echo $testimonial['is_featured'] ? 'Featured' : 'Regular'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($testimonial['created_at'])); ?></td>
                            <td>
                                <div class="actions">
                                    <a href="testimonials.php?edit=<?php echo $testimonial['id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="testimonials.php?toggle_featured=<?php echo $testimonial['id']; ?>" 
                                       class="btn btn-secondary btn-sm" 
                                       title="<?php echo $testimonial['is_featured'] ? 'Remove from Featured' : 'Add to Featured'; ?>">
                                        <i class="fas fa-<?php echo $testimonial['is_featured'] ? 'star' : 'star'; ?>"></i>
                                    </a>
                                    <a href="testimonials.php?toggle_active=<?php echo $testimonial['id']; ?>" 
                                       class="btn btn-secondary btn-sm" 
                                       title="<?php echo $testimonial['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas fa-<?php echo $testimonial['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                    </a>
                                    <a href="testimonials.php?delete=<?php echo $testimonial['id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this testimonial?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($testimonials)): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No testimonials found. Add your first testimonial above!
                </p>
            <?php endif; ?>
        </div>

        <!-- Featured Testimonials Preview -->
        <?php if (!empty($testimonials)): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Featured Testimonials Preview</h2>
                </div>
                
                <?php 
                $featured_testimonials = array_filter($testimonials, fn($t) => $t['is_featured'] && $t['is_active']);
                foreach (array_slice($featured_testimonials, 0, 3) as $testimonial): 
                ?>
                    <div class="testimonial-card">
                        <div class="testimonial-header">
                            <div class="testimonial-avatar">
                                <?php echo strtoupper(substr($testimonial['user_name'], 0, 1)); ?>
                            </div>
                            <div class="testimonial-info">
                                <h4><?php echo htmlspecialchars($testimonial['user_name']); ?></h4>
                                <p>
                                    <?php 
                                    $info_parts = [];
                                    if ($testimonial['user_title']) $info_parts[] = htmlspecialchars($testimonial['user_title']);
                                    if ($testimonial['company']) $info_parts[] = htmlspecialchars($testimonial['company']);
                                    echo implode(' | ', $info_parts);
                                    ?>
                                </p>
                            </div>
                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $testimonial['rating'] ? '' : 'empty'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="testimonial-text">
                            "<?php echo htmlspecialchars($testimonial['testimonial_text']); ?>"
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($featured_testimonials)): ?>
                    <p style="text-align: center; padding: 40px; color: #666;">
                        No featured testimonials found.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
