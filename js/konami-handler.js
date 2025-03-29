/**
 * Unified Konami Code Handler
 * Handles all Konami code detection, highlighting, and navigation
 */
(function() {
    // Initialize when the DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize Konami code tracking variables - separate state for login/signup
        window.konamiState = {
            signupInput: [],
            signupTimes: [],
            loginInput: [],
            loginTimes: [],
            isProcessingResetSignup: false,
            isProcessingResetLogin: false
        };
        
        // Initialize challenge variables globally
        window.challengeState = {
            sequence: [],
            userInput: [],
            pressTimes: []
        };
        
        setupKonamiDisplay();
        setupKeyboardListeners();
    });
    
    // Set up the Konami code sequence displays
    function setupKonamiDisplay() {
        // Display Konami sequences in header (if present)
        const signupDiv = document.getElementById('konami-sequence-signup');
        const loginDiv = document.getElementById('konami-sequence-login');
        
        if (signupDiv && typeof displayKonamiSequence === 'function') {
            displayKonamiSequence(window.signupSequence, 'konami-sequence-signup');
        }
        
        if (loginDiv && typeof displayKonamiSequence === 'function') {
            displayKonamiSequence(window.loginSequence, 'konami-sequence-login');
        }
        
        // Display sequences on login/register pages
        const signupLoginDiv = document.getElementById('konami-sequence-signup-login');
        if (signupLoginDiv && typeof displayKonamiSequence === 'function') {
            displayKonamiSequence(window.signupSequence, 'konami-sequence-signup-login');
        }
        
        const loginRegDiv = document.getElementById('konami-sequence-login-reg');
        if (loginRegDiv && typeof displayKonamiSequence === 'function') {
            displayKonamiSequence(window.loginSequence, 'konami-sequence-login-reg');
        }
        
        // Mobile toggle for Konami codes
        const konamiToggle = document.getElementById('konami-toggle');
        const konamiContainer = document.querySelector('.konami-container');
        
        if (window.innerWidth <= 768 && konamiToggle && konamiContainer) {
            konamiToggle.style.display = 'block';
            konamiToggle.addEventListener('click', () => {
                konamiContainer.classList.toggle('expanded');
            });
        }
    }
    
    // Set up keyboard event listeners
    function setupKeyboardListeners() {
        document.addEventListener('keydown', handleKeydown);
    }
    
    // Handle keydown events
    function handleKeydown(e) {
        const currentPage = window.location.pathname.split('/').pop() || 'index.php';
        const statusDiv = window.statusDiv || document.getElementById('status');
        
        // Skip Konami processing if user is logged in
        if (!statusDiv) return;
        
        // Skip arrow key processing on specific pages for challenge sequences
        if ((currentPage === 'game.php' || currentPage === 'login.php' || currentPage === 'register.php') && 
            (e.keyCode >= 37 && e.keyCode <= 40)) {
            handleChallengeKeypress(e);
            return;
        }
        
        // Process Konami code - Independently process both sequences
        processKonamiKeypress(e);
    }
    
    // Process keypresses for Konami code detection
    function processKonamiKeypress(e) {
        const state = window.konamiState;
        const currentPage = window.location.pathname.split('/').pop() || 'index.php';
        
        // Track signup sequence
        if (!state.isProcessingResetSignup) {
            processSignupSequence(e);
        }
        
        // Track login sequence
        if (!state.isProcessingResetLogin) {
            processLoginSequence(e);
        }
    }
    
    // Process signup sequence
    function processSignupSequence(e) {
        const state = window.konamiState;
        const currentPage = window.location.pathname.split('/').pop() || 'index.php';
        
        // Track input for signup sequence
        state.signupInput.push(e.keyCode);
        state.signupTimes.push(Date.now());
        
        if (state.signupInput.length > 10) {
            state.signupInput.shift();
            state.signupTimes.shift();
        }
        
        const currentIndex = state.signupInput.length - 1;
        const userInputStr = state.signupInput.join(',');
        const signupCodeStr = window.signupCode.join(',');
        
        // Handle highlighting based on the current page
        if (currentPage === 'login.php') {
            // On login page, highlight the signup sequence
            if (currentIndex >= 0 && currentIndex < window.signupSequence.length) {
                if (state.signupInput[currentIndex] === window.signupSequence[currentIndex].keyCode) {
                    highlightKonamiSymbol(currentIndex, 'konami-sequence-signup-login');
                } else {
                    resetKonamiHighlights('konami-sequence-signup-login');
                    resetSignupInput(1000);
                }
            }
        } else if (currentPage !== 'register.php') {
            // On non-register pages, highlight signup sequence
            if (currentIndex >= 0 && currentIndex < window.signupSequence.length) {
                if (state.signupInput[currentIndex] === window.signupSequence[currentIndex].keyCode) {
                    highlightKonamiSymbol(currentIndex, 'konami-sequence-signup');
                } else {
                    resetKonamiHighlights('konami-sequence-signup');
                    resetSignupInput(1000);
                }
            }
        }
        
        // Check for complete signup sequence
        if (userInputStr === signupCodeStr) {
            // Complete signup sequence detected
            if (currentPage !== 'register.php') {
                if (isBotDetected(state.signupTimes, window.signupCode.length)) {
                    showNotification('Bot detected! Slow down!');
                } else {
                    window.location.href = 'register.php';
                }
            }
            
            resetSignupInput();
        }
    }
    
    // Process login sequence
    function processLoginSequence(e) {
        const state = window.konamiState;
        const currentPage = window.location.pathname.split('/').pop() || 'index.php';
        
        // Track input for login sequence
        state.loginInput.push(e.keyCode);
        state.loginTimes.push(Date.now());
        
        if (state.loginInput.length > 10) {
            state.loginInput.shift();
            state.loginTimes.shift();
        }
        
        const currentIndex = state.loginInput.length - 1;
        const userInputStr = state.loginInput.join(',');
        const loginCodeStr = window.loginCode.join(',');
        
        // Handle highlighting based on the current page
        if (currentPage === 'register.php') {
            // On register page, highlight the login sequence
            if (currentIndex >= 0 && currentIndex < window.loginSequence.length) {
                if (state.loginInput[currentIndex] === window.loginSequence[currentIndex].keyCode) {
                    highlightKonamiSymbol(currentIndex, 'konami-sequence-login-reg');
                } else {
                    resetKonamiHighlights('konami-sequence-login-reg');
                    resetLoginInput(1000);
                }
            }
        } else if (currentPage !== 'login.php') {
            // On non-login pages, highlight login sequence
            if (currentIndex >= 0 && currentIndex < window.loginSequence.length) {
                if (state.loginInput[currentIndex] === window.loginSequence[currentIndex].keyCode) {
                    highlightKonamiSymbol(currentIndex, 'konami-sequence-login');
                } else {
                    resetKonamiHighlights('konami-sequence-login');
                    resetLoginInput(1000);
                }
            }
        }
        
        // Check for complete login sequence
        if (userInputStr === loginCodeStr) {
            // Complete login sequence detected
            if (currentPage !== 'login.php') {
                if (isBotDetected(state.loginTimes, window.loginCode.length)) {
                    showNotification('Bot detected! Slow down!');
                } else {
                    window.location.href = 'login.php';
                }
            }
            
            resetLoginInput();
        }
    }
    
    // Process keypresses for symbol challenges
    function handleChallengeKeypress(e) {
        // Only process arrow keys
        if (e.keyCode < 37 || e.keyCode > 40) return;
        
        const state = window.challengeState;
        const challengeSequence = state.sequence;
        
        if (!challengeSequence || challengeSequence.length === 0) return;
        
        state.userInput.push(e.keyCode);
        state.pressTimes.push(Date.now());
        
        const currentIndex = state.userInput.length - 1;
        
        if (currentIndex >= 0 && currentIndex < challengeSequence.length) {
            if (state.userInput[currentIndex] === challengeSequence[currentIndex].keyCode) {
                // Correct key pressed
                if (typeof highlightChallengeSymbol === 'function') {
                    highlightChallengeSymbol(currentIndex);
                }
                
                // Check if sequence is complete
                if (currentIndex === challengeSequence.length - 1) {
                    const isBotDetected = window.isBotDetected && 
                                       window.isBotDetected(state.pressTimes, challengeSequence.length);
                    const challengeStatus = document.getElementById('challenge-status');
                    const submitBtn = document.getElementById('submit-login-btn') || 
                                    document.getElementById('submit-register-btn') ||
                                    document.getElementById('submit-review-btn');
                                    
                    if (isBotDetected && challengeStatus) {
                        challengeStatus.textContent = 'Bot detected! Slow down!';
                        setTimeout(() => {
                            challengeStatus.textContent = 'Press the arrow keys to match the sequence above';
                            if (typeof generateSymbolChallenge === 'function') {
                                generateSymbolChallenge();
                            }
                        }, 2000);
                    } else if (challengeStatus && submitBtn) {
                        challengeStatus.textContent = 'Sequence matched! You can now submit.';
                        submitBtn.disabled = false;
                    }
                }
            } else {
                // Wrong key pressed
                if (typeof resetChallengeHighlights === 'function') {
                    resetChallengeHighlights();
                }
                
                const challengeStatus = document.getElementById('challenge-status');
                if (challengeStatus) {
                    challengeStatus.textContent = 'Incorrect sequence! Try again in 1 second...';
                }
                
                // Clear challenge inputs
                state.userInput = [];
                state.pressTimes = [];
                
                // Generate new challenge after delay
                setTimeout(() => {
                    if (challengeStatus) {
                        challengeStatus.textContent = 'Press the arrow keys to match the sequence above';
                    }
                    
                    if (typeof generateSymbolChallenge === 'function') {
                        generateSymbolChallenge();
                    }
                }, 1000);
            }
        }
    }
    
    // Reset signup input tracking
    function resetSignupInput(delay = 0) {
        const state = window.konamiState;
        
        if (delay > 0) {
            state.isProcessingResetSignup = true;
            setTimeout(() => {
                state.signupInput = [];
                state.signupTimes = [];
                state.isProcessingResetSignup = false;
                
                // Reset all signup highlights
                resetKonamiHighlights('konami-sequence-signup');
                resetKonamiHighlights('konami-sequence-signup-login');
            }, delay);
        } else {
            state.signupInput = [];
            state.signupTimes = [];
            
            // Reset all signup highlights
            resetKonamiHighlights('konami-sequence-signup');
            resetKonamiHighlights('konami-sequence-signup-login');
        }
    }
    
    // Reset login input tracking
    function resetLoginInput(delay = 0) {
        const state = window.konamiState;
        
        if (delay > 0) {
            state.isProcessingResetLogin = true;
            setTimeout(() => {
                state.loginInput = [];
                state.loginTimes = [];
                state.isProcessingResetLogin = false;
                
                // Reset all login highlights
                resetKonamiHighlights('konami-sequence-login');
                resetKonamiHighlights('konami-sequence-login-reg');
            }, delay);
        } else {
            state.loginInput = [];
            state.loginTimes = [];
            
            // Reset all login highlights
            resetKonamiHighlights('konami-sequence-login');
            resetKonamiHighlights('konami-sequence-login-reg');
        }
    }
})();