/**
 * Moderation Actions Module
 * Handles moderation actions such as user banning, report management, etc.
 */

(function() {
    'use strict';
    
    /**
     * Ban or unban a user from a review
     * @param {Object} review - The review object containing user data
     */
    function banUserFromReview(review) {
        // Verify current user has moderation privileges
        const currentUser = window.currentUser;
        
        if (!currentUser) {
            console.error("No current user for moderation action");
            UIUtils.showNotification('Authentication required for moderation actions', 'error');
            return;
        }
        
        if (!currentUser.is_admin && !currentUser.is_moderator) {
            console.error("User lacks moderation privileges:", currentUser);
            UIUtils.showNotification('You must be an admin or moderator to ban users', 'error');
            return;
        }

        const isBanned = review.is_banned || (review.is_anonymous && review.anonymous_is_banned) || false;
        const confirmMessage = isBanned ? 
            "Are you sure you want to unban this user?" : 
            "Are you sure you want to ban this user? They will not be able to submit reviews or vote.";
        
        if (!confirm(confirmMessage)) return;
        
        let endpoint;
        
        if (review.is_anonymous && review.anonymous_token) {
            // For anonymous users
            endpoint = `${window.baseUrl}/api.php?action=${isBanned ? 'unbanAnonymousUser' : 'banAnonymousUser'}&token=${review.anonymous_token}`;
        } else if (review.user_id) {
            // For registered users
            endpoint = `${window.baseUrl}/api.php?action=${isBanned ? 'unbanUser' : 'banUser'}&id=${review.user_id}`;
        } else {
            UIUtils.showNotification('Error: Could not determine user type', 'error');
            return;
        }
        
        console.log("Ban endpoint:", endpoint);
        console.log("Review data:", review);
        
        // Get fetch function from auth-utils or auth-client
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;
        
        fetchFn(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })
        .then(response => {
            if (!response.ok) {
                return handleApiError(response);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                UIUtils.showNotification(`User ${isBanned ? 'unbanned' : 'banned'} successfully`, 'success');
                
                // Reload the reviews to reflect changes
                const urlParams = new URLSearchParams(window.location.search);
                const gameId = urlParams.get('id');
                window.Reviews.loadReviews(1, gameId);
            } else {
                UIUtils.showNotification(`Error: ${data.error || 'Unknown error'}`, 'error');
            }
        })
        .catch(error => {
            UIUtils.showNotification(`Error: ${error.message}`, 'error');
            console.error("Ban operation error:", error);
        });
    }
    
    /**
     * Ban or unban a user from a comment
     * @param {Object} comment - The comment object containing user data
     */
    function banUserFromComment(comment) {
        // Verify current user has moderation privileges
        const currentUser = window.currentUser;
        
        if (!currentUser) {
            console.error("No current user for moderation action");
            UIUtils.showNotification('Authentication required for moderation actions', 'error');
            return;
        }
        
        if (!currentUser.is_admin && !currentUser.is_moderator) {
            console.error("User lacks moderation privileges:", currentUser);
            UIUtils.showNotification('You must be an admin or moderator to ban users', 'error');
            return;
        }

        const isBanned = comment.is_banned || false;
        const confirmMessage = isBanned ? 
            "Are you sure you want to unban this user?" : 
            "Are you sure you want to ban this user? They will not be able to submit comments, reviews or vote.";
        
        // Use the styled confirmation dialog instead of basic confirm()
        showConfirmDialog(confirmMessage, isBanned ? 'Yes, Unban' : 'Yes, Ban')
            .then(confirmed => {
                if (!confirmed) return;
                
                let endpoint;
                
                if (comment.is_anonymous && comment.anonymous_token) {
                    // For anonymous users
                    endpoint = `${window.baseUrl}/api.php?action=${isBanned ? 'unbanAnonymousUser' : 'banAnonymousUser'}&token=${comment.anonymous_token}`;
                } else if (comment.user_id) {
                    // For registered users
                    endpoint = `${window.baseUrl}/api.php?action=${isBanned ? 'unbanUser' : 'banUser'}&id=${comment.user_id}`;
                } else {
                    UIUtils.showNotification('Error: Could not determine user type', 'error');
                    return;
                }
                
                // Rest of the existing code...
                console.log("Ban endpoint:", endpoint);
                console.log("Comment data:", comment);
                
                const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;
                
                fetchFn(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        return handleApiError(response);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        UIUtils.showNotification(`User ${isBanned ? 'unbanned' : 'banned'} successfully`, 'success');
                        
                        // Reload the comments to reflect changes
                        if (comment.review_id) {
                            window.ReviewComments.loadComments(comment.review_id);
                        }
                    } else {
                        UIUtils.showNotification(`Error: ${data.error || 'Unknown error'}`, 'error');
                    }
                })
                .catch(error => {
                    UIUtils.showNotification(`Error: ${error.message}`, 'error');
                    console.error("Ban operation error:", error);
                });
            });
    }
    
    /**
     * Handle API errors with better debugging
     * @param {Response} response - Fetch API response object
     * @returns {Promise} - Promise that resolves to error data
     */
    function handleApiError(response) {
        console.log('API Error Response:', response);
        
        return response.text().then(text => {
            try {
                // Try to parse as JSON
                const json = JSON.parse(text);
                console.error('API Error JSON:', json);
                throw new Error(`Server error: ${response.status} - ${json.error || 'Unknown error'}`);
            } catch (e) {
                // If not JSON, return the text
                console.error('API Error Text:', text);
                throw new Error(`Server error: ${response.status} - ${text.substring(0, 100)}`);
            }
        });
    }
    
    /**
     * Show a list of reported reviews for moderation
     */
    function showReportedReviews() {
        // Get fetch function from auth-utils or auth-client
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;
        
        fetchFn(`${window.baseUrl}/api.php?action=getReportedReviews`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.reports) {
                    displayReportedReviewsList(data.reports);
                } else {
                    UIUtils.showNotification('Error loading reported reviews: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                UIUtils.showNotification('Error: ' + error.message, 'error');
            });
    }
    
    /**
     * Display a list of reported reviews in a modal
     * @param {Array} reports - Array of reported reviews
     */
    function displayReportedReviewsList(reports) {
        // Create modal HTML
        let reportsHTML = '';
        
        if (reports.length === 0) {
            reportsHTML = '<p>No reported reviews at this time.</p>';
        } else {
            reportsHTML = `
                <div class="reports-list">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Review</th>
                                <th>Reason</th>
                                <th>Reporter</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            reports.forEach(report => {
                const reportDate = new Date(report.created_at).toLocaleDateString();
                reportsHTML += `
                    <tr>
                        <td>${reportDate}</td>
                        <td>
                            <strong>${UIUtils.escapeHtml(report.review_title)}</strong><br>
                            <small>by ${UIUtils.escapeHtml(report.review_author)}</small>
                        </td>
                        <td>${UIUtils.escapeHtml(report.reason)}<br>
                            <small>${UIUtils.escapeHtml(report.details || '')}</small>
                        </td>
                        <td>${UIUtils.escapeHtml(report.reporter_name || 'Anonymous')}</td>
                        <td>
                            <button class="btn btn-sm btn-view-review" data-review-id="${report.review_id}">View</button>
                            <button class="btn btn-sm btn-dismiss-report" data-report-id="${report.id}">Dismiss</button>
                        </td>
                    </tr>
                `;
            });
            
            reportsHTML += `
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        const modalHTML = `
            <div class="modal" id="reported-reviews-modal">
                <div class="modal-content modal-large">
                    <span class="close-modal" id="close-reports-modal">&times;</span>
                    <h2>Reported Reviews</h2>
                    ${reportsHTML}
                </div>
            </div>
        `;
        
        // Add modal to body
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHTML;
        document.body.appendChild(modalContainer.firstElementChild);
        
        // Get modal element
        const modal = document.getElementById('reported-reviews-modal');
        
        // Show modal
        modal.style.display = 'block';
        
        // Setup close button
        const closeButton = document.getElementById('close-reports-modal');
        if (closeButton) {
            closeButton.onclick = function() {
                modal.remove();
            };
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target === modal) {
                modal.remove();
            }
        };
        
        // Setup view buttons
        const viewButtons = document.querySelectorAll('.btn-view-review');
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const reviewId = this.getAttribute('data-review-id');
                viewReviewFromReport(reviewId);
            });
        });
        
        // Setup dismiss buttons
        const dismissButtons = document.querySelectorAll('.btn-dismiss-report');
        dismissButtons.forEach(button => {
            button.addEventListener('click', function() {
                const reportId = this.getAttribute('data-report-id');
                dismissReport(reportId, this.closest('tr'));
            });
        });
    }
    
    /**
     * View a review from a report
     * @param {number} reviewId - ID of the review to view
     */
    function viewReviewFromReport(reviewId) {
        fetch(`${window.baseUrl}/api.php?action=getReview&id=${reviewId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.review) {
                    displayReportedReview(data.review);
                } else {
                    UIUtils.showNotification('Error loading review: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                UIUtils.showNotification('Error: ' + error.message, 'error');
            });
    }
    
    /**
     * Display a reported review in a modal
     * @param {Object} review - Review data
     */
    function displayReportedReview(review) {
        const filledStars = '★'.repeat(review.rating);
        const emptyStars = '☆'.repeat(10 - review.rating);
        
        const modalHTML = `
            <div class="modal" id="view-report-modal">
                <div class="modal-content modal-large">
                    <span class="close-modal" id="close-view-report-modal">&times;</span>
                    <h2>Reported Review</h2>
                    <div class="reported-review">
                        <h3>${UIUtils.escapeHtml(review.title)}</h3>
                        <div class="review-meta">
                            <div>By: ${UIUtils.escapeHtml(review.display_name)}</div>
                            <div>Date: ${new Date(review.created_at).toLocaleDateString()}</div>
                            <div>Rating: ${filledStars}${emptyStars} (${review.rating}/10)</div>
                        </div>
                        <div class="review-content">${review.content}</div>
                        
                        <div class="moderation-actions">
                            <button class="btn btn-primary" id="delete-reported-review" data-review-id="${review.id}">Delete Review</button>
                            <button class="btn btn-warning" id="ban-reported-user" data-review-id="${review.id}">Ban User</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to body
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHTML;
        document.body.appendChild(modalContainer.firstElementChild);
        
        // Get modal element
        const modal = document.getElementById('view-report-modal');
        
        // Show modal
        modal.style.display = 'block';
        
        // Setup close button
        const closeButton = document.getElementById('close-view-report-modal');
        if (closeButton) {
            closeButton.onclick = function() {
                modal.remove();
            };
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target === modal) {
                modal.remove();
            }
        };
        
        // Setup delete button
        const deleteButton = document.getElementById('delete-reported-review');
        if (deleteButton) {
            deleteButton.addEventListener('click', function() {
                const reviewId = this.getAttribute('data-review-id');
                window.ReviewActions.deleteReview(reviewId);
                modal.remove();
            });
        }
        
        // Setup ban button
        const banButton = document.getElementById('ban-reported-user');
        if (banButton) {
            banButton.addEventListener('click', function() {
                banUserFromReview(review);
                modal.remove();
            });
        }
    }
    
    /**
     * Dismiss a report
     * @param {number} reportId - ID of the report to dismiss
     * @param {Element} rowElement - Table row element to remove
     */
    function dismissReport(reportId, rowElement) {
        if (!confirm('Are you sure you want to dismiss this report?')) {
            return;
        }
        
        // Get fetch function from auth-utils or auth-client
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;
        
        fetchFn(`${window.baseUrl}/api.php?action=dismissReport&id=${reportId}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                UIUtils.showNotification('Report dismissed successfully', 'success');
                
                // Remove row from table
                if (rowElement) {
                    rowElement.remove();
                    
                    // Check if table is now empty
                    const tableBody = document.querySelector('.reports-table tbody');
                    if (tableBody && tableBody.children.length === 0) {
                        const reportsDiv = document.querySelector('.reports-list');
                        if (reportsDiv) {
                            reportsDiv.innerHTML = '<p>No reported reviews at this time.</p>';
                        }
                    }
                }
            } else {
                UIUtils.showNotification('Error: ' + (data.error || 'Failed to dismiss report'), 'error');
            }
        })
        .catch(error => {
            UIUtils.showNotification('Error: ' + error.message, 'error');
        });
    }
    
    /**
     * Show a styled confirmation dialog
     * @param {string} message - The confirmation message to display
     * @param {string} confirmText - Text for the confirm button
     * @returns {Promise<boolean>} - Resolves to true if confirmed, false otherwise
     */
    function showConfirmDialog(message, confirmText = 'Yes') {
        return new Promise((resolve) => {
            let dialog = document.getElementById('confirm-dialog');
            if (!dialog) {
                dialog = document.createElement('div');
                dialog.id = 'confirm-dialog';
                dialog.className = 'confirm-dialog';
                document.body.appendChild(dialog);
            }
            
            dialog.innerHTML = `
                <div class="confirm-dialog-content">
                    <p>${message}</p>
                    <div class="confirm-dialog-buttons">
                        <button id="confirm-yes" class="btn-warning">${confirmText}</button>
                        <button id="confirm-no">Cancel</button>
                    </div>
                </div>
            `;
            
            dialog.style.display = 'flex';
            
            const yesBtn = document.getElementById('confirm-yes');
            const noBtn = document.getElementById('confirm-no');
            
            yesBtn.addEventListener('click', () => {
                dialog.style.display = 'none';
                resolve(true);
            });
            
            noBtn.addEventListener('click', () => {
                dialog.style.display = 'none';
                resolve(false);
            });
            
            // Close when clicking outside the dialog content
            dialog.addEventListener('click', function(event) {
                if (event.target === dialog) {
                    dialog.style.display = 'none';
                    resolve(false);
                }
            });
        });
    }
    
    // Export functions to global scope
    window.ModerationActions = {
        banUserFromReview,
        banUserFromComment,
        showReportedReviews,
        handleApiError,
        showConfirmDialog
    };
})();