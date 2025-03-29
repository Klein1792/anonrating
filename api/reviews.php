<?php
function handleReviewActions($action, $db, $user_id = null) {
    // Allow either session user_id or JWT user_id
    $effectiveUserId = $user_id;
    if (!$effectiveUserId && isset($_SESSION["user_id"])) {
        $effectiveUserId = $_SESSION["user_id"];
    }

    if ($action === 'get') {
        $stmt = $db->prepare('SELECT r.*, u.username FROM reviews r LEFT JOIN users u ON r.user_id = u.id');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
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
        echo json_encode($reviews);
        return true;
    } elseif ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['gameId']) || !isset($data['gameName']) || !isset($data['reviewText'])) {
            echo json_encode(['status' => 'failed', 'error' => 'Invalid or missing data']);
            return true;
        }
    
        $game_id = (int)$data['gameId'];
        $session_id = session_id();
    
        // Check if the user (logged-in or anonymous) has voted on the game
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM game_votes WHERE game_id = ? AND (user_id = ? OR (user_id IS NULL AND session_id = ?))');
        if (!$stmt) {
            echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('iis', $game_id, $effectiveUserId, $session_id);
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result()->fetch_assoc();
        if ($result['count'] == 0) {
            echo json_encode(['status' => 'failed', 'error' => 'You must like or dislike the game before reviewing']);
            return true;
        }
        $stmt->close();
    
        // Insert the review (user_id can be NULL for anonymous users)
        $stmt = $db->prepare('INSERT INTO reviews (gameName, reviewText, votes, verified, user_id, session_id) VALUES (?, ?, 0, 0, ?, ?)');
        if (!$stmt) {
            echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('ssis', $data['gameName'], $data['reviewText'], $effectiveUserId, $session_id);
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();
        echo json_encode(['status' => 'success']);
        return true;
    } elseif ($action === 'upvote' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $review_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
        $session_id = session_id();

        // Check if the user (logged-in or anonymous) has already voted on this review
        $stmt = $db->prepare('SELECT vote_type FROM review_votes WHERE review_id = ? AND (user_id = ? OR (user_id IS NULL AND session_id = ?))');
        if (!$stmt) {
            echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('iis', $review_id, $effectiveUserId, $session_id);
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();

        $vote_change = 0;
        if ($result->num_rows > 0) {
            $existing_vote = $result->fetch_assoc()['vote_type'];
            if ($existing_vote === 'upvote') {
                echo json_encode(['status' => 'failed', 'error' => 'You have already upvoted this review']);
                return true;
            } else {
                // User is changing their vote from downvote to upvote
                $stmt = $db->prepare('UPDATE review_votes SET vote_type = "upvote" WHERE review_id = ? AND (user_id = ? OR (user_id IS NULL AND session_id = ?))');
                if (!$stmt) {
                    echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
                    return true;
                }
                $stmt->bind_param('iis', $review_id, $effectiveUserId, $session_id);
                $vote_change = 2; // Downvote (-1) to upvote (+1) = +2
            }
        } else {
            // User hasn’t voted yet, insert a new vote
            $stmt = $db->prepare('INSERT INTO review_votes (review_id, vote_type, user_id, session_id) VALUES (?, "upvote", ?, ?)');
            if (!$stmt) {
                echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
                return true;
            }
            $stmt->bind_param('iis', $review_id, $effectiveUserId, $session_id);
            $vote_change = 1; // New upvote = +1
        }

        if (!$stmt->execute()) {
            echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();

        // Update the review's vote count
        $stmt = $db->prepare('UPDATE reviews SET votes = votes + ? WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('ii', $vote_change, $review_id);
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();

        // Check if the review should be deleted
        $stmt = $db->prepare('SELECT votes FROM reviews WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $review_id);
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if ($row['votes'] <= -5) {
                $stmt = $db->prepare('DELETE FROM reviews WHERE id = ?');
                if (!$stmt) {
                    echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
                    return true;
                }
                $stmt->bind_param('i', $review_id);
                if (!$stmt->execute()) {
                    echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
                    return true;
                }
                $stmt->close();
            }
        } else {
            $stmt->close();
            echo json_encode(['status' => 'failed', 'error' => 'Review not found']);
            return true;
        }
        $stmt->close();
        echo json_encode(['status' => 'upvoted']);
        return true;
    } elseif ($action === 'downvote' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $review_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
        $session_id = session_id();

        // Check if the user (logged-in or anonymous) has already voted on this review
        $stmt = $db->prepare('SELECT vote_type FROM review_votes WHERE review_id = ? AND (user_id = ? OR (user_id IS NULL AND session_id = ?))');
        if (!$stmt) {
            echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('iis', $review_id, $effectiveUserId, $session_id);
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();

        $vote_change = 0;
        if ($result->num_rows > 0) {
            $existing_vote = $result->fetch_assoc()['vote_type'];
            if ($existing_vote === 'downvote') {
                echo json_encode(['status' => 'failed', 'error' => 'You have already downvoted this review']);
                return true;
            } else {
                // User is changing their vote from upvote to downvote
                $stmt = $db->prepare('UPDATE review_votes SET vote_type = "downvote" WHERE review_id = ? AND (user_id = ? OR (user_id IS NULL AND session_id = ?))');
                if (!$stmt) {
                    echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
                    return true;
                }
                $stmt->bind_param('iis', $review_id, $effectiveUserId, $session_id);
                $vote_change = -2; // Upvote (+1) to downvote (-1) = -2
            }
        } else {
            // User hasn’t voted yet, insert a new vote
            $stmt = $db->prepare('INSERT INTO review_votes (review_id, vote_type, user_id, session_id) VALUES (?, "downvote", ?, ?)');
            if (!$stmt) {
                echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
                return true;
            }
            $stmt->bind_param('iis', $review_id, $effectiveUserId, $session_id);
            $vote_change = -1; // New downvote = -1
        }

        if (!$stmt->execute()) {
            echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();

        // Update the review's vote count
        $stmt = $db->prepare('UPDATE reviews SET votes = votes + ? WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('ii', $vote_change, $review_id);
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();

        // Check if the review should be deleted
        $stmt = $db->prepare('SELECT votes FROM reviews WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $review_id);
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if ($row['votes'] <= -5) {
                $stmt = $db->prepare('DELETE FROM reviews WHERE id = ?');
                if (!$stmt) {
                    echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
                    return true;
                }
                $stmt->bind_param('i', $review_id);
                if (!$stmt->execute()) {
                    echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
                    return true;
                }
                $stmt->close();
            }
        } else {
            $stmt->close();
            echo json_encode(['status' => 'failed', 'error' => 'Review not found']);
            return true;
        }
        $stmt->close();
        echo json_encode(['status' => 'downvoted']);
        return true;
    } elseif ($action === 'checkReviewVote' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $review_id = isset($_GET['reviewId']) ? (int)$_GET['reviewId'] : -1;
        $session_id = session_id();

        $stmt = $db->prepare('SELECT vote_type FROM review_votes WHERE review_id = ? AND (user_id = ? OR (user_id IS NULL AND session_id = ?))');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('iis', $review_id, $effectiveUserId, $session_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $vote = $result->fetch_assoc();
            echo json_encode(['hasVoted' => true, 'voteType' => $vote['vote_type']]);
        } else {
            echo json_encode(['hasVoted' => false]);
        }
        $stmt->close();
        return true;
    } elseif ($action === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if the user is an admin or moderator
        if (!isset($effectiveUserId)) {
            echo json_encode(['error' => 'Unauthorized: You must be logged in to verify reviews']);
            return true;
        }
        $stmt = $db->prepare('SELECT is_admin, is_moderator FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $effectiveUserId);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();
        if (!$result || $result->num_rows === 0) {
            echo json_encode(['error' => 'Unauthorized: User not found']);
            return true;
        }
        $user = $result->fetch_assoc();
        $stmt->close();
        if (!$user['is_admin'] && !$user['is_moderator']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin or moderator to verify reviews']);
            return true;
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
        $stmt = $db->prepare('UPDATE reviews SET verified = 1 WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();
        echo json_encode(['status' => 'verified']);
        return true;
    } elseif ($action === 'unverify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if the user is an admin or moderator
        if (!isset($effectiveUserId)) {
            echo json_encode(['error' => 'Unauthorized: You must be logged in to unverify reviews']);
            return true;
        }
        $stmt = $db->prepare('SELECT is_admin, is_moderator FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $effectiveUserId);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();
        if (!$result || $result->num_rows === 0) {
            echo json_encode(['error' => 'Unauthorized: User not found']);
            return true;
        }
        $user = $result->fetch_assoc();
        $stmt->close();
        if (!$user['is_admin'] && !$user['is_moderator']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin or moderator to unverify reviews']);
            return true;
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
        $stmt = $db->prepare('UPDATE reviews SET verified = 0 WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();
        echo json_encode(['status' => 'unverified']);
        return true;
    } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if the user is logged in
        if (!isset($effectiveUserId)) {
            echo json_encode(['error' => 'Unauthorized: You must be logged in to delete reviews']);
            return true;
        }
        
        // Get the review ID
        $review_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
        if ($review_id <= 0) {
            echo json_encode(['error' => 'Invalid review ID']);
            return true;
        }
        
        // Check if the user is an admin or moderator
        $stmt = $db->prepare('SELECT is_admin, is_moderator FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $effectiveUserId);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();
        if (!$result || $result->num_rows === 0) {
            echo json_encode(['error' => 'Unauthorized: User not found']);
            return true;
        }
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Get review information to check if user is the author (users can delete their own reviews)
        $stmt = $db->prepare('SELECT user_id FROM reviews WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $review_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();
        if (!$result || $result->num_rows === 0) {
            echo json_encode(['error' => 'Review not found']);
            return true;
        }
        $review = $result->fetch_assoc();
        $stmt->close();
        
        // Check if user has permission to delete (admin, moderator, or review author)
        $isAuthor = $review['user_id'] == $effectiveUserId;
        $canDelete = $user['is_admin'] || $user['is_moderator'] || $isAuthor;
        
        if (!$canDelete) {
            echo json_encode(['error' => 'Unauthorized: You do not have permission to delete this review']);
            return true;
        }
        
        // First delete any votes for this review to maintain database integrity
        $stmt = $db->prepare('DELETE FROM review_votes WHERE review_id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $review_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Could not delete review votes: ' . $stmt->error]);
            return true;
        }
        $stmt->close();
        
        // Now delete the review
        $stmt = $db->prepare('DELETE FROM reviews WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $review_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Could not delete review: ' . $stmt->error]);
            return true;
        }
        $stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Review deleted successfully'
        ]);
        return true;
    }
    
    return false;
}