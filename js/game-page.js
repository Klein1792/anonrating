/**
 * Game Page JavaScript
 * Handles game details, reviews, and voting functionality
 */
(function() {
    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        // Get the game ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        const gameId = urlParams.get('id');
        
        if (!gameId) {
            showNotification('No game ID specified.');
            return;
        }
        
        loadGameDetails(gameId);
        loadReviews(1, gameId);
    });
    
    // Load game details
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
                if (data.error) {
                    throw new Error(data.error);
                }

                const coverUrl = data.cover ? data.cover.url : '';
                const thumbnailUrl = coverUrl ? coverUrl.replace('t_thumb', 't_cover_big') : `${window.baseUrl}/images/default-image.jpg`;
                
                document.getElementById('game-cover').src = thumbnailUrl;
                document.getElementById('game-cover').alt = data.name;
                document.getElementById('game-cover').onerror = function() {
                    this.src = `${window.baseUrl}/images/default-image.jpg`;
                };

                document.getElementById('game-title').textContent = data.name;

                const voteButtons = document.getElementById('vote-buttons');
                voteButtons.innerHTML = `
                    <button id="like-button-${gameId}" onclick="likeGame('${gameId}')">LIKE</button>
                    <button id="dislike-button-${gameId}" onclick="dislikeGame('${gameId}')">DISLIKE</button>
                `;
                updateGameVotes(gameId);
                checkUserVote(gameId);

                const ratingDiv = document.getElementById('game-rating');
                ratingDiv.textContent = data.rating ? `IGDB Rating: ${Math.round(data.rating)}/100` : 'IGDB Rating: N/A';

                const trailerDiv = document.getElementById('game-trailer');
                if (data.trailer) {
                    // Create a proper embedded iframe for the YouTube video
                    trailerDiv.innerHTML = `
                        <iframe 
                            width="100%" 
                            height="100%" 
                            src="${data.trailer}" 
                            frameborder="0" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                            allowfullscreen>
                        </iframe>`;
                } else {
                    trailerDiv.innerHTML = '<p>No trailer available.</p>';
                }

                document.getElementById('game-description').textContent = data.summary || 'No summary available.';

                updateGameMetaSection(data);
            })
            .catch(error => {
                console.error('Error loading game details:', error);
                document.getElementById('game-title').innerHTML = `<p style="color: #ff00ff;">Error: ${error.message}</p>`;
            });
    }
    
    // Update game meta section with release date, developer, etc.
    function updateGameMetaSection(gameData) {
        const metaItems = document.querySelectorAll('.game-meta .meta-item');
        
        // Set up label/value pairs for the meta items
        const metaValues = [
            { label: 'Release Date', value: formatDate(gameData.first_release_date) || 'N/A' },
            { label: 'Developer', value: gameData.developer || 'N/A' },
            { label: 'Publisher', value: gameData.publisher || 'N/A' },
            { label: 'Genres', value: gameData.genres ? gameData.genres.map(g => g.name).join(', ') : 'N/A' },
            { label: 'Platforms', value: gameData.platforms ? gameData.platforms.map(p => p.name).join(', ') : 'N/A' },
            { label: 'Tags', value: gameData.tags && gameData.tags.length > 0 ? gameData.tags.join(', ') : 'N/A' },
            { label: 'Websites', value: gameData.websites && gameData.websites.length > 0 ? 
                gameData.websites.map(w => `<a href="${w.url}" target="_blank">${w.category}</a>`).join(', ') : 'N/A' }
        ];
        
        // Update each meta item with its label/value
        metaItems.forEach((item, index) => {
            if (index < metaValues.length) {
                item.innerHTML = `<strong>${metaValues[index].label}:</strong> ${metaValues[index].value}`;
            }
        });
    }
    
    // Load game reviews
    window.loadReviews = function(page, gameId) {
        // If gameId is not provided, get from URL
        if (!gameId) {
            const urlParams = new URLSearchParams(window.location.search);
            gameId = urlParams.get('id');
        }
        
        const perPage = 5;
        
        fetch(`${window.baseUrl}/api.php?action=getReviewsByGame&id=${gameId}&page=${page}&perPage=${perPage}`)
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
                const reviewsList = document.getElementById('reviews-list');
                if (data.error) {
                    reviewsList.innerHTML = `<p style="color: #ff00ff;">Error: ${data.error}</p>`;
                    return;
                }
                
                reviewsList.innerHTML = !data.reviews || data.reviews.length === 0 ? '<p>No reviews yet.</p>' : '';
                
                // Check if the user is admin or moderator
                const isAdminOrModerator = window.isAdminOrModerator || false;
                
                // Get current user ID if available
                const currentUserId = window.currentUserId || 0;
                
                data.reviews.forEach(review => {
                    const reviewDiv = document.createElement('div');
                    reviewDiv.className = 'review-item';
                    reviewDiv.id = `review-${review.id}`;
                    
                    const isAuthor = currentUserId && currentUserId === review.userId;
                    
                    // Format the date if available
                    const reviewDate = review.createdAt ? new Date(review.createdAt).toLocaleDateString() : 'Unknown date';
                    
                    // Build the HTML for the review with improved structure
                    reviewDiv.innerHTML = `
                        <div class="review-header">
                            <div class="review-author">
                                ${review.username || 'Anonymous'}
                                ${review.verified ? '<span class="verified-badge">Verified</span>' : ''}
                            </div>
                            <div class="review-date">${reviewDate}</div>
                        </div>
                        <div class="review-text">${review.reviewText}</div>
                        <div class="review-metadata">
                            <div class="review-votes">Votes: ${review.votes || 0}</div>
                            <div class="review-buttons">
                                <button id="upvote-button-${review.id}" onclick="upvoteReview(${review.id})">👍</button>
                                <button id="downvote-button-${review.id}" onclick="downvoteReview(${review.id})">👎</button>
                            </div>
                        </div>
                    `;
                    
                    // SINGLE admin/mod controls section - add only once
                    const adminButtons = document.createElement('div');
                    adminButtons.className = 'admin-buttons';
                    let hasButtons = false;
                    
                    // Add verification buttons if admin/mod
                    if (isAdminOrModerator) {
                        // Verification buttons (admin/mod only)
                        if (!review.verified) {
                            const verifyBtn = document.createElement('button');
                            verifyBtn.setAttribute('onclick', `verifyReview(${review.id})`);
                            verifyBtn.textContent = 'Verify';
                            adminButtons.appendChild(verifyBtn);
                        } else {
                            const unverifyBtn = document.createElement('button');
                            unverifyBtn.setAttribute('onclick', `unverifyReview(${review.id})`);
                            unverifyBtn.textContent = 'Unverify';
                            adminButtons.appendChild(unverifyBtn);
                        }
                        hasButtons = true;
                    }
                    
                    // Add delete button if user is admin/mod OR is the author - only add once
                    if (isAdminOrModerator || isAuthor) {
                        const deleteBtn = document.createElement('button');
                        deleteBtn.setAttribute('onclick', `deleteReview(${review.id})`);
                        deleteBtn.textContent = 'Delete';
                        adminButtons.appendChild(deleteBtn);
                        hasButtons = true;
                    }
                    
                    // Only add the container if there are any buttons
                    if (hasButtons) {
                        const reviewMetadataEl = reviewDiv.querySelector('.review-metadata');
                        reviewMetadataEl.appendChild(adminButtons);
                    }

                    reviewsList.appendChild(reviewDiv);
                    checkReviewVote(review.id);
                });
                
                // Set up pagination using PornHub style
                setupPornhubPagination(page, data.totalPages, gameId);
            })
            .catch(error => {
                console.error('Error loading reviews:', error);
                document.getElementById('reviews-list').innerHTML = `<p style="color: #ff00ff;">Error: ${error.message}</p>`;
            });
    };
    
    // Replace the game voting functions with these improved versions

