/**
 * Game Page JavaScript
 * Handles game details, reviews, and voting functionality
 */
document.addEventListener('DOMContentLoaded', () => {
    // Explicitly sync with auth-client.js if it's loaded
    if (window.gameRating && window.gameRating.auth) {
        currentUser = window.gameRating.auth.getCurrentUser();
        isAuthenticated = window.gameRating.auth.isAuthenticated();
        isAnonymous = window.gameRating.auth.isAnonymous();
        console.log("Auth synchronized from gameRating.auth:", { currentUser, isAuthenticated, isAnonymous });
    }
});


///game-pag.js functions:
(function() {
    // Add this function near the top of your file (inside the IIFE)
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Track authentication state
    let challengeState = {
        sequence: [],
        userInput: [],
        pressTimes: []
    };
    
    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const gameId = urlParams.get('id');
        
        if (!gameId) {
            showNotification('No game ID specified.');
            return;
        }
        
        // Wait for auth-client.js to initialize
        const waitForAuth = async () => {
            if (window.gameRating && window.gameRating.auth && window.gameRating.auth.init) {
                await window.gameRating.auth.init();
                
                // Sync auth state variables directly from auth-client.js
                currentUser = window.gameRating.auth.getCurrentUser();
                isAuthenticated = window.gameRating.auth.isAuthenticated();
                isAnonymous = window.gameRating.auth.isAnonymous();
                console.log("Auth state initialized from auth-client.js:", { 
                    currentUser, isAuthenticated, isAnonymous 
                });
                
                return true;
            }
            
            // Fallback for when auth-client.js is not available
            console.warn("Auth client not available, using default anonymous state");
            currentUser = null;
            isAuthenticated = false;
            isAnonymous = true;
            return false;
        };
        
        waitForAuth().then(() => {
            loadGameDetails(gameId);
            loadReviews(1, gameId);
            setupReviewForm(gameId);
            setupChallengeSequence();
        });
        
        document.addEventListener('authStateChanged', (event) => {
            const { isAuthenticated: newIsAuth, isAnonymous: newIsAnon, user } = event.detail;
            
            isAuthenticated = newIsAuth;
            isAnonymous = newIsAnon;
            currentUser = user;
            
            updateAuthDependentUI(gameId);
            updateDisplayNameDropdown();
        });
        
        // Add special handling for the Top Rated tab to use user ratings by default
        const topRatedTab = document.getElementById('top-rated-tab');
        if (topRatedTab) {
            topRatedTab.addEventListener('shown.bs.tab', function() {
                const sortSelect = document.getElementById('sort-select');
                if (sortSelect && sortSelect.value !== 'avg_rating') {
                    sortSelect.value = 'avg_rating';
                    currentSort = 'avg_rating';
                    currentSortDirection = 'DESC';
                    const tabConfig = tabs['top-rated'];
                    loadTab(tabConfig, currentPage[tabConfig.id] || 1);
                }
            });
        }
    });
    
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
        generateSymbolChallenge();
    }
    
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
    
    function resetChallengeHighlights() {
        const symbols = document.querySelectorAll('#symbol-challenge .symbol');
        symbols.forEach(symbol => symbol.classList.remove('active'));
    }
    
    function updateAuthDependentUI(gameId) {
        const reviewForm = document.getElementById('review-form');
        
        if (reviewForm) {
            updateDisplayNameDropdown();
            
            if (isAuthenticated && currentUser) {
                const usernameField = document.getElementById('review-username');
                if (usernameField && currentUser.username) {
                    usernameField.value = currentUser.username;
                    usernameField.disabled = true;
                }
            }
        }
        
        if (gameId) {
            checkUserVote(gameId);
        }
        
        document.querySelectorAll('[id^="review-"]').forEach(reviewElement => {
            const reviewId = reviewElement.id.replace('review-', '');
            if (reviewId) {
                checkReviewVote(reviewId);
            }
        });
    }
    
    function loadGameDetails(gameId) {
        // Validate gameId
        if (!gameId || isNaN(gameId) || parseInt(gameId) <= 0) {
            console.error('Invalid gameId:', gameId);
            showNotification('Invalid game ID. Unable to load game details.', 'error');
            return;
        }
    
        // Try to get static content from cache first
        const cachedStaticData = window.GameCache?.getGameStatic(gameId);
        
        if (cachedStaticData) {
            console.log('Using cached static game data');
            // Render the static content immediately
            renderGameStatic(cachedStaticData);
            
            // Fetch only dynamic data
            fetch(`${window.baseUrl}/api.php?action=getGameDynamicData&id=${gameId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Failed to fetch dynamic data: ${response.status} ${response.statusText}`);
                    }
                    return response.text(); // Get raw text first to handle JSON parsing errors
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success && data.dynamic) {
                            // Update the dynamic parts of the page
                            updateGameDynamic(data.dynamic);
                        } else {
                            console.warn('Invalid dynamic data response:', data, 'Raw response:', text);
                            updateGameDynamic({
                                likes: 0,
                                dislikes: 0,
                                approval_percent: 0,
                                avg_rating: null,
                                review_count: 0
                            });
                            showNotification('Failed to load dynamic game data. Showing default values.', 'error');
                        }
                    } catch (e) {
                        console.error('Failed to parse dynamic data response as JSON:', e, 'Raw response:', text);
                        throw new Error('Invalid JSON response from server');
                    }
                })
                .catch(error => {
                    console.error('Error loading dynamic data:', error);
                    updateGameDynamic({
                        likes: 0,
                        dislikes: 0,
                        approval_percent: 0,
                        avg_rating: null,
                        review_count: 0
                    });
                    showNotification('Failed to load dynamic game data. Showing default values.', 'error');
                });
        } else {
            // Original code path for loading all game details
            fetch(`${window.baseUrl}/api.php?action=getGameDetails&id=${gameId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Failed to fetch game details: ${response.status} ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success && data.game && data.game.static && data.game.dynamic) {
                            const gameData = data.game;
                            
                            // Cache only the static part
                            if (window.GameCache && gameData.static) {
                                window.GameCache.saveGameStatic(gameId, gameData.static);
                            }
                            
                            // Render everything
                            renderGameStatic(gameData.static);
                            updateGameDynamic(gameData.dynamic);
                        } else {
                            console.warn('Invalid game details response:', data, 'Raw response:', text);
                            showNotification('Failed to load game details. Please try again later.', 'error');
                        }
                    } catch (e) {
                        console.error('Failed to parse game details response as JSON:', e, 'Raw response:', text);
                        throw new Error('Invalid JSON response from server');
                    }
                })
                .catch(error => {
                    console.error('Error loading game details:', error);
                    showNotification('Failed to load game details. Please try again later.', 'error');
                });
        }
    }
    
    function renderGameStatic(gameData) {
        document.title = `${gameData.name} - Game Details - Game Rater '98`;
                
        const titleElement = document.getElementById('game-title');
        if (titleElement) titleElement.textContent = gameData.name;
        
        const coverElement = document.getElementById('game-cover');
        if (coverElement) {
            const coverUrl = gameData.cover?.url 
                ? (gameData.cover.url.startsWith('https:') ? gameData.cover.url : 'https:' + gameData.cover.url).replace('t_thumb', 't_cover_big')
                : `${window.baseUrl}/images/default-image.jpg`;
            coverElement.src = coverUrl;
            coverElement.alt = gameData.name;
        }
        
        const descriptionElement = document.getElementById('game-description');
        if (descriptionElement) {
            const summary = gameData.summary || 'No description available.';
            
            // Create the description container with initial content
            const MAX_CHARS = 300; // Character limit for truncation
            const isLong = summary.length > MAX_CHARS;
            
            if (isLong) {
                // Create truncated and full versions
                const truncatedText = summary.substring(0, MAX_CHARS) + '...';
                
                // Set up the description with truncated text and show more button
                descriptionElement.innerHTML = `
                    <div class="description-content truncated">${truncatedText}</div>
                    <div class="description-content full" style="display: none;">${summary}</div>
                    <button class="description-toggle show-more">Show More</button>
                `;
                
                // Add event listener to the show more/less button
                const toggleButton = descriptionElement.querySelector('.description-toggle');
                toggleButton.addEventListener('click', function() {
                    const truncatedContent = descriptionElement.querySelector('.truncated');
                    const fullContent = descriptionElement.querySelector('.full');
                    
                    if (toggleButton.classList.contains('show-more')) {
                        // Show full description
                        truncatedContent.style.display = 'none';
                        fullContent.style.display = 'block';
                        toggleButton.textContent = 'Show Less';
                        toggleButton.classList.remove('show-more');
                        toggleButton.classList.add('show-less');
                    } else {
                        // Show truncated description
                        truncatedContent.style.display = 'block';
                        fullContent.style.display = 'none';
                        toggleButton.textContent = 'Show More';
                        toggleButton.classList.remove('show-less');
                        toggleButton.classList.add('show-more');
                    }
                });
            } else {
                // For short descriptions, just show the full text
                descriptionElement.textContent = summary;
            }
        }
        
        if (gameData.videos && gameData.videos.length > 0) {
            const trailerDiv = document.getElementById('game-trailer');
            if (trailerDiv) {
                trailerDiv.innerHTML = `
                    <iframe width="100%" height="100%" 
                        src="https://www.youtube.com/embed/${gameData.videos[0].video_id}" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                    </iframe>
                `;
            }
        } else {
            const trailerDiv = document.getElementById('game-trailer');
            if (trailerDiv) {
                trailerDiv.innerHTML = `<p>No trailer available</p>`;
            }
        }
        
        updateGameMetaSection(gameData);
        
        const voteButtonsDiv = document.getElementById('vote-buttons');
        if (voteButtonsDiv) {
            voteButtonsDiv.innerHTML = `
                <button id="like-button-${gameData.id}" onclick="likeGame(${gameData.id})">
                    Like
                </button>
                <button id="dislike-button-${gameData.id}" onclick="dislikeGame(${gameData.id})">
                    Dislike
                </button>
            `;
        }
        
        const ratingDiv = document.getElementById('game-rating');
        if (ratingDiv) {
            let igdbRatingText = 'IGDB Rating: N/A';
            if (gameData.rating) {
                const formattedRating = Math.round(gameData.rating);
                igdbRatingText = `IGDB Rating: ${formattedRating}/100`;
            }
            
            let userRatingText = 'anon Rating: N/A';
            if (gameData.avg_rating && gameData.avg_rating > 0) {
                const formattedAvgRating = gameData.avg_rating === 10 ? 10 : gameData.avg_rating.toFixed(1);
                userRatingText = `anon Rating: ${formattedAvgRating}/10 (${gameData.review_count} ${gameData.review_count === 1 ? 'review' : 'reviews'})`;
            }
            
            const likes = gameData.likes || 0;
            const dislikes = gameData.dislikes || 0;
            const totalVotes = likes + dislikes;
            const approvalPercent = gameData.approval_percent || 0;
            
            let approvalText = 'No votes yet';
            if (totalVotes > 0) {
                approvalText = `${Math.round(approvalPercent)}% (${likes}/${totalVotes}) approval`;
            }
            
            ratingDiv.innerHTML = `
                <div class="rating-container">
                    <div class="igdb-rating">${igdbRatingText}</div>
                    <div class="user-rating">${userRatingText}</div>
                    <div class="game-votes">${approvalText}</div>
                </div>
            `;
        }
        
        checkUserVote(gameData.id);
    }
    
    // update dynamic data
function updateGameDynamic(dynamicData) {
    const ratingDiv = document.getElementById('game-rating');
    if (ratingDiv) {
        // Get the existing IGDB rating if present
        const existingIgdbRating = ratingDiv.querySelector('.igdb-rating');
        const igdbRatingText = existingIgdbRating ? existingIgdbRating.textContent : 'IGDB Rating: N/A';
        
        let userRatingText = 'anon Rating: N/A';
        // Ensure avg_rating is a number and greater than 0
        const avgRating = dynamicData.avg_rating != null ? parseFloat(dynamicData.avg_rating) : null;
        if (avgRating != null && !isNaN(avgRating) && avgRating > 0) {
            userRatingText = `anon Rating: ${avgRating.toFixed(1)}/10 (${dynamicData.review_count} reviews)`;
        }
        
        const likes = dynamicData.likes || 0;
        const dislikes = dynamicData.dislikes || 0;
        const totalVotes = likes + dislikes;
        const approvalPercent = dynamicData.approval_percent || 0;
        
        let approvalText = 'No votes yet';
        if (totalVotes > 0) {
            approvalText = `${Math.round(approvalPercent)}% (${likes}/${totalVotes}) approval`;
        }
        
        ratingDiv.innerHTML = `
            <div class="rating-container">
                <div class="igdb-rating">${igdbRatingText}</div>
                <div class="user-rating">${userRatingText}</div>
                <div class="game-votes">${approvalText}</div>
            </div>
        `;
    }
    
    checkUserVote(dynamicData.id);
}
    
    function updateGameMetaSection(gameData) {
        const metaItems = document.querySelectorAll('.game-meta .meta-item');
        
        const releaseDate = gameData.first_release_date ? 
            new Date(gameData.first_release_date * 1000).toLocaleDateString() : 'N/A';
        
        let genreText = 'N/A';
        if (Array.isArray(gameData.genres)) {
            if (gameData.genres.length > 0 && typeof gameData.genres[0] === 'object' && gameData.genres[0].name) {
                genreText = gameData.genres.map(g => g.name).join(', ');
            } else if (gameData.genres.length > 0) {
                genreText = gameData.genres.join(', ');
            }
        }
        
        let platformText = 'N/A';
        if (Array.isArray(gameData.platforms)) {
            if (gameData.platforms.length > 0 && typeof gameData.platforms[0] === 'object' && gameData.platforms[0].name) {
                platformText = gameData.platforms.map(p => p.name).join(', ');
            } else if (gameData.platforms.length > 0) {
                platformText = gameData.platforms.join(', ');
            }
        }
        
        const metaValues = [
            { label: 'Release Date', value: releaseDate },
            { label: 'Developer', value: gameData.developer || 'N/A' },
            { label: 'Publisher', value: gameData.publisher || 'N/A' },
            { label: 'Genres', value: genreText },
            { label: 'Platforms', value: platformText },
            { label: 'Tags', value: gameData.tags && gameData.tags.length > 0 ? gameData.tags.join(', ') : 'N/A' }
        ];
        
        if (gameData.websites && gameData.websites.length > 0) {
            const websiteLinks = gameData.websites.map(website => {
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
        
        metaItems.forEach((item, index) => {
            if (index < metaValues.length) {
                item.innerHTML = `<strong>${metaValues[index].label}:</strong> ${metaValues[index].value}`;
            }
        });
    }
    
    function setupReviewForm(gameId) {
        const reviewForm = document.getElementById('review-form');
        if (!reviewForm) return;
        
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitReview(gameId);
        });
        
        updateDisplayNameDropdown();
        
        const titleInput = document.getElementById('review-title');
        const charCountDisplay = document.getElementById('title-char-count');
        const maxTitleLength = 50;
        
        if (titleInput && charCountDisplay) {
            titleInput.addEventListener('input', function() {
                const currentLength = titleInput.value.length;
                charCountDisplay.textContent = `${currentLength}/${maxTitleLength} characters`;
                
                if (currentLength > maxTitleLength) {
                    charCountDisplay.style.color = '#ff0000';
                } else {
                    charCountDisplay.style.color = '#00ff00';
                }
            });
            
            charCountDisplay.textContent = `0/${maxTitleLength} characters`;
        }
    }
    
    function updateDisplayNameDropdown() {
        const anonymousNameField = document.getElementById('anonymous-name-field');
        if (!anonymousNameField) return;
        
        const gameRatingAuth = window.gameRating?.auth;
        
        let username = null;
        if (gameRatingAuth?.getCurrentUser?.()) {
            username = gameRatingAuth.getCurrentUser().username;
        } else if (currentUser && currentUser.username) {
            username = currentUser.username;
        } else {
            const token = getCookie('access_token');
            if (token) {
                try {
                    const tokenData = parseJwt(token);
                    if (tokenData && tokenData.username) {
                        username = tokenData.username;
                    }
                } catch (e) {
                    // Error handled silently as fallback exists
                }
            }
        }
        
        const dropdownHTML = `
            <label for="anonymous-name">Post as:</label>
            <select id="anonymous-name" class="form-control">
                <option value="Anonymous" selected>Anonymous</option>
                ${username ? `<option value="${username}">${username}</option>` : ''}
            </select>
        `;
        
        anonymousNameField.innerHTML = dropdownHTML;
        
        const authenticated = gameRatingAuth?.isAuthenticated?.() || isAuthenticated || false;
        anonymousNameField.style.display = authenticated ? 'none' : 'block';
    }
    
    function submitReview(gameId) {
        if (challengeState.userInput.length !== challengeState.userInput.length) {
            showNotification('Please complete the arrow key sequence challenge first', 'error');
            return;
        }
        
        for (let i = 0; i < challengeState.sequence.length; i++) {
            if (challengeState.userInput[i] !== challengeState.sequence[i].keyCode) {
                showNotification('Please complete the arrow key sequence challenge correctly', 'error');
                return;
            }
        }
        
        const title = document.getElementById('review-title').value;
        const content = document.getElementById('review-content').value;
        const rating = parseInt(document.getElementById('review-rating').value);
        
        let displayName = 'Anonymous';
        if (!isAuthenticated) {
            const displayNameSelect = document.getElementById('anonymous-name');
            if (displayNameSelect) {
                displayName = displayNameSelect.value;
            }
        } else if (currentUser && currentUser.username) {
            displayName = currentUser.username;
        }
        
        if (!title.trim() || !content.trim() || isNaN(rating) || rating < 1 || rating > 10) {
            showNotification('Please fill in all required fields and provide a rating between 1 and 10.', 'error');
            return;
        }
        
        const maxTitleLength = 50;
        if (title.length > maxTitleLength) {
            showNotification(`Review title is too long. Please keep it under ${maxTitleLength} characters.`, 'error');
            return;
        }
        
        const reviewData = {
            gameId: parseInt(gameId),
            title: title,
            content: content,
            rating: rating
        };
        
        if (!isAuthenticated) {
            reviewData.displayName = displayName;
        }
        
        const submitBtn = document.querySelector('#review-form button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Submitting...';
        }
        
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
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Submit Review';
            }
            
            if (data.success) {
                document.getElementById('review-title').value = '';
                document.getElementById('review-content').value = '';
                document.getElementById('review-rating').value = '5';
                
                showNotification('Review submitted successfully!', 'success');
                generateSymbolChallenge();
                loadReviews(1, gameId);
            } else {
                showNotification('Error: ' + (data.error || 'Failed to submit review'), 'error');
            }
        })
        .catch(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Submit Review';
            }
            showNotification('An error occurred while submitting your review.', 'error');
        });
    }
    
    window.loadReviews = function(page, gameId) {
        if (!gameId) {
            const urlParams = new URLSearchParams(window.location.search);
            gameId = urlParams.get('id');
        }
        
        const limit = 5;
        
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
                if (data.error) {
                    throw new Error(data.error || 'Failed to load reviews');
                }
                
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
                
                setupPagination(pagination.current_page, pagination.total_pages, gameId);
            })
            .catch(error => {
                document.getElementById('reviews-list').innerHTML = `<p class="error">Error: ${error.message}</p>`;
            });
    };
    
    function renderReview(review, container) {
        console.log("Review object:", review);
    console.log("Current user:", currentUser);
        const reviewDiv = document.createElement('div');
        reviewDiv.className = 'review-item';
        reviewDiv.id = `review-${review.id}`;
        
        const reviewDate = new Date(review.created_at).toLocaleDateString();
        const wasUpdated = review.created_at !== review.updated_at;
        const updatedText = wasUpdated ? ` (edited ${new Date(review.updated_at).toLocaleDateString()})` : '';
        
        let userBadge = '';
        if (review.is_admin) {
            userBadge = '<span class="admin-badge">Admin</span>';
        } else if (review.is_moderator) {
            userBadge = '<span class="mod-badge">Moderator</span>';
        } else if (!review.is_anonymous) {
            userBadge = '<span class="user-badge">User</span>';
        }
        
        const filledStars = '★'.repeat(review.rating);
        const emptyStars = '☆'.repeat(10 - review.rating);
        
        reviewDiv.innerHTML = `
            <div class="review-header">
                <div class="review-author">
                    ${review.display_name} ${userBadge}
                </div>
                <div class="review-date">${reviewDate}${updatedText}</div>
            </div>
            <div class="review-rating">
                <span class="filled-stars">${filledStars}</span><span class="empty-stars">${emptyStars}</span> (${review.rating}/10)
            </div>
            <h4 class="review-title">${review.title}</h4>
            <div class="review-content">${review.content}</div>
            <div class="review-metadata">
                <div class="review-votes">
                    <span id="review-helpful-${review.id}">${review.helpful_votes} found this helpful</span> | 
                    <span id="review-unhelpful-${review.id}">${review.not_helpful_votes} found this unhelpful</span>
                </div>
            </div>
        `;
        
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'review-actions';
        
        const helpfulBtn = document.createElement('button');
        helpfulBtn.id = `helpful-button-${review.id}`;
        helpfulBtn.className = 'helpful-btn';
        helpfulBtn.textContent = 'Helpful';
        helpfulBtn.onclick = () => voteReviewHelpful(review.id);
        actionsDiv.appendChild(helpfulBtn);
        
        const unhelpfulBtn = document.createElement('button');
        unhelpfulBtn.id = `unhelpful-button-${review.id}`;
        unhelpfulBtn.className = 'unhelpful-btn';
        unhelpfulBtn.textContent = 'Not Helpful';
        unhelpfulBtn.onclick = () => voteReviewUnhelpful(review.id);
        actionsDiv.appendChild(unhelpfulBtn);
        
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
        
        // Add ban user button for admins/moderators
        if (currentUser && (currentUser.is_admin === true || currentUser.is_moderator === true)) {
            // Only show for reviews not from other admins
            if (!review.is_admin) {
                const banUserBtn = document.createElement('button');
                banUserBtn.className = 'ban-user-btn';
                
                // Works for both anonymous and registered users
                const userType = review.is_anonymous ? 'Token' : 'User';
                banUserBtn.textContent = review.is_banned ? `Unban ${userType}` : `Ban ${userType}`;
                
                banUserBtn.onclick = () => banUserFromReview(review);
                actionsDiv.appendChild(banUserBtn);
            }
        }
        
        // Existing report button code
        const reportBtn = document.createElement('button');
        reportBtn.className = 'report-review-btn';
        reportBtn.textContent = 'Report';
        reportBtn.onclick = () => showReportDialog(review.id);
        actionsDiv.appendChild(reportBtn);
        
        reviewDiv.appendChild(actionsDiv);
        container.appendChild(reviewDiv);
        
        checkReviewVote(review.id);
    }

