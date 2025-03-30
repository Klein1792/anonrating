<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;   
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/auth_helper.php';
// filepath: e:\bakcup\xampp\htdocs\gamerating\api\auth.php

/**
 * Handle all authentication-related API actions
 * 
 * @param string $action The API action
 * @param object $db Database connection
 * @return bool True if the action was handled
 */
function handleAuthActions($action, $db) {
    switch ($action) {
        case 'login':
            return handleLogin($db);
            
        case 'register':
            return handleRegister($db);
            
        case 'logout':
            return handleLogout($db);
            
        case 'refreshToken':
            return handleRefreshToken($db);
            
        case 'verifyToken':
            return handleVerifyToken();
            
        case 'createAnonymousToken':
            return handleCreateAnonymousToken($db);
            
        default:
            return false;
    }
}

/**
 * Handle user login
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleLogin($db) {
    // Get login data from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['username']) || empty($data['password'])) {
        echo json_encode(['success' => false, 'error' => 'Missing username or password']);
        return true;
    }
    
    $username = $data['username'];
    $password = $data['password'];
    $remember = isset($data['remember']) ? (bool)$data['remember'] : false;
    
    // Validate input
    if (strlen($username) < 3 || strlen($username) > 50) {
        echo json_encode(['success' => false, 'error' => 'Username must be between 3 and 50 characters']);
        return true;
    }
    
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
        return true;
    }
    
    // Check for rate limiting (optional)
    // ...
    
    // Check for user in database
    $stmt = $db->prepare('SELECT id, username, password, is_admin, is_moderator, is_banned FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
        return true;
    }
    
    $user = $result->fetch_assoc();
    
    // Check if user is banned
    if ($user['is_banned']) {
        echo json_encode(['success' => false, 'error' => 'Your account has been banned']);
        return true;
    }
    
    // Check password
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
        return true;
    }
    
    // Generate auth tokens
    $tokens = createAuthTokens($db, $user['id'], $user['username'], $user['is_admin'], $user['is_moderator'], $remember);
    
    if (!$tokens) {
        echo json_encode(['success' => false, 'error' => 'Failed to create authentication tokens']);
        return true;
    }
    
    // Return user data and tokens
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'user_id' => (int)$user['id'],
            'username' => $user['username'],
            'is_admin' => (bool)$user['is_admin'],
            'is_moderator' => (bool)$user['is_moderator']
        ],
        'access_token' => $tokens['access_token'],
        'token_type' => 'Bearer',
        'expires_in' => $tokens['expires_at'] - time()
    ]);
    
    return true;
}

/**
 * Handle user registration
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleRegister($db) {
    // Get registration data from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['username']) || empty($data['email']) || empty($data['password'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return true;
    }
    
    $username = trim($data['username']);
    $email = trim($data['email']);
    $password = $data['password'];
    
    // Validate input
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Username must be 3-20 characters and can only contain letters, numbers, and underscores'
        ]);
        return true;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email address']);
        return true;
    }
    
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
        return true;
    }
    
    // Check if username already exists
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Username already taken']);
        return true;
    }
    
    // Check if email already exists
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Email already registered']);
        return true;
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $db->prepare('
        INSERT INTO users (username, email, password, created_at) 
        VALUES (?, ?, ?, NOW())
    ');
    $stmt->bind_param('sss', $username, $email, $passwordHash);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Failed to create account: ' . $db->error]);
        return true;
    }
    
    $userId = $db->insert_id;
    
    // Generate auth tokens
    $tokens = createAuthTokens($db, $userId, $username, false, false, false);
    
    if (!$tokens) {
        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully, but failed to log in automatically'
        ]);
        return true;
    }
    
    // Return user data and tokens
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully',
        'user' => [
            'user_id' => $userId,
            'username' => $username,
            'is_admin' => false,
            'is_moderator' => false
        ],
        'access_token' => $tokens['access_token'],
        'token_type' => 'Bearer',
        'expires_in' => $tokens['expires_at'] - time()
    ]);
    
    return true;
}

/**
 * Handle logout request
 * 
 * @param object $db Database connection
 * @return bool True if handled successfully
 */
function handleLogout($db) {
    // Call the master logout function
    logoutUser($db);
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
    
    return true;
}

/**
 * Handle token refresh
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleRefreshToken($db) {
    // Get refresh token from cookie or request
    $refreshToken = $_COOKIE['refresh_token'] ?? null;
    
    // If no refresh token in cookie, check the request body
    if (!$refreshToken) {
        $data = json_decode(file_get_contents('php://input'), true);
        $refreshToken = $data['refresh_token'] ?? null;
    }
    
    if (!$refreshToken) {
        echo json_encode(['success' => false, 'error' => 'No refresh token provided']);
        return true;
    }
    
    // Refresh the access token
    $tokens = refreshAccessToken($db, $refreshToken);
    
    if (!$tokens) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired refresh token']);
        return true;
    }
    
    // Return the new tokens
    echo json_encode([
        'success' => true,
        'message' => 'Token refreshed successfully',
        'access_token' => $tokens['access_token'],
        'token_type' => 'Bearer',
        'expires_in' => $tokens['expires_at'] - time(),
        'user' => [
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'is_admin' => isset($_SESSION['is_admin']) ? (bool)$_SESSION['is_admin'] : false,
            'is_moderator' => isset($_SESSION['is_moderator']) ? (bool)$_SESSION['is_moderator'] : false
        ]
    ]);
    
    return true;
}

/**
 * Handle token verification
 * 
 * @return bool True if handled
 */
function handleVerifyToken() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
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
    
    if (!$jwt) {
        echo json_encode(['success' => false, 'error' => 'No token provided']);
        return true;
    }
    
    // Verify the token
    try {
        $decoded = JWT::decode($jwt, new Key(JWT_SECRET, JWT_ALGORITHM));
        
        // Check if token is valid
        if ($decoded->exp > time() && $decoded->iss === 'gamerating-app') {
            echo json_encode([
                'success' => true,
                'message' => 'Token is valid',
                'user' => [
                    'user_id' => $decoded->user_id,
                    'username' => $decoded->username,
                    'is_admin' => $decoded->is_admin ?? false,
                    'is_moderator' => $decoded->is_moderator ?? false
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Token has expired']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Invalid token: ' . $e->getMessage()]);
    }
    
    return true;
}

/**
 * Create anonymous token for users without accounts
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleCreateAnonymousToken($db) {
    // Create an anonymous user
    $anonymousUser = ensureAnonymousUser($db);
    
    if ($anonymousUser && !empty($anonymousUser['anonymous_token'])) {
        echo json_encode([
            'success' => true,
            'anonymous_token' => $anonymousUser['anonymous_token']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create anonymous token'
        ]);
    }
    
    return true;
}