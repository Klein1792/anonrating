/**
 * Game Page JavaScript
 * Handles game details, reviews, and voting functionality
 */
(function() {
    // Track authentication state
    let currentUser = null;
    let isAuthenticated = false;
    let isAnonymous = true;
    
    // Challenge sequence state
    let challengeState = {
        sequence: [],
        userInput: [],
        pressTimes: []
    };
    
    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        // Get the game ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        const gameId = urlParams.get('id');
        
        if (!gameId) {
            showNotification('No game ID specified.');
            return;
        }
        
        // Initialize auth state first
        initAuthState().then(() => {
            loadGameDetails(gameId);
            loadReviews(1, gameId);
            setupReviewForm(gameId);
            setupChallengeSequence(); // Initialize the challenge sequence
        });
        
        // Listen for auth state changes
        document.addEventListener('authStateChanged', (event) => {
            const { isAuthenticated: newIsAuth, isAnonymous: newIsAnon, user } = event.detail;
            
            // Update our local state
            isAuthenticated = newIsAuth;
            isAnonymous = newIsAnon;
            currentUser = user;
            
            console.log('Auth state changed:', { isAuthenticated, isAnonymous, currentUser });
            
            // Update UI elements that depend on auth state
            updateAuthDependentUI(gameId);
            
            // Specifically update the display name dropdown
            updateDisplayNameDropdown();
        });
    });
    
    // Setup the challenge sequence UI
    function setupChallengeSequence() {
        // Add challenge UI to review form
        const reviewForm = document.getElementById('review-form');
        if (!reviewForm) return;
        
        // Create challenge container if it doesn't exist
        if (!document.getElementById('review-challenge-container')) {
            const challengeContainer = document.createElement('div');
            challengeContainer.id = 'review-challenge-container';
            challengeContainer.className = 'review-challenge-container';
            challengeContainer.innerHTML = `
                <p>Complete the sequence to enable submit:</p>
                <div id="symbol-challenge" class="symbols"></div>
                <p id="challenge-status" class="challenge-status">Press the arrow keys to match the sequence above</p>
            `;
            
            // Insert before submit button
            const submitBtnGroup = reviewForm.querySelector('.form-group:last-child');
            reviewForm.insertBefore(challengeContainer, submitBtnGroup);
        }
        
        // Disable submit button initially
        const submitBtn = reviewForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
        }
        
        // Add keydown listener for challenge sequence - THIS IS THE KEY CHANGE
        document.addEventListener('keydown', handleKeyDown);
        
        // Generate initial challenge
        generateSymbolChallenge();
    }
    
    // Generate a random symbol challenge sequence
    function generateSymbolChallenge() {
        const symbols = [
            { name: 'up', keyCode: 38, symbol: '↑' },
            { name: 'down', keyCode: 40, symbol: '↓' },
            { name: 'left', keyCode: 37, symbol: '←' },
            { name: 'right', keyCode: 39, symbol: '→' }
        ];
        
        challengeState.sequence = [];
        
        // Generate 6 random symbols
        for (let i = 0; i < 6; i++) {
            const randomIndex = Math.floor(Math.random() * symbols.length);
            challengeState.sequence.push({...symbols[randomIndex]});
        }
        
        // Render the challenge symbols
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
        
        // Reset user input
        challengeState.userInput = [];
        challengeState.pressTimes = [];
        
        // Reset UI
        const submitBtn = document.getElementById('review-form').querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
        }
        
        const challengeStatus = document.getElementById('challenge-status');
        if (challengeStatus) {
            challengeStatus.textContent = 'Press the arrow keys to match the sequence above';
            challengeStatus.style.color = ''; // Reset color
        }
        
        // Reset highlight
        resetChallengeHighlights();
    }
    
    // Handle keydown events for challenge
    function handleKeyDown(e) {
        // Only handle arrow keys
        const arrowKeyCodes = [37, 38, 39, 40]; // left, up, right, down
        if (!arrowKeyCodes.includes(e.keyCode)) return;
        
        // Only handle if we have an active challenge and review form is visible
        if (!challengeState.sequence.length || !document.getElementById('review-form')) return;
        
        // Get current position in sequence
        const currentPos = challengeState.userInput.length;
        
        // Check if we've completed the sequence
        if (currentPos >= challengeState.sequence.length) return;
        
        // Record input and timing
        challengeState.userInput.push(e.keyCode);
        challengeState.pressTimes.push(Date.now());
        
        // Highlight the current symbol
        highlightChallengeSymbol(currentPos);
        
        // Check if the input matches the expected key
        if (e.keyCode !== challengeState.sequence[currentPos].keyCode) {
            // Input doesn't match, reset
            resetChallengeSequence("Incorrect sequence! Try again.");
            return;
        }
        
        // If we've completed the sequence correctly
        if (currentPos === challengeState.sequence.length - 1) {
            // Enable submit button
            const submitBtn = document.getElementById('review-form').querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
            }
            
            // Update status
            const challengeStatus = document.getElementById('challenge-status');
            if (challengeStatus) {
                challengeStatus.textContent = 'Sequence complete! You can now submit your review.';
                challengeStatus.style.color = '#00ff00';
            }
        }
    }
    
    // Reset challenge sequence after error
    function resetChallengeSequence(message) {
        // Reset user input
        challengeState.userInput = [];
        challengeState.pressTimes = [];
        
        // Update status
        const challengeStatus = document.getElementById('challenge-status');
        if (challengeStatus) {
            challengeStatus.textContent = message || 'Press the arrow keys to match the sequence above';
            challengeStatus.style.color = message ? '#ff0000' : '';
            
            // Reset status color after delay
            setTimeout(() => {
                challengeStatus.style.color = '';
            }, 2000);
        }
        
        // Reset highlights
        resetChallengeHighlights();
    }
    
    // Highlight the current symbol in the challenge
    function highlightChallengeSymbol(index) {
        const symbols = document.querySelectorAll('#symbol-challenge .symbol');
        
        // First reset all
        resetChallengeHighlights();
        
        // Then highlight current
        if (index >= 0 && index < symbols.length) {
            const currentSymbol = document.getElementById(`challenge-symbol-${index}`);
            if (currentSymbol) {
                currentSymbol.classList.add('active');
            }
        }
    }
    
    // Reset all highlighted symbols in the challenge
    function resetChallengeHighlights() {
        const symbols = document.querySelectorAll('#symbol-challenge .symbol');
        symbols.forEach(symbol => symbol.classList.remove('active'));
    }
    
    // Initialize authentication state
    async function initAuthState() {
        // Check if gameRating.auth is available from auth-client.js
        if (window.gameRating && window.gameRating.auth) {
            isAuthenticated = window.gameRating.auth.isAuthenticated();
            isAnonymous = window.gameRating.auth.isAnonymous();
            currentUser = window.gameRating.auth.getCurrentUser();
            return true;
        }
        
        // Fallback: Check JWT token
        const token = getCookie('access_token');
        
        if (token) {
            try {
                // Try to parse the token to get user info
                const tokenData = parseJwt(token);
                if (tokenData && tokenData.user_id) {
                    isAuthenticated = true;
                    isAnonymous = false;
                    currentUser = {
                        user_id: tokenData.user_id,
                        username: tokenData.username || 'User',
                        is_admin: tokenData.is_admin || false,
                        is_moderator: tokenData.is_moderator || false
                    };
                    return true;
                }
            } catch (e) {
                console.error('Error parsing JWT token:', e);
            }
        }
        
        // Try to get anonymous token
        const anonymousToken = getCookie('anonymous_token');
        isAnonymous = !!anonymousToken;
        return false;
    }
    
    // Update UI elements that depend on authentication state
    function updateAuthDependentUI(gameId) {
        // Update review form visibility
        const reviewForm = document.getElementById('review-form');
        const anonymousNameField = document.getElementById('anonymous-name-field');
        
        if (reviewForm) {
            // Update the display name dropdown with current user info
            updateDisplayNameDropdown();
            
            // Update form fields if authenticated
            if (isAuthenticated && currentUser) {
                const usernameField = document.getElementById('review-username');
                if (usernameField && currentUser.username) {
                    usernameField.value = currentUser.username;
                    usernameField.disabled = true;
                }
            }
        }
        
        // Refresh vote buttons
        if (gameId) {
            checkUserVote(gameId);
        }
        
        // Refresh review votes
        document.querySelectorAll('[id^="review-"]').forEach(reviewElement => {
            const reviewId = reviewElement.id.replace('review-', '');
            if (reviewId) {
                checkReviewVote(reviewId);
            }
        });
    }
    
    // Fix the loadGameDetails function to handle both response formats
    function loadGameDetails(gameId) {
        fetch(`${window.baseUrl}/api.php?action=getGameDetails&id=${gameId}`)
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        throw new Error('Response is not JSON: ' + text);
                    });
                }
                return response.json();
            })
            .then(data => {
                // Handle both response formats - with or without success wrapper
                let game;
                if (data.success === true && data.game) {
                    // New format with success wrapper
                    game = data.game;
                } else if (data.name) {
                    // Old format where data is directly the game object
                    game = data;
                } else if (data.error) {
                    throw new Error(data.error);
                } else {
                    throw new Error('Failed to load game details');
                }
                
                if (!game) {
                    throw new Error('Game details not found');
                }

                // Set cover image
                const coverUrl = game.cover_url || (game.cover && game.cover.url ? 'https:' + game.cover.url : '');

                // Try to get a higher resolution image if possible
                let highResCoverUrl = coverUrl;
                if (game.cover && game.cover.url && game.cover.url.includes('t_thumb')) {
                    highResCoverUrl = 'https:' + game.cover.url.replace('t_thumb', 't_cover_big');
                }

                // Create image element
                const imgElement = document.getElementById('game-cover');
                imgElement.onload = function() {
                    // If image dimensions are small, add special class
                    if (this.naturalWidth < 200 || this.naturalHeight < 200) {
                        this.classList.add('small-image');
                    }
                };

                // Set the source to the high-res version first
                imgElement.src = highResCoverUrl || `${window.baseUrl}/images/default-image.jpg`;
                imgElement.alt = game.name;

                // Fallback for errors
                imgElement.onerror = function() {
                    // If high-res fails, try original URL
                    if (highResCoverUrl !== coverUrl && coverUrl) {
                        this.src = coverUrl;
                        this.onerror = function() {
                            // If that also fails, use default image
                            this.src = `${window.baseUrl}/images/default-image.jpg`;
                            this.onerror = null;
                        };
                    } else {
                        // If no original URL or it's the same, go straight to default
                        this.src = `${window.baseUrl}/images/default-image.jpg`;
                        this.onerror = null;
                    }
                };

                // Rest of the function remains the same...
                // Set game title
                document.getElementById('game-title').textContent = game.name;

                // Set up vote buttons
                const voteButtons = document.getElementById('vote-buttons');
                voteButtons.innerHTML = `
                    <button id="like-button-${gameId}" onclick="likeGame(${gameId})">LIKE</button>
                    <button id="dislike-button-${gameId}" onclick="dislikeGame(${gameId})">DISLIKE</button>
                `;
                updateGameVotes(gameId);
                checkUserVote(gameId);

                // Set game rating
                const ratingDiv = document.getElementById('game-rating');
                ratingDiv.textContent = game.rating ? 
                    `IGDB Rating: ${Math.round(game.rating)}/100` : 
                    'IGDB Rating: N/A';
                
                // Add user rating if available
                if (game.user_rating && game.user_rating.count > 0) {
                    ratingDiv.textContent += ` | User Rating: ${game.user_rating.avg}/10 (${game.user_rating.count} ratings)`;
                }

                // Set up trailer
                const trailerDiv = document.getElementById('game-trailer');
                if (game.trailer) {
                    trailerDiv.innerHTML = `
                        <iframe 
                            width="100%" 
                            height="100%" 
                            src="${game.trailer}" 
                            frameborder="0" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                            allowfullscreen>
                        </iframe>`;
                } else {
                    trailerDiv.innerHTML = '<p>No trailer available.</p>';
                }

                // Set game description
                document.getElementById('game-description').textContent = game.summary || 'No summary available.';

                // Update game meta section
                updateGameMetaSection(game);
            })
            .catch(error => {
                console.error('Error loading game details:', error);
                document.getElementById('game-title').innerHTML = `<p style="color: #ff00ff;">Error: ${error.message}</p>`;
            });
    }
    
    // Update game meta section with release date, developer, etc.
    function updateGameMetaSection(gameData) {
        const metaItems = document.querySelectorAll('.game-meta .meta-item');
        
        // Format release date if available
        const releaseDate = gameData.first_release_date ? 
            new Date(gameData.first_release_date * 1000).toLocaleDateString() : 'N/A';
        
        // Format genres - handle both array of objects and array of strings
        let genreText = 'N/A';
        if (Array.isArray(gameData.genres)) {
            // Check if we have objects with name property or just strings
            if (gameData.genres.length > 0 && typeof gameData.genres[0] === 'object' && gameData.genres[0].name) {
                genreText = gameData.genres.map(g => g.name).join(', ');
            } else if (gameData.genres.length > 0) {
                genreText = gameData.genres.join(', ');
            }
        }
        
        // Format platforms - handle both array of objects and array of strings
        let platformText = 'N/A';
        if (Array.isArray(gameData.platforms)) {
            // Check if we have objects with name property or just strings
            if (gameData.platforms.length > 0 && typeof gameData.platforms[0] === 'object' && gameData.platforms[0].name) {
                platformText = gameData.platforms.map(p => p.name).join(', ');
            } else if (gameData.platforms.length > 0) {
                platformText = gameData.platforms.join(', ');
            }
        }
        
        // Set up label/value pairs for the meta items
        const metaValues = [
            { label: 'Release Date', value: releaseDate },
            { label: 'Developer', value: gameData.developer || 'N/A' },
            { label: 'Publisher', value: gameData.publisher || 'N/A' },
            { label: 'Genres', value: genreText },
            { label: 'Platforms', value: platformText },
            { label: 'Tags', value: gameData.tags && gameData.tags.length > 0 ? gameData.tags.join(', ') : 'N/A' }
        ];
        
        // Add websites if available
        if (gameData.websites && gameData.websites.length > 0) {
            const websiteLinks = gameData.websites.map(website => {
                // Handle both object format and string format
                if (typeof website === 'object' && website.url) {
                    const url = website.url;
                    const label = website.category || new URL(url).hostname;
                    return `<a href="${url}" target="_blank" rel="noopener noreferrer">${label}</a>`;
                } else if (typeof website === 'string') {
                    return `<a href="${website}" target="_blank" rel="noopener noreferrer">${new URL(website).hostname}</a>`;
                }
                return '';
            }).filter(link => link).join(', ');
            
            if (websiteLinks) {
                metaValues.push({ label: 'Websites', value: websiteLinks });
            }
        }
        
        // Update each meta item with its label/value
        metaItems.forEach((item, index) => {
            if (index < metaValues.length) {
                item.innerHTML = `<strong>${metaValues[index].label}:</strong> ${metaValues[index].value}`;
            }
        });
    }
    
    // Set up the review form with appropriate handlers
    function setupReviewForm(gameId) {
        const reviewForm = document.getElementById('review-form');
        if (!reviewForm) return;
        
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitReview(gameId);
        });
        
        // Set up the display name dropdown
        updateDisplayNameDropdown();
    }
    
    // Add this new function to update the dropdown
    function updateDisplayNameDropdown() {
        const anonymousNameField = document.getElementById('anonymous-name-field');
        if (!anonymousNameField) {
            console.log('Cannot find anonymous-name-field element');
            return;
        }
        
        // Get auth state from multiple sources to ensure we have the correct data
        const gameRatingAuth = window.gameRating?.auth;
        
        console.log('Auth state from gameRating.auth:', {
            isAuthenticated: gameRatingAuth?.isAuthenticated?.() || false,
            isAnonymous: gameRatingAuth?.isAnonymous?.() || true,
            currentUser: gameRatingAuth?.getCurrentUser?.() || null
        });
        
        console.log('Local auth variables:', {
            isAuthenticated: isAuthenticated,
            isAnonymous: isAnonymous,
            currentUser: currentUser
        });
        
        // Get username from any available source
        let username = null;
        if (gameRatingAuth?.getCurrentUser?.()) {
            username = gameRatingAuth.getCurrentUser().username;
        } else if (currentUser && currentUser.username) {
            username = currentUser.username;
        } else {
            // Try to parse from JWT as fallback
            const token = getCookie('access_token');
            if (token) {
                try {
                    const tokenData = parseJwt(token);
                    if (tokenData && tokenData.username) {
                        username = tokenData.username;
                    }
                } catch (e) {
                    console.error('Error parsing JWT token:', e);
                }
            }
        }
        
        console.log('Username for dropdown:', username);
        
        // Create dropdown HTML
        const dropdownHTML = `
            <label for="anonymous-name">Post as:</label>
            <select id="anonymous-name" class="form-control">
                <option value="Anonymous" selected>Anonymous</option>
                ${username ? `<option value="${username}">${username}</option>` : ''}
            </select>
        `;
        
        // Update the field
        anonymousNameField.innerHTML = dropdownHTML;
        console.log('Updated dropdown HTML:', anonymousNameField.innerHTML);
        
        // Ensure the dropdown is visible for anonymous users and hidden for authenticated users
        const authenticated = gameRatingAuth?.isAuthenticated?.() || isAuthenticated || false;
        anonymousNameField.style.display = authenticated ? 'none' : 'block';
        console.log('Dropdown visibility:', authenticated ? 'hidden (user is authenticated)' : 'visible (user is anonymous)');
    }
    
    // Submit a new review
    function submitReview(gameId) {
        // Check if challenge is completed - this is the critical validation
        if (challengeState.userInput.length !== challengeState.sequence.length) {
            showNotification('Please complete the arrow key sequence challenge first', 'error');
            return;
        }
        
        // Verify each key press matches the sequence
        for (let i = 0; i < challengeState.sequence.length; i++) {
            if (challengeState.userInput[i] !== challengeState.sequence[i].keyCode) {
                showNotification('Please complete the arrow key sequence challenge correctly', 'error');
                return;
            }
        }
        
        const title = document.getElementById('review-title').value;
        const content = document.getElementById('review-content').value;
        const rating = parseInt(document.getElementById('review-rating').value);
        
        // Get display name from dropdown instead of text input
        let displayName = 'Anonymous';
        if (!isAuthenticated) {
            const displayNameSelect = document.getElementById('anonymous-name');
            if (displayNameSelect) {
                displayName = displayNameSelect.value;
            }
        } else if (currentUser && currentUser.username) {
            displayName = currentUser.username;
        }
        
        // Basic validation
        if (!title.trim() || !content.trim() || isNaN(rating) || rating < 1 || rating > 10) {
            showNotification('Please fill in all required fields and provide a rating between 1 and 10.', 'error');
            return;
        }
        
        // Prepare the review data
        const reviewData = {
            gameId: parseInt(gameId),
            title: title,
            content: content,
            rating: rating
        };
        
        // Add display name for anonymous users
        if (!isAuthenticated) {
            reviewData.displayName = displayName;
        }
        
        // Show loading state
        const submitBtn = document.querySelector('#review-form button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Submitting...';
        }
        
        // Use the fetchWithAuth function from auth-client.js if available
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || fetchWithAuth;
        
        fetchFn(`${window.baseUrl}/api.php?action=addReview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(reviewData)
        })
        .then(response => response.json())
        .then(data => {
            // Reset button state
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Submit Review';
            }
            
            if (data.success) {
                // Clear the form
                document.getElementById('review-title').value = '';
                document.getElementById('review-content').value = '';
                document.getElementById('review-rating').value = '5';
                
                // Show success message
                showNotification('Review submitted successfully!', 'success');
                
                // Reset and regenerate challenge
                generateSymbolChallenge();
                
                // Reload reviews
                loadReviews(1, gameId);
            } else {
                showNotification('Error: ' + (data.error || 'Failed to submit review'), 'error');
            }
        })
        .catch(error => {
            console.error('Error submitting review:', error);
            
            // Reset button state
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Submit Review';
            }
            
            showNotification('An error occurred while submitting your review.', 'error');
        });
    }
    
    // Fix the loadReviews function to be more robust with error handling
    window.loadReviews = function(page, gameId) {
        // If gameId is not provided, get from URL
        if (!gameId) {
            const urlParams = new URLSearchParams(window.location.search);
            gameId = urlParams.get('id');
        }
        
        const limit = 5; // Reviews per page
        
        fetch(`${window.baseUrl}/api.php?action=getReviewsByGame&gameId=${gameId}&page=${page}&limit=${limit}`)
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.toLowerCase().includes('application/json')) {
                    return response.text().then(text => {
                        throw new Error('Response is not JSON: ' + text);
                    });
                }
                return response.json();
            })
            .then(data => {
                // Check for error in the data
                if (data.error) {
                    throw new Error(data.error || 'Failed to load reviews');
                }
                
                // Either expect a success property or just have reviews directly
                const reviews = data.reviews || (data.success ? data.reviews : []);
                const pagination = data.pagination || {
                    current_page: page,
                    total_pages: 1
                };
                
                const reviewsList = document.getElementById('reviews-list');
                reviewsList.innerHTML = '';
                
                if (!reviews || reviews.length === 0) {
                    reviewsList.innerHTML = '<p>No reviews yet. Be the first to review this game!</p>';
                    return;
                }
                
                reviews.forEach(review => {
                    renderReview(review, reviewsList);
                });
                
                // Set up pagination
                setupPagination(pagination.current_page, pagination.total_pages, gameId);
            })
            .catch(error => {
                console.error('Error loading reviews:', error);
                document.getElementById('reviews-list').innerHTML = `<p class="error">Error: ${error.message}</p>`;
            });
    };
    
    // Render an individual review
    function renderReview(review, container) {
        const reviewDiv = document.createElement('div');
        reviewDiv.className = 'review-item';
        reviewDiv.id = `review-${review.id}`;
        
        // Format the date
        const reviewDate = new Date(review.created_at).toLocaleDateString();
        const wasUpdated = review.created_at !== review.updated_at;
        const updatedText = wasUpdated ? ` (edited ${new Date(review.updated_at).toLocaleDateString()})` : '';
        
        // Build user badge based on user type
        let userBadge = '';
        if (review.is_admin) {
            userBadge = '<span class="admin-badge">Admin</span>';
        } else if (review.is_moderator) {
            userBadge = '<span class="mod-badge">Moderator</span>';
        } else if (!review.is_anonymous) {
            userBadge = '<span class="user-badge">User</span>';
        }
        
        // Create stars based on rating
        const ratingStars = '★'.repeat(review.rating) + '☆'.repeat(10 - review.rating);
        
        reviewDiv.innerHTML = `
            <div class="review-header">
                <div class="review-author">
                    ${review.display_name} ${userBadge}
                </div>
                <div class="review-date">${reviewDate}${updatedText}</div>
            </div>
            <div class="review-rating">${ratingStars} (${review.rating}/10)</div>
            <h4 class="review-title">${review.title}</h4>
            <div class="review-content">${review.content}</div>
            <div class="review-metadata">
                <div class="review-votes">
                    <span id="review-helpful-${review.id}">${review.helpful_votes} found this helpful</span> | 
                    <span id="review-unhelpful-${review.id}">${review.not_helpful_votes} found this unhelpful</span>
                </div>
                <div class="review-vote-buttons">
                    <button id="helpful-button-${review.id}" onclick="voteReviewHelpful(${review.id})">Helpful</button>
                    <button id="unhelpful-button-${review.id}" onclick="voteReviewUnhelpful(${review.id})">Not Helpful</button>
                </div>
            </div>
        `;
        
        // Add edit/delete buttons if the user can modify this review
        if (review.can_edit || review.can_delete) {
            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'review-actions';
            
            if (review.can_edit) {
                const editBtn = document.createElement('button');
                editBtn.className = 'edit-review-btn';
                editBtn.textContent = 'Edit';
                editBtn.onclick = () => editReview(review.id);
                actionsDiv.appendChild(editBtn);
            }
            
            if (review.can_delete) {
                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'delete-review-btn';
                deleteBtn.textContent = 'Delete';
                deleteBtn.onclick = () => deleteReview(review.id);
                actionsDiv.appendChild(deleteBtn);
            }
            
            reviewDiv.appendChild(actionsDiv);
        }
        
        container.appendChild(reviewDiv);
        
        // Check if the current user has already voted on this review
        checkReviewVote(review.id);
    }
    
    // Game voting functions
    window.likeGame = function(gameId) {
        // Use the fetchWithAuth function from auth-client.js if available
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || fetchWithAuth;
        
        fetchFn(`${window.baseUrl}/api.php?action=voteGame`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ gameId: gameId, vote: 1 })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Server error: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update UI
                updateGameVotes(gameId);
                checkUserVote(gameId);
                showNotification('Vote recorded!');
            } else {
                showNotification('Error: ' + (data.error || 'Failed to record vote'));
            }
        })
        .catch(error => {
            console.error('Error voting:', error);
            showNotification('An error occurred while voting.');
        });
    };
    
    window.dislikeGame = function(gameId) {
        // Use the fetchWithAuth function from auth-client.js if available
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || fetchWithAuth;
        
        fetchFn(`${window.baseUrl}/api.php?action=voteGame`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ gameId: gameId, vote: 0 })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Server error: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update UI
                updateGameVotes(gameId);
                checkUserVote(gameId);
                showNotification('Vote recorded!');
            } else {
                showNotification('Error: ' + (data.error || 'Failed to record vote'));
            }
        })
        .catch(error => {
            console.error('Error voting:', error);
            showNotification('An error occurred while voting.');
        });
    };
    
    // Review voting functions
    window.voteReviewHelpful = function(reviewId) {
        // Use the fetchWithAuth function from auth-client.js if available
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || fetchWithAuth;
        
        fetchFn(`${window.baseUrl}/api.php?action=voteReview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ reviewId: reviewId, isHelpful: true })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI
                document.getElementById(`review-helpful-${reviewId}`).textContent = 
                    `${data.helpful_votes} found this helpful`;
                document.getElementById(`review-unhelpful-${reviewId}`).textContent = 
                    `${data.not_helpful_votes} found this unhelpful`;
                checkReviewVote(reviewId);
                showNotification('Vote recorded!');
            } else {
                showNotification('Error: ' + (data.error || 'Failed to record vote'));
            }
        })
        .catch(error => {
            console.error('Error voting on review:', error);
            showNotification('An error occurred while voting.');
        });
    };
    
    window.voteReviewUnhelpful = function(reviewId) {
        // Use the fetchWithAuth function from auth-client.js if available
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || fetchWithAuth;
        
        fetchFn(`${window.baseUrl}/api.php?action=voteReview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ reviewId: reviewId, isHelpful: false })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI
                document.getElementById(`review-helpful-${reviewId}`).textContent = 
                    `${data.helpful_votes} found this helpful`;
                document.getElementById(`review-unhelpful-${reviewId}`).textContent = 
                    `${data.not_helpful_votes} found this unhelpful`;
                checkReviewVote(reviewId);
                showNotification('Vote recorded!');
            } else {
                showNotification('Error: ' + (data.error || 'Failed to record vote'));
            }
        })
        .catch(error => {
            console.error('Error voting on review:', error);
            showNotification('An error occurred while voting.');
        });
    };
    
    // Review modification functions
    window.editReview = function(reviewId) {
        const reviewElement = document.getElementById(`review-${reviewId}`);
        if (!reviewElement) return;
        
        const titleElement = reviewElement.querySelector('.review-title');
        const contentElement = reviewElement.querySelector('.review-content');
        const ratingStars = reviewElement.querySelector('.review-rating');
        
        if (!titleElement || !contentElement || !ratingStars) return;
        
        const currentTitle = titleElement.textContent;
        const currentContent = contentElement.textContent;
        const currentRating = parseInt(ratingStars.textContent.match(/\((\d+)\/10\)/)[1]);
        
        // Replace with edit form
        const editForm = document.createElement('form');
        editForm.id = `edit-review-form-${reviewId}`;
        editForm.className = 'edit-review-form';
        editForm.innerHTML = `
            <div class="form-group">
                <label for="edit-title-${reviewId}">Title:</label>
                <input type="text" id="edit-title-${reviewId}" value="${escapeHtml(currentTitle)}" required>
            </div>
            <div class="form-group">
                <label for="edit-content-${reviewId}">Review:</label>
                <textarea id="edit-content-${reviewId}" rows="5" required>${escapeHtml(currentContent)}</textarea>
            </div>
            <div class="form-group">
                <label for="edit-rating-${reviewId}">Rating:</label>
                <select id="edit-rating-${reviewId}">
                    ${Array.from({ length: 10 }, (_, i) => i + 1)
                        .map(num => `<option value="${num}" ${num === currentRating ? 'selected' : ''}>${num}/10</option>`)
                        .join('')}
                </select>
            </div>
            <div class="form-buttons">
                <button type="submit">Save Changes</button>
                <button type="button" class="cancel-edit-btn">Cancel</button>
            </div>
        `;
        
        // Hide review content
        titleElement.style.display = 'none';
        contentElement.style.display = 'none';
        ratingStars.style.display = 'none';
        
        // Insert edit form
        contentElement.parentNode.insertBefore(editForm, contentElement);
        
        // Add event listeners
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const updatedTitle = document.getElementById(`edit-title-${reviewId}`).value;
            const updatedContent = document.getElementById(`edit-content-${reviewId}`).value;
            const updatedRating = parseInt(document.getElementById(`edit-rating-${reviewId}`).value);
            
            submitEditedReview(reviewId, updatedTitle, updatedContent, updatedRating);
        });
        
        editForm.querySelector('.cancel-edit-btn').addEventListener('click', function() {
            // Remove form and show original content
            editForm.remove();
            titleElement.style.display = '';
            contentElement.style.display = '';
            ratingStars.style.display = '';
        });
    };
    
    function submitEditedReview(reviewId, title, content, rating) {
        if (!title.trim() || !content.trim() || isNaN(rating) || rating < 1 || rating > 10) {
            showNotification('Please fill in all required fields with valid values.');
            return;
        }
        
        // Use the fetchWithAuth function from auth-client.js if available
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || fetchWithAuth;
        
        fetchFn(`${window.baseUrl}/api.php?action=editReview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                reviewId: reviewId,
                title: title,
                content: content,
                rating: rating
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Review updated successfully!');
                
                // Reload reviews to reflect changes
                const urlParams = new URLSearchParams(window.location.search);
                const gameId = urlParams.get('id');
                loadReviews(1, gameId);
            } else {
                showNotification('Error: ' + (data.error || 'Failed to update review'));
            }
        })
        .catch(error => {
            console.error('Error updating review:', error);
            showNotification('An error occurred while updating your review.');
        });
    }
    
    window.deleteReview = function(reviewId) {
        if (!confirm("Are you sure you want to delete this review? This action cannot be undone.")) {
            return;
        }
        
        // Use the fetchWithAuth function from auth-client.js if available
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || fetchWithAuth;
        
        fetchFn(`${window.baseUrl}/api.php?action=deleteReview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ reviewId: reviewId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Review deleted successfully!');
                
                // Reload reviews
                const urlParams = new URLSearchParams(window.location.search);
                const gameId = urlParams.get('id');
                loadReviews(1, gameId);
            } else {
                showNotification('Error: ' + (data.error || 'Failed to delete review'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while deleting your review.');
        });
    };

    // Helper functions
    function updateGameVotes(gameId) {
        fetch(`${window.baseUrl}/api.php?action=getGameVotes&gameId=${gameId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Server error: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    console.error('Error fetching votes:', data.error);
                    return;
                }
                
                const voteDiv = document.getElementById('game-votes');
                if (voteDiv) {
                    const { likes, dislikes, total, approval } = data;
                    
                    if (total === 0) {
                        voteDiv.innerHTML = 'No votes yet';
                    } else {
                        voteDiv.innerHTML = `
                            <div class="vote-stats">
                                <div class="vote-bar">
                                    <div class="vote-fill" style="width: ${approval}%"></div>
                                </div>
                                <div class="vote-numbers">
                                    ${likes} likes | ${dislikes} dislikes (${approval}% positive)
                                </div>
                            </div>
                        `;
                    }
                }
            })
            .catch(error => console.error('Error fetching votes:', error));
    }

    function checkUserVote(gameId) {
        fetch(`${window.baseUrl}/api.php?action=checkUserVote&gameId=${gameId}`, {
            credentials: 'include'  // Important for cookies
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Server error: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            const likeButton = document.getElementById(`like-button-${gameId}`);
            const dislikeButton = document.getElementById(`dislike-button-${gameId}`);
            
            if (!likeButton || !dislikeButton) return;
            
            // Reset both buttons first
            likeButton.classList.remove('active');
            dislikeButton.classList.remove('active');
            
            if (data.success && data.hasVoted) {
                // User voted - highlight the correct button
                if (data.vote === 1) {
                    likeButton.classList.add('active');
                } else if (data.vote === 0) {
                    dislikeButton.classList.add('active');
                }
            }
        })
        .catch(error => console.error('Error checking user vote:', error));
    }
    
    window.checkReviewVote = function(reviewId) {
        fetch(`${window.baseUrl}/api.php?action=checkReviewVote&reviewId=${reviewId}`, {
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            const helpfulBtn = document.getElementById(`helpful-button-${reviewId}`);
            const unhelpfulBtn = document.getElementById(`unhelpful-button-${reviewId}`);
            
            if (!helpfulBtn || !unhelpfulBtn) return;
            
            // Reset both buttons first
            helpfulBtn.classList.remove('active');
            unhelpfulBtn.classList.remove('active');
            
            if (data.success && data.hasVoted) {
                if (data.isHelpful) {
                    helpfulBtn.classList.add('active');
                } else {
                    unhelpfulBtn.classList.add('active');
                }
            }
        })
        .catch(error => {
            console.error('Error checking review vote:', error);
        });
    };

    // Setup pagination for reviews
    function setupPagination(currentPage, totalPages, gameId) {
        const paginationDiv = document.getElementById('pagination');
        if (!paginationDiv) return;
        
        paginationDiv.innerHTML = '';
        
        if (totalPages <= 1) return;
        
        const ul = document.createElement('ul');
        ul.className = 'pagination';
        
        // Previous button
        if (currentPage > 1) {
            const prevLi = document.createElement('li');
            const prevLink = document.createElement('a');
            prevLink.href = '#';
            prevLink.innerHTML = '&laquo; Previous';
            prevLink.addEventListener('click', function(e) {
                e.preventDefault();
                window.loadReviews(currentPage - 1, gameId);
            });
            prevLi.appendChild(prevLink);
            ul.appendChild(prevLi);
        }
        
        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, startPage + 4);
        
        for (let i = startPage; i <= endPage; i++) {
            const li = document.createElement('li');
            li.className = i === currentPage ? 'active' : '';
            
            const a = document.createElement('a');
            a.href = '#';
            a.textContent = i;
            if (i !== currentPage) {
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.loadReviews(i, gameId);
                });
            }
            
            li.appendChild(a);
            ul.appendChild(li);
        }
        
        // Next button
        if (currentPage < totalPages) {
            const nextLi = document.createElement('li');
            const nextLink = document.createElement('a');
            nextLink.href = '#';
            nextLink.innerHTML = 'Next &raquo;';
            nextLink.addEventListener('click', function(e) {
                e.preventDefault();
                window.loadReviews(currentPage + 1, gameId);
            });
            nextLi.appendChild(nextLink);
            ul.appendChild(nextLi);
        }
        
        paginationDiv.appendChild(ul);
    }
    
    // Show a notification message
    function showNotification(message, type = 'info') {
        // Check if we need to create the notification container
        let container = document.getElementById('notifications-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notifications-container';
            container.className = 'notifications-container';
            document.body.appendChild(container);
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">${message}</div>
            <button class="notification-close">&times;</button>
        `;
        
        // Add close button functionality
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            notification.classList.add('hiding');
            setTimeout(() => notification.remove(), 300);
        });
        
        // Add to container
        container.appendChild(notification);
        
        // Animate in
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Auto-remove after delay
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.add('hiding');
                setTimeout(() => {
                    if (notification.parentNode) notification.remove();
                }, 300);
            }
        }, 5000);
    }
    
    // Parse JWT token
    function parseJwt(token) {
        try {
            const base64Url = token.split('.')[1];
            const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
            const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            
            return JSON.parse(jsonPayload);
        } catch (e) {
            console.error('Error parsing JWT token:', e);
            return null;
        }
    }
    
    // Get cookie by name
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
        return match ? decodeURIComponent(match[3]) : null;
    }
    
    // Escape HTML to prevent XSS when inserting user content
    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    // Fallback fetchWithAuth function if the auth-client.js one isn't available
    function fetchWithAuth(url, options = {}) {
        // Get token from cookie or localStorage
        const token = getCookie('access_token') || localStorage.getItem('jwt_token');
        
        // Clone options to avoid modifying the original
        const fetchOptions = { ...options };
        fetchOptions.headers = { ...fetchOptions.headers } || {};
        
        // Add auth header if token exists
        if (token) {
            fetchOptions.headers['Authorization'] = `Bearer ${token}`;
        }
        
        // Add credentials for cookies
        fetchOptions.credentials = 'same-origin';
        
        return fetch(url, fetchOptions);
    }
})();