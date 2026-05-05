<?php
require_once 'config.php';
require_once '../includes/settings_helpers.php';
require_once 'get_translation.php';

requireLogin();

// Hide balance card from TaskHistory page
$hideBalanceCard = true;

$conn = getConnection();
$userId = (int)$_SESSION['user_id'];
$user = getUserById($userId);

if (!$user) {
    session_destroy();
    redirect('login.php');
}

$levels = ['AllLevels', 'Bronze', 'Silver', 'Gold', 'VIP 1'];
$selectedLevel = $_GET['level'] ?? 'AllLevels';
$filterLevel = '';
if ($selectedLevel !== 'AllLevels') {
    $normalized = normalizeLevelName($selectedLevel);
    if (in_array($normalized, ['Bronze', 'Silver', 'Gold', 'VIP 1'], true)) {
        $filterLevel = $normalized;
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = "ct.user_id = ?";
$params = [$userId];
if ($filterLevel !== '') {
    $where .= " AND COALESCE(NULLIF(ct.level, ''), t.level) = ?";
    $params[] = $filterLevel;
}

$countStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM completed_tasks ct
    LEFT JOIN tasks t ON t.id = ct.task_id
    WHERE $where
");
$countStmt->execute($params);
$totalTasks = (int)($countStmt->fetch()['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalTasks / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$limitSql = (int)$limit;
$offsetSql = (int)$offset;

$historyStmt = $conn->prepare("
    SELECT
        ct.id,
        ct.user_id,
        ct.task_id,
        ct.completed_at,
        t.title,
        t.level,
        t.reward,
        'Completed' AS status,
        t.description
    FROM completed_tasks ct
    JOIN tasks t ON t.id = ct.task_id
    WHERE $where
    ORDER BY ct.completed_at DESC
    LIMIT $limitSql OFFSET $offsetSql
");
$historyStmt->execute($params);
$completedTasks = $historyStmt->fetchAll();

$summaryStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_tasks,
        COALESCE(SUM(reward), 0) AS total_earned,
        SUM(CASE WHEN DATE(completed_at) = CURDATE() THEN 1 ELSE 0 END) AS today_tasks
    FROM completed_tasks
    WHERE user_id = ?
");
$summaryStmt->execute([$userId]);
$summary = $summaryStmt->fetch();

$siteName = get_setting('site_name', 'HandToGlobal');
function historyQuery(array $overrides = []) {
    $query = array_merge($_GET, $overrides);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }
    return '?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task History - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: var(--body-bg, #f4f6f9); color: var(--text-primary, #111827); }
        .main-content { margin-left: 260px; min-height: 100vh; padding-top: 56px; transition: margin-left .3s ease; }
        .main-content.expanded { margin-left: 0; }
        .content-area { padding: 24px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 22px; }
        .page-header h1 { margin: 0; font-size: 28px; font-weight: 800; }
        .page-header p { margin: 6px 0 0; color: var(--text-secondary, #6b7280); }
        .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
        .stat-card, .panel { background: var(--card-bg, #fff); border: 1px solid var(--card-border, #e5e7eb); border-radius: 8px; box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,.05)); }
        .stat-card { padding: 18px; }
        .stat-label { color: var(--text-secondary, #6b7280); font-size: 12px; text-transform: uppercase; font-weight: 800; }
        .stat-value { margin-top: 8px; font-size: 24px; font-weight: 800; color: var(--primary, #0d6efd); }
        .panel { padding: 20px; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        .filter-btn, .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 8px; padding: 10px 13px; font-size: 14px; font-weight: 800; text-decoration: none; }
        .filter-btn { color: var(--text-primary, #111827); background: var(--hover, #f3f4f6); border: 1px solid var(--border, #e5e7eb); }
        .filter-btn.active { background: var(--primary, #0d6efd); border-color: var(--primary, #0d6efd); color: #fff; }
        .btn-primary { background: var(--primary, #0d6efd); color: #fff; border: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 13px 12px; border-bottom: 1px solid var(--table-border, #e5e7eb); text-align: left; vertical-align: top; }
        th { font-size: 12px; color: var(--text-secondary, #6b7280); text-transform: uppercase; letter-spacing: .03em; background: var(--table-header-bg, #f9fafb); }
        .task-title { font-weight: 800; }
        .task-description { color: var(--text-secondary, #6b7280); font-size: 13px; margin-top: 3px; }
        .badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 5px 9px; font-size: 12px; font-weight: 800; }
        .badge.completed { background: #dcfce7; color: #166534; }
        .level-badge { background: rgba(13,110,253,.1); color: var(--primary, #0d6efd); }
        .reward { color: #16a34a; font-weight: 900; white-space: nowrap; }
        .date-cell small { display: block; color: var(--text-secondary, #6b7280); margin-top: 2px; }
        .table-meta { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; color: var(--text-secondary, #6b7280); }
        .empty-state { text-align: center; padding: 42px 16px; color: var(--text-secondary, #6b7280); }
        .empty-state i { font-size: 34px; color: var(--primary, #0d6efd); margin-bottom: 12px; }
        .pagination { display: flex; gap: 8px; justify-content: flex-end; margin-top: 18px; flex-wrap: wrap; }
        .page-btn { min-width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border, #e5e7eb); border-radius: 8px; color: var(--text-primary, #111827); text-decoration: none; font-weight: 800; }
        .page-btn.active { background: var(--primary, #0d6efd); color: #fff; border-color: var(--primary, #0d6efd); }
        .table-wrap { overflow-x: auto; }
        @media (max-width: 960px) { .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .main-content { margin-left: 0; } }
        @media (max-width: 640px) { .content-area { padding: 16px; } .stats-grid { grid-template-columns: 1fr; } .page-header, .table-meta { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body>
    <?php require 'includes/sidebar.php'; ?>
    <?php require 'includes/topbar.php'; ?>

    <main class="main-content">
        <div class="content-area">
            <div class="page-header">
                <div>
                    <h1>TaskHistory</h1>
                    <p>Subtitle</p>
                </div>
                <a class="btn btn-primary" href="dashboard.php"><i class="fas fa-play"></i> Start Tasks</a>
            </div>

            <section class="stats-grid">
                <div class="stat-card"><div class="stat-label">Total Tasks</div><div class="stat-value"><?php echo (int)($summary['total_tasks'] ?? 0); ?></div></div>
                <div class="stat-card"><div class="stat-label">Total Earned</div><div class="stat-value">$<?php echo number_format((float)($summary['total_earned'] ?? 0), 2); ?></div></div>
                <div class="stat-card"><div class="stat-label">Today</div><div class="stat-value"><?php echo (int)($summary['today_tasks'] ?? 0); ?></div></div>
                <div class="stat-card"><div class="stat-label">Current Level</div><div class="stat-value"><?php echo htmlspecialchars(normalizeLevelName($user['level'] ?? 'Bronze')); ?></div></div>
            </section>

            <section class="panel">
                <div class="filter-row">
                    <?php foreach ($levels as $level): ?>
                        <a href="task_history.php?level=<?php echo urlencode($level); ?>" class="filter-btn <?php echo $selectedLevel === $level ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($level); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="table-meta">
                    <strong>Task History</strong>
                    <span>Showing <?php echo $totalTasks ? ($offset + 1) . '-' . min($offset + $limit, $totalTasks) : '0'; ?> of <?php echo $totalTasks; ?></span>
                </div>

                <?php if (!$completedTasks): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No tasks completed yet</h3>
                        <p>Start from the dashboard and your completed tasks will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Level</th>
                                    <th>Reward</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completedTasks as $task): ?>
                                    <tr>
                                        <td>
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <?php if (!empty($task['description'])): ?><div class="task-description"><?php echo htmlspecialchars($task['description']); ?></div><?php endif; ?>
                                        </td>
                                        <td><span class="badge level-badge"><?php echo htmlspecialchars(normalizeLevelName($task['level'])); ?></span></td>
                                        <td><span class="reward">+$<?php echo number_format((float)$task['reward'], 2); ?></span></td>
                                        <td><span class="badge completed">✓ Completed</span></td>
                                        <td class="date-cell">
                                            <?php echo htmlspecialchars(date('M j, Y', strtotime($task['completed_at']))); ?>
                                            <small><?php echo htmlspecialchars(date('g:i A', strtotime($task['completed_at']))); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?><a class="page-btn" href="<?php echo historyQuery(['page' => $page - 1]); ?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a class="page-btn <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo historyQuery(['page' => $i]); ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?><a class="page-btn" href="<?php echo historyQuery(['page' => $page + 1]); ?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
