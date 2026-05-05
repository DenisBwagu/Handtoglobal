<?php
require_once 'config.php';
require_once '../includes/settings_helpers.php';
require_once 'get_translation.php';

requireLogin();

// Hide balance card from Profile page
$hideBalanceCard = true;

$conn = getConnection();
$userId = (int)$_SESSION['user_id'];
$message = '';
$error = '';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    if (isset($_POST['update_profile'])) {
        $fullname = trim($_POST['fullname'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($fullname === '') {
            $error = 'Please enter your full name.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([$fullname, $phone ?: null, $address ?: null, $userId]);
            $_SESSION['user_fullname'] = $fullname;
            $message = 'Profile updated successfully.';
        }
    }

    if (isset($_POST['update_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $passwordRow = $stmt->fetch();

        if (!$passwordRow || !password_verify($currentPassword, $passwordRow['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            $message = 'Password updated successfully.';
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect('login.php');
}

$stats = getUserStats($userId);
$currentLanguage = function_exists('get_current_language') ? get_current_language() : 'english';
$availableLanguages = function_exists('get_frontend_languages') ? get_frontend_languages() : ['english' => 'English'];

if (isset($_POST['update_language']) && empty($error)) {
    $language = $_POST['language'] ?? 'english';
    if (function_exists('is_language_supported') && is_language_supported($language) && set_user_language($language)) {
        $currentLanguage = $language;
        $message = 'Language updated successfully.';
    } else {
        $error = 'Invalid language selection.';
    }
}

$stmt = $conn->prepare("SELECT COUNT(*) AS pending FROM withdrawals WHERE user_id = ? AND status = 'Pending'");
$stmt->execute([$userId]);
$pendingWithdrawals = (int)($stmt->fetch()['pending'] ?? 0);

$siteName = get_setting('site_name', 'HandToGlobal');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: var(--body-bg, #f4f6f9); color: var(--text-primary, #111827); }
        .main-content { margin-left: 260px; min-height: 100vh; padding-top: 56px; transition: margin-left .3s ease; }
        .main-content.expanded { margin-left: 0; }
        .content-area { padding: 24px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 22px; }
        .page-header h1 { margin: 0; font-size: 28px; font-weight: 800; }
        .page-header p { margin: 6px 0 0; color: var(--text-secondary, #6b7280); }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
        .stat-card, .panel { background: var(--card-bg, #fff); border: 1px solid var(--card-border, #e5e7eb); border-radius: 8px; box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,.05)); }
        .stat-card { padding: 18px; }
        .stat-label { color: var(--text-secondary, #6b7280); font-size: 12px; text-transform: uppercase; font-weight: 800; }
        .stat-value { margin-top: 8px; font-size: 24px; font-weight: 800; color: var(--primary, #0d6efd); }
        .panel { padding: 22px; margin-bottom: 20px; }
        .panel h2 { margin: 0 0 18px; font-size: 18px; font-weight: 800; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .form-group.full { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 7px; font-size: 13px; color: var(--text-secondary, #374151); font-weight: 800; }
        input, select { width: 100%; border: 1px solid var(--input-border, #d1d5db); border-radius: 8px; padding: 12px 13px; font-size: 14px; background: var(--input-bg, #fff); color: var(--input-color, #111827); }
        input:focus, select:focus { outline: none; border-color: var(--primary, #0d6efd); box-shadow: 0 0 0 3px rgba(13,110,253,.12); }
        input[readonly] { background: var(--table-header-bg, #f9fafb); color: var(--text-secondary, #6b7280); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 8px; padding: 11px 16px; font-weight: 800; cursor: pointer; text-decoration: none; }
        .btn-primary { background: var(--primary, #0d6efd); color: #fff; }
        .btn-secondary { background: var(--hover, #f3f4f6); color: var(--text-primary, #111827); border: 1px solid var(--border, #e5e7eb); }
        .notice { padding: 13px 15px; border-radius: 8px; margin-bottom: 18px; font-weight: 700; }
        .notice.success { background: #dcfce7; color: #166534; }
        .notice.error { background: #fee2e2; color: #991b1b; }
        .actions { margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap; }
        @media (max-width: 960px) { .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .main-content { margin-left: 0; } }
        @media (max-width: 640px) { .content-area { padding: 16px; } .grid, .form-grid { grid-template-columns: 1fr; } .form-group.full { grid-column: auto; } .page-header { flex-direction: column; } }
    </style>
</head>
<body>
    <?php require 'includes/sidebar.php'; ?>
    <?php require 'includes/topbar.php'; ?>

    <main class="main-content">
        <div class="content-area">
            <div class="page-header">
                <div>
                    <h1>Profile</h1>
                    <p>Account details are loaded from the database on every page load.</p>
                </div>
                <a class="btn btn-secondary" href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>

            <?php if ($message): ?><div class="notice success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="notice error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <section class="grid">
                <div class="stat-card"><div class="stat-label">Current Level</div><div class="stat-value"><?php echo htmlspecialchars(normalizeLevelName($user['level'] ?? 'Bronze')); ?></div></div>
                <div class="stat-card"><div class="stat-label">Balance</div><div class="stat-value">$<?php echo number_format((float)($user['balance'] ?? 0), 2); ?></div></div>
                <div class="stat-card"><div class="stat-label">Tasks Completed</div><div class="stat-value"><?php echo (int)($stats['total_tasks'] ?? 0); ?></div></div>
                <div class="stat-card"><div class="stat-label">Pending Withdrawals</div><div class="stat-value"><?php echo $pendingWithdrawals; ?></div></div>
            </section>

            <section class="panel">
                <h2>Profile Information</h2>
                <form method="post" class="form-grid">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                    </div>
                    <div class="form-group full actions">
                        <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save"></i> Save Profile</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <h2>Security</h2>
                <form method="post" class="form-grid">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" autocomplete="new-password">
                    </div>
                    <div class="form-group actions">
                        <button type="submit" name="update_password" class="btn btn-primary"><i class="fas fa-key"></i> Update Password</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <h2>Language</h2>
                <form method="post" class="form-grid">
                    <div class="form-group">
                        <label>Language</label>
                        <select name="language">
                            <?php foreach ($availableLanguages as $code => $name): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $currentLanguage === $code ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group actions">
                        <button type="submit" name="update_language" class="btn btn-secondary"><i class="fas fa-language"></i> Save Language</button>
                    </div>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
