<?php
require_once 'config.php';
require_once 'get_setting.php';

// Get Telegram link from settings
$supportLink = getSupportLink();

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle POST request (form submission) ONLY
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($_POST['fullname'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $invitation_code = sanitize($_POST['invitation_code'] ?? '');
    
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        try {
            $conn = getConnection();
            ensureAuthSchema();

            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already exists';
            } else {
                $invitation = null;
                $starting_balance = 0.00;
                $referredBy = null;

                if ($invitation_code !== '') {
                    $stmt = $conn->prepare("SELECT * FROM invitation_codes WHERE code = ? AND COALESCE(is_active, active, 1) = 1 LIMIT 1");
                    $stmt->execute([$invitation_code]);
                    $invitation = $stmt->fetch();

                    if (!$invitation) {
                        $error = 'Invalid invitation code';
                    } elseif ((int)($invitation['used_count'] ?? 0) >= (int)($invitation['max_uses'] ?? 1)) {
                        $error = 'Invitation code has already been used';
                    } else {
                        $starting_balance = 20.00;
                        $referredBy = $invitation['employee_id'] ?? null;
                    }
                }

                if (!$error) {
                $conn->beginTransaction();

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    INSERT INTO users
                        (fullname, email, password, balance, level, rating, accuracy, total_tasks,
                         bronze_unlocked, silver_unlocked, gold_unlocked, platinum_unlocked,
                         invite_code_used, invitation_code, invitation_code_used, referred_by,
                         is_blocked, is_active, status, role, created_at)
                    VALUES
                        (?, ?, ?, ?, 'Bronze', 0, 0, 0,
                         0, 0, 0, 0,
                         ?, ?, ?, ?,
                         0, 1, 'active', 'user', NOW())
                ");
                $stmt->execute([
                    $fullname,
                    $email,
                    $hashed_password,
                    $starting_balance,
                    $invitation_code ?: null,
                    $invitation_code ?: null,
                    $invitation_code ?: null,
                    $referredBy
                ]);
                $user_id = $conn->lastInsertId();

                if ($invitation) {
                    $stmt = $conn->prepare("UPDATE invitation_codes SET used_count = COALESCE(used_count, 0) + 1, uses_remaining = GREATEST(COALESCE(uses_remaining, 1) - 1, 0) WHERE id = ?");
                    $stmt->execute([$invitation['id']]);
                }

                if ($starting_balance > 0) {
                    try {
                        $stmt = $conn->prepare("INSERT INTO finance_activities (user_id, type, category, amount, reason, balance_after, source_table, source_id) VALUES (?, 'invitation_credit', 'Initial Balance', ?, 'Registration bonus with invitation code', ?, 'users', ?)");
                        $stmt->execute([$user_id, $starting_balance, $starting_balance, $user_id]);
                    } catch (Throwable $activityError) {
                        // Finance logging should not block account creation.
                    }
                }

                $conn->commit();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $fullname;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_fullname'] = $fullname;
                $_SESSION['role'] = 'user';
                redirect('dashboard.php');
                }
            }
        } catch(Throwable $e) {
            if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Registration failed: ' . $e->getMessage());
            $error = 'Registration failed. Please check your details and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo get_setting('site_name', 'HandToGlobal'); ?></title>
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
        
        .register-container {
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
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #4a5568;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h1 class="login-title">Create Account</h1>
        <div class="logo">
            <i style="display: inline-block; font-size: 48px; color: #667eea;">H</i>
            <h1><?php echo get_setting('site_name', 'HandToGlobal'); ?></h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="fullname">Full Name</label>
                <input type="text" id="fullname" name="fullname" placeholder="Enter your full name" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
            </div>
            
            <div class="form-group">
                <label for="invitation_code">Invitation Code (Optional)</label>
                <input type="text" id="invitation_code" name="invitation_code" placeholder="Enter invitation code">
            </div>
            
            <button type="submit" class="btn">Create Account</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login</a>
        </div>
    </div>
</body>
</html>
