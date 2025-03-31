<?php
include 'db_connect.php';
include 'includes/auth_helper.php'; // Include the new auth helper

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// If user is already logged in, redirect to index.php
if (verifyAuth($db)) {
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
            // Fetch user data including admin status
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

                    // Create auth tokens (access + refresh)
                    $tokens = createAuthTokens($db, $user['id']);
                    
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="<?php echo $page_description; ?>">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <link rel="stylesheet" href="css/main.css">
    <script src="js/konami.js"></script>
    <style>
        /* Notification overlay for auto-submit delay */
        #submit-notification {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(17, 17, 17, 0.9);
            border: 2px solid #ff00ff;
            color: #00ff00;
            padding: 20px 40px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(255, 0, 255, 0.5);
            font-family: 'Courier New', Courier, monospace;
            font-size: 16px;
            text-align: center;
            z-index: 2000;
        }

        #submit-notification.show {
            display: block;
        }
    </style>
</head>
<body class="login-page">
    <div style="text-align: center; margin: 20px;">
        <h2>LOGIN</h2>
        <?php if (isset($error)): ?>
            <p style="color: #ff00ff;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST" id="login-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <p><input type="text" name="username" placeholder="Username" required id="login-username"></p>
            <p><input type="password" name="password" placeholder="Password" required id="login-password"></p>
            <div id="symbol-challenge" class="symbols"></div>
            <p id="challenge-status">Press the arrow keys to match the sequence above</p>
            <p><button type="submit" id="submit-login-btn" disabled>LOGIN</button></p>
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

    <!-- Submit notification overlay -->
    <div id="submit-notification">
        <p>Logging in, please wait...</p>
    </div>

<script src="js/auth-client.js"></script>
<script src="js/auth-pages.js"></script>
<script src="js/auto-submit.js"></script>
<?php include 'footer.php'; ?>
</body>
</html>

<?php
$db->close();
?>