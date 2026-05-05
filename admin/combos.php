<?php
require_once '../config.php';
require_once '../get_setting.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();

$msg = "";
$error = "";

// Handle combo operations
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $combo_id = (int)($_GET['id'] ?? 0);
    
    if ($combo_id > 0) {
        try {
            switch ($action) {
                case 'activate':
                    // Resolve all pending user combos for this combo
                    $stmt = $conn->prepare("
                        UPDATE user_combo_status 
                        SET status = 'activated', updated_at = NOW() 
                        WHERE combo_id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$combo_id]);
                    $msg = "Combo activated and all pending users cleared!";
                    break;
                    
                case 'deactivate':
                    $stmt = $conn->prepare("UPDATE combos SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$combo_id]);
                    $msg = "Combo deactivated successfully!";
                    break;
                    
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM combos WHERE id = ?");
                    $stmt->execute([$combo_id]);
                    $msg = "Combo deleted successfully!";
                    break;
            }
        } catch(PDOException $e) {
            $error = "Failed to update combo: " . $e->getMessage();
        }
    }
}

// Handle new combo creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_combo'])) {
    $level = trim($_POST['level'] ?? '');
    $start_task = (int)($_POST['start_task'] ?? 0);
    $end_task = (int)($_POST['end_task'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if (empty($level) || $start_task <= 0 || $end_task <= 0 || empty($message)) {
        $error = "Please fill all required fields.";
    } elseif ($start_task > $end_task) {
        $error = "Start task must be less than or equal to end task.";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO combos (level, start_task, end_task, amount, message, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$level, $start_task, $end_task, $amount, $message, $status]);
            $msg = "Combo created successfully!";
        } catch(PDOException $e) {
            $error = "Failed to create combo: " . $e->getMessage();
        }
    }
}

// Get all combos with pending user count
$stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM user_combo_status ucs WHERE ucs.combo_id = c.id AND ucs.status = 'pending') as pending_users
    FROM combos c
    WHERE c.level LIKE :search OR c.start_task LIKE :search OR c.end_task LIKE :search OR c.amount LIKE :search OR c.message LIKE :search
    ORDER BY c.created_at DESC
");
$stmt->execute(['search' => '%' . $search . '%']);
$combos = $stmt->fetchAll();

