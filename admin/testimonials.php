<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
}

// Get database connection
$conn = getConnection();

// Create testimonials table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS testimonials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_name VARCHAR(255) NOT NULL,
            user_email VARCHAR(255),
            user_avatar VARCHAR(255) DEFAULT 'default-avatar.jpg',
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            testimonial_text TEXT NOT NULL,
            user_title VARCHAR(255),
            company VARCHAR(255),
            is_featured TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch(PDOException $e) {
    die("Failed to create testimonials table: " . $e->getMessage());
}

$msg = "";
$error = "";

// Handle testimonial operations
if (isset($_POST['add_testimonial'])) {
    $user_name = trim($_POST['user_name']);
    $user_email = trim($_POST['user_email']);
    $user_title = trim($_POST['user_title']);
    $company = trim($_POST['company']);
    $rating = (int)$_POST['rating'];
    $testimonial_text = trim($_POST['testimonial_text']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    if (empty($user_name) || empty($testimonial_text) || $rating < 1 || $rating > 5) {
        $error = "Please fill all required fields with valid values";
    } elseif (!empty($user_email) && !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO testimonials (user_name, user_email, user_title, company, rating, testimonial_text, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_name, $user_email, $user_title, $company, $rating, $testimonial_text, $is_featured]);
            $msg = "Testimonial added successfully!";
        } catch(PDOException $e) {
            $error = "Failed to add testimonial: " . $e->getMessage();
        }
    }
}

if (isset($_POST['edit_testimonial'])) {
    $id = (int)$_POST['testimonial_id'];
    $user_name = trim($_POST['user_name']);
    $user_email = trim($_POST['user_email']);
    $user_title = trim($_POST['user_title']);
    $company = trim($_POST['company']);
    $rating = (int)$_POST['rating'];
    $testimonial_text = trim($_POST['testimonial_text']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
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
                    <a href="../admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
