/**
 * Unified Utilities Module
 * Common utility functions used across the application
 */
(function() {
    // Create namespace and config
    window.gameRating = window.gameRating || {};
    window.gameRating.config = window.gameRating.config || {
        baseUrl: '/gamerating'
    };
    
    // Core utility functions
    window.gameRating.utils = {
        // Show notification
        showNotification: function(message, type = 'info') {
            const container = document.getElementById('custom-notification');
            const messageEl = document.getElementById('notification-message');
            
            if (!container || !messageEl) {
                console.error('Notification elements not found');
                alert(message);
                return;
            }
            
            messageEl.textContent = message;
            container.className = `custom-notification ${type}`;
            container.style.display = 'block';
            
            setTimeout(() => {
                container.style.display = 'none';
            }, 3000);
        },
        
        // Bot detection
        isBotDetected: function(pressTimes, sequenceLength) {
            if (!pressTimes || pressTimes.length < sequenceLength) return false;
            
            const timeDiffs = [];
            for (let i = 1; i < pressTimes.length; i++) {
                timeDiffs.push(pressTimes[i] - pressTimes[i - 1]);
            }
            
            const avgTimeDiff = timeDiffs.reduce((sum, diff) => sum + diff, 0) / timeDiffs.length;
            const variance = timeDiffs.reduce((sum, diff) => sum + Math.pow(diff - avgTimeDiff, 2), 0) / timeDiffs.length;
            const stdDev = Math.sqrt(variance);
            
            return avgTimeDiff < 30 || stdDev < 10;
        },
        
        // Get CSRF token
        getCsrfToken: function() {
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            return metaTag ? metaTag.getAttribute('content') : '';
        },
        
        // Format date
        formatDate: function(timestamp, format = 'short') {
            if (!timestamp) return 'N/A';
            
            const date = new Date(timestamp * 1000);
            
            switch (format) {
                case 'short':
                    return date.toLocaleDateString();
                case 'long':
                    return date.toLocaleString();
                case 'relative':
                    const now = new Date();
                    const diff = Math.floor((now - date) / 1000);
                    
                    if (diff < 60) return 'Just now';
                    if (diff < 3600) return `${Math.floor(diff / 60)} minutes ago`;
                    if (diff < 86400) return `${Math.floor(diff / 3600)} hours ago`;
                    if (diff < 604800) return `${Math.floor(diff / 86400)} days ago`;
                    
                    return date.toLocaleDateString();
                default:
                    return date.toLocaleDateString();
            }
        },
        
        // Cookie functions
        getCookie: function(name) {
            const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return match ? decodeURIComponent(match[2]) : null;
        },
        
        setCookie: function(name, value, days = 30) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            const expires = "expires=" + date.toUTCString();
            document.cookie = name + "=" + value + ";" + expires + ";path=/";
        },
        
        // Parse JWT token
        parseJwt: function(token) {
            try {
                const base64Url = token.split('.')[1];
                const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
                const jsonPayload = decodeURIComponent(atob(base64).split('').map(c => {
                    return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                }).join(''));
                
                return JSON.parse(jsonPayload);
            } catch (e) {
                console.error('Error parsing JWT token:', e);
                return null;
            }
        },
        
        // Clear all auth cookies and storage
        clearAuthCookies: function() {
            document.cookie = 'access_token=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            document.cookie = 'refresh_token=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            document.cookie = 'anonymous_token=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            document.cookie = 'PHPSESSID=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            
            try {
                localStorage.removeItem('user_data');
                localStorage.removeItem('access_token');
                sessionStorage.removeItem('user_data');
                sessionStorage.removeItem('access_token');
            } catch (e) {
                console.warn("Error clearing storage:", e);
            }
        }
    };
    
    // Backward compatibility - limit to what's actually used
    window.showNotification = window.gameRating.utils.showNotification;
    window.getCsrfToken = window.gameRating.utils.getCsrfToken;
    window.formatDate = window.gameRating.utils.formatDate;
    window.getCookie = window.gameRating.utils.getCookie;
    window.setCookie = window.gameRating.utils.setCookie;
    window.parseJwt = window.gameRating.utils.parseJwt;
    
    // Define Konami code sequences
    window.gameRating.konamiCodes = {
        signup: {
            sequence: [
                { name: 'up', keyCode: 38, symbol: '↑' },
                { name: 'up', keyCode: 38, symbol: '↑' },
                { name: 'down', keyCode: 40, symbol: '↓' },
                { name: 'down', keyCode: 40, symbol: '↓' },
                { name: 'left', keyCode: 37, symbol: '←' },
                { name: 'right', keyCode: 39, symbol: '→' },
                { name: 'left', keyCode: 37, symbol: '←' },
                { name: 'right', keyCode: 39, symbol: '→' },
                { name: 'a', keyCode: 65, symbol: 'A' },
                { name: 'b', keyCode: 66, symbol: 'B' }
            ],
            keyCodes: [38, 38, 40, 40, 37, 39, 37, 39, 65, 66]
        },
        login: {
            sequence: [
                { name: 'up', keyCode: 38, symbol: '↑' },
                { name: 'up', keyCode: 38, symbol: '↑' },
                { name: 'down', keyCode: 40, symbol: '↓' },
                { name: 'down', keyCode: 40, symbol: '↓' },
                { name: 'left', keyCode: 37, symbol: '←' },
                { name: 'right', keyCode: 39, symbol: '→' },
                { name: 'left', keyCode: 37, symbol: '←' },
                { name: 'right', keyCode: 39, symbol: '→' },
                { name: 'b', keyCode: 66, symbol: 'B' },
                { name: 'a', keyCode: 65, symbol: 'A' }
            ],
            keyCodes: [38, 38, 40, 40, 37, 39, 37, 39, 66, 65]
        }
    };
    
    // Backward compatibility for konami.js references
    window.signupSequence = window.gameRating.konamiCodes.signup.sequence;
    window.loginSequence = window.gameRating.konamiCodes.login.sequence;
    window.signupCode = window.gameRating.konamiCodes.signup.keyCodes;
    window.loginCode = window.gameRating.konamiCodes.login.keyCodes;
})();