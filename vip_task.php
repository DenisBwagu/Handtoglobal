<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user'];
$level = $_GET['level'] ?? '';

if (!in_array($level, ['VIP1','VIP2','VIP3'])) {
    header("Location: dashboard.php");
    exit();
}

/* Get next unanswered question */
$stmt = $conn->prepare("
    SELECT q.*
    FROM vip_questions q
    LEFT JOIN vip_completed vc 
        ON vc.question_id = q.id AND vc.user_id = ?
    WHERE q.level = ? AND vc.id IS NULL
    ORDER BY q.id ASC
    LIMIT 1
");
$stmt->bind_param("is", $user_id, $level);
$stmt->execute();
$question = $stmt->get_result()->fetch_assoc();

if (!$question) {
    echo "<h3>All VIP questions completed.</h3>";
    exit();
}

/* Handle submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $answer = $_POST['answer'] ?? '';

    if (!in_array($answer, ['Yes','No'])) {
        header("Location: vip_task.php?level=".$level);
        exit();
    }

    if ($answer === $question['correct_answer']) {

        // reward logic (adjust per level)
        $reward = 0;

        if ($level === 'VIP1') $reward = 100;
        if ($level === 'VIP2') $reward = 200;
        if ($level === 'VIP3') $reward = 350;

        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id=?");
        $stmt->bind_param("di", $reward, $user_id);
        $stmt->execute();
    }

    // insert completion
    $stmt = $conn->prepare("
        INSERT INTO vip_completed (user_id, question_id, level)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iis", $user_id, $question['id'], $level);
    $stmt->execute();

    header("Location: vip_task.php?level=".$level);
    exit();
}
?>

<h2><?php echo $level; ?> Question</h2>

<div style="margin:20px 0;font-weight:700;">
    <?php echo htmlspecialchars($question['question']); ?>
</div>

<form method="POST">
    <label>
        <input type="radio" name="answer" value="Yes" required> Yes
    </label>
    <br><br>
    <label>
        <input type="radio" name="answer" value="No" required> No
    </label>
    <br><br>
    <button type="submit">Submit</button>
</form>