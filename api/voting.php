<?php
function handleVotingActions($action, $db) {
    if ($action === 'likeGame' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $game_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $session_id = session_id();

        // Check if the user (logged-in or anonymous) has already voted on this game
        $stmt = $db->prepare('SELECT vote_type FROM game_votes WHERE game_id = ? AND (user_id = ? OR (user_id IS NULL AND session_id = ?))');
        if (!$stmt) {
            echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('iis', $game_id, $user_id, $session_id);
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $existing_vote = $result->fetch_assoc()['vote_type'];
            if ($existing_vote === 'like') {
                echo json_encode(['status' => 'failed', 'error' => 'You have already liked this game']);
                return true;
            } else {
                // User is changing their vote from dislike to like
                $stmt = $db->prepare('UPDATE game_votes SET vote_type = "like" WHERE game_id = ? AND (user_id = ? OR (user_id IS NULL AND session_id = ?))');
                if (!$stmt) {
                    echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
                    return true;
                }
                $stmt->bind_param('iis', $game_id, $user_id, $session_id);
            }
        } else {
            // User hasn’t voted yet, insert a new vote
            $stmt = $db->prepare('INSERT INTO game_votes (game_id, vote_type, user_id, session_id) VALUES (?, "like", ?, ?)');
            if (!$stmt) {
                echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
                return true;
            }
            $stmt->bind_param('iis', $game_id, $user_id, $session_id);
        }

        if (!$stmt->execute()) {
            echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();
        echo json_encode(['status' => 'liked']);
        return true;
    } elseif ($action === 'dislikeGame' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $game_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $session_id = session_id();

        // Check if the user (logged-in or anonymous) has already voted on this game
        $stmt = $db->prepare('SELECT vote_type FROM game_votes WHERE game_id = ? AND (user_id = ? OR (user_id IS NULL AND session_id = ?))');
        if (!$stmt) {
            echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('iis', $game_id, $user_id, $session_id);
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $existing_vote = $result->fetch_assoc()['vote_type'];
            if ($existing_vote === 'dislike') {
                echo json_encode(['status' => 'failed', 'error' => 'You have already disliked this game']);
                return true;
            } else {
                // User is changing their vote from like to dislike
                $stmt = $db->prepare('UPDATE game_votes SET vote_type = "dislike" WHERE game_id = ? AND (user_id = ? OR (user_id IS NULL AND session_id = ?))');
                if (!$stmt) {
                    echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
                    return true;
                }
                $stmt->bind_param('iis', $game_id, $user_id, $session_id);
            }
        } else {
            // User hasn’t voted yet, insert a new vote
            $stmt = $db->prepare('INSERT INTO game_votes (game_id, vote_type, user_id, session_id) VALUES (?, "dislike", ?, ?)');
            if (!$stmt) {
                echo json_encode(['status' => 'failed', 'error' => 'Prepare failed: ' . $db->error]);
                return true;
            }
            $stmt->bind_param('iis', $game_id, $user_id, $session_id);
        }

        if (!$stmt->execute()) {
            echo json_encode(['status' => 'failed', 'error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();
        echo json_encode(['status' => 'disliked']);
        return true;
    } elseif ($action === 'getGameVotes' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $game_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
        
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM game_votes WHERE game_id = ? AND vote_type = "like"');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $game_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $likes = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();

        $stmt = $db->prepare('SELECT COUNT(*) as count FROM game_votes WHERE game_id = ? AND vote_type = "dislike"');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $game_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $dislikes = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();

        $total = $likes + $dislikes;
        echo json_encode(['likes' => (int)$likes, 'dislikes' => (int)$dislikes, 'total' => (int)$total]);
        return true;
    } elseif ($action === 'checkUserVote' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $game_id = isset($_GET['gameId']) ? (int)$_GET['gameId'] : -1;
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $session_id = session_id();

        $stmt = $db->prepare('SELECT vote_type FROM game_votes WHERE game_id = ? AND (user_id = ? OR (user_id IS NULL AND session_id = ?))');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('iis', $game_id, $user_id, $session_id);
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
    }
    
}