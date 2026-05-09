<?php
require_once '../config.php';
require_once '../includes/settings_helpers.php';
require_once '../includes/admin_helpers.php';
require_once '../includes/admin_helpers.php';

if (!isAdminLoggedIn()) {
    redirect('../login.php');
}

$conn = getConnection();
$employeeId = (int)($_GET['id'] ?? 0);

if ($employeeId <= 0) {
    redirect('employees.php');
}

$error = '';
$msg = '';

$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    redirect('employees.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = trim($_POST['role'] ?? 'Employee');
    $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

    if ($name === '') {
        $error = 'Name is required.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid email is required.';
    } else {
        try {
            $stmt = $conn->prepare("SELECT id FROM employees WHERE email = ? AND id <> ? LIMIT 1");
            $stmt->execute([$email, $employeeId]);
            if ($stmt->fetch()) {
                $error = 'Email already exists.';
            } else {
                $stmt = $conn->prepare("
                    UPDATE employees
                    SET name = ?, fullname = ?, email = ?, phone = ?, role = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $name, $email, $phone ?: null, $role, $status, $employeeId]);
                redirect('employees.php?updated=1');
            }
        } catch (Throwable $e) {
            $error = 'Failed to update employee: ' . $e->getMessage();
        }
    }
}

$name = $employee['name'] ?: ($employee['fullname'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - <?php echo htmlspecialchars(get_site_name()); ?> Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="includes/admin_styles.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="admin-layout">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        <div class="main-content">
            <?php admin_back_button('employees.php'); ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($msg): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>

            <div class="page-header">
                <h1><?php echo __t('edit_employee', 'Edit Employee'); ?></h1>
                <p>Update employee account details.</p>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="post">
                        <div class="form-group">
                            <label><?php echo __t('name', 'Name'); ?></label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?php echo __t('email', 'Email'); ?></label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?php echo __t('phone', 'Phone'); ?></label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php echo __t('role', 'Role'); ?></label>
                            <input type="text" name="role" class="form-control" value="<?php echo htmlspecialchars($employee['role'] ?? 'Employee'); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php echo __t('status', 'Status'); ?></label>
                            <select name="status" class="form-control">
                                <option value="Active" <?php echo ($employee['status'] ?? 'Active') === 'Active' ? 'selected' : ''; ?>><?php echo __t('active', 'Active'); ?></option>
                                <option value="Inactive" <?php echo ($employee['status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>><?php echo __t('inactive', 'Inactive'); ?></option>
                            </select>
                        </div>
                        <div class="actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            <a href="employee_view.php?id=<?php echo $employeeId; ?>" class="btn btn-secondary"><?php echo __t('cancel', 'Cancel'); ?></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
