<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/review_comments.php'; // Updated path

/**
 * Handle all review-related API actions
 * 
 * @param string $action The API action
 * @param object $db Database connection
 * @return bool True if the action was handled
 */
function handleReviewActions($action, $db) {
    switch ($action) {
        case 'addReview':
            return handleAddReview($db);
            
        case 'editReview':
            return handleEditReview($db);
            
        case 'deleteReview':
            return handleDeleteReview($db);
            
        case 'getReviewsByGame':
            return handleGetReviewsByGame($db);
            
        case 'getRecentReviews':
            return handleGetRecentReviews($db);
            
        case 'reportReview':
            return handleReportReview($db);
            
        case 'getReportedReviews':
            return handleGetReportedReviews($db);
            
        case 'updateReportStatus':
            return handleUpdateReportStatus($db);
            
        case 'adminDeleteReview':
            return handleAdminDeleteReview($db);
            
        case 'getReportDetails':
            return handleGetReportDetails($db);
            
        case 'addReviewComment':
            return handleAddReviewComment($db);
            
        case 'getReviewComments':
            return handleGetReviewComments($db);
            
        case 'deleteReviewComment':
            return handleDeleteReviewComment($db);
            
        case 'reportComment':
            return handleReportComment($db);
            
        case 'getReportedComments':
            return handleGetReportedComments($db);
            
        case 'getCommentReportDetails':
            return handleGetCommentReportDetails($db);
            
        case 'updateCommentReportStatus':
            return handleUpdateCommentReportStatus($db);
            
        case 'adminDeleteComment':
            return handleAdminDeleteComment($db);
            
        default:
            return false;
    }
}

