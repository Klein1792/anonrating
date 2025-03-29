<?php
/**
 * Authentication Helper Functions with Refresh Token Support
 * 
 * This file provides comprehensive authentication functions including:
 * - Access & refresh token management
 * - Persistent login mechanisms
 * - Permission verification
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Constants for token durations
define('ACCESS_TOKEN_LIFETIME', 60 * 60 * 24); // 1 day in seconds
define('REFRESH_TOKEN_LIFETIME', 60 * 60 * 24 * 30); // 30 days in seconds

/**
 * Verify user authentication status and refresh if needed
 * Call this at the beginning of protected pages
 */
function verifyAuth($db) {
    // Already authenticated via session with valid access token
    if (isset($_SESSION['user_id']) && isset($_SESSION['access_token_expires']) && 
        strtotime($_SESSION['access_token_expires']) > time()) {
        return true;
    }
    
    // Try refresh token authentication if session exists but access token expired
    if (isset($_SESSION['user_id']) && isset($_SESSION['refresh_token'])) {
        return refreshAccessToken($db, $_SESSION['refresh_token']);
    }
    
    // Try cookie-based refresh token authentication
    if (isset($_COOKIE['refresh_token'])) {
        return refreshAccessToken($db, $_COOKIE['refresh_token']);
    }
    
    return false;
}

/**
 * Validates refresh token and issues new access token
 * This will keep extending the login indefinitely
 */
