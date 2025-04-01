/**
 * Reviews Module
 * Handles loading, rendering and pagination of game reviews
 */

(function() {
    'use strict';
    
    /**
     * Load reviews for a specific game
     * @param {number} page - Page number to load
     * @param {number|string} gameId - ID of the game to load reviews for
     */
    function loadReviews(page, gameId) {
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
                
                // Add this line to initialize comments after reviews are loaded
                if (window.ReviewComments && window.ReviewComments.initializeComments) {
                    window.ReviewComments.initializeComments();
                }
            })
            .catch(error => {
                document.getElementById('reviews-list').innerHTML = `<p class="error">Error: ${error.message}</p>`;
            });
            
    }
    
    /**
     * Render a single review
     * @param {Object} review - Review object from the API
     * @param {Element} container - DOM element to append the review to
     */
    function renderReview(review, container) {
        console.log("Review object:", review);

        // Ensure currentUser is properly initialized
        const currentUser = window.currentUser || null;
        console.log("Current user:", currentUser);

        const reviewDiv = document.createElement('div');
        reviewDiv.className = 'review-item';
        reviewDiv.id = `review-${review.id}`;
        // Add banned class if the user is banned
        if (review.is_banned) {
            reviewDiv.classList.add('review-banned');
        }
        
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
        helpfulBtn.onclick = () => ReviewVotes.voteReviewHelpful(review.id);
        actionsDiv.appendChild(helpfulBtn);
        
        const unhelpfulBtn = document.createElement('button');
        unhelpfulBtn.id = `unhelpful-button-${review.id}`;
        unhelpfulBtn.className = 'unhelpful-btn';
        unhelpfulBtn.textContent = 'Not Helpful';
        unhelpfulBtn.onclick = () => ReviewVotes.voteReviewUnhelpful(review.id);
        actionsDiv.appendChild(unhelpfulBtn);
        
        if (review.can_edit) {
            const editBtn = document.createElement('button');
            editBtn.className = 'edit-review-btn';
            editBtn.textContent = 'Edit';
            editBtn.onclick = () => ReviewActions.editReview(review.id);
            actionsDiv.appendChild(editBtn);
        }
        
        if (review.can_delete) {
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'delete-review-btn';
            deleteBtn.textContent = 'Delete';
            deleteBtn.onclick = () => ReviewActions.deleteReview(review.id);
            actionsDiv.appendChild(deleteBtn);
        }
        
        // Add ban user button for admins/moderators
        if (currentUser && (currentUser.is_admin || currentUser.is_moderator)) {
            const banUserBtn = document.createElement('button');
            banUserBtn.className = 'ban-user-btn';
            banUserBtn.textContent = review.is_banned ? 'Unban User' : 'Ban User';
            banUserBtn.onclick = () => ModerationActions.banUserFromReview(review);
            actionsDiv.appendChild(banUserBtn); // This is correct - adding to actionsDiv
        }
        
        // Report button for all users
        const reportBtn = document.createElement('button');
        reportBtn.className = 'report-review-btn';
        reportBtn.textContent = 'Report';
        reportBtn.onclick = () => ReviewActions.showReportDialog(review.id);
        actionsDiv.appendChild(reportBtn);
        
        // Add Reply button after the existing buttons in actionsDiv
        const replyBtn = document.createElement('button');
        replyBtn.className = 'reply-review-btn';
        replyBtn.textContent = 'Reply';
        replyBtn.onclick = () => ReviewComments.showReplyForm(review.id);
        actionsDiv.appendChild(replyBtn);
        
        reviewDiv.appendChild(actionsDiv);
        
        // Add a container for comments
        const commentsContainer = document.createElement('div');
        commentsContainer.className = 'review-comments';
        commentsContainer.id = `comments-${review.id}`;
        reviewDiv.appendChild(commentsContainer);
        
        // Load existing comments
        ReviewComments.loadComments(review.id);
        
        container.appendChild(reviewDiv);
        
        ReviewVotes.checkReviewVote(review.id);
    }
    
    /**
     * Sets up pagination controls for reviews
     * @param {number} currentPage - Current page number
     * @param {number} totalPages - Total pages available
     * @param {number|string} gameId - Game ID
     */
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
                loadReviews(currentPage - 1, gameId);
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
                    loadReviews(i, gameId);
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
                loadReviews(currentPage + 1, gameId);
            });
            nextLi.appendChild(nextLink);
            ul.appendChild(nextLi);
        }
        
        paginationDiv.appendChild(ul);
    }
    
    /**
     * Setup the review form
     * @param {number|string} gameId - ID of the game 
     */
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
    
    /**
     * Update the display name dropdown for anonymous reviews 
     */
    function updateDisplayNameDropdown() {
        const anonymousNameField = document.getElementById('anonymous-name-field');
        if (!anonymousNameField) return;
        
        const gameRatingAuth = window.gameRating?.auth;
        const currentUser = window.currentUser;
        const isAuthenticated = window.isAuthenticated;
        
        let username = null;
        if (gameRatingAuth?.getCurrentUser?.()) {
            username = gameRatingAuth.getCurrentUser().username;
        } else if (currentUser && currentUser.username) {
            username = currentUser.username;
        } else {
            const token = AuthUtils.getCookie('access_token');
            if (token) {
                try {
                    const tokenData = AuthUtils.parseJwt(token);
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
    
    /**
     * Submit a review
     * @param {number|string} gameId - ID of the game being reviewed
     */
    function submitReview(gameId) {
        if (window.ChallengeSystem && !window.ChallengeSystem.isSequenceCompleted()) {
            UIUtils.showNotification('Please complete the arrow key sequence challenge first', 'error');
            return;
        }
        
        const title = document.getElementById('review-title').value;
        const content = document.getElementById('review-content').value;
        const rating = parseInt(document.getElementById('review-rating').value);
        
        let displayName = 'Anonymous';
        if (!window.isAuthenticated) {
            const displayNameSelect = document.getElementById('anonymous-name');
            if (displayNameSelect) {
                displayName = displayNameSelect.value;
            }
        } else if (window.currentUser && window.currentUser.username) {
            displayName = window.currentUser.username;
        }
        
        if (!title.trim() || !content.trim() || isNaN(rating) || rating < 1 || rating > 10) {
            UIUtils.showNotification('Please fill in all required fields and provide a rating between 1 and 10.', 'error');
            return;
        }
        
        const maxTitleLength = 50;
        if (title.length > maxTitleLength) {
            UIUtils.showNotification(`Review title is too long. Please keep it under ${maxTitleLength} characters.`, 'error');
            return;
        }
        
        const reviewData = {
            gameId: parseInt(gameId),
            title: title,
            content: content,
            rating: rating
        };
        
        if (!window.isAuthenticated) {
            reviewData.displayName = displayName;
        }
        
        const submitBtn = document.querySelector('#review-form button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Submitting...';
        }
        
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;
        
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
                
                UIUtils.showNotification('Review submitted successfully!', 'success');
                
                if (window.ChallengeSystem) {
                    window.ChallengeSystem.generateSymbolChallenge();
                }
                
                loadReviews(1, gameId);
            } else {
                UIUtils.showNotification('Error: ' + (data.error || 'Failed to submit review'), 'error');
            }
        })
        .catch(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Submit Review';
            }
            UIUtils.showNotification('An error occurred while submitting your review.', 'error');
        });
    }
    
    // Export functions to global scope
    window.Reviews = {
        loadReviews,
        renderReview,
        setupPagination,
        setupReviewForm,
        updateDisplayNameDropdown,
        submitReview
    };
})();