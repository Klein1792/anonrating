/**
 * Game Page Main Script
 * Initializes all game page components and handles global state
 */

// Define these in global scope so they're available throughout the file
let currentUser = null;
let isAuthenticated = false;
let isAnonymous = true;

// Main initialization function
document.addEventListener('DOMContentLoaded', async () => {
    console.log("Initializing authentication...");

    // Wait for auth-client.js to initialize
    if (window.gameRating && window.gameRating.auth && typeof window.gameRating.auth.init === 'function') {
        await window.gameRating.auth.init();
        console.log("Auth state initialized from auth-client.js:", {
            currentUser: window.gameRating.auth.getCurrentUser(),
            isAuthenticated: window.gameRating.auth.isAuthenticated(),
            isAnonymous: window.gameRating.auth.isAnonymous()
        });

        // Sync global variables
        window.currentUser = window.gameRating.auth.getCurrentUser();
        window.isAuthenticated = window.gameRating.auth.isAuthenticated();
        window.isAnonymous = window.gameRating.auth.isAnonymous();
    } else {
        console.error("Auth client is not available.");
        window.currentUser = null;
        window.isAuthenticated = false;
        window.isAnonymous = true;
    }

    console.log("Starting game page initialization...");

    const urlParams = new URLSearchParams(window.location.search);
    const gameId = urlParams.get('id');

    if (!gameId) {
        UIUtils.showNotification('No game ID specified.');
        return;
    }

    UIUtils.showNotification('Loading game...', 'info', 1000);

    try {
        const data = await BatchAPI.loadGamePage(gameId);
        console.log("BatchAPI.loadGamePage response:", data);

        if (data.error || !data.game) {
            console.warn("Batch API error or no game data:", data.error);
            // Fallback to individual loads
            await window.GameDetails.loadGameDetails(gameId);
            await window.Reviews.loadReviews(1, gameId);
        } else {
            // Game data should already be rendered by BatchAPI.loadGamePage
            // Just handle reviews here
            if (data.reviews && data.reviews.reviews) {
                const reviewsList = document.getElementById('reviews-list');
                if (reviewsList) {
                    reviewsList.innerHTML = '';
                    if (data.reviews.reviews.length === 0) {
                        reviewsList.innerHTML = '<p>No reviews yet. Be the first to review this game!</p>';
                    } else {
                        data.reviews.reviews.forEach(review => {
                            window.Reviews.renderReview(review, reviewsList);
                        });
                        if (window.Reviews.setupPagination && data.reviews.pagination) {
                            window.Reviews.setupPagination(
                                data.reviews.pagination.current_page,
                                data.reviews.pagination.total_pages,
                                gameId
                            );
                        }
                        if (window.ReviewComments && window.ReviewComments.initializeComments) {
                            window.ReviewComments.initializeComments();
                        }
                    }
                }
            }
        }

        if (window.Reviews && window.Reviews.setupReviewForm) {
            window.Reviews.setupReviewForm(gameId);
        }

        if (window.ChallengeSystem) {
            window.ChallengeSystem.setupChallengeSequence();
        }

        UIUtils.showNotification('Game loaded!', 'success', 1000);
    } catch (error) {
        console.error("Failed to load game:", error);
        UIUtils.showNotification('Error loading game', 'error', 2000);
        // Attempt individual loads as a last resort
        await window.GameDetails.loadGameDetails(gameId);
        await window.Reviews.loadReviews(1, gameId);
    }

    if (window.currentUser && (window.currentUser.is_admin || window.currentUser.is_moderator)) {
        setupModerationUI();
    }
});

// Setup UI elements for moderation
function setupModerationUI() {
    const moderationNav = document.querySelector('.moderation-nav');
    if (moderationNav) return; // Already set up
    
    const navContainer = document.querySelector('.navbar-nav') || document.querySelector('nav ul');
    if (!navContainer) return;
    
    const moderationLink = document.createElement('li');
    moderationLink.className = 'moderation-nav';
    moderationLink.innerHTML = '<a href="#reported-reviews">Moderation</a>';
    
    moderationLink.querySelector('a').addEventListener('click', function(e) {
        e.preventDefault();
        if (window.ModerationActions) {
            window.ModerationActions.showReportedReviews();
        }
    });
    
    navContainer.appendChild(moderationLink);
}

// Remove moderation UI elements
function removeModerationUI() {
    const moderationNav = document.querySelector('.moderation-nav');
    if (moderationNav) {
        moderationNav.remove();
    }
}