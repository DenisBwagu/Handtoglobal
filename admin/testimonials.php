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
