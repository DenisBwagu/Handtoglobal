<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
}

// Get database connection
$conn = getConnection();

// Create invitation_codes table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS invitation_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            created_by INT NOT NULL,
            used_by INT NULL,
            is_used TINYINT(1) DEFAULT 0,
            bonus_amount DECIMAL(10,2) DEFAULT 0.00,
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            used_at TIMESTAMP NULL,
            FOREIGN KEY (created_by) REFERENCES admins(id),
            FOREIGN KEY (used_by) REFERENCES users(id)
        )
    ");
} catch(PDOException $e) {
    die("Failed to create invitation_codes table: " . $e->getMessage());
}

$msg = "";
$error = "";

// Generate unique invitation code
function generateInvitationCode($length = 8) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $code;
}

// Handle invitation code operations
if (isset($_POST['generate_codes'])) {
    $count = (int)$_POST['code_count'];
    $bonus = (float)$_POST['bonus_amount'];
    $expires_days = (int)$_POST['expires_days'];
    
    if ($count <= 0 || $count > 100) {
        $error = "Please enter a valid number of codes (1-100)";
    } else {
        $generated_codes = [];
        $expires_at = null;
        
        if ($expires_days > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_days days"));
        }
        
        try {
            $admin_id = $_SESSION['admin'];
            
            for ($i = 0; $i < $count; $i++) {
                $code = generateInvitationCode();
                
                // Ensure code is unique
                $stmt = $conn->prepare("SELECT id FROM invitation_codes WHERE code = ?");
                $stmt->execute([$code]);
                
                while ($stmt->fetch()) {
                    $code = generateInvitationCode();
                    $stmt->execute([$code]);
                }
                
                $stmt = $conn->prepare("INSERT INTO invitation_codes (code, created_by, bonus_amount, expires_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$code, $admin_id, $bonus, $expires_at]);
                $generated_codes[] = $code;
            }
            
            $msg = "Successfully generated $count invitation codes!";
            
            // Store generated codes in session for display
            $_SESSION['generated_codes'] = $generated_codes;
            
        } catch(PDOException $e) {
            $error = "Failed to generate codes: " . $e->getMessage();
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM invitation_codes WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Invitation code deleted successfully!";
    } catch(PDOException $e) {
        $error = "Failed to delete invitation code: " . $e->getMessage();
    }
}

if (isset($_GET['deactivate'])) {
    $id = (int)$_GET['deactivate'];
    try {
        $stmt = $conn->prepare("UPDATE invitation_codes SET expires_at = NOW() WHERE id=? AND is_used=0");
        $stmt->execute([$id]);
        $msg = "Invitation code deactivated successfully!";
    } catch(PDOException $e) {
        $error = "Failed to deactivate invitation code: " . $e->getMessage();
    }
}

// Get invitation codes for display
$codes = [];
try {
    $stmt = $conn->prepare("
        SELECT ic.*, a.email as created_by_email, u.email as used_by_email 
        FROM invitation_codes ic 
        LEFT JOIN admins a ON ic.created_by = a.id 
        LEFT JOIN users u ON ic.used_by = u.id 
        ORDER BY ic.created_at DESC
    ");
    $stmt->execute();
    $codes = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch invitation codes: " . $e->getMessage();
}

// Get statistics
$stats = [];
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM invitation_codes");
    $stmt->execute();
    $stats['total'] = $stmt->fetch()['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as used FROM invitation_codes WHERE is_used=1");
    $stmt->execute();
    $stats['used'] = $stmt->fetch()['used'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as active FROM invitation_codes WHERE is_used=0 AND (expires_at IS NULL OR expires_at > NOW())");
    $stmt->execute();
    $stats['active'] = $stmt->fetch()['active'];
    
    $stmt = $conn->prepare("SELECT SUM(bonus_amount) as total_bonus FROM invitation_codes WHERE is_used=1");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_bonus'] = $result['total_bonus'] ?? 0;
    
} catch(PDOException $e) {
    $error = "Failed to fetch statistics: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation Codes - HandToGlobal Admin</title>
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
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .badge-used {
            background: #6c757d;
            color: white;
        }
        
        .badge-expired {
            background: #dc3545;
            color: white;
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
        
        .code-display {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .code-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .code-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            text-align: center;
            font-family: monospace;
            font-weight: bold;
            color: #667eea;
        }
        
        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            margin-top: 5px;
        }
        
        .copy-btn:hover {
            background: #5a6fd8;
        }
        
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-active {
            background: #28a745;
        }
        
        .status-used {
            background: #6c757d;
        }
        
        .status-expired {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav-menu">
                <h1><i class="fas fa-ticket-alt"></i> Invitation Codes</h1>
                <div class="nav-links">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
                    <a href="combos.php"><i class="fas fa-layer-group"></i> Combos</a>
                    <a href="invitation-codes.php"><i class="fas fa-ticket-alt"></i> Codes</a>
                    <a href="finance-analysis.php"><i class="fas fa-chart-line"></i> Finance</a>
                    <a href="deposits.php"><i class="fas fa-dollar-sign"></i> Deposits</a>
                    <a href="withdrawals.php"><i class="fas fa-money-bill-wave"></i> Withdrawals</a>
                    <a href="/handtoglobal/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
                <div class="stat-label">Total Codes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Active Codes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['used']; ?></div>
                <div class="stat-label">Used Codes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['total_bonus'], 2); ?></div>
                <div class="stat-label">Total Bonus Paid</div>
            </div>
        </div>

        <!-- Generate Codes Form -->
        <div class="card">
            <div class="card-header">
                <h2>Generate Invitation Codes</h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="generate_codes" value="1">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="code_count">Number of Codes *</label>
                        <input type="number" id="code_count" name="code_count" class="form-control" 
                               min="1" max="100" value="10" required>
                        <small style="color: #666;">Maximum 100 codes at once</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="bonus_amount">Bonus Amount (USDT)</label>
                        <input type="number" id="bonus_amount" name="bonus_amount" class="form-control" 
                               step="0.01" min="0" value="5.00">
                        <small style="color: #666;">Bonus given to users who use these codes</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="expires_days">Expires In (Days)</label>
                        <input type="number" id="expires_days" name="expires_days" class="form-control" 
                               min="0" value="30">
                        <small style="color: #666;">0 = No expiration</small>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-magic"></i> Generate Codes
                </button>
            </form>
            
            <?php if (isset($_SESSION['generated_codes']) && !empty($_SESSION['generated_codes'])): ?>
                <div class="code-display">
                    <h4><i class="fas fa-check-circle"></i> Generated Codes:</h4>
                    <div class="code-list">
                        <?php foreach ($_SESSION['generated_codes'] as $code): ?>
                            <div class="code-item">
                                <?php echo htmlspecialchars($code); ?>
                                <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($code); ?>')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn btn-secondary btn-sm" onclick="clearGeneratedCodes()" style="margin-top: 10px;">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Invitation Codes List -->
        <div class="card">
            <div class="card-header">
                <h2>All Invitation Codes</h2>
                <button class="btn btn-success btn-sm" onclick="window.location.reload()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Bonus</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Used By</th>
                        <th>Created</th>
                        <th>Expires</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($codes as $code): ?>
                        <tr>
                            <td><?php echo $code['id']; ?></td>
                            <td>
                                <strong style="font-family: monospace; color: #667eea;">
                                    <?php echo htmlspecialchars($code['code']); ?>
                                </strong>
                            </td>
                            <td>$<?php echo number_format($code['bonus_amount'], 2); ?></td>
                            <td>
                                <?php
                                $status = 'active';
                                $badge_class = 'badge-active';
                                $status_class = 'status-active';
                                
                                if ($code['is_used']) {
                                    $status = 'used';
                                    $badge_class = 'badge-used';
                                    $status_class = 'status-used';
                                } elseif ($code['expires_at'] && strtotime($code['expires_at']) < time()) {
                                    $status = 'expired';
                                    $badge_class = 'badge-expired';
                                    $status_class = 'status-expired';
                                }
                                ?>
                                <span class="status-indicator <?php echo $status_class; ?>"></span>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($code['created_by_email'] ?? 'Unknown'); ?></td>
                            <td><?php echo $code['used_by_email'] ? htmlspecialchars($code['used_by_email']) : '-'; ?></td>
                            <td><?php echo date('M j, Y', strtotime($code['created_at'])); ?></td>
                            <td>
                                <?php 
                                if ($code['expires_at']) {
                                    echo date('M j, Y', strtotime($code['expires_at']));
                                } else {
                                    echo 'Never';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <button class="btn btn-secondary btn-sm" onclick="copyToClipboard('<?php echo htmlspecialchars($code['code']); ?>')">
                                        <i class="fas fa-copy"></i> Copy
                                    </button>
                                    <?php if (!$code['is_used'] && (!$code['expires_at'] || strtotime($code['expires_at']) > time())): ?>
                                        <a href="invitation-codes.php?deactivate=<?php echo $code['id']; ?>" 
                                           class="btn btn-warning btn-sm" 
                                           onclick="return confirm('Are you sure you want to deactivate this code?')">
                                            <i class="fas fa-pause"></i> Deactivate
                                        </a>
                                    <?php endif; ?>
                                    <a href="invitation-codes.php?delete=<?php echo $code['id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this invitation code?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($codes)): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No invitation codes found. Generate your first codes above!
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show success message
                const btn = event.target;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                btn.style.background = '#28a745';
                
                setTimeout(function() {
                    btn.innerHTML = originalText;
                    btn.style.background = '';
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
            });
        }
        
        function clearGeneratedCodes() {
            if (confirm('Are you sure you want to clear the generated codes display?')) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'clear_codes=1'
                }).then(() => {
                    location.reload();
                });
            }
        }
        
        // Handle clear codes request
        <?php if (isset($_POST['clear_codes'])): ?>
            <?php unset($_SESSION['generated_codes']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
