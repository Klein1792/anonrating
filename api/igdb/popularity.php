<?php
require_once __DIR__ . '/token.php';
require_once __DIR__ . '/games.php';

function fetchFamousGamesFromIGDB($client_id, $client_secret, $token_file, $db) {
    $token = getIgdbToken($client_id, $client_secret, $token_file);
    if (!$token) {
        error_log("Failed to get IGDB token for famous games");
        return ['error' => 'Failed to authenticate with IGDB API'];
    }

    $gameIds = [];
    $offset = 0;
    $limit = 50;
    $targetCount = 200;
    $retry = false;

    while (count($gameIds) < $targetCount) {
        $ch = curl_init('https://api.igdb.com/v4/popularity_primitives');
        $query = "fields game_id,value; where popularity_type = 1; sort value desc; limit $limit; offset $offset;";
        error_log("IGDB Popularity Query: $query");
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            error_log("cURL Error (Popularity Fetch): " . $error);
            curl_close($ch);
            return ['error' => 'Failed to fetch popularity data from IGDB: ' . $error];
        }
        curl_close($ch);
        error_log("Popularity Fetch Response Code: $http_code, Body: " . substr($response, 0, 500));
        if ($http_code === 401 && !$retry) {
            error_log("IGDB HTTP Error 401 (Popularity), retrying with new token...");
            $token = getIgdbToken($client_id, $client_secret, $token_file, true);
            if (!$token) {
                error_log("Failed to get new IGDB token on retry for popularity");
                return ['error' => 'Failed to authenticate with IGDB API after retry'];
            }
            $retry = true;
            continue;
        }
        if ($http_code !== 200) {
            error_log("IGDB HTTP Error (Popularity): $http_code, Response: $response");
            return ['error' => 'Failed to fetch popularity data from IGDB: HTTP ' . $http_code . ' - ' . $response];
        }
        $popularityData = json_decode($response, true);
        if (!is_array($popularityData)) {
            error_log("Invalid IGDB Popularity Response: Not an array, Response: " . $response);
            return ['error' => 'Invalid response from IGDB Popularity API'];
        }

        if (empty($popularityData)) {
            error_log("No more popularity data available at offset $offset");
            break;
        }

        foreach ($popularityData as $entry) {
            if (isset($entry['game_id']) && !in_array($entry['game_id'], $gameIds)) {
                $gameIds[] = $entry['game_id'];
            }
        }
        $offset += $limit;

        if (count($popularityData) < $limit) {
            break;
        }

        sleep(1);
    }

    $allGames = [];
    $idBatches = array_chunk(array_slice($gameIds, 0, $targetCount), 10);
    error_log("Processing " . count($idBatches) . " batches of game IDs");
    foreach ($idBatches as $index => $batch) {
        $ch = curl_init('https://api.igdb.com/v4/games');
        $idList = implode(',', $batch);
        $query = "fields name,first_release_date,cover.url,rating; where id = ($idList);";
        error_log("IGDB Games Batch Query #$index: $query");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $headers = [
            "Client-ID: " . trim($client_id),
            "Authorization: Bearer " . trim($token),
            "Accept: application/json"
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            error_log("cURL Error (Games Batch #$index): " . $error);
            curl_close($ch);
            continue;
        }
        curl_close($ch);
        error_log("Games Batch #$index Response Code: $http_code, Body: " . substr($response, 0, 500));
        if ($http_code !== 200) {
            error_log("IGDB HTTP Error (Games Batch #$index): $http_code, Response: $response");
            continue;
        }
        $games = json_decode($response, true);
        if (is_array($games)) {
            $allGames = array_merge($allGames, $games);
        } else {
            error_log("Invalid games response for batch #$index: " . $response);
        }
        sleep(1);
    }

    foreach ($allGames as $game) {
        if (!isset($game['id'])) {
            error_log("Skipping game with no ID: " . json_encode($game));
            continue;
        }
        $id = (int)$game['id'];
        $name = $db->real_escape_string($game['name'] ?? 'Unknown');
        $first_release_date = isset($game['first_release_date']) ? (int)$game['first_release_date'] : null;
        $cover_url = isset($game['cover']['url']) ? $db->real_escape_string('https:' . $game['cover']['url']) : null;
        $rating = isset($game['rating']) ? (float)$game['rating'] : null;

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
            $details['last_updated'] = time();
            $details_json = json_encode($details);
        } else {
            error_log("Failed to fetch details for game ID $id: " . $details['error']);
        }

        $stmt = $db->prepare('INSERT INTO games (id, name, first_release_date, cover_url, details) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = ?, first_release_date = ?, cover_url = ?, details = ?');
        if (!$stmt) {
            error_log("Prepare failed for games insert: " . $db->error);
            continue;
        }
        $stmt->bind_param('isisssiss', $id, $name, $first_release_date, $cover_url, $details_json, $name, $first_release_date, $cover_url, $details_json);
        if (!$stmt->execute()) {
            error_log("Execute failed for game ID $id: " . $stmt->error);
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

    $currentTime = time();
    $twoYearsAgo = $currentTime - (2 * 365 * 24 * 60 * 60);

    $releasedGames = array_filter($allGames, function($game) use ($twoYearsAgo, $currentTime) {
        return isset($game['first_release_date']) && $game['first_release_date'] <= $currentTime && $game['first_release_date'] > $twoYearsAgo;
    });

    $upcomingGames = array_filter($allGames, function($game) use ($currentTime) {
        return isset($game['first_release_date']) && $game['first_release_date'] > $currentTime;
    });

    usort($releasedGames, function($a, $b) {
        return $b['first_release_date'] - $a['first_release_date'];
    });
    $releasedGames = array_slice($releasedGames, 0, 20);

    usort($upcomingGames, function($a, $b) {
        return $a['first_release_date'] - $b['first_release_date'];
    });
    $upcomingGames = array_slice($upcomingGames, 0, 20);

    error_log("Fetched " . count($allGames) . " games, Released: " . count($releasedGames) . ", Upcoming: " . count($upcomingGames));
    return ['released' => array_values($releasedGames), 'upcoming' => array_values($upcomingGames)];
}
?>