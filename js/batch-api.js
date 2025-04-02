window.BatchAPI = {
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
            
            console.log("Batch API responses:", data.responses);
            return data.responses;
        } catch (error) {
            console.error('Batch API error:', error);
            throw error;
        }
    },
    
    loadGamePage: async function(gameId, useCache = true) {
        if (!window.GameDetails || !window.GameDetails.renderGameStatic) {
            console.error("GameDetails module not loaded yet. Falling back to individual requests.");
            return {
                error: "GameDetails not initialized",
                game: null,
                reviews: { reviews: [] },
                userVote: { hasVoted: false }
            };
        }

        const cachedStaticData = useCache ? window.GameCache?.getGameStatic(gameId) : null;
        console.log("Cached static data:", cachedStaticData);

        try {
            if (cachedStaticData && useCache) {
                // Use cached static data and fetch dynamic data
                const responses = await this.batch([
                    { action: 'getGameDynamicData', params: { id: gameId, _: Date.now() } },
                    { action: 'getReviewsByGame', params: { gameId: gameId, page: 1 } },
                    { action: 'checkUserVote', params: { gameId: gameId } }
                ]);

                // Ensure static data is rendered even from cache
                window.GameDetails.renderGameStatic(cachedStaticData);

                if (responses[0].success) {
                    window.GameDetails.updateGameDynamic(responses[0].dynamic);
                }

                return {
                    game: { ...cachedStaticData, ...(responses[0].success ? responses[0].dynamic : {}) },
                    reviews: responses[1].success ? responses[1] : { reviews: [] },
                    userVote: responses[2].success ? responses[2] : { hasVoted: false }
                };
            } else {
                // Fetch all data if no cache or cache is bypassed
                const responses = await this.batch([
                    { action: 'getGameDetails', params: { id: gameId, _: Date.now() } },
                    { action: 'getReviewsByGame', params: { gameId: gameId, page: 1 } },
                    { action: 'checkUserVote', params: { gameId: gameId } }
                ]);

                if (responses[0].success && responses[0].game) {
                    const gameData = responses[0].game.static && responses[0].game.dynamic
                        ? { ...responses[0].game.static, ...responses[0].game.dynamic }
                        : responses[0].game;

                    // Render and cache the static data
                    window.GameDetails.renderGameStatic(gameData);
                    if (window.GameCache && window.GameCache.saveGameStatic) {
                        window.GameCache.saveGameStatic(gameId, gameData);
                    }

                    return {
                        game: gameData,
                        reviews: responses[1].success ? responses[1] : { reviews: [] },
                        userVote: responses[2].success ? responses[2] : { hasVoted: false }
                    };
                } else {
                    throw new Error(responses[0].error || 'Failed to fetch game details');
                }
            }
        } catch (error) {
            console.error('Failed to load game page:', error);
            // Fallback to individual load if batch fails
            await window.GameDetails.loadGameDetails(gameId);
            const reviews = await window.Reviews.loadReviews(1, gameId);
            return {
                game: gameDataCache[gameId] || null,
                reviews: reviews || { reviews: [] },
                userVote: { hasVoted: false }
            };
        }
    }
};