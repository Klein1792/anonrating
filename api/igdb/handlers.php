<?php
require_once __DIR__ . '/token.php';
require_once __DIR__ . '/games.php';
require_once __DIR__ . '/popularity.php';
require_once __DIR__ . '/tags.php';

/**
 * Handle IGDB API actions
 * 
 * @param string $action The API action
 * @param object $db Database connection
 * @param string $client_id IGDB Client ID
 * @param string $client_secret IGDB Client Secret
 * @return bool True if action was handled
 */
function handleIgdbActions($action, $db, $client_id, $client_secret) {
    // Path to token file for caching
    $token_file = __DIR__ . '/../../cache/igdb_token.json';
    
    // Ensure cache directory exists
    if (!file_exists(dirname($token_file))) {
        mkdir(dirname($token_file), 0777, true);
    }
    
    // Ensure tables exist
    ensureIgdbTables($db);
    
    // Handle IGDB-related actions
    if ($action === 'games') {
        error_log("Calling fetchGames for action=games");
        $games = fetchGames($client_id, $client_secret, $token_file, $db);
        error_log("fetchGames response: " . json_encode($games));
        echo json_encode($games);
        return true;
    } 
    // Admin-only action to fetch games now
    elseif ($action === 'fetchGamesNow' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized: You must be logged in']);
            return true;
        }
        
        // Check admin status
        $user_id = (int)$_SESSION['user_id'];
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            error_log("Prepare failed: " . $db->error);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->error]);
            return true;
        }
        
        $stmt->bind_param('i', $user_id);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
            return true;
        }
        
        $result = $stmt->get_result();
        if (!$result || $result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized: User not found']);
            return true;
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user['is_admin']) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized: You must be an admin']);
            return true;
        }

        $games = fetchGamesFromIGDB($client_id, $client_secret, $token_file, $db);
        if (isset($games['error'])) {
            echo json_encode(['success' => false, 'error' => $games['error']]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Games fetched successfully']);
        }
        return true;
    } 
    // Admin-only action to fetch famous games
    elseif ($action === 'fetchFamousGames' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Same admin check as above
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized: You must be logged in']);
            return true;
        }
        
        $user_id = (int)$_SESSION['user_id'];
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            error_log("Prepare failed: " . $db->error);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->error]);
            return true;
        }
        
        $stmt->bind_param('i', $user_id);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
            return true;
        }
        
        $result = $stmt->get_result();
        if (!$result || $result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized: User not found']);
            return true;
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user['is_admin']) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized: You must be an admin']);
            return true;
        }

        $games = fetchFamousGamesFromIGDB($client_id, $client_secret, $token_file, $db);
        if (isset($games['error'])) {
            echo json_encode(['success' => false, 'error' => $games['error']]);
        } else {
            echo json_encode(['success' => true, 'message' => '200 famous games fetched successfully']);
        }
        return true;
    } 
    // Get details for a specific game
    elseif ($action === 'getGameDetails' && isset($_GET['id'])) {
        header('Content-Type: application/json');
        $game_id = (int)$_GET['id'];
        $token = getIgdbToken($client_id, $client_secret, $token_file);
        
        if (!$token) {
            error_log("Failed to authenticate with IGDB API");
            echo json_encode(['success' => false, 'error' => 'Failed to authenticate with IGDB API']);
            return true;
        }

        try {
            // Ensure games table exists
            ensureGamesTable($db);
            
            $stmt = $db->prepare('SELECT details FROM games WHERE id = ?');
            if (!$stmt) {
                error_log("Prepare failed: " . $db->error);
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->error]);
                return true;
            }
            
            $stmt->bind_param('i', $game_id);
            if (!$stmt->execute()) {
                error_log("Execute failed: " . $stmt->error);
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
                return true;
            }
            
            $result = $stmt->get_result();
            $game = null;
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (!empty($row['details'])) {
                    $details = json_decode($row['details'], true);
                    $oneDayInSeconds = 24 * 60 * 60;
                    $currentTime = time();
                    
                    if ($details && isset($details['last_updated']) && 
                        ($currentTime - $details['last_updated']) < $oneDayInSeconds) {
                        error_log("Using cached game details for ID: $game_id");
                        $game = $details;
                    }
                }
            }
            $stmt->close();
            
            // If no cached data or it's outdated, fetch from API
            if (!$game) {
                error_log("Fetching game details from IGDB for ID: $game_id");
                $game = fetchGameDetails($client_id, $token, $game_id);
                
                if (!$game) {
                    error_log("Game not found for ID: $game_id");
                    echo json_encode(['success' => false, 'error' => 'Game not found']);
                    return true;
                }

                if (isset($game['error'])) {
                    error_log("Error fetching game details: " . $game['error']);
                    echo json_encode(['success' => false, 'error' => $game['error']]);
                    return true;
                }

                // Process game data
                $developer = 'N/A';
                $publisher = 'N/A';
                if (isset($game['involved_companies']) && is_array($game['involved_companies'])) {
                    foreach ($game['involved_companies'] as $company) {
                        if (isset($company['developer']) && $company['developer'] && isset($company['company']['name'])) {
                            $developer = $company['company']['name'];
                        }
                        if (isset($company['publisher']) && $company['publisher'] && isset($company['company']['name'])) {
                            $publisher = $company['company']['name'];
                        }
                    }
                }

                $trailer = null;
                if (isset($game['videos']) && is_array($game['videos'])) {
                    foreach ($game['videos'] as $video) {
                        if (isset($video['video_id'])) {
                            $trailer = "https://www.youtube.com/embed/{$video['video_id']}";
                            break;
                        }
                    }
                }

                $tags = [];
                if (isset($game['tags']) && is_array($game['tags'])) {
                    $tag_names = fetchTagNames($client_id, $token, $game['tags']);
                    $tags = $tag_names;
                }

                $websites = [];
                if (isset($game['websites']) && is_array($game['websites'])) {
                    foreach ($game['websites'] as $website) {
                        if (isset($website['url']) && is_string($website['url']) && !empty($website['url'])) {
                            $url = filter_var($website['url'], FILTER_VALIDATE_URL) ? $website['url'] : null;
                            if ($url) {
                                $websites[] = $url;
                            } else {
                                error_log("Invalid URL skipped: " . $website['url']);
                            }
                        }
                    }
                }

                $game['developer'] = $developer;
                $game['publisher'] = $publisher;
                $game['trailer'] = $trailer;
                $game['tags'] = $tags;
                $game['websites'] = $websites;
                $game['last_updated'] = time();

                // Cache to database
                $details_json = json_encode($game);
                $name = $db->real_escape_string($game['name'] ?? 'Unknown');
                $first_release_date = isset($game['first_release_date']) ? (int)$game['first_release_date'] : null;
                $cover_url = isset($game['cover']['url']) ? $db->real_escape_string('https:' . $game['cover']['url']) : null;

                $stmt = $db->prepare('INSERT INTO games (id, name, first_release_date, cover_url, details) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = ?, first_release_date = ?, cover_url = ?, details = ?');
                if (!$stmt) {
                    error_log("Prepare failed: " . $db->error);
                } else {
                    $stmt->bind_param('isisssiss', $game_id, $name, $first_release_date, $cover_url, $details_json, $name, $first_release_date, $cover_url, $details_json);
                    if (!$stmt->execute()) {
                        error_log("Execute failed: " . $stmt->error);
                    }
                    $stmt->close();
                }
            }

            // Return game data
            echo json_encode([
                'success' => true,
                'game' => $game
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Exception in getGameDetails: " . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'error' => 'Error processing game details: ' . $e->getMessage()
            ]);
            return true;
        }
    } elseif ($action === 'getReviewsByGame' && isset($_GET['id'])) {
        $game_id = (int)$_GET['id'];
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 5;
        $offset = ($page - 1) * $perPage;

        $token = getIgdbToken($client_id, $client_secret, $token_file);
        if (!$token) {
            echo json_encode(['error' => 'Failed to authenticate with IGDB API']);
            return true;
        }
        $game = fetchGameDetails($client_id, $token, $game_id);
        if (isset($game['error'])) {
            echo json_encode(['error' => $game['error']]);
            return true;
        }
        if (!$game || !isset($game['name'])) {
            echo json_encode(['error' => 'Game not found']);
            return true;
        }
        $game_name = $game['name'];

        $stmt = $db->prepare('SELECT COUNT(*) as total FROM reviews WHERE gameName = ?');
        if (!$stmt) {
            error_log("Prepare failed: " . $db->error);
            echo json_encode(['error' => 'Database error: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('s', $game_name);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            echo json_encode(['error' => 'Database error: ' . $stmt->error]);
            return true;
        }
        $totalResult = $stmt->get_result()->fetch_assoc();
        $totalReviews = $totalResult['total'];
        $stmt->close();

        $stmt = $db->prepare('SELECT r.*, u.username FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.gameName = ? ORDER BY r.created_at DESC LIMIT ? OFFSET ?');
        if (!$stmt) {
            error_log("Prepare failed: " . $db->error);
            echo json_encode(['error' => 'Database error: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('sii', $game_name, $perPage, $offset);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            echo json_encode(['error' => 'Database error: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = [
                'id' => (int)$row['id'],
                'gameName' => $row['gameName'],
                'reviewText' => $row['reviewText'],
                'votes' => (int)$row['votes'],
                'verified' => (bool)$row['verified'],
                'username' => $row['user_id'] ? $row['username'] : 'Anonymous'
            ];
        }
        $stmt->close();
        echo json_encode([
            'reviews' => $reviews,
            'total' => $totalReviews,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($totalReviews / $perPage)
        ]);
        return true;
    }
    
    // No handler matched
    return false;
}

/**
 * Ensure all required IGDB tables exist
 * 
 * @param object $db Database connection
 */
function ensureIgdbTables($db) {
    // Create games table if it doesn't exist
    ensureGamesTable($db);
    
    // Create game_votes table if it doesn't exist  
    $db->query("
        CREATE TABLE IF NOT EXISTS game_votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            game_id INT NOT NULL,
            user_id INT NULL,
            anonymous_token VARCHAR(64) NULL,
            vote TINYINT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY user_game (game_id, user_id),
            UNIQUE KEY anon_game (game_id, anonymous_token)
        )
    ");
}

/**
 * Ensure games table exists
 * 
 * @param object $db Database connection
 */
function ensureGamesTable($db) {
    $db->query("
        CREATE TABLE IF NOT EXISTS games (
            id INT NOT NULL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            first_release_date INT NULL,
            cover_url VARCHAR(255) NULL,
            details TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
}
/* Removed duplicate ensureIgdbTables function */
?>