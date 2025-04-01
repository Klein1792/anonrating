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

    // Initialize all components
    if (window.GameDetails && window.GameDetails.loadGameDetails) {
        window.GameDetails.loadGameDetails(gameId);
    } else {
        console.error("GameDetails module not available");
    }

    if (window.Reviews && window.Reviews.loadReviews) {
        window.Reviews.loadReviews(1, gameId);
    } else {
        console.error("Reviews module not available");
    }

    if (window.Reviews && window.Reviews.setupReviewForm) {
        window.Reviews.setupReviewForm(gameId);
    }

    if (window.ChallengeSystem && window.ChallengeSystem.setupChallengeSequence) {
        window.ChallengeSystem.setupChallengeSequence();
    }

    // Setup moderation UI elements if user is admin/moderator
    if (window.currentUser && (window.currentUser.is_admin || window.currentUser.is_moderator)) {
        setupModerationUI();
    }

    console.log("Game page initialization complete");
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