/**
 * Unified Keyboard Handler
 * Handles Konami codes and challenge key sequences
 */
(function() {
    // Create namespace
    window.gameRating = window.gameRating || {};
    window.gameRating.keyboard = {
        // State for tracking keyboard sequences
        state: {
            konami: {
                login: {
                    input: [],
                    times: [],
                    resetting: false
                },
                signup: {
                    input: [],
                    times: [],
                    resetting: false
                }
            }
        },
        
        // Initialize keyboard handling
        init: function() {
            // Check if konamiCodes is defined
            if (!window.gameRating.konamiCodes) {
                console.error('Konami codes not defined. Make sure konami-codes.js is loaded before keyboard-handler.js');
                
                // Create default empty definitions to prevent errors
                window.gameRating.konamiCodes = {
                    signup: {
                        sequence: [],
                        keyCodes: []
                    },
                    login: {
                        sequence: [],
                        keyCodes: []
                    }
                };
            }
            
            // Display Konami code sequences if applicable
            this.setupKonamiDisplay();
        },
        
        // Set up the Konami code displays
        setupKonamiDisplay: function() {
            // Display Konami sequences in header (if present)
            const signupDiv = document.getElementById('konami-sequence-signup');
            const loginDiv = document.getElementById('konami-sequence-login');
            
            if (signupDiv) {
                this.displayKonamiSequence(window.gameRating.konamiCodes.signup.sequence, 'konami-sequence-signup');
            }
            
            if (loginDiv) {
                this.displayKonamiSequence(window.gameRating.konamiCodes.login.sequence, 'konami-sequence-login');
            }
            
            // Display sequences on login/register pages
            const signupLoginDiv = document.getElementById('konami-sequence-signup-login');
            if (signupLoginDiv) {
                this.displayKonamiSequence(window.gameRating.konamiCodes.signup.sequence, 'konami-sequence-signup-login');
            }
            
            const loginRegDiv = document.getElementById('konami-sequence-login-reg');
            if (loginRegDiv) {
                this.displayKonamiSequence(window.gameRating.konamiCodes.login.sequence, 'konami-sequence-login-reg');
            }
        },
        
        // Handle keydown event (called from app.js event delegation)
        handleKeydown: function(e) {
            // Store reference to this keyboard handler
            const keyboard = window.gameRating.keyboard;
            
            const currentPage = window.location.pathname.split('/').pop() || 'index.php';
            const statusDiv = window.statusDiv || document.getElementById('status');
            
            // Skip Konami processing if user is logged in
            if (!statusDiv) return;
            
            // Process Konami code with proper this reference
            keyboard.processKonamiKeypress(e);
        },
        
        // Display a Konami code sequence as symbols
        displayKonamiSequence: function(sequence, elementId) {
            const sequenceDiv = document.getElementById(elementId);
            if (!sequenceDiv) return;
            
            sequenceDiv.innerHTML = '';
            sequenceDiv.className = 'symbols';
            
            sequence.forEach((item, index) => {
                const symbolSpan = document.createElement('span');
                symbolSpan.className = 'symbol';
                symbolSpan.id = `${elementId}-symbol-${index}`;
                symbolSpan.textContent = item.symbol;
                sequenceDiv.appendChild(symbolSpan);
            });
        },
        
        // Process keypresses for Konami code detection
        processKonamiKeypress: function(e) {
            // Store reference to this keyboard handler
            const keyboard = window.gameRating.keyboard;
            
            // Process both sequences independently with proper this reference
            keyboard.processSignupSequence(e);
            keyboard.processLoginSequence(e);
        },
        
        // Process signup sequence
        processSignupSequence: function(e) {
            const state = this.state.konami.signup;
            const currentPage = window.location.pathname.split('/').pop() || 'index.php';
            
            // Skip if we're resetting
            if (state.resetting) return;
            
            // Track input for signup sequence
            state.input.push(e.keyCode);
            state.times.push(Date.now());
            
            // Keep only the last 10 keypresses
            if (state.input.length > 10) {
                state.input.shift();
                state.times.shift();
            }
            
            const currentIndex = state.input.length - 1;
            
            // Handle highlighting based on the current page
            if (currentPage === 'login.php') {
                // On login page, highlight the signup sequence
                this.handleSequenceHighlighting(
                    currentIndex, 
                    state.input, 
                    window.gameRating.konamiCodes.signup.sequence, 
                    'konami-sequence-signup-login'
                );
            } else if (currentPage !== 'register.php') {
                // On non-register pages, highlight signup sequence
                this.handleSequenceHighlighting(
                    currentIndex, 
                    state.input, 
                    window.gameRating.konamiCodes.signup.sequence, 
                    'konami-sequence-signup'
                );
            }
            
            // Check for complete sequence
            const completed = this.checkCompleteSequence(
                state.input,
                window.gameRating.konamiCodes.signup.keyCodes
            );
            
            if (completed && currentPage !== 'register.php') {
                if (window.gameRating.utils.isBotDetected(state.times, 10)) {
                    window.gameRating.utils.showNotification('Bot detected! Slow down!');
                } else {
                    window.location.href = 'register.php';
                }
                
                this.resetSequenceState('signup');
            }
        },
        
        // Process login sequence
        processLoginSequence: function(e) {
            const state = this.state.konami.login;
            const currentPage = window.location.pathname.split('/').pop() || 'index.php';
            
            // Skip if we're resetting
            if (state.resetting) return;
            
            // Track input for login sequence
            state.input.push(e.keyCode);
            state.times.push(Date.now());
            
            // Keep only the last 10 keypresses
            if (state.input.length > 10) {
                state.input.shift();
                state.times.shift();
            }
            
            const currentIndex = state.input.length - 1;
            
            // Handle highlighting based on the current page
            if (currentPage === 'register.php') {
                // On register page, highlight the login sequence
                this.handleSequenceHighlighting(
                    currentIndex, 
                    state.input, 
                    window.gameRating.konamiCodes.login.sequence, 
                    'konami-sequence-login-reg'
                );
            } else if (currentPage !== 'login.php') {
                // On non-login pages, highlight login sequence
                this.handleSequenceHighlighting(
                    currentIndex, 
                    state.input, 
                    window.gameRating.konamiCodes.login.sequence, 
                    'konami-sequence-login'
                );
            }
            
            // Check for complete sequence
            const completed = this.checkCompleteSequence(
                state.input,
                window.gameRating.konamiCodes.login.keyCodes
            );
            
            if (completed && currentPage !== 'login.php') {
                if (window.gameRating.utils.isBotDetected(state.times, 10)) {
                    window.gameRating.utils.showNotification('Bot detected! Slow down!');
                } else {
                    window.location.href = 'login.php';
                }
                
                this.resetSequenceState('login');
            }
        },
        
        // Handle sequence highlighting
        handleSequenceHighlighting: function(currentIndex, userInput, sequence, elementId) {
            if (currentIndex >= 0 && currentIndex < sequence.length) {
                if (userInput[currentIndex] === sequence[currentIndex].keyCode) {
                    this.highlightKonamiSymbol(currentIndex, elementId);
                } else {
                    this.resetKonamiHighlights(elementId);
                    this.resetSequenceStateById(elementId);
                }
            }
        },
        
        // Check if a sequence is complete
        checkCompleteSequence: function(userInput, targetSequence) {
            if (userInput.length < targetSequence.length) return false;
            
            const lastInputs = userInput.slice(-targetSequence.length);
            return lastInputs.every((keyCode, i) => keyCode === targetSequence[i]);
        },
        
        // Highlight a symbol in a Konami sequence
        highlightKonamiSymbol: function(index, elementId) {
            // First, reset all highlights
            this.resetKonamiHighlights(elementId);
            
            // Then, highlight the current symbol
            if (index >= 0) {
                const symbolId = `${elementId}-symbol-${index}`;
                const symbol = document.getElementById(symbolId);
                if (symbol) {
                    symbol.classList.add('active');
                }
            }
        },
        
        // Reset all highlighted symbols in a sequence
        resetKonamiHighlights: function(elementId) {
            const symbols = document.querySelectorAll(`#${elementId} .symbol`);
            symbols.forEach(symbol => symbol.classList.remove('active'));
        },
        
        // Reset sequence state by ID
        resetSequenceStateById: function(elementId) {
            if (elementId.includes('signup')) {
                this.resetSequenceState('signup', 1000);
            } else if (elementId.includes('login')) {
                this.resetSequenceState('login', 1000);
            }
        },
        
        // Reset sequence state
        resetSequenceState: function(type, delay = 0) {
            const state = this.state.konami[type];
            
            if (delay > 0) {
                state.resetting = true;
                setTimeout(() => {
                    state.input = [];
                    state.times = [];
                    state.resetting = false;
                    
                    // Reset all highlights
                    if (type === 'signup') {
                        this.resetKonamiHighlights('konami-sequence-signup');
                        this.resetKonamiHighlights('konami-sequence-signup-login');
                    } else {
                        this.resetKonamiHighlights('konami-sequence-login');
                        this.resetKonamiHighlights('konami-sequence-login-reg');
                    }
                }, delay);
            } else {
                state.input = [];
                state.times = [];
                
                // Reset all highlights
                if (type === 'signup') {
                    this.resetKonamiHighlights('konami-sequence-signup');
                    this.resetKonamiHighlights('konami-sequence-signup-login');
                } else {
                    this.resetKonamiHighlights('konami-sequence-login');
                    this.resetKonamiHighlights('konami-sequence-login-reg');
                }
            }
        }
    };
    
})();