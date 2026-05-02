<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
}

// Get database connection
$conn = getConnection();

// Create contacts table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50),
            subject VARCHAR(255),
            message TEXT NOT NULL,
            status ENUM('pending', 'read', 'replied') DEFAULT 'pending',
            priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch(PDOException $e) {
    die("Failed to create contacts table: " . $e->getMessage());
}

$msg = "";
$error = "";

// Handle contact operations
if (isset($_POST['add_contact'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $priority = $_POST['priority'];
    
    if (empty($name) || empty($email) || empty($message)) {
        $error = "Please fill all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO contacts (name, email, phone, subject, message, priority) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $subject, $message, $priority]);
            $msg = "Contact message added successfully!";
        } catch(PDOException $e) {
            $error = "Failed to add contact: " . $e->getMessage();
        }
    }
}

if (isset($_POST['update_status'])) {
    $id = (int)$_POST['contact_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $conn->prepare("UPDATE contacts SET status=? WHERE id=?");
        $stmt->execute([$status, $id]);
        $msg = "Contact status updated successfully!";
    } catch(PDOException $e) {
        $error = "Failed to update status: " . $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM contacts WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Contact deleted successfully!";
    } catch(PDOException $e) {
        $error = "Failed to delete contact: " . $e->getMessage();
    }
}

if (isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    try {
        $stmt = $conn->prepare("UPDATE contacts SET status='read' WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Contact marked as read!";
    } catch(PDOException $e) {
        $error = "Failed to mark as read: " . $e->getMessage();
    }
}

// Get contacts with filters
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';

$contacts = [];
try {
    $sql = "SELECT * FROM contacts WHERE 1=1";
    $params = [];
    
    if ($status_filter !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status_filter;
    }
    
    if ($priority_filter !== 'all') {
        $sql .= " AND priority = ?";
        $params[] = $priority_filter;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $contacts = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch contacts: " . $e->getMessage();
}

// Get statistics
$stats = [];
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM contacts");
    $stmt->execute();
    $stats['total'] = $stmt->fetch()['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contacts WHERE status='pending'");
    $stmt->execute();
    $stats['pending'] = $stmt->fetch()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contacts WHERE status='read'");
    $stmt->execute();
    $stats['read'] = $stmt->fetch()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contacts WHERE status='replied'");
    $stmt->execute();
    $stats['replied'] = $stmt->fetch()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contacts WHERE priority='high'");
    $stmt->execute();
    $stats['high_priority'] = $stmt->fetch()['count'];
    
} catch(PDOException $e) {
    $error = "Failed to fetch statistics: " . $e->getMessage();
}

// Get contact for viewing
$view_contact = null;
if (isset($_GET['view'])) {
    $id = (int)$_GET['view'];
    try {
        $stmt = $conn->prepare("SELECT * FROM contacts WHERE id=?");
        $stmt->execute([$id]);
        $view_contact = $stmt->fetch();
        
        // Mark as read if pending
        if ($view_contact && $view_contact['status'] === 'pending') {
            $stmt = $conn->prepare("UPDATE contacts SET status='read' WHERE id=?");
            $stmt->execute([$id]);
        }
    } catch(PDOException $e) {
        $error = "Failed to fetch contact: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts Management - HandToGlobal Admin</title>
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
        
        .badge-pending {
            background: #ffc107;
            color: #212529;
        }
        
        .badge-read {
            background: #17a2b8;
            color: white;
        }
        
        .badge-replied {
            background: #28a745;
            color: white;
        }
        
        .badge-high {
            background: #dc3545;
            color: white;
        }
        
        .badge-medium {
            background: #ffc107;
            color: #212529;
        }
        
        .badge-low {
            background: #6c757d;
            color: white;
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
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
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
        
        .filter-bar {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .contact-detail {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .contact-detail h3 {
            margin-bottom: 15px;
            color: #667eea;
        }
        
        .contact-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .contact-info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .contact-info-item i {
            color: #667eea;
            width: 20px;
        }
        
        .message-content {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #667eea;
            margin-top: 15px;
        }
        
        .priority-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .priority-high {
            background: #dc3545;
        }
        
        .priority-medium {
            background: #ffc107;
        }
        
        .priority-low {
            background: #6c757d;
        }
        
        .message-preview {
            max-width: 200px;
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
                <h1><i class="fas fa-envelope"></i> Contacts Management</h1>
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
                <div class="stat-label">Total Contacts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['read']; ?></div>
                <div class="stat-label">Read</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['replied']; ?></div>
                <div class="stat-label">Replied</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['high_priority']; ?></div>
                <div class="stat-label">High Priority</div>
            </div>
        </div>

        <!-- Contact Detail View -->
        <?php if ($view_contact): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Contact Details</h2>
                    <a href="contacts.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
                
                <div class="contact-detail">
                    <h3><?php echo htmlspecialchars($view_contact['subject'] ?: 'No Subject'); ?></h3>
                    
                    <div class="contact-info">
                        <div class="contact-info-item">
                            <i class="fas fa-user"></i>
                            <strong>Name:</strong> <?php echo htmlspecialchars($view_contact['name']); ?>
                        </div>
                        <div class="contact-info-item">
                            <i class="fas fa-envelope"></i>
                            <strong>Email:</strong> <?php echo htmlspecialchars($view_contact['email']); ?>
                        </div>
                        <?php if ($view_contact['phone']): ?>
                            <div class="contact-info-item">
                                <i class="fas fa-phone"></i>
                                <strong>Phone:</strong> <?php echo htmlspecialchars($view_contact['phone']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="contact-info-item">
                            <i class="fas fa-flag"></i>
                            <strong>Priority:</strong> 
                            <span class="priority-indicator priority-<?php echo strtolower($view_contact['priority']); ?>"></span>
                            <?php echo ucfirst($view_contact['priority']); ?>
                        </div>
                        <div class="contact-info-item">
                            <i class="fas fa-info-circle"></i>
                            <strong>Status:</strong> 
                            <span class="badge badge-<?php echo strtolower($view_contact['status']); ?>">
                                <?php echo ucfirst($view_contact['status']); ?>
                            </span>
                        </div>
                        <div class="contact-info-item">
                            <i class="fas fa-calendar"></i>
                            <strong>Created:</strong> <?php echo date('M j, Y H:i', strtotime($view_contact['created_at'])); ?>
                        </div>
                    </div>
                    
                    <div class="message-content">
                        <strong>Message:</strong>
                        <p style="margin-top: 10px; line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($view_contact['message'])); ?>
                        </p>
                    </div>
                    
                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="update_status" value="1">
                            <input type="hidden" name="contact_id" value="<?php echo $view_contact['id']; ?>">
                            <select name="status" class="form-control" style="width: 150px; display: inline-block;">
                                <option value="pending" <?php echo $view_contact['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="read" <?php echo $view_contact['status'] === 'read' ? 'selected' : ''; ?>>Read</option>
                                <option value="replied" <?php echo $view_contact['status'] === 'replied' ? 'selected' : ''; ?>>Replied</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-save"></i> Update Status
                            </button>
                        </form>
                        
                        <a href="mailto:<?php echo htmlspecialchars($view_contact['email']); ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-reply"></i> Reply via Email
                        </a>
                        
                        <a href="contacts.php?delete=<?php echo $view_contact['id']; ?>" 
                           class="btn btn-danger btn-sm" 
                           onclick="return confirm('Are you sure you want to delete this contact?')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Add Contact Form -->
        <?php if (!$view_contact): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Add New Contact</h2>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="add_contact" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Name *</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="priority">Priority</label>
                            <select id="priority" name="priority" class="form-control">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" class="form-control" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Contact
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <?php if (!$view_contact): ?>
            <div class="filter-bar">
                <form method="GET" class="form-row">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                            <option value="replied" <?php echo $status_filter === 'replied' ? 'selected' : ''; ?>>Replied</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priority</option>
                            <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Contacts List -->
            <div class="card">
                <div class="card-header">
                    <h2>Contacts List</h2>
                    <div>
                        <button class="btn btn-success btn-sm" onclick="window.location.reload()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <?php if ($stats['pending'] > 0): ?>
                            <span class="alert alert-warning" style="display: inline-block; padding: 8px 12px; margin: 0;">
                                <i class="fas fa-exclamation-triangle"></i> 
                                <?php echo $stats['pending']; ?> pending contact(s)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): ?>
                            <tr>
                                <td><?php echo $contact['id']; ?></td>
                                <td><?php echo htmlspecialchars($contact['name']); ?></td>
                                <td><?php echo htmlspecialchars($contact['email']); ?></td>
                                <td>
                                    <?php 
                                    $subject = $contact['subject'] ?: 'No Subject';
                                    echo htmlspecialchars(substr($subject, 0, 30)) . (strlen($subject) > 30 ? '...' : '');
                                    ?>
                                </td>
                                <td>
                                    <span class="priority-indicator priority-<?php echo strtolower($contact['priority']); ?>"></span>
                                    <span class="badge badge-<?php echo strtolower($contact['priority']); ?>">
                                        <?php echo ucfirst($contact['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($contact['status']); ?>">
                                        <?php echo ucfirst($contact['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($contact['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="contacts.php?view=<?php echo $contact['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($contact['status'] === 'pending'): ?>
                                            <a href="contacts.php?mark_read=<?php echo $contact['id']; ?>" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-check"></i> Mark Read
                                            </a>
                                        <?php endif; ?>
                                        <a href="contacts.php?delete=<?php echo $contact['id']; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Are you sure you want to delete this contact?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($contacts)): ?>
                    <p style="text-align: center; padding: 40px; color: #666;">
                        No contacts found for the selected criteria.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
