<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
}

// Get database connection
$conn = getConnection();

// Create employees table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id VARCHAR(20) NOT NULL UNIQUE,
            firstname VARCHAR(100) NOT NULL,
            lastname VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            phone VARCHAR(20),
            department VARCHAR(100),
            position VARCHAR(100),
            salary DECIMAL(10,2),
            hire_date DATE,
            status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
            address TEXT,
            city VARCHAR(100),
            country VARCHAR(100),
            postal_code VARCHAR(20),
            emergency_contact_name VARCHAR(255),
            emergency_contact_phone VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch(PDOException $e) {
    die("Failed to create employees table: " . $e->getMessage());
}

// Create employee_attendance table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS employee_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            date DATE NOT NULL,
            check_in TIME,
            check_out TIME,
            break_duration INT DEFAULT 0,
            total_hours DECIMAL(4,2),
            status ENUM('present', 'absent', 'late', 'half_day') DEFAULT 'present',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id),
            UNIQUE KEY unique_attendance (employee_id, date)
        )
    ");
} catch(PDOException $e) {
    die("Failed to create employee_attendance table: " . $e->getMessage());
}

$msg = "";
$error = "";

// Handle employee operations
if (isset($_POST['add_employee'])) {
    $employee_id = trim($_POST['employee_id']);
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $position = trim($_POST['position']);
    $salary = (float)$_POST['salary'];
    $hire_date = $_POST['hire_date'];
    $status = $_POST['status'];
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $country = trim($_POST['country']);
    $postal_code = trim($_POST['postal_code']);
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
    
    if (empty($employee_id) || empty($firstname) || empty($lastname) || empty($email)) {
        $error = "Please fill all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO employees (employee_id, firstname, lastname, email, phone, department, position, salary, hire_date, status, address, city, country, postal_code, emergency_contact_name, emergency_contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$employee_id, $firstname, $lastname, $email, $phone, $department, $position, $salary, $hire_date, $status, $address, $city, $country, $postal_code, $emergency_contact_name, $emergency_contact_phone]);
            $msg = "Employee added successfully!";
        } catch(PDOException $e) {
            $error = "Failed to add employee: " . $e->getMessage();
        }
    }
}

if (isset($_POST['edit_employee'])) {
    $id = (int)$_POST['employee_id_edit'];
    $employee_id = trim($_POST['employee_id']);
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $position = trim($_POST['position']);
    $salary = (float)$_POST['salary'];
    $hire_date = $_POST['hire_date'];
    $status = $_POST['status'];
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $country = trim($_POST['country']);
    $postal_code = trim($_POST['postal_code']);
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
    
    if (empty($employee_id) || empty($firstname) || empty($lastname) || empty($email)) {
        $error = "Please fill all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE employees SET employee_id=?, firstname=?, lastname=?, email=?, phone=?, department=?, position=?, salary=?, hire_date=?, status=?, address=?, city=?, country=?, postal_code=?, emergency_contact_name=?, emergency_contact_phone=? WHERE id=?");
            $stmt->execute([$employee_id, $firstname, $lastname, $email, $phone, $department, $position, $salary, $hire_date, $status, $address, $city, $country, $postal_code, $emergency_contact_name, $emergency_contact_phone, $id]);
            $msg = "Employee updated successfully!";
        } catch(PDOException $e) {
            $error = "Failed to update employee: " . $e->getMessage();
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM employees WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Employee deleted successfully!";
    } catch(PDOException $e) {
        $error = "Failed to delete employee: " . $e->getMessage();
    }
}

if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    try {
        $stmt = $conn->prepare("UPDATE employees SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Employee status updated successfully!";
    } catch(PDOException $e) {
        $error = "Failed to update employee status: " . $e->getMessage();
    }
}

// Handle search and pagination
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$whereClause = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (e.firstname LIKE ? OR e.lastname LIKE ? OR e.email LIKE ? OR e.employee_id LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}

if ($department_filter !== 'all') {
    $whereClause .= " AND e.department = ?";
    $params[] = $department_filter;
}

if ($status_filter !== 'all') {
    $whereClause .= " AND e.status = ?";
    $params[] = $status_filter;
}

// Get total employees count
$countSql = "SELECT COUNT(*) as total FROM employees e" . $whereClause;
$stmt = $conn->prepare($countSql);
$stmt->execute($params);
$totalEmployees = $stmt->fetch()['total'];
$totalPages = ceil($totalEmployees / $limit);