/**
 * Handle adding a new review
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleAddReview($db) {
    // Get review data from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        return true;
    }
    
    // Extract and validate review parameters
    $gameId = isset($data['gameId']) ? (int)$data['gameId'] : 0;
    $title = isset($data['title']) ? trim($data['title']) : '';
    $content = isset($data['content']) ? trim($data['content']) : '';
    $rating = isset($data['rating']) ? (int)$data['rating'] : 0;
    $displayName = isset($data['displayName']) ? trim($data['displayName']) : 'Anonymous'; 
    
    if (!$gameId || empty($title) || empty($content) || $rating < 1 || $rating > 10) {
        echo json_encode(['success' => false, 'error' => 'Invalid review data']);
        return true;
    } 
    
    // Get user identifier (registered user or anonymous)
    $userIdentifier = getCurrentUserIdentifier($db);
    
    // Check if user already reviewed this game (using both user_id and anonymous_token)
    $checkQuery = 'SELECT id FROM reviews WHERE game_id = ? AND (';
    $checkParams = [$gameId];
    $checkTypes = 'i';
    
    if ($userIdentifier['type'] === 'user_id') {
        $userId = $userIdentifier['value'];
        $anonymousToken = $userIdentifier['anonymous_token'];
        
        $checkQuery .= 'user_id = ?';
        $checkParams[] = $userId;
        $checkTypes .= 'i';
        
        if ($anonymousToken) {
            $checkQuery .= ' OR anonymous_token = ?';
            $checkParams[] = $anonymousToken;
            $checkTypes .= 's';
        }
    } else {
        $anonymousToken = $userIdentifier['value'];
        
        // Ensure we have a valid anonymous token
        if (!$anonymousToken) {
            $anonymousUser = ensureAnonymousUser($db);
            $anonymousToken = $anonymousUser['anonymous_token'];
        }
        
        $checkQuery .= 'anonymous_token = ?';
        $checkParams[] = $anonymousToken;
        $checkTypes .= 's';
    }
    
    $checkQuery .= ')';
    
    $stmt = $db->prepare($checkQuery);
    $stmt->bind_param($checkTypes, ...$checkParams);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'You have already reviewed this game']);
        return true;
    }
    
    try {
        $db->begin_transaction();
        
        if ($userIdentifier['type'] === 'user_id') {
            // Registered user - use their username
            $userId = $userIdentifier['value'];
            
            // Get username
            $userStmt = $db->prepare('SELECT username FROM users WHERE id = ?');
            $userStmt->bind_param('i', $userId);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            if ($userResult->num_rows > 0) {
                $displayName = $userResult->fetch_assoc()['username'];
            }
            
            // Insert review
            $stmt = $db->prepare('
                INSERT INTO reviews (game_id, user_id, display_name, title, content, rating) 
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->bind_param('iisssi', $gameId, $userId, $displayName, $title, $content, $rating);
        } else {
            // Anonymous user
            $anonymousToken = $userIdentifier['value'];
            
            // Insert review
            $stmt = $db->prepare('
                INSERT INTO reviews (game_id, anonymous_token, display_name, title, content, rating) 
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->bind_param('issssi', $gameId, $anonymousToken, $displayName, $title, $content, $rating);
        }
        
        $stmt->execute();
        $reviewId = $db->insert_id;
        
        // Update game rating statistics
        updateGameRatingStats($db, $gameId);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Review added successfully',
            'review_id' => $reviewId
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log('Error adding review: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to add review: ' . $e->getMessage()]);
    }
    
    return true;
}

/**
 * Handle editing an existing review
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleEditReview($db) {
    // Get review data from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        return true;
    }
    
    // Extract and validate review parameters
    $reviewId = isset($data['reviewId']) ? (int)$data['reviewId'] : 0;
    $title = isset($data['title']) ? trim($data['title']) : '';
    $content = isset($data['content']) ? trim($data['content']) : '';
    $rating = isset($data['rating']) ? (int)$data['rating'] : 0;
    
    if (!$reviewId || empty($title) || empty($content) || $rating < 1 || $rating > 10) {
        echo json_encode(['success' => false, 'error' => 'Invalid review data']);
        return true;
    }
    
    // Get user identifier (registered user or anonymous)
    $userIdentifier = getCurrentUserIdentifier($db);
    
    // Check if user owns this review or is admin/moderator
    $ownerCheckQuery = 'SELECT game_id FROM reviews WHERE id = ? AND ';
    $ownerParams = [$reviewId];
    $ownerTypes = 'i';
    
    // For registered users
    if ($userIdentifier['type'] === 'user_id') {
        $userId = $userIdentifier['value'];
        
        // Check if user is admin or moderator
        $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
        $isModerator = isset($_SESSION['is_moderator']) && $_SESSION['is_moderator'];
        
        if ($isAdmin || $isModerator) {
            // Admin/mod can edit any review, just check it exists
            $ownerCheckQuery = 'SELECT game_id FROM reviews WHERE id = ?';
        } else {
            // Regular user can only edit their own reviews
            $ownerCheckQuery .= 'user_id = ?';
            $ownerParams[] = $userId;
            $ownerTypes .= 'i';
        }
    } else {
        // Anonymous user
        $anonymousToken = $userIdentifier['value'];
        
        if (!$anonymousToken) {
            echo json_encode(['success' => false, 'error' => 'No permission to edit this review']);
            return true;
        }
        
        $ownerCheckQuery .= 'anonymous_token = ?';
        $ownerParams[] = $anonymousToken;
        $ownerTypes .= 's';
    }
    
    // Check ownership
    $stmt = $db->prepare($ownerCheckQuery);
    $stmt->bind_param($ownerTypes, ...$ownerParams);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'No permission to edit this review']);
        return true;
    }
    
    // Get game ID for rating update later
    $gameId = (int)$result->fetch_assoc()['game_id'];
    
    try {
        $db->begin_transaction();
        
        // Update the review
        $stmt = $db->prepare('
            UPDATE reviews 
            SET title = ?, content = ?, rating = ?, updated_at = NOW()
            WHERE id = ?
        ');
        $stmt->bind_param('ssii', $title, $content, $rating, $reviewId);
        $stmt->execute();
        
        // Update game rating statistics
        updateGameRatingStats($db, $gameId);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Review updated successfully',
            'review_id' => $reviewId
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log('Error updating review: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to update review: ' . $e->getMessage()]);
    }
    
    return true;
}

/**
 * Handle deleting a review
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleDeleteReview($db) {
    // Get review ID from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        return true;
    }
    
    $reviewId = isset($data['reviewId']) ? (int)$data['reviewId'] : 0;
    
    if (!$reviewId) {
        echo json_encode(['success' => false, 'error' => 'Invalid review ID']);
        return true;
    }
    
    // Get user identifier (registered user or anonymous)
    $userIdentifier = getCurrentUserIdentifier($db);
    
    // Check if user owns this review or is admin/moderator
    $ownerCheckQuery = 'SELECT game_id FROM reviews WHERE id = ? AND ';
    $ownerParams = [$reviewId];
    $ownerTypes = 'i';
    
    // For registered users
    if ($userIdentifier['type'] === 'user_id') {
        $userId = $userIdentifier['value'];
        
        // Check if user is admin or moderator
        $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
        $isModerator = isset($_SESSION['is_moderator']) && $_SESSION['is_moderator'];
        
        if ($isAdmin || $isModerator) {
            // Admin/mod can delete any review, just check it exists
            $ownerCheckQuery = 'SELECT game_id FROM reviews WHERE id = ?';
        } else {
            // Regular user can only delete their own reviews
            $ownerCheckQuery .= 'user_id = ?';
            $ownerParams[] = $userId;
            $ownerTypes .= 'i';
        }
    } else {
        // Anonymous user
        $anonymousToken = $userIdentifier['value'];
        
        if (!$anonymousToken) {
            echo json_encode(['success' => false, 'error' => 'No permission to delete this review']);
            return true;
        }
        
        $ownerCheckQuery .= 'anonymous_token = ?';
        $ownerParams[] = $anonymousToken;
        $ownerTypes .= 's';
    }
    
    // Check ownership
    $stmt = $db->prepare($ownerCheckQuery);
    $stmt->bind_param($ownerTypes, ...$ownerParams);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'No permission to delete this review']);
        return true;
    }
    
    // Get game ID for rating update later
    $gameId = (int)$result->fetch_assoc()['game_id'];
    
    try {
        $db->begin_transaction();
        
        // Delete the review
        $stmt = $db->prepare('DELETE FROM reviews WHERE id = ?');
        $stmt->bind_param('i', $reviewId);
        $stmt->execute();
        
        // Also delete associated votes
        $stmt = $db->prepare('DELETE FROM review_votes WHERE review_id = ?');
        $stmt->bind_param('i', $reviewId);
        $stmt->execute();
        
        // Update game rating statistics
        updateGameRatingStats($db, $gameId);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Review deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log('Error deleting review: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to delete review: ' . $e->getMessage()]);
    }
    
    return true;
}

/**
 * Handle retrieving reviews for a specific game
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleGetReviewsByGame($db) {
    $gameId = isset($_GET['gameId']) ? (int)$_GET['gameId'] : 0;
    
    if (!$gameId) {
        echo json_encode(['success' => false, 'error' => 'Invalid game ID']);
        return true;
    }
    
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;
    
    $sortField = isset($_GET['sortField']) ? $_GET['sortField'] : 'created_at';
    $sortDirection = isset($_GET['sortDirection']) ? $_GET['sortDirection'] : 'DESC';
    
    $allowedSortFields = ['created_at', 'rating', 'helpful_votes'];
    if (!in_array($sortField, $allowedSortFields)) {
        $sortField = 'created_at';
    }
    
    $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
    
    // Modified query to include comment count
    $stmt = $db->prepare("
        SELECT r.*, 
               u.username, u.is_admin, u.is_moderator, u.is_banned,
               a.is_banned AS anonymous_is_banned,
               r.anonymous_token,
               (SELECT COUNT(*) FROM review_comments rc WHERE rc.review_id = r.id) AS comment_count
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN anonymous_users a ON r.anonymous_token = a.token
        WHERE r.game_id = ?
        ORDER BY $sortField $sortDirection
        LIMIT ?, ?
    ");
    
    $stmt->bind_param('iii', $gameId, $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $countStmt = $db->prepare('SELECT COUNT(*) as total FROM reviews WHERE game_id = ?');
    $countStmt->bind_param('i', $gameId);
    $countStmt->execute();
    $totalReviews = (int)$countStmt->get_result()->fetch_assoc()['total'];
    
    $userIdentifier = getCurrentUserIdentifier($db);
    
    $isAdminOrMod = false;
    if ($userIdentifier['type'] === 'user_id') {
        $isAdminOrMod = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] || 
                       isset($_SESSION['is_moderator']) && $_SESSION['is_moderator'];
    }
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $canEdit = false;
        $canDelete = false;
        
        if ($userIdentifier['type'] === 'user_id') {
            $userId = $userIdentifier['value'];
            $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
            $isModerator = isset($_SESSION['is_moderator']) && $_SESSION['is_moderator'];
            
            $isOwner = $row['user_id'] && (int)$row['user_id'] === (int)$userId;
            $canEdit = $isOwner || $isAdmin || $isModerator;
            $canDelete = $isOwner || $isAdmin || $isModerator;
        } else {
            $anonymousToken = $userIdentifier['value'];
            $isOwner = $row['anonymous_token'] && $row['anonymous_token'] === $anonymousToken;
            $canEdit = $isOwner;
            $canDelete = $isOwner;
        }
        
        $reviewData = [
            'id' => (int)$row['id'],
            'game_id' => (int)$row['game_id'],
            'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
            'display_name' => $row['username'] ?? $row['display_name'],
            'is_anonymous' => !empty($row['anonymous_token']),
            'anonymous_token' => ($isAdminOrMod && $row['anonymous_token']) ? $row['anonymous_token'] : null,
            'is_banned' => (bool)($row['is_banned'] ?? $row['anonymous_is_banned'] ?? false),
            'is_admin' => (bool)($row['is_admin'] ?? false),
            'is_moderator' => (bool)($row['is_moderator'] ?? false),
            'title' => $row['title'],
            'content' => $row['content'],
            'rating' => (int)$row['rating'],
            'helpful_votes' => (int)$row['helpful_votes'],
            'not_helpful_votes' => (int)$row['not_helpful_votes'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'comment_count' => (int)$row['comment_count'] // Add comment count to response
        ];
        
        $reviews[] = $reviewData;
    }
    
    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalReviews / $limit),
            'total_reviews' => $totalReviews,
            'per_page' => $limit
        ]
    ]);
    
    return true;
}

/**
 * Get recent reviews across all games
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleGetRecentReviews($db) {
    // Pagination parameters
    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
    
    $stmt = $db->prepare("
        SELECT r.*, g.name as game_name, g.cover_url, u.username, u.is_admin, u.is_moderator
        FROM reviews r
        JOIN games g ON r.game_id = g.id
        LEFT JOIN users u ON r.user_id = u.id
        ORDER BY r.created_at DESC
        LIMIT ?
    ");
    
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        // Format the review data
        $reviewData = [
            'id' => (int)$row['id'],
            'game_id' => (int)$row['game_id'],
            'game_name' => $row['game_name'],
            'cover_url' => $row['cover_url'],
            'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
            'display_name' => $row['username'] ?? $row['display_name'],
            'is_anonymous' => !$row['user_id'],
            'is_admin' => (bool)($row['is_admin'] ?? false),
            'is_moderator' => (bool)($row['is_moderator'] ?? false),
            'title' => $row['title'],
            'content' => $row['content'],
            'rating' => (int)$row['rating'],
            'helpful_votes' => (int)$row['helpful_votes'],
            'not_helpful_votes' => (int)$row['not_helpful_votes'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
        
        $reviews[] = $reviewData;
    }
    
    echo json_encode([
        'success' => true,
        'reviews' => $reviews
    ]);
    
    return true;
}

/**
 * Handle reporting a review as inappropriate
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleReportReview($db) {
    // Get user identifier (registered user or anonymous)
    $userIdentifier = getCurrentUserIdentifier($db);
    
    // Parse request body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data || !isset($data['reviewId']) || !isset($data['reason'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return true;
    }
    
    $reviewId = (int)$data['reviewId'];
    $reason = $data['reason'];
    $details = $data['details'] ?? '';
    
    // Check if this review exists
    $stmt = $db->prepare('SELECT id FROM reviews WHERE id = ?');
    $stmt->bind_param('i', $reviewId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Review not found']);
        return true;
    }
    
    // Check if user has already reported this review
    $stmt = $db->prepare('SELECT id FROM review_reports 
                         WHERE review_id = ? AND reporter_type = ? AND reporter_value = ?');
                         
    $stmt->bind_param('iss', $reviewId, $userIdentifier['type'], $userIdentifier['value']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'You have already reported this review']);
        return true;
    }
    
    // Insert the report
    $stmt = $db->prepare('INSERT INTO review_reports 
                        (review_id, reporter_type, reporter_value, reason, details) 
                        VALUES (?, ?, ?, ?, ?)');
                        
    $stmt->bind_param('issss', $reviewId, $userIdentifier['type'], $userIdentifier['value'], $reason, $details);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Review reported successfully']);
        return true;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to report review: ' . $db->error]);
        return true;
    }
}

/**
 * Handle getting reported reviews for admins
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleGetReportedReviews($db) {
    // First check if the user is an admin or moderator
    $user_id = $_SESSION['user_id'] ?? 0;
    $stmt = $db->prepare('SELECT is_admin, is_moderator FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: User not found']);
        return true;
    }
    
    $user = $result->fetch_assoc();
    if (!$user['is_admin'] && !$user['is_moderator']) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Admin or moderator required']);
        return true;
    }
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;
    
    // Count total reports
    $countStmt = $db->prepare('SELECT COUNT(*) as total FROM review_reports');
    $countStmt->execute();
    $totalResult = $countStmt->get_result();
    $totalReports = $totalResult->fetch_assoc()['total'];
    $totalPages = ceil($totalReports / $limit);
    
    // Get reports with related data
    $stmt = $db->prepare("
        SELECT r.id, r.review_id, r.reporter_type, r.reporter_value, r.reason, r.details, 
               r.status, r.created_at, r.updated_at, 
               rv.content as review_content, rv.game_id,
               g.name as game_name,
               CASE 
                    WHEN r.reporter_type = 'user_id' THEN u.username
                    ELSE NULL
               END as reporter_name
        FROM review_reports r
        JOIN reviews rv ON r.review_id = rv.id
        JOIN games g ON rv.game_id = g.id
        LEFT JOIN users u ON r.reporter_type = 'user_id' AND r.reporter_value = u.id
        ORDER BY 
            CASE 
                WHEN r.status = 'pending' THEN 1
                WHEN r.status = 'reviewing' THEN 2
                WHEN r.status = 'rejected' THEN 3
                WHEN r.status = 'actioned' THEN 4
                ELSE 5
            END,
            r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'reports' => $reports,
        'total_reports' => $totalReports,
        'total_pages' => $totalPages,
        'current_page' => $page
    ]);
    return true;
}

/**
 * Handle updating report status
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleUpdateReportStatus($db) {
    // First check if the user is an admin or moderator
    $user_id = $_SESSION['user_id'] ?? 0;
    $stmt = $db->prepare('SELECT is_admin, is_moderator FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: User not found']);
        return true;
    }
    
    $user = $result->fetch_assoc();
    if (!$user['is_admin'] && !$user['is_moderator']) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Admin or moderator required']);
        return true;
    }
    
    $reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    
    if (empty($reportId) || empty($status)) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        return true;
    }
    
    // Validate status
    $validStatuses = ['pending', 'reviewing', 'rejected', 'actioned'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        return true;
    }
    
    // Update report status
    $stmt = $db->prepare('UPDATE review_reports SET status = ?, handled_by = ? WHERE id = ?');
    $stmt->bind_param('sii', $status, $user_id, $reportId);
    $result = $stmt->execute();
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Report status updated']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update report: ' . $db->error]);
    }
    
    return true;
}

/**
 * Handle admin deletion of a review
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleAdminDeleteReview($db) {
    // First check if the user is an admin or moderator
    $user_id = $_SESSION['user_id'] ?? 0;
    $stmt = $db->prepare('SELECT is_admin, is_moderator FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: User not found']);
        return true;
    }
    
    $user = $result->fetch_assoc();
    if (!$user['is_admin'] && !$user['is_moderator']) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Admin or moderator required']);
        return true;
    }
    
    $reviewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (empty($reviewId)) {
        echo json_encode(['success' => false, 'error' => 'Missing review ID']);
        return true;
    }
    
    // Delete the review
    $stmt = $db->prepare('DELETE FROM reviews WHERE id = ?');
    $stmt->bind_param('i', $reviewId);
    $result = $stmt->execute();
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Review successfully removed']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete review: ' . $db->error]);
    }
    
    return true;
}

/**
 * Get detailed information about a specific report
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleGetReportDetails($db) {
    // First check if the user is an admin or moderator
    $user_id = $_SESSION['user_id'] ?? 0;
    $stmt = $db->prepare('SELECT is_admin, is_moderator FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0 || !($user = $result->fetch_assoc()) || (!$user['is_admin'] && !$user['is_moderator'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return true;
    }
    
    $reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (empty($reportId)) {
        echo json_encode(['success' => false, 'error' => 'Missing report ID']);
        return true;
    }
    
    // Get full report details
    $stmt = $db->prepare("
        SELECT r.id, r.review_id, r.reporter_type, r.reporter_value, r.reason, r.details, 
               r.status, r.created_at, r.updated_at, 
               rv.content as review_content, rv.game_id, rv.user_id as reviewer_id,
               g.name as game_name,
               CASE 
                    WHEN r.reporter_type = 'user_id' THEN u_reporter.username
                    ELSE NULL
               END as reporter_name,
               u_reviewer.username as reviewer_name
        FROM review_reports r
        JOIN reviews rv ON r.review_id = rv.id
        JOIN games g ON rv.game_id = g.id
        JOIN users u_reviewer ON rv.user_id = u_reviewer.id
        LEFT JOIN users u_reporter ON r.reporter_type = 'user_id' AND r.reporter_value = u_reporter.id
        WHERE r.id = ?
    ");
    
    $stmt->bind_param('i', $reportId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Report not found']);
        return true;
    }
    
    $report = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'report' => $report
    ]);
    
    return true;
}

/**
 * Update the average rating and review count for a game
 * 
 * @param object $db Database connection
 * @param int $gameId Game ID
 * @return void
 */
function updateGameRatingStats($db, $gameId) {
    $stmt = $db->prepare('
        SELECT AVG(rating) as avg_rating, COUNT(*) as count
        FROM reviews
        WHERE game_id = ?
    ');
    $stmt->bind_param('i', $gameId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    
    // Update game table
    $stmt = $db->prepare('
        UPDATE games
        SET avg_rating = ?, review_count = ?, last_rating_update = NOW()
        WHERE id = ?
    ');
    $avgRating = $stats['avg_rating'] ? round($stats['avg_rating'], 1) : 0;
    $reviewCount = (int)$stats['count'];
    $stmt->bind_param('dii', $avgRating, $reviewCount, $gameId);
    $stmt->execute();
}

