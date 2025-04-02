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
    <link rel="stylesheet" href="css/game.css">
    <link rel="stylesheet" href="css/main.css">
    
    
    <!-- Add user data meta tag -->
    <?php echo getUserDataMetaTag(); ?>
    
    <!-- CSRF token -->
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    
    
    <!-- For backward compatibility -->
    <script>
        window.baseUrl = '/gamerating';
    </script>
</head>
<body class="game-page">

  <!-- Add this before the game-page.js script -->
  <script>
// Game Static Content Cache Manager
const GameCache = {
    // Get cached static data for a game
    getGameStatic: function(gameId) {
        try {
            const cachedItem = localStorage.getItem(`game_static_${gameId}`);
            if (!cachedItem) return null;
            
            const item = JSON.parse(cachedItem);
            // Cache for 12 hours (43200000 ms)
            if (Date.now() > item.expiry) {
                localStorage.removeItem(`game_static_${gameId}`);
                return null;
            }
            
            return item.data;
        } catch (e) {
            console.error('Cache read error:', e);
            return null;
        }
    },
    
    // Save static game data
    saveGameStatic: function(gameId, staticData) {
        try {
            const item = {
                data: staticData,
                expiry: Date.now() + (12 * 60 * 60 * 1000) // 12 hours
            };
            localStorage.setItem(`game_static_${gameId}`, JSON.stringify(item));
        } catch (e) {
            console.error('Cache write error:', e);
        }
    },
    
    // Clear static game data
    clearGameStatic: function(gameId) {
        try {
            localStorage.removeItem(`game_static_${gameId}`);
        } catch (e) {
            console.error('Cache clear error:', e);
        }
    }
};


// Add to window object
window.GameCache = GameCache;
</script>
<script src="js/batch-api.js"></script>

    <?php include 'header.php'; ?>
    <div class="game-page-container">
        <!-- Header Section -->
        <!-- Header Section -->
<div class="game-header">
    <!-- Video Player (Left) -->
    <div class="game-header-video">
        <div class="game-trailer" id="game-trailer"></div>
        <div id="game-rating"></div>
    </div>
    

    <!-- Cover Image (Center) -->
    <div class="game-header-image">
        <img id="game-cover" alt="Game Cover">
        <h1 id="game-title"></h1>

        <!-- Container for ratings and buttons -->
        <div class="game-interaction">
            
            <div class="vote-buttons" id="vote-buttons"></div>
        </div>
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
            <!-- Add this inside the game-header-info div, after the meta-item divs -->
<!-- In game.php, update the script section for the refresh button -->
<div class="meta-item cache-controls">
    <button id="refresh-static" class="btn btn-sm" onclick="refreshGameData()">
        <span class="refresh-icon">↻</span> Refresh Game Data
    </button>
    <small id="cache-timestamp"></small>
</div>

<script>
// Function to refresh game data
function refreshGameData() {
    const urlParams = new URLSearchParams(window.location.search);
    const gameId = urlParams.get('id');
    
    if (gameId) {
        // Clear the local storage cache
        if (window.GameCache && typeof window.GameCache.clearGameStatic === 'function') {
            window.GameCache.clearGameStatic(gameId);
        } else {
            // Fallback if GameCache isn't available
            localStorage.removeItem(`game_static_${gameId}`);
        }
        
        // Show loading message
        const refreshButton = document.getElementById('refresh-static');
        refreshButton.disabled = true;
        refreshButton.innerHTML = '<span class="refresh-icon spinning">↻</span> Refreshing...';
        
        // Call loadGameDetails with forceRefresh=true
        window.GameDetails.loadGameDetails(gameId, true).finally(() => {
            refreshButton.disabled = false;
            refreshButton.innerHTML = '<span class="refresh-icon">↻</span> Refresh Game Data';
            
            // Update cache timestamp
            const cachedItem = localStorage.getItem(`game_static_${gameId}`);
            if (cachedItem) {
                try {
                    const item = JSON.parse(cachedItem);
                    const cacheTime = new Date(item.expiry - (12 * 60 * 60 * 1000));
                    document.getElementById('cache-timestamp').textContent = 
                        `Data cached: ${cacheTime.toLocaleString()}`;
                } catch (e) {
                    console.error('Error reading cache timestamp:', e);
                }
            } else {
                document.getElementById('cache-timestamp').textContent = '';
            }
        });
    }
}

// Update the cache timestamp display
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const gameId = urlParams.get('id');
    
    if (gameId && window.GameCache) {
        const cachedItem = localStorage.getItem(`game_static_${gameId}`);
        if (cachedItem) {
            try {
                const item = JSON.parse(cachedItem);
                const cacheTime = new Date(item.expiry - (12 * 60 * 60 * 1000));
                document.getElementById('cache-timestamp').textContent = 
                    `Data cached: ${cacheTime.toLocaleString()}`;
            } catch (e) {
                console.error('Error reading cache timestamp:', e);
            }
        }
    }
});
</script>
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
            <input type="text" id="review-title" maxlength="50" required>
            <small id="title-char-count" class="char-count">0/50 characters</small>
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

  
    <?php include 'footer.php'; ?>
</body>
</html>

<?php
$db->close();
?>