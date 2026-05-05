<?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 require_once __DIR__ . '/../config.php'; ?>
<?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}

/**
 * Edit Combo Page
 * This page allows admins to edit existing combos
 */

require_once __DIR__ . '/../config.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get combo ID from URL
$comboId = (int)($_GET['id'] ?? 0);

if ($comboId <= 0) {
    header('Location: combos.php?error=Invalid combo ID');
    exit;
}

try {
    $conn = getConnection();
    
    // Load combo data
    $stmt = $conn->prepare("SELECT * FROM combos WHERE id = ?");
    $stmt->execute([$comboId]);
    $combo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$combo) {
        header('Location: combos.php?error=Combo not found');
        exit;
    }
    
    // Get levels for dropdown
    $stmt = $conn->prepare("SELECT DISTINCT level FROM tasks WHERE active = 1 ORDER BY level");
    $stmt->execute();
    $levels = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get assigned user if any
    $assignedUser = null;
    if ($combo['user_id']) {
        $stmt = $conn->prepare("SELECT fullname, email FROM users WHERE id = ?");
        $stmt->execute([$combo['user_id']]);
        $assignedUser = $stmt->fetch();
    }
    
} catch (Exception $e) {
    header('Location: combos.php?error=Database error');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_combo'])) {
    $level = trim($_POST['level'] ?? '');
    $start_task = (int)($_POST['start_task'] ?? 0);
    $end_task = (int)($_POST['end_task'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $multiplier = (float)($_POST['multiplier'] ?? 1);
    $message = trim($_POST['message'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    
    if (empty($level) || $start_task <= 0 || $end_task <= 0 || empty($message)) {
        $error = "Please fill all required fields.";
    } elseif ($start_task > $end_task) {
        $error = "Start task must be less than or equal to end task.";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than 0.";
    } else {
        try {
            // Check for duplicate combo (excluding current combo)
            $stmt = $conn->prepare("
                SELECT id FROM combos 
                WHERE level = ? AND start_task = ? AND end_task = ? AND id != ?
            ");
            $stmt->execute([$level, $start_task, $end_task, $comboId]);
            if ($stmt->fetch()) {
                $error = "A combo with the same level and task range already exists.";
            } else {
                $stmt = $conn->prepare("
                    UPDATE combos 
                    SET level = ?, start_task = ?, end_task = ?, amount = ?, multiplier = ?, user_id = ?, message = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$level, $start_task, $end_task, $amount, $multiplier, $user_id, $message, $status, $comboId]);
                
                // Redirect silently to combos page
                header("Location: combos.php");
                exit;
            }
        } catch(PDOException $e) {
            $error = "Failed to update combo: " . $e->getMessage();
        }
    }
}

// Get messages from URL
$msg = $_GET['msg'] ?? $msg ?? '';
$error = $_GET['error'] ?? $error ?? '';

$siteName = get_setting('site_name', 'HandToGlobal');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Combo - HandToGlobal Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="includes/admin_styles.css">
    <style>
        .user-select-container {
            position: relative;
        }
        
        .user-search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .user-search-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f3f5;
        }
        
        .user-search-item:hover {
            background: #f8f9fa;
        }
        
        .user-search-item:last-child {
            border-bottom: none;
        }
        
        .user-name {
            font-weight: 500;
            color: #212529;
            font-size: 14px;
        }
        
        .user-email {
            color: #6c757d;
            font-size: 12px;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 require_once __DIR__ . '/includes/topbar.php'; ?>
    
    <!-- Admin Layout -->
    <div class="admin-layout">
        <?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <a href="combos.php" class="btn btn-secondary" style="text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Back to Combos
                    </a>
                    <div>
                        <h1 class="page-title">Edit Combo</h1>
                        <p class="page-subtitle">Modify combo settings for ID: <?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 echo $comboId; ?></p>
                    </div>
                </div>
            </div>
            
                        
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">Edit Combo Details</h1>
                </div>
                
                <div class="card-body">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="level">Level</label>
                                <select id="level" name="level" required onchange="loadTasksByLevel()">
                                    <option value="">Select Level</option>
                                    <?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 foreach ($levels as $level): ?>
                                        <option value="<?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 echo htmlspecialchars($level); ?>" <?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 echo $combo['level'] === $level ? 'selected' : ''; ?>>
                                            <?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 echo htmlspecialchars($level); ?>
                                        </option>
                                    <?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="user_select">Select User</label>
                                <div class="user-select-container">
                                    <input type="text" id="user_select" name="user_select" placeholder="Search user by name or email..." autocomplete="off" value="<?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 echo $assignedUser ? htmlspecialchars($assignedUser['fullname'] . ' - ' . $assignedUser['email']) : ''; ?>">
                                    <input type="hidden" id="user_id" name="user_id" value="<?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 echo $combo['user_id'] ?: ''; ?>">
                                    <div id="user_search_results" class="user-search-results" style="display: none;"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_task">Start Task</label>
                                <select id="start_task" name="start_task" required>
                                    <option value="">Select Level First</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="end_task">End Task</label>
                                <select id="end_task" name="end_task" required>
                                    <option value="">Select Level First</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="amount">Amount ($)</label>
                                <input type="number" id="amount" name="amount" step="0.01" min="0" value="<?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 echo $combo['amount']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" required>
                                    <option value="active" <?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 echo $combo['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 echo $combo['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" required placeholder="Enter combo message for users" rows="3"><?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 echo htmlspecialchars($combo['message']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="multiplier">Multiplier</label>
                            <input type="number" id="multiplier" name="multiplier" step="0.1" min="1" value="<?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 echo $combo['multiplier']; ?>" required>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='combos.php'">Cancel</button>
                            <button type="submit" name="update_combo" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Combo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // User search functionality
        let userSearchTimeout;
        const userSelectInput = document.getElementById('user_select');
        const userIdInput = document.getElementById('user_id');
        const userSearchResults = document.getElementById('user_search_results');
        
        userSelectInput.addEventListener('input', function() {
            clearTimeout(userSearchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                userSearchResults.style.display = 'none';
                return;
            }
            
            userSearchTimeout = setTimeout(() => {
                fetch('search_users.php?query=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error:', data.error);
                            userSearchResults.style.display = 'none';
                            return;
                        }
                        
                        displayUserSearchResults(data);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        userSearchResults.style.display = 'none';
                    });
            }, 300);
        });
        
        function displayUserSearchResults(users) {
            if (users.length === 0) {
                userSearchResults.innerHTML = '<div class="user-search-item">No users found</div>';
            } else {
                let html = '';
                users.forEach(user => {
                    html += `
                        <div class="user-search-item" onclick="selectUser(${user.id}, '${user.fullname}', '${user.email}')">
                            <div class="user-name">${user.fullname}</div>
                            <div class="user-email">${user.email}</div>
                        </div>
                    `;
                });
                userSearchResults.innerHTML = html;
            }
            userSearchResults.style.display = 'block';
        }
        
        function selectUser(userId, fullName, email) {
            userIdInput.value = userId;
            userSelectInput.value = `${fullName} - ${email}`;
            userSearchResults.style.display = 'none';
        }
        
        // Clear user selection when input is cleared
        userSelectInput.addEventListener('blur', function() {
            setTimeout(() => {
                if (this.value.trim() === '') {
                    userIdInput.value = '';
                }
            }, 200);
        });
        
        // Close user search results when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.user-select-container')) {
                userSearchResults.style.display = 'none';
            }
        });
        
        // Task loading functionality
        function loadTasksByLevel() {
            const level = document.getElementById('level').value;
            const startSelect = document.getElementById('start_task');
            const endSelect = document.getElementById('end_task');
            
            // Clear existing options
            startSelect.innerHTML = '<option value="">Loading...</option>';
            endSelect.innerHTML = '<option value="">Loading...</option>';
            
            if (!level) {
                startSelect.innerHTML = '<option value="">Select Level First</option>';
                endSelect.innerHTML = '<option value="">Select Level First</option>';
                return;
            }
            
            fetch('get_tasks_by_level.php?level=' + encodeURIComponent(level))
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error:', data.error);
                        startSelect.innerHTML = '<option value="">Error loading tasks</option>';
                        endSelect.innerHTML = '<option value="">Error loading tasks</option>';
                        return;
                    }
                    
                    // Populate both dropdowns with task numbering
                    let options = '<option value="">Select Task</option>';
                    data.forEach(task => {
                        options += `<option value="${task.task_number}">Task ${task.task_number} - ${task.title}</option>`;
                    });
                    
                    startSelect.innerHTML = options;
                    endSelect.innerHTML = options;
                    
                    // Preselect current values
                    startSelect.value = '<?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 echo $combo['start_task']; ?>';
                    endSelect.value = '<?php
require_once __DIR__ . '/../config.php';
if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        return $default;
    }
}
 echo $combo['end_task']; ?>';
                })
                .catch(error => {
                    console.error('Error:', error);
                    startSelect.innerHTML = '<option value="">Error loading tasks</option>';
                    endSelect.innerHTML = '<option value="">Error loading tasks</option>';
                });
        }
        
        // Load tasks on page load if level is selected
        document.addEventListener('DOMContentLoaded', function() {
            const levelSelect = document.getElementById('level');
            if (levelSelect.value) {
                loadTasksByLevel();
            }
        });
    </script>
</body>
</html>

