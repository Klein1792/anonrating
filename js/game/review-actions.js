/**
 * Review Actions Module
 * Handles review actions like editing, deleting, and reporting
 */

(function() {
    'use strict';
    
    /**
     * Edit a review
     * @param {number} reviewId - ID of the review to edit
     */
    function editReview(reviewId) {
        const reviewElement = document.getElementById(`review-${reviewId}`);
        if (!reviewElement) {
            console.error('Review element not found');
            UIUtils.showNotification('Review not found', 'error');
            return;
        }

        const titleElement = reviewElement.querySelector('.review-title');
        const contentElement = reviewElement.querySelector('.review-content');
        const ratingStars = reviewElement.querySelector('.review-rating');

        if (!titleElement || !contentElement || !ratingStars) {
            console.error('Missing review elements');
            UIUtils.showNotification('Unable to edit review. Missing data.', 'error');
            return;
        }

        const currentTitle = titleElement.textContent.trim();
        const currentContent = contentElement.textContent.trim();
        const currentRating = parseInt(ratingStars.textContent.match(/\((\d+)\/10\)/)[1]);

        // Create the edit form
        const editForm = document.createElement('form');
        editForm.id = `edit-review-form-${reviewId}`;
        editForm.className = 'edit-review-form';
        editForm.innerHTML = `
            <div class="form-group">
                <label for="edit-title-${reviewId}">Title:</label>
                <input type="text" id="edit-title-${reviewId}" value="${UIUtils.escapeHtml(currentTitle)}" maxlength="50" required>
            </div>
            <div class="form-group">
                <label for="edit-content-${reviewId}">Review:</label>
                <textarea id="edit-content-${reviewId}" rows="5" required>${UIUtils.escapeHtml(currentContent)}</textarea>
            </div>
            <div class="form-group">
                <label for="edit-rating-${reviewId}">Rating:</label>
                <select id="edit-rating-${reviewId}">
                    ${Array.from({ length: 10 }, (_, i) => i + 1)
                        .map(num => `<option value="${num}" ${num === currentRating ? 'selected' : ''}>${num}/10</option>`)
                        .join('')}
                </select>
            </div>
            <div class="form-buttons">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-secondary cancel-edit-btn">Cancel</button>
            </div>
        `;

        // Hide original content and insert the edit form
        titleElement.style.display = 'none';
        contentElement.style.display = 'none';
        ratingStars.style.display = 'none';
        reviewElement.appendChild(editForm);

        // Handle form submission
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const updatedTitle = document.getElementById(`edit-title-${reviewId}`).value.trim();
            const updatedContent = document.getElementById(`edit-content-${reviewId}`).value.trim();
            const updatedRating = parseInt(document.getElementById(`edit-rating-${reviewId}`).value);

            if (!updatedTitle || !updatedContent || isNaN(updatedRating) || updatedRating < 1 || updatedRating > 10) {
                UIUtils.showNotification('Please fill in all fields with valid values.', 'error');
                return;
            }

            submitEditedReview(reviewId, updatedTitle, updatedContent, updatedRating);
        });

        // Handle cancel button
        editForm.querySelector('.cancel-edit-btn').addEventListener('click', function () {
            editForm.remove();
            titleElement.style.display = '';
            contentElement.style.display = '';
            ratingStars.style.display = '';
        });
    }

    /**
     * Render the edit form for a review
     * @param {number} reviewId - ID of the review
     * @param {Object} review - Review data
     */
    function renderEditForm(reviewId, review) {
        // Create the edit form
        const editFormHTML = `
            <div class="edit-review-form" id="edit-form-${reviewId}">
                <h3>Edit Review</h3>
                <div class="form-group">
                    <label for="edit-review-title-${reviewId}">Title:</label>
                    <input type="text" id="edit-review-title-${reviewId}" class="form-control" value="${UIUtils.escapeHtml(review.title)}" maxlength="50">
                    <span class="char-count" id="edit-title-char-count-${reviewId}">0/50 characters</span>
                </div>
                <div class="form-group">
                    <label for="edit-review-content-${reviewId}">Review:</label>
                    <textarea id="edit-review-content-${reviewId}" class="form-control" rows="4">${UIUtils.escapeHtml(review.content)}</textarea>
                </div>
                <div class="form-group">
                    <label for="edit-review-rating-${reviewId}">Rating (1-10):</label>
                    <input type="range" id="edit-review-rating-${reviewId}" min="1" max="10" value="${review.rating}" class="rating-slider">
                    <span class="rating-value" id="edit-rating-value-${reviewId}">${review.rating}/10</span>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-primary" id="save-edit-${reviewId}">Save Changes</button>
                    <button type="button" class="btn btn-secondary" id="cancel-edit-${reviewId}">Cancel</button>
                </div>
            </div>
        `;
        
        // Find the review container
        const reviewElement = document.getElementById(`review-${reviewId}`);
        if (reviewElement) {
            // Hide the existing content
            const contentDiv = reviewElement.querySelector('.review-content');
            const titleDiv = reviewElement.querySelector('.review-title');
            const ratingDiv = reviewElement.querySelector('.review-rating');
            
            if (contentDiv && titleDiv && ratingDiv) {
                // Store original content in data attributes
                contentDiv.dataset.originalContent = contentDiv.innerHTML;
                titleDiv.dataset.originalContent = titleDiv.innerHTML;
                ratingDiv.dataset.originalContent = ratingDiv.innerHTML;
                
                // Hide original content
                contentDiv.style.display = 'none';
                titleDiv.style.display = 'none';
                ratingDiv.style.display = 'none';
                
                // Hide the actions
                const actionsDiv = reviewElement.querySelector('.review-actions');
                if (actionsDiv) {
                    actionsDiv.dataset.originalDisplay = actionsDiv.style.display;
                    actionsDiv.style.display = 'none';
                }
                
                // Insert the edit form before the content
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = editFormHTML;
                contentDiv.parentNode.insertBefore(tempDiv.firstElementChild, contentDiv);
                
                // Setup character counter for title
                const titleInput = document.getElementById(`edit-review-title-${reviewId}`);
                const charCounter = document.getElementById(`edit-title-char-count-${reviewId}`);
                if (titleInput && charCounter) {
                    charCounter.textContent = `${titleInput.value.length}/50 characters`;
                    
                    titleInput.addEventListener('input', function() {
                        const currentLength = this.value.length;
                        charCounter.textContent = `${currentLength}/50 characters`;
                        
                        if (currentLength > 50) {
                            charCounter.style.color = '#ff0000';
                        } else {
                            charCounter.style.color = '#00ff00';
                        }
                    });
                }
                
                // Setup rating slider
                const ratingSlider = document.getElementById(`edit-review-rating-${reviewId}`);
                const ratingValue = document.getElementById(`edit-rating-value-${reviewId}`);
                if (ratingSlider && ratingValue) {
                    ratingSlider.addEventListener('input', function() {
                        ratingValue.textContent = `${this.value}/10`;
                    });
                }
                
                // Setup save button
                const saveButton = document.getElementById(`save-edit-${reviewId}`);
                if (saveButton) {
                    saveButton.addEventListener('click', function() {
                        saveReviewEdit(reviewId);
                    });
                }
                
                // Setup cancel button
                const cancelButton = document.getElementById(`cancel-edit-${reviewId}`);
                if (cancelButton) {
                    cancelButton.addEventListener('click', function() {
                        cancelReviewEdit(reviewId);
                    });
                }
            }
        }
    }

    /**
     * Save changes to an edited review
     * @param {number} reviewId - ID of the review being edited
     */
    function saveReviewEdit(reviewId) {
        const title = document.getElementById(`edit-review-title-${reviewId}`).value;
        const content = document.getElementById(`edit-review-content-${reviewId}`).value;
        const rating = parseInt(document.getElementById(`edit-review-rating-${reviewId}`).value);
        
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
            id: reviewId,
            title: title,
            content: content,
            rating: rating
        };
        
        // Get fetch function from auth-utils or auth-client
        const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;
        
        fetchFn(`${window.baseUrl}/api.php?action=updateReview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(reviewData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                UIUtils.showNotification('Review updated successfully!', 'success');
                
                // Get the game ID to reload reviews
                const urlParams = new URLSearchParams(window.location.search);
                const gameId = urlParams.get('id');
                
                if (gameId) {
                    window.Reviews.loadReviews(1, gameId);
                }
            } else {
                UIUtils.showNotification('Error: ' + (data.error || 'Failed to update review'), 'error');
                cancelReviewEdit(reviewId);
            }
        })
        .catch(error => {
            UIUtils.showNotification('Error: ' + error.message, 'error');
            cancelReviewEdit(reviewId);
        });
    }
    
    /**
     * Cancel editing a review and restore original content
     * @param {number} reviewId - ID of the review being edited
     */
    function cancelReviewEdit(reviewId) {
        const reviewElement = document.getElementById(`review-${reviewId}`);
        if (!reviewElement) return;

        const editForm = document.getElementById(`edit-review-form-${reviewId}`);
        if (editForm) {
            editForm.remove();
        }

        const titleElement = reviewElement.querySelector('.review-title');
        const contentElement = reviewElement.querySelector('.review-content');
        const ratingStars = reviewElement.querySelector('.review-rating');

        if (titleElement) titleElement.style.display = '';
        if (contentElement) contentElement.style.display = '';
        if (ratingStars) ratingStars.style.display = '';
    }
    
    /**
     * Delete a review after confirmation
     * @param {number} reviewId - ID of the review to delete
     */
   /**
 * Delete a review after confirmation
 * @param {number} reviewId - ID of the review to delete
 */
function deleteReview(reviewId) {
    if (!reviewId) {
        console.error('Invalid review ID');
        UIUtils.showNotification('Invalid review ID', 'error');
        return;
    }

    if (!confirm('Are you sure you want to delete this review? This action cannot be undone.')) {
        return;
    }

    // Get fetch function from auth-utils or auth-client
    const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;

    fetchFn(`${window.baseUrl}/api.php?action=deleteReview`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ reviewId: reviewId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            UIUtils.showNotification('Review deleted successfully.', 'success');
            // Reload reviews
            const urlParams = new URLSearchParams(window.location.search);
            const gameId = urlParams.get('id');
            if (gameId) {
                window.Reviews.loadReviews(1, gameId);
            }
        } else {
            UIUtils.showNotification(data.error || 'Failed to delete review', 'error');
        }
    })
    .catch(error => {
        UIUtils.showNotification('Error: ' + error.message, 'error');
    });
}

/**
 * Submit the edited review to the server
 * @param {number} reviewId - ID of the review being edited
 * @param {string} title - Updated title
 * @param {string} content - Updated content
 * @param {number} rating - Updated rating
 */
function submitEditedReview(reviewId, title, content, rating) {
    const fetchFn = window.gameRating?.auth?.fetchWithAuth || AuthUtils.fetchWithAuth;

    fetchFn(`${window.baseUrl}/api.php?action=editReview`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            reviewId: reviewId,
            title: title,
            content: content,
            rating: rating
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                UIUtils.showNotification('Review updated successfully!', 'success');
                const urlParams = new URLSearchParams(window.location.search);
                const gameId = urlParams.get('id');
                if (gameId) {
                    window.Reviews.loadReviews(1, gameId); // Reload reviews
                }
            } else {
                UIUtils.showNotification(data.error || 'Failed to update review', 'error');
            }
        })
        .catch(error => {
            console.error('Error updating review:', error);
            UIUtils.showNotification('An error occurred while updating your review.', 'error');
        });
}

// Export functions to global scope
window.ReviewActions = {
    editReview,
    deleteReview
};
    
    /**
     * Show the report dialog for a review
     * @param {number} reviewId - ID of the review to report
     */
    function showReportDialog(reviewId) {
        const MAX_DETAILS_LENGTH = 500; // Set the character limit for details
        
        const modal = document.createElement('div');
        modal.className = 'report-modal';
        
        modal.innerHTML = `
            <div class="report-modal-content">
                <h3>Report Review</h3>
                <form id="report-form-${reviewId}">
                    <div class="form-group">
                        <label for="report-reason-${reviewId}">Reason:</label>
                        <select id="report-reason-${reviewId}" class="form-control" required>
                            <option value="">Select a reason</option>
                            <option value="spam">Spam</option>
                            <option value="offensive">Offensive Content</option>
                            <option value="inappropriate">Inappropriate</option>
                            <option value="off-topic">Off Topic</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="report-details-${reviewId}">Additional Details:</label>
                        <textarea id="report-details-${reviewId}" class="form-control" rows="3" maxlength="${MAX_DETAILS_LENGTH}"></textarea>
                        <span id="details-char-count-${reviewId}" class="report-char-count">0/${MAX_DETAILS_LENGTH} characters</span>
                    </div>
                    <div class="form-buttons">
                        <button type="submit" class="submit-report-btn">Submit Report</button>
                        <button type="button" class="cancel-report-btn">Cancel</button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Setup character counter for details
        const detailsInput = document.getElementById(`report-details-${reviewId}`);
        const charCountDisplay = document.getElementById(`details-char-count-${reviewId}`);
        
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
        const reportForm = document.getElementById(`report-form-${reviewId}`);
        if (reportForm) {
            reportForm.addEventListener('submit', function(e) {
                e.preventDefault();
                submitReviewReport(reviewId);
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
     * Submit a report for a review
     * @param {number} reviewId - ID of the review being reported
     */
    function submitReviewReport(reviewId) {
        const reasonSelect = document.getElementById(`report-reason-${reviewId}`);
        const detailsTextarea = document.getElementById(`report-details-${reviewId}`);
        
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
        
        fetchFn(`${window.baseUrl}/api.php?action=reportReview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                reviewId: reviewId,
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
    window.ReviewActions = {
        editReview,
        deleteReview,
        showReportDialog
    };
})();