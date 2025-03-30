<?php
include 'db_connect.php';
include_once 'includes/auth_helper.php'; // Changed to include_once

// Verify and refresh authentication if needed
verifyAuth($db);

$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Use the helper function to check admin/mod status - no DB query needed
$is_admin_or_moderator = hasPermission('moderator');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta charset="UTF-8">
    <title>Game Details - Game Rater '98</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/game.css">
    
    <!-- Add user data meta tag -->
    <?php echo getUserDataMetaTag(); ?>
    
    <!-- CSRF token -->
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    
    <!-- Include auth-client.js for frontend auth handling -->
    <script src="js/auth-client.js"></script>
    
    <!-- For backward compatibility -->
    <script>
        window.baseUrl = '/gamerating';
    </script>
</head>
<body class="game-page">
    <?php include 'header.php'; ?>
    <div class="game-page-container">
        <!-- Header Section -->
        <div class="game-header">
            <!-- Video Player (Left) -->
            <div class="game-header-video">
                <div class="game-trailer" id="game-trailer"></div>
            </div>

            <!-- Cover Image (Center) -->
            <div class="game-header-image">
                <img id="game-cover" alt="Game Cover">
                <h1 id="game-title"></h1>
                <div class="game-votes" id="game-votes"></div>
                <div class="game-rating" id="game-rating"></div>
                <div class="vote-buttons" id="vote-buttons"></div>
            </div>

            <!-- Game Information (Right) -->
            <div class="game-header-info">
                <div class="game-meta">
                    <div class="meta-item">
                        <strong>Release Date:</strong> <span id="game-release-date"></span>
                    </div>
                    <div class="meta-item">
                        <strong>Developer:</strong> <span id="game-developer"></span>
                    </div>
                    <div class="meta-item">
                        <strong>Publisher:</strong> <span id="game-publisher"></span>
                    </div>
                    <div class="meta-item">
                        <strong>Genres:</strong> <span id="game-genres"></span>
                    </div>
                    <div class="meta-item">
                        <strong>Platforms:</strong> <span id="game-platforms"></span>
                    </div>
                    <div class="meta-item">
                        <strong>Tags:</strong> <span id="game-tags"></span>
                    </div>
                    <div class="meta-item">
                        <strong>Related Links:</strong> <span id="game-websites"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Section -->
        <div class="game-content">
            <!-- Description -->
            <div class="game-description" id="game-description"></div>

            <!-- Reviews -->
            <div class="game-reviews">
                <h2>Reviews</h2>
                <div id="reviews-list"></div>
                <nav class="pagination" id="pagination"></nav>
            </div>

            <!-- Review Form -->
            <div class="game-review-form">
                <h3>Write a Review</h3>
                <form id="review-form">
                    <div class="form-group">
                        <label for="review-title">Title:</label>
                        <input type="text" id="review-title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="review-content">Review:</label>
                        <textarea id="review-content" rows="5" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="review-rating">Rating:</label>
                        <select id="review-rating" required>
                            <?php for ($i = 10; $i >= 1; $i--): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?>/10</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" id="anonymous-name-field">
                        <label for="anonymous-name">Post as:</label>
                        <select id="anonymous-name" class="form-control">
                            <option value="Anonymous" selected>Anonymous</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Submit Review</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/game-page.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>

<?php
$db->close();
?>