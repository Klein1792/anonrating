/**
 * Auto-Submit Challenge Enhancement
 * Adds automatic form submission when the arrow key challenge is completed
 * Works with both login.php and register.php
 * Prevents any form submission until challenge is complete
 */
(function() {
    // Wait for DOM and auth-pages.js to fully load
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(initAutoSubmit, 300);
    });

    function initAutoSubmit() {
        // Check if gameRating and auth module are available
        if (!window.gameRating || !window.gameRating.auth) {
            console.warn('Auto-submit challenge: gameRating.auth not found. Retrying...');
            setTimeout(initAutoSubmit, 200);
            return;
        }

        // Detect current page
        const isLoginPage = window.location.pathname.includes('login.php');
        const formId = isLoginPage ? 'login-form' : 'register-form';
        const successMessage = isLoginPage ? 'Sequence matched! Logging in...' : 'Sequence matched! Registering...';
        
        // Flag to track if we're in the process of auto-submitting
        let isAutoSubmitting = false;
        
        // Get form and add id if missing
        const form = document.getElementById(formId) || 
                     document.querySelector('form[method="POST"]');
                
        if (form && !form.id) {
            form.id = formId;
        }
        
        if (form) {
            // THIS IS THE CRITICAL FIX:
            // Completely override the form's submit method to prevent any uncontrolled submissions
            const originalSubmit = form.submit;
            form.submit = function() {
                // Only allow submission if we're in auto-submit mode
                if (isAutoSubmitting) {
                    originalSubmit.apply(form);
                } else {
                    const submitBtn = document.getElementById(window.gameRating.auth.submitBtnId);
                    if (submitBtn && !submitBtn.disabled) {
                        // If challenge is complete but not auto-submitting yet,
                        // just show message and don't submit
                        const statusEl = document.getElementById('challenge-status');
                        if (statusEl) {
                            statusEl.textContent = successMessage;
                            statusEl.style.color = 'green';
                        }
                    } else {
                        // Challenge not complete
                        const statusEl = document.getElementById('challenge-status');
                        if (statusEl) {
                            statusEl.textContent = 'Complete the arrow sequence first!';
                            statusEl.style.color = 'red';
                            
                            setTimeout(() => {
                                statusEl.textContent = 'Press the arrow keys to match the sequence above';
                                statusEl.style.color = '';
                            }, 2000);
                        }
                    }
                }
            };
            
            // Prevent default form submission event
            form.addEventListener('submit', function(e) {
                // Always prevent normal submission
                e.preventDefault();
                e.stopPropagation();
                
                const submitBtn = document.getElementById(window.gameRating.auth.submitBtnId);
                if (submitBtn && submitBtn.disabled) {
                    // Challenge not completed
                    const statusEl = document.getElementById('challenge-status');
                    if (statusEl) {
                        statusEl.textContent = 'Complete the arrow sequence first!';
                        statusEl.style.color = 'red';
                        
                        // Reset after a delay
                        setTimeout(() => {
                            statusEl.textContent = 'Press the arrow keys to match the sequence above';
                            statusEl.style.color = '';
                        }, 2000);
                    } else {
                        window.gameRating.utils.showNotification('Complete the arrow sequence first!');
                    }
                }
                return false;
            });
        }
        
        // Prevent Enter key in form fields from submitting
        document.addEventListener('keydown', function(e) {
            // If Enter key is pressed anywhere in the document
            if (e.key === 'Enter') {
                const submitBtn = document.getElementById(window.gameRating.auth.submitBtnId);
                
                // If the challenge is not complete OR 
                // if we're waiting for auto-submit, prevent Enter key action
                if (!submitBtn || submitBtn.disabled || isAutoSubmitting) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (submitBtn && submitBtn.disabled) {
                        // Show message only if challenge is not complete
                        const statusEl = document.getElementById('challenge-status');
                        if (statusEl) {
                            statusEl.textContent = 'Complete the arrow sequence first!';
                            statusEl.style.color = 'red';
                            
                            setTimeout(() => {
                                statusEl.textContent = 'Press the arrow keys to match the sequence above';
                                statusEl.style.color = '';
                            }, 2000);
                        }
                    }
                    return false;
                }
            }
        }, true); // Using capture phase to ensure we get the event first
        
        // Override handleChallengeKey to add auto-submit
        window.gameRating.auth.handleChallengeKey = function(keyCode) {
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
                            // Success! Update the status message
                            if (statusEl) {
                                statusEl.textContent = successMessage;
                                statusEl.style.color = 'green';
                            }
                            
                            // Add success class to symbols
                            const challengeDiv = document.getElementById('symbol-challenge');
                            if (challengeDiv) {
                                challengeDiv.classList.add('success');
                            }
                            
                            // Enable submit button
                            if (submitBtn) submitBtn.disabled = false;
                            
                            // Set auto-submitting flag
                            isAutoSubmitting = true;
                            
                            // Auto-submit after a delay using our overridden method
                            setTimeout(() => {
                                if (form) {
                                    // This will call our overridden submit method above
                                    form.submit();
                                }
                            }, 800);
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
        };

        console.log('Auto-submit challenge enhancement loaded with priority Enter key blocking.');
    }
})();