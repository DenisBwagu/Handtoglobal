<?php
require_once '../config.php';
require_once '../includes/settings_helpers.php';

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
    <link rel="stylesheet" href="includes/admin_styles.css">
</head>
        </head>
<body>
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>
    
    <!-- Admin Layout -->
    <div class="admin-layout">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
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

