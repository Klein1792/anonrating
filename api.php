<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Set headers immediately
header('Content-Type: application/json');

// Set up CORS headers first
$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? 'https://localhost:8080';
if (in_array($allowedOrigin, ['https://localhost:8080', 'https://localhost:8080'])) {
    header("Access-Control-Allow-Origin: $allowedOrigin");
} else {
    header("Access-Control-Allow-Origin: https://localhost:8080");
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, X-CSRF-Token, Content-Type");
header("Access-Control-Allow-Credentials: true");

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include 'db_connect.php';
include 'api/igdb.php';
include 'phpconfig.php';
include 'api/reviews.php';
include 'api/users.php';
include 'api/voting.php';
include 'api/auth.php';
include 'api/rate_limiter.php';
include 'includes/auth_helper.php';

// First, get the action so we can determine if authentication is needed
$action = $_GET['action'] ?? '';

// Function to validate JWT token
function validateJWT($jwtSecret) {
    $token = null;
    if (isset($_COOKIE['access_token'])) {
        $token = $_COOKIE['access_token'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }

    if (!$token) {
        return null;
    }

    try {
        $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));
        return $decoded->user_id;
    } catch (Exception $e) {
        error_log("JWT validation error: " . $e->getMessage());
        return null;
    }
}

// Define public actions that don't need authentication
$publicActions = [
    'getPageviews', 
    'getStatistics', 
    'logout',
    'incrementPageview',
    'games',
    'getGenres',
    'getPlatforms',
    'search',
    'getGameVotes',
    'getGameDetails',
    'getReviewsByGame',
    'checkUserVote',
    'checkReviewVote',
    'likeGame',
    'dislikeGame',
    'add'
];

// Get user ID from JWT if available
$user_id = null;
$requiresAuth = true;

// Check if this is a public action that doesn't need authentication
if (in_array($action, $publicActions)) {
    $requiresAuth = false;
} else if ($action === 'games') {
    $gameType = $_GET['type'] ?? '';
    $publicGameTypes = ['recent', 'featured', 'genre', 'platform'];
    if (in_array($gameType, $publicGameTypes)) {
        $requiresAuth = false;
    }
}

// Try to get the user ID from JWT or session
$jwtSecret = SECRET_KEY;
$user_id = validateJWT($jwtSecret);

// Fallback to session if JWT is invalid or missing
if ($user_id === null && isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    // Optionally, regenerate a new JWT token if the session is still valid
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $stmt = $db->prepare('INSERT INTO access_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $user_id, $token, $expiresAt);
    $stmt->execute();
    $stmt->close();
    $_SESSION['access_token'] = $token;
    setcookie('access_token', $token, [
        'expires' => strtotime($expiresAt),
        'path' => '/',
        'httponly' => false,
        'samesite' => 'Strict'
    ]);
}

// Only require authentication for protected endpoints
if ($requiresAuth && $user_id === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Valid authentication required for this action']);
    exit;
}

// Validate CSRF token for POST requests (except for public actions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $publicActions)) {
    $headers = getallheaders();
    $csrfToken = isset($headers['X-CSRF-Token']) ? $headers['X-CSRF-Token'] : (isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null);

    if (!$csrfToken || !isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

// Check database connection
if (!$db || $db->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . ($db ? $db->connect_error : 'No DB object')]);
    exit;
}

// Auth-related API endpoints
elseif ($action === 'check_auth') {
    $wasAuthenticated = isset($_SESSION['user_id']);
    
    // Try to verify and refresh if needed
    $isAuthenticated = verifyAuth($db);
    
    // Check if token was refreshed (by looking for new expiration time)
    $tokenRefreshed = $isAuthenticated && isset($_SESSION['access_token_expires']) &&
                     (!$wasAuthenticated || (strtotime($_SESSION['access_token_expires']) > time() + 86400 * 6));
    
    echo json_encode([
        'authenticated' => $isAuthenticated,
        'token_refreshed' => $tokenRefreshed,
        'expires_at' => $_SESSION['access_token_expires'] ?? null
    ]);
    exit;
}

// Handle actions
if (handleIgdbActions($action, IGDB_CLIENT_ID, IGDB_CLIENT_SECRET, 'token.txt', $db)) {
    // IGDB actions handled
} elseif (handleReviewActions($action, $db, $user_id)) {
    // Review actions handled
} elseif (handleUserActions($action, $db, $user_id)) {
    // User actions handled
} elseif (handleVotingActions($action, $db, $user_id)) {
    // Voting actions handled
} elseif (handleAuthActions($action)) {
    // Auth actions handled
} elseif ($action === 'incrementPageview') {
    $db->query("UPDATE pageviews SET total = total + 1 WHERE id = 1");
    echo json_encode(['message' => 'Pageview incremented']);
} elseif ($action === 'getPageviews') {
    $result = $db->query("SELECT total FROM pageviews WHERE id = 1");
    if ($result && $row = $result->fetch_assoc()) {
        echo json_encode(['total_pageviews' => $row['total']]);
    } else {
        $error_message = $db->error ? $db->error : 'Unknown error';
        error_log("Failed to fetch pageviews: " . $error_message);
        echo json_encode(['error' => 'Failed to fetch pageviews: ' . $error_message]);
    }
} elseif ($action === 'getStatistics') {
    $result = $db->query("SELECT COUNT(*) as total FROM games");
    if ($result && $row = $result->fetch_assoc()) {
        echo json_encode(['total_games' => $row['total']]);
    } else {
        echo json_encode(['error' => 'Failed to fetch statistics']);
    }
} elseif ($action === 'logout') {
    error_log('Logout action called'); // Debug
    
    // First call the consolidated logout function from auth_helper.php
    logoutUser($db);
    
    // Then restart session to ensure new session ID
    session_regenerate_id(true);
    
    // Send appropriate headers to prevent caching
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
} elseif ($action === 'reviewAction') {
    $reviewAction = isset($_GET['reviewAction']) ? $_GET['reviewAction'] : '';
    // Include reviews.php file if it's not included already
    if (!function_exists('handleReviewActions')) {
        include 'api/reviews.php';
    }
    
    // Handle the review action
    handleReviewActions($reviewAction, $db, $user_id);
} else {
    echo json_encode(['error' => 'Invalid action']);
}

$db->close();
?>