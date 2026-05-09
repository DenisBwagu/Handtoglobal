<?php
/**
 * Global Layout Wrapper
 * Used to wrap all pages with consistent layout structure
 */

// Determine if we're in admin area
$isAdmin = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
?>

<!-- Sidebar -->
<?php 
if ($isAdmin) {
   
} else {
    require_once 'includes/sidebar.php';
}
?>

<!-- Topbar -->
<?php 
if ($isAdmin) {
    require_once 'admin/includes/topbar.php';
} else {
    require_once 'includes/topbar.php';
}
?>

<!-- Global Scripts -->
<script src="../assets/js/theme.js"></script>
<script src="../assets/js/layout.js"></script>

<!-- Global CSS -->
<link rel="stylesheet" href="../assets/css/global-theme.css">

<style>
/* Global Layout Structure */
.app-wrapper {
    display: flex;
    min-height: 100vh;
    background: var(--body-bg);
    color: var(--text-primary);
    transition: all 0.3s ease;
}

.main-content {
    flex: 1;
    margin-left: 260px;
    min-height: 100vh;
    background: var(--body-bg);
    transition: all 0.3s ease;
}

.content-area {
    padding: 20px;
    margin-top: 56px;
    min-height: calc(100vh - 56px);
    background: var(--body-bg);
}

/* Layout States */
.app-wrapper.sidebar-collapsed .main-content {
    margin-left: 0;
}

.app-wrapper.sidebar-expanded .main-content {
    margin-left: 260px;
}

/* Responsive Layout */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
    }
    
    .app-wrapper.sidebar-collapsed .main-content {
        margin-left: 0;
    }
    
    .app-wrapper.sidebar-expanded .main-content {
        margin-left: 0;
    }
    
    .content-area {
        padding: 16px;
    }
}

/* Admin Layout Adjustments */
.admin-layout {
    display: flex;
    min-height: 100vh;
    background: var(--body-bg);
}

.admin-layout .sidebar {
    position: fixed;
    top: 56px;
    left: 0;
    width: 260px;
    height: calc(100vh - 56px);
    background: var(--sidebar-bg);
    border-right: 1px solid var(--sidebar-border);
    overflow-y: auto;
    z-index: 999;
    transition: all 0.3s ease;
}

.admin-layout .main-content {
    flex: 1;
    margin-left: 260px;
    min-height: 100vh;
    background: var(--body-bg);
    transition: all 0.3s ease;
}

.admin-layout.sidebar-collapsed .sidebar {
    margin-left: -260px;
}

.admin-layout.sidebar-collapsed .main-content {
    margin-left: 0;
}

.admin-layout.sidebar-expanded .sidebar {
    margin-left: 0;
}

.admin-layout.sidebar-expanded .main-content {
    margin-left: 260px;
}

/* User Layout Adjustments */
.dashboard-layout,
.user-layout {
    display: flex;
    min-height: 100vh;
    background: var(--body-bg);
}

.dashboard-layout .sidebar,
.user-layout .sidebar {
    position: fixed;
    top: 56px;
    left: 0;
    width: 260px;
    height: calc(100vh - 56px);
    background: var(--sidebar-bg);
    border-right: 1px solid var(--sidebar-border);
    overflow-y: auto;
    z-index: 999;
    transition: all 0.3s ease;
}

.dashboard-layout .main-content,
.user-layout .main-content {
    flex: 1;
    margin-left: 260px;
    min-height: 100vh;
    background: var(--body-bg);
    transition: all 0.3s ease;
}

.dashboard-layout.sidebar-collapsed .sidebar,
.user-layout.sidebar-collapsed .sidebar {
    transform: translateX(-100%);
}

.dashboard-layout.sidebar-collapsed .main-content,
.user-layout.sidebar-collapsed .main-content {
    margin-left: 0;
}

.dashboard-layout.sidebar-expanded .sidebar,
.user-layout.sidebar-expanded .sidebar {
    transform: translateX(0);
}

.dashboard-layout.sidebar-expanded .main-content,
.user-layout.sidebar-expanded .main-content {
    margin-left: 260px;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .admin-layout .main-content,
    .dashboard-layout .main-content,
    .user-layout .main-content {
        margin-left: 0;
    }
    
    .admin-layout .sidebar,
    .dashboard-layout .sidebar,
    .user-layout .sidebar {
        transform: translateX(-100%);
    }
    
    .admin-layout.sidebar-expanded .sidebar,
    .dashboard-layout.sidebar-expanded .sidebar,
    .user-layout.sidebar-expanded .sidebar {
        transform: translateX(0);
    }
    
    .admin-layout.sidebar-collapsed .sidebar,
    .dashboard-layout.sidebar-collapsed .sidebar,
    .user-layout.sidebar-collapsed .sidebar {
        transform: translateX(-100%);
    }
}

/* Content Area Styling */
.content-area > *:first-child {
    margin-top: 0;
}

.content-area > *:last-child {
    margin-bottom: 0;
}

/* Ensure proper spacing for page content */
.page-header,
.dashboard-header {
    margin-bottom: 30px;
}

.card {
    margin-bottom: 20px;
}

/* Smooth transitions for layout changes */
.sidebar,
.main-content,
.app-wrapper {
    transition: all 0.3s ease;
}
</style>

<script>
// Initialize global layout
document.addEventListener('DOMContentLoaded', function() {
    // Apply saved sidebar state
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    const appWrapper = document.getElementById('appWrapper');
    const mainContent = document.getElementById('mainContent');
    
    if (isCollapsed) {
        appWrapper.classList.add('sidebar-collapsed');
        appWrapper.classList.remove('sidebar-expanded');
        if (mainContent) {
            mainContent.classList.add('sidebar-collapsed');
            mainContent.classList.remove('sidebar-expanded');
        }
    } else {
        appWrapper.classList.add('sidebar-expanded');
        appWrapper.classList.remove('sidebar-collapsed');
        if (mainContent) {
            mainContent.classList.add('sidebar-expanded');
            mainContent.classList.remove('sidebar-collapsed');
        }
    }
    
    // Listen for sidebar state changes
    window.addEventListener('sidebarStateChanged', function(e) {
        const collapsed = e.detail.collapsed;
        if (collapsed) {
            appWrapper.classList.add('sidebar-collapsed');
            appWrapper.classList.remove('sidebar-expanded');
            if (mainContent) {
                mainContent.classList.add('sidebar-collapsed');
                mainContent.classList.remove('sidebar-expanded');
            }
        } else {
            appWrapper.classList.add('sidebar-expanded');
            appWrapper.classList.remove('sidebar-collapsed');
            if (mainContent) {
                mainContent.classList.add('sidebar-expanded');
                mainContent.classList.remove('sidebar-collapsed');
            }
        }
    });
});
</script>
