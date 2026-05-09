// Global JavaScript functionality for HandToGlobal

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all functionality
    initSidebar();
    initDropdowns();
    initNotifications();
    initQuickAmountButtons();
    initFormValidation();
});

// Sidebar functionality
function initSidebar() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        display: none;
    `;
    document.body.appendChild(overlay);

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.style.display = 'none';
        });
    }

    // Close sidebar when clicking nav items on mobile
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
            }
        });
    });
}

// Dropdown functionality
function initDropdowns() {
    // Profile dropdown
    const profileBtn = document.querySelector('.profile-btn');
    const profileMenu = document.querySelector('.profile-menu');
    
    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileMenu.classList.toggle('show');
            
            // Close other dropdowns
            const notificationMenu = document.querySelector('.notification-menu');
            if (notificationMenu) {
                notificationMenu.classList.remove('show');
            }
        });
    }

    // Notification dropdown
    const notificationBtn = document.querySelector('.notification-btn');
    const notificationMenu = document.querySelector('.notification-menu');
    
    if (notificationBtn && notificationMenu) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationMenu.classList.toggle('show');
            
            // Close other dropdowns
            const profileMenu = document.querySelector('.profile-menu');
            if (profileMenu) {
                profileMenu.classList.remove('show');
            }
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        const dropdowns = document.querySelectorAll('.profile-menu, .notification-menu');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    });
}

// Notification functionality
function initNotifications() {
    // Auto-refresh notifications every 30 seconds
    setInterval(function() {
        const notificationBadge = document.querySelector('.notification-badge');
        if (notificationBadge) {
            // In a real implementation, this would fetch from the server
            // For now, we'll just keep the current count
        }
    }, 30000);
}

// Quick amount buttons for deposit
function initQuickAmountButtons() {
    const amountButtons = document.querySelectorAll('.amount-btn');
    const amountInput = document.getElementById('amount');
    
    amountButtons.forEach(button => {
        button.addEventListener('click', function() {
            const amount = this.dataset.amount;
            if (amountInput) {
                amountInput.value = amount;
                amountInput.focus();
            }
        });
    });
}

// Form validation
function initFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    
                    // Remove error class on input
                    field.addEventListener('input', function() {
                        this.classList.remove('error');
                    }, { once: true });
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Please fill in all required fields', 'error');
            }
        });
    });
}

// Show notification (toast)
function showNotification(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas fa-${getIconForType(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Style the toast
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${getColorForType(type)};
        color: white;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 300px;
        animation: slideIn 0.3s ease;
    `;
    
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .toast-content {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }
        
        .toast-close {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
        }
        
        .toast-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }
    `;
    
    if (!document.querySelector('#toast-styles')) {
        style.id = 'toast-styles';
        document.head.appendChild(style);
    }
    
    document.body.appendChild(toast);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 5000);
}

function getIconForType(type) {
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    return icons[type] || 'info-circle';
}

function getColorForType(type) {
    const colors = {
        success: '#16a34a',
        error: '#dc2626',
        warning: '#f59e0b',
        info: '#0284c7'
    };
    return colors[type] || '#0284c7';
}

// Utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2
    }).format(amount);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Search functionality
function initSearch() {
    const searchInput = document.querySelector('.search-box input');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function(e) {
            const query = e.target.value.toLowerCase();
            // Implement search functionality here
            console.log('Searching for:', query);
        }, 300));
    }
}

// Progress bar animations
function animateProgressBars() {
    const progressFills = document.querySelectorAll('.progress-fill');
    progressFills.forEach(fill => {
        const width = fill.style.width;
        fill.style.width = '0%';
        setTimeout(() => {
            fill.style.width = width;
        }, 100);
    });
}

// Initialize search when page loads
document.addEventListener('DOMContentLoaded', function() {
    initSearch();
    animateProgressBars();
});

// Export functions for use in other scripts
window.GlobalHand = {
    showNotification,
    formatCurrency,
    formatDate,
    debounce
};