// Game voting functions - improved with immediate feedback
window.likeGame = function(gameId) {
    // Get CSRF token directly from meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    // Get button elements
    const likeButton = document.getElementById(`like-button-${gameId}`);
    const dislikeButton = document.getElementById(`dislike-button-${gameId}`);
    
    // Immediately update UI for better feedback
    if (likeButton.classList.contains('active')) {
        // Already liked - do nothing visually yet
    } else {
        // Not already liked - update visual state immediately
        likeButton.classList.add('active');
        likeButton.innerHTML = '✓ LIKED';
        dislikeButton.classList.remove('active');
        dislikeButton.innerHTML = 'DISLIKE';
    }
    
    fetch(`${window.baseUrl}/api.php?action=likeGame&id=${gameId}`, { 
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        // Update counts and refresh UI
        updateGameVotes(gameId);
        
        // No need to call checkUserVote as we've already updated the UI
        // Only show notification if successful
        if (!data.error) {
            if (data.status === 'switched_to_like') {
                showNotification('Changed to like!');
            } else {
                showNotification('Game liked successfully!');
            }
        }
    })
    .catch(error => {
        console.error('Like error:', error);
        // On error, revert the UI changes
        checkUserVote(gameId);
    });
};

window.dislikeGame = function(gameId) {
    // Get CSRF token directly from meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    // Get button elements
    const likeButton = document.getElementById(`like-button-${gameId}`);
    const dislikeButton = document.getElementById(`dislike-button-${gameId}`);
    
    // Immediately update UI for better feedback
    if (dislikeButton.classList.contains('active')) {
        // Already disliked - do nothing visually yet
    } else {
        // Not already disliked - update visual state immediately
        dislikeButton.classList.add('active');
        dislikeButton.innerHTML = '✓ DISLIKED';
        likeButton.classList.remove('active');
        likeButton.innerHTML = 'LIKE';
    }
    
    fetch(`${window.baseUrl}/api.php?action=dislikeGame&id=${gameId}`, { 
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        // Update counts and refresh UI
        updateGameVotes(gameId);
        
        // No need to call checkUserVote as we've already updated the UI
        // Only show notification if successful
        if (!data.error) {
            if (data.status === 'switched_to_dislike') {
                showNotification('Changed to dislike!');
            } else {
                showNotification('Game disliked successfully!');
            }
        }
    })
    .catch(error => {
        console.error('Dislike error:', error);
        // On error, revert the UI changes
        checkUserVote(gameId);
    });
};
    
    // Review voting functions
    window.upvoteReview = function(reviewId) {
        fetchWithAuth(`${window.baseUrl}/api.php?action=upvote&id=${reviewId}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Upvote error:', data.error);
                // Optionally show an error message to the user
            } else {
                // Update UI to reflect the vote
                const upvoteBtn = document.getElementById(`upvote-button-${reviewId}`);
                const downvoteBtn = document.getElementById(`downvote-button-${reviewId}`);
                
                if (upvoteBtn) upvoteBtn.classList.add('active');
                if (downvoteBtn) downvoteBtn.classList.remove('active');
                
                // Reload reviews to update vote count
                const urlParams = new URLSearchParams(window.location.search);
                const gameId = urlParams.get('id');
                loadReviews(1, gameId);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    };

    window.downvoteReview = function(reviewId) {
        fetchWithAuth(`${window.baseUrl}/api.php?action=downvote&id=${reviewId}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Downvote error:', data.error);
                // Optionally show an error message to the user
            } else {
                // Update UI to reflect the vote
                const upvoteBtn = document.getElementById(`upvote-button-${reviewId}`);
                const downvoteBtn = document.getElementById(`downvote-button-${reviewId}`);
                
                if (upvoteBtn) upvoteBtn.classList.remove('active');
                if (downvoteBtn) downvoteBtn.classList.add('active');
                
                // Reload reviews to update vote count
                const urlParams = new URLSearchParams(window.location.search);
                const gameId = urlParams.get('id');
                loadReviews(1, gameId);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    };

    // Review verification functions
    window.verifyReview = function(reviewId) {
        fetchWithAuth(`${window.baseUrl}/api.php?action=reviewAction&reviewAction=verify&id=${reviewId}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                // Reload reviews
                const urlParams = new URLSearchParams(window.location.search);
                const gameId = urlParams.get('id');
                loadReviews(1, gameId);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error verifying review: ' + error.message);
        });
    };
    
    window.unverifyReview = function(reviewId) {
        fetchWithAuth(`${window.baseUrl}/api.php?action=reviewAction&reviewAction=unverify&id=${reviewId}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                // Reload reviews
                const urlParams = new URLSearchParams(window.location.search);
                const gameId = urlParams.get('id');
                loadReviews(1, gameId);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error unverifying review: ' + error.message);
        });
    };
    
    // Delete review function
    window.deleteReview = function(reviewId) {
        if (!confirm("Are you sure you want to delete this review?")) {
            return;
        }
        
        fetchWithAuth(`${window.baseUrl}/api.php?action=reviewAction&reviewAction=delete&id=${reviewId}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                alert('Review deleted successfully!');
                // Reload reviews
                const urlParams = new URLSearchParams(window.location.search);
                const gameId = urlParams.get('id');
                loadReviews(1, gameId);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting review: ' + error.message);
        });
    };

    // Helper functions
    function updateGameVotes(gameId) {
        fetch(`${window.baseUrl}/api.php?action=getGameVotes&id=${gameId}`)
            .then(response => response.json())
            .then(data => {
                const voteDiv = document.getElementById('game-votes');
                if (voteDiv) {
                    voteDiv.innerHTML = data.total === 0 
                        ? 'No votes yet' 
                        : `Likes: ${Math.round((data.likes / data.total) * 100)}% (${data.likes}/${data.total})`;
                }
            })
            .catch(error => console.error('Vote fetch error:', error));
    }
    
    function checkUserVote(gameId) {
        fetch(`${window.baseUrl}/api.php?action=checkUserVote&gameId=${gameId}`)
            .then(response => response.json())
            .then(data => {
                const likeButton = document.getElementById(`like-button-${gameId}`);
                const dislikeButton = document.getElementById(`dislike-button-${gameId}`);
                
                if (!likeButton || !dislikeButton) return;
                
                // Reset both buttons first
                likeButton.disabled = false;
                dislikeButton.disabled = false;
                likeButton.className = '';
                dislikeButton.className = '';
                likeButton.innerHTML = 'LIKE';
                dislikeButton.innerHTML = 'DISLIKE';
                
                if (data.hasVoted) {
                    if (data.vote === 1) {
                        // User liked this game - highlight LIKE button
                        likeButton.className = 'active';
                        likeButton.innerHTML = '✓ LIKED';
                    } else if (data.vote === -1) {
                        // User disliked this game - highlight DISLIKE button
                        dislikeButton.className = 'active';
                        dislikeButton.innerHTML = '✓ DISLIKED';
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
            const upvoteBtn = document.getElementById(`upvote-button-${reviewId}`);
            const downvoteBtn = document.getElementById(`downvote-button-${reviewId}`);
            
            if (data.hasVoted) {
                if (data.voteType === 'upvote') {
                    if (upvoteBtn) upvoteBtn.classList.add('active');
                    if (downvoteBtn) downvoteBtn.classList.remove('active');
                } else {
                    if (upvoteBtn) upvoteBtn.classList.remove('active');
                    if (downvoteBtn) downvoteBtn.classList.add('active');
                }
            } else {
                if (upvoteBtn) upvoteBtn.classList.remove('active');
                if (downvoteBtn) downvoteBtn.classList.remove('active');
            }
        })
        .catch(error => {
            console.error('Error checking vote:', error);
        });
    };

    // Enhanced fetch with authentication and CSRF
    function fetchWithAuth(url, options = {}) {
        const token = getAuthToken();
        // Get CSRF token directly from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        const defaultOptions = {
            credentials: 'include', // Important! Include cookies in the request
            headers: {
                "Authorization": token ? `Bearer ${token}` : "",
                "X-CSRF-Token": csrfToken
            }
        };
        
        // Merge options
        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {})
            }
        };
        
        return fetch(url, mergedOptions);
    }
    
    function getAuthToken() {
        return localStorage.getItem("jwt_token") || "";
    }

    // PornHub style pagination for reviews
    function setupPornhubPagination(currentPage, totalPages, gameId) {
        const paginationDiv = document.getElementById('pagination');
        paginationDiv.innerHTML = '';
        
        if (totalPages <= 1) return;
        
        const ul = document.createElement('ul');
        ul.className = 'pagination';
        
        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = 'page-item prev-page';
        if (currentPage <= 1) prevLi.classList.add('disabled');
        const prevLink = document.createElement('a');
        prevLink.className = 'page-link';
        prevLink.innerHTML = '«';
        prevLink.href = '#';
        prevLink.setAttribute('aria-label', 'Previous');
        if (currentPage > 1) {
            prevLink.addEventListener('click', function(e) {
                e.preventDefault();
                window.loadReviews(currentPage - 1, gameId);
            });
        }
        prevLi.appendChild(prevLink);
        ul.appendChild(prevLi);
        
        // First 3 pages
        for (let i = 1; i <= Math.min(3, totalPages); i++) {
            const li = document.createElement('li');
            li.className = 'page-item' + (i === currentPage ? ' active' : '');
            
            const a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.textContent = i;
            a.onclick = (e) => {
                e.preventDefault();
                window.loadReviews(i, gameId);
            };
            
            li.appendChild(a);
            ul.appendChild(li);
        }
        
        // First ellipsis if needed
        if (totalPages > 3 && currentPage > 4) {
            const ellipsisLi = document.createElement('li');
            ellipsisLi.className = 'page-item disabled';
            ellipsisLi.innerHTML = '<span class="page-link">...</span>';
            ul.appendChild(ellipsisLi);
        }
        
        // Current page if not in first 3 or last
        if (currentPage > 3 && currentPage < totalPages - 2) {
            const li = document.createElement('li');
            li.className = 'page-item active';
            
            const a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.textContent = currentPage;
            a.onclick = (e) => {
                e.preventDefault();
                window.loadReviews(currentPage, gameId);
            };
            
            li.appendChild(a);
            ul.appendChild(li);
        }
        
        // Second ellipsis if needed
        if (totalPages > 6 && currentPage < totalPages - 3) {
            const ellipsisLi = document.createElement('li');
            ellipsisLi.className = 'page-item disabled';
            ellipsisLi.innerHTML = '<span class="page-link">...</span>';
            ul.appendChild(ellipsisLi);
        }
        
        // Last page if not in first 3
        if (totalPages > 3 && (totalPages !== currentPage + 2)) {
            const li = document.createElement('li');
            li.className = 'page-item' + (totalPages === currentPage ? ' active' : '');
            
            const a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.textContent = totalPages;
            a.onclick = (e) => {
                e.preventDefault();
                window.loadReviews(totalPages, gameId);
            };
            
            li.appendChild(a);
            ul.appendChild(li);
        }
        
        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = 'page-item next-page';
        if (currentPage >= totalPages) nextLi.classList.add('disabled');
        const nextLink = document.createElement('a');
        nextLink.className = 'page-link';
        nextLink.innerHTML = '»';
        nextLink.href = '#';
        nextLink.setAttribute('aria-label', 'Next');
        if (currentPage < totalPages) {
            nextLink.addEventListener('click', function(e) {
                e.preventDefault();
                window.loadReviews(currentPage + 1, gameId);
            });
        }
        nextLi.appendChild(nextLink);
        ul.appendChild(nextLi);
        
        paginationDiv.appendChild(ul);
    }

    // Add this simple function to check permissions
    function canModifyReview(reviewUserId) {
        // Current user ID is passed from PHP to window.currentUserId
        const isOwnReview = window.currentUserId && window.currentUserId === reviewUserId;
        // Admin/mod status is passed from PHP to window.isAdminOrModerator
        return window.isAdminOrModerator === true || isOwnReview;
    }
})();