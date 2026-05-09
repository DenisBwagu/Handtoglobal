<?php
require_once '../config.php';
require_once '../includes/settings_helpers.php';
require_once '../includes/admin_helpers.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();

$msg = "";
$error = "";

// Handle form submission
if (isset($_POST['create_combo'])) {
    $user_id = (int)$_POST['user_id'];
    $level = $_POST['level'];
    $start_task_id = (int)$_POST['start_task_id'];
    $end_task_id = (int)$_POST['end_task_id'];
    $multiplier = (float)$_POST['multiplier'];
    $deposit_amount = (float)$_POST['deposit_amount'];
    $message = trim($_POST['message']);
    $activate_now = isset($_POST['activate_now']) ? 1 : 0;
    
    // Validation
    if (empty($user_id) || empty($level) || empty($start_task_id) || empty($end_task_id)) {
        $error = "Please fill all required fields";
    } elseif ($start_task_id > $end_task_id) {
        $error = "End task cannot be before start task";
    } else {
        try {
            // Check if user exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            if (!$stmt->fetch()) {
                $error = "Selected user does not exist";
            } else {
                // Get task titles for validation
                $stmt = $conn->prepare("SELECT id, title FROM tasks WHERE id IN (?, ?) AND level = ?");
                $stmt->execute([$start_task_id, $end_task_id, $level]);
                $tasks = $stmt->fetchAll();
                
                if (count($tasks) !== 2) {
                    $error = "Invalid task selection or level mismatch";
                } else {
                    // Determine status
                    $status = $activate_now ? 'Active' : 'Pending';
                    
                    // Insert combo
                    $stmt = $conn->prepare("
                        INSERT INTO combos (user_id, level, start_task_id, end_task_id, multiplier, deposit_amount, message, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$user_id, $level, $start_task_id, $end_task_id, $multiplier, $deposit_amount, $message, $status]);
                    
                    $msg = "Combo created successfully!";
                    
                    // Redirect to combos list
                    if (empty($error)) {
                        redirect('combos.php?msg=' . urlencode('Combo created successfully!'));
                    }
                }
            }
        } catch(PDOException $e) {
            $error = "Failed to create combo: " . $e->getMessage();
        }
    }
}

// Get users for dropdown
$users = [];
try {
    $stmt = $conn->prepare("SELECT id, fullname, email FROM users ORDER BY fullname");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch users: " . $e->getMessage();
}

// Get levels
$levels = ['Bronze', 'Sliver', 'Gold', 'VIP 1', 'Platinum'];

// Get tasks for selected level
$level_tasks = [];
$selected_level = $_POST['level'] ?? '';
if (!empty($selected_level)) {
    try {
        $stmt = $conn->prepare("SELECT id, title FROM tasks WHERE level = ? ORDER BY id");
        $stmt->execute([$selected_level]);
        $level_tasks = $stmt->fetchAll();
    } catch(PDOException $e) {
        $error = "Failed to fetch tasks: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('create_combo', 'Create Combo'); ?> - <?php echo htmlspecialchars(get_site_name()); ?> Admin</title>
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
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--muted);
            align-self: flex-start;
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Form Card */
        .form-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 520px;
            padding: 30px;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text);
            background: var(--white);
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-control-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text);
            background: var(--white);
            cursor: pointer;
        }
        
        .form-control-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text);
            background: var(--white);
            min-height: 100px;
            resize: vertical;
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        
        .checkbox-input {
            width: 16px;
            height: 16px;
            border: 1px solid var(--border);
            border-radius: 3px;
            cursor: pointer;
            margin-top: 2px;
        }
        
        .checkbox-label {
            flex: 1;
        }
        
        .helper-text {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--success);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: #16a34a;
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            width: 100%;
            max-width: 520px;
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
    </style>
