<?php
session_start();
include 'db_connect.php';
include 'api/rate_limiter.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    error_log("Received POST data: username='$username', password='$password', csrf_token='$csrf_token'");

    if (!$csrf_token || !isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token";
        error_log("Registration failed: Invalid CSRF token");
    } else {
        if (!preg_match('/^[a-zA-Z0-9]{3,20}$/', $username)) {
            $error = "Username must be 3-20 characters long and contain only letters and numbers.";
            error_log("Registration failed: Invalid username format - $username");
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
            $error = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.";
            error_log("Registration failed: Invalid password format - '$password'");
        } else {
            $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "Username already exists.";
                error_log("Registration failed: Username already exists - $username");
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
                $stmt->bind_param('ss', $username, $hashed_password);
                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;
                    error_log("User inserted successfully: $username, ID: $user_id");
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['is_admin'] = 0;
                    $_SESSION['is_moderator'] = 0;

                    $token = bin2hex(random_bytes(32));
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
                    $tokenStmt = $db->prepare('INSERT INTO access_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
                    $tokenStmt->bind_param('iss', $user_id, $token, $expiresAt);
                    if ($tokenStmt->execute()) {
                        error_log("Token inserted successfully for user: $username");
                    } else {
                        error_log("Token insertion failed: " . $tokenStmt->error);
                    }

                    $_SESSION['access_token'] = $token;
                    setcookie('access_token', $token, [
                        'expires' => strtotime('+7 days'),
                        'path' => '/',
                        'httponly' => true,
                        'samesite' => 'Lax',
                        'secure' => false
                    ]);

                    session_regenerate_id(true);
                    header('Location: /gamerating/index.php');
                    exit;
                } else {
                    $error = "Registration failed: " . $stmt->error;
                    error_log("Registration failed: " . $stmt->error);
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta charset="UTF-8">
    <title>Register - Game Rater '98</title>
    <meta name="description" content="Register to rate and review games anonymously.">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <link rel="stylesheet" href="css/main.css">

    <script src="js/konami.js"></script>
</head>
<body>
    <div style="text-align: center; margin: 20px;">
        <h2>REGISTER</h2>
        <?php if (isset($error)): ?>
            <p style="color: #ff00ff;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST" id="register-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <p><input type="text" name="username" placeholder="Username" required id="username"></p>
            <p><input type="password" name="password" placeholder="Password" required id="password"></p>
            <div id="symbol-challenge" class="symbols"></div>
            <p id="challenge-status">Press the arrow keys to match the sequence above</p>
            <p><button type="submit" id="submit-register-btn" disabled>REGISTER</button></p>
        </form>
        <p>Enter Konami Code to login instead!</p>
        <p><a href="index.php">Back to Home</a></p>
        <p>LOGIN:</p>
        <!-- Konami code sequence display -->
        <div class="konami-section" style="display: flex; justify-content: center; align-items: center; gap: 10px;">
            <div id="konami-sequence-login-reg" class="symbols"></div>
        </div>
    </div>

    <!-- Custom notification container -->
    <div id="custom-notification" class="custom-notification" style="display: flex">
        <p id="notification-message"></p>
    </div>
    
<script src="js/auth-pages.js"></script>
 <script src="js/auto-submit.js"></script>
<?php include 'footer.php'; ?>
</body>
</html>

<?php
$db->close();
?>