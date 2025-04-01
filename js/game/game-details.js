/**
 * Game Details Module
 * Handles loading and displaying game information
 */
(function() {
    'use strict';
    
    // Cache for game data
    let gameDataCache = {};
    
    /**
     * Load game details from API or cache
     * @param {number|string} gameId - The ID of the game to load
     * @param {boolean} [forceRefresh=false] - Whether to force a refresh, bypassing the cache
     * @returns {Promise} - Promise that resolves when game is loaded
     */
    function loadGameDetails(gameId, forceRefresh = false) {
        if (!gameId || isNaN(gameId) || parseInt(gameId) <= 0) {
            console.error('Invalid gameId:', gameId);
            UIUtils.showNotification('Invalid game ID. Unable to load game details.', 'error');
            return Promise.reject(new Error('Invalid game ID'));
        }

        const cachedStaticData = !forceRefresh ? window.GameCache?.getGameStatic(gameId) : null;

        if (cachedStaticData && !forceRefresh) {
            renderGameStatic(cachedStaticData);

            return fetch(`${window.baseUrl}/api.php?action=getGameDynamicData&id=${gameId}&_=${Date.now()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server error: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success && data.dynamic) {
                            updateGameDynamic(data.dynamic);
                            return data.dynamic;
                        } else {
                            throw new Error(data.error || 'Unknown error loading dynamic data');
                        }
                    } catch (e) {
                        console.error('Error parsing dynamic game data:', e, text);
                        throw new Error('Invalid response format');
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
                    UIUtils.showNotification('Failed to load dynamic game data. Showing default values.', 'error');
                    throw error;
                });
        } else {
            UIUtils.showNotification('Loading game data...', 'info');

            const timestamp = Date.now();
            return fetch(`${window.baseUrl}/api.php?action=getGameDetails&id=${gameId}&_=${timestamp}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server error: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success && data.game) {
                            // Handle nested structure
                            let staticData = data.game;
                            let dynamicData = data.game;
                            if (data.game .static && data.game.dynamic) {
                                staticData = data.game.static;
                                dynamicData = data.game.dynamic;
                                // Merge static and dynamic data for caching
                                data.game = { ...data.game.static, ...data.game.dynamic };
                            }

                            gameDataCache[gameId] = data.game;
                            if (window.GameCache && typeof window.GameCache.setGameStatic === 'function') {
                                window.GameCache.setGameStatic(gameId, staticData);
                            }
                            renderGameStatic(staticData);
                            updateGameDynamic(dynamicData);
                            UIUtils.showNotification('Game data loaded successfully!', 'success');
                            return data.game;
                        } else {
                            throw new Error(data.error || 'Unknown error loading game data');
                        }
                    } catch (e) {
                        console.error('Error parsing game data:', e, text);
                        throw new Error('Invalid response format');
                    }
                })
                .catch(error => {
                    console.error('Error loading game details:', error);
                    UIUtils.showNotification('Failed to load game details. Please try again later.', 'error');
                    throw error;
                });
        }
    }
    
    /**
     * Render static game data (description, metadata, etc.)
     * @param {Object} gameData - The game data object
     */
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
                    <button}
                    class="description-toggle show-more">Show More</button>
                `;
                
                // Add event listener to the show more/less button
                const toggleButton = descriptionElement.querySelector('.description-toggle');
                toggleButton.addEventListener('click', function() {
                    const truncated = descriptionElement.querySelector('.truncated');
                    const full = descriptionElement.querySelector('.full');
                    
                    if (truncated.style.display !== 'none') {
                        truncated.style.display = 'none';
                        full.style.display = 'block';
                        this.textContent = 'Show Less';
                        this.classList.remove('show-more');
                        this.classList.add('show-less');
                    } else {
                        truncated.style.display = 'block';
                        full.style.display = 'none';
                        this.textContent = 'Show More';
                        this.classList.remove('show-less');
                        this.classList.add('show-more');
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
        
        window.checkUserVote?.(gameData.id);
    }
    
    /**
     * Update dynamic game data (ratings, votes, etc.)
     * @param {Object} dynamicData - The dynamic game data
     */
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
        
        window.checkUserVote?.(dynamicData.id);
    }
    
    /**
     * Update game metadata section (release date, platforms, etc.)
     * @param {Object} gameData - The game data object
     */
    function updateGameMetaSection(gameData) {
        const metaItems = document.querySelectorAll('.game-meta .meta-item');
        if (!metaItems.length) return;
        
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
    
    // Export functions to global scope
    window.GameDetails = {
        loadGameDetails,
        renderGameStatic,
        updateGameDynamic,
        updateGameMetaSection,
    };
})();