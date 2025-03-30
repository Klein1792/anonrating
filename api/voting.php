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
    
    // Ensure games_votes table exists
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
    
    // Get user identifier (registered user or anonymous)
    $userIdentifier = getCurrentUserIdentifier();
    
    try {
        $db->begin_transaction();
        
        if ($userIdentifier['type'] === 'user_id') {
            // Registered user
            $userId = $userIdentifier['value'];
            
            // Check if the user already voted
            $stmt = $db->prepare('SELECT id, vote FROM game_votes WHERE game_id = ? AND user_id = ?');
            $stmt->bind_param('ii', $gameId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing vote
                $existing = $result->fetch_assoc();
                if ((int)$existing['vote'] === $vote) {
                    // Same vote, remove it (toggle behavior)
                    $stmt = $db->prepare('DELETE FROM game_votes WHERE id = ?');
                    $stmt->bind_param('i', $existing['id']);
                    $stmt->execute();
                } else {
                    // Different vote, update it
                    $stmt = $db->prepare('UPDATE game_votes SET vote = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->bind_param('ii', $vote, $existing['id']);
                    $stmt->execute();
                }
            } else {
                // Insert new vote
                $stmt = $db->prepare('INSERT INTO game_votes (game_id, user_id, vote) VALUES (?, ?, ?)');
                $stmt->bind_param('iii', $gameId, $userId, $vote);
                $stmt->execute();
            }
        } else {
            // Anonymous user
            $anonymousToken = $userIdentifier['value'];
            
            // Ensure we have a valid anonymous token
            if (!$anonymousToken) {
                $anonymousUser = ensureAnonymousUser($db);
                $anonymousToken = $anonymousUser['anonymous_token'];
            }
            
            // Check if the anonymous user already voted
            $stmt = $db->prepare('SELECT id, vote FROM game_votes WHERE game_id = ? AND anonymous_token = ?');
            $stmt->bind_param('is', $gameId, $anonymousToken);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing vote
                $existing = $result->fetch_assoc();
                if ((int)$existing['vote'] === $vote) {
                    // Same vote, remove it (toggle behavior)
                    $stmt = $db->prepare('DELETE FROM game_votes WHERE id = ?');
                    $stmt->bind_param('i', $existing['id']);
                    $stmt->execute();
                } else {
                    // Different vote, update it
                    $stmt = $db->prepare('UPDATE game_votes SET vote = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->bind_param('ii', $vote, $existing['id']);
                    $stmt->execute();
                }
            } else {
                // Insert new vote
                $stmt = $db->prepare('INSERT INTO game_votes (game_id, anonymous_token, vote) VALUES (?, ?, ?)');
                $stmt->bind_param('isi', $gameId, $anonymousToken, $vote);
                $stmt->execute();
            }
        }
        
        // Get updated vote counts
        $stmt = $db->prepare('
            SELECT 
                SUM(vote = 1) as likes, 
                SUM(vote = 0) as dislikes
            FROM game_votes
            WHERE game_id = ?
        ');
        $stmt->bind_param('i', $gameId);
        $stmt->execute();
        $result = $stmt->get_result();
        $votes = $result->fetch_assoc();
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'likes' => (int)$votes['likes'],
            'dislikes' => (int)$votes['dislikes'],
            'total' => (int)$votes['likes'] + (int)$votes['dislikes']
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
    
    $userIdentifier = getCurrentUserIdentifier();
    
    if ($userIdentifier['type'] === 'user_id') {
        $userId = $userIdentifier['value'];
        $stmt = $db->prepare('SELECT vote FROM game_votes WHERE game_id = ? AND user_id = ?');
        $stmt->bind_param('ii', $gameId, $userId);
    } else {
        $anonymousToken = $userIdentifier['value'];
        
        if (!$anonymousToken) {
            echo json_encode(['hasVoted' => false]);
            return true;
        }
        
        $stmt = $db->prepare('SELECT vote FROM game_votes WHERE game_id = ? AND anonymous_token = ?');
        $stmt->bind_param('is', $gameId, $anonymousToken);
    }
    
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
    // Accept either id or gameId parameter for flexibility
    $gameId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['gameId']) ? (int)$_GET['gameId'] : 0);
    
    if (!$gameId) {
        echo json_encode(['success' => false, 'error' => 'Invalid game ID']);
        return true;
    }
    
    // Get vote counts
    $stmt = $db->prepare('
        SELECT 
            SUM(vote = 1) as likes, 
            SUM(vote = 0) as dislikes
        FROM game_votes
        WHERE game_id = ?
    ');
    $stmt->bind_param('i', $gameId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $votes = $result->fetch_assoc();
        $likes = (int)$votes['likes'];
        $dislikes = (int)$votes['dislikes'];
        $total = $likes + $dislikes;
        
        echo json_encode([
            'success' => true,
            'likes' => $likes,
            'dislikes' => $dislikes,
            'total' => $total,
            'approval' => $total > 0 ? round(($likes / $total) * 100, 1) : 0
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
    // Get vote data from request
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        return true;
    }
    
    // Extract and validate vote parameters
    $reviewId = isset($data['reviewId']) ? (int)$data['reviewId'] : 0;
    $isHelpful = isset($data['isHelpful']) ? (bool)$data['isHelpful'] : null;
    
    if (!$reviewId || $isHelpful === null) {
        echo json_encode(['success' => false, 'error' => 'Invalid review ID or vote value']);
        return true;
    }
    
    // Ensure review_votes table exists
    $db->query("
        CREATE TABLE IF NOT EXISTS review_votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_id INT NOT NULL,
            user_id INT NULL,
            anonymous_token VARCHAR(64) NULL,
            is_helpful TINYINT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY user_review (review_id, user_id),
            UNIQUE KEY anon_review (review_id, anonymous_token)
        )
    ");
    
    // Get user identifier (registered user or anonymous)
    $userIdentifier = getCurrentUserIdentifier();
    $voteValue = $isHelpful ? 1 : 0;
    
    try {
        $db->begin_transaction();
        
        // First check if the user is voting on their own review (not allowed)
        $ownerCheckQuery = 'SELECT id FROM reviews WHERE id = ? AND ';
        $ownerParams = [$reviewId];
        $ownerTypes = 'i';
        
        if ($userIdentifier['type'] === 'user_id') {
            $ownerCheckQuery .= 'user_id = ?';
            $ownerParams[] = $userIdentifier['value'];
            $ownerTypes .= 'i';
        } else {
            $ownerCheckQuery .= 'anonymous_token = ?';
            $ownerParams[] = $userIdentifier['value'];
            $ownerTypes .= 's';
        }
        
        $stmt = $db->prepare($ownerCheckQuery);
        $stmt->bind_param($ownerTypes, ...$ownerParams);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $db->rollback();
            echo json_encode(['success' => false, 'error' => 'You cannot vote on your own review']);
            return true;
        }
        
        if ($userIdentifier['type'] === 'user_id') {
            // Registered user
            $userId = $userIdentifier['value'];
            
            // Check if the user already voted
            $stmt = $db->prepare('SELECT id, is_helpful FROM review_votes WHERE review_id = ? AND user_id = ?');
            $stmt->bind_param('ii', $reviewId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing vote
                $existing = $result->fetch_assoc();
                if ((int)$existing['is_helpful'] === $voteValue) {
                    // Same vote, remove it (toggle behavior)
                    $stmt = $db->prepare('DELETE FROM review_votes WHERE id = ?');
                    $stmt->bind_param('i', $existing['id']);
                    $stmt->execute();
                } else {
                    // Different vote, update it
                    $stmt = $db->prepare('UPDATE review_votes SET is_helpful = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->bind_param('ii', $voteValue, $existing['id']);
                    $stmt->execute();
                }
            } else {
                // Insert new vote
                $stmt = $db->prepare('INSERT INTO review_votes (review_id, user_id, is_helpful) VALUES (?, ?, ?)');
                $stmt->bind_param('iii', $reviewId, $userId, $voteValue);
                $stmt->execute();
            }
        } else {
            // Anonymous user
            $anonymousToken = $userIdentifier['value'];
            
            // Ensure we have a valid anonymous token
            if (!$anonymousToken) {
                $anonymousUser = ensureAnonymousUser($db);
                $anonymousToken = $anonymousUser['anonymous_token'];
            }
            
            // Check if the anonymous user already voted
            $stmt = $db->prepare('SELECT id, is_helpful FROM review_votes WHERE review_id = ? AND anonymous_token = ?');
            $stmt->bind_param('is', $reviewId, $anonymousToken);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing vote
                $existing = $result->fetch_assoc();
                if ((int)$existing['is_helpful'] === $voteValue) {
                    // Same vote, remove it (toggle behavior)
                    $stmt = $db->prepare('DELETE FROM review_votes WHERE id = ?');
                    $stmt->bind_param('i', $existing['id']);
                    $stmt->execute();
                } else {
                    // Different vote, update it
                    $stmt = $db->prepare('UPDATE review_votes SET is_helpful = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->bind_param('ii', $voteValue, $existing['id']);
                    $stmt->execute();
                }
            } else {
                // Insert new vote
                $stmt = $db->prepare('INSERT INTO review_votes (review_id, anonymous_token, is_helpful) VALUES (?, ?, ?)');
                $stmt->bind_param('isi', $reviewId, $anonymousToken, $voteValue);
                $stmt->execute();
            }
        }
        
        // Update review vote counts
        $stmt = $db->prepare('
            SELECT 
                SUM(is_helpful = 1) as helpful, 
                SUM(is_helpful = 0) as not_helpful
            FROM review_votes
            WHERE review_id = ?
        ');
        $stmt->bind_param('i', $reviewId);
        $stmt->execute();
        $result = $stmt->get_result();
        $votes = $result->fetch_assoc();
        
        $helpfulCount = (int)$votes['helpful'];
        $notHelpfulCount = (int)$votes['not_helpful'];
        
        // Update the review with new vote counts
        $stmt = $db->prepare('
            UPDATE reviews 
            SET helpful_votes = ?, not_helpful_votes = ?
            WHERE id = ?
        ');
        $stmt->bind_param('iii', $helpfulCount, $notHelpfulCount, $reviewId);
        $stmt->execute();
        
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
    
    // Get user identifier
    $userIdentifier = getCurrentUserIdentifier();
    
    if ($userIdentifier['type'] === 'user_id') {
        $userId = $userIdentifier['value'];
        $stmt = $db->prepare('SELECT is_helpful FROM review_votes WHERE review_id = ? AND user_id = ?');
        $stmt->bind_param('ii', $reviewId, $userId);
    } else {
        $anonymousToken = $userIdentifier['value'];
        
        if (!$anonymousToken) {
            echo json_encode([
                'success' => true,
                'hasVoted' => false
            ]);
            return true;
        }
        
        $stmt = $db->prepare('SELECT is_helpful FROM review_votes WHERE review_id = ? AND anonymous_token = ?');
        $stmt->bind_param('is', $reviewId, $anonymousToken);
    }
    
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