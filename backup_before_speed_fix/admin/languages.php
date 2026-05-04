<?php
require_once '../config.php';
require_once '../get_setting.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('../admin_login.php');
}

// Get database connection
$conn = getConnection();

// Create languages table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS languages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(10) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            native_name VARCHAR(100) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            is_default TINYINT(1) DEFAULT 0,
            flag_icon VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch(PDOException $e) {
    die("Failed to create languages table: " . $e->getMessage());
}

// Create translations table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS translations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            language_code VARCHAR(10) NOT NULL,
            translation_key VARCHAR(255) NOT NULL,
            translation_value TEXT NOT NULL,
            module VARCHAR(50) DEFAULT 'general',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_translation (language_code, translation_key),
            FOREIGN KEY (language_code) REFERENCES languages(code) ON DELETE CASCADE
        )
    ");
} catch(PDOException $e) {
    die("Failed to create translations table: " . $e->getMessage());
}

$msg = "";
$error = "";

// Handle language operations
if (isset($_POST['add_language'])) {
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $native_name = trim($_POST['native_name']);
    $flag_icon = trim($_POST['flag_icon']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    if (empty($code) || empty($name) || empty($native_name)) {
        $error = "Please fill all required fields";
    } else {
        try {
            // If setting as default, unset previous default
            if ($is_default) {
                $conn->exec("UPDATE languages SET is_default = 0 WHERE is_default = 1");
            }
            
            $stmt = $conn->prepare("INSERT INTO languages (code, name, native_name, flag_icon, is_default) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$code, $name, $native_name, $flag_icon, $is_default]);
            $msg = "Language added successfully!";
        } catch(PDOException $e) {
            $error = "Failed to add language: " . $e->getMessage();
        }
    }
}

if (isset($_POST['edit_language'])) {
    $id = (int)$_POST['language_id'];
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $native_name = trim($_POST['native_name']);
    $flag_icon = trim($_POST['flag_icon']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    if (empty($code) || empty($name) || empty($native_name)) {
        $error = "Please fill all required fields";
    } else {
        try {
            // If setting as default, unset previous default
            if ($is_default) {
                $conn->exec("UPDATE languages SET is_default = 0 WHERE is_default = 1");
            }
            
            $stmt = $conn->prepare("UPDATE languages SET code=?, name=?, native_name=?, flag_icon=?, is_active=?, is_default=? WHERE id=?");
            $stmt->execute([$code, $name, $native_name, $flag_icon, $is_active, $is_default, $id]);
            $msg = "Language updated successfully!";
        } catch(PDOException $e) {
            $error = "Failed to update language: " . $e->getMessage();
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("SELECT is_default FROM languages WHERE id=?");
        $stmt->execute([$id]);
        $language = $stmt->fetch();
        
        if ($language && $language['is_default']) {
            $error = "Cannot delete default language";
        } else {
            $stmt = $conn->prepare("DELETE FROM languages WHERE id=?");
            $stmt->execute([$id]);
            $msg = "Language deleted successfully!";
        }
    } catch(PDOException $e) {
        $error = "Failed to delete language: " . $e->getMessage();
    }
}

if (isset($_GET['toggle_active'])) {
    $id = (int)$_GET['toggle_active'];
    try {
        $stmt = $conn->prepare("SELECT is_default FROM languages WHERE id=?");
        $stmt->execute([$id]);
        $language = $stmt->fetch();
        
        if ($language && $language['is_default']) {
            $error = "Cannot deactivate default language";
        } else {
            $stmt = $conn->prepare("UPDATE languages SET is_active = NOT is_active WHERE id=?");
            $stmt->execute([$id]);
            $msg = "Language status updated successfully!";
        }
    } catch(PDOException $e) {
        $error = "Failed to update language status: " . $e->getMessage();
    }
}

if (isset($_GET['set_default'])) {
    $id = (int)$_GET['set_default'];
    try {
        // Unset previous default
        $conn->exec("UPDATE languages SET is_default = 0 WHERE is_default = 1");
        
        // Set new default
        $stmt = $conn->prepare("UPDATE languages SET is_default = 1 WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Default language updated successfully!";
    } catch(PDOException $e) {
        $error = "Failed to set default language: " . $e->getMessage();
    }
}

// Handle translation operations
if (isset($_POST['add_translation'])) {
    $language_code = $_POST['language_code'];
    $translation_key = trim($_POST['translation_key']);
    $translation_value = trim($_POST['translation_value']);
    $module = $_POST['module'];
    
    if (empty($language_code) || empty($translation_key) || empty($translation_value)) {
        $error = "Please fill all required fields";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO translations (language_code, translation_key, translation_value, module) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE translation_value = ?");
            $stmt->execute([$language_code, $translation_key, $translation_value, $module, $translation_value]);
            $msg = "Translation added successfully!";
        } catch(PDOException $e) {
            $error = "Failed to add translation: " . $e->getMessage();
        }
    }
}

if (isset($_GET['delete_translation'])) {
    $id = (int)$_GET['delete_translation'];
    try {
        $stmt = $conn->prepare("DELETE FROM translations WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Translation deleted successfully!";
    } catch(PDOException $e) {
        $error = "Failed to delete translation: " . $e->getMessage();
    }
}

// Get languages
$languages = [];
try {
    $stmt = $conn->prepare("SELECT * FROM languages ORDER BY is_default DESC, name");
    $stmt->execute();
    $languages = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch languages: " . $e->getMessage();
}

// Get translations
$translations = [];
$language_filter = $_GET['language_filter'] ?? 'all';
$module_filter = $_GET['module_filter'] ?? 'all';

try {
    $sql = "SELECT t.*, l.name as language_name FROM translations t JOIN languages l ON t.language_code = l.code WHERE 1=1";
    $params = [];
    
    if ($language_filter !== 'all') {
        $sql .= " AND t.language_code = ?";
        $params[] = $language_filter;
    }
    
    if ($module_filter !== 'all') {
        $sql .= " AND t.module = ?";
        $params[] = $module_filter;
    }
    
    $sql .= " ORDER BY t.module, t.translation_key";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $translations = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Failed to fetch translations: " . $e->getMessage();
}

// Get language for editing
$edit_language = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    try {
        $stmt = $conn->prepare("SELECT * FROM languages WHERE id=?");
        $stmt->execute([$id]);
        $edit_language = $stmt->fetch();
    } catch(PDOException $e) {
        $error = "Failed to fetch language for editing: " . $e->getMessage();
    }
}

// Get available modules
$modules = [];
try {
    $stmt = $conn->prepare("SELECT DISTINCT module FROM translations ORDER BY module");
    $stmt->execute();
    $module_data = $stmt->fetchAll();
    $modules = array_column($module_data, 'module');
} catch(PDOException $e) {
    $error = "Failed to fetch modules: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Languages Management - HandToGlobal Admin</title>
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
        
        .badge-inactive {
            background: #6c757d;
            color: white;
        }
        
        .badge-default {
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
        
        .flag-icon {
            width: 24px;
            height: 16px;
            border-radius: 2px;
            display: inline-block;
            margin-right: 8px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .tab:hover {
            color: #667eea;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .translation-key {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
        }
        
        .module-badge {
            background: #e9ecef;
            color: #495057;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav-menu">
                <h1><i class="fas fa-language"></i> Languages Management</h1>
                <div class="nav-links">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a>
                    <a href="combos.php"><i class="fas fa-layer-group"></i> Combos</a>
                    <a href="invitation_codes.php"><i class="fas fa-ticket-alt"></i> Codes</a>
                    <a href="finance_analysis.php"><i class="fas fa-chart-line"></i> Finance</a>
                    <a href="deposits.php"><i class="fas fa-dollar-sign"></i> Deposits</a>
                    <a href="withdrawals.php"><i class="fas fa-money-bill-wave"></i> Withdrawals</a>
                    <a href="contacts.php"><i class="fas fa-envelope"></i> Contacts</a>
                    <a href="testimonials.php"><i class="fas fa-quote-left"></i> Testimonials</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="languages.php"><i class="fas fa-language"></i> Languages</a>
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
                <div class="stat-number"><?php echo count($languages); ?></div>
                <div class="stat-label">Total Languages</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $active_count = array_filter($languages, fn($l) => $l['is_active']);
                    echo count($active_count);
                    ?>
                </div>
                <div class="stat-label">Active Languages</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($translations); ?></div>
                <div class="stat-label">Total Translations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($modules); ?></div>
                <div class="stat-label">Translation Modules</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="card">
            <div class="card-header">
                <h2>Language & Translation Management</h2>
            </div>
            
            <div class="tabs">
                <button type="button" class="tab active" onclick="showTab('languages')">
                    <i class="fas fa-language"></i> Languages
                </button>
                <button type="button" class="tab" onclick="showTab('translations')">
                    <i class="fas fa-globe"></i> Translations
                </button>
            </div>
            
            <!-- Languages Tab -->
            <div id="languages" class="tab-content active">
                <!-- Add/Edit Language Form -->
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h3><?php echo $edit_language ? 'Edit Language' : 'Add New Language'; ?></h3>
                        <?php if ($edit_language): ?>
                            <a href="languages.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST">
                        <?php if ($edit_language): ?>
                            <input type="hidden" name="edit_language" value="1">
                            <input type="hidden" name="language_id" value="<?php echo $edit_language['id']; ?>">
                        <?php else: ?>
                            <input type="hidden" name="add_language" value="1">
                        <?php endif; ?>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="code">Language Code *</label>
                                <input type="text" id="code" name="code" class="form-control" 
                                       value="<?php echo $edit_language ? htmlspecialchars($edit_language['code']) : ''; ?>" 
                                       placeholder="e.g., en, es, fr" required>
                            </div>
                            <div class="form-group">
                                <label for="name">Language Name *</label>
                                <input type="text" id="name" name="name" class="form-control" 
                                       value="<?php echo $edit_language ? htmlspecialchars($edit_language['name']) : ''; ?>" 
                                       placeholder="e.g., English, Spanish, French" required>
                            </div>
                            <div class="form-group">
                                <label for="native_name">Native Name *</label>
                                <input type="text" id="native_name" name="native_name" class="form-control" 
                                       value="<?php echo $edit_language ? htmlspecialchars($edit_language['native_name']) : ''; ?>" 
                                       placeholder="e.g., English, Español, Français" required>
                            </div>
                            <div class="form-group">
                                <label for="flag_icon">Flag Icon URL</label>
                                <input type="url" id="flag_icon" name="flag_icon" class="form-control" 
                                       value="<?php echo $edit_language ? htmlspecialchars($edit_language['flag_icon']) : ''; ?>" 
                                       placeholder="https://example.com/flag.png">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="is_active" name="is_active" value="1" 
                                           <?php echo $edit_language ? ($edit_language['is_active'] ? 'checked' : '') : 'checked'; ?>>
                                    <label for="is_active">Active</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="is_default" name="is_default" value="1" 
                                           <?php echo $edit_language && $edit_language['is_default'] ? 'checked' : ''; ?>>
                                    <label for="is_default">Default Language</label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $edit_language ? 'Update Language' : 'Add Language'; ?>
                        </button>
                    </form>
                </div>

                <!-- Languages List -->
                <div class="card">
                    <div class="card-header">
                        <h3>All Languages</h3>
                        <button class="btn btn-success btn-sm" onclick="window.location.reload()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Native Name</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($languages as $language): ?>
                                <tr>
                                    <td>
                                        <span class="flag-icon" style="background: url('<?php echo htmlspecialchars($language['flag_icon'] ?? ''); ?>') center/cover;"></span>
                                        <strong><?php echo htmlspecialchars($language['code']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($language['name']); ?></td>
                                    <td><?php echo htmlspecialchars($language['native_name']); ?></td>
                                    <td>
                                        <?php if ($language['is_default']): ?>
                                            <span class="badge badge-default">Default</span>
                                        <?php endif; ?>
                                        <span class="badge badge-<?php echo $language['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $language['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="languages.php?edit=<?php echo $language['id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php if (!$language['is_default']): ?>
                                                <a href="languages.php?set_default=<?php echo $language['id']; ?>" 
                                                   class="btn btn-secondary btn-sm" 
                                                   onclick="return confirm('Set this as default language?')">
                                                    <i class="fas fa-star"></i> Set Default
                                                </a>
                                                <a href="languages.php?toggle_active=<?php echo $language['id']; ?>" 
                                                   class="btn btn-secondary btn-sm">
                                                    <i class="fas fa-<?php echo $language['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                </a>
                                                <a href="languages.php?delete=<?php echo $language['id']; ?>" 
                                                   class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('Are you sure you want to delete this language?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($languages)): ?>
                        <p style="text-align: center; padding: 40px; color: #666;">
                            No languages found. Add your first language above!
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Translations Tab -->
            <div id="translations" class="tab-content">
                <!-- Add Translation Form -->
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h3>Add New Translation</h3>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="add_translation" value="1">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="language_code">Language *</label>
                                <select id="language_code" name="language_code" class="form-control" required>
                                    <option value="">Select Language</option>
                                    <?php foreach ($languages as $language): ?>
                                        <?php if ($language['is_active']): ?>
                                            <option value="<?php echo $language['code']; ?>">
                                                <?php echo htmlspecialchars($language['name']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="translation_key">Translation Key *</label>
                                <input type="text" id="translation_key" name="translation_key" class="form-control" 
                                       placeholder="e.g., welcome_message" required>
                            </div>
                            <div class="form-group">
                                <label for="module">Module</label>
                                <select id="module" name="module" class="form-control">
                                    <option value="general">General</option>
                                    <option value="admin">Admin</option>
                                    <option value="user">User</option>
                                    <option value="tasks">Tasks</option>
                                    <option value="payments">Payments</option>
                                    <option value="notifications">Notifications</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="translation_value">Translation Value *</label>
                            <textarea id="translation_value" name="translation_value" class="form-control" 
                                      placeholder="Enter the translated text" required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Translation
                        </button>
                    </form>
                </div>

                <!-- Translation Filters -->
                <div class="filter-bar">
                    <form method="GET" class="form-row">
                        <div class="form-group">
                            <label for="language_filter">Language</label>
                            <select id="language_filter" name="language_filter" class="form-control">
                                <option value="all" <?php echo $language_filter === 'all' ? 'selected' : ''; ?>>All Languages</option>
                                <?php foreach ($languages as $language): ?>
                                    <option value="<?php echo $language['code']; ?>" 
                                            <?php echo $language_filter === $language['code'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($language['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="module_filter">Module</label>
                            <select id="module_filter" name="module_filter" class="form-control">
                                <option value="all" <?php echo $module_filter === 'all' ? 'selected' : ''; ?>>All Modules</option>
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?php echo $module; ?>" 
                                            <?php echo $module_filter === $module ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($module); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Translations List -->
                <div class="card">
                    <div class="card-header">
                        <h3>All Translations</h3>
                        <button class="btn btn-success btn-sm" onclick="window.location.reload()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Language</th>
                                <th>Translation</th>
                                <th>Module</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($translations as $translation): ?>
                                <tr>
                                    <td>
                                        <span class="translation-key"><?php echo htmlspecialchars($translation['translation_key']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($translation['language_name']); ?></td>
                                    <td>
                                        <?php 
                                        $text = htmlspecialchars($translation['translation_value']);
                                        echo strlen($text) > 100 ? substr($text, 0, 100) . '...' : $text;
                                        ?>
                                    </td>
                                    <td>
                                        <span class="module-badge"><?php echo htmlspecialchars($translation['module']); ?></span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="languages.php?delete_translation=<?php echo $translation['id']; ?>" 
                                               class="btn btn-danger btn-sm" 
                                               onclick="return confirm('Are you sure you want to delete this translation?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($translations)): ?>
                        <p style="text-align: center; padding: 40px; color: #666;">
                            No translations found for the selected criteria.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (e.target.querySelector('[name="add_language"]') || e.target.querySelector('[name="edit_language"]')) {
                const requiredFields = ['code', 'name', 'native_name'];
                let isValid = true;
                
                requiredFields.forEach(fieldName => {
                    const field = document.getElementById(fieldName);
                    if (!field.value.trim()) {
                        field.style.borderColor = '#dc3545';
                        isValid = false;
                    } else {
                        field.style.borderColor = '';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill all required fields');
                }
            }
        });
    </script>
</body>
</html>
