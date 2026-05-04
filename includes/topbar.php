<?php
/**
 * Shared Topbar Component
 * Used by both admin and user sides
 */

// Determine if we're in admin area
$isAdmin = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;

// Get user data based on context
if ($isAdmin) {
    $userName = $_SESSION['admin_name'] ?? 'Admin';
    $userEmail = $_SESSION['admin_email'] ?? 'admin@handtoglobal.com';
    $userBadge = 'ADMIN';
    $logoutUrl = 'admin_login.php';
} else {
    // User side - get from session or database
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId) {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT fullname, email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            $userName = $user['fullname'] ?? 'User';
            $userEmail = $user['email'] ?? 'user@handtoglobal.com';
        } catch (Exception $e) {
            $userName = $_SESSION['user_name'] ?? 'User';
            $userEmail = 'user@handtoglobal.com';
        }
    } else {
        $userName = $_SESSION['user_name'] ?? 'User';
        $userEmail = 'user@handtoglobal.com';
    }
    $userBadge = '';
    $logoutUrl = 'logout.php';
}

// Get first letter for avatar
$avatarLetter = strtoupper(substr($userName, 0, 1));
$assetPrefix = $isAdmin ? '../' : '';
?>

<link rel="stylesheet" href="<?php echo $assetPrefix; ?>assets/vendor/adminlte/css/adminlte.min.css">
<link rel="stylesheet" href="<?php echo $assetPrefix; ?>assets/css/global-theme.css">
<link rel="stylesheet" href="<?php echo $assetPrefix; ?>assets/css/adminlte-temporary.css">

<!-- Topbar Header -->
<div class="topbar" id="topbar">
    <div class="topbar-left">
        <div class="menu-icon" id="menuToggle">
            <i class="fas fa-bars"></i>
        </div>
        <div class="topbar-title">
            <?php 
            if ($isAdmin) {
                echo get_translation('admin_panel', 'Admin Panel');
            } else {
                echo get_translation('dashboard', 'Dashboard');
            }
            ?>
        </div>
    </div>
    <div class="topbar-right">
        <?php if ($userBadge): ?>
            <div class="admin-badge"><?php echo $userBadge; ?></div>
        <?php endif; ?>
        
        <div class="topbar-icon" id="themeToggle">
            <i class="fas fa-moon" id="themeIcon"></i>
        </div>
        
        <div class="profile-dropdown">
            <div class="profile-info" id="profileToggle">
                <div class="profile-avatar">
                    <?php echo $avatarLetter; ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($userName); ?></div>
                <div class="dropdown-arrow">
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
            
            <div class="dropdown-menu" id="profileDropdown">
                <div class="dropdown-header">
                    <div class="dropdown-avatar">
                        <?php echo $avatarLetter; ?>
                    </div>
                    <div class="dropdown-info">
                        <div class="dropdown-name"><?php echo htmlspecialchars($userName); ?></div>
                        <div class="dropdown-email"><?php echo htmlspecialchars($userEmail); ?></div>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i>
                    <?php echo get_translation('profile', 'Profile'); ?>
                </a>
                <a href="<?php echo $logoutUrl; ?>" class="dropdown-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <?php echo get_translation('logout', 'Logout'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Topbar Styles */
.topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 56px;
    background: var(--topbar-bg, #ffffff);
    border-bottom: 1px solid var(--border, #e5e7eb);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    z-index: 1000;
    transition: all 0.3s ease;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.menu-icon {
    cursor: pointer;
    color: var(--text, #1a1a1a);
    font-size: 18px;
    padding: 8px;
    border-radius: 6px;
    transition: background-color 0.2s ease;
}

.menu-icon:hover {
    background: var(--hover-bg, #f3f4f6);
}

.topbar-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text, #1a1a1a);
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.admin-badge {
    background: var(--primary, #4f46e5);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.topbar-icon {
    cursor: pointer;
    color: var(--muted, #6b7280);
    font-size: 16px;
    padding: 8px;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.topbar-icon:hover {
    background: var(--hover-bg, #f3f4f6);
    color: var(--text, #1a1a1a);
}

.profile-dropdown {
    position: relative;
}

.profile-info {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 6px 8px;
    border-radius: 8px;
    transition: background-color 0.2s ease;
}

.profile-info:hover {
    background: var(--hover-bg, #f3f4f6);
}

.profile-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary, #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.profile-name {
    font-weight: 500;
    color: var(--text, #1a1a1a);
    font-size: 14px;
}

.dropdown-arrow {
    color: var(--muted, #6b7280);
    font-size: 12px;
    transition: transform 0.2s ease;
}

.profile-dropdown.active .dropdown-arrow {
    transform: rotate(180deg);
}

.dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: var(--surface, #ffffff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    min-width: 240px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    z-index: 1001;
}

.profile-dropdown.active .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
}

.dropdown-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary, #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
}

.dropdown-info {
    flex: 1;
}

.dropdown-name {
    font-weight: 600;
    color: var(--text, #1a1a1a);
    font-size: 14px;
    margin-bottom: 2px;
}

.dropdown-email {
    color: var(--muted, #6b7280);
    font-size: 12px;
}

.dropdown-divider {
    height: 1px;
    background: var(--border, #e5e7eb);
    margin: 0;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: var(--text, #1a1a1a);
    text-decoration: none;
    font-size: 14px;
    transition: background-color 0.2s ease;
}

.dropdown-item:hover {
    background: var(--hover-bg, #f3f4f6);
}

.dropdown-item.logout {
    color: var(--danger, #dc2626);
}

.dropdown-item.logout:hover {
    background: rgba(220, 38, 38, 0.1);
}

.dropdown-item i {
    width: 16px;
    text-align: center;
}

/* Dark mode styles */
[data-theme="dark"] .topbar {
    background: var(--surface-dark, #1e293b);
    border-bottom-color: var(--border-dark, #334155);
}

[data-theme="dark"] .menu-icon:hover,
[data-theme="dark"] .topbar-icon:hover,
[data-theme="dark"] .profile-info:hover {
    background: var(--hover-bg-dark, #334155);
}

[data-theme="dark"] .dropdown-menu {
    background: var(--surface-dark, #1e293b);
    border-color: var(--border-dark, #334155);
}

[data-theme="dark"] .dropdown-item:hover {
    background: var(--hover-bg-dark, #334155);
}
</style>

<script>
// Topbar functionality
document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.add('adminlte-preview');

    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const html = document.documentElement;
    
    // Load saved theme
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);
    
    themeToggle.addEventListener('click', function() {
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeIcon(newTheme);
    });
    
    function updateThemeIcon(theme) {
        themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
    
    // Profile dropdown
    const profileToggle = document.getElementById('profileToggle');
    const profileDropdown = document.getElementById('profileDropdown');
    const profileDropdownParent = profileToggle.closest('.profile-dropdown');
    
    profileToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        profileDropdownParent.classList.toggle('active');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!profileDropdownParent.contains(e.target)) {
            profileDropdownParent.classList.remove('active');
        }
    });
    
    // Sidebar toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (sidebar && mainContent) {
        // Load saved sidebar state
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }
        
        menuToggle.addEventListener('click', function() {
            const isCollapsed = sidebar.classList.contains('collapsed');
            
            if (isCollapsed) {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
                localStorage.setItem('sidebarCollapsed', 'false');
            } else {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                localStorage.setItem('sidebarCollapsed', 'true');
            }
        });
    }
});

// CSS Variables for theme
document.addEventListener('DOMContentLoaded', function() {
    const style = document.createElement('style');
    style.textContent = `
        :root {
            --topbar-bg: #ffffff;
            --surface: #ffffff;
            --text: #1a1a1a;
            --muted: #6b7280;
            --border: #e5e7eb;
            --hover-bg: #f3f4f6;
            --primary: #4f46e5;
            --danger: #dc2626;
        }
        
        [data-theme="dark"] {
            --topbar-bg: #1e293b;
            --surface: #1e293b;
            --surface-dark: #1e293b;
            --text: #f8fafc;
            --muted: #94a3b8;
            --border: #334155;
            --border-dark: #334155;
            --hover-bg: #f8fafc;
            --hover-bg-dark: #334155;
            --primary: #4f46e5;
            --danger: #dc2626;
        }
        
        .sidebar {
            transition: margin-left 0.3s ease, width 0.3s ease;
        }
        
        .sidebar.collapsed {
            margin-left: -260px;
        }
        
        .main-content {
            transition: margin-left 0.3s ease;
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -260px;
                transition: left 0.3s ease;
            }
            
            .sidebar.collapsed {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    `;
    document.head.appendChild(style);
});
</script>
