<?php
/**
 * Authentication Helper Functions with JWT Support
 * 
 * This file provides comprehensive authentication functions including:
 * - JWT access token management
 * - Refresh token mechanisms
 * - Anonymous user support
 * - Permission verification
 */

// Load configuration
require_once __DIR__ . '/../phpconfig.php';
require_once __DIR__ . '/../api/auth.php';

// Load Firebase JWT library
require_once __DIR__ . '/../db_connect.php'; // Load database connection
require_once __DIR__ . '/../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define constants if not already defined
// Avoid redefining constants to prevent errors
if (!defined('ACCESS_TOKEN_LIFETIME')) {
    define('ACCESS_TOKEN_LIFETIME', 60 * 60 * 24); // 1 day in seconds
}

if (!defined('REFRESH_TOKEN_LIFETIME')) {
    define('REFRESH_TOKEN_LIFETIME', 60 * 60 * 24 * 30); // 30 days in seconds
}

// Use JWT_SECRET from phpconfig.php instead of SECRET_KEY
if (!defined('JWT_ALGORITHM')) {
    define('JWT_ALGORITHM', 'HS256');
}

if (!defined('ANONYMOUS_TOKEN_LIFETIME')) {
    define('ANONYMOUS_TOKEN_LIFETIME', 60 * 60 * 24 * 365); // 1 year in seconds
}

/**
 * Verify user authentication status and refresh if needed
 * Call this at the beginning of protected pages
 */
function verifyAuth($db) {
    // Debug info for troubleshooting
    if (isset($_GET['debug_auth'])) {
        error_log('DEBUG AUTH - Session: ' . json_encode($_SESSION));
        error_log('DEBUG AUTH - Cookies: ' . json_encode($_COOKIE));
    }
    
    // Check for session hijacking (IP change)
    if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
        error_log('Potential session hijacking detected. IP mismatch.');
        logoutUser($db);
        return false;
    }
    
    // Already authenticated via session with valid access token
    if (isset($_SESSION['user_id']) && isset($_SESSION['access_token_expires']) && 
        $_SESSION['access_token_expires'] > date('Y-m-d H:i:s')) {
        
        // Record the current IP address with the session
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
        
        return true;
    }
    
    // Try refresh token authentication if session exists but access token expired
    if (isset($_SESSION['user_id']) && isset($_SESSION['refresh_token'])) {
        $refreshResult = refreshAccessToken($db, $_SESSION['refresh_token']);
        if ($refreshResult) {
            // Record the current IP address with the session
            $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
            return true;
        }
    }
    
    // Try cookie-based refresh token authentication
    if (isset($_COOKIE['refresh_token'])) {
        $refreshResult = refreshAccessToken($db, $_COOKIE['refresh_token']);
        if ($refreshResult) {
            // Record the current IP address with the session
            $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
            return true;
        }
    }
    
    // If we reached here, no valid auth was found
    // Clear any partial/invalid session data
    if (isset($_SESSION['user_id'])) {
        // There's a user_id but we couldn't authenticate - clear it
        unset($_SESSION['user_id']);
        unset($_SESSION['username']);
        unset($_SESSION['is_admin']);
        unset($_SESSION['is_moderator']);
        unset($_SESSION['access_token']);
        unset($_SESSION['access_token_expires']);
        unset($_SESSION['refresh_token']);
    }
    
    return false;
}

/**
 * Verify JWT token and update user session
 * 
 * @param string $token The JWT token to verify
 * @return bool|object False if invalid, decoded token if valid
 */
