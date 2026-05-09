<?php
session_start();
require_once __DIR__ . '/config.php';
require 'get_setting.php';

// Get Telegram link from settings
$supportLink = get_setting('telegram_link', '<?php echo htmlspecialchars($supportLink); ?>');

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user'];
$level = $_POST['level'] ?? '';

if (!$level) {
    header("Location: dashboard.php");
    exit();
}

/* ================= FETCH USER ================= */
$stmt = $conn->prepare("
    SELECT balance, level,
           bronze_unlocked,
           silver_unlocked,
           gold_unlocked,
           platinum_unlocked
    FROM users
    WHERE id=?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

/* ================= HELPER: COUNT COMPLETION ================= */
function countCompleted($conn, $user_id, $level) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) c
        FROM completed_tasks ct
        JOIN tasks t ON t.id = ct.task_id
        WHERE ct.user_id=? AND t.level=?
    ");
    $stmt->bind_param("is", $user_id, $level);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}

/* ================= BRONZE ================= */
if ($level === "Bronze") {
    // Bronze is always unlocked - redirect directly to tasks
    header("Location: dashboard.php?level=Bronze");
    exit();
}

/* ================= SILVER ================= */
if ($level === "Silver") {

    if ((int)$user['silver_unlocked'] === 1) {
        header("Location: dashboard.php?level=Silver");
        exit();
    }

    if ((int)$user['bronze_unlocked'] !== 1) {
        header("Location: dashboard.php?level=Bronze");
        exit();
    }

    $completedBronze = countCompleted($conn, $user_id, "Bronze");

    if ($completedBronze < 40) {
        header("Location: dashboard.php?level=Bronze");
        exit();
    }

    if ((float)$user['balance'] < 150) {
        header("Location: " . $supportLink);
        exit();
    }

    $stmt = $conn->prepare("
        UPDATE users
        SET silver_unlocked = 1,
            level = 'Silver'
        WHERE id=?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    header("Location: dashboard.php?level=Silver");
    exit();
}

/* ================= GOLD ================= */
if ($level === "Gold") {

    if ((int)$user['gold_unlocked'] === 1) {
        header("Location: dashboard.php?level=Gold");
        exit();
    }

    if ((int)$user['silver_unlocked'] !== 1) {
        header("Location: dashboard.php?level=Silver");
        exit();
    }

    $completedSilver = countCompleted($conn, $user_id, "Silver");

    if ($completedSilver < 40) {
        header("Location: dashboard.php?level=Silver");
        exit();
    }

    if ((float)$user['balance'] < 500) {
        header("Location: " . $supportLink);
        exit();
    }

    $stmt = $conn->prepare("
        UPDATE users
        SET gold_unlocked = 1,
            level = 'Gold'
        WHERE id=?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    header("Location: dashboard.php?level=Gold");
    exit();
}

/* ================= PLATINUM ================= */
if ($level === "Platinum") {

    if ((int)$user['platinum_unlocked'] === 1) {
        header("Location: dashboard.php?level=Platinum");
        exit();
    }

    if ((int)$user['gold_unlocked'] !== 1) {
        header("Location: dashboard.php?level=Gold");
        exit();
    }

    $completedGold = countCompleted($conn, $user_id, "Gold");

    if ($completedGold < 40) {
        header("Location: dashboard.php?level=Gold");
        exit();
    }

    if ((float)$user['balance'] < 800) {
        header("Location: " . $supportLink);
        exit();
    }

    $stmt = $conn->prepare("
        UPDATE users
        SET platinum_unlocked = 1,
            level = 'Platinum'
        WHERE id=?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    header("Location: dashboard.php?level=Platinum");
    exit();
}

header("Location: dashboard.php");
exit();
?>