// Add this function to handle ban user action
window.toggleUserBan = async function(userId, isBanned) {
    const action = isBanned ? 'unban' : 'ban';
    const confirmMessage = isBanned 
        ? "Are you sure you want to unban this user?" 
        : "Are you sure you want to ban this user? They will not be able to submit reviews or vote.";
        
    if (!confirm(confirmMessage)) return;
    
    const fetchFn = window.gameRating?.auth?.fetchWithAuth || fetchWithAuth;
    
    fetchFn(`${window.baseUrl}/api.php?action=${isBanned ? 'unbanUser' : 'banUser'}&id=${userId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success || data.message) {
            showNotification(`User ${isBanned ? 'unbanned' : 'banned'} successfully!`, 'success');
            
            // Reload reviews to reflect the updated ban status
            const urlParams = new URLSearchParams(window.location.search);
            const gameId = urlParams.get('id');
            loadReviews(1, gameId);
        } else {
            showNotification('Error: ' + (data.error || `Failed to ${action} user`), 'error');
        }
    })
    .catch((error) => {
        showNotification(`An error occurred while trying to ${action} the user: ${error.message}`, 'error');
    });
};
    
    window.showReportDialog = function(reviewId) {
        const MAX_DETAILS_LENGTH = 500; // Set the character limit for details
        
        const modal = document.createElement('div');
        modal.className = 'report-modal';
        
        modal.innerHTML = `
            <div class="report-modal-content">
                <h3>Report Review</h3>
                <form id="report-form">
                    <div class="form-group">
                        <label for="report-reason">Reason:</label>
                        <select id="report-reason" class="form-control" required>
                            <option value="">Select a reason</option>
                            <option value="spam">Spam</option>
                            <option value="offensive">Offensive Content</option>
                            <option value="inappropriate">Inappropriate</option>
                            <option value="off-topic">Off Topic</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="report-details">Additional Details:</label>
                        <textarea id="report-details" class="form-control" rows="3" maxlength="${MAX_DETAILS_LENGTH}"></textarea>
                        <span id="details-char-count" class="report-char-count">0/${MAX_DETAILS_LENGTH} characters</span>
                    </div>
                    <div class="form-buttons">
                        <button type="submit" class="submit-report-btn">Submit Report</button>
                        <button type="button" class="cancel-report-btn">Cancel</button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Setup character counter for details
        const detailsInput = document.getElementById('report-details');
        const charCountDisplay = document.getElementById('details-char-count');
        
        detailsInput.addEventListener('input', function() {
            const currentLength = detailsInput.value.length;
            charCountDisplay.textContent = `${currentLength}/${MAX_DETAILS_LENGTH} characters`;
            
            if (currentLength > MAX_DETAILS_LENGTH) {
                charCountDisplay.style.color = '#ff0000';
                detailsInput.value = detailsInput.value.substring(0, MAX_DETAILS_LENGTH);
            } else {
                charCountDisplay.style.color = '#00ff00';
            }
        });
        
        document.querySelector('.cancel-report-btn').addEventListener('click', () => {
            modal.remove();
        });
        
        document.getElementById('report-form').addEventListener('submit', (e) => {
            e.preventDefault();
            submitReport(reviewId, modal);
        });
    };

    function submitReport(reviewId, modal) {
        const reason = document.getElementById('report-reason').value;
        const details = document.getElementById('report-details').value;
        
        if (!reason) {
            showNotification('Please select a reason for your report', 'error');
            return;
        }
        
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || fetchWithAuth;
        
        fetchFn(`${window.baseUrl}/api.php?action=reportReview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                reviewId: reviewId,
                reason: reason,
                details: details
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Thank you. Your report has been submitted.', 'success');
                modal.remove();
            } else {
                showNotification('Error: ' + (data.error || 'Failed to submit report'), 'error');
            }
        })
        .catch(() => {
            showNotification('An error occurred while submitting your report.', 'error');
        });
    }
    
    window.likeGame = function(gameId) {
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
                checkUserVote(gameId);
                showNotification('Vote recorded!', 'success');
                
                const votesDiv = document.querySelector('.game-votes');
                if (votesDiv) {
                    const likes = data.likes || 0;
                    const dislikes = data.dislikes || 0;
                    const totalVotes = likes + dislikes;
                    const approvalPercent = data.approval_percent !== undefined ? 
                        data.approval_percent : 
                        (totalVotes > 0 ? Math.round((likes / totalVotes) * 100) : 0);
                    
                    let votesText = 'No votes yet';
                    if (totalVotes > 0) {
                        votesText = `${Math.round(approvalPercent)}% (${likes}/${totalVotes}) approval`;
                    }
                    
                    votesDiv.textContent = votesText;
                }
            } else {
                showNotification('Error: ' + (data.error || 'Failed to record vote'), 'error');
            }
        })
        .catch(() => {
            showNotification('An error occurred while voting.', 'error');
        });
    };
    
    window.dislikeGame = function(gameId) {
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
                checkUserVote(gameId);
                showNotification('Vote recorded!', 'success');
                
                const votesDiv = document.querySelector('.game-votes');
                if (votesDiv) {
                    const likes = data.likes || 0;
                    const dislikes = data.dislikes || 0;
                    const totalVotes = likes + dislikes;
                    const approvalPercent = data.approval_percent !== undefined ? 
                        data.approval_percent : 
                        (totalVotes > 0 ? Math.round((likes / totalVotes) * 100) : 0);
                    
                    let votesText = 'No votes yet';
                    if (totalVotes > 0) {
                        votesText = `${Math.round(approvalPercent)}% (${likes}/${totalVotes}) approval`;
                    }
                    
                    votesDiv.textContent = votesText;
                }
            } else {
                showNotification('Error: ' + (data.error || 'Failed to record vote'), 'error');
            }
        })
        .catch(() => {
            showNotification('An error occurred while voting.', 'error');
        });
    };
    
    window.voteReviewHelpful = function(reviewId) {
        const helpfulBtn = document.getElementById(`helpful-button-${reviewId}`);
        const unhelpfulBtn = document.getElementById(`unhelpful-button-${reviewId}`);
        
        if (!helpfulBtn || !unhelpfulBtn) return;
        
        helpfulBtn.disabled = true;
        unhelpfulBtn.disabled = true;
        
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
            helpfulBtn.disabled = false;
            unhelpfulBtn.disabled = false;
            
            if (data.success) {
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
        .catch(() => {
            helpfulBtn.disabled = false;
            unhelpfulBtn.disabled = false;
            showNotification('An error occurred while voting.');
        });
    };
    
    window.voteReviewUnhelpful = function(reviewId) {
        const helpfulBtn = document.getElementById(`helpful-button-${reviewId}`);
        const unhelpfulBtn = document.getElementById(`unhelpful-button-${reviewId}`);
        
        if (!helpfulBtn || !unhelpfulBtn) return;
        
        helpfulBtn.disabled = true;
        unhelpfulBtn.disabled = true;
        
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
            helpfulBtn.disabled = false;
            unhelpfulBtn.disabled = false;
            
            if (data.success) {
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
        .catch(() => {
            helpfulBtn.disabled = false;
            unhelpfulBtn.disabled = false;
            showNotification('An error occurred while voting.');
        });
    };
    
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
        
        titleElement.style.display = 'none';
        contentElement.style.display = 'none';
        ratingStars.style.display = 'none';
        
        contentElement.parentNode.insertBefore(editForm, contentElement);
        
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const updatedTitle = document.getElementById(`edit-title-${reviewId}`).value;
            const updatedContent = document.getElementById(`edit-content-${reviewId}`).value;
            const updatedRating = parseInt(document.getElementById(`edit-rating-${reviewId}`).value);
            
            submitEditedReview(reviewId, updatedTitle, updatedContent, updatedRating);
        });
        
        editForm.querySelector('.cancel-edit-btn').addEventListener('click', function() {
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
                const urlParams = new URLSearchParams(window.location.search);
                const gameId = urlParams.get('id');
                loadReviews(1, gameId);
            } else {
                showNotification('Error: ' + (data.error || 'Failed to update review'));
            }
        })
        .catch(() => {
            showNotification('An error occurred while updating your review.');
        });
    }
    
    function showConfirmDialog(message) {
        return new Promise((resolve) => {
            let dialog = document.getElementById('confirm-dialog');
            if (!dialog) {
                dialog = document.createElement('div');
                dialog.id = 'confirm-dialog';
                dialog.className = 'confirm-dialog';
                document.body.appendChild(dialog);
            }
            
            dialog.innerHTML = `
                <div class="confirm-dialog-content">
                    <p>${message}</p>
                    <div class="confirm-dialog-buttons">
                        <button id="confirm-yes">Yes</button>
                        <button id="confirm-no">No</button>
                    </div>
                </div>
            `;
            
            dialog.style.display = 'flex';
            
            const yesBtn = document.getElementById('confirm-yes');
            const noBtn = document.getElementById('confirm-no');
            
            yesBtn.addEventListener('click', () => {
                dialog.style.display = 'none';
                resolve(true);
            });
            
            noBtn.addEventListener('click', () => {
                dialog.style.display = 'none';
                resolve(false);
            });
        });
    }
    
    window.deleteReview = async function(reviewId) {
        const confirmed = await showConfirmDialog("Are you sure you want to delete this review? This action cannot be undone.");
        if (!confirmed) return;
        
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
                showNotification('Review deleted successfully!', 'success');
                const urlParams = new URLSearchParams(window.location.search);
                const gameId = urlParams.get('id');
                loadReviews(1, gameId);
            } else {
                showNotification('Error: ' + (data.error || 'Failed to delete review'), 'error');
            }
        })
        .catch(() => {
            showNotification('An error occurred while deleting your review.', 'error');
        });
    };
    
    function checkUserVote(gameId) {
        fetch(`${window.baseUrl}/api.php?action=checkUserVote&gameId=${gameId}`, {
            credentials: 'include'
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
            
            likeButton.classList.remove('active');
            dislikeButton.classList.remove('active');
            
            if (data.success && data.hasVoted) {
                if (data.vote === 1) {
                    likeButton.classList.add('active');
                } else if (data.vote === 0) {
                    dislikeButton.classList.add('active');
                }
            }
        })
        .catch(() => {});
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
        .catch(() => {});
    };
    
    function setupPagination(currentPage, totalPages, gameId) {
        const paginationDiv = document.getElementById('pagination');
        if (!paginationDiv) return;
        
        paginationDiv.innerHTML = '';
        
        if (totalPages <= 1) return;
        
        const ul = document.createElement('ul');
        ul.className = 'pagination';
        
        if (currentPage > 1) {
            const prevLi = document.createElement('li');
            const prevLink = document.createElement('a');
            prevLink.href = '#';
            prevLink.innerHTML = '« Previous';
            prevLink.addEventListener('click', function(e) {
                e.preventDefault();
                window.loadReviews(currentPage - 1, gameId);
            });
            prevLi.appendChild(prevLink);
            ul.appendChild(prevLi);
        }
        
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
        
        if (currentPage < totalPages) {
            const nextLi = document.createElement('li');
            const nextLink = document.createElement('a');
            nextLink.href = '#';
            nextLink.innerHTML = 'Next »';
            nextLink.addEventListener('click', function(e) {
                e.preventDefault();
                window.loadReviews(currentPage + 1, gameId);
            });
            nextLi.appendChild(nextLink);
            ul.appendChild(nextLi);
        }
        
        paginationDiv.appendChild(ul);
    }
    
    function showNotification(message, type = 'info') {
        let container = document.getElementById('notifications-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notifications-container';
            container.className = 'notifications-container';
            document.body.appendChild(container);
        }
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">${message}</div>
            <button class="notification-close">×</button>
        `;
        
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            notification.classList.add('hiding');
            setTimeout(() => notification.remove(), 300);
        });
        
        container.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 10);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.add('hiding');
                setTimeout(() => {
                    if (notification.parentNode) notification.remove();
                }, 300);
            }
        }, 5000);
    }
    
    function fetchWithAuth(url, options = {}) {
        const token = getCookie('access_token') || localStorage.getItem('jwt_token');
        
        const fetchOptions = { ...options };
        fetchOptions.headers = { ...fetchOptions.headers } || {};
        
        if (token) {
            fetchOptions.headers['Authorization'] = `Bearer ${token}`;
        }
        
        fetchOptions.credentials = 'same-origin';
        
        return fetch(url, fetchOptions);
    }
    
    // Add these functions after your existing functions

