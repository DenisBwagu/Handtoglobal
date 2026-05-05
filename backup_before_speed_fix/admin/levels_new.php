<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getConnection();

// Create levels table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS levels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            min_balance DECIMAL(10,2) NOT NULL,
            max_balance DECIMAL(10,2),
            task_reward DECIMAL(10,2) NOT NULL,
            daily_task_limit INT DEFAULT 40,
            withdrawal_limit DECIMAL(10,2) DEFAULT 10000,
            referral_bonus DECIMAL(10,2) DEFAULT 0,
            color VARCHAR(7) DEFAULT '#667eea',
            icon VARCHAR(50),
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            task_type VARCHAR(50) DEFAULT 'Name_items',
            deposit_amount DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch(PDOException $e) {
    // Table creation failed, continue without it
}

$msg = "";
$error = "";

// Handle level operations
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM levels WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Level deleted successfully!";
    } catch(PDOException $e) {
        $error = "Failed to delete level: " . $e->getMessage();
    }
}

// Get levels from database
$levels = [];
try {
    $stmt = $conn->prepare("SELECT * FROM levels ORDER BY sort_order ASC, id ASC");
    $stmt->execute();
    $levels = $stmt->fetchAll();
} catch(PDOException $e) {
    // Query failed, continue with empty array
}

// If no levels exist, add sample data matching screenshot
if (empty($levels)) {
    $sampleLevels = [
        ['Bronze', 1, '$1.20', 40, '$20.00'],
        ['Sliver', 2, '$1.50', 40, '$100.00'],
        ['Gold', 3, '$2.50', 40, '$250.00'],
        ['VIP 1', 4, '$4.00', 40, '$1000.00']
    ];
    
    foreach ($sampleLevels as $level) {
        try {
            $stmt = $conn->prepare("INSERT INTO levels (name, sort_order, task_reward, daily_task_limit, deposit_amount, task_type, is_active) VALUES (?, ?, ?, ?, ?, 'Name_items', 1)");
            $stmt->execute([$level[0], $level[1], $level[2], $level[3], $level[4]]);
        } catch(PDOException $e) {
            // Continue if insertion fails
        }
    }
    
    // Refresh the data
    $stmt = $conn->prepare("SELECT * FROM levels ORDER BY sort_order ASC, id ASC");
    $stmt->execute();
    $levels = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levels - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .levels-container {
            display: flex;
            justify-content: center;
            margin-top: 40px;
        }
        
        .card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 800px;
        }
        
        .card-header {
            background: #f8f9fa;
            padding: 20px 24px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #212529;
        }
        
        .btn-add {
            padding: 8px 16px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        
        .btn-add:hover {
            background: #218838;
        }
        
        .card-body {
            padding: 0;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 12px 24px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table td {
            padding: 16px 24px;
            border-bottom: 1px solid #f1f3f5;
            font-size: 14px;
            color: #495057;
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
        
        .actions {
            display: flex;
            gap: 12px;
        }
        
        .action-link {
            text-decoration: none;
            font-size: 14px;
            transition: color 0.15s ease;
        }
        
        .action-link.edit {
            color: #556b2f;
        }
        
        .action-link.edit:hover {
            color: #3d4a1f;
        }
        
        .action-link.delete {
            color: #dc3545;
        }
        
        .action-link.delete:hover {
            color: #c82333;
        }
        
        .empty-state {
            padding: 40px;
            text-align: center;
            color: #6c757d;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
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
    </style>
</head>
<body>
    <div class="container">
        <?php if ($msg): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="levels-container">
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">Levels</h1>
                    <button class="btn-add" onclick="window.location.href='levels_create.php'">
                        Add
                    </button>
                </div>
                
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ORDER</th>
                                <th>NAME</th>
                                <th>TYPE</th>
                                <th>REWARD</th>
                                <th>TASKS</th>
                                <th>DEPOSIT</th>
                                <th>ACTIVE</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($levels as $level): ?>
                                <tr>
                                    <td><?php echo $level['sort_order']; ?></td>
                                    <td><?php echo htmlspecialchars($level['name']); ?></td>
                                    <td>
                                        <span class="badge" style="background: #e9ecef; color: #495057;">
                                            <?php echo htmlspecialchars($level['task_type'] ?? 'Name_items'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $level['task_reward']; ?></td>
                                    <td><?php echo $level['daily_task_limit']; ?>/<?php echo $level['daily_task_limit']; ?></td>
                                    <td><?php echo $level['deposit_amount']; ?></td>
                                    <td>
                                        <span class="badge badge-active">
                                            Active
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="levels_edit.php?id=<?php echo $level['id']; ?>" class="action-link edit">Edit</a>
                                            <a href="levels.php?delete=<?php echo $level['id']; ?>" class="action-link delete" onclick="return confirm('Are you sure you want to delete this level?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($levels)): ?>
                        <div class="empty-state">
                            No levels found. Click "Add" to create the first level.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
