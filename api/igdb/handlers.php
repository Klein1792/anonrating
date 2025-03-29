<?php
require_once __DIR__ . '/token.php';
require_once __DIR__ . '/games.php';
require_once __DIR__ . '/popularity.php';
require_once __DIR__ . '/tags.php';

function handleIgdbActions($action, $client_id, $client_secret, $token_file, $db) {
    if ($action === 'games') {
        error_log("Calling fetchGames for action=games");
        $games = fetchGames($client_id, $client_secret, $token_file, $db);
        error_log("fetchGames response: " . json_encode($games));
        echo json_encode($games);
        return true;
    } elseif ($action === 'fetchGamesNow' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'Unauthorized: You must be logged in']);
            return true;
        }
        $user_id = (int)$_SESSION['user_id'];
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            error_log("Prepare failed: " . $db->error);
            echo json_encode(['error' => 'Database error: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $user_id);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            echo json_encode(['error' => 'Database error: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();
        if (!$result || $result->num_rows === 0) {
            echo json_encode(['error' => 'Unauthorized: User not found']);
            return true;
        }
        $user = $result->fetch_assoc();
        $stmt->close();
        if (!$user['is_admin']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin']);
            return true;
        }

        $games = fetchGamesFromIGDB($client_id, $client_secret, $token_file, $db);
        if (isset($games['error'])) {
            echo json_encode(['error' => $games['error']]);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Games fetched successfully']);
        }
        return true;
    } elseif ($action === 'fetchFamousGames' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'Unauthorized: You must be logged in']);
            return true;
        }
        $user_id = (int)$_SESSION['user_id'];
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            error_log("Prepare failed: " . $db->error);
            echo json_encode(['error' => 'Database error: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $user_id);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            echo json_encode(['error' => 'Database error: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();
        if (!$result || $result->num_rows === 0) {
            echo json_encode(['error' => 'Unauthorized: User not found']);
            return true;
        }
        $user = $result->fetch_assoc();
        $stmt->close();
        if (!$user['is_admin']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin']);
            return true;
        }

        $games = fetchFamousGamesFromIGDB($client_id, $client_secret, $token_file, $db);
        if (isset($games['error'])) {
            echo json_encode(['error' => $games['error']]);
        } else {
            echo json_encode(['status' => 'success', 'message' => '200 famous games fetched successfully']);
        }
        return true;
    } elseif ($action === 'getGameDetails' && isset($_GET['id'])) {
        header('Content-Type: application/json');
        $game_id = (int)$_GET['id'];
        $token = getIgdbToken($client_id, $client_secret, $token_file);
        if (!$token) {
            error_log("Failed to authenticate with IGDB API");
            echo json_encode(['error' => 'Failed to authenticate with IGDB API']);
            return true;
        }

        $stmt = $db->prepare('SELECT details FROM games WHERE id = ?');
        if (!$stmt) {
            error_log("Prepare failed: " . $db->error);
            echo json_encode(['error' => 'Database error: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $game_id);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            echo json_encode(['error' => 'Database error: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $oneDayInSeconds = 24 * 60 * 60;
        $currentTime = time();
        $game = null;

        if ($row && !empty($row['details'])) {
            $details = json_decode($row['details'], true);
            if ($details && isset($details['last_updated']) && ($currentTime - $details['last_updated']) < $oneDayInSeconds) {
                error_log("Using cached game details for ID: $game_id");
                $game = $details;
            }
        }

        if (!$game) {
            error_log("Fetching game details from IGDB for ID: $game_id");
            $game = fetchGameDetails($client_id, $token, $game_id);
            if (isset($game['error'])) {
                error_log("Error fetching game details: " . $game['error']);
                echo json_encode(['error' => $game['error']]);
                return true;
            }
            if (!$game) {
                error_log("Game not found for ID: $game_id");
                echo json_encode(['error' => 'Game not found']);
                return true;
            }

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
            $game['last_updated'] = $currentTime;

            $details_json = json_encode($game);
            $name = $db->real_escape_string($game['name'] ?? 'Unknown');
            $first_release_date = isset($game['first_release_date']) ? (int)$game['first_release_date'] : null;
            $cover_url = isset($game['cover']['url']) ? $db->real_escape_string('https:' . $game['cover']['url']) : null;

            $stmt = $db->prepare('INSERT INTO games (id, name, first_release_date, cover_url, details) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = ?, first_release_date = ?, cover_url = ?, details = ?');
            if (!$stmt) {
                error_log("Prepare failed: " . $db->error);
                echo json_encode(['error' => 'Database error: ' . $db->error]);
                return true;
            }
            $stmt->bind_param('isisssiss', $game_id, $name, $first_release_date, $cover_url, $details_json, $name, $first_release_date, $cover_url, $details_json);
            if (!$stmt->execute()) {
                error_log("Execute failed: " . $stmt->error);
                echo json_encode(['error' => 'Database error: ' . $stmt->error]);
                return true;
            }
            $stmt->close();
        }

        echo json_encode($game);
        return true;
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
    return false;
}
?>