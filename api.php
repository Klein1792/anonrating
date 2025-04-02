<?php
// filepath: e:\bakcup\xampp\htdocs\gamerating\api.php

// Include configuration first
require_once 'phpconfig.php';

// Include database connection
require_once 'db_connect.php';

// Now include auth helper after configuration is loaded
require_once 'includes/auth_helper.php';
require_once 'vendor/autoload.php';   
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Error handling configuration
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Set headers for CORS and content type
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

try {
    // Include necessary files
    require_once 'api/auth.php';
    require_once 'db_connect.php';
    require_once 'includes/auth_helper.php';
    require_once 'api/users.php';
    require_once 'api/igdb.php';
    require_once 'api/voting.php';
    require_once 'api/reviews.php';
    require_once 'api/stats.php';
    require_once 'api/batch.php';
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Get request action
    $action = $_GET['action'] ?? '';
    
    // Define public actions that don't need authenticated users
    $publicActions = [
        'getPageviews', 
        'getStatistics', 
        'logout',
        'batch',
        'incrementPageview',
        'games',
        'search',
        'getGameVotes',
        'getGameDetails',
        'getReviewsByGame',
        'getGameDynamicData',
        'checkUserVote',
        'checkReviewVote',
        'login',
        'register',
        'refreshToken',
        'getReviewComments'
    ];
    
    // Define semi-restricted actions that need either authenticated or anonymous users
    $semiRestrictedActions = [
        'voteGame',
        'voteReview',
        'addReview',
        'editReview',
        'deleteReview',
        'reportReview',
        'addReviewComment',
        'deleteReviewComment', // Added: Requires user to be comment owner or admin/moderator
        'reportComment'        // Added: Allows authenticated or anonymous users
    ];
    
    // Define admin-only actions
    $adminActions = [
        'getUsers',
        'getModerators', 
        'getAdmins', 
        'banUser', 
        'unbanUser', 
        'setModerator', 
        'removeModerator', 
        'setAdmin', 
        'removeAdmin',
        'deleteGame',
        'updateGame',
        'addGame',
        'getAnonymousUsers',
        'banAnonymousUser',
        'unbanAnonymousUser',
        'getReportedReviews',
        'getReportDetails',
        'updateReportStatus',
        'adminDeleteReview',
        'getReportedComments',      // Added: Admin/moderator only
        'getCommentReportDetails',  // Added: Admin/moderator only
        'updateCommentReportStatus',// Added: Admin/moderator only
        'adminDeleteComment'        // Added: Admin/moderator only
    ];
    
    // Check if the current action requires authentication
    $requiresAuth = !in_array($action, $publicActions);
    $requiresAdmin = in_array($action, $adminActions);
    $requiresUser = $requiresAuth && !in_array($action, $semiRestrictedActions);
    
    // Extract JWT token if present
    $jwt = null;
    
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $jwt = $matches[1];
        }
    }
    
    // Also check cookies for JWT
    if (empty($jwt) && isset($_COOKIE['access_token'])) {
        $jwt = $_COOKIE['access_token'];
    }
    
    // Check if user is authenticated via JWT
    $isAuthenticated = false;
    $userData = null;
    
    if ($jwt) {
        try {
            $decoded = JWT::decode($jwt, new Key(JWT_SECRET, JWT_ALGORITHM));
            
            // Validate token
            if ($decoded->exp > time() && $decoded->iss === 'gamerating-app') {
                $isAuthenticated = true;
                $userData = [
                    'user_id' => $decoded->user_id,
                    'username' => $decoded->username ?? '',
                    'is_admin' => $decoded->is_admin ?? false,
                    'is_moderator' => $decoded->is_moderator ?? false
                ];
                
                // Store in session for consistency
                $_SESSION['user_id'] = $userData['user_id'];
                $_SESSION['username'] = $userData['username'];
                $_SESSION['is_admin'] = $userData['is_admin'];
                $_SESSION['is_moderator'] = $userData['is_moderator'];
            }
        } catch (Exception $e) {
            // Token invalid, proceed as anonymous
            error_log('JWT validation failed: ' . $e->getMessage());
        }
    }
    
    // Create anonymous user session if not authenticated and needed
    if (!$isAuthenticated && in_array($action, $semiRestrictedActions)) {
        $anonymousUser = ensureAnonymousUser($db);
    }
    
    // Check authorization for protected actions
    if ($requiresUser && !$isAuthenticated) {
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required',
            'code' => 'auth_required'
        ]);
        exit;
    }
    
    if ($requiresAdmin && (!$isAuthenticated || !$userData['is_admin'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Admin privileges required',
            'code' => 'admin_required'
        ]);
        exit;
    }
    
    // Process the API request based on action
    $handled = false;

    // Handle batch requests first
    if (function_exists('handleBatchRequests')) {
        $handled = handleBatchRequests($action, $db);
        if ($handled) {
            if (isset($db) && $db instanceof mysqli) { $db->close(); }
            exit;
        }
    }

    // Auth actions
    if (function_exists('handleAuthActions')) {
        $handled = handleAuthActions($action, $db);
    }

    // IGDB game actions
    if (!$handled && function_exists('handleIgdbActions')) {
        $handled = handleIgdbActions($action, $db, IGDB_CLIENT_ID, IGDB_CLIENT_SECRET);
    }

    if (!$handled && function_exists('handleReviewActions')) {
        $handled = handleReviewActions($action, $db);
    }

    if (!$handled && function_exists('handleVotingActions')) {
        $handled = handleVotingActions($action, $db);
    }

    if (!$handled && function_exists('handleUserActions') && isset($_SESSION['user_id'])) {
        $handled = handleUserActions($action, $db, $_SESSION['user_id']);
    }

    if (!$handled && function_exists('handleSearchActions')) {
        $handled = handleSearchActions($action, $db);
    }

    if (!$handled && function_exists('handleStatsActions')) {
        $handled = handleStatsActions($action, $db);
    }

    // Default built-in actions if not already handled
    if (!$handled) {
        switch ($action) {
            case 'incrementPageview':
                // Increment pageview count
                $db->query("UPDATE pageviews SET total = total + 1 WHERE id = 1");
                echo json_encode(['success' => true, 'message' => 'Pageview incremented']);
                $handled = true;
                break;
            case 'getUserReviews':
                require_once 'api/user_dashboard_api.php';
                handleUserDashboardAction($action, $db);
                break;
                
            case 'getUserComments':
                require_once 'api/user_dashboard_api.php';
                handleUserDashboardAction($action, $db);
                break;
                
            case 'getUserLikedGames':
                require_once 'api/user_dashboard_api.php';
                handleUserDashboardAction($action, $db);
                break;               
            case 'getPageviews':
                // Get pageview count
                $result = $db->query("SELECT total FROM pageviews WHERE id = 1");
                if ($result && $row = $result->fetch_assoc()) {
                    echo json_encode(['success' => true, 'total_pageviews' => $row['total']]);
                } else {
                    // Create pageviews table if doesn't exist
                    $db->query("CREATE TABLE IF NOT EXISTS pageviews (id INT PRIMARY KEY, total BIGINT NOT NULL DEFAULT 0)");
                    $db->query("INSERT IGNORE INTO pageviews (id, total) VALUES (1, 1)");
                    echo json_encode(['success' => true, 'total_pageviews' => 1]);
                }
                $handled = true;
                break;
            
            case 'updateAllGameRatings':
                // Update average ratings for all games
                $result = $db->query('SELECT DISTINCT game_id FROM reviews');
                $updated = 0;
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $gameId = (int)$row['game_id'];
                        
                        // Calculate average
                        $ratingStmt = $db->prepare('
                            SELECT AVG(rating) as avg_rating, COUNT(*) as count
                            FROM reviews
                            WHERE game_id = ?
                        ');
                        $ratingStmt->bind_param('i', $gameId);
                        $ratingStmt->execute();
                        $ratingResult = $ratingStmt->get_result();
                        $stats = $ratingResult->fetch_assoc();
                        
                        // Update game table
                        $updateStmt = $db->prepare('
                            UPDATE games
                            SET avg_rating = ?, review_count = ?, last_rating_update = NOW()
                            WHERE id = ?
                        ');
                        $avgRating = $stats['avg_rating'] ? round($stats['avg_rating'], 1) : NULL;
                        $reviewCount = (int)$stats['count'];
                        $updateStmt->bind_param('dii', $avgRating, $reviewCount, $gameId);
                        $updateStmt->execute();
                        $updated++;
                    }
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Updated ratings for $updated games"
                ]);
                $handled = true;
                break;
                
            case 'getBatchUserData':
                require_once 'api/user_dashboard_api.php';
                handleUserDashboardAction($action, $db);
                $handled = true;
                break;

            default:
                // Unknown action
                if (!empty($action)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
                    $handled = true;
                }
                break;
        }
    }

    // If nothing handled the request
    if (!$handled) {
        echo json_encode(['success' => false, 'error' => 'No action specified']);
    }
} catch (Throwable $e) {
    // Log and report error
    error_log('API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => 'An unexpected error occurred. Please try again later.',
        'debug' => $e->getMessage()
    ]);
}

// Close database connection
if (isset($db) && $db instanceof mysqli) { $db->close(); }
?>