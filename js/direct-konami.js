/**
 * Direct Konami Code Handler
 * Self-contained implementation for login/register pages
 */
(function() {
    // Run immediately when the script loads
    console.log('Direct Konami handler initializing');
    
    // Define Konami codes
    const KONAMI_LOGIN = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65]; // ↑↑↓↓←→←→BA
    const KONAMI_SIGNUP = [38, 38, 40, 40, 37, 39, 37, 39, 65, 66]; // ↑↑↓↓←→←→AB
    
    // State variables
    let userInput = [];
    let lastKeyTime = 0;
    
    // Element references
    let loginSequenceElement = null;
    let signupSequenceElement = null;
    
    // Get current page
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    const isLoginPage = currentPage === 'login.php';
    const isRegisterPage = currentPage === 'register.php';
    
    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Direct Konami: DOM loaded');
        
        // Find sequence elements
        if (isLoginPage) {
            signupSequenceElement = document.getElementById('konami-sequence-signup-login');
        } else if (isRegisterPage) {
            loginSequenceElement = document.getElementById('konami-sequence-login-reg');
        } else {
            signupSequenceElement = document.getElementById('konami-sequence-signup');
            loginSequenceElement = document.getElementById('konami-sequence-login');
        }
        
        // Display sequences if elements exist
        if (signupSequenceElement) {
            displaySequence(signupSequenceElement, KONAMI_SIGNUP);
        }
        
        if (loginSequenceElement) {
            displaySequence(loginSequenceElement, KONAMI_LOGIN);
        }
        
        // Add keyboard listener
        document.addEventListener('keydown', handleKeyPress);
        
        console.log('Direct Konami: Initialization complete');
    });
    
    // Handle key presses
    function handleKeyPress(e) {
        // Store this keypress time to check for bots
        const now = Date.now();
        
        // Add to input history
        userInput.push(e.keyCode);
        
        // Keep only the last 10 keypresses
        if (userInput.length > 10) {
            userInput.shift();
        }
        
        // Get current key index
        const currentIndex = userInput.length - 1;
        
        // Check if we should highlight in the login sequence
        if (loginSequenceElement && currentIndex >= 0 && currentIndex < KONAMI_LOGIN.length) {
            // Clear all highlights first
            clearHighlights(loginSequenceElement);
            
            // If correct key so far, highlight it
            if (compareArrayPart(userInput, KONAMI_LOGIN, currentIndex + 1)) {
                highlightSymbol(loginSequenceElement, currentIndex);
            } else {
                // Wrong key for login sequence, reset after delay
                setTimeout(() => {
                    clearHighlights(loginSequenceElement);
                }, 1000);
            }
        }
        
        // Check if we should highlight in the signup sequence
        if (signupSequenceElement && currentIndex >= 0 && currentIndex < KONAMI_SIGNUP.length) {
            // Clear all highlights first
            clearHighlights(signupSequenceElement);
            
            // If correct key so far, highlight it
            if (compareArrayPart(userInput, KONAMI_SIGNUP, currentIndex + 1)) {
                highlightSymbol(signupSequenceElement, currentIndex);
            } else {
                // Wrong key for signup sequence, reset after delay
                setTimeout(() => {
                    clearHighlights(signupSequenceElement);
                }, 1000);
            }
        }
        
        // Check for complete Konami codes
        const inputStr = userInput.join(',');
        const loginStr = KONAMI_LOGIN.join(',');
        const signupStr = KONAMI_SIGNUP.join(',');
        
        // Check if we have a complete login code
        if (inputStr === loginStr) {
            // We have a complete login code!
            if (!isLoginPage) {
                // Calculate average key press time to detect bots
                const avgTime = (now - lastKeyTime) / 10;
                if (avgTime < 100) {
                    showNotification('Bot detected! Slow down!');
                } else {
                    window.location.href = 'login.php';
                }
            }
            userInput = [];
        }
        // Check if we have a complete signup code
        else if (inputStr === signupStr) {
            // We have a complete signup code!
            if (!isRegisterPage) {
                // Calculate average key press time to detect bots
                const avgTime = (now - lastKeyTime) / 10;
                if (avgTime < 100) {
                    showNotification('Bot detected! Slow down!');
                } else {
                    window.location.href = 'register.php';
                }
            }
            userInput = [];
        }
        
        // Update last key time
        lastKeyTime = now;
    }
    
    // Display sequence in the provided element
    function displaySequence(element, sequence) {
        if (!element) return;
        
        element.innerHTML = '';
        
        // Map keycodes to symbols
        const keySymbols = {
            37: '←',
            38: '↑',
            39: '→',
            40: '↓',
            65: 'A',
            66: 'B'
        };
        
        // Create and add each symbol
        sequence.forEach((keyCode, index) => {
            const symbol = document.createElement('span');
            symbol.classList.add('symbol');
            symbol.dataset.index = index;
            symbol.textContent = keySymbols[keyCode] || '?';
            element.appendChild(symbol);
        });
    }
    
    // Highlight a symbol at the specified index
    function highlightSymbol(element, index) {
        if (!element) return;
        
        const symbols = element.querySelectorAll('.symbol');
        if (index >= 0 && index < symbols.length) {
            symbols[index].classList.add('active');
        }
    }
    
    // Clear all highlights
    function clearHighlights(element) {
        if (!element) return;
        
        const symbols = element.querySelectorAll('.symbol');
        symbols.forEach(symbol => {
            symbol.classList.remove('active');
        });
    }
    
    // Compare part of two arrays
    function compareArrayPart(arr1, arr2, length) {
        if (arr1.length < length || arr2.length < length) return false;
        
        for (let i = 0; i < length; i++) {
            if (arr1[arr1.length - length + i] !== arr2[i]) {
                return false;
            }
        }
        
        return true;
    }
    
    // Show notification
    function showNotification(message) {
        const notificationEl = document.getElementById('notification-message');
        const containerEl = document.getElementById('custom-notification');
        
        if (notificationEl && containerEl) {
            notificationEl.textContent = message;
            containerEl.style.display = 'block';
            setTimeout(() => {
                containerEl.style.display = 'none';
            }, 3000);
        } else {
            alert(message);
        }
    }
})();