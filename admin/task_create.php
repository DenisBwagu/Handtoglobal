<?php
require_once '../config.php';
require_once '../get_setting.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    redirect('admin_login.php');
}

// Get database connection
$conn = getConnection();

$msg = "";
$error = "";

// Handle form submission
if (isset($_POST['create_task'])) {
    $level = $_POST['level'];
    $title = trim($_POST['title']);
    $type = trim($_POST['type']);
    $description = trim($_POST['description']);
    $instructions = trim($_POST['instructions']);
    $external_link = trim($_POST['external_link']);
    
    if (empty($title) || empty($level) || empty($type)) {
        $error = "Please fill all required fields";
    } else {
        try {
            // Handle image upload if provided
            $image_filename = null;
            if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
                // Validate image type
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                $file_type = mime_content_type($_FILES['item_image']['tmp_name']);
                
                if (!in_array($file_type, $allowed_types)) {
                    $error = "Invalid image type. Please upload JPG, PNG, or WEBP images.";
                } else {
                    // Generate unique filename
                    $image_filename = time() . '_' . basename($_FILES['item_image']['name']);
                    $image_path = '../uploads/tasks/' . $image_filename;
                    
                    // Create directory if it doesn't exist
                    if (!is_dir('../uploads/tasks')) {
                        mkdir('../uploads/tasks', 0755, true);
                    }
                    
                    if (move_uploaded_file($_FILES['item_image']['tmp_name'], $image_path)) {
                        // Image uploaded successfully
                    } else {
                        $error = "Failed to upload image.";
                        $image_filename = null;
                    }
                }
            }
            
            // Insert new task into database
            $stmt = $conn->prepare("
                INSERT INTO tasks (level, title, type, description, instructions, external_link, image, active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$level, $title, $type, $description, $instructions, $external_link, $image_filename]);
            
            $msg = "Task created successfully!";
            
            // Check if this is an AJAX request
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                // Return JSON response for AJAX
                echo json_encode([
                    'success' => true,
                    'message' => $msg,
                    'task_level' => $level,
                    'redirect' => 'tasks.php'
                ]);
                exit;
            }
            
            // Redirect to tasks list after successful creation
            if (empty($error)) {
                redirect('tasks.php?msg=' . urlencode('Task created successfully!'));
            }
            
            // Clear form
            $_POST = [];
            
        } catch(PDOException $e) {
            $error = "Failed to create task: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Task - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --secondary: #7c3aed;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #0284c7;
            --text: #1a1a1a;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #f5f7fb;
            --white: #ffffff;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }
        
        /* Admin Layout */
        .admin-layout {
            display: flex;
            margin-top: 70px;
            min-height: calc(100vh - 70px);
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--border);
            padding: 20px 0;
            position: fixed;
            top: 70px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 900;
        }
        
        .sidebar-header {
            display: flex;
            align-items: center;
            padding: 0 20px;
            margin-bottom: 30px;
        }
        
        .sidebar-header i {
            font-size: 24px;
            margin-right: 12px;
            color: var(--primary);
        }
        
        .sidebar-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        
        .sidebar-section {
            margin-bottom: 25px;
            padding: 0 20px;
        }
        
        .sidebar-section-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            opacity: 0.6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 2px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: var(--text);
            text-decoration: none;
            border-radius: 0;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }
        
        .sidebar-menu a:hover {
            background: var(--bg);
            color: var(--primary);
        }
        
        .sidebar-menu a.active {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border-left: 3px solid var(--success);
            border-radius: 0 8px 8px 0;
        }
        
        .sidebar-menu i {
            margin-right: 12px;
            width: 16px;
            font-size: 14px;
            text-align: center;
        }
        
        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            height: 70px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 999;
        }
        
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .menu-icon {
            display: none;
            font-size: 20px;
            color: var(--muted);
            cursor: pointer;
        }
        
        .topbar-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .admin-badge {
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .topbar-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .topbar-icon:hover {
            background: var(--border);
            color: var(--text);
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .profile-name {
            font-weight: 500;
            color: var(--text);
        }
        
        .dropdown-arrow {
            font-size: 12px;
            color: var(--muted);
            opacity: 0.6;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--muted);
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Form Card */
        .form-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            padding: 30px;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text);
            background: var(--white);
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-control-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text);
            background: var(--white);
            cursor: pointer;
        }
        
        .form-control-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text);
            background: var(--white);
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #059669;
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: #047857;
        }
        
        /* File Input */
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text);
            background: var(--white);
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <!-- Topbar Header -->
    <div class="topbar">
        <div class="topbar-left">
            <div class="menu-icon">
                <i class="fas fa-bars"></i>
            </div>
            <div class="topbar-title">Create Task</div>
        </div>
        <div class="topbar-right">
            <div class="admin-badge">ADMIN</div>
            <div class="topbar-icon">
                <i class="fas fa-moon"></i>
            </div>
            <div class="profile-info">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></div>
                <div class="dropdown-arrow">
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Admin Layout -->
    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <?php $site_logo = get_setting('site_logo'); ?>
                <?php if ($site_logo): ?>
                    <img src="../<?php echo $site_logo; ?>" alt="<?php echo get_setting('site_name', 'HandToGlobal'); ?>" style="height: 24px; margin-right: 12px;">
                <?php else: ?>
                    <i class="fas fa-hand-holding-usd"></i>
                <?php endif; ?>
                <h2><?php echo get_setting('site_name', 'HandToGlobal'); ?></h2>
            </div>
            
            <!-- MANAGEMENT Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">MANAGEMENT</div>
                <ul class="sidebar-menu">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    <li><a href="employees.php"><i class="fas fa-user-tie"></i> Employees</a></li>
                </ul>
            </div>
            
            <!-- PLATFORM Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">PLATFORM</div>
                <ul class="sidebar-menu">
                    <li><a href="levels.php"><i class="fas fa-layer-group"></i> Levels</a></li>
                    <li><a href="tasks.php" class="active"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="combos.php"><i class="fas fa-link"></i> Combos</a></li>
                    <li><a href="invitation-codes.php"><i class="fas fa-ticket-alt"></i> InvitationCodes</a></li>
                </ul>
            </div>
            
            <!-- FINANCE Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">FINANCE</div>
                <ul class="sidebar-menu">
                    <li><a href="finance_analysis.php"><i class="fas fa-chart-line"></i> FinanceAnalysis</a></li>
                    <li><a href="withdrawals.php"><i class="fas fa-arrow-up"></i> Withdrawals</a></li>
                </ul>
            </div>
            
            <!-- MONITORING Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">MONITORING</div>
                <ul class="sidebar-menu">
                    <li><a href="contacts.php"><i class="fas fa-address-book"></i> Contacts</a></li>
                    <li><a href="testimonials.php"><i class="fas fa-comments"></i> Testimonials</a></li>
                </ul>
            </div>
            
            <!-- SYSTEM Section -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">SYSTEM</div>
                <ul class="sidebar-menu">
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="languages.php"><i class="fas fa-language"></i> Languages</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
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
            
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="tasks.php">Tasks</a> > Create
            </div>
            
            <!-- Form Card -->
            <div class="form-card">
                <form method="POST" enctype="multipart/form-data">
                    <!-- Level -->
                    <div class="form-group">
                        <label class="form-label">Level</label>
                        <select name="level" class="form-control-select" required>
                            <option value="">SelectLevel</option>
                            <option value="Bronze">Bronze</option>
                            <option value="Sliver">Sliver</option>
                            <option value="Gold">Gold</option>
                            <option value="VIP 1">VIP 1</option>
                        </select>
                    </div>
                    
                    <!-- Title -->
                    <div class="form-group">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                    </div>
                    
                    <!-- Type -->
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <input type="text" name="type" class="form-control" value="<?php echo htmlspecialchars($_POST['type'] ?? 'Name_items'); ?>" required>
                    </div>
                    
                    <!-- Description -->
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control-textarea" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Instructions -->
                    <div class="form-group">
                        <label class="form-label">Instructions</label>
                        <textarea name="instructions" class="form-control-textarea"><?php echo htmlspecialchars($_POST['instructions'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- ItemImage -->
                    <div class="form-group">
                        <label class="form-label">ItemImage</label>
                        <div class="file-input-wrapper">
                            <input type="file" name="item_image" class="file-input" accept="image/*" onchange="previewImage(this)">
                        </div>
                        <div id="imagePreview" style="margin-top: 10px; display: none;">
                            <img id="previewImg" src="" alt="Image preview" style="max-width: 200px; max-height: 150px; border: 1px solid #e5e7eb; border-radius: 4px; padding: 4px;">
                        </div>
                    </div>
                    
                    <!-- ExternalLink -->
                    <div class="form-group">
                        <label class="form-label">ExternalLink</label>
                        <input type="text" name="external_link" class="form-control" value="<?php echo htmlspecialchars($_POST['external_link'] ?? ''); ?>">
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="form-group">
                        <button type="submit" name="create_task" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                document.getElementById('imagePreview').style.display = 'none';
            }
        }
        
        // Handle form submission with AJAX for real-time updates
        document.querySelector('form[name="create_task"]').addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            var submitBtn = this.querySelector('button[type="submit"]');
            var originalText = submitBtn.textContent;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';
            
            // Create AJAX request
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            // Show success message
                            showSuccessMessage(response.message);
                            
                            // Update level cards if on dashboard
                            updateLevelCards();
                            
                            // Clear form
                            document.querySelector('form[name="create_task"]').reset();
                            document.getElementById('imagePreview').style.display = 'none';
                            
                            // Redirect after a delay
                            setTimeout(function() {
                                window.location.href = response.redirect;
                            }, 1500);
                        } else {
                            showErrorMessage(response.message || 'Failed to create task');
                        }
                    } catch (e) {
                        showErrorMessage('Invalid response from server');
                    }
                } else {
                    showErrorMessage('Server error occurred');
                }
                
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            };
            
            xhr.onerror = function() {
                showErrorMessage('Network error occurred');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            };
            
            xhr.send(formData);
        });
        
        function updateLevelCards() {
            // Only update if we're on the dashboard page
            if (window.location.pathname.includes('dashboard.php')) {
                fetch('get_level_stats.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update level progress section
                            updateLevelProgressSection(data.level_stats);
                            
                            // Update stats cards
                            updateStatsCards(data);
                        }
                    })
                    .catch(error => {
                        console.error('Error updating level stats:', error);
                    });
            }
        }
        
        function updateLevelProgressSection(levelStats) {
            // Find and update level progress elements
            Object.keys(levelStats).forEach(function(level) {
                var levelElement = document.querySelector('[data-level="' + level + '"]');
                if (levelElement) {
                    var completedEl = levelElement.querySelector('.level-progress');
                    var progressEl = levelElement.querySelector('.progress-fill');
                    var availableEl = levelElement.querySelector('.available-tasks');
                    
                    if (completedEl) {
                        completedEl.textContent = levelStats[level].completed + '/' + levelStats[level].total;
                    }
                    if (progressEl) {
                        progressEl.style.width = levelStats[level].progress + '%';
                    }
                    if (availableEl) {
                        availableEl.textContent = 'Available: ' + levelStats[level].available;
                    }
                }
            });
        }
        
        function updateStatsCards(data) {
            // Update stats cards if they exist
            var totalTasksEl = document.querySelector('[data-stat="total_tasks"]');
            var completedTasksEl = document.querySelector('[data-stat="completed_tasks"]');
            var activeCombosEl = document.querySelector('[data-stat="active_combos"]');
            
            if (totalTasksEl) totalTasksEl.textContent = data.total_tasks;
            if (completedTasksEl) completedTasksEl.textContent = data.completed_tasks;
            if (activeCombosEl) activeCombosEl.textContent = data.active_combos;
        }
        
        function showSuccessMessage(message) {
            showMessage(message, 'success');
        }
        
        function showErrorMessage(message) {
            showMessage(message, 'error');
        }
        
        function showMessage(message, type) {
            // Remove existing messages
            var existingMessages = document.querySelectorAll('.alert-message');
            existingMessages.forEach(function(msg) {
                msg.remove();
            });
            
            // Create new message
            var messageDiv = document.createElement('div');
            messageDiv.className = 'alert-message alert-' + type;
            messageDiv.textContent = message;
            messageDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 4px;
                color: white;
                font-weight: 500;
                z-index: 9999;
                max-width: 300px;
                word-wrap: break-word;
                ${type === 'success' ? 'background: #22c55e;' : 'background: #ef4444;'}
            `;
            
            document.body.appendChild(messageDiv);
            
            // Remove message after 3 seconds
            setTimeout(function() {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, 3000);
        }
    </script>
</body>
</html>
