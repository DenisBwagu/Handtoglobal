<?php
/**
 * Get Level Statistics AJAX Endpoint
 * Returns real-time level statistics for dashboard updates
 */

header('Content-Type: application/json');

require_once '../config.php';
require_once '../get_setting.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $conn = getConnection();
    
    // Get levels
    $levels = ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond'];
    $levelStats = [];
    
    foreach ($levels as $level) {
        // Get completed tasks for this level
        $stmt = $conn->prepare("
            SELECT COUNT(*) as completed 
            FROM completed_tasks ct 
            JOIN tasks t ON ct.task_id = t.id 
            WHERE t.level = ?
        ");
        $stmt->execute([$level]);
        $completed = $stmt->fetch()['completed'];
        
        // Get total tasks for this level
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tasks WHERE level = ? AND active = 1");
        $stmt->execute([$level]);
        $total = $stmt->fetch()['total'];
        
        $levelStats[$level] = [
            'completed' => (int)$completed,
            'total' => (int)$total,
            'available' => (int)($total - $completed),
            'progress' => $total > 0 ? round(($completed / $total) * 100, 1) : 0
        ];
    }
    
    // Get overall stats
    $totalTasks = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE active = 1")->fetch()['count'];
    $completedTasks = $conn->query("SELECT COUNT(*) as count FROM completed_tasks")->fetch()['count'];
    $activeCombos = $conn->query("SELECT COUNT(*) as count FROM combos WHERE status = 'active' AND is_active = 1")->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'level_stats' => $levelStats,
        'total_tasks' => (int)$totalTasks,
        'completed_tasks' => (int)$completedTasks,
        'active_combos' => (int)$activeCombos,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
