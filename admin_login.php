<?php
require 'config.php';

$msg = "";

if (isset($_POST['login'])) {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Get database connection
    $conn = getConnection();

    // Check admin credentials from database
    $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin'] = $admin['id'];
        $_SESSION['admin_email'] = $admin['email'];
        header("Location: admin/dashboard.php");
        exit();
    }

    $msg = "Invalid admin credentials!";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Login</title>
<style>
body{font-family:Arial;background:#f6f7fb;padding:40px}
.box{max-width:400px;margin:auto;background:#fff;padding:25px;border-radius:10px}
input{width:100%;padding:10px;margin:10px 0}
button{width:100%;padding:10px;background:#635bff;color:#fff;border:none}
.msg{color:red}
</style>
</head>
<body>
<div class="box">
<h2>Admin Login</h2>
<?php if($msg) echo "<div class='msg'>$msg</div>"; ?>
<form method="POST">
<input type="email" name="email" placeholder="Admin Email" required>
<input type="password" name="password" placeholder="Password" required>
<button name="login">Login</button>
</form>
</div>
</body>
</html>