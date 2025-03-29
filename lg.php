<?php
include 'db_connect.php';

// Include the auth helper
if (file_exists('auth_helper.php')) {
    include_once 'auth_helper.php';
}

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if already authenticated via token or session
if (isset($db) && function_exists('checkAuthentication')) {
    $auth = checkAuthentication($db);
    if ($auth !== false) {
        // Already authenticated
        header('Location: index.php');
        exit;
    }
}

// If user is already logged in via session, redirect to index.php
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!$csrf_token || !isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate username: alphanumeric, 3-20 characters
        if (!preg_match('/^[a-zA-Z0-9]{3,20}$/', $username)) {
            $error = "Invalid username format.";
        } else {
            $stmt = $db->prepare('SELECT id, password, username, is_admin, is_moderator FROM users WHERE username = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $error = "Invalid username or password.";
            } else {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // Store user data in session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['is_admin'] = $user['is_admin'];
                    $_SESSION['is_moderator'] = $user['is_moderator'];

                    // Generate access token with longer lifetime (7 days)
                    $token = bin2hex(random_bytes(32));
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
                    
                    // Delete any existing tokens for this user
                    $deleteStmt = $db->prepare('DELETE FROM access_tokens WHERE user_id = ?');
                    $deleteStmt->bind_param('i', $user['id']);
                    $deleteStmt->execute();
                    
                    // Create new token
                    $tokenStmt = $db->prepare('INSERT INTO access_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
                    $tokenStmt->bind_param('iss', $user['id'], $token, $expiresAt);
                    $tokenStmt->execute();

                    // Store token in session and cookie
                    $_SESSION['access_token'] = $token;
                    
                    // Set cookie with 7-day expiration and proper attributes for your local env
                    setcookie('access_token', $token, [
                        'expires' => strtotime('+7 days'),
                        'path' => '/',
                        'httponly' => true,
                        'samesite' => 'Lax', // Changed from Strict to Lax for better compatibility
                        'secure' => false // Local dev environment, change to true for production HTTPS
                    ]);
                    
                    // Force session write
                    session_write_close();
                    session_start();

                    header('Location: index.php');
                    exit;
                } else {
                    $error = "Invalid username or password.";
                }
            }
            $stmt->close();
        }
    }
}

// Set page variables for header
$page_title = 'Login - Game Rater \'98';
$page_description = 'Login to rate and review games';
$body_class = 'login-page';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="<?php echo $page_description; ?>">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <link rel="stylesheet" href="css/main.css">
    <script src="js/konami.js"></script>
</head>
<body class="<?php echo $body_class; ?>">
    <div style="text-align: center; margin: 20px;">
        <h2>LOGIN</h2>
        <?php if (isset($error)): ?>
            <p style="color: #ff00ff;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <p><input type="text" name="username" placeholder="Username" required></p>
            <p><input type="password" name="password" placeholder="Password" required></p>
            <!-- Remove the symbol-challenge div and use a normal login flow -->
            <p><button type="submit">LOGIN</button></p>
        </form>
        <p>Enter Konami Code to register instead!</p>
        <p><a href="index.php">Back to Home</a></p>
        <p>REGISTER:</p>
        <!-- Konami code sequence display -->
        <div class="konami-section" style="display: flex; justify-content: center; align-items: center; gap: 10px;">
            <div id="konami-sequence-signup-login" class="symbols"></div>
        </div>
    </div>

    <!-- Custom notification container -->
    <div id="custom-notification" class="custom-notification" style="display: none;">
        <p id="notification-message"></p>
    </div>
    
    <script>
    // Simple script to display Konami code sequence for registration
    document.addEventListener('DOMContentLoaded', function() {
        // Display Konami sequence if function available
        if (typeof displayKonamiSequence === 'function' && window.signupSequence) {
            displayKonamiSequence(window.signupSequence, 'konami-sequence-signup-login');
        }
        
        // Set up Konami code handler for registration
        if (typeof Konami === 'function') {
            const konami = new Konami(() => {
                window.location.href = 'register.php';
            });
        }
    });
    </script>

<?php include 'footer.php'; ?>
</body>
</html>