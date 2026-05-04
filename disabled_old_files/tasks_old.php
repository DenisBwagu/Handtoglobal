<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
}

// Get database connection
$conn = getConnection();

$msg = "";
$error = "";

// Handle task operations
if (isset($_POST['add_task'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $level = $_POST['level'];
    $reward = (float)$_POST['reward'];
    
    if (empty($title) || empty($description) || empty($level) || $reward <= 0) {
        $error = "Please fill all required fields with valid values";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO tasks (title, description, level, reward) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $description, $level, $reward]);
            $msg = "Task added successfully!";
        } catch(PDOException $e) {
            $error = "Failed to add task: " . $e->getMessage();
        }
    }
}

if (isset($_POST['edit_task'])) {
    $id = (int)$_POST['task_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $level = $_POST['level'];
    $reward = (float)$_POST['reward'];
    
    if (empty($title) || empty($description) || empty($level) || $reward <= 0) {
        $error = "Please fill all required fields with valid values";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE tasks SET title=?, description=?, level=?, reward=? WHERE id=?");
            $stmt->execute([$title, $description, $level, $reward, $id]);
            $msg = "Task updated successfully!";
        } catch(PDOException $e) {
            $error = "Failed to update task: " . $e->getMessage();
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Task deleted successfully!";
    } catch(PDOException $e) {
        $error = "Failed to delete task: " . $e->getMessage();
    }
}

// Get tasks for display
$tasks = [];
try {
    $stmt = $conn->prepare("SELECT * FROM tasks ORDER BY level, id");
    $stmt->execute();
    $tasks = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch tasks: " . $e->getMessage();
}

// Get task for editing
$edit_task = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    try {
        $stmt = $conn->prepare("SELECT * FROM tasks WHERE id=?");
        $stmt->execute([$id]);
        $edit_task = $stmt->fetch();
    } catch(PDOException $e) {
        $error = "Failed to fetch task for editing: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks Management - HandToGlobal Admin</title>
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
        
        select.form-control {
            cursor: pointer;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
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
        
        .badge-bronze {
            background: #cd7f32;
            color: white;
        }
        
        .badge-silver {
            background: #c0c0c0;
            color: #333;
        }
        
        .badge-gold {
            background: #ffd700;
            color: #333;
        }
        
        .badge-platinum {
            background: #e5e4e2;
            color: #333;
        }
        
        .actions {
            display: flex;
            gap: 8px;
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
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close {
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
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
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav-menu">
                <h1><i class="fas fa-tasks"></i> Tasks Management</h1>
                <div class="nav-links">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
                    <a href="deposits.php"><i class="fas fa-dollar-sign"></i> Deposits</a>
                    <a href="withdrawals.php"><i class="fas fa-money-bill-wave"></i> Withdrawals</a>
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
                <div class="stat-number"><?php echo count($tasks); ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $bronze_count = array_filter($tasks, fn($t) => $t['level'] == 'Bronze');
                    echo count($bronze_count);
                    ?>
                </div>
                <div class="stat-label">Bronze Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $silver_count = array_filter($tasks, fn($t) => $t['level'] == 'Silver');
                    echo count($silver_count);
                    ?>
                </div>
                <div class="stat-label">Silver Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $gold_count = array_filter($tasks, fn($t) => $t['level'] == 'Gold');
                    echo count($gold_count);
                    ?>
                </div>
                <div class="stat-label">Gold Tasks</div>
            </div>
        </div>

        <!-- Add/Edit Task Form -->
        <div class="card">
            <div class="card-header">
                <h2><?php echo $edit_task ? 'Edit Task' : 'Add New Task'; ?></h2>
                <?php if ($edit_task): ?>
                    <a href="tasks.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
            
            <form method="POST">
                <?php if ($edit_task): ?>
                    <input type="hidden" name="edit_task" value="1">
                    <input type="hidden" name="task_id" value="<?php echo $edit_task['id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="add_task" value="1">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="title">Task Title *</label>
                    <input type="text" id="title" name="title" class="form-control" 
                           value="<?php echo $edit_task ? htmlspecialchars($edit_task['title']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="description">Task Description *</label>
                    <textarea id="description" name="description" class="form-control" required><?php 
                        echo $edit_task ? htmlspecialchars($edit_task['description']) : ''; 
                    ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="level">Task Level *</label>
                    <select id="level" name="level" class="form-control" required>
                        <option value="">Select Level</option>
                        <option value="Bronze" <?php echo $edit_task && $edit_task['level'] == 'Bronze' ? 'selected' : ''; ?>>Bronze (1.80 USDT)</option>
                        <option value="Silver" <?php echo $edit_task && $edit_task['level'] == 'Silver' ? 'selected' : ''; ?>>Silver (2.50 USDT)</option>
                        <option value="Gold" <?php echo $edit_task && $edit_task['level'] == 'Gold' ? 'selected' : ''; ?>>Gold (3.50 USDT)</option>
                        <option value="Platinum" <?php echo $edit_task && $edit_task['level'] == 'Platinum' ? 'selected' : ''; ?>>Platinum (5.00 USDT)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="reward">Reward (USDT) *</label>
                    <input type="number" id="reward" name="reward" class="form-control" 
                           step="0.01" min="0.01" 
                           value="<?php echo $edit_task ? $edit_task['reward'] : ''; ?>" 
                           required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $edit_task ? 'Update Task' : 'Add Task'; ?>
                </button>
            </form>
        </div>

        <!-- Tasks List -->
        <div class="card">
            <div class="card-header">
                <h2>All Tasks</h2>
                <button class="btn btn-success btn-sm" onclick="window.location.reload()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Level</th>
                        <th>Reward</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td><?php echo $task['id']; ?></td>
                            <td><?php echo htmlspecialchars($task['title']); ?></td>
                            <td><?php echo htmlspecialchars(substr($task['description'], 0, 50)) . '...'; ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($task['level']); ?>">
                                    <?php echo $task['level']; ?>
                                </span>
                            </td>
                            <td>$<?php echo number_format($task['reward'], 2); ?></td>
                            <td><?php echo date('M j, Y', strtotime($task['created_at'])); ?></td>
                            <td>
                                <div class="actions">
                                    <a href="tasks.php?edit=<?php echo $task['id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="tasks.php?delete=<?php echo $task['id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this task?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($tasks)): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No tasks found. Add your first task above!
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-set reward based on level selection
        document.getElementById('level').addEventListener('change', function() {
            const rewardInput = document.getElementById('reward');
            const levelRewards = {
                'Bronze': 1.80,
                'Silver': 2.50,
                'Gold': 3.50,
                'Platinum': 5.00
            };
            
            if (levelRewards[this.value]) {
                rewardInput.value = levelRewards[this.value];
            }
        });
    </script>
</body>
</html>
