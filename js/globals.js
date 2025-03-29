/**
 * Global variable management system for GameRating
 * Prevents duplicate declarations and conflicts
 */
(function() {
    // Create namespace if it doesn't exist
    window.gameRating = window.gameRating || {};
    
    // Common configuration
    window.gameRating.config = {
        baseUrl: '/gamerating',
        debug: false
    };
    
    // Konami code definitions
    window.gameRating.konami = {
        signupSequence: [
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
        loginSequence: [
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
        userInput: [],
        pressTimes: []
    };
    
    // Create code arrays for comparison
    window.gameRating.konami.signupCode = window.gameRating.konami.signupSequence.map(item => item.keyCode);
    window.gameRating.konami.loginCode = window.gameRating.konami.loginSequence.map(item => item.keyCode);
    
    // For backwards compatibility
    window.baseUrl = '/gamerating';
    window.signupSequence = [
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
    ];
    window.loginSequence = [
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
    ];
    window.signupCode = [38, 38, 40, 40, 37, 39, 37, 39, 65, 66]; // ↑↑↓↓←→←→AB
    window.loginCode = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65]; // ↑↑↓↓←→←→BA
    window.userInput = window.gameRating.konami.userInput;
    window.pressTimes = window.gameRating.konami.pressTimes;
    
    // Common utility functions
    window.gameRating.utils = {
        showNotification: function(message, type = 'info') {
            const notification = document.getElementById('custom-notification');
            const messageElement = document.getElementById('notification-message');
            
            if (notification && messageElement) {
                messageElement.textContent = message;
                notification.style.display = 'block';
                
                // Hide notification after 3 seconds
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 3000);
            } else {
                // Fallback to alert
                alert(message);
            }
        },
        
        isBotDetected: function(pressTimes, sequenceLength) {
            if (pressTimes.length < sequenceLength) return false;
            
            const timeDiffs = [];
            for (let i = 1; i < pressTimes.length; i++) {
                timeDiffs.push(pressTimes[i] - pressTimes[i - 1]);
            }
            
            const avgTimeDiff = timeDiffs.reduce((sum, diff) => sum + diff, 0) / timeDiffs.length;
            const variance = timeDiffs.reduce((sum, diff) => sum + Math.pow(diff - avgTimeDiff, 2), 0) / timeDiffs.length;
            const stdDev = Math.sqrt(variance);
            
            return avgTimeDiff < 30 || stdDev < 10;
        },
        
        getCsrfToken: function() {
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            return metaTag ? metaTag.getAttribute('content') : '';
        },
        
        // Fix the logout function:
        logout: function() {
            fetch(`${window.baseUrl}/api.php?action=logout`)
                .then(response => {
                    window.location.href = `${window.baseUrl}/index.php`;
                })
                .catch(error => {
                    console.error('Logout error:', error);
                    window.location.href = `${window.baseUrl}/index.php`;
                });
            
            return false; // Prevent default action
        }
    };
    
    // Expose for backward compatibility
    window.showNotification = window.gameRating.utils.showNotification;
    window.isBotDetected = window.gameRating.utils.isBotDetected;
    window.getCsrfToken = window.gameRating.utils.getCsrfToken;
    window.logout = window.gameRating.utils.logout;
    
    // Define the missing variable
    window.currentPageHeader = document.querySelector('.page-header') || document.createElement('div');

    console.log("Global variables initialized");
})();