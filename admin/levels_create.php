<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $order = (int)$_POST['order'];
    $icon = $_POST['icon'] ?? '';
    $task_type = $_POST['task_type'] ?? 'Name_items';
    $reward = (float)$_POST['reward'];
    $tasks_count = (int)$_POST['tasks_count'];
    $requires_deposit = isset($_POST['requires_deposit']) ? 1 : 0;
    $deposit_amount = (float)$_POST['deposit_amount'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name)) {
        $error = "Name is required";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO levels (name, sort_order, icon, task_type, task_reward, daily_task_limit, deposit_amount, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $order, $icon, $task_type, $reward, $tasks_count, $deposit_amount, $is_active]);
            $msg = "Level created successfully!";
            
            // Redirect to levels list
            header("Location: levels.php");
            exit;
        } catch(PDOException $e) {
            $error = "Failed to create level: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Level - HandToGlobal Admin</title>
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
        
        .breadcrumb {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .breadcrumb a {
            color: #6c757d;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            color: #495057;
        }
        
        .form-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 600px;
        }
        
        .card-body {
            padding: 32px;
        }
        
        .form-row {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.15s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #86b7fe;
        }
        
        .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            transition: border-color 0.15s ease;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #86b7fe;
        }
        
        .icon-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .icon-card {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 16px 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        
        .icon-card:hover {
            border-color: #ced4da;
        }
        
        .icon-card.selected {
            border-color: #28a745;
            background: #d1e7dd;
        }
        
        .icon-card i {
            font-size: 20px;
            color: #6c757d;
            margin-bottom: 8px;
        }
        
        .icon-card.selected i {
            color: #28a745;
        }
        
        .icon-label {
            font-size: 12px;
            color: #495057;
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .form-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
        }
        
        .form-checkbox label {
            font-size: 14px;
            color: #495057;
            cursor: pointer;
        }
        
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        
        .btn-submit:hover {
            background: #218838;
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
        <div class="breadcrumb">
            <a href="levels.php">Levels</a> > Create
        </div>
        
        <div class="form-container">
            <div class="card">
                <div class="card-body">
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

                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Order</label>
                                <input type="number" name="order" class="form-input" value="5" required>
                            </div>
                        </div>
                        
                        <label class="form-label">Icon</label>
                        <div class="icon-grid">
                            <div class="icon-card" onclick="selectIcon('medal-bronze')">
                                <i class="fas fa-medal"></i>
                                <div class="icon-label">Bronze Medal</div>
                            </div>
                            <div class="icon-card" onclick="selectIcon('medal-silver')">
                                <i class="fas fa-medal"></i>
                                <div class="icon-label">Silver Medal</div>
                            </div>
                            <div class="icon-card" onclick="selectIcon('medal-gold')">
                                <i class="fas fa-medal"></i>
                                <div class="icon-label">Gold Medal</div>
                            </div>
                            <div class="icon-card" onclick="selectIcon('trophy')">
                                <i class="fas fa-trophy"></i>
                                <div class="icon-label">Trophy</div>
                            </div>
                            <div class="icon-card" onclick="selectIcon('crown')">
                                <i class="fas fa-crown"></i>
                                <div class="icon-label">Crown</div>
                            </div>
                            <div class="icon-card" onclick="selectIcon('star')">
                                <i class="fas fa-star"></i>
                                <div class="icon-label">Star</div>
                            </div>
                            <div class="icon-card" onclick="selectIcon('shield')">
                                <i class="fas fa-shield-alt"></i>
                                <div class="icon-label">Shield</div>
                            </div>
                            <div class="icon-card" onclick="selectIcon('gem')">
                                <i class="fas fa-gem"></i>
                                <div class="icon-label">Gem</div>
                            </div>
                            <div class="icon-card" onclick="selectIcon('fire')">
                                <i class="fas fa-fire"></i>
                                <div class="icon-label">Fire</div>
                            </div>
                            <div class="icon-card" onclick="selectIcon('lightning')">
                                <i class="fas fa-bolt"></i>
                                <div class="icon-label">Lightning</div>
                            </div>
                            <div class="icon-card" onclick="selectIcon('rocket')">
                                <i class="fas fa-rocket"></i>
                                <div class="icon-label">Rocket</div>
                            </div>
                            <div class="icon-card" onclick="selectIcon('diamond')">
                                <i class="fas fa-gem"></i>
                                <div class="icon-label">Diamond</div>
                            </div>
                        </div>
                        
                        <input type="hidden" name="icon" id="selected_icon" value="">
                        
                        <div class="form-group">
                            <label class="form-label">TaskType</label>
                            <select name="task_type" class="form-select">
                                <option value="">SelectType</option>
                                <option value="Name_items">Name_items</option>
                                <option value="Name Items">Name Items</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">RewardPerTask</label>
                                <input type="number" step="0.01" name="reward" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">NumberOfTasks</label>
                                <input type="number" name="tasks_count" class="form-input" required>
                            </div>
                        </div>
                        
                        <div class="form-checkbox">
                            <input type="checkbox" name="requires_deposit" id="requires_deposit">
                            <label for="requires_deposit">RequiresDeposit</label>
                        </div>
                        
                        <button type="submit" class="btn-submit">Create</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function selectIcon(iconName) {
            // Remove selected class from all icon cards
            document.querySelectorAll('.icon-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
            
            // Update hidden input
            document.getElementById('selected_icon').value = iconName;
        }
    </script>
</body>
</html>
