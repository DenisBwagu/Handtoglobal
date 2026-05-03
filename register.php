<?php
require_once 'config.php';

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
    
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password) || empty($invitation_code)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        try {
            $conn = getConnection();
            
            // Ensure users table has required columns
            $conn->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    fullname VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    balance DECIMAL(10,2) DEFAULT 0.00,
                    level ENUM('Bronze', 'Silver', 'Gold', 'Platinum') DEFAULT 'Bronze',
                    is_active TINYINT(1) DEFAULT 1,
                    invitation_code VARCHAR(50) NULL,
                    employee_id INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_email (email),
                    INDEX idx_invitation (invitation_code),
                    INDEX idx_employee (employee_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Add missing columns if they don't exist
            $columns = $conn->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('invitation_code', $columns)) {
                $conn->exec("ALTER TABLE users ADD COLUMN invitation_code VARCHAR(50) NULL AFTER is_active");
            }
            if (!in_array('employee_id', $columns)) {
                $conn->exec("ALTER TABLE users ADD COLUMN employee_id INT NULL AFTER invitation_code");
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            // Validate invitation code with LIMIT 1 for speed
            $stmt = $conn->prepare("SELECT * FROM invitation_codes WHERE code = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$invitation_code]);
            $invitation = $stmt->fetch();
            
            if (!$invitation) {
                $error = 'Invalid invitation code';
            } elseif ($invitation['used_count'] >= $invitation['max_uses']) {
                $error = 'Invitation code has reached its usage limit';
            } else {
                // Check if email already exists with LIMIT 1 for speed
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email already exists';
                } else {
                    // Create user with starting balance from invitation code
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $starting_balance = $invitation['starting_balance'] > 0 ? $invitation['starting_balance'] : 20.00;
                    
                    $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, balance, level, is_active, invitation_code, employee_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$fullname, $email, $hashed_password, $starting_balance, 'Bronze', 1, $invitation_code, $invitation['employee_id']]);
                    $user_id = $conn->lastInsertId();
                    
                    // Update invitation code usage
                    $stmt = $conn->prepare("UPDATE invitation_codes SET used_count = used_count + 1 WHERE code = ?");
                    $stmt->execute([$invitation_code]);
                    
                    // Record finance activity if starting balance > 0
                    if ($starting_balance > 0) {
                        $stmt = $conn->prepare("INSERT INTO finance_activities (user_id, type, category, amount, reason, balance_after, source_table, source_id) VALUES (?, 'registration_bonus', 'Initial Balance', ?, 'Registration bonus with invitation code', ?, 'users', ?)");
                        $stmt->execute([$user_id, $starting_balance, $starting_balance, $user_id]);
                    }
                    
                    $conn->commit();
                    $success = 'Registration successful! You can now login with your credentials.';
                }
            }
        } catch(PDOException $e) {
            $conn->rollback();
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - HandToGlobal</title>
    <style>
        body {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
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
            <h1>HandToGlobal</h1>
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
                <label for="invitation_code">Invitation Code</label>
                <input type="text" id="invitation_code" name="invitation_code" placeholder="Enter invitation code" required>
            </div>
            
            <button type="submit" class="btn">Create Account</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login</a>
        </div>
    </div>
</body>
</html>
