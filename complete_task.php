<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user'];
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($task_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

/* Get user */
$stmt = $conn->prepare("SELECT balance FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) exit();

/* Prevent duplicate completion */
$stmt = $conn->prepare("SELECT id FROM completed_tasks WHERE user_id=? AND task_id=?");
$stmt->bind_param("ii", $user_id, $task_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    header("Location: dashboard.php");
    exit();
}

/* Get task level */
$stmt = $conn->prepare("SELECT level FROM tasks WHERE id=?");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
if (!$task) exit();


// ==========================
// VIP YES/NO VALIDATION
// ==========================
{

    if (!isset($_POST['answer'])) {
        header("Location: dashboard.php");
        exit();
    }

    $userAnswer = $_POST['answer'];

    if ($userAnswer !== $task['correct_answer']) {

        // Reduce accuracy slightly
        $stmt = $conn->prepare("
            UPDATE users 
            SET accuracy = GREATEST(0, accuracy - 2)
            WHERE id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        header("Location: dashboard.php?wrong=1");
        exit();
    }
}

/* DAILY EARNING CAP */
$dailyCap = 999999;

$stmt = $conn->prepare("
    SELECT SUM(t.reward) total
    FROM completed_tasks ct
    JOIN tasks t ON t.id = ct.task_id
    WHERE ct.user_id=? AND DATE(ct.completed_at)=CURDATE()
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$todayEarned = $row['total'] ?? 0;

if ($todayEarned >= $dailyCap) {
    header("Location: dashboard.php");
    exit();
}

/*
    LEVEL FIXED REWARD SYSTEM
*/

switch ($task['level']) {
    case "VIP1":
    $earning = 100;
    break;

case "VIP2":
    $earning = 200;
    break;

case "VIP3":
    $earning = 350;
    break;

    case "Bronze":
        $earning = 1.8;

        $stmt = $conn->prepare("
            SELECT SUM(t.reward) total
            FROM completed_tasks ct
            JOIN tasks t ON t.id = ct.task_id
            WHERE ct.user_id=? AND t.level='Bronze'
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $bronzeTotal = $row['total'] ?? 0;

        if (($bronzeTotal + $earning) > 81) {
            $earning = max(0, 81 - $bronzeTotal);
        }
        break;

    case "Silver":
        $earning = 2.5;
        break;

    case "Gold":
        $earning = 3.5;
        break;

    case "Platinum":
        $earning = 5;
        break;

    default:
        $earning = 1.8;
}

/* INSERT COMPLETED TASK */
$stmt = $conn->prepare("INSERT INTO completed_tasks (user_id, task_id, completed_at) VALUES (?, ?, NOW())");
$stmt->bind_param("ii", $user_id, $task_id);
$stmt->execute();

/* Update balance */
$newBalance = $user['balance'] + $earning;

$stmt = $conn->prepare("UPDATE users SET balance=? WHERE id=?");
$stmt->bind_param("di", $newBalance, $user_id);
$stmt->execute();

/* Keep your extra balance update but FIX variable */
$stmt = $conn->prepare("
    UPDATE users 
    SET balance = balance + ?
    WHERE id = ?
");
$stmt->bind_param("di", $earning, $user_id);
$stmt->execute();


// ===============================
// CONTROLLED BRONZE PERFORMANCE SYSTEM
// ===============================

$stmt = $conn->prepare("SELECT accuracy, rating, level FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

$currentAccuracy = (float)$row['accuracy'];
$currentRating   = (float)$row['rating'];
$currentLevel    = $row['level'];

if ($currentLevel === "Bronze") {

    $newAccuracy = $currentAccuracy + 0.8;

    if ($newAccuracy > 73) {
        $newAccuracy = 73;
    }

    $newRating = $newAccuracy / 20;

    if ($newRating > 3.7) {
        $newRating = 3.7;
    }

} else {

    $newAccuracy = $currentAccuracy + 1.2;

    if ($newAccuracy > 100) {
        $newAccuracy = 100;
    }

    $newRating = $newAccuracy / 20;
}

$stmt = $conn->prepare("
    UPDATE users
    SET accuracy = ?, 
        rating = ?, 
        total_tasks = total_tasks + 1
    WHERE id = ?
");
$stmt->bind_param("ddi", $newAccuracy, $newRating, $user_id);
$stmt->execute();

header("Location: dashboard.php?level=".$task['level']);
exit();
?>