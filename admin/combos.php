<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
}

// Get database connection
$conn = getConnection();

// Create combos table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS task_combos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            discount_percentage DECIMAL(5,2) DEFAULT 0.00,
            total_reward DECIMAL(10,2) NOT NULL,
            task_ids TEXT NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch(PDOException $e) {
    die("Failed to create combos table: " . $e->getMessage());
}

$msg = "";
$error = "";

// Handle combo operations
if (isset($_POST['add_combo'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $discount = (float)$_POST['discount'];
    $task_ids = isset($_POST['task_ids']) ? implode(',', $_POST['task_ids']) : '';
    
    if (empty($name) || empty($task_ids)) {
        $error = "Please provide combo name and select at least one task";
    } else {
        try {
            // Calculate total reward
            $stmt = $conn->prepare("SELECT SUM(reward) as total FROM tasks WHERE id IN ($task_ids)");
            $stmt->execute();
            $total_reward = $stmt->fetch()['total'];
            
            // Apply discount
            $final_reward = $total_reward * (1 - ($discount / 100));
            
            $stmt = $conn->prepare("INSERT INTO task_combos (name, description, discount_percentage, total_reward, task_ids) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $discount, $final_reward, $task_ids]);
            $msg = "Combo added successfully!";
        } catch(PDOException $e) {
            $error = "Failed to add combo: " . $e->getMessage();
        }
    }
}

if (isset($_POST['edit_combo'])) {
    $id = (int)$_POST['combo_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $discount = (float)$_POST['discount'];
    $task_ids = isset($_POST['task_ids']) ? implode(',', $_POST['task_ids']) : '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name) || empty($task_ids)) {
        $error = "Please provide combo name and select at least one task";
    } else {
        try {
            // Calculate total reward
            $stmt = $conn->prepare("SELECT SUM(reward) as total FROM tasks WHERE id IN ($task_ids)");
            $stmt->execute();
            $total_reward = $stmt->fetch()['total'];
            
            // Apply discount
            $final_reward = $total_reward * (1 - ($discount / 100));
            
            $stmt = $conn->prepare("UPDATE task_combos SET name=?, description=?, discount_percentage=?, total_reward=?, task_ids=?, is_active=? WHERE id=?");
            $stmt->execute([$name, $description, $discount, $final_reward, $task_ids, $is_active, $id]);
            $msg = "Combo updated successfully!";
        } catch(PDOException $e) {
            $error = "Failed to update combo: " . $e->getMessage();
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM task_combos WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Combo deleted successfully!";
    } catch(PDOException $e) {
        $error = "Failed to delete combo: " . $e->getMessage();
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    try {
        $stmt = $conn->prepare("UPDATE task_combos SET is_active = NOT is_active WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Combo status updated successfully!";
    } catch(PDOException $e) {
        $error = "Failed to update combo status: " . $e->getMessage();
    }
}

// Get combos for display
$combos = [];
try {
    $stmt = $conn->prepare("SELECT * FROM task_combos ORDER BY created_at DESC");
    $stmt->execute();
    $combos = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch combos: " . $e->getMessage();
}

// Get tasks for selection
$tasks = [];
try {
    $stmt = $conn->prepare("SELECT * FROM tasks ORDER BY level, title");
    $stmt->execute();
    $tasks = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch tasks: " . $e->getMessage();
}

// Get combo for editing
$edit_combo = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    try {
        $stmt = $conn->prepare("SELECT * FROM task_combos WHERE id=?");
        $stmt->execute([$id]);
        $edit_combo = $stmt->fetch();
    } catch(PDOException $e) {
        $error = "Failed to fetch combo for editing: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Combos - HandToGlobal Admin</title>
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
            min-height: 100px;
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 8px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .checkbox-item:hover {
            background: #f8f9fa;
        }
        
        .checkbox-item input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .checkbox-item label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
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
        
        .combo-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .task-list {
            margin-top: 10px;
            font-size: 12px;
            color: #666;
        }
        
        .task-item {
            display: inline-block;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            margin: 2px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav-menu">
                <h1><i class="fas fa-layer-group"></i> Task Combos</h1>
                <div class="nav-links">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
                    <a href="combos.php"><i class="fas fa-layer-group"></i> Combos</a>
                    <a href="deposits.php"><i class="fas fa-dollar-sign"></i> Deposits</a>
                    <a href="withdrawals.php"><i class="fas fa-money-bill-wave"></i> Withdrawals</a>
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
                <div class="stat-number"><?php echo count($combos); ?></div>
                <div class="stat-label">Total Combos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $active_count = array_filter($combos, fn($c) => $c['is_active']);
                    echo count($active_count);
                    ?>
                </div>
                <div class="stat-label">Active Combos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($tasks); ?></div>
                <div class="stat-label">Available Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $total_combo_value = array_sum(array_column($combos, 'total_reward'));
                    echo '$' . number_format($total_combo_value, 2);
                    ?>
                </div>
                <div class="stat-label">Total Combo Value</div>
            </div>
        </div>

        <!-- Add/Edit Combo Form -->
        <div class="card">
            <div class="card-header">
                <h2><?php echo $edit_combo ? 'Edit Combo' : 'Create New Combo'; ?></h2>
                <?php if ($edit_combo): ?>
                    <a href="combos.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
            
            <form method="POST">
                <?php if ($edit_combo): ?>
                    <input type="hidden" name="edit_combo" value="1">
                    <input type="hidden" name="combo_id" value="<?php echo $edit_combo['id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="add_combo" value="1">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">Combo Name *</label>
                    <input type="text" id="name" name="name" class="form-control" 
                           value="<?php echo $edit_combo ? htmlspecialchars($edit_combo['name']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control"><?php 
                        echo $edit_combo ? htmlspecialchars($edit_combo['description']) : ''; 
                    ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="discount">Discount Percentage (%)</label>
                    <input type="number" id="discount" name="discount" class="form-control" 
                           step="0.01" min="0" max="100" 
                           value="<?php echo $edit_combo ? $edit_combo['discount_percentage'] : '0'; ?>">
                    <small style="color: #666;">Discount applied to total task reward</small>
                </div>
                
                <div class="form-group">
                    <label>Select Tasks *</label>
                    <div class="checkbox-group">
                        <?php 
                        $selected_tasks = $edit_combo ? explode(',', $edit_combo['task_ids']) : [];
                        foreach ($tasks as $task): 
                        ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="task_ids[]" value="<?php echo $task['id']; ?>" 
                                       id="task_<?php echo $task['id']; ?>"
                                       <?php echo in_array($task['id'], $selected_tasks) ? 'checked' : ''; ?>>
                                <label for="task_<?php echo $task['id']; ?>">
                                    <?php echo htmlspecialchars($task['title']); ?> 
                                    (<?php echo $task['level']; ?> - $<?php echo $task['reward']; ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if ($edit_combo): ?>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php echo $edit_combo['is_active'] ? 'checked' : ''; ?>>
                            Active Combo
                        </label>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $edit_combo ? 'Update Combo' : 'Create Combo'; ?>
                </button>
            </form>
        </div>

        <!-- Combos List -->
        <div class="card">
            <div class="card-header">
                <h2>All Combos</h2>
                <button class="btn btn-success btn-sm" onclick="window.location.reload()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Tasks</th>
                        <th>Discount</th>
                        <th>Total Reward</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($combos as $combo): ?>
                        <tr>
                            <td><?php echo $combo['id']; ?></td>
                            <td><?php echo htmlspecialchars($combo['name']); ?></td>
                            <td><?php echo htmlspecialchars(substr($combo['description'] ?? '', 0, 50)) . '...'; ?></td>
                            <td>
                                <?php 
                                $task_ids = explode(',', $combo['task_ids']);
                                $task_count = count($task_ids);
                                echo $task_count . ' task' . ($task_count > 1 ? 's' : '');
                                ?>
                            </td>
                            <td><?php echo $combo['discount_percentage']; ?>%</td>
                            <td>$<?php echo number_format($combo['total_reward'], 2); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $combo['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $combo['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($combo['created_at'])); ?></td>
                            <td>
                                <div class="actions">
                                    <a href="combos.php?edit=<?php echo $combo['id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="combos.php?toggle=<?php echo $combo['id']; ?>" 
                                       class="btn btn-secondary btn-sm" 
                                       title="<?php echo $combo['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas fa-<?php echo $combo['is_active'] ? 'pause' : 'play'; ?>"></i>
                                    </a>
                                    <a href="combos.php?delete=<?php echo $combo['id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this combo?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($combos)): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No combos found. Create your first combo above!
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Calculate total reward when tasks are selected
        function calculateTotalReward() {
            const checkboxes = document.querySelectorAll('input[name="task_ids[]"]:checked');
            const discount = parseFloat(document.getElementById('discount').value) || 0;
            let total = 0;
            
            checkboxes.forEach(checkbox => {
                const label = document.querySelector(`label[for="${checkbox.id}"]`);
                const rewardText = label.textContent.match(/\$([\d.]+)/);
                if (rewardText) {
                    total += parseFloat(rewardText[1]);
                }
            });
            
            const finalTotal = total * (1 - (discount / 100));
            console.log(`Tasks: ${checkboxes.length}, Total: $${total.toFixed(2)}, Discount: ${discount}%, Final: $${finalTotal.toFixed(2)}`);
        }
        
        // Add event listeners
        document.querySelectorAll('input[name="task_ids[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', calculateTotalReward);
        });
        
        document.getElementById('discount').addEventListener('input', calculateTotalReward);
    </script>
</body>
</html>