function verifyJWT($token) {
    if (empty($token)) {
        return false;
    }
    
    try {
        // Decode the token
        $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGORITHM));
        
        // Verify issuer, audience and expiration
        $now = time();
        if ($decoded->iss !== 'gamerating-app' || $decoded->exp < $now) {
            return false;
        }
        
        // Update session with user data from token
        $_SESSION['user_id'] = $decoded->user_id;
        $_SESSION['username'] = $decoded->username ?? '';
        $_SESSION['is_admin'] = $decoded->is_admin ?? false;
        $_SESSION['is_moderator'] = $decoded->is_moderator ?? false;
        $_SESSION['access_token'] = $token;
        $_SESSION['access_token_expires'] = date('Y-m-d H:i:s', $decoded->exp);
        
        return $decoded;
    } catch (Exception $e) {
        error_log('JWT verification error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create JWT tokens for both access and refresh
 * 
 * @param object $db Database connection
 * @param int $user_id User ID
 * @param string $username Username (optional)
 * @param bool $is_admin Admin status (optional)
 * @param bool $is_moderator Moderator status (optional)
 * @param bool $remember Whether to create long-lived tokens
 * @return array|bool Token information or false on failure
 */
function createAuthTokens($db, $user_id, $username = null, $is_admin = false, $is_moderator = false, $remember = false) {
    // If username not provided, get it from the database
    if ($username === null) {
        $stmt = $db->prepare('SELECT username, is_admin, is_moderator FROM users WHERE id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $username = $user['username'];
            $is_admin = (bool)$user['is_admin'];
            $is_moderator = (bool)$user['is_moderator'];
        } else {
            return false; // User not found
        }
    }
    
    // Create refresh token
    $refreshToken = bin2hex(random_bytes(32));
    $fingerprint = createDeviceFingerprint();
    
    // Set token lifetimes
    $accessTokenLifetime = $remember ? ACCESS_TOKEN_LIFETIME * 7 : ACCESS_TOKEN_LIFETIME;
    $refreshTokenLifetime = $remember ? REFRESH_TOKEN_LIFETIME * 2 : REFRESH_TOKEN_LIFETIME;
    
    // Generate timestamps
    $issuedAt = time();
    $accessTokenExpiry = $issuedAt + $accessTokenLifetime;
    $refreshTokenExpiry = $issuedAt + $refreshTokenLifetime;
    $refreshExpiryDate = date('Y-m-d H:i:s', $refreshTokenExpiry);
    
    // Create JWT payload for access token
    $accessPayload = [
        'iss' => 'gamerating-app',         // Issuer
        'aud' => 'gamerating-users',       // Audience
        'iat' => $issuedAt,                // Issued at
        'nbf' => $issuedAt,                // Not before
        'exp' => $accessTokenExpiry,       // Expiration time
        'jti' => bin2hex(random_bytes(16)), // JWT ID
        'sub' => (string)$user_id,         // Subject (user ID)
        'user_id' => (int)$user_id,
        'username' => $username,
        'is_admin' => (bool)$is_admin,
        'is_moderator' => (bool)$is_moderator
    ];
    
    // Store IP and user agent for security logging
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Start transaction to ensure all DB operations succeed or fail together
    $db->begin_transaction();
    
    try {
        // Delete old refresh tokens for this user (single-session policy)
        $stmt = $db->prepare('DELETE FROM refresh_tokens WHERE user_id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        // Insert new refresh token
        $stmt = $db->prepare('
            INSERT INTO refresh_tokens (user_id, token, expires_at, fingerprint, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->bind_param('isssss', $user_id, $refreshToken, $refreshExpiryDate, $fingerprint, $ip, $userAgent);
        $stmt->execute();
        
        // Generate the JWT access token
        $accessToken = JWT::encode($accessPayload, JWT_SECRET, JWT_ALGORITHM);
        
        $db->commit();
        
        // Store data in session for PHP access
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['is_admin'] = $is_admin;
        $_SESSION['is_moderator'] = $is_moderator;
        $_SESSION['access_token'] = $accessToken;
        $_SESSION['access_token_expires'] = date('Y-m-d H:i:s', $accessTokenExpiry);
        $_SESSION['refresh_token'] = $refreshToken;
        
        // Set cookies for JavaScript and API access
        setcookie('access_token', $accessToken, [
            'expires' => $accessTokenExpiry,
            'path' => '/',
            'httponly' => false, // Allow JS access for API calls
            'samesite' => 'Lax',
            'secure' => isSecureConnection() // Auto-detect HTTPS
        ]);
        
        // Refresh token cookie - HttpOnly for security
        setcookie('refresh_token', $refreshToken, [
            'expires' => $refreshTokenExpiry,
            'path' => '/',
            'httponly' => true, // No JS access
            'samesite' => 'Lax',
            'secure' => isSecureConnection()
        ]);
        
        return [
            'access_token' => $accessToken,
            'expires_at' => $accessTokenExpiry,
            'refresh_token' => $refreshToken
        ];
    } catch (Exception $e) {
        $db->rollback();
        error_log('Error creating auth tokens: ' . $e->getMessage());
        return false;
    }
}

/**
 * Refresh access token using refresh token
 * 
 * @param object $db Database connection
 * @param string $refreshToken Refresh token
 * @return array|bool New tokens or false on failure
 */
function refreshAccessToken($db, $refreshToken) {
    if (empty($refreshToken)) {
        return false;
    }
    
    // Validate refresh token
    $stmt = $db->prepare('
        SELECT user_id, fingerprint, expires_at
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
    
    $tokenData = $result->fetch_assoc();
    
    // Verify device fingerprint for security (optional)
    $currentFingerprint = createDeviceFingerprint();
    if ($tokenData['fingerprint'] !== $currentFingerprint) {
        // Potential token theft - invalidate all tokens
        $stmt = $db->prepare('DELETE FROM refresh_tokens WHERE user_id = ?');
        $stmt->bind_param('i', $tokenData['user_id']);
        $stmt->execute();
        return false;
    }
    
    // Get user data
    $userId = $tokenData['user_id'];
    $stmt = $db->prepare('SELECT username, is_admin, is_moderator FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // User not found (may have been deleted)
        return false;
    }
    
    $user = $result->fetch_assoc();
    
    // Create new auth tokens
    return createAuthTokens($db, $userId, $user['username'], $user['is_admin'], $user['is_moderator'], true);
}

/**
 * Master logout function - the ONLY one to use across the application
 */
function logoutUser($db) {
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
            'httponly' => false // Changed to false to ensure JavaScript can see it's gone
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
            // Delete refresh tokens
            $stmt = $db->prepare('DELETE FROM refresh_tokens WHERE user_id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log('Error during logout token deletion: ' . $e->getMessage());
        }
    }
    
    // Start a fresh session to avoid errors
    session_start();
    
    // Generate a new CSRF token for the new session
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Create or retrieve anonymous token for users without accounts
 * 
 * @param object $db Database connection
 * @return array Anonymous user information
 */
function ensureAnonymousUser($db) {
    // Check if the anonymous_users table exists
    $db->query("
        CREATE TABLE IF NOT EXISTS anonymous_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(64) NOT NULL UNIQUE,
            fingerprint VARCHAR(64) NULL,
            ip_address VARCHAR(45) NULL,
            first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            INDEX (token),
            INDEX (expires_at)
        )
    ");
    
    // Check for existing anonymous token
    $anonymousToken = $_COOKIE['anonymous_token'] ?? null;
    $fingerprint = createDeviceFingerprint();
    
    if ($anonymousToken) {
        // Try to validate and update existing token
        $stmt = $db->prepare('
            SELECT id, token 
            FROM anonymous_users 
            WHERE token = ? AND expires_at > NOW()
        ');
        $stmt->bind_param('s', $anonymousToken);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Token exists and is valid, update last seen
            $anonymousData = $result->fetch_assoc();
            $db->query("
                UPDATE anonymous_users 
                SET last_seen = NOW() 
                WHERE token = '$anonymousToken'
            ");
            
            // Store in session for current request
            $_SESSION['anonymous_token'] = $anonymousToken;
            $_SESSION['anonymous_id'] = $anonymousData['id'];
            
            return [
                'anonymous_id' => (int)$anonymousData['id'],
                'anonymous_token' => $anonymousToken,
                'is_new' => false
            ];
        }
    }
    
    // Generate new anonymous token
    $anonymousToken = bin2hex(random_bytes(32));
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $expiryDate = date('Y-m-d H:i:s', time() + ANONYMOUS_TOKEN_LIFETIME);
    
    // Insert into database
    $stmt = $db->prepare('
        INSERT INTO anonymous_users (token, fingerprint, ip_address, expires_at) 
        VALUES (?, ?, ?, ?)
    ');
    $stmt->bind_param('ssss', $anonymousToken, $fingerprint, $ipAddress, $expiryDate);
    $stmt->execute();
    
    // Get the ID of the new anonymous user
    $anonymousId = $db->insert_id;
    
    // Set cookie
    setcookie('anonymous_token', $anonymousToken, [
        'expires' => time() + ANONYMOUS_TOKEN_LIFETIME,
        'path' => '/',
        'httponly' => false,  // Allow JS access for consistent UX
        'samesite' => 'Lax',
        'secure' => isSecureConnection()
    ]);
    
    // Store in session for current request
    $_SESSION['anonymous_token'] = $anonymousToken;
    $_SESSION['anonymous_id'] = $anonymousId;
    
    return [
        'anonymous_id' => $anonymousId,
        'anonymous_token' => $anonymousToken,
        'is_new' => true
    ];
}

/**
 * Create JWT for anonymous users
 * 
 * @param string $anonymousToken The anonymous token
 * @return string JWT token
 */
function createAnonymousJWT($anonymousToken) {
    $issuedAt = time();
    $expiresAt = $issuedAt + ANONYMOUS_TOKEN_LIFETIME;
    
    $payload = [
        'iss' => 'gamerating-app',
        'aud' => 'gamerating-users',
        'iat' => $issuedAt,
        'nbf' => $issuedAt,
        'exp' => $expiresAt,
        'jti' => bin2hex(random_bytes(16)),
        'sub' => 'anon:' . $anonymousToken,
        'anonymous_token' => $anonymousToken,
        'is_anonymous' => true
    ];
    
    return JWT::encode($payload, JWT_SECRET, JWT_ALGORITHM);
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

/**
 * Get current user identifier (either user_id or anonymous_token)
 * This function is critical for supporting both regular and anonymous users
 * 
 * @return array User identifier type and value
 */
function getCurrentUserIdentifier() {
    if (isset($_SESSION['user_id'])) {
        return [
            'type' => 'user_id',
            'value' => $_SESSION['user_id']
        ];
    }
    
    // Try to get anonymous token
    $anonymousToken = $_SESSION['anonymous_token'] ?? $_COOKIE['anonymous_token'] ?? null;
    
    if ($anonymousToken) {
        return [
            'type' => 'anonymous_token',
            'value' => $anonymousToken
        ];
    }
    
    return [
        'type' => 'anonymous_token',
        'value' => null
    ];
}

/**
 * Helper function to determine if the connection is secure
 * 
 * @return bool True if HTTPS
 */
function isSecureConnection() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
}

/**
 * Create a unique device fingerprint for security
 * 
 * @return string A fingerprint based on browser data
 */
function createDeviceFingerprint() {
    $data = [
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''
    ];
    return hash('sha256', implode('|', $data));
}

?>