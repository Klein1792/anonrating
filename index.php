<?php
include 'db_connect.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Game Rater '98</title>
    <meta name="description" content="Retro 1998-style community ratings for new video games.">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    
    <!-- Bootstrap and custom styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/main.css">
    
    <!-- Scripts that need to be loaded early -->
    <script>
        // Define baseUrl globally before any scripts that use it
        window.baseUrl = '/gamerating';
    </script>
</head>
<body class="index-page">
    <!-- Include header -->
    <?php include 'header.php'; ?>

    <div class="container mt-3">
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php
            $user_id = (int)$_SESSION['user_id'];
            $result = $db->query("SELECT is_admin FROM users WHERE id = $user_id");
            $is_admin = $result && $result->num_rows > 0 && $result->fetch_assoc()['is_admin'];
            if ($is_admin):
            ?>
                <div class="text-center mb-3">
                    <button id="fetch-famous-games-btn" class="btn btn-primary">Fetch Famous Games</button>
                    <button id="fetch-games-btn" class="btn btn-primary">Fetch Games Now</button>
                    <p id="fetch-status"></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" id="search-input" class="form-control" placeholder="Search games...">
            </div>
            <div class="col-md-3">
                <select id="sort-select" class="form-select">
                    <option value="rating">IGDB Rating</option>
                    <option value="release">Release Date</option>
                    <option value="approval">User Approval %</option>
                    <option value="avg_rating">User Ratings</option>
                    <option value="name">Name</option>
                </select>
            </div>
        </div>

        <ul class="nav nav-tabs" id="gameTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="top-rated-tab" data-bs-toggle="tab" data-bs-target="#top-rated" type="button" role="tab" aria-controls="top-rated" aria-selected="true">Top Rated</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="recent-tab" data-bs-toggle="tab" data-bs-target="#recent" type="button" role="tab">Recently Released</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="rpg-tab" data-bs-toggle="tab" data-bs-target="#rpg" type="button" role="tab">Role-playing (RPG)</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="shooter-tab" data-bs-toggle="tab" data-bs-target="#shooter" type="button" role="tab">Shooter</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="moba-tab" data-bs-toggle="tab" data-bs-target="#moba" type="button" role="tab">MOBA</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pc-tab" data-bs-toggle="tab" data-bs-target="#pc" type="button" role="tab">PC (Microsoft Windows)</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="console-tab" data-bs-toggle="tab" data-bs-target="#console" type="button" role="tab">Console</button>
            </li>
        </ul>

        <div class="tab-content" id="gameTabsContent">
            <div class="tab-pane fade show active" id="top-rated" role="tabpanel" aria-labelledby="top-rated-tab">
                <div id="top-rated-games" class="new-games-grid">
                    <div class="loading-container">
                        <div class="loading-spinner"></div>
                    </div>
                </div>
                <nav class="pagination" id="top-rated-pagination"></nav>
            </div>
            <div class="tab-pane fade" id="recent" role="tabpanel">
                <div class="new-games-grid" id="recent-games"></div>
                <nav class="pagination" id="recent-pagination"></nav>
            </div>
            <div class="tab-pane fade" id="rpg" role="tabpanel">
                <div class="new-games-grid" id="rpg-games"></div>
                <nav class="pagination" id="rpg-pagination"></nav>
            </div>
            <div class="tab-pane fade" id="shooter" role="tabpanel">
                <div class="new-games-grid" id="shooter-games"></div>
                <nav class="pagination" id="shooter-pagination"></nav>
            </div>
            <div class="tab-pane fade" id="moba" role="tabpanel">
                <div class="new-games-grid" id="moba-games"></div>
                <nav class="pagination" id="moba-pagination"></nav>
            </div>
            <div class="tab-pane fade" id="pc" role="tabpanel">
                <div class="new-games-grid" id="pc-games"></div>
                <nav class="pagination" id="pc-pagination"></nav>
            </div>
            <div class="tab-pane fade" id="console" role="tabpanel">
                <div class="new-games-grid" id="console-games"></div>
                <nav class="pagination" id="console-pagination"></nav>
            </div>
        </div>
    </div>

    <!-- Include footer with GameBoy controller -->
    <?php include 'footer.php'; ?>

    <script>
    // Simple BatchAPI implementation for the index page
    window.BatchAPI = {
        batch: async function(requests) {
            try {
                const response = await fetch(`${window.baseUrl}/api.php?action=batch`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ batch: requests }),
                    credentials: 'include'
                });
                
                if (!response.ok) {
                    throw new Error(`Batch request failed: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Unknown batch error');
                }
                
                return data.responses;
            } catch (error) {
                console.error('Batch API error:', error);
                throw error;
            }
        }
    };
    </script>
    <script src="js/index-page.js"></script>
</body>
</html>

<?php
$db->close();
?>