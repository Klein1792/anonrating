<?php
include_once 'db_connect.php';
include_once 'includes/auth_helper.php'; 

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Determine the current page
$current_page = basename($_SERVER['PHP_SELF']);

// Check if the user is an admin or moderator
$is_admin = $is_moderator = false;
if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $result = $db->query("SELECT username, is_admin, is_moderator FROM users WHERE id = $user_id");
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $username = htmlspecialchars($user['username']);
        $is_admin = (bool)$user['is_admin'];
        $is_moderator = (bool)$user['is_moderator'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title ?? 'Game Rater \'98'; ?></title>
    <meta name="description" content="<?php echo $page_description ?? 'Retro 1998-style community ratings for new video games.'; ?>">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    
    <!-- Load global variables first -->
    <script src="js/globals.js"></script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/game-cards.css">
    <link rel="stylesheet" href="css/konami-code.css">
    <link rel="stylesheet" href="css/mobile-controller.css">
    <link rel="stylesheet" href="css/mobile-nav.css">
    <link rel="stylesheet" href="css/responsive.css">
    
    <!-- Core JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Core utilities first -->
<script src="js/konami.js"></script>
<script src="js/konami-handler.js"></script>

<!-- Auth module -->
<script src="js/auth-client.js"></script>



<!-- Page-specific scripts loaded conditionally -->
<?php if ($current_page === 'game.php'): ?>
    <script src="js/game/ui-utils.js"></script>
    <script src="js/batch-api.js"></script>
    <script src="js/game/game-details.js"></script>
    <script src="js/game/reviews.js"></script>
    <script src="js/game/review-votes.js"></script>
    <script src="js/game/review-actions.js"></script>
    <script src="js/game/review-comments.js"></script>
    <script src="js/game/auth-utils.js"></script>
    <script src="js/game/moderation-actions.js"></script>
    <script src="js/game/challenge-system.js"></script>
    <script src="js/game/game-votes.js"></script>
    <script src="js/game-page.js"></script>
<?php endif; ?>

<!-- user dashboard -->
<?php if ($current_page === 'user_dashboard.php'): ?>
    <link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/user_dashboard.css">

<?php endif; ?>

    <!-- Any page-specific head content -->
    <?php if (isset($head_content)) echo $head_content; ?>
</head>
<body class="<?php echo $body_class ?? ''; ?>">
    <div id="page-container">
        <!-- Fixed header for top-level navigation -->
        <header class="header-container">
            <div class="header-main">
                <div class="site-branding">
                    <h1><a href="index.php">ANONRATING</a></h1>
                    <p class="site-description">Anonrating - anonymous, unbias game rating</p>
                </div>
                <div class="user-status">
                    <p id="status">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        Logged in as: <?php echo $username; ?>
                        | <a href="user_dashboard.php">Dashboard</a>
                        <?php if ($is_admin): ?>
                            | <a href="admin.php">Admin Dashboard</a>
                        <?php endif; ?>
                        <?php if ($is_moderator): ?>
                            | <a href="moderator.php">Moderator Panel</a>
                        <?php endif; ?>
                        | <a href="#" onclick="return logout();">Click here to logout</a>
                    <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php if ($current_page !== 'game.php' && !isset($_SESSION['user_id'])): ?>
            <div class="konami-wrapper">
                <div class="konami-container">
                    <!-- Login section first -->
                    <div class="konami-section">
                        <span class="konami-instructions">Login:</span>
                        <div id="konami-sequence-login" class="symbols"></div>
                    </div>
                    <!-- Signup section second -->
                    <div class="konami-section">
                        <span class="konami-instructions">Register:</span>
                        <div id="konami-sequence-signup" class="symbols"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </header>

        <!-- Custom Notification Container -->
        <div id="custom-notification" class="custom-notification" style="display: none;">
            <p id="notification-message"></p>
        </div>

        <!-- Main content wrapper -->
        <div id="main-content-wrapper">

        <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Add logged-in class to body if user is logged in
            if (document.getElementById('status') && document.getElementById('status').textContent.trim()) {
                document.body.classList.add('user-logged-in');
            }
            
            // Set statusDiv reference 
            window.statusDiv = document.getElementById('status');
            
            // Increment pageview on page load
            fetch(`${window.gameRating.config.baseUrl}/api.php?action=incrementPageview`)
                .catch(error => console.error('Error incrementing pageview:', error));
            
            // Display Konami code sequences if user not logged in
            if (!window.statusDiv.textContent.trim()) return;
            
            // Display sequences
            const signupDiv = document.getElementById('konami-sequence-signup');
            const loginDiv = document.getElementById('konami-sequence-login');
            
            if (signupDiv && typeof displayKonamiSequence === 'function') {
                displayKonamiSequence(window.signupSequence, 'konami-sequence-signup');
            }
            
            if (loginDiv && typeof displayKonamiSequence === 'function') {
                displayKonamiSequence(window.loginSequence, 'konami-sequence-login');
            }
            
            // Mobile toggle for Konami codes
            const konamiToggle = document.getElementById('konami-toggle');
            const konamiContainer = document.querySelector('.konami-container');
            if (window.innerWidth <= 768 && konamiToggle && konamiContainer) {
                konamiToggle.style.display = 'block';
                konamiToggle.addEventListener('click', () => {
                    konamiContainer.classList.toggle('expanded');
                });
            }
        });
        </script>
        <script>
document.addEventListener('DOMContentLoaded', () => {
    // Add logged-in class to body if user is logged in
    if (document.getElementById('status') && document.getElementById('status').textContent.trim()) {
        document.body.classList.add('user-logged-in');
    }
    
    // Set statusDiv reference 
    window.statusDiv = document.getElementById('status');
    
    // Display Konami code sequences
    const signupDiv = document.getElementById('konami-sequence-signup');
    const loginDiv = document.getElementById('konami-sequence-login');
    
    if (signupDiv && typeof displayKonamiSequence === 'function') {
        displayKonamiSequence(window.signupSequence, 'konami-sequence-signup');
    }
    
    if (loginDiv && typeof displayKonamiSequence === 'function') {
        displayKonamiSequence(window.loginSequence, 'konami-sequence-login');
    }
    
    // Mobile toggle for Konami codes
    const konamiToggle = document.getElementById('konami-toggle');
    const konamiContainer = document.querySelector('.konami-container');
    
    if (window.innerWidth <= 768 && konamiToggle && konamiContainer) {
        konamiToggle.style.display = 'block';
        konamiToggle.addEventListener('click', () => {
            konamiContainer.classList.toggle('expanded');
        });
    }
    
    // Detect iPhone and add specific class
    const isIPhone = /iPhone/.test(navigator.userAgent);
    if (isIPhone) {
        document.body.classList.add('iphone');
    }
});
</script>
    </div>
</body>
</html>