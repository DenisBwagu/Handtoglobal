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

// Handle combo operations
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $combo_id = (int)($_GET['id'] ?? 0);
    
    if ($combo_id > 0) {
        try {
            switch ($action) {
                case 'activate':
                    // Set combo to active and resolve all pending user combos for this combo
                    $stmt = $conn->prepare("UPDATE combos SET status = 'deactivated', is_active = 0, is_active = 1, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$combo_id]);
                    
                    $stmt = $conn->prepare("
                        UPDATE user_combo_status 
                        SET status = 'activated', updated_at = NOW() 
                        WHERE combo_id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$combo_id]);
                    $msg = "Combo activated and all pending users cleared!";
                    break;
                    
                case 'deactivate':
                    $stmt = $conn->prepare("UPDATE combos SET status = 'inactive', is_active = 0, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$combo_id]);
                    $stmt = $conn->prepare("
                        UPDATE user_combo_status
                        SET status = 'cleared', updated_at = NOW()
                        WHERE combo_id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$combo_id]);
                    $msg = "Combo deactivated successfully!";
                    break;
                    
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM user_combo_status WHERE combo_id = ?");
                    $stmt->execute([$combo_id]);
                    $stmt = $conn->prepare("DELETE FROM combos WHERE id = ?");
                    $stmt->execute([$combo_id]);
                    $msg = "Combo deleted successfully!";
                    break;
            }
            header("Location: combos.php?msg=" . urlencode($msg));
            exit;
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
    $multiplier = (float)($_POST['multiplier'] ?? 1);
    $message = trim($_POST['message'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    
    if (empty($level) || $start_task <= 0 || $end_task <= 0 || empty($message)) {
        $error = "Please fill all required fields.";
    } elseif ($start_task > $end_task) {
        $error = "Start task must be less than or equal to end task.";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than 0.";
    } else {
        try {
            // Check for duplicate combo (same level and task range)
            $stmt = $conn->prepare("
                SELECT id FROM combos 
                WHERE level = ? AND start_task = ? AND end_task = ?
            ");
            $stmt->execute([$level, $start_task, $end_task]);
            if ($stmt->fetch()) {
                $error = "A combo with the same level and task range already exists.";
            } else {
                $is_active = strtolower($status) === 'active' ? 1 : 0;
                $stmt = $conn->prepare("
                    INSERT INTO combos (level, start_task, end_task, amount, multiplier, user_id, message, status, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$level, $start_task, $end_task, $amount, $multiplier, $user_id, $message, $status, $is_active]);
                $msg = "Combo created successfully!";
                
                // Redirect to prevent form resubmission
                header("Location: combos.php?msg=" . urlencode($msg));
                exit;
            }
        } catch(PDOException $e) {
            $error = "Failed to create combo: " . $e->getMessage();
        }
    }
}

// Get GET parameters
$search = $_GET['search'] ?? '';
$msg = $_GET['msg'] ?? $msg ?? '';

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

// Get levels for dropdown from database
$stmt = $conn->prepare("SELECT DISTINCT level FROM tasks WHERE active = 1 ORDER BY level");
$stmt->execute();
$levels = $stmt->fetchAll(PDO::FETCH_COLUMN);
$siteName = get_setting('site_name', 'HandToGlobal');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Combos - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="includes/admin_styles.css">
    <style>
        .user-select-container {
            position: relative;
        }
        
        .user-search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .user-search-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f3f5;
        }
        
        .user-search-item:hover {
            background: #f8f9fa;
        }
        
        .user-search-item:last-child {
            border-bottom: none;
        }
        
        .user-name {
            font-weight: 500;
            color: #212529;
            font-size: 14px;
        }
        
        .user-email {
            color: #6c757d;
            font-size: 12px;
            margin-top: 2px;
        }
        
        .action-link.warning {
            color: #f59e0b !important;
            border-color: #f59e0b !important;
            background: rgba(245, 158, 11, 0.1) !important;
        }
        
        .action-link.warning:hover {
            background: rgba(245, 158, 11, 0.2) !important;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    
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
                                <th>MULTIPLIER</th>
                                <th>USER</th>
                                <th>STATUS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($combos)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: #6c757d;">
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
                                            <?php echo number_format($combo['multiplier'], 1); ?>x
                                        </td>
                                        <td>
                                            <?php 
                                            if ($combo['user_id']) {
                                                // Get user info for display
                                                $stmt = $conn->prepare("SELECT fullname, email FROM users WHERE id = ?");
                                                $stmt->execute([$combo['user_id']]);
                                                $user = $stmt->fetch();
                                                if ($user) {
                                                    echo htmlspecialchars($user['fullname'] . ' - ' . $user['email']);
                                                } else {
                                                    echo 'User ID: ' . $combo['user_id'];
                                                }
                                            } else {
                                                echo 'All Users';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $combo['status']; ?>">
                                                <?php echo htmlspecialchars($combo['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="edit_combo.php?id=<?php echo $combo['id']; ?>" class="action-link">Edit</a>
                                                <?php if ($combo['status'] === 'active' && $combo['is_active'] == 1): ?>
                                                    <a href="?action=deactivate&id=<?php echo $combo['id']; ?>" class="action-link warning">Deactivate</a>
                                                <?php else: ?>
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
                        <label for="user_select">Select User</label>
                        <div class="user-select-container">
                            <input type="text" id="user_select" name="user_select" placeholder="Search user by name or email..." autocomplete="off">
                            <input type="hidden" id="user_id" name="user_id" value="">
                            <div id="user_search_results" class="user-search-results" style="display: none;"></div>
                        </div>
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
                <div class="form-group">
                    <label for="multiplier">Multiplier</label>
                    <input type="number" id="multiplier" name="multiplier" step="0.1" min="1" value="1" required>
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
                    
                    // Populate both dropdowns with task numbering
                    let options = '<option value="">Select Task</option>';
                    data.forEach(task => {
                        options += `<option value="${task.task_number}">Task ${task.task_number} - ${task.title}</option>`;
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
        
        // User search functionality
        let userSearchTimeout;
        const userSelectInput = document.getElementById('user_select');
        const userIdInput = document.getElementById('user_id');
        const userSearchResults = document.getElementById('user_search_results');
        
        userSelectInput.addEventListener('input', function() {
            clearTimeout(userSearchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                userSearchResults.style.display = 'none';
                return;
            }
            
            userSearchTimeout = setTimeout(() => {
                fetch('search_users.php?query=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error:', data.error);
                            userSearchResults.style.display = 'none';
                            return;
                        }
                        
                        displayUserSearchResults(data);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        userSearchResults.style.display = 'none';
                    });
            }, 300);
        });
        
        function displayUserSearchResults(users) {
            if (users.length === 0) {
                userSearchResults.innerHTML = '<div class="user-search-item">No users found</div>';
            } else {
                let html = '';
                users.forEach(user => {
                    html += `
                        <div class="user-search-item" onclick="selectUser(${user.id}, '${user.fullname}', '${user.email}')">
                            <div class="user-name">${user.fullname}</div>
                            <div class="user-email">${user.email}</div>
                        </div>
                    `;
                });
                userSearchResults.innerHTML = html;
            }
            userSearchResults.style.display = 'block';
        }
        
        function selectUser(userId, fullName, email) {
            userIdInput.value = userId;
            userSelectInput.value = `${fullName} - ${email}`;
            userSearchResults.style.display = 'none';
        }
        
        // Clear user selection when input is cleared
        userSelectInput.addEventListener('blur', function() {
            setTimeout(() => {
                if (this.value.trim() === '') {
                    userIdInput.value = '';
                }
            }, 200);
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('createModal');
            if (event.target === modal) {
                hideCreateModal();
            }
            // Close user search results when clicking outside
            if (!event.target.closest('.user-select-container')) {
                userSearchResults.style.display = 'none';
            }
        }
    </script>
</body>
</html>
