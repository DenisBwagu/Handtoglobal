<?php
require_once 'config.php';

header('Content-Type: text/plain; charset=utf-8');

function testLine($label, $ok, $detail = '') {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label;
    if ($detail !== '') {
        echo ' - ' . $detail;
    }
    echo PHP_EOL;
}

try {
    $conn = getConnection();
    testLine('Database connection', true, 'Connected to handtoglobal');

    ensureAuthSchema();

    $usersExists = (bool)$conn->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    testLine('users table exists', $usersExists);

    $adminsExists = (bool)$conn->query("SHOW TABLES LIKE 'admins'")->fetchColumn();
    testLine('admins table exists', $adminsExists);

    $stmt = $conn->prepare("SELECT id, name, email, password FROM admins WHERE email = ? LIMIT 1");
    $stmt->execute(['admin@handtoglobal.com']);
    $admin = $stmt->fetch();
    testLine('default admin exists', (bool)$admin, $admin ? 'id=' . $admin['id'] : '');
    testLine('default admin password hash works', $admin && password_verify('admin123', $admin['password']));
    testLine('can fetch admin by email', (bool)$admin, 'admin@handtoglobal.com');

    $hash = password_hash('sample123', PASSWORD_DEFAULT);
    testLine('password_hash/password_verify works', password_verify('sample123', $hash));

    $user = $conn->query("SELECT id, fullname, email FROM users ORDER BY id DESC LIMIT 1")->fetch();
    testLine('can fetch user by email if users exist', true, $user ? $user['email'] : 'no users yet');

    if ($user) {
        $stmt = $conn->prepare("SELECT id, fullname, email FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$user['email']]);
        $fetchedUser = $stmt->fetch();
        testLine('latest user email lookup', (bool)$fetchedUser, $user['email']);
    }
} catch (Throwable $e) {
    testLine('auth test crashed', false, $e->getMessage());
}
