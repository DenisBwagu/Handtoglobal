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
if (isset($_GET['deleted'])) {
    $msg = "Task deleted successfully!";
}

// Handle task operations
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id=?");
        $stmt->execute([$id]);
        redirect('tasks.php?deleted=1');
    } catch(PDOException $e) {
        $error = "Failed to delete task: " . $e->getMessage();
    }
}

// Pagination setup
$limit = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Level filter
$level_filter = $_GET['level'] ?? 'AllLevels';

// Get total count for pagination
$total_tasks = 0;
try {
    $count_sql = "SELECT COUNT(*) as total FROM tasks";
    $params = [];
    
    if ($level_filter !== 'AllLevels') {
        $count_sql .= " WHERE level = ?";
        $params[] = $level_filter;
    }
    
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    $total_tasks = $result['total'] ?? 0;
} catch(PDOException $e) {
    $error = "Failed to get count: " . $e->getMessage();
}

// Get tasks with pagination and filtering
$tasks = [];
try {
    $sql = "SELECT * FROM tasks";
    $params = [];
    
    if ($level_filter !== 'AllLevels') {
        $sql .= " WHERE level = ?";
        $params[] = $level_filter;
    }
    
    $sql .= " ORDER BY level, id LIMIT $limit OFFSET $offset";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch tasks: " . $e->getMessage();
}

// Calculate pagination info
$total_pages = ceil($total_tasks / $limit);
$start_record = ($page - 1) * $limit + 1;
$end_record = min($page * $limit, $total_tasks);
if ($total_tasks == 0) {
    $start_record = 0;
    $end_record = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks - <?php echo htmlspecialchars(get_site_name()); ?> Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="includes/admin_styles.css">
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
                <h1><?php echo __t('tasks_management', 'Tasks Management'); ?></h1>
                <p><?php echo __t('manage_all_tasks', 'Manage all tasks'); ?></p>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?php echo __t('all_tasks', 'All Tasks'); ?></h2>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <select class="level-filter" onchange="window.location.href='?level=' + this.value" style="padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 14px;">
                            <option value="AllLevels" <?php echo $level_filter === 'AllLevels' ? 'selected' : ''; ?>><?php echo __t('all_levels', 'All Levels'); ?></option>
                            <option value="Bronze" <?php echo $level_filter === 'Bronze' ? 'selected' : ''; ?>>Bronze</option>
                            <option value="Silver" <?php echo $level_filter === 'Silver' ? 'selected' : ''; ?>>Silver</option>
                            <option value="Gold" <?php echo $level_filter === 'Gold' ? 'selected' : ''; ?>>Gold</option>
                            <option value="VIP 1" <?php echo $level_filter === 'VIP 1' ? 'selected' : ''; ?>>VIP 1</option>
                        </select>
                        <a href="task_create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Task
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php echo __t('title', 'TITLE'); ?></th>
                                <th><?php echo __t('description', 'DESCRIPTION'); ?></th>
                                <th><?php echo __t('level', 'LEVEL'); ?></th>
                                <th><?php echo __t('reward', 'REWARD'); ?></th>
                                <th><?php echo __t('image', 'IMAGE'); ?></th>
                                <th><?php echo __t('active', 'ACTIVE'); ?></th>
                                <th><?php echo __t('actions', 'ACTIONS'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                                    <td>
                                        <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" 
                                             title="<?php echo htmlspecialchars($task['description'] ?? ''); ?>">
                                            <?php echo htmlspecialchars(substr($task['description'] ?? '', 0, 50)) . (strlen($task['description'] ?? '') > 50 ? '...' : ''); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($task['level']); ?>">
                                            <?php echo htmlspecialchars($task['level']); ?>
                                        </span>
                                    </td>
                                    <td>$<?php echo number_format($task['reward'] ?? 1.80, 2); ?></td>
                                    <td>
                                        <?php if ($task['image']): ?>
                                            <img src="../uploads/tasks/<?php echo htmlspecialchars($task['image']); ?>" 
                                                 alt="Task Image" 
                                                 style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #dee2e6;">
                                        <?php else: ?>
                                            <span style="color: #6c757d; font-size: 12px;"><?php echo __t('no_image', 'No image'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($task['active']): ?>
                                            <span class="badge badge-active"><?php echo __t('active', 'Active'); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive"><?php echo __t('inactive', 'Inactive'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="task_edit.php?id=<?= $task['id'] ?>" class="action-link">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="?delete=<?php echo $task['id']; ?>" class="action-link delete" 
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
                        <div class="empty-state" style="text-align: center; padding: 40px; color: #6c757d;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px;"></i>
                            <h3><?php echo __t('no_tasks_found', 'No tasks found'); ?></h3>
                            <p>No tasks match the selected criteria.</p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Pagination -->
                    <?php if ($total_tasks > 0): ?>
                        <div class="pagination">
                            <div class="pagination-info">
                                Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_tasks; ?>
                            </div>
                            <div class="pagination-controls">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&level=<?php echo urlencode($level_filter); ?>" class="pagination-btn">
                                        Â« Previous
                                    </a>
                                <?php else: ?>
                                    <button class="pagination-btn" disabled>Â« Previous</button>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 5);
                                $end_page = min($total_pages, $page + 5);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <a href="?page=<?php echo $i; ?>&level=<?php echo urlencode($level_filter); ?>" 
                                       class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&level=<?php echo urlencode($level_filter); ?>" class="pagination-btn">
                                        Next Â»
                                    </a>
                                <?php else: ?>
                                    <button class="pagination-btn" disabled>Next Â»</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
