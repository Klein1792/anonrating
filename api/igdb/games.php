<?php
require_once __DIR__ . '/token.php';

function fetchGamesFromIGDB($client_id, $client_secret, $token_file, $db) {
    $token = getIgdbToken($client_id, $client_secret, $token_file);
    if (!$token) {
        error_log("Failed to get IGDB token");
        return ['error' => 'Failed to authenticate with IGDB API'];
    }

    $allGames = [];
    $offset = 0;
    $limit = 50;
    $targetCount = 100;
    $retry = false;

    while (count($allGames) < $targetCount) {
        $ch = curl_init('https://api.igdb.com/v4/games');
        $query = "fields name,first_release_date,cover.url,rating,genres.name,platforms.name; limit $limit; offset $offset;";
        error_log("IGDB Query: $query");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $clientIdHeader = "Client-ID: " . trim($client_id);
        $authHeader = "Authorization: Bearer " . trim($token);
        $headers = [
            $clientIdHeader,
            $authHeader,
            "Accept: application/json"
        ];
        error_log("Raw Headers: " . json_encode($headers));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            error_log("cURL Error: " . $error);
            curl_close($ch);
            return ['error' => 'Failed to fetch games from IGDB: ' . $error];
        }
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code === 401 && !$retry) {
            error_log("IGDB HTTP Error 401, retrying with new token...");
            $token = getIgdbToken($client_id, $client_secret, $token_file, true);
            if (!$token) {
                error_log("Failed to get new IGDB token on retry");
                return ['error' => 'Failed to authenticate with IGDB API after retry'];
            }
            $retry = true;
            continue;
        }
        if ($http_code !== 200) {
            error_log("IGDB HTTP Error: $http_code, Response: $response");
            return ['error' => 'Failed to fetch games from IGDB: HTTP ' . $http_code];
        }
        $games = json_decode($response, true);
        error_log("IGDB Response (Offset $offset): " . json_encode($games));

        if (!is_array($games)) {
            error_log("Invalid IGDB Response: Not an array");
            return ['error' => 'Invalid response from IGDB API'];
        }

        if (empty($games)) {
            break;
        }

        $allGames = array_merge($allGames, $games);
        $offset += $limit;

        if (count($games) < $limit) {
            break;
        }
    }

    foreach ($allGames as $game) {
        if (!isset($game['id'])) {
            continue;
        }
        $id = (int)$game['id'];
        $name = $db->real_escape_string($game['name'] ?? 'Unknown');
        $first_release_date = isset($game['first_release_date']) ? (int)$game['first_release_date'] : null;
        $cover_url = isset($game['cover']['url']) ? $db->real_escape_string('https:' . $game['cover']['url']) : null;
        $rating = isset($game['rating']) ? (float)$game['rating'] : null;
        $genres = isset($game['genres']) ? array_map(function($g) { return $g['name']; }, $game['genres']) : [];
        $platforms = isset($game['platforms']) ? array_map(function($p) { return $p['name']; }, $game['platforms']) : [];

        $details = fetchGameDetails($client_id, $token, $id);
        $details_json = null;
        if (!isset($details['error'])) {
            $developer = 'N/A';
            $publisher = 'N/A';
            if (isset($details['involved_companies']) && is_array($details['involved_companies'])) {
                foreach ($details['involved_companies'] as $company) {
                    if (isset($company['developer']) && $company['developer'] && isset($company['company']['name'])) {
                        $developer = $company['company']['name'];
                    }
                    if (isset($company['publisher']) && $company['publisher'] && isset($company['company']['name'])) {
                        $publisher = $company['company']['name'];
                    }
                }
            }

            $trailer = null;
            if (isset($details['videos']) && is_array($details['videos'])) {
                foreach ($details['videos'] as $video) {
                    if (isset($video['video_id'])) {
                        $trailer = "https://www.youtube.com/embed/{$video['video_id']}";
                        break;
                    }
                }
            }

            $tags = [];
            if (isset($details['tags']) && is_array($details['tags'])) {
                $tag_names = fetchTagNames($client_id, $token, $details['tags']);
                $tags = $tag_names;
            }

            $websites = [];
            if (isset($details['websites']) && is_array($details['websites'])) {
                foreach ($details['websites'] as $website) {
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

            $details['developer'] = $developer;
            $details['publisher'] = $publisher;
            $details['trailer'] = $trailer;
            $details['tags'] = $tags;
            $details['websites'] = $websites;
            $details['rating'] = isset($details['rating']) ? (float)$details['rating'] : $rating;
            $details['genres'] = $genres;
            $details['platforms'] = $platforms;
            $details['last_updated'] = time();
            $details_json = json_encode($details);
        } else {
            error_log("Failed to fetch details for game ID $id: " . $details['error']);
        }

        $stmt = $db->prepare('INSERT INTO games (id, name, first_release_date, cover_url, details) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = ?, first_release_date = ?, cover_url = ?, details = ?');
        if (!$stmt) {
            error_log("Prepare failed: " . $db->error);
            continue;
        }
        $stmt->bind_param('isisssiss', $id, $name, $first_release_date, $cover_url, $details_json, $name, $first_release_date, $cover_url, $details_json);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
        }
        $stmt->close();
    }

    $stmt = $db->prepare('INSERT INTO fetch_log (last_fetched) VALUES (NOW())');
    if ($stmt) {
        if (!$stmt->execute()) {
            error_log("Failed to update fetch log: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Prepare failed for fetch log: " . $db->error);
    }

    return $allGames;
}

function fetchGames($client_id, $client_secret, $token_file, $db) {
    $type = $_GET['type'] ?? 'recent';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'likes';
    $sortDirection = $_GET['sortDirection'] ?? 'DESC';
    $genre = $_GET['genre'] ?? '';
    $platform = $_GET['platform'] ?? '';

    $allowedSortFields = ['created_at', 'first_release_date', 'rating', 'likes', 'approval', 'avg_rating'];

    $result = $db->query('SELECT last_fetched FROM fetch_log ORDER BY last_fetched DESC LIMIT 1');
    if (!$result) {
        error_log("Failed to fetch last_fetched: " . $db->error);
        return ['error' => 'Database error: ' . $db->error];
    }
    $lastFetched = $result->num_rows ? strtotime($result->fetch_assoc()['last_fetched']) : null;

    $currentTime = time();
    $oneDayInSeconds = 24 * 60 * 60;

    if (!$lastFetched || ($currentTime - $lastFetched) > $oneDayInSeconds) {
        error_log("Fetching games from IGDB (last fetch was more than 24 hours ago or never fetched)");
        $allGames = fetchGamesFromIGDB($client_id, $client_secret, $token_file, $db);
        if (isset($allGames['error'])) {
            return $allGames;
        }
    }

    // Update the SQL query to include avg_rating and review_count AND vote data
    $query = 'SELECT id, name, first_release_date, cover_url, details, avg_rating, review_count, likes, dislikes, approval_percent FROM games';
    $conditions = [];
    $params = [];
    $types = '';

    if ($search) {
        $conditions[] = 'name LIKE ?';
        $params[] = "%$search%";
        $types .= 's';
    }

    if ($type === 'recent') {
        $conditions[] = 'first_release_date <= ? AND first_release_date > ?';
        $params[] = $currentTime;
        $params[] = $currentTime - (2 * 365 * 24 * 60 * 60);
        $types .= 'ii';
    } elseif ($type === 'genre' && $genre) {
        $conditions[] = 'details LIKE ?';
        $params[] = '%"' . $genre . '"%';
        $types .= 's';
    } elseif ($type === 'platform' && $platform) {
        if ($platform === 'Console') {
            $conditions[] = '(details LIKE ? OR details LIKE ?)';
            $params[] = '%"PlayStation 5"%';
            $params[] = '%"Xbox Series"%';
            $types .= 'ss';
        } else {
            $conditions[] = 'details LIKE ?';
            $params[] = '%"' . $platform . '"%';
            $types .= 's';
        }
    }

    if ($conditions) {
        $query .= ' WHERE ' . implode(' AND ', $conditions);
    }

    // Define base columns that should always be included
    $baseColumns = "id, name, first_release_date, cover_url, details, avg_rating, review_count, likes, dislikes, approval_percent";

    // Sort by rating or release date in SQL
    if ($sort === 'rating') {
        // Add rating_value for sorting but keep all base columns
        $query = "SELECT $baseColumns, CAST(COALESCE(JSON_EXTRACT(details, '$.rating'), 0) AS DECIMAL(10,2)) as rating_value FROM games" 
            . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '') 
            . " ORDER BY rating_value $sortDirection, first_release_date DESC";
    } else if ($sort === 'avg_rating') {
        // Use existing columns but maintain consistent order
        $query = "SELECT $baseColumns FROM games" 
            . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '') 
            . " ORDER BY COALESCE(avg_rating, 0) $sortDirection, review_count DESC, name ASC";
    } else if ($sort === 'likes' || $sort === 'approval') {
        // Sort by approval_percent
        $query = "SELECT $baseColumns FROM games" 
            . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '') 
            . " ORDER BY approval_percent $sortDirection, (likes + dislikes) DESC, name ASC";
        
        // Debug the approval sort
        error_log("Approval sort query: $query with direction $sortDirection");
    } else if ($sort === 'name') {
        // Sort alphabetically
        $query = "SELECT $baseColumns FROM games"
            . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '')
            . " ORDER BY name $sortDirection";
    } else if ($sort === 'release' || $sort === 'first_release_date') {
        // Sort by release date
        $query = "SELECT $baseColumns FROM games"
            . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '')
            . " ORDER BY first_release_date $sortDirection, name ASC";
    } else {
        // Default sort (usually rating)
        $query = "SELECT $baseColumns, CAST(COALESCE(JSON_EXTRACT(details, '$.rating'), 0) AS DECIMAL(10,2)) as rating_value FROM games" 
            . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '')
            . " ORDER BY rating_value $sortDirection, first_release_date DESC";
    }

    error_log("Query: $query, Params: " . json_encode($params));
    $stmt = $db->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $db->error);
        return ['error' => 'Database error: ' . $db->error];
    }
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return ['error' => 'Database error: ' . $stmt->error];
    }
    $result = $stmt->get_result();

    $games = [];
    while ($row = $result->fetch_assoc()) {
        $details = $row['details'] ? json_decode($row['details'], true) : [];
        if ($details === null) {
            error_log("Invalid JSON in details for game ID {$row['id']}: " . $row['details']);
            $details = [];
        }
        
        // Get vote data from games table
        $likes = isset($row['likes']) ? (int)$row['likes'] : 0;
        $dislikes = isset($row['dislikes']) ? (int)$row['dislikes'] : 0;
        $totalVotes = $likes + $dislikes;
        $approvalPercent = isset($row['approval_percent']) ? (float)$row['approval_percent'] : 0;
        
        $game = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'first_release_date' => $row['first_release_date'] ? (int)$row['first_release_date'] : null,
            'cover' => ['url' => $row['cover_url']],
            'rating' => isset($details['rating']) ? (float)$details['rating'] : null,
            'genres' => $details['genres'] ?? [],
            'platforms' => $details['platforms'] ?? [],
            // Add these fields for average rating
            'avg_rating' => isset($row['avg_rating']) ? (float)$row['avg_rating'] : null,
            'review_count' => isset($row['review_count']) ? (int)$row['review_count'] : 0,
            // Add these fields for likes/dislikes
            'likes' => $likes,
            'dislikes' => $dislikes,
            'total_votes' => $totalVotes,
            'approval_percent' => $approvalPercent
        ];
        
        $games[] = $game;
        error_log("Game ID {$game['id']}: Name: {$game['name']}, Rating: " . ($game['rating'] ?? 'N/A'));
    }
    $stmt->close();

    // Calculate total before pagination
    $totalQuery = 'SELECT COUNT(*) as total FROM games' . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '');
    $totalStmt = $db->prepare($totalQuery);
    if (!$totalStmt) {
        error_log("Total Prepare failed: " . $db->error);
        return ['error' => 'Database error: ' . $db->error];
    }
    if ($params) {
        $totalStmt->bind_param($types, ...$params);
    }
    $totalStmt->execute();
    $total = $totalStmt->get_result()->fetch_assoc()['total'];
    $totalStmt->close();

    // Apply pagination after sorting
    $games = array_slice($games, $offset, $limit);

    error_log("Fetched " . count($games) . " games (after pagination) for type=$type, genre=$genre, platform=$platform, total=$total");
    return ['games' => $games, 'total' => $total];
}

function fetchGameDetails($client_id, $token, $game_id) {
    $ch = curl_init('https://api.igdb.com/v4/games');
    $query = 'fields name,first_release_date,summary,genres.name,platforms.name,cover.url,'
           . 'websites.url,involved_companies.company.name,involved_companies.developer,involved_companies.publisher,'
           . 'videos.video_id,tags,rating; where id = ' . (int)$game_id . ';';
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = [
        "Client-ID: $client_id",
        "Authorization: Bearer $token",
        "Accept: application/json"
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        error_log("cURL Error (fetchGameDetails): " . $error);
        curl_close($ch);
        return ['error' => 'Failed to fetch game details from IGDB: ' . $error];
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code !== 200) {
        error_log("fetchGameDetails HTTP Error: $http_code, Response: $response");
        return ['error' => 'Failed to fetch game details from IGDB: HTTP ' . $http_code];
    }
    $games = json_decode($response, true);
    if (!is_array($games) || empty($games)) {
        error_log("Invalid or empty response from IGDB: " . json_encode($games));
        return ['error' => 'Invalid or empty response from IGDB API'];
    }
    return $games[0];
}
?>