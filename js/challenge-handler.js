/**
 * Symbol Challenge Handler
 * Manages the arrow key challenges on login, register and game pages
 */
(function() {
    // Initialize when the DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        const currentPage = window.location.pathname.split('/').pop() || 'index.php';
        
        // Only initialize on pages that need the challenge
        if (currentPage === 'login.php' || currentPage === 'register.php' || currentPage === 'game.php') {
            initializeChallenge();
        }
    });
    
    // Initialize the symbol challenge
    function initializeChallenge() {
        // Create or access the challenge state
        window.challengeState = window.challengeState || {
            sequence: [],
            userInput: [],
            pressTimes: []
        };
        
        // Generate initial challenge
        generateSymbolChallenge();
        
        // Disable submit buttons initially
        const submitBtn = document.getElementById('submit-login-btn') || 
                          document.getElementById('submit-register-btn') ||
                          document.getElementById('submit-review-btn');
                          
        if (submitBtn) {
            submitBtn.disabled = true;
        }
    }
    
    // Generate a random symbol challenge sequence
    window.generateSymbolChallenge = function() {
        const symbols = [
            { name: 'up', keyCode: 38, symbol: '↑' },
            { name: 'down', keyCode: 40, symbol: '↓' },
            { name: 'left', keyCode: 37, symbol: '←' },
            { name: 'right', keyCode: 39, symbol: '→' }
        ];
        
        const state = window.challengeState;
        state.sequence = [];
        
        // Generate 6 random symbols
        for (let i = 0; i < 6; i++) {
            const randomIndex = Math.floor(Math.random() * symbols.length);
            state.sequence.push({...symbols[randomIndex]});
        }
        
        // Render the challenge symbols
        const challengeDiv = document.getElementById('symbol-challenge');
        if (challengeDiv) {
            challengeDiv.innerHTML = '';
            
            state.sequence.forEach((item, index) => {
                const symbolSpan = document.createElement('span');
                symbolSpan.className = 'symbol';
                symbolSpan.id = `challenge-symbol-${index}`;
                symbolSpan.textContent = item.symbol;
                challengeDiv.appendChild(symbolSpan);
            });
        }
        
        // Reset user input
        state.userInput = [];
        state.pressTimes = [];
        
        // Reset UI
        const submitBtn = document.getElementById('submit-login-btn') || 
                          document.getElementById('submit-register-btn') ||
                          document.getElementById('submit-review-btn');
        
        if (submitBtn) {
            submitBtn.disabled = true;
        }
        
        const challengeStatus = document.getElementById('challenge-status');
        if (challengeStatus) {
            challengeStatus.textContent = 'Press the arrow keys to match the sequence above';
        }
    };
    
    // Highlight the current symbol in the challenge
    window.highlightChallengeSymbol = function(index) {
        const symbols = document.querySelectorAll('#symbol-challenge .symbol');
        symbols.forEach(symbol => symbol.classList.remove('active'));
        
        if (index >= 0 && index < symbols.length) {
            const currentSymbol = document.getElementById(`challenge-symbol-${index}`);
            if (currentSymbol) {
                currentSymbol.classList.add('active');
            }
        }
    };
    
    // Reset all highlighted symbols in the challenge
    window.resetChallengeHighlights = function() {
        const symbols = document.querySelectorAll('#symbol-challenge .symbol');
        symbols.forEach(symbol => symbol.classList.remove('active'));
    };
    
    // Submit handlers for different pages
    window.submitReview = function() {
        const state = window.challengeState;
        
        if (state.userInput.length !== state.sequence.length || 
            state.userInput.some((key, index) => key !== state.sequence[index].keyCode)) {
            showNotification('Please complete the symbol challenge by pressing the correct arrow keys.');
            return;
        }
        
        const reviewText = document.getElementById('review-text').value;
        if (!reviewText.trim()) {
            showNotification('Please enter a review before submitting.');
            return;
        }
        
        // Get the game ID from URL or hidden field
        const gameId = new URLSearchParams(window.location.search).get('id') || 
                       document.getElementById('game-id')?.value;
        
        fetch(`${window.baseUrl}/api.php?action=getGameDetails&id=${gameId}`)
            .then(response => response.json())
            .then(game => {
                if (game.error) {
                    showNotification('Error: ' + game.error);
                    return;
                }
                
                const reviewData = {
                    gameId: gameId,
                    gameName: game.name,
                    reviewText: reviewText
                };
                
                fetch(`${window.baseUrl}/api.php?action=add`, {
                    method: 'POST',
                    headers: { 
                        'X-CSRF-Token': window.getCsrfToken() || ""
                    },
                    body: JSON.stringify(reviewData)
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            showNotification('Error: ' + data.error);
                        } else {
                            showNotification('Review submitted successfully!');
                            document.getElementById('review-text').value = '';
                            window.generateSymbolChallenge();
                            
                            // Reload reviews if function exists
                            if (typeof loadReviews === 'function') {
                                loadReviews(1);
                            }
                        }
                    })
                    .catch(error => showNotification('Error: ' + error.message));
            })
            .catch(error => showNotification('Error: ' + error.message));
    };
})();