// Ban function that works with both anonymous and registered users
function banUserFromReview(review) {
    if (!currentUser || (!currentUser.is_admin && !currentUser.is_moderator)) {
        showNotification('You must be an admin or moderator to ban users', 'error');
        return;
    }

    const isBanned = review.is_banned || false;
    const confirmMessage = isBanned ? 
        "Are you sure you want to unban this user?" : 
        "Are you sure you want to ban this user? They will not be able to submit reviews or vote.";
    
    if (!confirm(confirmMessage)) return;
    
    let endpoint;
    
    if (review.is_anonymous && review.anonymous_token) {
        // For anonymous users
        endpoint = `${window.baseUrl}/api.php?action=${isBanned ? 'unbanAnonymousUser' : 'banAnonymousUser'}&token=${review.anonymous_token}`;
    } else if (review.user_id) {
        // For registered users
        endpoint = `${window.baseUrl}/api.php?action=${isBanned ? 'unbanUser' : 'banUser'}&id=${review.user_id}`;
    } else {
        showNotification('Error: Could not determine user type', 'error');
        return;
    }
    
    const fetchFn = window.gameRating?.auth?.fetchWithAuth || fetchWithAuth;
    
    fetchFn(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification(`User ${isBanned ? 'unbanned' : 'banned'} successfully`, 'success');
            
            // Reload the reviews to reflect changes
            const urlParams = new URLSearchParams(window.location.search);
            const gameId = urlParams.get('id');
            loadReviews(1, gameId);
        } else {
            showNotification(`Error: ${data.error || 'Unknown error'}`, 'error');
        }
    })
    .catch(error => {
        showNotification(`Error: ${error.message}`, 'error');
    });
}


    
})();