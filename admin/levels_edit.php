<?php
require_once '../config.php';
require_once '../includes/settings_helpers.php';
require_once '../includes/admin_helpers.php';
require_once '../includes/admin_helpers.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

// Get level ID from URL
$levelId = $_GET['id'] ?? null;
if (!$levelId || !is_numeric($levelId)) {
    redirect('levels.php');
    exit;
}

// Get database connection
$conn = getConnection();








// Get level details
$stmt = $conn->prepare("SELECT * FROM levels WHERE id = ?");
$stmt->execute([$levelId]);
$level = $stmt->fetch();

if (!$level) {
    die("Level not found");
}

// Add safe defaults to prevent undefined array key warnings
$level = array_merge([
    'name' => '',
    'sort_order' => 1,
    'icon' => 'medal-bronze',
    'task_type' => 'Name_items',
    'reward' => 0,
    'tasks_count' => 40,
    'requires_deposit' => 0,
    'deposit_amount' => 0,
    'is_active' => 1
], $level ?? []);

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
            $stmt = $conn->prepare("UPDATE levels SET name=?, sort_order=?, icon=?, task_type=?, task_reward=?, daily_task_limit=?, deposit_amount=?, is_active=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$name, $order, $icon, $task_type, $reward, $tasks_count, $deposit_amount, $is_active, $levelId]);
            $msg = "Level updated successfully!";
            
            // Refresh level data
            $stmt = $conn->prepare("SELECT * FROM levels WHERE id = ?");
            $stmt->execute([$levelId]);
            $level = $stmt->fetch();
        } catch(PDOException $e) {
            $error = "Failed to update level: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Level - <?php echo htmlspecialchars(get_site_name()); ?> Admin</title>
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
<body><?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="container">
        <div class="breadcrumb">
            <a href="levels.php">Levels</a> > Edit
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
                                <label class="form-label"><?php echo __t('name', 'Name'); ?></label>
                                <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($level['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php echo __t('order', 'Order'); ?></label>
                                <input type="number" name="order" class="form-input" value="<?php echo $level['sort_order'] ?? 1; ?>" required>
                            </div>
                        </div>
                        
                        <label class="form-label"><?php echo __t('icon', 'Icon'); ?></label>
                        <div class="icon-grid">
                            <button type="button" class="icon-card <?= ($level['icon'] ?? '') === 'medal-bronze' ? 'selected' : '' ?>" onclick="selectIcon('medal-bronze')">
                                <i class="fas fa-medal"></i>
                                <div class="icon-label">Bronze Medal</div>
                            </button>
                            <button type="button" class="icon-card <?= ($level['icon'] ?? '') === 'medal-silver' ? 'selected' : '' ?>" onclick="selectIcon('medal-silver')">
                                <i class="fas fa-medal"></i>
                                <div class="icon-label">Silver Medal</div>
                            </button>
                            <button type="button" class="icon-card <?= ($level['icon'] ?? '') === 'medal-gold' ? 'selected' : '' ?>" onclick="selectIcon('medal-gold')">
                                <i class="fas fa-medal"></i>
                                <div class="icon-label">Gold Medal</div>
                            </button>
                            <button type="button" class="icon-card <?= ($level['icon'] ?? '') === 'trophy' ? 'selected' : '' ?>" onclick="selectIcon('trophy')">
                                <i class="fas fa-trophy"></i>
                                <div class="icon-label">Trophy</div>
                            </button>
                            <button type="button" class="icon-card <?= ($level['icon'] ?? '') === 'crown' ? 'selected' : '' ?>" onclick="selectIcon('crown')">
                                <i class="fas fa-crown"></i>
                                <div class="icon-label">Crown</div>
                            </button>
                            <button type="button" class="icon-card <?= ($level['icon'] ?? '') === 'star' ? 'selected' : '' ?>" onclick="selectIcon('star')">
                                <i class="fas fa-star"></i>
                                <div class="icon-label">Star</div>
                            </button>
                            <button type="button" class="icon-card <?= ($level['icon'] ?? '') === 'shield' ? 'selected' : '' ?>" onclick="selectIcon('shield')">
                                <i class="fas fa-shield-alt"></i>
                                <div class="icon-label">Shield</div>
                            </button>
                            <button type="button" class="icon-card <?= ($level['icon'] ?? '') === 'gem' ? 'selected' : '' ?>" onclick="selectIcon('gem')">
                                <i class="fas fa-gem"></i>
                                <div class="icon-label">Gem</div>
                            </button>
                            <button type="button" class="icon-card <?= ($level['icon'] ?? '') === 'fire' ? 'selected' : '' ?>" onclick="selectIcon('fire')">
                                <i class="fas fa-fire"></i>
                                <div class="icon-label">Fire</div>
                            </button>
                            <button type="button" class="icon-card <?= ($level['icon'] ?? '') === 'lightning' ? 'selected' : '' ?>" onclick="selectIcon('lightning')">
                                <i class="fas fa-bolt"></i>
                                <div class="icon-label">Lightning</div>
                            </button>
                            <button type="button" class="icon-card <?= ($level['icon'] ?? '') === 'rocket' ? 'selected' : '' ?>" onclick="selectIcon('rocket')">
                                <i class="fas fa-rocket"></i>
                                <div class="icon-label">Rocket</div>
                            </button>
                            <button type="button" class="icon-card <?= ($level['icon'] ?? '') === 'diamond' ? 'selected' : '' ?>" onclick="selectIcon('diamond')">
                                <i class="fas fa-gem"></i>
                                <div class="icon-label">Diamond</div>
                            </button>
                        </div>
                        
                        <input type="hidden" name="icon" id="selectedIcon" value="<?php echo htmlspecialchars($level['icon'] ?? 'medal-bronze'); ?>">
                        
                        <div class="form-group">
                            <label class="form-label"><?php echo __t('task_type', 'Task Type'); ?></label>
                            <select name="task_type" class="form-select">
                                <option value="Name_items" <?php echo ($level['task_type'] ?? 'Name_items') === 'Name_items' ? 'selected' : ''; ?>>Name_items</option>
                                <option value="Name Items" <?php echo ($level['task_type'] ?? '') === 'Name Items' ? 'selected' : ''; ?>><?php echo __t('name_items', 'Name Items'); ?></option>
                                <option value="Other" <?php echo ($level['task_type'] ?? '') === 'Other' ? 'selected' : ''; ?>><?php echo __t('other', 'Other'); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><?php echo __t('reward', 'Reward'); ?></label>
                                <input type="number" step="0.01" name="reward" class="form-input" value="<?php echo $level['task_reward'] ?? 0; ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php echo __t('tasks_count', 'Tasks Count'); ?></label>
                                <input type="number" name="tasks_count" class="form-input" value="<?php echo $level['daily_task_limit'] ?? 40; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-checkbox">
                            <input type="checkbox" name="requires_deposit" id="requires_deposit" <?php echo ($level['deposit_amount'] ?? 0) > 0 ? 'checked' : ''; ?>>
                            <label for="requires_deposit"><?php echo __t('requires_deposit', 'Requires Deposit'); ?></label>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><?php echo __t('deposit_amount', 'Deposit Amount'); ?></label>
                            <input type="number" step="0.01" name="deposit_amount" class="form-input" value="<?php echo $level['deposit_amount'] ?? 0; ?>">
                        </div>
                        
                        <div class="form-checkbox">
                            <input type="checkbox" name="is_active" id="is_active" <?php echo !empty($level['is_active']) ? 'checked' : ''; ?>>
                            <label for="is_active"><?php echo __t('active', 'Active'); ?></label>
                        </div>
                        
                        <button type="submit" class="btn-submit"><?php echo __t('update', 'Update'); ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function selectIcon(icon) {
            // Update hidden input
            document.getElementById('selectedIcon').value = icon;

            // Remove selected class from all icon cards
            document.querySelectorAll('.icon-card').forEach(btn => {
                btn.classList.remove('selected');
            });

            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
        }
    </script>
</body>
</html>
