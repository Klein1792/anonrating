/**
 * Challenge System Module
 * Handles Konami-style key sequences for review submission verification
 */

(function() {
    'use strict';
    
    // Track challenge state
    let challengeState = {
        sequence: [],
        userInput: [],
        pressTimes: []
    };
    
    /**
     * Setup the challenge sequence UI and event handlers
     */
    function setupChallengeSequence() {
        const reviewForm = document.getElementById('review-form');
        if (!reviewForm) return;
    
        if (!document.getElementById('review-challenge-container')) {
            const challengeContainer = document.createElement('div');
            challengeContainer.id = 'review-challenge-container';
            challengeContainer.className = 'review-challenge-container';
            challengeContainer.innerHTML = `
                <p>Complete the sequence to enable submit:</p>
                <div id="symbol-challenge" class="symbols"></div>
                <p id="challenge-status" class="challenge-status">Press the arrow keys to match the sequence above</p>
            `;
    
            const submitBtnGroup = reviewForm.querySelector('.form-group:last-child');
            reviewForm.insertBefore(challengeContainer, submitBtnGroup);
        }
    
        const submitBtn = reviewForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
        }
    
        document.addEventListener('keydown', handleKeyDown);
    
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            document.removeEventListener('keydown', handleKeyDown);
        });
    
        generateSymbolChallenge();
    }
    
    /**
     * Generate a random sequence of arrow symbols for the challenge
     */
    function generateSymbolChallenge() {
        const symbols = [
            { name: 'up', keyCode: 38, symbol: '↑' },
            { name: 'down', keyCode: 40, symbol: '↓' },
            { name: 'left', keyCode: 37, symbol: '←' },
            { name: 'right', keyCode: 39, symbol: '→' }
        ];
        
        challengeState.sequence = [];
        
        for (let i = 0; i < 6; i++) {
            const randomIndex = Math.floor(Math.random() * symbols.length);
            challengeState.sequence.push({...symbols[randomIndex]});
        }
        
        const challengeDiv = document.getElementById('symbol-challenge');
        if (challengeDiv) {
            challengeDiv.innerHTML = '';
            
            challengeState.sequence.forEach((item, index) => {
                const symbolSpan = document.createElement('span');
                symbolSpan.className = 'symbol';
                symbolSpan.id = `challenge-symbol-${index}`;
                symbolSpan.textContent = item.symbol;
                challengeDiv.appendChild(symbolSpan);
            });
        }
        
        challengeState.userInput = [];
        challengeState.pressTimes = [];
        
        const submitBtn = document.getElementById('review-form').querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
        }
        
        const challengeStatus = document.getElementById('challenge-status');
        if (challengeStatus) {
            challengeStatus.textContent = 'Press the arrow keys to match the sequence above';
            challengeStatus.style.color = '';
        }
        
        resetChallengeHighlights();
    }
    
    /**
     * Handle key press events for the challenge system
     * @param {KeyboardEvent} e - The keyboard event
     */
    function handleKeyDown(e) {
        const arrowKeyCodes = [37, 38, 39, 40];
        if (!arrowKeyCodes.includes(e.keyCode)) return;
        
        if (!challengeState.sequence.length || !document.getElementById('review-form')) return;
        
        const currentPos = challengeState.userInput.length;
        
        if (currentPos >= challengeState.sequence.length) return;
        
        challengeState.userInput.push(e.keyCode);
        challengeState.pressTimes.push(Date.now());
        
        highlightChallengeSymbol(currentPos);
        
        if (e.keyCode !== challengeState.sequence[currentPos].keyCode) {
            resetChallengeSequence("Incorrect sequence! Try again.");
            return;
        }
        
        if (currentPos === challengeState.sequence.length - 1) {
            const submitBtn = document.getElementById('review-form').querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
            }
            
            const challengeStatus = document.getElementById('challenge-status');
            if (challengeStatus) {
                challengeStatus.textContent = 'Sequence complete! You can now submit your review.';
                challengeStatus.style.color = '#00ff00';
            }
        }
    }
    
    /**
     * Reset the challenge sequence when a mistake is made
     * @param {string} message - Optional message to display
     */
    function resetChallengeSequence(message) {
        challengeState.userInput = [];
        challengeState.pressTimes = [];
        
        const challengeStatus = document.getElementById('challenge-status');
        if (challengeStatus) {
            challengeStatus.textContent = message || 'Press the arrow keys to match the sequence above';
            challengeStatus.style.color = message ? '#ff0000' : '';
            
            setTimeout(() => {
                challengeStatus.style.color = '';
            }, 2000);
        }
        
        resetChallengeHighlights();
    }
    
    /**
     * Highlight the current symbol in the challenge
     * @param {number} index - Index of the symbol to highlight
     */
    function highlightChallengeSymbol(index) {
        const symbols = document.querySelectorAll('#symbol-challenge .symbol');
        
        resetChallengeHighlights();
        
        if (index >= 0 && index < symbols.length) {
            const currentSymbol = document.getElementById(`challenge-symbol-${index}`);
            if (currentSymbol) {
                currentSymbol.classList.add('active');
            }
        }
    }
    
    /**
     * Reset all symbol highlights in the challenge
     */
    function resetChallengeHighlights() {
        const symbols = document.querySelectorAll('#symbol-challenge .symbol');
        symbols.forEach(symbol => symbol.classList.remove('active'));
    }
    
    /**
     * Check if the sequence has been completed
     * @returns {boolean} True if the sequence is completed
     */
    function isSequenceCompleted() {
        if (!challengeState.sequence.length) return false;
        
        return (
            challengeState.userInput.length === challengeState.sequence.length &&
            challengeState.userInput.every((input, index) => input === challengeState.sequence[index].keyCode)
        );
    }
    
    // Export functions to global scope
    window.ChallengeSystem = {
        setupChallengeSequence,
        generateSymbolChallenge,
        isSequenceCompleted,
        resetChallengeSequence
    };
})();