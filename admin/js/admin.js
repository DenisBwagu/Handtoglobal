// Admin JavaScript functionality

document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    initActivityFilters();
    initQuickActions();
    initTooltips();
});

// Sidebar functionality
function initSidebar() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.admin-sidebar');
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

// Activity filters
function initActivityFilters() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const activityItems = document.querySelectorAll('.activity-item');

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filter = this.dataset.filter;
            
            // Update active button
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Filter activity items
            activityItems.forEach(item => {
                if (filter === 'all' || item.dataset.type === filter) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
}

// Quick actions
function initQuickActions() {
    const actionCards = document.querySelectorAll('.action-card');
    
    actionCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Add ripple effect
            const ripple = document.createElement('div');
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;
            
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
}

// Tooltips
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.dataset.tooltip;
            tooltip.style.cssText = `
                position: absolute;
                background: #1e293b;
                color: white;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 600;
                z-index: 10000;
                pointer-events: none;
                white-space: nowrap;
                transform: translate(-50%, -100%);
                margin-top: -8px;
                left: 50%;
            `;
            
            this.style.position = 'relative';
            this.appendChild(tooltip);
        });
        
        element.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.tooltip');
            if (tooltip) tooltip.remove();
        });
    });
}

// Confirmation dialogs
function confirmAction(message, callback) {
    const modal = document.createElement('div');
    modal.className = 'confirm-modal';
    modal.innerHTML = `
        <div class="confirm-overlay"></div>
        <div class="confirm-dialog">
            <div class="confirm-content">
                <h3>Confirm Action</h3>
                <p>${message}</p>
                <div class="confirm-actions">
                    <button class="btn btn-outline" id="confirmCancel">Cancel</button>
                    <button class="btn btn-danger" id="confirmOk">Confirm</button>
                </div>
            </div>
        </div>
    `;
    
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    const overlay = modal.querySelector('.confirm-overlay');
    overlay.style.cssText = `
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
    `;
    
    const dialog = modal.querySelector('.confirm-dialog');
    dialog.style.cssText = `
        background: white;
        border-radius: 12px;
        padding: 24px;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        z-index: 1;
    `;
    
    const content = modal.querySelector('.confirm-content');
    content.style.cssText = `
        text-align: center;
    `;
    
    const actions = modal.querySelector('.confirm-actions');
    actions.style.cssText = `
        display: flex;
        gap: 12px;
        justify-content: center;
        margin-top: 20px;
    `;
    
    document.body.appendChild(modal);
    
    document.getElementById('confirmCancel').addEventListener('click', () => {
        modal.remove();
    });
    
    document.getElementById('confirmOk').addEventListener('click', () => {
        modal.remove();
        callback();
    });
    
    overlay.addEventListener('click', () => {
        modal.remove();
    });
}

// Status updates
function updateStatus(element, status, message) {
    const statusElement = document.createElement('span');
    statusElement.className = `badge badge-${status}`;
    statusElement.textContent = message;
    
    if (element.tagName === 'TD') {
        element.innerHTML = '';
        element.appendChild(statusElement);
    } else {
        element.className = `badge badge-${status}`;
        element.textContent = message;
    }
}

// AJAX helpers
function postRequest(url, data, callback) {
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(data)
    })
    .then(response => response.json())
    .then(data => callback(data))
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${getIconForType(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    notification.style.cssText = `
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
    
    const content = notification.querySelector('.notification-content');
    content.style.cssText = `
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
    `;
    
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = `
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        padding: 4px;
        border-radius: 4px;
    `;
    
    closeBtn.addEventListener('click', () => notification.remove());
    
    document.body.appendChild(notification);
    
    setTimeout(() => notification.remove(), 5000);
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

// Auto-refresh dashboard stats
function initAutoRefresh() {
    setInterval(() => {
        // Refresh stats every 30 seconds
        const statsElements = document.querySelectorAll('.stat-content h3');
        statsElements.forEach(element => {
            // Add loading state
            element.style.opacity = '0.5';
        });
        
        // Simulate refresh (in real app, this would be an API call)
        setTimeout(() => {
            statsElements.forEach(element => {
                element.style.opacity = '1';
            });
        }, 1000);
    }, 30000);
}

// Export functions
window.AdminJS = {
    confirmAction,
    updateStatus,
    postRequest,
    showNotification
};

// Add CSS for animations
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
    
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    .notification-close:hover {
        background: rgba(255, 255, 255, 0.2);
    }
`;
document.head.appendChild(style);
