/**
 * Review Comments Module
 * Handles comment functionality for reviews including replies and cooldown timing
 */
(function() {
    'use strict';
    
    const COOLDOWN_TIME = 60000; // 1 minute cooldown in milliseconds
    
    /**
     * Initialize comments for all reviews on the page
     * This should be called when the page loads
     */
    function initializeComments() {
        console.log('Initializing review comments');
        // Find all review comment containers
        const reviewElements = document.querySelectorAll('[id^="comments-"]');
        
        reviewElements.forEach(container => {
            const reviewId = container.id.replace('comments-', '');
            console.log(`Loading comments for review ID: ${reviewId}`);
            loadComments(parseInt(reviewId));
        });
    }
    
    /**
     * Show reply form for a review
     * @param {number} reviewId - ID of the review to reply to
     */
    function showReplyForm(reviewId) {
        if (isInCooldown(reviewId)) {
            const timeLeft = Math.ceil((COOLDOWN_TIME - (Date.now() - parseInt(localStorage.getItem(`comment_cooldown_${reviewId}`) || 0))) / 1000);
            UIUtils.showNotification(`Please wait ${timeLeft} seconds before replying again`, 'error');
            return;
        }
        
        const commentsContainer = document.getElementById(`comments-${reviewId}`);
        if (!commentsContainer) return;
        
        const existingForm = document.getElementById(`comment-form-${reviewId}`);
        if (existingForm) existingForm.remove();
        
        const replyForm = document.createElement('form');
        replyForm.id = `comment-form-${reviewId}`;
        replyForm.className = 'comment-form';
        
        let displayNameOptions = '<option value="Anonymous" selected>Anonymous</option>';
        if (window.currentUser && window.currentUser.username) {
            displayNameOptions += `<option value="${window.currentUser.username}">${window.currentUser.username}</option>`;
        }
        
        replyForm.innerHTML = `
            <div class="form-group">
                <textarea id="comment-content-${reviewId}" placeholder="Write your reply..." rows="2" required></textarea>
            </div>
            ${!window.isAuthenticated ? `
            <div class="form-group">
                <label for="comment-name-${reviewId}">Post as:</label>
                <select id="comment-name-${reviewId}" class="form-control">
                    ${displayNameOptions}
                </select>
            </div>
            ` : ''}
            <div class="form-buttons">
                <button type="submit" class="btn btn-primary">Post Reply</button>
                <button type="button" class="btn btn-secondary cancel-reply-btn">Cancel</button>
            </div>
        `;
        
        commentsContainer.insertBefore(replyForm, commentsContainer.firstChild);
        
        document.getElementById(`comment-content-${reviewId}`).focus();
        
        replyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitComment(reviewId);
        });
        
        replyForm.querySelector('.cancel-reply-btn').addEventListener('click', function() {
            replyForm.remove();
        });
    }
    
    /**
     * Submit a comment to a review
     * @param {number} reviewId - ID of the review being commented on
     */
    function submitComment(reviewId) {
        if (isInCooldown(reviewId)) {
            const timeLeft = Math.ceil((COOLDOWN_TIME - (Date.now() - parseInt(localStorage.getItem(`comment_cooldown_${reviewId}`) || 0))) / 1000);
            UIUtils.showNotification(`Please wait ${timeLeft} seconds before replying again`, 'error');
            return;
        }
        
        const contentElement = document.getElementById(`comment-content-${reviewId}`);
        if (!contentElement || !contentElement.value.trim()) {
            UIUtils.showNotification('Please enter a comment', 'error');
            return;
        }
        
        let displayName = 'Anonymous';
        if (!window.isAuthenticated) {
            const nameSelect = document.getElementById(`comment-name-${reviewId}`);
            if (nameSelect) {
                displayName = nameSelect.value;
            }
        } else if (window.currentUser && window.currentUser.username) {
            displayName = window.currentUser.username;
        }
        
        const commentData = {
            reviewId: reviewId,
            content: contentElement.value.trim(),
            displayName: displayName
        };
        
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;
        
        const submitBtn = document.querySelector(`#comment-form-${reviewId} button[type="submit"]`);
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Posting...';
        }
        
        fetchFn(`${window.baseUrl}/api.php?action=addReviewComment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(commentData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                localStorage.setItem(`comment_cooldown_${reviewId}`, Date.now().toString());
                
                const form = document.getElementById(`comment-form-${reviewId}`);
                if (form) form.remove();
                
                loadComments(reviewId);
                
                // Ensure comments are expanded after posting
                setTimeout(() => {
                    const commentsContent = document.getElementById(`comments-content-${reviewId}`);
                    const toggleButton = document.querySelector(`#comments-${reviewId} .comments-toggle`);
                    
                    if (commentsContent && commentsContent.classList.contains('collapsed')) {
                        commentsContent.classList.remove('collapsed');
                        if (toggleButton) {
                            toggleButton.textContent = 'Hide Replies';
                            toggleButton.setAttribute('aria-expanded', 'true');
                        }
                    }
                }, 100);
                
                UIUtils.showNotification('Reply posted successfully', 'success');
            } else {
                UIUtils.showNotification(data.error || 'Failed to post reply', 'error');
                
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Post Reply';
                }
            }
        })
        .catch(error => {
            UIUtils.showNotification('Error: ' + error.message, 'error');
            
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Post Reply';
            }
        });
    }
    
    /**
     * Load comments for a review with simple show/hide toggle
     * @param {number} reviewId - ID of the review
     */
    function loadComments(reviewId) {
        const commentsContainer = document.getElementById(`comments-${reviewId}`);
        if (!commentsContainer) return;
        
        const existingForm = document.getElementById(`comment-form-${reviewId}`);
        const formHTML = existingForm ? existingForm.outerHTML : '';
        
        commentsContainer.innerHTML = '<div class="comments-loading">Loading comments...</div>' + formHTML;
        
        fetch(`${window.baseUrl}/api.php?action=getReviewComments&reviewId=${reviewId}`)
            .then(response => response.json())
            .then(data => {
                commentsContainer.innerHTML = formHTML;
                
                if (data.success && data.comments && data.comments.length > 0) {
                    // Create toggle button matching the description toggle style
                    const toggleButton = document.createElement('button');
                    toggleButton.className = 'description-toggle show-more';
                    toggleButton.textContent = `Show Replies (${data.comments.length})`;
                    commentsContainer.appendChild(toggleButton);
                    
                    // Create the content container (initially hidden)
                    const commentsContent = document.createElement('div');
                    commentsContent.className = 'description-content';
                    commentsContent.style.display = 'none'; // Start hidden
                    commentsContent.id = `comments-content-${reviewId}`;
                    
                    // Add comments to the content
                    data.comments.forEach(comment => {
                        renderComment(comment, commentsContent);
                    });
                    
                    commentsContainer.appendChild(commentsContent);
                    
                    // Add click behavior
                    toggleButton.addEventListener('click', function() {
                        if (commentsContent.style.display === 'none') {
                            commentsContent.style.display = 'block';
                            this.textContent = `Hide Replies (${data.comments.length})`;
                            this.classList.remove('show-more');
                            this.classList.add('show-less');
                        } else {
                            commentsContent.style.display = 'none';
                            this.textContent = `Show Replies (${data.comments.length})`;
                            this.classList.remove('show-less');
                            this.classList.add('show-more');
                        }
                    });
                } else if (data.success) {
                    if (!existingForm) {
                        const noComments = document.createElement('p');
                        noComments.className = 'no-comments';
                        noComments.textContent = 'No replies yet';
                        commentsContainer.appendChild(noComments);
                    }
                } else {
                    const errorMsg = document.createElement('p');
                    errorMsg.className = 'error-message';
                    errorMsg.textContent = data.error || 'Failed to load comments';
                    commentsContainer.appendChild(errorMsg);
                }
            })
            .catch(error => {
                commentsContainer.innerHTML = `<p class="error-message">Error: ${error.message}</p>` + formHTML;
            });
    }
    
    /**
     * Toggle comments visibility (simplified version)
     * @param {number} reviewId - ID of the review
     */
    function toggleComments(reviewId) {
        const commentsContent = document.getElementById(`comments-content-${reviewId}`);
        const toggleButton = document.querySelector(`#comments-${reviewId} .description-toggle`);
        
        if (!commentsContent || !toggleButton) return;
        
        if (commentsContent.style.display === 'none') {
            commentsContent.style.display = 'block';
            toggleButton.classList.remove('show-more');
            toggleButton.classList.add('show-less');
            toggleButton.textContent = toggleButton.textContent.replace('Show', 'Hide');
        } else {
            commentsContent.style.display = 'none';
            toggleButton.classList.remove('show-less');
            toggleButton.classList.add('show-more');
            toggleButton.textContent = toggleButton.textContent.replace('Hide', 'Show');
        }
    }
    
    /**
     * Check if user is in cooldown period for a specific review
     * @param {number} reviewId - ID of the review
     * @returns {boolean} - Whether the user is in cooldown
     */
    function isInCooldown(reviewId) {
        const key = `comment_cooldown_${reviewId}`;
        const lastTimeStr = localStorage.getItem(key);
        if (!lastTimeStr) return false;
        
        const lastTime = parseInt(lastTimeStr, 10);
        const remaining = COOLDOWN_TIME - (Date.now() - lastTime);
        return remaining > 0;
    }
    /**
     * Render a single comment
     * @param {Object} comment - Comment data
     * @param {Element} container - DOM element to append to
     */
    function renderComment(comment, container) {
        const commentElement = document.createElement('div');
        commentElement.className = 'review-comment';
        commentElement.id = `comment-${comment.id}`;
        
        // Add banned class if the user is banned
        if (comment.is_banned) {
            commentElement.classList.add('comment-banned');
        }
        
        const commentDate = new Date(comment.created_at).toLocaleDateString();
        
        // Add user badges if needed
        let userBadge = '';
        if (comment.is_admin) {
            userBadge = '<span class="admin-badge">Admin</span>';
        } else if (comment.is_moderator) {
            userBadge = '<span class="mod-badge">Moderator</span>';
        } else if (comment.is_banned) {
            userBadge = '<span class="banned-badge">Banned</span>'; // Add the banned badge here
        } else if (!comment.is_anonymous) {
            userBadge = '<span class="user-badge">User</span>';
        }
        
        // Modify content display if user is banned
        const contentDisplay = comment.is_banned ? 
            '<div class="comment-content banned-content">[This comment was posted by a banned user]</div>' : 
            `<div class="comment-content">${comment.content}</div>`;
        
        commentElement.innerHTML = `
            <div class="comment-header">
                <span class="comment-author">${comment.display_name} ${userBadge}</span>
                <span class="comment-date">${commentDate}</span>
            </div>
            ${contentDisplay}
        `;
        
        // Add comment actions
        const commentActions = document.createElement('div');
        commentActions.className = 'comment-actions';
        
        // Only add report button if it's not the current user's comment
        const isCurrentUserComment = window.currentUser && 
            ((window.isAuthenticated && comment.user_id === window.currentUser.id) || 
            (!window.isAuthenticated && comment.is_anonymous));
            
        if (!isCurrentUserComment) {
            const reportBtn = document.createElement('button');
            reportBtn.className = 'report-comment-btn';
            reportBtn.textContent = 'Report';
            reportBtn.onclick = () => showReportCommentDialog(comment.id);
            commentActions.appendChild(reportBtn);
        }
        
        // Add delete button if it's the current user's comment or if user is admin/mod
        if (isCurrentUserComment || (window.currentUser && (window.currentUser.is_admin || window.currentUser.is_moderator))) {
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'delete-comment-btn';
            deleteBtn.textContent = 'Delete';
            deleteBtn.onclick = () => deleteComment(comment.id, comment.review_id);
            commentActions.appendChild(deleteBtn);
        }
        
        // Add ban button for admins/moderators
        if (window.currentUser && (window.currentUser.is_admin || window.currentUser.is_moderator)) {
            const banBtn = document.createElement('button');
            banBtn.className = 'ban-user-btn';
            banBtn.textContent = comment.is_banned ? 'Unban User' : 'Ban User';
            banBtn.onclick = () => ModerationActions.banUserFromComment(comment);
            commentActions.appendChild(banBtn);
        }
        
        commentElement.appendChild(commentActions);
        container.appendChild(commentElement);
    }
    

    
    /**
     * Delete a comment
     * @param {number} commentId - ID of the comment to delete
     * @param {number} reviewId - ID of the parent review
     */
    function deleteComment(commentId, reviewId) {
        if (!confirm('Are you sure you want to delete this comment?')) return;
        
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;
        
        fetchFn(`${window.baseUrl}/api.php?action=deleteReviewComment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ commentId: commentId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                UIUtils.showNotification('Comment deleted successfully', 'success');
                loadComments(reviewId);
            } else {
                UIUtils.showNotification(data.error || 'Failed to delete comment', 'error');
            }
        })
        .catch(error => {
            UIUtils.showNotification('Error: ' + error.message, 'error');
        });
    }
    
    /**
     * Show dialog to report a comment
     * @param {number} commentId - ID of the comment to report
     */
    function showReportCommentDialog(commentId) {
        const MAX_DETAILS_LENGTH = 500;
        
        const modal = document.createElement('div');
        modal.className = 'report-modal';
        
        modal.innerHTML = `
            <div class="report-modal-content">
                <h3>Report Comment</h3>
                <form id="report-comment-form-${commentId}">
                    <div class="form-group">
                        <label for="report-comment-reason-${commentId}">Reason:</label>
                        <select id="report-comment-reason-${commentId}" class="form-control" required>
                            <option value="">Select a reason</option>
                            <option value="spam">Spam</option>
                            <option value="offensive">Offensive Content</option>
                            <option value="inappropriate">Inappropriate</option>
                            <option value="off-topic">Off Topic</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="report-comment-details-${commentId}">Additional Details:</label>
                        <textarea id="report-comment-details-${commentId}" class="form-control" rows="3" maxlength="${MAX_DETAILS_LENGTH}"></textarea>
                        <span id="report-comment-char-count-${commentId}" class="report-char-count">0/${MAX_DETAILS_LENGTH} characters</span>
                    </div>
                    <div class="form-buttons">
                        <button type="submit" class="submit-report-btn">Submit Report</button>
                        <button type="button" class="cancel-report-btn">Cancel</button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Setup character counter
        const detailsInput = document.getElementById(`report-comment-details-${commentId}`);
        const charCountDisplay = document.getElementById(`report-comment-char-count-${commentId}`);
        
        if (detailsInput && charCountDisplay) {
            detailsInput.addEventListener('input', function() {
                const currentLength = this.value.length;
                charCountDisplay.textContent = `${currentLength}/${MAX_DETAILS_LENGTH} characters`;
                
                if (currentLength > MAX_DETAILS_LENGTH) {
                    charCountDisplay.style.color = '#ff0000';
                    detailsInput.value = detailsInput.value.substring(0, MAX_DETAILS_LENGTH);
                } else {
                    charCountDisplay.style.color = '#00ff00';
                }
            });
        }
        
        // Setup form submission
        const reportForm = document.getElementById(`report-comment-form-${commentId}`);
        if (reportForm) {
            reportForm.addEventListener('submit', function(e) {
                e.preventDefault();
                submitCommentReport(commentId);
            });
        }
        
        // Setup cancel button
        const cancelButton = modal.querySelector('.cancel-report-btn');
        if (cancelButton) {
            cancelButton.addEventListener('click', function() {
                modal.remove();
            });
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.remove();
            }
        });
    }
    
    /**
     * Submit a report for a comment
     * @param {number} commentId - ID of the comment being reported
     */
    function submitCommentReport(commentId) {
        const reasonSelect = document.getElementById(`report-comment-reason-${commentId}`);
        const detailsTextarea = document.getElementById(`report-comment-details-${commentId}`);
        
        if (!reasonSelect) return;
        
        const reason = reasonSelect.value;
        const details = detailsTextarea ? detailsTextarea.value : '';
        
        if (!reason) {
            UIUtils.showNotification('Please select a reason for your report.', 'error');
            return;
        }
        
        // Get fetch function from auth-utils or auth-client
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;
        
        // Disable submit button while processing
        const submitBtn = document.querySelector('.submit-report-btn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
        }
        
        fetchFn(`${window.baseUrl}/api.php?action=reportComment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                commentId: commentId,
                reason: reason,
                details: details
            })
        })
        .then(response => response.json())
        .then(data => {
            // Close the modal
            const modal = document.querySelector('.report-modal');
            if (modal) {
                modal.remove();
            }
            
            if (data.success) {
                UIUtils.showNotification('Thank you for your report. Our moderators will review it.', 'success');
            } else {
                UIUtils.showNotification('Error: ' + (data.error || 'Failed to submit report'), 'error');
            }
        })
        .catch(error => {
            UIUtils.showNotification('Error: ' + error.message, 'error');
            
            // Close the modal
            const modal = document.querySelector('.report-modal');
            if (modal) {
                modal.remove();
            }
        });
    }
    

    // Export functions to global scope
    window.ReviewComments = {
        showReplyForm,
        loadComments,
        submitComment,
        deleteComment,
        initializeComments,
        toggleComments
    };
    
    // Auto-initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing comments');
        initializeComments();
    });
})();