// Get levels for dropdown
$levels = ['Bronze', 'Silver', 'Gold', 'Platinum'];
$siteName = get_setting('site_name', 'HandToGlobal');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Combos - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #212529;
            line-height: 1.6;
        }
        
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: white;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1000;
        }
        
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .menu-icon {
            font-size: 18px;
            color: #6c757d;
            cursor: pointer;
        }
        
        .topbar-title {
            font-size: 18px;
            font-weight: 600;
            color: #212529;
        }
        
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .admin-badge {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .language-form select {
            padding: 6px 10px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .theme-toggle {
            font-size: 16px;
            color: #6c757d;
            cursor: pointer;
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .profile-avatar {
            width: 32px;
            height: 32px;
            background: #007bff;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }
        
        .profile-name {
            font-size: 14px;
            font-weight: 500;
        }
        
        .admin-layout {
            display: flex;
            min-height: 100vh;
            padding-top: 60px;
        }
        
        .sidebar {
            width: 250px;
            background: #343a40;
            color: white;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #495057;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar-header h2 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        
        .sidebar-section {
            padding: 15px 0;
        }
        
        .sidebar-section-title {
            padding: 0 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #adb5bd;
            margin-bottom: 10px;
        }
        
        .sidebar-menu {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 2px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #adb5bd;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #495057;
            color: white;
        }
        
        .main-content {
            flex: 1;
            padding: 30px;
            background: #f8f9fa;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #212529;
            margin-bottom: 8px;
        }
        
        .page-header p {
            color: #6c757d;
            font-size: 16px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #212529;
            margin: 0;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .search-container {
            position: relative;
        }
        
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .search-input {
            padding: 8px 12px 8px 36px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 14px;
            width: 250px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        
        .badge-inactive {
            background: #f8d7da;
            color: #842029;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #664d03;
        }
        
        .actions {
            display: flex;
            gap: 12px;
        }
        
        .action-link {
            color: #495057;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.15s ease;
        }
        
        .action-link:hover {
            color: #212529;
        }
        
        .action-link.delete {
            color: #dc3545;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            position: relative;
        }
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #6c757d;
        }
        
        .modal-footer {
            margin-top: 20px;
            text-align: right;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #495057;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
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
            <div class="topbar-title">Combos</div>
        </div>
        <div class="topbar-right">
            <div class="admin-badge">ADMIN</div>
            <form class="language-form" method="post" action="../language_action.php">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/combos.php'); ?>">
                <input type="hidden" name="context" value="admin">
                <select name="language" onchange="this.form.submit()">
                    <?php foreach (['english' => 'English', 'chinese' => 'Chinese'] as $code => $label): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($_SESSION['admin_language'] ?? $_SESSION['language'] ?? 'english') === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </div>
            <a href="/handtoglobal/admin/logout.php" style="display:inline-flex;align-items:center;gap:8px;height:34px;padding:0 12px;border-radius:6px;background:#dc2626;color:#fff;text-decoration:none;font-size:13px;font-weight:700;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <div class="profile-info">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></div>
                <div>
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
                    <li><a href="combos.php" class="active"><i class="fas fa-link"></i> Combos</a></li>
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
            
            <div class="page-header">
                <h1>Combos Management</h1>
                <p>Create and manage combo offers for users</p>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">All Combos</h1>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <button class="btn btn-primary" onclick="showCreateModal()">
                            <i class="fas fa-plus"></i> New Combo
                        </button>
                        <form method="GET" style="display: flex; gap: 8px;">
                            <div class="search-container">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" name="search" class="search-input" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>LEVEL</th>
                                <th>START TASK</th>
                                <th>END TASK</th>
                                <th>AMOUNT</th>
                                <th>MESSAGE</th>
                                <th>STATUS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($combos)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #6c757d;">
                                        <i class="fas fa-link" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                                        No combos found. Create your first combo to get started.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($combos as $combo): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($combo['level']); ?></td>
                                        <td><?php echo $combo['start_task']; ?></td>
                                        <td><?php echo $combo['end_task']; ?></td>
                                        <td>$<?php echo number_format($combo['amount'], 2); ?></td>
                                        <td>
                                            <span style="max-width: 200px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($combo['message']); ?>">
                                                <?php echo htmlspecialchars(substr($combo['message'], 0, 50)) . (strlen($combo['message']) > 50 ? '...' : ''); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $combo['status']; ?>">
                                                <?php echo htmlspecialchars($combo['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <?php if ($combo['status'] === 'active' && $combo['pending_users'] > 0): ?>
                                                    <a href="?action=activate&id=<?php echo $combo['id']; ?>" class="action-link">Activate</a>
                                                <?php endif; ?>
                                                <a href="?action=delete&id=<?php echo $combo['id']; ?>" class="action-link delete" onclick="return confirm('Are you sure you want to delete this combo?')">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Combo Modal -->
    <div class="modal" id="createModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create New Combo</h3>
                <button class="modal-close" onclick="hideCreateModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="level">Level</label>
                        <select id="level" name="level" required onchange="loadTasksByLevel()">
                            <option value="">Select Level</option>
                            <?php foreach ($levels as $level): ?>
                                <option value="<?php echo htmlspecialchars($level); ?>"><?php echo htmlspecialchars($level); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="start_task">Start Task</label>
                        <select id="start_task" name="start_task" required>
                            <option value="">Select Level First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="end_task">End Task</label>
                        <select id="end_task" name="end_task" required>
                            <option value="">Select Level First</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="amount">Amount ($)</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0" value="0" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" required placeholder="Enter combo message for users" rows="3"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideCreateModal()">Cancel</button>
                    <button type="submit" name="create_combo" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Combo
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showCreateModal() {
            document.getElementById('createModal').style.display = 'block';
        }
        
        function hideCreateModal() {
            document.getElementById('createModal').style.display = 'none';
        }
        
        function loadTasksByLevel() {
            const level = document.getElementById('level').value;
            const startSelect = document.getElementById('start_task');
            const endSelect = document.getElementById('end_task');
            
            // Clear existing options
            startSelect.innerHTML = '<option value="">Loading...</option>';
            endSelect.innerHTML = '<option value="">Loading...</option>';
            
            if (!level) {
                startSelect.innerHTML = '<option value="">Select Level First</option>';
                endSelect.innerHTML = '<option value="">Select Level First</option>';
                return;
            }
            
            fetch('get_tasks_by_level.php?level=' + encodeURIComponent(level))
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error:', data.error);
                        startSelect.innerHTML = '<option value="">Error loading tasks</option>';
                        endSelect.innerHTML = '<option value="">Error loading tasks</option>';
                        return;
                    }
                    
                    // Populate both dropdowns
                    let options = '<option value="">Select Task</option>';
                    data.forEach(task => {
                        options += `<option value="${task.id}">${task.title}</option>`;
                    });
                    
                    startSelect.innerHTML = options;
                    endSelect.innerHTML = options;
                })
                .catch(error => {
                    console.error('Error:', error);
                    startSelect.innerHTML = '<option value="">Error loading tasks</option>';
                    endSelect.innerHTML = '<option value="">Error loading tasks</option>';
                });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('createModal');
            if (event.target === modal) {
                hideCreateModal();
            }
        }
    </script>
</body>
</html>
