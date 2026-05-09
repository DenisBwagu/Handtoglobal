<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/settings_helpers.php';
set_language('english');

// Get Telegram link from settings
$supportLink = get_telegram_link();

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$siteName = get_site_name();
$siteLogo = get_site_logo();

// Handle POST request (form submission) ONLY
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($_POST['fullname'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $invitation_code = sanitize($_POST['invitation_code'] ?? '');
    $defaultLevel = 'Bronze';
    
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields';
    } elseif ($invitation_code === '') {
        $error = 'Invitation code is required';
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
                $error = 'User already exists.';
            } else {
                $conn->beginTransaction();

                $stmt = $conn->prepare("SELECT * FROM invitation_codes WHERE code = ? LIMIT 1 FOR UPDATE");
                $stmt->execute([$invitation_code]);
                $invitation = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$invitation) {
                    $error = 'Invalid invitation code.';
                    $conn->rollBack();
                } else {
                    $invitationColumns = htgTableColumns($conn, 'invitation_codes');
                    $usedCount = max(
                        (int)($invitation['used_count'] ?? 0),
                        (int)($invitation['total_used'] ?? 0)
                    );
                    $usageLimit = null;
                    foreach (['max_uses', 'usage_limit', 'max_users'] as $limitColumn) {
                        if (array_key_exists($limitColumn, $invitation) && (int)$invitation[$limitColumn] > 0) {
                            $usageLimit = (int)$invitation[$limitColumn];
                            break;
                        }
                    }
                    $usageLimit = $usageLimit ?: 1;

                    $isActive = true;
                    if (array_key_exists('is_active', $invitation) && (int)$invitation['is_active'] !== 1) {
                        $isActive = false;
                    }
                    if (array_key_exists('active', $invitation) && (int)$invitation['active'] !== 1) {
                        $isActive = false;
                    }
                    $usesRemaining = array_key_exists('uses_remaining', $invitation) ? (int)$invitation['uses_remaining'] : null;

                    if ($usedCount >= $usageLimit || $usesRemaining === 0) {
                        $error = 'Invitation code has reached its usage limit.';
                        $conn->rollBack();
                    } elseif (!$isActive) {
                        $error = 'Invitation code is inactive.';
                        $conn->rollBack();
                    } else {
                        $starting_balance = isset($invitation['starting_balance']) && (float)$invitation['starting_balance'] > 0
                            ? (float)$invitation['starting_balance']
                            : 20.00;
                        $referredBy = $invitation['employee_id'] ?? null;

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    INSERT INTO users
                        (fullname, email, password, balance, level, rating, accuracy, total_tasks,
                         bronze_unlocked, silver_unlocked, gold_unlocked, platinum_unlocked,
                         invite_code_used, invitation_code, invitation_code_used, referred_by,
                         employee_id, is_blocked, is_active, status, role, created_at)
                    VALUES
                        (?, ?, ?, ?, ?, 0, 0, 0,
                         1, 0, 0, 0,
                         ?, ?, ?, ?,
                         ?, 0, 1, 'active', 'user', NOW())
                ");
                $stmt->execute([
                    $fullname,
                    $email,
                    $hashed_password,
                    $starting_balance,
                    $defaultLevel,
                    $invitation_code ?: null,
                    $invitation_code ?: null,
                    $invitation_code ?: null,
                    $referredBy,
                    $referredBy
                ]);
                $user_id = $conn->lastInsertId();

                $newUsedCount = $usedCount + 1;
                $remainingUses = max($usageLimit - $newUsedCount, 0);
                $updates = [];
                $updateParams = [];
                if (in_array('used_count', $invitationColumns, true)) {
                    $updates[] = 'used_count = ?';
                    $updateParams[] = $newUsedCount;
                }
                if (in_array('total_used', $invitationColumns, true)) {
                    $updates[] = 'total_used = ?';
                    $updateParams[] = $newUsedCount;
                }
                if (in_array('usage_limit', $invitationColumns, true) && empty($invitation['usage_limit'])) {
                    $updates[] = 'usage_limit = ?';
                    $updateParams[] = $usageLimit;
                }
                if (in_array('uses_remaining', $invitationColumns, true)) {
                    $updates[] = 'uses_remaining = ?';
                    $updateParams[] = $remainingUses;
                }
                if ($remainingUses <= 0) {
                    if (in_array('is_active', $invitationColumns, true)) {
                        $updates[] = 'is_active = 0';
                    }
                    if (in_array('active', $invitationColumns, true)) {
                        $updates[] = 'active = 0';
                    }
                }
                if ($updates) {
                    $updateParams[] = $invitation['id'];
                    $stmt = $conn->prepare("UPDATE invitation_codes SET " . implode(', ', $updates) . " WHERE id = ?");
                    $stmt->execute($updateParams);
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
            }
        } catch(Throwable $e) {
            if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
                $conn->rollBack();
            }
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                $error = 'User already exists.';
            } else {
            error_log('Registration failed: ' . $e->getMessage());
            $error = 'Registration failed. Please check your details and try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(get_meta_title()); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(get_meta_description()); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars(get_meta_keywords()); ?>">
    <meta name="robots" content="<?php echo htmlspecialchars(get_meta_robots()); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars(get_meta_title()); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(get_meta_description()); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars(get_og_image()); ?>">
    <?php
$favicon = get_setting('site_favicon', 'assets/images/favicon.ico');
?>
<link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>?v=<?php echo time(); ?>" type="image/x-icon">
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
            <?php if ($siteLogo): ?>
                <img src="<?php echo htmlspecialchars($siteLogo); ?>" alt="<?php echo htmlspecialchars($siteName); ?>" style="height: 48px; margin-bottom: 10px;">
            <?php else: ?>
                <i style="display: inline-block; font-size: 48px; color: #667eea;"><?php echo htmlspecialchars(strtoupper(substr($siteName, 0, 1))); ?></i>
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($siteName); ?></h1>
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
