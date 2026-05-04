<?php
require_once 'config.php';
require_once 'get_setting.php';

// Get Telegram link from settings
$supportLink = getSupportLink();

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}
if (isAdminLoggedIn()) {
    redirect('admin/dashboard.php');
}

$error = '';

// Handle POST request (form submission) ONLY
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        try {
            $conn = getConnection();
            ensureAuthSchema();
            
            // Check admins table with LIMIT 1 for speed
            $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                // Admin login successful
                session_regenerate_id(true);
                $_SESSION['admin'] = $admin['id'];
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_name'] = $admin['name'] ?? 'Admin';
                $_SESSION['role'] = 'admin';
                redirect('admin/dashboard.php');
            } else {
                // Check users table with LIMIT 1 for speed
                $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    // User login successful
                    if ((int)($user['is_blocked'] ?? 0) === 1 || strtolower((string)($user['status'] ?? 'active')) === 'blocked') {
                        $error = 'Your account has been blocked. Please contact support.';
                    } elseif (isset($user['is_active']) && (int)$user['is_active'] !== 1) {
                        $error = 'Your account is inactive. Please contact support.';
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['fullname'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_fullname'] = $user['fullname'];
                        $_SESSION['role'] = 'user';
                        redirect('dashboard.php');
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            }
        } catch(PDOException $e) {
            error_log('Login failed: ' . $e->getMessage());
            $error = 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo get_setting('site_name', 'HandToGlobal'); ?></title>
    <link rel="stylesheet" href="assets/css/global-theme.css">
    <script src="assets/js/theme.js" defer></script>
    <style>
        body {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: #f4f6f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        
        .login-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
            padding: 40px;
            width: 100%;
            max-width: 420px;
            position: relative;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo i {
            font-size: 48px;
            background: #0d6efd;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        .logo h1 {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn:hover {
            background: #0b5ed7;
            box-shadow: 0 6px 14px rgba(13, 110, 253, 0.25);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #4a5568;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1 class="login-title">Welcome to <?php echo get_setting('site_name', 'HandToGlobal'); ?></h1>
        <div class="logo">
            <?php $site_logo = get_setting('site_logo'); ?>
            <?php if ($site_logo): ?>
                <img src="<?php echo $site_logo; ?>" alt="<?php echo get_setting('site_name', 'HandToGlobal'); ?>" style="height: 48px; margin-bottom: 10px;">
            <?php else: ?>
                <i style="display: inline-block; font-size: 48px; color: #667eea;"><?php echo strtoupper(substr(get_setting('site_name', 'HandToGlobal'), 0, 1)); ?></i>
            <?php endif; ?>
            <h1><?php echo get_setting('site_name', 'HandToGlobal'); ?></h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
        
        <div class="register-link">
            Don't have an account? <a href="register.php">Create account</a>
        </div>
    </div>
</body>
</html>
