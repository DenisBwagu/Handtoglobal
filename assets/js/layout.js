/**
 * Global Layout System
 * Handles sidebar collapse/expand across the entire project
 */

(function() {
    'use strict';

    // Layout configuration
    const LAYOUT_CONFIG = {
        sidebarWidth: 260,
        sidebarCollapsedWidth: 0,
        transitionDuration: 300,
        localStorageKey: 'sidebarCollapsed',
        collapsedClass: 'sidebar-collapsed',
        expandedClass: 'sidebar-expanded'
    };

    // Initialize layout system
    function initLayout() {
        // Apply saved sidebar state
        applySidebarState();
        
        // Set up sidebar toggle listeners
        setupSidebarToggle();
        
        // Handle responsive behavior
        handleResponsive();
        
        // Apply layout adjustments
        adjustLayout();
    }

    // Apply sidebar state from localStorage
    function applySidebarState() {
        const isCollapsed = localStorage.getItem(LAYOUT_CONFIG.localStorageKey) === 'true';
        const sidebar = getSidebarElement();
        const mainContent = getMainContentElement();
        
        if (sidebar && mainContent) {
            if (isCollapsed) {
                collapseSidebar(false); // Don't save to localStorage, just apply
            } else {
                expandSidebar(false); // Don't save to localStorage, just apply
            }
        }
    }

    // Setup sidebar toggle listeners
    function setupSidebarToggle() {
        // Menu toggle button
        const menuToggle = document.getElementById('menuToggle');
        if (menuToggle) {
            menuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }
        
        // Any element with menu-toggle class
        const menuToggles = document.querySelectorAll('.menu-toggle, .menu-icon');
        menuToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        });
        
        // Keyboard shortcut (Ctrl/Cmd + B)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                toggleSidebar();
            }
        });
    }

    // Toggle sidebar state
    function toggleSidebar() {
        const sidebar = getSidebarElement();
        if (!sidebar) return;
        
        const isCollapsed = sidebar.classList.contains(LAYOUT_CONFIG.collapsedClass);
        
        if (isCollapsed) {
            expandSidebar(true);
        } else {
            collapseSidebar(true);
        }
    }

    // Collapse sidebar
    function collapseSidebar(saveState = true) {
        const sidebar = getSidebarElement();
        const mainContent = getMainContentElement();
        const appWrapper = getAppWrapper();
        
        if (!sidebar) return;
        
        // Add collapsed class
        sidebar.classList.add(LAYOUT_CONFIG.collapsedClass);
        sidebar.classList.remove(LAYOUT_CONFIG.expandedClass);
        
        if (mainContent) {
            mainContent.classList.add(LAYOUT_CONFIG.collapsedClass);
            mainContent.classList.remove(LAYOUT_CONFIG.expandedClass);
        }
        
        if (appWrapper) {
            appWrapper.classList.add(LAYOUT_CONFIG.collapsedClass);
            appWrapper.classList.remove(LAYOUT_CONFIG.expandedClass);
        }
        
        // Update menu icon
        updateMenuIcon(true);
        
        // Save state to localStorage
        if (saveState) {
            localStorage.setItem(LAYOUT_CONFIG.localStorageKey, 'true');
        }
        
        // Dispatch layout change event
        window.dispatchEvent(new CustomEvent('sidebarStateChanged', { 
            detail: { collapsed: true } 
        }));
    }

    // Expand sidebar
    function expandSidebar(saveState = true) {
        const sidebar = getSidebarElement();
        const mainContent = getMainContentElement();
        const appWrapper = getAppWrapper();
        
        if (!sidebar) return;
        
        // Remove collapsed class
        sidebar.classList.remove(LAYOUT_CONFIG.collapsedClass);
        sidebar.classList.add(LAYOUT_CONFIG.expandedClass);
        
        if (mainContent) {
            mainContent.classList.remove(LAYOUT_CONFIG.collapsedClass);
            mainContent.classList.add(LAYOUT_CONFIG.expandedClass);
        }
        
        if (appWrapper) {
            appWrapper.classList.remove(LAYOUT_CONFIG.collapsedClass);
            appWrapper.classList.add(LAYOUT_CONFIG.expandedClass);
        }
        
        // Update menu icon
        updateMenuIcon(false);
        
        // Save state to localStorage
        if (saveState) {
            localStorage.setItem(LAYOUT_CONFIG.localStorageKey, 'false');
        }
        
        // Dispatch layout change event
        window.dispatchEvent(new CustomEvent('sidebarStateChanged', { 
            detail: { collapsed: false } 
        }));
    }

    // Update menu icon based on sidebar state
    function updateMenuIcon(collapsed) {
        const menuIcons = document.querySelectorAll('#menuToggle i, .menu-icon i');
        menuIcons.forEach(icon => {
            if (collapsed) {
                icon.className = 'fas fa-bars';
            } else {
                icon.className = 'fas fa-bars';
            }
        });
    }

    // Handle responsive behavior
    function handleResponsive() {
        // Check screen size on resize
        window.addEventListener('resize', function() {
            adjustLayout();
        });
        
        // Initial responsive adjustment
        adjustLayout();
    }

    // Adjust layout based on screen size
    function adjustLayout() {
        const sidebar = getSidebarElement();
        if (!sidebar) return;
        
        const screenWidth = window.innerWidth;
        const isMobile = screenWidth < 768;
        
        if (isMobile) {
            // Mobile behavior: sidebar is overlay
            sidebar.style.position = 'fixed';
            sidebar.style.zIndex = '999';
            sidebar.style.left = sidebar.classList.contains(LAYOUT_CONFIG.collapsedClass) ? '-260px' : '0';
            
            const mainContent = getMainContentElement();
            if (mainContent) {
                mainContent.style.marginLeft = '0';
            }
        } else {
            // Desktop behavior: sidebar is fixed
            sidebar.style.position = 'relative';
            sidebar.style.zIndex = 'auto';
            sidebar.style.left = '0';
            
            const mainContent = getMainContentElement();
            if (mainContent) {
                if (sidebar.classList.contains(LAYOUT_CONFIG.collapsedClass)) {
                    mainContent.style.marginLeft = '0';
                } else {
                    mainContent.style.marginLeft = LAYOUT_CONFIG.sidebarWidth + 'px';
                }
            }
        }
    }

    // Get sidebar element
    function getSidebarElement() {
        return document.querySelector('.sidebar') || 
               document.getElementById('sidebar') ||
               document.querySelector('[class*="sidebar"]');
    }

    // Get main content element
    function getMainContentElement() {
        return document.querySelector('.main-content') || 
               document.getElementById('main-content') ||
               document.querySelector('[class*="main-content"]');
    }

    // Get app wrapper element
    function getAppWrapper() {
        return document.querySelector('.app-wrapper') || 
               document.getElementById('app-wrapper') ||
               document.querySelector('[class*="app-wrapper"]');
    }

    // Get current sidebar state
    function getSidebarState() {
        const sidebar = getSidebarElement();
        if (!sidebar) return 'unknown';
        
        return sidebar.classList.contains(LAYOUT_CONFIG.collapsedClass) ? 'collapsed' : 'expanded';
    }

    // Check if sidebar is collapsed
    function isSidebarCollapsed() {
        return getSidebarState() === 'collapsed';
    }

    // Public API
    window.LayoutSystem = {
        init: initLayout,
        toggle: toggleSidebar,
        collapse: collapseSidebar,
        expand: expandSidebar,
        getState: getSidebarState,
        isCollapsed: isSidebarCollapsed,
        adjust: adjustLayout
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLayout);
    } else {
        initLayout();
    }

})();
