/**
 * Authentication Challenges
 * Handles Konami code and arrow challenge sequences for login/register pages
 */
(function() {
    window.gameRating = window.gameRating || {};
    window.gameRating.auth = {};
    
    window.gameRating.utils = {
        showNotification: function(message, type = 'error') {
            const notification = document.getElementById('custom-notification');
            const messageElement = document.getElementById('notification-message');
            if (notification && messageElement) {
                messageElement.textContent = message;
                notification.className = `custom-notification ${type}`; // Add type for styling
                notification.style.display = 'block';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 3000);
            } else {
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
        }
    };
    
    const KEY_SYMBOLS = { 37: '←', 38: '↑', 39: '→', 40: '↓', 65: 'A', 66: 'B' };
    const KONAMI_SIGNUP = [38, 38, 40, 40, 37, 39, 37, 39, 65, 66];
    const KONAMI_LOGIN = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65];
    
    window.gameRating.auth = {
        challengeSequence: [],
        challengeUserInput: [],
        challengePressTimes: [],
        konamiInput: [],
        konamiTimes: [],
        currentPage: '',
        submitBtnId: '',
        konamiSequenceId: '',
        konamiCode: [],
        konamiRedirect: '',
        
        init: function(page) {
            this.currentPage = page;
            this.submitBtnId = page === 'login' ? 'submit-login-btn' : 'submit-register-btn';
            this.konamiSequenceId = page === 'login' ? 'konami-sequence-signup-login' : 'konami-sequence-login-reg';
            this.konamiCode = page === 'login' ? KONAMI_SIGNUP : KONAMI_LOGIN;
            this.konamiRedirect = page === 'login' ? 'register.php' : 'login.php';
            
            this.setupKonamiDisplay();
            this.generateSymbolChallenge();
            document.addEventListener('keydown', this.handleKeydown.bind(this));
            
            // Add password validation on input
            const passwordInput = document.getElementById('password');
            if (passwordInput) {
                passwordInput.addEventListener('input', this.validatePassword.bind(this));
            }
        },
        
        validatePassword: function(e) {
            const password = e.target.value;
            const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
            if (!regex.test(password)) {
                window.gameRating.utils.showNotification(
                    'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.',
                    'error'
                );
            }
        },
        
        // Set up Konami code display
        setupKonamiDisplay: function() {
            const konamiDiv = document.getElementById(this.konamiSequenceId);
            if (!konamiDiv) return;
            
            if (typeof displayKonamiSequence === 'function') {
                // Use the global displayKonamiSequence function if available
                if (this.currentPage === 'login') {
                    displayKonamiSequence(window.signupSequence || this.createSequenceObjects(KONAMI_SIGNUP), this.konamiSequenceId);
                } else {
                    displayKonamiSequence(window.loginSequence || this.createSequenceObjects(KONAMI_LOGIN), this.konamiSequenceId);
                }
            } else {
                // Fallback rendering
                konamiDiv.innerHTML = '';
                
                const sequence = this.konamiCode;
                sequence.forEach((keyCode, i) => {
                    const symbolSpan = document.createElement('span');
                    symbolSpan.className = 'symbol';
                    symbolSpan.id = `${this.konamiSequenceId}-symbol-${i}`;
                    symbolSpan.textContent = KEY_SYMBOLS[keyCode] || '?';
                    symbolSpan.style.margin = '0 5px';
                    konamiDiv.appendChild(symbolSpan);
                });
            }
        },
        
        // Create sequence objects for displayKonamiSequence
        createSequenceObjects: function(sequence) {
            return sequence.map(keyCode => ({
                name: this.getKeyName(keyCode),
                keyCode: keyCode,
                symbol: KEY_SYMBOLS[keyCode] || '?'
            }));
        },
        
        // Get key name from key code
        getKeyName: function(keyCode) {
            switch(keyCode) {
                case 37: return 'left';
                case 38: return 'up';
                case 39: return 'right';
                case 40: return 'down';
                case 65: return 'a';
                case 66: return 'b';
                default: return 'unknown';
            }
        },
        
        // Generate symbol challenge
        generateSymbolChallenge: function() {
            const symbols = [
                { name: 'up', keyCode: 38, symbol: '↑' },
                { name: 'down', keyCode: 40, symbol: '↓' },
                { name: 'left', keyCode: 37, symbol: '←' },
                { name: 'right', keyCode: 39, symbol: '→' }
            ];
            this.challengeSequence = [];
            
            // Generate 6 random symbols
            for (let i = 0; i < 6; i++) {
                const randomIndex = Math.floor(Math.random() * symbols.length);
                this.challengeSequence.push({...symbols[randomIndex]});
            }
            
            const challengeDiv = document.getElementById('symbol-challenge');
            if (!challengeDiv) return;
            
            challengeDiv.innerHTML = '';
            
            this.challengeSequence.forEach((item, index) => {
                const symbolSpan = document.createElement('span');
                symbolSpan.className = 'symbol';
                symbolSpan.id = `challenge-symbol-${index}`;
                symbolSpan.textContent = item.symbol;
                challengeDiv.appendChild(symbolSpan);
            });
            
            this.challengeUserInput = [];
            this.challengePressTimes = [];
            window.gameRating.auth.challengeCompleted = false; // Reset challenge completion flag
            
            const submitBtn = document.getElementById(this.submitBtnId);
            const statusEl = document.getElementById('challenge-status');
            
            if (submitBtn) submitBtn.disabled = true;
            if (statusEl) statusEl.textContent = 'Press the arrow keys to match the sequence above';
        },
        
        // Highlight challenge symbol
        highlightChallengeSymbol: function(index) {
            const symbols = document.querySelectorAll('#symbol-challenge .symbol');
            symbols.forEach(symbol => symbol.classList.remove('active'));
            
            if (index >= 0 && index < symbols.length) {
                const currentSymbol = document.getElementById(`challenge-symbol-${index}`);
                if (currentSymbol) currentSymbol.classList.add('active');
            }
        },
        
        // Reset challenge highlights
        resetChallengeHighlights: function() {
            const symbols = document.querySelectorAll('#symbol-challenge .symbol');
            symbols.forEach(symbol => symbol.classList.remove('active'));
        },
        
        // Handle keydown event
        handleKeydown: function(e) {
            // Skip if in input fields
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                return;
            }
            
            // Ignore all key presses if auto-submitting is in progress
            if (window.gameRating.auth.isAutoSubmitting) {
                return;
            }
            
            // Process for Konami code
            this.konamiInput.push(e.keyCode);
            this.konamiTimes.push(Date.now());
            
            if (this.konamiInput.length > this.konamiCode.length) {
                this.konamiInput.shift();
                this.konamiTimes.shift();
            }
            
            // Check for Konami sequence match
            const userInputStr = this.konamiInput.join(',');
            const konamiCodeStr = this.konamiCode.join(',');
            
            // Highlight current symbol in sequence
            const currentKonamiIndex = this.konamiInput.length - 1;
            if (currentKonamiIndex >= 0 && currentKonamiIndex < 10) {
                // Get expected key code
                const expectedKeyCode = this.konamiCode[currentKonamiIndex];
                
                if (this.konamiInput[currentKonamiIndex] === expectedKeyCode) {
                    if (typeof highlightKonamiSymbol === 'function') {
                        highlightKonamiSymbol(currentKonamiIndex, this.konamiSequenceId);
                    } else {
                        // Fallback highlighting
                        const symbols = document.querySelectorAll(`#${this.konamiSequenceId} .symbol`);
                        if (symbols && symbols.length > currentKonamiIndex) {
                            symbols.forEach((s, i) => {
                                s.style.color = i === currentKonamiIndex ? '#ff00ff' : '#00ff00';
                            });
                        }
                    }
                }
            }
            
            // Complete match detected
            if (userInputStr === konamiCodeStr) {
                if (window.gameRating.utils.isBotDetected(this.konamiTimes, this.konamiCode.length)) {
                    window.gameRating.utils.showNotification('Bot detected! Slow down!');
                } else {
                    window.location.href = this.konamiRedirect;
                }
                
                this.konamiInput = [];
                this.konamiTimes = [];
                
                if (typeof resetKonamiHighlights === 'function') {
                    resetKonamiHighlights(this.konamiSequenceId);
                }
            }
            
            // Process for arrow key challenge
            if (e.keyCode >= 37 && e.keyCode <= 40) {
                this.handleChallengeKey(e.keyCode);
            }
        },
        
        // Handle challenge key press
        handleChallengeKey: function(keyCode) {
            this.challengeUserInput.push(keyCode);
            this.challengePressTimes.push(Date.now());
            
            const currentIndex = this.challengeUserInput.length - 1;
            if (currentIndex >= 0 && currentIndex < this.challengeSequence.length) {
                if (this.challengeUserInput[currentIndex] === this.challengeSequence[currentIndex].keyCode) {
                    this.highlightChallengeSymbol(currentIndex);
                    
                    // Check if sequence is complete
                    if (currentIndex === this.challengeSequence.length - 1) {
                        const statusEl = document.getElementById('challenge-status');
                        const submitBtn = document.getElementById(this.submitBtnId);
                        
                        if (window.gameRating.utils.isBotDetected(this.challengePressTimes, this.challengeSequence.length)) {
                            if (statusEl) statusEl.textContent = 'Bot detected! Slow down!';
                            
                            setTimeout(() => {
                                if (statusEl) statusEl.textContent = 'Press the arrow keys to match the sequence above';
                                this.generateSymbolChallenge();
                            }, 2000);
                        } else {
                            // Mark the challenge as completed
                            window.gameRating.auth.challengeCompleted = true;
                            
                            // Success! Update the status message
                            if (statusEl) {
                                statusEl.textContent = this.currentPage === 'login' 
                                    ? 'Sequence matched! Logging in...' 
                                    : 'Sequence matched! Registering...';
                                statusEl.style.color = 'green';
                            }
                            
                            // Add success class to symbols
                            const challengeDiv = document.getElementById('symbol-challenge');
                            if (challengeDiv) {
                                challengeDiv.classList.add('success');
                            }
                            
                            // Enable submit button
                            if (submitBtn) submitBtn.disabled = false;
                            
                            // Auto-submit logic will be handled by auto-submit.js
                        }
                    }
                } else {
                    // Wrong key - use original error handling
                    this.resetChallengeHighlights();
                    
                    const statusEl = document.getElementById('challenge-status');
                    if (statusEl) {
                        statusEl.textContent = 'Incorrect sequence! Try again.';
                        statusEl.style.color = 'red';
                    }
                    
                    // Show error feedback temporarily
                    const challengeDiv = document.getElementById('symbol-challenge');
                    if (challengeDiv) {
                        challengeDiv.classList.add('error');
                        setTimeout(() => {
                            challengeDiv.classList.remove('error');
                        }, 500);
                    }
                    
                    setTimeout(() => {
                        if (statusEl) {
                            statusEl.textContent = 'Press the arrow keys to match the sequence above';
                            statusEl.style.color = '';
                        }
                        this.generateSymbolChallenge();
                    }, 2000);
                }
            }
        }
    };
    
    // Initialize on DOM loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Detect current page
        const currentPage = window.location.pathname.includes('login.php') ? 'login' : 'register';
        
        // Initialize auth challenges
        window.gameRating.auth.init(currentPage);
    });
})();