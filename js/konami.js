/**
 * GameRating Konami Code and Utility Functions
 * This script handles konami code sequences and related utilities
 */

// Define baseUrl for API calls - make it available globally
(function() {
    // Get the base URL dynamically
    const getBaseUrl = () => {
        // Get the current path
        const path = window.location.pathname;
        // Find the occurrence of 'gamerating' in the path
        const gameRatingIndex = path.indexOf('gamerating');
        
        if (gameRatingIndex !== -1) {
            // Extract everything up to and including 'gamerating'
            return window.location.origin + path.substring(0, gameRatingIndex + 'gamerating'.length);
        }
        
        // Fallback: Return origin + current directory without the filename
        return window.location.origin + path.substring(0, path.lastIndexOf('/'));
    };
    
    // Set baseUrl globally
    window.baseUrl = window.baseUrl || '/gamerating';
})();

// Define the Konami code sequences with symbols - use window object to avoid redeclaration
window.konamiCodes = window.konamiCodes || {};

// Only define these sequences if they don't already exist
if (!window.konamiCodes.signupSequence) {
    window.konamiCodes.signupSequence = [
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
}

if (!window.konamiCodes.loginSequence) {
    window.konamiCodes.loginSequence = [
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
}

// Create aliases for backward compatibility (non-const)
signupSequence = window.konamiCodes.signupSequence;
loginSequence = window.konamiCodes.loginSequence;

// Extract the sequence key codes for comparison
window.konamiCodes.signupCode = window.konamiCodes.signupSequence.map(item => item.keyCode);
window.konamiCodes.loginCode = window.konamiCodes.loginSequence.map(item => item.keyCode);

// Also create aliases for these
signupCode = window.konamiCodes.signupCode;
loginCode = window.konamiCodes.loginCode;

// Define the Konami code sequences with symbols (only for signup and login)
function getCsrfToken() {
    const name = 'csrf_token=';
    const decodedCookie = decodeURIComponent(document.cookie);
    const ca = decodedCookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) === 0) {
            return c.substring(name.length, c.length);
        }
    }
    return '';
}

// Helper function to format dates as dd/mm/yyyy
function formatDate(timestamp) {
    if (!timestamp) return 'N/A';
    const date = new Date(timestamp * 1000);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are 0-based
    const year = date.getFullYear(); // Get full year
    return `${day}/${month}/${year}`;
}

/**
 * Konami code functions and utilities
 */

// Display a Konami code sequence as symbols
function displayKonamiSequence(sequence, elementId) {
    const sequenceDiv = document.getElementById(elementId);
    if (!sequenceDiv) {
        console.error(`Element with ID ${elementId} not found`);
        return;
    }
    
    sequenceDiv.innerHTML = '';
    sequenceDiv.className = 'symbols';
    
    sequence.forEach((item, index) => {
        const symbolSpan = document.createElement('span');
        symbolSpan.className = 'symbol';
        symbolSpan.id = `${elementId}-symbol-${index}`;
        symbolSpan.textContent = item.symbol;
        sequenceDiv.appendChild(symbolSpan);
    });
}

// Highlight a symbol in a Konami sequence
function highlightKonamiSymbol(index, elementId) {
    // First, reset all highlights
    resetKonamiHighlights(elementId);
    
    // Then, highlight the current symbol
    if (index >= 0) {
        const symbolId = `${elementId}-symbol-${index}`;
        const symbol = document.getElementById(symbolId);
        if (symbol) {
            symbol.classList.add('active');
        }
    }
}

// Reset all highlighted symbols in a sequence
function resetKonamiHighlights(elementId) {
    const symbols = document.querySelectorAll(`#${elementId} .symbol`);
    symbols.forEach(symbol => symbol.classList.remove('active'));
}

// Detect if input is from a bot (too fast or too consistent)
function isBotDetected(pressTimes, sequenceLength) {
    if (!pressTimes || pressTimes.length < sequenceLength) return false;
    
    const timeDiffs = [];
    for (let i = 1; i < pressTimes.length; i++) {
        timeDiffs.push(pressTimes[i] - pressTimes[i - 1]);
    }
    
    const avgTimeDiff = timeDiffs.reduce((sum, diff) => sum + diff, 0) / timeDiffs.length;
    const variance = timeDiffs.reduce((sum, diff) => sum + Math.pow(diff - avgTimeDiff, 2), 0) / timeDiffs.length;
    const stdDev = Math.sqrt(variance);
    
    return avgTimeDiff < 30 || stdDev < 10; // Too fast or too consistent
}

// Custom notification function
function showNotification(message) {
    const notification = document.getElementById('custom-notification');
    const messageElement = document.getElementById('notification-message');
    if (!notification || !messageElement) {
        console.error('Notification elements not found');
        return;
    }
    messageElement.textContent = message;
    notification.style.display = 'block';
    setTimeout(() => {
        notification.style.display = 'none';
    }, 3000); // Hide after 3 seconds
}

// Helper function to clear authentication cookies
function clearAuthCookies() {
    // Clear all possible auth cookies by setting them to expire in the past
    document.cookie = 'access_token=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
    document.cookie = 'refresh_token=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
    document.cookie = 'PHPSESSID=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
    
    // Clear any local/session storage items related to auth
    try {
        localStorage.removeItem('user_data');
        localStorage.removeItem('access_token');
        sessionStorage.removeItem('user_data');
        sessionStorage.removeItem('access_token');
    } catch (e) {
        console.warn("Error clearing storage:", e);
    }
    
    console.log("Auth cookies and storage cleared");
}

// Export the function to global scope explicitly
window.clearAuthCookies = clearAuthCookies;