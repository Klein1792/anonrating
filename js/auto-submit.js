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
        
        // Flag to track if we're in the process of auto-submitting (made global)
        window.gameRating.auth.isAutoSubmitting = false;
        // Flag to track if the challenge is completed
        window.gameRating.auth.challengeCompleted = false;
        // Flag to track if an auto-submit is already scheduled
        let autoSubmitScheduled = false;
        
        // Get form and add id if missing
        const form = document.getElementById(formId) || 
                     document.querySelector('form[method="POST"]');
                
        if (form && !form.id) {
            form.id = formId;
        }
        
        if (form) {
            // Override the form's submit method to perform the actual submission
            const originalSubmit = form.submit;
            form.submit = function() {
                // Only allow submission if we're in auto-submit mode or challenge is completed
                if (window.gameRating.auth.isAutoSubmitting || window.gameRating.auth.challengeCompleted) {
                    // Create a FormData object with the form data
                    const formData = new FormData(form);
                    
                    // Send the form data via fetch to ensure proper submission
                    fetch(form.action, {
                        method: form.method,
                        body: formData,
                        credentials: 'same-origin'
                    }).then(response => {
                        if (response.redirected) {
                            // Follow the redirect to index.php
                            window.location.href = response.url;
                        } else {
                            return response.json().then(data => {
                                if (data.success) {
                                    // Redirect to index.php if the response indicates success
                                    window.location.href = '/index.php';
                                } else {
                                    // Show error message if login fails
                                    const statusEl = document.getElementById('challenge-status');
                                    if (statusEl) {
                                        statusEl.textContent = data.error || 'Login failed. Please try again.';
                                        statusEl.style.color = 'red';
                                    }
                                    // Reset flags to allow retry
                                    window.gameRating.auth.isAutoSubmitting = false;
                                    form.disabled = false;
                                    autoSubmitScheduled = false;
                                }
                            });
                        }
                    }).catch(error => {
                        console.error('Error submitting form:', error);
                        const statusEl = document.getElementById('challenge-status');
                        if (statusEl) {
                            statusEl.textContent = 'An error occurred. Please try again.';
                            statusEl.style.color = 'red';
                        }
                        // Reset flags to allow retry
                        window.gameRating.auth.isAutoSubmitting = false;
                        form.disabled = false;
                        autoSubmitScheduled = false;
                    });
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
            
            // Handle form submission event
            form.addEventListener('submit', function(e) {
                // Always prevent default submission
                e.preventDefault();
                e.stopPropagation();
                
                const submitBtn = document.getElementById(window.gameRating.auth.submitBtnId);
                if (submitBtn && submitBtn.disabled) {
                    // Challenge not completed
                    const statusEl = document.getElementById('challenge-status');
                    if (statusEl) {
                        statusEl.textContent = 'Complete the arrow sequence first!';
                        statusEl.style.color = 'red';
                        
                        setTimeout(() => {
                            statusEl.textContent = 'Press the arrow keys to match the sequence above';
                            statusEl.style.color = '';
                        }, 2000);
                    } else {
                        window.gameRating.utils.showNotification('Complete the arrow sequence first!');
                    }
                } else if (window.gameRating.auth.challengeCompleted) {
                    // Challenge is completed, trigger immediate submission
                    window.gameRating.auth.isAutoSubmitting = true;
                    
                    const statusEl = document.getElementById('challenge-status');
                    if (statusEl) {
                        statusEl.textContent = successMessage;
                        statusEl.style.color = 'green';
                    }
                    
                    // Add success class to symbols
                    const challengeDiv = document.getElementById('symbol-challenge');
                    if (challengeDiv) {
                        challengeDiv.classList.add('success');
                    }
                    
                    // Disable the entire form to prevent further interaction
                    form.disabled = true;
                    
                    // Immediately trigger the submission
                    form.submit();
                }
                return false;
            });
        }
        
        // Block other key presses at the document level during auto-submit (for Konami code)
        document.addEventListener('keydown', function(e) {
            // If auto-submitting is in progress, block all key presses except Enter
            if (window.gameRating.auth.isAutoSubmitting && e.key !== 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                
                const statusEl = document.getElementById('challenge-status');
                if (statusEl) {
                    statusEl.textContent = 'Please wait, processing...';
                    statusEl.style.color = 'green';
                }
                return false;
            }
        }, true); // Using capture phase to ensure we block keys first
        
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
                            // Mark the challenge as completed
                            window.gameRating.auth.challengeCompleted = true;
                            
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
                            
                            // Trigger auto-submit
                            if (!autoSubmitScheduled) {
                                autoSubmitScheduled = true;
                                window.gameRating.auth.isAutoSubmitting = true;
                                
                                // Disable the entire form to prevent further interaction
                                form.disabled = true;
                                
                                // Auto-submit immediately (0ms delay)
                                setTimeout(() => {
                                    if (form) {
                                        form.submit();
                                        window.gameRating.auth.isAutoSubmitting = false;
                                        form.disabled = false;
                                        autoSubmitScheduled = false;
                                    }
                                }, 0); // Changed from 800ms to 0ms
                            }
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

        console.log('Auto-submit challenge enhancement loaded with immediate submission.');
    }
})();