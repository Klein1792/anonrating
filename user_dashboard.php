<?php

$page_title = 'User Dashboard - Game Rater \'98';
$page_description = 'View your profile, reviews, comments and liked games';
$body_class = 'user-dashboard-page';

include_once 'header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login or show error
    echo '<div class="container"><div class="error-message">Please log in to view your dashboard.</div></div>';
    include_once 'footer.php';
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Verify database connection is working
if (!$db || $db->connect_error) {
    // Try to reconnect
    include_once 'db_connect.php';
    
    if (!$db || $db->connect_error) {
        echo '<div class="container"><div class="error-message">Database connection error: ' . $db->connect_error . '</div></div>';
        include_once 'footer.php';
        exit;
    }
}

// Get user information with error handling
$query = 'SELECT username, created_at, is_admin, is_moderator, is_verified, is_banned FROM users WHERE id = ?';
$stmt = $db->prepare($query);

if ($stmt === false) {
    echo '<div class="container"><div class="error-message">Database query error: ' . $db->error . '</div></div>';
    include_once 'footer.php';
    exit;
}

$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user) {
    echo '<div class="container"><div class="error-message">User not found.</div></div>';
    include_once 'footer.php';
    exit;
}
?>

<div class="container">
    <div class="dashboard-wrapper">
        <!-- Replace the single h1 with this div -->
        <div class="dashboard-header">
            <h1 class="dashboard-title">Your Dashboard</h1>
        </div>
        
        <!-- Dashboard Navigation -->
        <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">Profile</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab" aria-controls="reviews" aria-selected="false">My Reviews</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="comments-tab" data-bs-toggle="tab" data-bs-target="#comments" type="button" role="tab" aria-controls="comments" aria-selected="false">My Comments</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="liked-games-tab" data-bs-toggle="tab" data-bs-target="#liked-games" type="button" role="tab" aria-controls="liked-games" aria-selected="false">Liked Games</button>
            </li>
        </ul>
        
        <div class="tab-content" id="dashboardTabContent">
            <!-- Profile Tab -->
            <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                <div class="profile-section">
                    <h2>Profile Information</h2>
                    <div class="profile-details">
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                        <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                        <p><strong>Account Status:</strong> 
                            <?php 
                            if ($user['is_banned']) {
                                echo '<span class="banned-badge">Banned</span>';
                            } else if ($user['is_verified']) {
                                echo '<span class="verified-badge">Verified</span>';
                            } else {
                                echo '<span class="unverified-badge">Unverified</span>';
                            }
                            ?>
                        </p>
                        <p><strong>Role:</strong> 
                            <?php 
                            if ($user['is_admin']) echo '<span class="admin-badge">Admin</span>';
                            else if ($user['is_moderator']) echo '<span class="mod-badge">Moderator</span>';
                            else echo '<span class="user-badge">User</span>';
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Reviews Tab -->
            <div class="tab-pane fade" id="reviews" role="tabpanel" aria-labelledby="reviews-tab">
                <div class="reviews-section">
                    <h2>My Reviews</h2>
                    <div id="user-reviews-container" class="user-reviews-container">
                        <div class="loading">Loading your reviews...</div>
                    </div>
                    <div class="pagination-container">
                        <button id="reviews-prev-page" class="btn btn-sm" disabled>Previous</button>
                        <span id="reviews-page-info">Page 1 of 1</span>
                        <button id="reviews-next-page" class="btn btn-sm" disabled>Next</button>
                    </div>
                </div>
            </div>
            
            <!-- Comments Tab -->
            <div class="tab-pane fade" id="comments" role="tabpanel" aria-labelledby="comments-tab">
                <div class="comments-section">
                    <h2>My Comments</h2>
                    <div id="user-comments-container" class="user-comments-container">
                        <div class="loading">Loading your comments...</div>
                    </div>
                    <div class="pagination-container">
                        <button id="comments-prev-page" class="btn btn-sm" disabled>Previous</button>
                        <span id="comments-page-info">Page 1 of 1</span>
                        <button id="comments-next-page" class="btn btn-sm" disabled>Next</button>
                    </div>
                </div>
            </div>
            
            <!-- Liked Games Tab -->
            <div class="tab-pane fade" id="liked-games" role="tabpanel" aria-labelledby="liked-games-tab">
                <div class="liked-games-section">
                    <h2>Games You've Liked</h2>
                    <div id="liked-games-container" class="game-cards">
                        <div class="loading">Loading your liked games...</div>
                    </div>
                    <div class="pagination-container">
                        <button id="games-prev-page" class="btn btn-sm" disabled>Previous</button>
                        <span id="games-page-info">Page 1 of 1</span>
                        <button id="games-next-page" class="btn btn-sm" disabled>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Before the dashboard.js script in user_dashboard.php -->
<script src="js/game/ui-utils.js"></script>
<script src="js/game/review-actions.js"></script>
<script src="js/game/review-comments.js"></script>
<script>window.isAuthenticated = true;</script>
<script src="js/dashboard.js"></script>

<?php include_once 'footer.php'; ?>