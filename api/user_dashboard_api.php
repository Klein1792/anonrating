<?php
require_once __DIR__ . '/../includes/auth_helper.php';

// Add this header at the top to ensure clean JSON output
header('Content-Type: application/json');

// Make sure no output was sent before
if (ob_get_length()) ob_clean();

// Add this helper function for safe JSON encoding
function jsonSafeEncode($data) {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}

// Add this wrapper function
function sendJsonResponse($data) {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // Set headers again just to be sure
    header('Content-Type: application/json');
    
    // Output the JSON data
    echo jsonSafeEncode($data);
    exit; // Important: exit to prevent any additional output
}

/**
 * Handle all user dashboard API actions
 * 
 * @param string $action The API action
 * @param object $db Database connection
 * @return bool True if the action was handled
 */
function handleUserDashboardAction($action, $db) {
    // Make sure no output was sent before
    if (ob_get_length()) ob_clean();
    
    // Make sure user is authenticated
    if (!isset($_SESSION['user_id'])) {
        echo jsonSafeEncode(['success' => false, 'error' => 'Authentication required']);
        return true;
    }
    
    $user_id = (int)$_SESSION['user_id'];
    
    switch ($action) {
        case 'getBatchUserData':
            return handleGetBatchUserData($db, $user_id);
            
        case 'getUserReviews':
            return handleGetUserReviews($db, $user_id);
            
        case 'getUserComments':
            return handleGetUserComments($db, $user_id);
            
        case 'getUserLikedGames':
            return handleGetUserLikedGames($db, $user_id);
            
        default:
            return false;
    }
}

/**
 * Get reviews created by the user
 * 
 * @param object $db Database connection
 * @param int $user_id User ID
 * @return bool True if handled
 */
