/**
 * Batch API Client
 * Allows multiple API requests to be sent in a single HTTP request
 */
const BatchAPI = {
    /**
     * Send multiple API requests in a single batch
     * 
     * @param {Array} requests - Array of request objects {action, params}
     * @returns {Promise} - Promise resolving to array of responses
     */
    batch: async function(requests) {
        try {
            const response = await fetch(`${window.baseUrl}/api.php?action=batch`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ batch: requests }),
                credentials: 'include'
            });
            
            if (!response.ok) {
                throw new Error(`Batch request failed: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Unknown batch error');
            }
            
            return data.responses;
        } catch (error) {
            console.error('Batch API error:', error);
            throw error;
        }
    },
    
    /**
     * Load a game page with all related data in a single request
     * 
     * @param {number} gameId - ID of the game to load
     * @returns {Promise} - Promise resolving to combined game data
     */
    loadGamePage: async function(gameId) {
        try {
            const responses = await this.batch([
                { action: 'getGameDetails', params: { id: gameId } },
                { action: 'getReviewsByGame', params: { gameId: gameId, page: 1 } },
                { action: 'checkUserVote', params: { gameId: gameId } }
            ]);
            
            return {
                game: responses[0].success ? responses[0].game : null,
                reviews: responses[1].success ? responses[1] : { reviews: [] },
                userVote: responses[2].success ? responses[2] : { hasVoted: false }
            };
        } catch (error) {
            console.error('Failed to load game page:', error);
            throw error;
        }
    }
};

// Make available globally
window.BatchAPI = BatchAPI;