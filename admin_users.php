<?php
session_start();
require 'config.php';
if (empty($_SESSION['admin'])) { header("Location: admin_login.php"); exit(); }

$users = $conn->query("SELECT * FROM users ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Users</title>
<style>
body{font-family:Arial;background:#f6f7fb;padding:30px}
table{width:100%;background:#fff;border-collapse:collapse}
th,td{padding:12px;border-bottom:1px solid #ddd}
a.btn{background:#007bff;color:#fff;padding:6px 10px;text-decoration:none;border-radius:6px}
</style>
</head>
<body>

<h2>Manage Users</h2>

<table>
<tr>
<th>ID</th>
<th>Name</th>
<th>Email</th>
<th>Balance</th>
<th>Level</th>
<th>Action</th>
</tr>

<?php while($u = $users->fetch_assoc()){ ?>
<tr>
<td><?php echo $u['id']; ?></td>
<td><?php echo $u['fullname']; ?></td>
<td><?php echo $u['email']; ?></td>
<td><?php echo $u['balance']; ?> USDT</td>
<td><?php echo $u['level']; ?></td>
<td>
<a class="btn" href="admin_edit_user.php?id=<?php echo $u['id']; ?>">Edit</a>
</td>
</tr>
<?php } ?>

</table>

</body>
</html>