function handleGetUserReviews($db, $user_id) {
    try {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        
        // Count total reviews by this user
        $countStmt = $db->prepare('SELECT COUNT(*) as total FROM reviews WHERE user_id = ?');
        $countStmt->bind_param('i', $user_id);
        $countStmt->execute();
        $totalResult = $countStmt->get_result();
        $totalReviews = (int)$totalResult->fetch_assoc()['total'];
        $totalPages = ceil($totalReviews / $limit);
        
        // Get user reviews with game information
        $stmt = $db->prepare("
            SELECT r.*, g.name as game_name, g.cover_url 
            FROM reviews r
            JOIN games g ON r.game_id = g.id
            WHERE r.user_id = ?
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('iii', $user_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Add more error handling
        if ($stmt === false) {
            echo jsonSafeEncode(['success' => false, 'error' => 'Database query error: ' . $db->error]);
            return true;
        }
        
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = [
                'id' => (int)$row['id'],
                'game_id' => (int)$row['game_id'],
                'game_name' => $row['game_name'],
                'title' => $row['title'],
                'content' => $row['content'],
                'rating' => (int)$row['rating'],
                'helpful_votes' => (int)$row['helpful_votes'],
                'not_helpful_votes' => (int)$row['not_helpful_votes'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        
        sendJsonResponse([
            'success' => true,
            'reviews' => $reviews,
            'total_reviews' => $totalReviews,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'per_page' => $limit
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
    
    return true;
}

/**
 * Get comments created by the user
 * 
 * @param object $db Database connection
 * @param int $user_id User ID
 * @return bool True if handled
 */
function handleGetUserComments($db, $user_id) {
    try {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        
        // Count total comments by this user
        $countStmt = $db->prepare('SELECT COUNT(*) as total FROM review_comments WHERE user_id = ?');
        $countStmt->bind_param('i', $user_id);
        $countStmt->execute();
        $totalResult = $countStmt->get_result();
        $totalComments = (int)$totalResult->fetch_assoc()['total'];
        $totalPages = ceil($totalComments / $limit);
        
        // Get user comments with review and game information
        $stmt = $db->prepare("
            SELECT c.*, r.title as review_title, r.game_id, g.name as game_name
            FROM review_comments c
            JOIN reviews r ON c.review_id = r.id
            JOIN games g ON r.game_id = g.id
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('iii', $user_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $comments = [];
        while ($row = $result->fetch_assoc()) {
            $comments[] = [
                'id' => (int)$row['id'],
                'review_id' => (int)$row['review_id'],
                'review_title' => $row['review_title'],
                'game_id' => (int)$row['game_id'],
                'game_name' => $row['game_name'],
                'content' => $row['content'],
                'created_at' => $row['created_at']
            ];
        }
        
        sendJsonResponse([
            'success' => true,
            'comments' => $comments,
            'total_comments' => $totalComments,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'per_page' => $limit
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
    
    return true;
}

/**
 * Get games liked by the user
 * 
 * @param object $db Database connection
 * @param int $user_id User ID
 * @return bool True if handled
 */
function handleGetUserLikedGames($db, $user_id) {
    try {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        
        // Count total liked games by this user
        $countStmt = $db->prepare('SELECT COUNT(*) as total FROM game_votes WHERE user_id = ? AND vote = 1');
        $countStmt->bind_param('i', $user_id);
        $countStmt->execute();
        $totalResult = $countStmt->get_result();
        $totalGames = (int)$totalResult->fetch_assoc()['total'];
        $totalPages = ceil($totalGames / $limit);
        
        // Get liked games with detailed information
        $stmt = $db->prepare("
            SELECT g.id, g.name, g.cover_url, g.avg_rating, g.likes, g.dislikes, gv.created_at as liked_at
            FROM game_votes gv
            JOIN games g ON gv.game_id = g.id
            WHERE gv.user_id = ? AND gv.vote = 1
            ORDER BY gv.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('iii', $user_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $games = [];
        while ($row = $result->fetch_assoc()) {
            $games[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'cover_url' => $row['cover_url'],
                'avg_rating' => ($row['avg_rating'] !== null) ? (float)$row['avg_rating'] : null,
                'likes' => (int)$row['likes'],
                'dislikes' => (int)$row['dislikes'],
                'liked_at' => $row['liked_at']
            ];
        }
        
        sendJsonResponse([
            'success' => true,
            'games' => $games,
            'total_games' => $totalGames,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'per_page' => $limit
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
    
    return true;
}

/**
 * Get batch data for user dashboard (reviews, comments, and liked games in one request)
 * 
 * @param object $db Database connection
 * @param int $user_id User ID
 * @return bool True if handled
 */
function handleGetBatchUserData($db, $user_id) {
    try {
        $reviewsPage = isset($_GET['reviews_page']) ? max(1, (int)$_GET['reviews_page']) : 1;
        $commentsPage = isset($_GET['comments_page']) ? max(1, (int)$_GET['comments_page']) : 1;
        $gamesPage = isset($_GET['games_page']) ? max(1, (int)$_GET['games_page']) : 1;
        $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
        
        // Create response array
        $response = [
            'success' => true,
            'reviews' => null,
            'comments' => null,
            'liked_games' => null
        ];
        
        // Process reviews
        $reviews = [];
        $reviewsOffset = ($reviewsPage - 1) * $limit;
        
        // Get review count
        $countStmt = $db->prepare('SELECT COUNT(*) as total FROM reviews WHERE user_id = ?');
        $countStmt->bind_param('i', $user_id);
        $countStmt->execute();
        $totalResult = $countStmt->get_result();
        $totalReviews = (int)$totalResult->fetch_assoc()['total'];
        $reviewsPages = ceil($totalReviews / $limit);
        
        // Get reviews if there are any
        if ($totalReviews > 0) {
            $reviewsStmt = $db->prepare("
                SELECT r.*, g.name as game_name, g.cover_url 
                FROM reviews r
                JOIN games g ON r.game_id = g.id
                WHERE r.user_id = ?
                ORDER BY r.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $reviewsStmt->bind_param('iii', $user_id, $limit, $reviewsOffset);
            $reviewsStmt->execute();
            $result = $reviewsStmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $reviews[] = [
                    'id' => (int)$row['id'],
                    'game_id' => (int)$row['game_id'],
                    'game_name' => $row['game_name'],
                    'title' => $row['title'],
                    'content' => $row['content'],
                    'rating' => (int)$row['rating'],
                    'helpful_votes' => (int)$row['helpful_votes'],
                    'not_helpful_votes' => (int)$row['not_helpful_votes'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                ];
            }
        }
        
        $response['reviews'] = [
            'items' => $reviews,
            'total' => $totalReviews,
            'pages' => $reviewsPages,
            'current_page' => $reviewsPage
        ];
        
        // Process comments - follow similar pattern
        $comments = [];
        $commentsOffset = ($commentsPage - 1) * $limit;
        
        $countStmt = $db->prepare('SELECT COUNT(*) as total FROM review_comments WHERE user_id = ?');
        $countStmt->bind_param('i', $user_id);
        $countStmt->execute();
        $totalResult = $countStmt->get_result();
        $totalComments = (int)$totalResult->fetch_assoc()['total'];
        $commentsPages = ceil($totalComments / $limit);
        
        if ($totalComments > 0) {
            $commentsStmt = $db->prepare("
                SELECT c.*, r.title as review_title, r.game_id, g.name as game_name
                FROM review_comments c
                JOIN reviews r ON c.review_id = r.id
                JOIN games g ON r.game_id = g.id
                WHERE c.user_id = ?
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $commentsStmt->bind_param('iii', $user_id, $limit, $commentsOffset);
            $commentsStmt->execute();
            $result = $commentsStmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $comments[] = [
                    'id' => (int)$row['id'],
                    'review_id' => (int)$row['review_id'],
                    'review_title' => $row['review_title'],
                    'game_id' => (int)$row['game_id'],
                    'game_name' => $row['game_name'],
                    'content' => $row['content'],
                    'created_at' => $row['created_at']
                ];
            }
        }
        
        $response['comments'] = [
            'items' => $comments,
            'total' => $totalComments,
            'pages' => $commentsPages,
            'current_page' => $commentsPage
        ];
        
        // Process liked games - follow similar pattern
        $games = [];
        $gamesOffset = ($gamesPage - 1) * $limit;
        
        $countStmt = $db->prepare('SELECT COUNT(*) as total FROM game_votes WHERE user_id = ? AND vote = 1');
        $countStmt->bind_param('i', $user_id);
        $countStmt->execute();
        $totalResult = $countStmt->get_result();
        $totalGames = (int)$totalResult->fetch_assoc()['total'];
        $gamesPages = ceil($totalGames / $limit);
        
        if ($totalGames > 0) {
            $gamesStmt = $db->prepare("
                SELECT g.id, g.name, g.cover_url, g.avg_rating, g.likes, g.dislikes, gv.created_at as liked_at
                FROM game_votes gv
                JOIN games g ON gv.game_id = g.id
                WHERE gv.user_id = ? AND gv.vote = 1
                ORDER BY gv.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $gamesStmt->bind_param('iii', $user_id, $limit, $gamesOffset);
            $gamesStmt->execute();
            $result = $gamesStmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $games[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'cover_url' => $row['cover_url'],
                    'avg_rating' => ($row['avg_rating'] !== null) ? (float)$row['avg_rating'] : null,
                    'likes' => (int)$row['likes'],
                    'dislikes' => (int)$row['dislikes'],
                    'liked_at' => $row['liked_at']
                ];
            }
        }
        
        $response['liked_games'] = [
            'items' => $games,
            'total' => $totalGames,
            'pages' => $gamesPages,
            'current_page' => $gamesPage
        ];
        
        // Send the complete batch response
        sendJsonResponse($response);
        
    } catch (Exception $e) {
        sendJsonResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
    
    return true;
}