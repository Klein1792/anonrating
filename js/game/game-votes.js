/**
 * Game Votes Module
 * Handles liking and disliking games
 */

// Define functions directly on window object for direct access
window.likeGame = function(gameId) {
    if (!gameId) {
        console.error('Invalid game ID');
        UIUtils.showNotification('Invalid game ID', 'error');
        return;
    }

    // Use the proper fetch function with authentication
    const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;

    fetchFn(`${window.baseUrl}/api.php?action=voteGame`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'include', // Important for authentication cookies
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
            UIUtils.showNotification('Vote recorded!', 'success');
            updateGameVotes(gameId, data.likes, data.dislikes, data.approval_percent);
            
            // Important: Check user vote to update button styling
            window.checkUserVote(gameId);
        } else {
            UIUtils.showNotification(data.error || 'Failed to record vote', 'error');
        }
    })
    .catch(error => {
        console.error('Error liking game:', error);
        UIUtils.showNotification('An error occurred while liking the game.', 'error');
    });
};

window.dislikeGame = function(gameId) {
    if (!gameId) {
        console.error('Invalid game ID');
        UIUtils.showNotification('Invalid game ID', 'error');
        return;
    }

    // Use the proper fetch function with authentication
    const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;

    fetchFn(`${window.baseUrl}/api.php?action=voteGame`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'include', // Important for authentication cookies
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
            UIUtils.showNotification('Vote recorded!', 'success');
            updateGameVotes(gameId, data.likes, data.dislikes, data.approval_percent);
            
            if (window.checkUserVote) {
                window.checkUserVote(gameId);
            }
        } else {
            UIUtils.showNotification(data.error || 'Failed to record vote', 'error');
        }
    })
    .catch(error => {
        console.error('Error disliking game:', error);
        UIUtils.showNotification('An error occurred while disliking the game.', 'error');
    });
};

/**
 * Check user's previous vote on a game and update button styling accordingly
 * @param {number|string} gameId - The ID of the game
 */
window.checkUserVote = function(gameId) {
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
        
        // Reset both buttons first
        likeButton.classList.remove('active');
        dislikeButton.classList.remove('active');
        
        // Highlight the active button based on user's previous vote
        if (data.success && data.hasVoted) {
            if (data.vote === 1) {
                likeButton.classList.add('active');
            } else {
                dislikeButton.classList.add('active');
            }
        }
    })
    .catch(error => {
        console.error('Error checking user vote:', error);
    });
};

// Helper function to update UI with vote results
/**
 * Update game votes in the UI with accurate values
 */
function updateGameVotes(gameId, likes, dislikes, approvalPercent) {
    const votesDiv = document.querySelector('.game-votes');
    if (votesDiv) {
        // Ensure we have numeric values
        const likesNum = parseInt(likes) || 0;
        const dislikesNum = parseInt(dislikes) || 0;
        const totalVotes = likesNum + dislikesNum;
        
        // Calculate approval percentage if not provided
        let approval = approvalPercent;
        if ((approval === undefined || approval === null) && totalVotes > 0) {
            approval = Math.round((likesNum / totalVotes) * 100);
        } else {
            approval = Math.round(approval || 0);
        }
        
        let votesText = 'No votes yet';
        if (totalVotes > 0) {
            votesText = `${approval}% (${likesNum}/${totalVotes}) approval`;
        }
        votesDiv.textContent = votesText;
    }
    
    // Also update individual like/dislike count elements if present
    const likesElement = document.getElementById(`like-count-${gameId}`);
    const dislikesElement = document.getElementById(`dislike-count-${gameId}`);
    
    if (likesElement) likesElement.textContent = likes || 0;
    if (dislikesElement) dislikesElement.textContent = dislikes || 0;
}

// Also provide functions through GameVotes object if needed for more structured code
window.GameVotes = {
    likeGame: window.likeGame,
    dislikeGame: window.dislikeGame,
    updateGameVotes: updateGameVotes
};