function refreshAccessToken($db, $refreshToken) {
    // Find valid refresh token
    $stmt = $db->prepare('
        SELECT user_id, token, expires_at 
        FROM refresh_tokens 
        WHERE token = ? AND expires_at > NOW()
    ');
    $stmt->bind_param('s', $refreshToken);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Invalid or expired refresh token
        return false;
    }
    
    $refreshTokenData = $result->fetch_assoc();
    $user_id = $refreshTokenData['user_id'];
    
    // Get user details
    $stmt = $db->prepare('SELECT id, username, is_admin, is_moderator FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // User no longer exists
        return false;
    }
    
    $user = $result->fetch_assoc();
    
    // Generate new access token with longer expiration (7 days)
    $accessToken = bin2hex(random_bytes(32));
    $accessTokenExpiry = date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60));
    
    // Update or insert access token
    $db->begin_transaction();
    
    try {
        // Delete any existing access tokens
        $stmt = $db->prepare('DELETE FROM access_tokens WHERE user_id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        // Create new access token
        $stmt = $db->prepare('INSERT INTO access_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $user_id, $accessToken, $accessTokenExpiry);
        $stmt->execute();
        
        $db->commit();
        
        // Update session with user data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['is_moderator'] = $user['is_moderator'];
        $_SESSION['access_token'] = $accessToken;
        $_SESSION['access_token_expires'] = $accessTokenExpiry;
        
        // Set access token cookie (used for API calls)
        setcookie('access_token', $accessToken, [
            'expires' => time() + (7 * 24 * 60 * 60),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => false // Set to true in production with HTTPS
        ]);
        
        return true;
    } catch (Exception $e) {
        $db->rollback();
        error_log('Error refreshing access token: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create permanent authentication tokens
 * This will keep users logged in indefinitely until they log out
 */
function createAuthTokens($db, $user_id) {
    // Generate tokens
    $accessToken = bin2hex(random_bytes(32));
    $refreshToken = bin2hex(random_bytes(32));
    
    // Set expiration times - Access token expires in 7 days, refresh token never expires
    $accessTokenExpiry = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    // Special date for "never expires" - using a very far future date
    // MySQL DATETIME max value is '9999-12-31 23:59:59'
    $permanentExpiry = '9999-12-31 23:59:59';
    
    // Store IP and user agent for security logging
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Delete existing tokens for this user
    $db->begin_transaction();
    
    try {
        // Delete old access tokens
        $stmt = $db->prepare('DELETE FROM access_tokens WHERE user_id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        // Insert new access token
        $stmt = $db->prepare('INSERT INTO access_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $user_id, $accessToken, $accessTokenExpiry);
        $stmt->execute();
        
        // Delete old refresh tokens
        $stmt = $db->prepare('DELETE FROM refresh_tokens WHERE user_id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        // Insert new refresh token that never expires
        $stmt = $db->prepare('
            INSERT INTO refresh_tokens (user_id, token, expires_at, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->bind_param('issss', $user_id, $refreshToken, $permanentExpiry, $ip, $userAgent);
        $stmt->execute();
        
        $db->commit();
        
        // Store tokens in session
        $_SESSION['access_token'] = $accessToken;
        $_SESSION['access_token_expires'] = $accessTokenExpiry;
        $_SESSION['refresh_token'] = $refreshToken;
        
        // Set cookies - 10 year expiration for "permanent" login
        $tenYearsInSeconds = 10 * 365 * 24 * 60 * 60;
        
        // Access token cookie with 7 day expiry
        setcookie('access_token', $accessToken, [
            'expires' => time() + (7 * 24 * 60 * 60),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => false // Set to true in production
        ]);
        
        // Refresh token cookie with 10 year expiry for "permanent" login
        setcookie('refresh_token', $refreshToken, [
            'expires' => time() + $tenYearsInSeconds,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict', // More restrictive for refresh tokens
            'secure' => false // Set to true in production
        ]);
        
        return [
            'access_token' => $accessToken,
            'access_token_expires' => $accessTokenExpiry,
            'refresh_token' => $refreshToken
        ];
    } catch (Exception $e) {
        $db->rollback();
        error_log('Error creating auth tokens: ' . $e->getMessage());
        return false;
    }
}

/**
 * Master logout function - the ONLY one to use across the application
 * This ensures consistent, complete logout behavior
 */
function logoutUser($db) {
    error_log('LogoutUser helper called - MASTER VERSION');
    
    // Track user ID before clearing session
    $user_id = $_SESSION['user_id'] ?? null;
    
    // First, destroy the session completely
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session data
    session_destroy();
    
    // Clear specific auth cookies with multiple approaches for compatibility
    $cookie_paths = ['/', '/gamerating', ''];
    
    foreach ($cookie_paths as $path) {
        // Using the modern approach
        setcookie('access_token', '', [
            'expires' => 1,
            'path' => $path,
            'httponly' => true
        ]);
        
        setcookie('refresh_token', '', [
            'expires' => 1,
            'path' => $path,
            'httponly' => true
        ]);
        
        // Legacy cookie format for older browsers
        setcookie('access_token', '', time() - 3600, $path);
        setcookie('refresh_token', '', time() - 3600, $path);
    }
    
    // Delete token database entries if we have a user ID
    if ($user_id) {
        try {
            // Delete access tokens
            $stmt = $db->prepare('DELETE FROM access_tokens WHERE user_id = ?');
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Delete refresh tokens if that table exists
            $result = $db->query("SHOW TABLES LIKE 'refresh_tokens'");
            if ($result && $result->num_rows > 0) {
                $stmt = $db->prepare('DELETE FROM refresh_tokens WHERE user_id = ?');
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log('Error during logout token deletion: ' . $e->getMessage());
        }
    }
    
    error_log('Logout completed - MASTER VERSION');
    
    // Start a fresh session to avoid errors
    session_start();
}

/**
 * Check if user has the required permission level
 */
function hasPermission($level) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    switch($level) {
        case 'admin':
            return !empty($_SESSION['is_admin']);
        case 'moderator':
            return !empty($_SESSION['is_admin']) || !empty($_SESSION['is_moderator']);
        case 'user':
            return true;
        default:
            return false;
    }
}

/**
 * Get user data for JavaScript synchronization
 */
function getUserDataForJS() {
    if (!isset($_SESSION['user_id'])) {
        return [
            'id' => 0,
            'username' => '',
            'isLoggedIn' => false,
            'isAdmin' => false,
            'isModerator' => false,
            'tokenExpiry' => null
        ];
    }
    
    return [
        'id' => (int)$_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'isLoggedIn' => true,
        'isAdmin' => !empty($_SESSION['is_admin']),
        'isModerator' => !empty($_SESSION['is_moderator']),
        'tokenExpiry' => $_SESSION['access_token_expires'] ?? null
    ];
}

/**
 * Generate HTML meta tag with user auth data
 */
function getUserDataMetaTag() {
    $userData = getUserDataForJS();
    return '<meta name="user-data" content="' . base64_encode(json_encode($userData)) . '">';
}
?>