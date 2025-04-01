/**
 * Review Votes Module
 * Handles voting on reviews (helpful/unhelpful)
 */

(function() {
    'use strict';
    
    /**
     * Vote that a review was helpful
     * @param {number} reviewId - The ID of the review to vote on
     */
    function voteReviewHelpful(reviewId) {
        voteReview(reviewId, true); // Pass `true` for helpful votes
    }
    
    /**
     * Vote that a review was unhelpful
     * @param {number} reviewId - The ID of the review to vote on
     */
    function voteReviewUnhelpful(reviewId) {
        voteReview(reviewId, false); // Pass `false` for unhelpful votes
    }
    
    /**
     * Vote on a review
     * @param {number} reviewId - The ID of the review to vote on
     * @param {boolean} isHelpful - Whether the vote is helpful or not
     */
    function voteReview(reviewId, isHelpful) {
        if (!reviewId || typeof isHelpful !== 'boolean') {
            console.error('Invalid review ID or vote value');
            UIUtils.showNotification('Invalid review ID or vote value', 'error');
            return;
        }

        // Get fetch function from auth-utils or auth-client
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;

        fetchFn(`${window.baseUrl}/api.php?action=voteReview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                reviewId: reviewId,
                isHelpful: isHelpful
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateVoteCounts(reviewId, data.helpfulVotes || 0, data.unhelpfulVotes || 0);
                updateVoteButtons(reviewId, isHelpful ? 'helpful' : 'unhelpful');
                UIUtils.showNotification(data.message || 'Vote submitted successfully', 'success');
            } else {
                UIUtils.showNotification(data.error || 'Failed to submit vote', 'error');
            }
        })
        .catch(error => {
            UIUtils.showNotification('Error: ' + error.message, 'error');
        });
    }
    
    /**
     * Check current vote status for a review
     * @param {number} reviewId - The ID of the review to check
     */
    function checkReviewVote(reviewId) {
        // Get fetch function from auth-utils or auth-client
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;
        
        fetchFn(`${window.baseUrl}/api.php?action=checkReviewVote&reviewId=${reviewId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.voteType) {
                    updateVoteButtons(reviewId, data.voteType);
                }
            })
            .catch(error => {
                console.error('Failed to check review vote status:', error);
            });
    }
    
    /**
     * Update vote counts in the UI
     * @param {number} reviewId - The ID of the review
     * @param {number} helpfulCount - Number of helpful votes
     * @param {number} unhelpfulCount - Number of unhelpful votes
     */
    function updateVoteCounts(reviewId, helpfulCount, unhelpfulCount) {
        const helpfulElement = document.getElementById(`review-helpful-${reviewId}`);
        if (helpfulElement) {
            helpfulElement.textContent = `${helpfulCount} found this helpful`;
        }
        
        const unhelpfulElement = document.getElementById(`review-unhelpful-${reviewId}`);
        if (unhelpfulElement) {
            unhelpfulElement.textContent = `${unhelpfulCount} found this unhelpful`;
        }
    }
    
    /**
     * Update vote buttons based on user's vote
     * @param {number} reviewId - The ID of the review
     * @param {string} voteType - The type of vote ('helpful' or 'unhelpful')
     */
    function updateVoteButtons(reviewId, voteType) {
        const helpfulBtn = document.getElementById(`helpful-button-${reviewId}`);
        const unhelpfulBtn = document.getElementById(`unhelpful-button-${reviewId}`);
        
        if (helpfulBtn && unhelpfulBtn) {
            // Reset both buttons first
            helpfulBtn.classList.remove('active');
            unhelpfulBtn.classList.remove('active');
            
            // Then highlight the active one
            if (voteType === 'helpful') {
                helpfulBtn.classList.add('active');
            } else if (voteType === 'unhelpful') {
                unhelpfulBtn.classList.add('active');
            }
        }
    }
    
    // Export functions to global scope
    window.ReviewVotes = {
        voteReviewHelpful,
        voteReviewUnhelpful,
        checkReviewVote
    };
})();