<?php

if (!function_exists('htg_level_aliases')) {
    function htg_level_aliases($level) {
        $normalized = normalizeLevelName($level);
        $aliases = [$normalized];

        if ($normalized === 'Silver') {
            $aliases[] = 'Sliver';
        } elseif ($normalized === 'VIP 1') {
            $aliases[] = 'VIP';
            $aliases[] = 'Platinum';
            $aliases[] = 'VIP / Platinum';
        }

        return array_values(array_unique($aliases));
    }
}

if (!function_exists('htg_in_clause')) {
    function htg_in_clause(array $values) {
        return implode(',', array_fill(0, count($values), '?'));
    }
}

if (!function_exists('htg_level_stats')) {
    function htg_level_stats(PDO $conn, $userId, $level) {
        $aliases = htg_level_aliases($level);
        $placeholders = htg_in_clause($aliases);

        $stmt = $conn->prepare("SELECT COUNT(*) FROM tasks WHERE active = 1 AND level IN ($placeholders)");
        $stmt->execute($aliases);
        $total = min(40, (int)$stmt->fetchColumn());

        $params = array_merge([$userId], $aliases);
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT ct.task_id)
            FROM completed_tasks ct
            INNER JOIN tasks t ON t.id = ct.task_id
            WHERE ct.user_id = ? AND t.level IN ($placeholders)
        ");
        $stmt->execute($params);
        $completed = min(40, (int)$stmt->fetchColumn());

        return [
            'total' => $total,
            'completed' => $completed,
            'available' => max(0, $total - $completed),
            'progress' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }
}

