<?php
session_start();
require 'config.php';

/* SIMPLE ADMIN AUTH */
if (!isset($_SESSION['admin'])) {
    die("Admin access only.");
}

/* HANDLE ACTIONS */

// Update Balance
if (isset($_POST['update_balance'])) {
    $uid = (int)$_POST['user_id'];
    $amount = (float)$_POST['amount'];

    $stmt = $conn->prepare("UPDATE users SET balance=? WHERE id=?");
    $stmt->bind_param("di", $amount, $uid);
    $stmt->execute();
}

// Block User
if (isset($_POST['block_user'])) {
    $uid = (int)$_POST['user_id'];
    $stmt = $conn->prepare("UPDATE users SET is_blocked=1 WHERE id=?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
}

// Unblock User
if (isset($_POST['unblock_user'])) {
    $uid = (int)$_POST['user_id'];
    $stmt = $conn->prepare("UPDATE users SET is_blocked=0 WHERE id=?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
}

// Delete User
if (isset($_POST['delete_user'])) {
    $uid = (int)$_POST['user_id'];

    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
}

// Create Invitation Code
if (isset($_POST['create_code'])) {
    $code = strtoupper(bin2hex(random_bytes(4)));

    $stmt = $conn->prepare("INSERT INTO invitation_codes (code) VALUES (?)");
    $stmt->bind_param("s", $code);
    $stmt->execute();
}

/* FETCH USERS */
$users = $conn->query("SELECT * FROM users ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

/* FETCH INVITE CODES */
$codes = $conn->query("SELECT * FROM invitation_codes ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Panel</title>
<style>
body{font-family:Arial;padding:20px;background:#f6f7fb}
table{width:100%;border-collapse:collapse;background:#fff}
th,td{padding:10px;border:1px solid #ddd;font-size:13px}
button{padding:6px 10px;border:none;background:#635bff;color:#fff;border-radius:6px;cursor:pointer}
.danger{background:#ef4444}
.blocked{color:red;font-weight:bold}
</style>
</head>
<body>

<h2>Admin Panel</h2>

<h3>Create Invitation Code</h3>
<form method="POST">
<button name="create_code">Generate Code</button>
</form>

<h3>Invitation Codes</h3>
<table>
<tr><th>Code</th><th>Used</th></tr>
<?php foreach($codes as $c){ ?>
<tr>
<td><?php echo htmlspecialchars($c['code']); ?></td>
<td><?php echo $c['is_used'] ? "Yes" : "No"; ?></td>
</tr>
<?php } ?>
</table>

<h3>Users</h3>
<table>
<tr>
<th>ID</th>
<th>Name</th>
<th>Email</th>
<th>Balance</th>
<th>Status</th>
<th>Actions</th>
</tr>

<?php foreach($users as $u){ ?>
<tr>
<td><?php echo $u['id']; ?></td>
<td><?php echo htmlspecialchars($u['fullname']); ?></td>
<td><?php echo htmlspecialchars($u['email']); ?></td>
<td><?php echo $u['balance']; ?> USDT</td>
<td>
<?php echo $u['is_blocked'] ? "<span class='blocked'>Blocked</span>" : "Active"; ?>
</td>
<td>

<form method="POST" style="display:inline">
<input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
<input type="number" step="0.01" name="amount" placeholder="New Balance">
<button name="update_balance">Update</button>
</form>

<form method="POST" style="display:inline">
<input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
<button name="block_user" class="danger">Block</button>
</form>

<form method="POST" style="display:inline">
<input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
<button name="unblock_user">Unblock</button>
</form>

<form method="POST" style="display:inline">
<input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
<button name="delete_user" class="danger">Delete</button>
</form>

</td>
</tr>
<?php } ?>

</table>

</body>
</html>