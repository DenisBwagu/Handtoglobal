<?php
require_once __DIR__ . '/config.php';
if (!isAdminLoggedIn()) exit();
$conn = getConnection();

$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    exit('User not found');
}

if($_SERVER['REQUEST_METHOD']=="POST"){

    $newBalance = (float)$_POST['balance'];
    $newLevel = normalizeLevelName($_POST['level']);
    $bonus = (float)$_POST['bonus'];

    // Update balance manually
    $stmt = $conn->prepare("UPDATE users SET balance=? WHERE id=?");
    $stmt->execute([$newBalance, $id]);
    setCurrentUserActiveLevel($id, $newLevel);

    // Add bonus separately if given
    if($bonus > 0){
        $stmt = $conn->prepare("UPDATE users SET balance=balance+? WHERE id=?");
        $stmt->execute([$bonus, $id]);
    }

    header("Location: admin_users.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit User</title>
<style>
body{font-family:Arial;background:#f6f7fb;padding:40px}
.box{max-width:500px;margin:auto;background:#fff;padding:25px;border-radius:10px}
input,select{width:100%;padding:10px;margin:10px 0}
button{background:#007bff;color:#fff;padding:10px;border:none;width:100%}
</style>
</head>
<body>

<div class="box">
<h2>Edit User</h2>

<form method="POST">
<label>Balance</label>
<input type="number" step="0.01" name="balance" value="<?php echo $user['balance']; ?>" required>

<label>Level</label>
<select name="level">
<?php foreach (getAppLevelNames() as $level): ?>
<option value="<?php echo htmlspecialchars($level); ?>" <?php echo normalizeLevelName($user['level'] ?? '') === $level ? 'selected' : ''; ?>><?php echo htmlspecialchars($level); ?></option>
<?php endforeach; ?>
</select>

<label>Give Bonus (Optional)</label>
<input type="number" step="0.01" name="bonus" placeholder="Enter bonus amount">

<button type="submit">Update User</button>
</form>

</div>

</body>
</html>