</head>
<body><?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    
    <!-- Admin Layout -->
    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="sidebar">
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
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <?php admin_back_button('combos.php'); ?>
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
            
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="combos.php">Combos</a> > Create
            </div>
            
            <!-- Form Card -->
            <div class="form-card">
                <form method="POST">
                    <!-- User -->
                    <div class="form-group">
                        <label class="form-label">User</label>
                        <select name="user_id" class="form-control-select" required>
                            <option value="">SearchUserPlaceholder</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo (($_POST['user_id'] ?? '') == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['fullname'] . ' (' . $user['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Level -->
                    <div class="form-group">
                        <label class="form-label">Level</label>
                        <select name="level" class="form-control-select" id="levelSelect" required onchange="loadTasks()">
                            <option value="">SelectLevel</option>
                            <?php foreach ($levels as $level): ?>
                                <option value="<?php echo $level; ?>" <?php echo (($_POST['level'] ?? '') == $level) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($level); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- StartTask -->
                    <div class="form-group">
                        <label class="form-label">StartTask</label>
                        <select name="start_task_id" class="form-control-select" id="startTaskSelect" required>
                            <option value="">SelectStart</option>
                            <?php foreach ($level_tasks as $task): ?>
                                <option value="<?php echo $task['id']; ?>" <?php echo (($_POST['start_task_id'] ?? '') == $task['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($task['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- EndTask -->
                    <div class="form-group">
                        <label class="form-label">EndTask</label>
                        <select name="end_task_id" class="form-control-select" id="endTaskSelect" required>
                            <option value="">SelectEnd</option>
                            <?php foreach ($level_tasks as $task): ?>
                                <option value="<?php echo $task['id']; ?>" <?php echo (($_POST['end_task_id'] ?? '') == $task['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($task['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Multiplier -->
                    <div class="form-group">
                        <label class="form-label">Multiplier</label>
                        <input type="number" name="multiplier" class="form-control" value="<?php echo htmlspecialchars($_POST['multiplier'] ?? '6'); ?>" step="0.1" min="1" required>
                    </div>
                    
                    <!-- DepositAmount -->
                    <div class="form-group">
                        <label class="form-label">DepositAmount</label>
                        <input type="number" name="deposit_amount" class="form-control" value="<?php echo htmlspecialchars($_POST['deposit_amount'] ?? '0'); ?>" step="0.01" min="0" required>
                    </div>
                    
                    <!-- Message -->
                    <div class="form-group">
                        <label class="form-label">Message</label>
                        <textarea name="message" class="form-control-textarea" placeholder="MessagePlaceholder"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- ActivateNow -->
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="activate_now" class="checkbox-input" <?php echo (isset($_POST['activate_now'])) ? 'checked' : ''; ?>>
                            <div class="checkbox-label">
                                <label class="form-label" style="margin-bottom: 0;">ActivateNow</label>
                                <div class="helper-text">ActivateNowHelp</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Create Button -->
                    <div class="form-group">
                        <button type="submit" name="create_combo" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function loadTasks() {
            const level = document.getElementById('levelSelect').value;
            const startSelect = document.getElementById('startTaskSelect');
            const endSelect = document.getElementById('endTaskSelect');
            
            // Clear current options
            startSelect.innerHTML = '<option value="">SelectStart</option>';
            endSelect.innerHTML = '<option value="">SelectEnd</option>';
            
            if (level) {
                // Fetch tasks for selected level
                fetch(`combo_create.php?get_tasks=1&level=${encodeURIComponent(level)}`)
                    .then(response => response.json())
                    .then(tasks => {
                        tasks.forEach(task => {
                            startSelect.innerHTML += `<option value="${task.id}">${task.title}</option>`;
                            endSelect.innerHTML += `<option value="${task.id}">${task.title}</option>`;
                        });
                    })
                    .catch(error => console.error('Error loading tasks:', error));
            }
        }
    </script>
</body>
</html>

<?php
// Handle AJAX request for tasks
if (isset($_GET['get_tasks']) && isset($_GET['level'])) {
    $level = $_GET['level'];
    $tasks = [];
    
    try {
        $stmt = $conn->prepare("SELECT id, title FROM tasks WHERE level = ? ORDER BY id");
        $stmt->execute([$level]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $tasks = [];
    }
    
    header('Content-Type: application/json');
    echo json_encode($tasks);
    exit;
}
?>
