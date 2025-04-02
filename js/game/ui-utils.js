/**
 * UI Utilities
 * Helper functions for notifications, HTML manipulation, and UI components
 */

(function() {
    'use strict';
    
    /**
     * Safely escape HTML to prevent XSS attacks
     * @param {string} text - The text to escape
     * @return {string} Escaped HTML string
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Display a notification message
     * @param {string} message - The message to display
     * @param {string} type - The type/style of notification (info, success, error, warning)
     * @param {number} duration - Duration in ms (0 to prevent auto-dismiss)
     * @returns {Element} - The notification element
     */
    function showNotification(message, type = 'info', duration = 5000) {
        let container = document.getElementById('notifications-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notifications-container';
            container.className = 'notifications-container';
            document.body.appendChild(container);
        }
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">${message}</div>
            <button class="notification-close">×</button>
        `;
        
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            notification.classList.add('hiding');
            setTimeout(() => notification.remove(), 300);
        });
        
        container.appendChild(notification);
        
        // Trigger animation
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Auto-hide after duration (if not 0)
        if (duration > 0) {
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.classList.add('hiding');
                    setTimeout(() => {
                        if (notification.parentNode) notification.remove();
                    }, 300);
                }
            }, duration);
        }
        
        return notification;
    }
    
    /**
     * Hide a specific notification element
     * @param {Element} notification - The notification element to hide
     */
    function hideNotification(notification) {
        if (notification && notification.parentNode) {
            notification.classList.add('hiding');
            setTimeout(() => {
                if (notification.parentNode) notification.remove();
            }, 300);
        }
    }
    
    /**
     * Format a date object or timestamp to a human-readable string
     * @param {number|Date} dateObj - Date object or Unix timestamp
     * @return {string} Formatted date string
     */
    function formatDate(dateObj) {
        if (!dateObj) return 'Unknown date';
        
        const date = dateObj instanceof Date ? dateObj : new Date(dateObj * 1000);
        
        if (isNaN(date.getTime())) {
            return 'Invalid date';
        }
        
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
    
    // Export functions to global scope
    window.UIUtils = {
        escapeHtml,
        showNotification,
        hideNotification,
        formatDate
    };
})();