if (!function_exists('htg_all_task_progress')) {
    function htg_all_task_progress(PDO $conn, $userId, $level) {
        $aliases = htg_level_aliases($level);
        $placeholders = htg_in_clause($aliases);
        $params = array_merge([$userId], $aliases);

        $stmt = $conn->prepare("
            SELECT t.id, t.title, t.level, CASE WHEN ct.id IS NULL THEN 0 ELSE 1 END AS completed
            FROM tasks t
            LEFT JOIN completed_tasks ct ON ct.task_id = t.id AND ct.user_id = ?
            WHERE t.active = 1 AND t.level IN ($placeholders)
            ORDER BY t.id ASC
            LIMIT 40
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('htg_next_task')) {
    function htg_next_task(PDO $conn, $userId, $level) {
        $aliases = htg_level_aliases($level);
        $placeholders = htg_in_clause($aliases);
        $params = array_merge([$userId], $aliases);

        $stmt = $conn->prepare("
            SELECT t.id, t.title, t.description, t.image, t.level, t.instructions, t.reward
            FROM tasks t
            LEFT JOIN completed_tasks ct ON ct.task_id = t.id AND ct.user_id = ?
            WHERE t.active = 1 AND t.level IN ($placeholders) AND ct.id IS NULL
            ORDER BY t.id ASC
            LIMIT 1
        ");
        $stmt->execute($params);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            return null;
        }

        $stats = htg_level_stats($conn, $userId, $level);
        $task['level'] = normalizeLevelName($task['level'] ?? $level);
        $task['task_number'] = $stats['completed'] + 1;
        $task['completed_count'] = $stats['completed'];
        $task['total_tasks'] = $stats['total'];
        $task['available_count'] = $stats['available'];
        $task['progress_text'] = $stats['completed'] . '/' . $stats['total'];

        return $task;
    }
}

if (!function_exists('htg_active_combo_for_task_number')) {
    function htg_active_combo_for_task_number(PDO $conn, $userId, $level, $taskNumber) {
        $aliases = htg_level_aliases($level);
        $placeholders = htg_in_clause($aliases);
        $params = array_merge($aliases, [$taskNumber, $userId]);

        $stmt = $conn->prepare("
            SELECT c.*
            FROM combos c
            WHERE c.level IN ($placeholders)
              AND c.is_active = 1
              AND LOWER(c.status) = 'active'
              AND c.start_task = ?
              AND (c.user_id IS NULL OR c.user_id = ?)
            ORDER BY c.id ASC
            LIMIT 1
        ");
        $stmt->execute($params);
        $combo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$combo) {
            return null;
        }

        $rawMultiplier = (float)($combo['multiplier'] ?? 1);
        $rawAmount = (float)($combo['amount'] ?? 0);
        $effectiveMultiplier = $rawMultiplier > 1 ? $rawMultiplier : ($rawAmount > 1 ? $rawAmount : 1);

        return [
            'id' => (int)$combo['id'],
            'amount' => (float)$combo['amount'],
            'multiplier' => $effectiveMultiplier,
            'message' => $combo['message'],
            'level' => normalizeLevelName($combo['level']),
            'task_number' => (int)$taskNumber,
            'start_task' => (int)$combo['start_task'],
            'end_task' => (int)$combo['end_task'],
        ];
    }
}

if (!function_exists('htg_pending_combo_for_task_number')) {
    function htg_pending_combo_for_task_number(PDO $conn, $userId, $level, $taskNumber) {
        $aliases = htg_level_aliases($level);
        $placeholders = htg_in_clause($aliases);
        $params = array_merge([$userId], $aliases, [$taskNumber, $userId]);

        $stmt = $conn->prepare("
            SELECT c.*, ucs.status AS user_combo_status
            FROM combos c
            LEFT JOIN user_combo_status ucs ON ucs.combo_id = c.id AND ucs.user_id = ?
            WHERE c.level IN ($placeholders)
              AND c.is_active = 1
             AND LOWER(c.status) = 'active'
AND (ucs.status IS NULL OR ucs.status = 'pending')
              AND c.start_task = ?
              AND (c.user_id IS NULL OR c.user_id = ?)
            ORDER BY c.id ASC
            LIMIT 1
        ");
        $stmt->execute($params);
        $combo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$combo) {
            return null;
        }

        $status = strtolower((string)($combo['user_combo_status'] ?? ''));
        // 🚨 DO NOT BLOCK if admin has deactivated
if (strtolower($combo['status']) !== 'active') {
    return null;
}
        if ($status === 'activated' || $status === 'cleared' || $status === 'released' || $status === 'cancelled') {
            return null;
        }

        if ($status !== 'pending') {
            $stmt = $conn->prepare("
                INSERT INTO user_combo_status (user_id, combo_id, status, created_at, updated_at)
                VALUES (?, ?, 'pending', NOW(), NOW())
                ON DUPLICATE KEY UPDATE status = 'pending', updated_at = NOW()
            ");
            $stmt->execute([$userId, $combo['id']]);
        }

        return [
            'id' => (int)$combo['id'],
            'amount' => (float)$combo['amount'],
            'multiplier' => max(1, (float)($combo['multiplier'] ?? 1)),
            'message' => $combo['message'],
            'level' => normalizeLevelName($combo['level']),
            'task_number' => (int)$taskNumber,
            'start_task' => (int)$combo['start_task'],
            'end_task' => (int)$combo['end_task'],
        ];
    }
}

if (!function_exists('htg_dashboard_stats')) {
    function htg_dashboard_stats(PDO $conn, $userId, $currentLevel, $balance = null) {
        $levels = getAppLevelNames();
        $allLevels = [];

        foreach ($levels as $level) {
            $allLevels[$level] = htg_level_stats($conn, $userId, $level);
        }

        if ($balance === null) {
            $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $balance = (float)$stmt->fetchColumn();
        }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM withdrawals WHERE user_id = ? AND status = 'Pending'");
        $stmt->execute([$userId]);
        $pendingWithdrawals = (int)$stmt->fetchColumn();

        $stmt = $conn->prepare("SELECT rating FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $performanceScore = (float)$stmt->fetchColumn();

        $currentStats = $allLevels[$currentLevel] ?? ['completed' => 0, 'total' => 40, 'available' => 40, 'progress' => 0];

        return [
            'current_level' => $currentLevel,
            'level_completed_tasks' => $currentStats['completed'],
            'level_total_tasks' => $currentStats['total'],
            'available_tasks' => $currentStats['available'],
            'completed_tasks' => $currentStats['completed'],
            'pending_withdrawals' => $pendingWithdrawals,
            'performance_score' => $performanceScore,
            'balance' => (float)$balance,
            'progress' => $currentStats['progress'],
            'all_levels' => $allLevels,
        ];
    }
}
