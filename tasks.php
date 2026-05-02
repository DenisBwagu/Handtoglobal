<?php
session_start();
require "../config.php";

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)$_SESSION['user'];

/* Get user */
$stmt = $conn->prepare("
    SELECT level, bronze_unlocked, silver_unlocked,
           gold_unlocked, platinum_unlocked
    FROM users WHERE id=?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) exit("User not found.");

/* Get requested level */
$level = $_GET['level'] ?? 'Bronze';

/* Check if level unlocked */
if (
    ($level == 'Bronze' && !$user['bronze_unlocked']) ||
    ($level == 'Silver' && !$user['silver_unlocked']) ||
    ($level == 'Gold' && !$user['gold_unlocked']) ||
    ($level == 'Platinum' && !$user['platinum_unlocked'])
) {
    echo "<h3>Level Locked</h3>";
    exit();
}

/* Count completed tasks in this level */
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM completed_tasks ct
    JOIN tasks t ON t.id = ct.task_id
    WHERE ct.user_id=? AND t.level=?
");
$stmt->bind_param("is", $user_id, $level);
$stmt->execute();
$completed = (int)$stmt->get_result()->fetch_assoc()['total'];

/* Stop if 40 already completed */
if ($completed >= 40) {
    echo "<h3>No available tasks</h3>";
    echo "<p>You have completed all 40 tasks for this level.</p>";
    exit();
}

/* Fetch remaining tasks (not completed yet) */
$stmt = $conn->prepare("
    SELECT t.id, t.title, t.description
    FROM tasks t
    WHERE t.level=?
    AND t.id NOT IN (
        SELECT task_id FROM completed_tasks WHERE user_id=?
    )
    LIMIT ?
");

$limit = 40 - $completed;

$stmt->bind_param("sii", $level, $user_id, $limit);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<h3>No available tasks</h3>";
    exit();
}

while ($task = $result->fetch_assoc()) {
    echo "<div style='background:white;padding:15px;margin-bottom:10px;border-radius:6px;'>";
    echo "<h4>" . htmlspecialchars($task['title']) . "</h4>";
    echo "<p>" . htmlspecialchars($task['description']) . "</p>";
    echo "<form method='POST' action='complete_task.php'>
            <input type='hidden' name='task_id' value='".$task['id']."'>
            <button type='submit'>Complete Task</button>
          </form>";
    echo "</div>";
}