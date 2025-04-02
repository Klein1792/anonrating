/**
 * User Dashboard JavaScript - with Batch API implementation
 */
(function() {
    // Set default baseUrl if not already defined
    window.baseUrl = window.baseUrl || '';

    // Current page tracking for pagination
    const pageState = {
        reviews: 1,
        comments: 1,
        likedGames: 1
    };
    
    const itemsPerPage = 10;
    let dashboardData = null;
    
    document.addEventListener('DOMContentLoaded', function() {
        // Setup tab events
        document.querySelectorAll('#dashboardTabs .nav-link').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(event) {
                const targetId = event.target.getAttribute('data-bs-target').substring(1);
                updateTabContent(targetId);
            });
        });
        
        // Set up pagination controls
        setupPaginationControls();
        
        // Load all dashboard data at once
        loadBatchDashboardData();
    });
    
    /**
     * Set up all pagination control event handlers
     */
    function setupPaginationControls() {
        document.getElementById('reviews-prev-page').addEventListener('click', () => {
            if (pageState.reviews > 1) {
                pageState.reviews--;
                loadBatchDashboardData();
            }
        });
        
        document.getElementById('reviews-next-page').addEventListener('click', () => {
            pageState.reviews++;
            loadBatchDashboardData();
        });
        
        document.getElementById('comments-prev-page').addEventListener('click', () => {
            if (pageState.comments > 1) {
                pageState.comments--;
                loadBatchDashboardData();
            }
        });
        
        document.getElementById('comments-next-page').addEventListener('click', () => {
            pageState.comments++;
            loadBatchDashboardData();
        });
        
        document.getElementById('games-prev-page').addEventListener('click', () => {
            if (pageState.likedGames > 1) {
                pageState.likedGames--;
                loadBatchDashboardData();
            }
        });
        
        document.getElementById('games-next-page').addEventListener('click', () => {
            pageState.likedGames++;
            loadBatchDashboardData();
        });
    }
    
    /**
     * Load all dashboard data in a single batch request
     */
    function loadBatchDashboardData() {
        // Show loading indicators in all tabs
        document.getElementById('user-reviews-container').innerHTML = '<div class="loading">Loading your data...</div>';
        document.getElementById('user-comments-container').innerHTML = '<div class="loading">Loading your data...</div>';
        document.getElementById('liked-games-container').innerHTML = '<div class="loading">Loading your data...</div>';
        
        const url = `${window.baseUrl}/api.php?action=getBatchUserData&reviews_page=${pageState.reviews}&comments_page=${pageState.comments}&games_page=${pageState.likedGames}&limit=${itemsPerPage}`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    showError('Failed to load dashboard data: ' + (data.error || 'Unknown error'));
                    return;
                }
                
                // Store the data
                dashboardData = data;
                
                // Update the active tab content
                const activeTabId = document.querySelector('#dashboardTabs .nav-link.active').getAttribute('data-bs-target').substring(1);
                updateTabContent(activeTabId);
                
                // Update all pagination controls
                updatePaginationControls();
            })
            .catch(error => {
                showError('Network error: ' + error.message);
            });
    }
    
    /**
     * Update content for a specific tab using the cached dashboard data
     */
    function updateTabContent(tabId) {
        if (!dashboardData) return;
        
        switch(tabId) {
            case 'reviews':
                renderReviews();
                break;
            case 'comments':
                renderComments();
                break;
            case 'liked-games':
                renderLikedGames();
                break;
        }
    }
    
    /**
     * Update all pagination controls based on current data
     */
    function updatePaginationControls() {
        // Reviews pagination
        const reviewsData = dashboardData.reviews;
        document.getElementById('reviews-prev-page').disabled = pageState.reviews <= 1;
        document.getElementById('reviews-next-page').disabled = pageState.reviews >= reviewsData.pages;
        document.getElementById('reviews-page-info').textContent = 
            reviewsData.total > 0 ? `Page ${pageState.reviews} of ${reviewsData.pages}` : 'No reviews';
            
        // Comments pagination
        const commentsData = dashboardData.comments;
        document.getElementById('comments-prev-page').disabled = pageState.comments <= 1;
        document.getElementById('comments-next-page').disabled = pageState.comments >= commentsData.pages;
        document.getElementById('comments-page-info').textContent = 
            commentsData.total > 0 ? `Page ${pageState.comments} of ${commentsData.pages}` : 'No comments';
            
        // Liked games pagination
        const gamesData = dashboardData.liked_games;
        document.getElementById('games-prev-page').disabled = pageState.likedGames <= 1;
        document.getElementById('games-next-page').disabled = pageState.likedGames >= gamesData.pages;
        document.getElementById('games-page-info').textContent = 
            gamesData.total > 0 ? `Page ${pageState.likedGames} of ${gamesData.pages}` : 'No liked games';
    }
    
    /**
     * Render reviews from cached data
     */
    function renderReviews() {
        const container = document.getElementById('user-reviews-container');
        const reviews = dashboardData.reviews.items;
        
        if (reviews.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <p>You haven't written any reviews yet.</p>
                    <a href="index.php" class="btn btn-primary">Browse Games to Review</a>
                </div>`;
            return;
        }
        
        container.innerHTML = '';
        reviews.forEach(review => {
            const reviewElement = document.createElement('div');
            reviewElement.className = 'user-review-item';
            
            const date = new Date(review.created_at).toLocaleDateString();
            const stars = '★'.repeat(review.rating) + '☆'.repeat(10 - review.rating);
            
            reviewElement.innerHTML = `
                <div class="review-header">
                    <h3><a href="${window.baseUrl}/game.php?id=${review.game_id}">${review.game_name}</a></h3>
                    <span class="review-date">${date}</span>
                </div>
                <div class="review-rating">${stars} (${review.rating}/10)</div>
                <h4>${review.title}</h4>
                <div class="review-content">${review.content}</div>
                <div class="review-footer">
                    <span>${review.helpful_votes} found this helpful</span>
                    <span class="review-actions">
                        <a href="${window.baseUrl}/game.php?id=${review.game_id}#review-${review.id}" class="view-btn">View</a>
                        <button onclick="ReviewActions.editReview(${review.id})" class="edit-review-btn">Edit</button>
                        <button onclick="ReviewActions.deleteReview(${review.id})" class="delete-review-btn">Delete</button>
                    </span>
                </div>
            `;
            
            container.appendChild(reviewElement);
        });
    }
    
    /**
     * Render comments from cached data
     */
    function renderComments() {
        const container = document.getElementById('user-comments-container');
        const comments = dashboardData.comments.items;
        
        if (comments.length === 0) {
            container.innerHTML = '<p class="no-data">You haven\'t posted any comments yet.</p>';
            return;
        }
        
        container.innerHTML = '';
        comments.forEach(comment => {
            const commentElement = document.createElement('div');
            commentElement.className = 'user-comment-item';
            
            const date = new Date(comment.created_at).toLocaleDateString();
            
            commentElement.innerHTML = `
                <div class="comment-header">
                    <h3>Comment on review: <a href="${window.baseUrl}/game.php?id=${comment.game_id}#review-${comment.review_id}">${comment.review_title}</a></h3>
                    <span class="comment-date">${date}</span>
                </div>
                <div class="comment-content">${comment.content}</div>
                <div class="comment-footer">
                    <span class="comment-game">Game: <a href="${window.baseUrl}/game.php?id=${comment.game_id}">${comment.game_name}</a></span>
                    <span class="comment-actions">
                        <a href="${window.baseUrl}/game.php?id=${comment.game_id}#comment-${comment.id}" class="view-btn">View</a>
                        <button onclick="ReviewComments.deleteComment(${comment.id}, ${comment.review_id})" class="delete-comment-btn">Delete</button>
                    </span>
                </div>
            `;
            
            container.appendChild(commentElement);
        });
    }
    
    /**
     * Render liked games from cached data
     */
    function renderLikedGames() {
        const container = document.getElementById('liked-games-container');
        const games = dashboardData.liked_games.items;
        
        if (games.length === 0) {
            container.innerHTML = '<p class="no-data">You haven\'t liked any games yet.</p>';
            return;
        }
        
        container.innerHTML = '';
        games.forEach(game => {
            const gameCard = document.createElement('div');
            gameCard.className = 'game-card';
            
            const coverUrl = game.cover_url || '/images/default-game-cover.jpg';
            
            gameCard.innerHTML = `
                <a href="${window.baseUrl}/game.php?id=${game.id}" class="game-link">
                    <div class="game-card-inner">
                        <div class="game-cover">
                            <img src="${coverUrl}" alt="${game.name}" loading="lazy">
                        </div>
                        <div class="game-info">
                            <h3 class="game-title">${game.name}</h3>
                            <div class="game-meta">
                                <span class="game-rating">Rating: ${game.avg_rating ? game.avg_rating + '/10' : 'N/A'}</span>
                                <span class="game-votes">Likes: ${game.likes}</span>
                            </div>
                        </div>
                    </div>
                </a>
            `;
            
            container.appendChild(gameCard);
        });
    }
    
    /**
     * Display an error notification
     */
    function showError(message) {
        // Show error in all containers
        const containers = ['user-reviews-container', 'user-comments-container', 'liked-games-container'];
        containers.forEach(id => {
            document.getElementById(id).innerHTML = `<p class="error">${message}</p>`;
        });
        console.error(message);
    }
})();
