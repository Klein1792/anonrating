<?php
// filepath: e:\bakcup\xampp\htdocs\gamerating\api\voting.php
require_once __DIR__ . '/../includes/auth_helper.php';
/**
 * Handle all voting-related API actions
 * 
 * @param string $action The API action
 * @param object $db Database connection
 * @return bool True if the action was handled
 */
function handleVotingActions($action, $db) {
    switch ($action) {
        case 'voteGame':
            return handleGameVote($db);
            
        case 'voteReview':
            return handleReviewVote($db);
            
        case 'checkUserVote':
            return handleCheckUserVote($db);
            
        case 'checkReviewVote':
            return handleCheckReviewVote($db);
            
        case 'getGameVotes':
            return handleGetGameVotes($db);
            
        default:
            return false;
    }
}

/**
 * Handle game voting (like/dislike)
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleGameVote($db) {
    // Get vote data from request
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        return true;
    }
    
    // Extract and validate vote parameters
    $gameId = isset($data['gameId']) ? (int)$data['gameId'] : 0;
    $vote = isset($data['vote']) ? (int)$data['vote'] : null;
    
    if (!$gameId || ($vote !== 0 && $vote !== 1)) {
        echo json_encode(['success' => false, 'error' => 'Invalid game ID or vote value']);
        return true;
    }
      
    // Get user identifier (registered user or anonymous)
    $userIdentifier = getCurrentUserIdentifier($db);
    
    try {
        $db->begin_transaction();
        
        // Check if the user has already voted (using both user_id and anonymous_token)
        $existingVote = null;
        $voteId = null;
        
        if ($userIdentifier['type'] === 'user_id') {
            $userId = $userIdentifier['value'];
            $anonymousToken = $userIdentifier['anonymous_token'];
            
            // Check for votes by user_id or associated anonymous_token
            $query = 'SELECT id, vote FROM game_votes WHERE game_id = ? AND (user_id = ?';
            $params = [$gameId, $userId];
            $types = 'ii';
            
            if ($anonymousToken) {
                $query .= ' OR anonymous_token = ?';
                $params[] = $anonymousToken;
                $types .= 's';
            }
            $query .= ')';
            
            $stmt = $db->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $existingVote = $result->fetch_assoc();
                $voteId = $existingVote['id'];
            }
        } else {
            // Anonymous user
            $anonymousToken = $userIdentifier['value'];
            
            // Ensure we have a valid anonymous token
            if (!$anonymousToken) {
                $anonymousUser = ensureAnonymousUser($db);
                $anonymousToken = $anonymousUser['anonymous_token'];
            }
            
            // Check for votes by anonymous_token
            $stmt = $db->prepare('SELECT id, vote FROM game_votes WHERE game_id = ? AND anonymous_token = ?');
            $stmt->bind_param('is', $gameId, $anonymousToken);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $existingVote = $result->fetch_assoc();
                $voteId = $existingVote['id'];
            }
        }
        
        if ($existingVote) {
            // Update existing vote
            if ((int)$existingVote['vote'] === $vote) {
                // Same vote, remove it (toggle behavior)
                $stmt = $db->prepare('DELETE FROM game_votes WHERE id = ?');
                $stmt->bind_param('i', $voteId);
                $stmt->execute();
            } else {
                // Different vote, update it
                $stmt = $db->prepare('UPDATE game_votes SET vote = ?, updated_at = NOW() WHERE id = ?');
                $stmt->bind_param('ii', $vote, $voteId);
                $stmt->execute();
            }
        } else {
            // Insert new vote
            if ($userIdentifier['type'] === 'user_id') {
                $userId = $userIdentifier['value'];
                $stmt = $db->prepare('INSERT INTO game_votes (game_id, user_id, vote) VALUES (?, ?, ?)');
                $stmt->bind_param('iii', $gameId, $userId, $vote);
            } else {
                $anonymousToken = $userIdentifier['value'];
                $stmt = $db->prepare('INSERT INTO game_votes (game_id, anonymous_token, vote) VALUES (?, ?, ?)');
                $stmt->bind_param('isi', $gameId, $anonymousToken, $vote);
            }
            $stmt->execute();
        }
        
        // Fetch updated vote counts from games table
        $stmt = $db->prepare('
            SELECT 
                likes,
                dislikes,
                approval_percent
            FROM games
            WHERE id = ?
        ');
        $stmt->bind_param('i', $gameId);
        $stmt->execute();
        $result = $stmt->get_result();
        $game = $result->fetch_assoc();
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'likes' => (int)$game['likes'],
            'dislikes' => (int)$game['dislikes'],
            'total' => (int)$game['likes'] + (int)$game['dislikes']
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log('Error recording vote: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to record vote']);
    }
    
    return true;
}

/**
 * Handle checking a user's vote on a game
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleCheckUserVote($db) {
    $gameId = isset($_GET['gameId']) ? (int)$_GET['gameId'] : 0;
    
    if (!$gameId) {
        echo json_encode(['success' => false, 'error' => 'Invalid game ID']);
        return true;
    }
    
    $userIdentifier = getCurrentUserIdentifier($db);
    
    $query = 'SELECT vote FROM game_votes WHERE game_id = ? AND (';
    $params = [$gameId];
    $types = 'i';
    
    if ($userIdentifier['type'] === 'user_id') {
        $userId = $userIdentifier['value'];
        $anonymousToken = $userIdentifier['anonymous_token'];
        
        $query .= 'user_id = ?';
        $params[] = $userId;
        $types .= 'i';
        
        if ($anonymousToken) {
            $query .= ' OR anonymous_token = ?';
            $params[] = $anonymousToken;
            $types .= 's';
        }
    } else {
        $anonymousToken = $userIdentifier['value'];
        
        if (!$anonymousToken) {
            echo json_encode(['hasVoted' => false]);
            return true;
        }
        
        $query .= 'anonymous_token = ?';
        $params[] = $anonymousToken;
        $types .= 's';
    }
    
    $query .= ')';
    
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $vote = (int)$result->fetch_assoc()['vote'];
        echo json_encode([
            'success' => true,
            'hasVoted' => true,
            'vote' => $vote,
            'type' => $vote === 1 ? 'like' : 'dislike'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'hasVoted' => false
        ]);
    }
    
    return true;
}

/**
 * Handle getting vote statistics for a game
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleGetGameVotes($db) {
    $gameId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['gameId']) ? (int)$_GET['gameId'] : 0);
    
    if (!$gameId) {
        echo json_encode(['success' => false, 'error' => 'Invalid game ID']);
        return true;
    }
    
    // Get vote data directly from games table instead of calculating
    $stmt = $db->prepare('
        SELECT 
            likes,
            dislikes,
            approval_percent
        FROM games
        WHERE id = ?
    ');
    $stmt->bind_param('i', $gameId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $game = $result->fetch_assoc();
        $likes = (int)$game['likes'];
        $dislikes = (int)$game['dislikes'];
        $total = $likes + $dislikes;
        
        echo json_encode([
            'success' => true,
            'likes' => $likes,
            'dislikes' => $dislikes,
            'total' => $total,
            'approval' => (float)$game['approval_percent']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'likes' => 0,
            'dislikes' => 0,
            'total' => 0,
            'approval' => 0
        ]);
    }
    
    return true;
}

/**
 * Handle voting on reviews (helpful/not helpful)
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleReviewVote($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        return true;
    }
    
    $reviewId = isset($data['reviewId']) ? (int)$data['reviewId'] : 0;
    $isHelpful = isset($data['isHelpful']) ? (bool)$data['isHelpful'] : null;
    
    if (!$reviewId || $isHelpful === null) {
        echo json_encode(['success' => false, 'error' => 'Invalid review ID or vote value']);
        return true;
    }
    
    $userIdentifier = getCurrentUserIdentifier($db);
    error_log("User identifier: " . json_encode($userIdentifier));
    
    $voteValue = $isHelpful ? 1 : 0;
    
    try {
        $db->begin_transaction();
        
        // Check if the review exists
        $stmt = $db->prepare('SELECT user_id, anonymous_token, helpful_votes, not_helpful_votes FROM reviews WHERE id = ?');
        if (!$stmt) {
            throw new Exception("Failed to prepare review existence check query: " . $db->error);
        }
        $stmt->bind_param('i', $reviewId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute review existence check query: " . $stmt->error);
        }
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $db->rollback();
            echo json_encode(['success' => false, 'error' => 'Review not found']);
            return true;
        }
        
        $review = $result->fetch_assoc();
        
        // Check if user is voting on their own review
        if ($userIdentifier['type'] === 'user_id' && $review['user_id'] == $userIdentifier['value']) {
            $db->rollback();
            echo json_encode(['success' => false, 'error' => 'You cannot vote on your own review']);
            return true;
        }
        if ($userIdentifier['type'] === 'anonymous_token' && $review['anonymous_token'] == $userIdentifier['value']) {
            $db->rollback();
            echo json_encode(['success' => false, 'error' => 'You cannot vote on your own review']);
            return true;
        }
        
        // Check for existing votes (using both user_id and anonymous_token)
        $existingVote = null;
        $voteId = null;
        
        if ($userIdentifier['type'] === 'user_id') {
            $userId = $userIdentifier['value'];
            $anonymousToken = $userIdentifier['anonymous_token'];
            
            $query = 'SELECT id, is_helpful FROM review_votes WHERE review_id = ? AND (user_id = ?';
            $params = [$reviewId, $userId];
            $types = 'ii';
            
            if ($anonymousToken) {
                $query .= ' OR anonymous_token = ?';
                $params[] = $anonymousToken;
                $types .= 's';
            }
            $query .= ')';
            
            $stmt = $db->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $existingVote = $result->fetch_assoc();
                $voteId = $existingVote['id'];
            }
        } else {
            $anonymousToken = $userIdentifier['value'];
            
            if (!$anonymousToken) {
                $anonymousUser = ensureAnonymousUser($db);
                $anonymousToken = $anonymousUser['anonymous_token'];
            }
            
            $stmt = $db->prepare('SELECT id, is_helpful FROM review_votes WHERE review_id = ? AND anonymous_token = ?');
            $stmt->bind_param('is', $reviewId, $anonymousToken);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $existingVote = $result->fetch_assoc();
                $voteId = $existingVote['id'];
            }
        }
        
        if ($existingVote) {
            // Update existing vote
            if ((int)$existingVote['is_helpful'] === $voteValue) {
                $stmt = $db->prepare('DELETE FROM review_votes WHERE id = ?');
                $stmt->bind_param('i', $voteId);
                $stmt->execute();
            } else {
                $stmt = $db->prepare('UPDATE review_votes SET is_helpful = ?, updated_at = NOW() WHERE id = ?');
                $stmt->bind_param('ii', $voteValue, $voteId);
                $stmt->execute();
            }
        } else {
            // Insert new vote
            if ($userIdentifier['type'] === 'user_id') {
                $userId = $userIdentifier['value'];
                $stmt = $db->prepare('INSERT INTO review_votes (review_id, user_id, is_helpful) VALUES (?, ?, ?)');
                $stmt->bind_param('iii', $reviewId, $userId, $voteValue);
            } else {
                $anonymousToken = $userIdentifier['value'];
                $stmt = $db->prepare('INSERT INTO review_votes (review_id, anonymous_token, is_helpful) VALUES (?, ?, ?)');
                $stmt->bind_param('isi', $reviewId, $anonymousToken, $voteValue);
            }
            $stmt->execute();
        }
        
        // Fetch updated counts (triggers will have updated them)
        $stmt = $db->prepare('SELECT helpful_votes, not_helpful_votes FROM reviews WHERE id = ?');
        $stmt->bind_param('i', $reviewId);
        $stmt->execute();
        $result = $stmt->get_result();
        $counts = $result->fetch_assoc();
        
        $helpfulCount = (int)($counts['helpful_votes'] ?? 0);
        $notHelpfulCount = (int)($counts['not_helpful_votes'] ?? 0);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'helpful_votes' => $helpfulCount,
            'not_helpful_votes' => $notHelpfulCount,
            'total_votes' => $helpfulCount + $notHelpfulCount
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log('Error recording review vote: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to record vote: ' . $e->getMessage()]);
        return true;
    }
    
    return true;
}

/**
 * Handle checking if user has voted on a review
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleCheckReviewVote($db) {
    $reviewId = isset($_GET['reviewId']) ? (int)$_GET['reviewId'] : 0;
    
    if (!$reviewId) {
        echo json_encode(['success' => false, 'error' => 'Invalid review ID']);
        return true;
    }
    
    $userIdentifier = getCurrentUserIdentifier($db);
    
    $query = 'SELECT is_helpful FROM review_votes WHERE review_id = ? AND (';
    $params = [$reviewId];
    $types = 'i';
    
    if ($userIdentifier['type'] === 'user_id') {
        $userId = $userIdentifier['value'];
        $anonymousToken = $userIdentifier['anonymous_token'];
        
        $query .= 'user_id = ?';
        $params[] = $userId;
        $types .= 'i';
        
        if ($anonymousToken) {
            $query .= ' OR anonymous_token = ?';
            $params[] = $anonymousToken;
            $types .= 's';
        }
    } else {
        $anonymousToken = $userIdentifier['value'];
        
        if (!$anonymousToken) {
            echo json_encode([
                'success' => true,
                'hasVoted' => false
            ]);
            return true;
        }
        
        $query .= 'anonymous_token = ?';
        $params[] = $anonymousToken;
        $types .= 's';
    }
    
    $query .= ')';
    
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $isHelpful = (bool)(int)$result->fetch_assoc()['is_helpful'];
        echo json_encode([
            'success' => true,
            'hasVoted' => true,
            'isHelpful' => $isHelpful
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'hasVoted' => false
        ]);
    }
    
    return true;
}
   