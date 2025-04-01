<?php
// filepath: e:\bakcup\xampp\htdocs\gamerating\api\reviews.php
require_once __DIR__ . '/../includes/auth_helper.php';
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
    
    // Pagination parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;
    
    // Sorting parameters
    $sortField = isset($_GET['sortField']) ? $_GET['sortField'] : 'created_at';
    $sortDirection = isset($_GET['sortDirection']) ? $_GET['sortDirection'] : 'DESC';
    
    // Validate sort field to prevent SQL injection
    $allowedSortFields = ['created_at', 'rating', 'helpful_votes'];
    if (!in_array($sortField, $allowedSortFields)) {
        $sortField = 'created_at';
    }
    
    // Validate sort direction
    $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get reviews
    $stmt = $db->prepare("
        SELECT r.*, u.username, u.is_admin, u.is_moderator
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.game_id = ?
        ORDER BY r.$sortField $sortDirection
        LIMIT ?, ?
    ");
    
    $stmt->bind_param('iii', $gameId, $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get total reviews count for pagination
    $countStmt = $db->prepare('SELECT COUNT(*) as total FROM reviews WHERE game_id = ?');
    $countStmt->bind_param('i', $gameId);
    $countStmt->execute();
    $totalReviews = (int)$countStmt->get_result()->fetch_assoc()['total'];
    
    // Get user identifier for checking ownership
    $userIdentifier = getCurrentUserIdentifier($db);
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        // Determine if the current user can edit/delete this review
        $canEdit = false;
        $canDelete = false;
        
        if ($userIdentifier['type'] === 'user_id') {
            // Registered user
            $userId = $userIdentifier['value'];
            $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
            $isModerator = isset($_SESSION['is_moderator']) && $_SESSION['is_moderator'];
            
            $isOwner = $row['user_id'] && (int)$row['user_id'] === (int)$userId;
            $canEdit = $isOwner || $isAdmin || $isModerator;
            $canDelete = $isOwner || $isAdmin || $isModerator;
        } else {
            // Anonymous user
            $anonymousToken = $userIdentifier['value'];
            $isOwner = $row['anonymous_token'] && $row['anonymous_token'] === $anonymousToken;
            $canEdit = $isOwner;
            $canDelete = $isOwner;
        }
        
        // Format the review data
        $reviewData = [
            'id' => (int)$row['id'],
            'game_id' => (int)$row['game_id'],
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
            'updated_at' => $row['updated_at'],
            'can_edit' => $canEdit,
            'can_delete' => $canDelete
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