// Get employees list
$sql = "SELECT e.*, 
               (SELECT COUNT(*) FROM employee_attendance WHERE employee_id = e.id) as attendance_days
        FROM employees e" . $whereClause . " 
        ORDER BY e.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Get statistics
$stats = [];
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees");
    $stmt->execute();
    $stats['total'] = $stmt->fetch()['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as active FROM employees WHERE status='active'");
    $stmt->execute();
    $stats['active'] = $stmt->fetch()['active'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as inactive FROM employees WHERE status='inactive'");
    $stmt->execute();
    $stats['inactive'] = $stmt->fetch()['inactive'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as on_leave FROM employees WHERE status='on_leave'");
    $stmt->execute();
    $stats['on_leave'] = $stmt->fetch()['on_leave'];
    
    $stmt = $conn->prepare("SELECT SUM(salary) as total_payroll FROM employees WHERE status='active'");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_payroll'] = $result['total_payroll'] ?? 0;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as new_hires FROM employees WHERE hire_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $stats['new_hires'] = $stmt->fetch()['new_hires'];
    
} catch(PDOException $e) {
    $error = "Failed to fetch statistics: " . $e->getMessage();
}

// Get departments
$departments = [];
try {
    $stmt = $conn->prepare("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department");
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $error = "Failed to fetch departments: " . $e->getMessage();
}

// Get employee for editing
$edit_employee = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    try {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE id=?");
        $stmt->execute([$id]);
        $edit_employee = $stmt->fetch();
    } catch(PDOException $e) {
        $error = "Failed to fetch employee for editing: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees Management - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .nav-menu {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102,126,234,0.2);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-active {
            background: #28a745;
            color: white;
        }
        
        .badge-inactive {
            background: #6c757d;
            color: white;
        }
        
        .badge-on-leave {
            background: #ffc107;
            color: #212529;
        }
        
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .filter-bar {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        
        .pagination a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #667eea;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        
        .pagination .active {
            background: #667eea;
            color: white;
        }
        
        .employee-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .salary {
            font-weight: bold;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav-menu">
                <h1><i class="fas fa-users-cog"></i> Employees Management</h1>
                <div class="nav-links">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a href="employees.php"><i class="fas fa-users-cog"></i> Employees</a>
                    <a href="levels.php"><i class="fas fa-layer-group"></i> Levels</a>
                    <a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
                    <a href="combos.php"><i class="fas fa-layer-group"></i> Combos</a>
                    <a href="invitation-codes.php"><i class="fas fa-ticket-alt"></i> Codes</a>
                    <a href="finance-analysis.php"><i class="fas fa-chart-line"></i> Finance</a>
                    <a href="../admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($msg): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Employees</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Active Employees</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['inactive']; ?></div>
                <div class="stat-label">Inactive Employees</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['total_payroll'], 2); ?></div>
                <div class="stat-label">Total Payroll</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['new_hires']; ?></div>
                <div class="stat-label">New Hires (30 days)</div>
            </div>
        </div>

        <!-- Add/Edit Employee Form -->
        <div class="card">
            <div class="card-header">
                <h2><?php echo $edit_employee ? 'Edit Employee' : 'Add New Employee'; ?></h2>
                <?php if ($edit_employee): ?>
                    <a href="employees.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
            
            <form method="POST">
                <?php if ($edit_employee): ?>
                    <input type="hidden" name="edit_employee" value="1">
                    <input type="hidden" name="employee_id_edit" value="<?php echo $edit_employee['id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="add_employee" value="1">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="employee_id">Employee ID *</label>
                        <input type="text" id="employee_id" name="employee_id" class="form-control" 
                               value="<?php echo $edit_employee ? htmlspecialchars($edit_employee['employee_id']) : ''; ?>" 
                               placeholder="e.g., EMP001" required>
                    </div>
                    <div class="form-group">
                        <label for="firstname">First Name *</label>
                        <input type="text" id="firstname" name="firstname" class="form-control" 
                               value="<?php echo $edit_employee ? htmlspecialchars($edit_employee['firstname']) : ''; ?>" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="lastname">Last Name *</label>
                        <input type="text" id="lastname" name="lastname" class="form-control" 
                               value="<?php echo $edit_employee ? htmlspecialchars($edit_employee['lastname']) : ''; ?>" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo $edit_employee ? htmlspecialchars($edit_employee['email']) : ''; ?>" 
                               required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo $edit_employee ? htmlspecialchars($edit_employee['phone']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department" class="form-control" 
                               value="<?php echo $edit_employee ? htmlspecialchars($edit_employee['department']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="position">Position</label>
                        <input type="text" id="position" name="position" class="form-control" 
                               value="<?php echo $edit_employee ? htmlspecialchars($edit_employee['position']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="salary">Salary</label>
                        <input type="number" id="salary" name="salary" class="form-control" 
                               step="0.01" min="0" 
                               value="<?php echo $edit_employee ? htmlspecialchars($edit_employee['salary']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="hire_date">Hire Date</label>
                        <input type="date" id="hire_date" name="hire_date" class="form-control" 
                               value="<?php echo $edit_employee ? htmlspecialchars($edit_employee['hire_date']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="active" <?php echo $edit_employee && $edit_employee['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $edit_employee && $edit_employee['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="on_leave" <?php echo $edit_employee && $edit_employee['status'] == 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" class="form-control" 
                               value="<?php echo $edit_employee ? htmlspecialchars($edit_employee['city']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country" class="form-control" 
                               value="<?php echo $edit_employee ? htmlspecialchars($edit_employee['country']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control"><?php 
                        echo $edit_employee ? htmlspecialchars($edit_employee['address']) : ''; 
                    ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="emergency_contact_name">Emergency Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" 
                               value="<?php echo $edit_employee ? htmlspecialchars($edit_employee['emergency_contact_name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="emergency_contact_phone">Emergency Contact Phone</label>
                        <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" 
                               value="<?php echo $edit_employee ? htmlspecialchars($edit_employee['emergency_contact_phone']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="postal_code">Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code" class="form-control" 
                               value="<?php echo $edit_employee ? htmlspecialchars($edit_employee['postal_code']) : ''; ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $edit_employee ? 'Update Employee' : 'Add Employee'; ?>
                </button>
            </form>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="form-row">
                <div class="form-group">
                    <label for="search">Search Employees</label>
                    <input type="text" id="search" name="search" class="form-control" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Name, Email, or Employee ID">
                </div>
                <div class="form-group">
                    <label for="department">Department</label>
                    <select id="department" name="department" class="form-control">
                        <option value="all" <?php echo $department_filter === 'all' ? 'selected' : ''; ?>>All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept; ?>" <?php echo $department_filter === $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="on_leave" <?php echo $status_filter === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Employees List -->
        <div class="card">
            <div class="card-header">
                <h2>All Employees</h2>
                <button class="btn btn-success btn-sm" onclick="window.location.reload()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Employee ID</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Salary</th>
                        <th>Status</th>
                        <th>Hire Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td>
                                <div class="employee-info">
                                    <div class="employee-avatar">
                                        <?php echo strtoupper(substr($employee['firstname'], 0, 1) . substr($employee['lastname'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($employee['firstname'] . ' ' . $employee['lastname']); ?></strong>
                                        <br><small><?php echo htmlspecialchars($employee['email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                            <td><?php echo $employee['department'] ? htmlspecialchars($employee['department']) : '-'; ?></td>
                            <td><?php echo $employee['position'] ? htmlspecialchars($employee['position']) : '-'; ?></td>
                            <td>
                                <span class="salary">$<?php echo number_format($employee['salary'], 2); ?></span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo str_replace('_', '-', $employee['status']); ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $employee['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo $employee['hire_date'] ? date('M j, Y', strtotime($employee['hire_date'])) : '-'; ?></td>
                            <td>
                                <div class="actions">
                                    <a href="employees.php?edit=<?php echo $employee['id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="employees.php?toggle_status=<?php echo $employee['id']; ?>" 
                                       class="btn btn-secondary btn-sm" 
                                       onclick="return confirm('Are you sure you want to toggle employee status?')">
                                        <i class="fas fa-exchange-alt"></i> Toggle
                                    </a>
                                    <a href="employees.php?delete=<?php echo $employee['id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this employee?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($employees)): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No employees found for the selected criteria.
                </p>
            <?php endif; ?>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo $department_filter; ?>&status=<?php echo $status_filter; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo $department_filter; ?>&status=<?php echo $status_filter; ?>" 
                           class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo $department_filter; ?>&status=<?php echo $status_filter; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
