/**
 * Global Theme System
 * Handles dark/light mode across the entire project
 */

(function() {
    'use strict';

    // Theme configuration
    const THEMES = {
        light: {
            name: 'light',
            icon: 'fas fa-moon',
            bodyBg: '#ffffff',
            bodyColor: '#1a1a1a',
            topbarBg: '#ffffff',
            topbarBorder: '#e5e7eb',
            sidebarBg: '#ffffff',
            sidebarBorder: '#e5e7eb',
            cardBg: '#ffffff',
            cardBorder: '#e5e7eb',
            inputBg: '#ffffff',
            inputBorder: '#d1d5db',
            inputColor: '#1a1a1a',
            tableBg: '#ffffff',
            tableBorder: '#e5e7eb',
            tableHover: '#f9fafb',
            modalBg: '#ffffff',
            modalBorder: '#e5e7eb',
            dropdownBg: '#ffffff',
            dropdownBorder: '#e5e7eb',
            dropdownHover: '#f3f4f6',
            textPrimary: '#1a1a1a',
            textSecondary: '#6b7280',
            textMuted: '#9ca3af',
            border: '#e5e7eb',
            hover: '#f3f4f6',
            primary: '#4f46e5',
            success: '#22c55e',
            warning: '#f59e0b',
            danger: '#dc2626'
        },
        dark: {
            name: 'dark',
            icon: 'fas fa-sun',
            bodyBg: '#0f172a',
            bodyColor: '#f8fafc',
            topbarBg: '#1e293b',
            topbarBorder: '#334155',
            sidebarBg: '#1e293b',
            sidebarBorder: '#334155',
            cardBg: '#1e293b',
            cardBorder: '#334155',
            inputBg: '#0f172a',
            inputBorder: '#475569',
            inputColor: '#f8fafc',
            tableBg: '#1e293b',
            tableBorder: '#334155',
            tableHover: '#334155',
            modalBg: '#1e293b',
            modalBorder: '#334155',
            dropdownBg: '#1e293b',
            dropdownBorder: '#334155',
            dropdownHover: '#334155',
            textPrimary: '#f8fafc',
            textSecondary: '#cbd5e1',
            textMuted: '#94a3b8',
            border: '#334155',
            hover: '#334155',
            primary: '#4f46e5',
            success: '#22c55e',
            warning: '#f59e0b',
            danger: '#dc2626'
        }
    };

    // Initialize theme system
    function initTheme() {
        // Get saved theme or default to light
        const savedTheme = localStorage.getItem('theme') || 'light';
        applyTheme(savedTheme);
        
        // Set up theme toggle listeners
        setupThemeToggle();
        
        // Apply theme CSS variables
        updateCSSVariables(savedTheme);
    }

    // Apply theme to the page
    function applyTheme(theme) {
        const html = document.documentElement;
        const body = document.body;
        
        // Remove existing theme classes
        html.removeAttribute('data-theme');
        body.classList.remove('light-mode', 'dark-mode');
        
        // Apply new theme
        html.setAttribute('data-theme', theme);
        body.classList.add(theme + '-mode');
        
        // Update theme icon
        updateThemeIcon(theme);
        
        // Save to localStorage
        localStorage.setItem('theme', theme);
        
        // Dispatch theme change event
        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
    }

    // Update CSS variables for theme
    function updateCSSVariables(theme) {
        const themeConfig = THEMES[theme];
        const root = document.documentElement;
        
        // Set CSS variables
        Object.keys(themeConfig).forEach(key => {
            if (key !== 'name' && key !== 'icon') {
                root.style.setProperty(`--theme-${key}`, themeConfig[key]);
            }
        });
        
        // Set global CSS variables for backward compatibility
        root.style.setProperty('--body-bg', themeConfig.bodyBg);
        root.style.setProperty('--body-color', themeConfig.textPrimary);
        root.style.setProperty('--topbar-bg', themeConfig.topbarBg);
        root.style.setProperty('--topbar-border', themeConfig.topbarBorder);
        root.style.setProperty('--sidebar-bg', themeConfig.sidebarBg);
        root.style.setProperty('--sidebar-border', themeConfig.sidebarBorder);
        root.style.setProperty('--card-bg', themeConfig.cardBg);
        root.style.setProperty('--card-border', themeConfig.cardBorder);
        root.style.setProperty('--input-bg', themeConfig.inputBg);
        root.style.setProperty('--input-border', themeConfig.inputBorder);
        root.style.setProperty('--input-color', themeConfig.inputColor);
        root.style.setProperty('--table-bg', themeConfig.tableBg);
        root.style.setProperty('--table-border', themeConfig.tableBorder);
        root.style.setProperty('--table-hover', themeConfig.tableHover);
        root.style.setProperty('--modal-bg', themeConfig.modalBg);
        root.style.setProperty('--modal-border', themeConfig.modalBorder);
        root.style.setProperty('--dropdown-bg', themeConfig.dropdownBg);
        root.style.setProperty('--dropdown-border', themeConfig.dropdownBorder);
        root.style.setProperty('--dropdown-hover', themeConfig.dropdownHover);
        root.style.setProperty('--text-primary', themeConfig.textPrimary);
        root.style.setProperty('--text-secondary', themeConfig.textSecondary);
        root.style.setProperty('--text-muted', themeConfig.textMuted);
        root.style.setProperty('--border', themeConfig.border);
        root.style.setProperty('--hover', themeConfig.hover);
        root.style.setProperty('--primary', themeConfig.primary);
        root.style.setProperty('--success', themeConfig.success);
        root.style.setProperty('--warning', themeConfig.warning);
        root.style.setProperty('--danger', themeConfig.danger);
    }

    // Update theme icon
    function updateThemeIcon(theme) {
        const themeIcons = document.querySelectorAll('#themeIcon, .theme-icon');
        themeIcons.forEach(icon => {
            if (icon) {
                icon.className = THEMES[theme].icon;
            }
        });
    }

    // Setup theme toggle listeners
    function setupThemeToggle() {
        // Theme toggle button
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleTheme();
            });
        }
        
        // Any element with theme-toggle class
        const themeToggles = document.querySelectorAll('.theme-toggle');
        themeToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleTheme();
            });
        });
    }

    // Toggle between themes
    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        applyTheme(newTheme);
    }

    // Get current theme
    function getCurrentTheme() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    }

    // Public API
    window.ThemeSystem = {
        init: initTheme,
        apply: applyTheme,
        toggle: toggleTheme,
        current: getCurrentTheme,
        updateCSS: updateCSSVariables
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }

})();
