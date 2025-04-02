<?php
require_once __DIR__ . '/../includes/auth_helper.php'; // Ensure auth_helper.php is included

/**
 * Handle adding a comment to a review
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleAddReviewComment($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        return true;
    }
    
    $reviewId = isset($data['reviewId']) ? (int)$data['reviewId'] : 0;
    $content = isset($data['content']) ? trim($data['content']) : '';
    $displayName = isset($data['displayName']) ? trim($data['displayName']) : 'Anonymous';
    
    if (!$reviewId || empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Invalid comment data']);
        return true;
    }
    
    $checkReview = $db->prepare('SELECT id FROM reviews WHERE id = ?');
    $checkReview->bind_param('i', $reviewId);
    $checkReview->execute();
    if ($checkReview->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Review not found']);
        return true;
    }
    
    $userIdentifier = getCurrentUserIdentifier($db);
    
    if ($userIdentifier['type'] === 'user_id') {
        $userId = $userIdentifier['value'];
        
        $userStmt = $db->prepare('SELECT username FROM users WHERE id = ?');
        $userStmt->bind_param('i', $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($userResult->num_rows > 0) {
            $displayName = $userResult->fetch_assoc()['username'];
        }
        
        $stmt = $db->prepare('INSERT INTO review_comments 
                             (review_id, user_id, display_name, content, is_anonymous) 
                             VALUES (?, ?, ?, ?, 0)');
        $stmt->bind_param('iiss', $reviewId, $userId, $displayName, $content);
    } else {
        $anonymousToken = $userIdentifier['value'];
        if (!$anonymousToken) {
            $anonymousUser = ensureAnonymousUser($db);
            $anonymousToken = $anonymousUser['anonymous_token'];
        }
        
        $stmt = $db->prepare('INSERT INTO review_comments 
                             (review_id, anonymous_token, display_name, content, is_anonymous) 
                             VALUES (?, ?, ?, ?, 1)');
        $stmt->bind_param('isss', $reviewId, $anonymousToken, $displayName, $content);
    }
    
    if ($stmt->execute()) {
        $commentId = $db->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Comment added successfully',
            'id' => $commentId
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to add comment: ' . $db->error
        ]);
    }
    
    return true;
}

/**
 * Handle retrieving comments for a review
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleGetReviewComments($db) {
    $reviewId = isset($_GET['reviewId']) ? (int)$_GET['reviewId'] : 0;
    
    if (!$reviewId) {
        echo json_encode(['success' => false, 'error' => 'Invalid review ID']);
        return true;
    }
    
    $stmt = $db->prepare("
        SELECT 
            rc.id, rc.review_id, rc.user_id, rc.anonymous_token, 
            rc.display_name, rc.content, rc.is_anonymous, rc.created_at,
            u.is_admin, u.is_moderator, u.is_banned,
            au.is_banned AS anonymous_banned
        FROM 
            review_comments rc
        LEFT JOIN 
            users u ON rc.user_id = u.id
        LEFT JOIN 
            anonymous_users au ON rc.anonymous_token = au.token
        WHERE 
            rc.review_id = ?
        ORDER BY 
            rc.created_at ASC
    ");
    
    $stmt->bind_param('i', $reviewId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['review_id'] = (int)$row['review_id'];
        $row['user_id'] = $row['user_id'] ? (int)$row['user_id'] : null;
        $row['is_anonymous'] = (bool)$row['is_anonymous'];
        $row['is_admin'] = (bool)($row['is_admin'] ?? false);
        $row['is_moderator'] = (bool)($row['is_moderator'] ?? false);
        
        $isBanned = (bool)($row['is_banned'] ?? $row['anonymous_banned'] ?? false);
        $row['is_banned'] = $isBanned;
        
        if ($isBanned) {
            $row['content'] = '[This comment has been removed because the user was banned]';
        }
        
        unset($row['anonymous_banned']);
        
        $comments[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'comments' => $comments
    ]);
    
    return true;
}

/**
 * Handle deleting a review comment
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleDeleteReviewComment($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['commentId'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        return true;
    }
    
    $commentId = (int)$data['commentId'];
    
    if (!$commentId) {
        echo json_encode(['success' => false, 'error' => 'Invalid comment ID']);
        return true;
    }
    
    $userIdentifier = getCurrentUserIdentifier($db);
    
    $canDelete = false;
    $reviewId = 0;
    
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
    $isModerator = isset($_SESSION['is_moderator']) && $_SESSION['is_moderator'];
    
    if ($isAdmin || $isModerator) {
        $canDelete = true;
        
        $stmt = $db->prepare('SELECT review_id FROM review_comments WHERE id = ?');
        $stmt->bind_param('i', $commentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $reviewId = (int)$result->fetch_assoc()['review_id'];
        } else {
            echo json_encode(['success' => false, 'error' => 'Comment not found']);
            return true;
        }
    } else {
        if ($userIdentifier['type'] === 'user_id') {
            $userId = $userIdentifier['value'];
            $stmt = $db->prepare('SELECT review_id FROM review_comments WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $commentId, $userId);
        } else {
            $anonymousToken = $userIdentifier['value'];
            if (!$anonymousToken) {
                echo json_encode(['success' => false, 'error' => 'Not authorized to delete this comment']);
                return true;
            }
            
            $stmt = $db->prepare('SELECT review_id FROM review_comments WHERE id = ? AND anonymous_token = ?');
            $stmt->bind_param('is', $commentId, $anonymousToken);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $canDelete = true;
            $reviewId = (int)$result->fetch_assoc()['review_id'];
        }
    }
    
    if (!$canDelete) {
        echo json_encode(['success' => false, 'error' => 'Not authorized to delete this comment']);
        return true;
    }
    
    $stmt = $db->prepare('DELETE FROM review_comments WHERE id = ?');
    $stmt->bind_param('i', $commentId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Comment deleted successfully',
            'reviewId' => $reviewId
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to delete comment: ' . $db->error
        ]);
    }
    
    return true;
}

/**
 * Handle reporting a comment as inappropriate
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleReportComment($db) {
    $userIdentifier = getCurrentUserIdentifier($db);
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['commentId']) || !isset($data['reason'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return true;
    }
    
    $commentId = (int)$data['commentId'];
    $reason = $data['reason'];
    $details = $data['details'] ?? '';
    
    $stmt = $db->prepare('SELECT id FROM review_comments WHERE id = ?');
    $stmt->bind_param('i', $commentId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Comment not found']);
        return true;
    }
    
    $stmt = $db->prepare('SELECT id FROM comment_reports 
                         WHERE comment_id = ? AND reporter_type = ? AND reporter_value = ?');
                         
    $stmt->bind_param('iss', $commentId, $userIdentifier['type'], $userIdentifier['value']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'You have already reported this comment']);
        return true;
    }
    
    $stmt = $db->prepare('INSERT INTO comment_reports 
                        (comment_id, reporter_type, reporter_value, reason, details) 
                        VALUES (?, ?, ?, ?, ?)');
                        
    $stmt->bind_param('issss', $commentId, $userIdentifier['type'], $userIdentifier['value'], $reason, $details);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Comment reported successfully']);
        return true;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to report comment: ' . $db->error]);
        return true;
    }
}

/**
 * Handle getting reported comments for admins
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleGetReportedComments($db) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $stmt = $db->prepare('SELECT is_admin, is_moderator FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: User not found']);
        return true;
    }
    
    $user = $result->fetch_assoc();
    if (!$user['is_admin'] && !$user['is_moderator']) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Admin or moderator required']);
        return true;
    }
    
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;
    
    $countStmt = $db->prepare('SELECT COUNT(*) as total FROM comment_reports');
    $countStmt->execute();
    $totalResult = $countStmt->get_result();
    $totalReports = $totalResult->fetch_assoc()['total'];
    $totalPages = ceil($totalReports / $limit);
    
    $stmt = $db->prepare("
        SELECT cr.id, cr.comment_id, cr.reporter_type, cr.reporter_value, cr.reason, cr.details, 
               cr.status, cr.created_at, cr.updated_at, 
               c.content as comment_content, c.review_id,
               r.game_id, r.title as review_title,
               g.name as game_name,
               CASE 
                    WHEN cr.reporter_type = 'user_id' THEN u.username
                    ELSE NULL
               END as reporter_name
        FROM comment_reports cr
        LEFT JOIN review_comments c ON cr.comment_id = c.id
        LEFT JOIN reviews r ON c.review_id = r.id
        LEFT JOIN games g ON r.game_id = g.id
        LEFT JOIN users u ON cr.reporter_type = 'user_id' AND cr.reporter_value = u.id
        ORDER BY 
            CASE 
                WHEN cr.status = 'pending' THEN 1
                WHEN cr.status = 'reviewing' THEN 2
                WHEN cr.status = 'rejected' THEN 3
                WHEN cr.status = 'actioned' THEN 4
                ELSE 5
            END,
            cr.created_at DESC
        LIMIT ? OFFSET ?
    ");
    if (!$stmt) {
        error_log('Prepare failed in handleGetReportedComments: ' . $db->error);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->error]);
        return true;
    }
    
    $stmt->bind_param('ii', $limit, $offset);
    if (!$stmt->execute()) {
        error_log('Execute failed in handleGetReportedComments: ' . $stmt->error);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
        return true;
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        error_log('Get result failed in handleGetReportedComments: ' . $db->error);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->error]);
        return true;
    }
    
    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    
    error_log('Fetched reported comments: ' . json_encode($reports));
    
    echo json_encode([
        'success' => true,
        'reports' => $reports,
        'total_reports' => $totalReports,
        'total_pages' => $totalPages,
        'current_page' => $page
    ]);
    return true;
}

/**
 * Get detailed information about a specific comment report
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleGetCommentReportDetails($db) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $stmt = $db->prepare('SELECT is_admin, is_moderator FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0 || !($user = $result->fetch_assoc()) || (!$user['is_admin'] && !$user['is_moderator'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return true;
    }
    
    $reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (empty($reportId)) {
        echo json_encode(['success' => false, 'error' => 'Missing report ID']);
        return true;
    }
    
    $stmt = $db->prepare("
        SELECT cr.id, cr.comment_id, cr.reporter_type, cr.reporter_value, cr.reason, cr.details, 
               cr.status, cr.created_at, cr.updated_at, 
               c.content as comment_content, c.review_id, c.user_id as commenter_id,
               r.game_id, r.title as review_title,
               g.name as game_name,
               CASE 
                    WHEN cr.reporter_type = 'user_id' THEN u_reporter.username
                    ELSE NULL
               END as reporter_name
        FROM comment_reports cr
        LEFT JOIN review_comments c ON cr.comment_id = c.id
        LEFT JOIN reviews r ON c.review_id = r.id
        LEFT JOIN games g ON r.game_id = g.id
        LEFT JOIN users u_reporter ON cr.reporter_type = 'user_id' AND cr.reporter_value = u_reporter.id
        WHERE cr.id = ?
    ");
    
    $stmt->bind_param('i', $reportId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Report not found']);
        return true;
    }
    
    $report = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'report' => $report
    ]);
    
    return true;
}

/**
 * Handle updating comment report status
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleUpdateCommentReportStatus($db) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $stmt = $db->prepare('SELECT is_admin, is_moderator FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: User not found']);
        return true;
    }
    
    $user = $result->fetch_assoc();
    if (!$user['is_admin'] && !$user['is_moderator']) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Admin or moderator required']);
        return true;
    }
    
    $reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    
    if (empty($reportId) || empty($status)) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        return true;
    }
    
    $validStatuses = ['pending', 'reviewing', 'rejected', 'actioned'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        return true;
    }
    
    $stmt = $db->prepare('UPDATE comment_reports SET status = ?, handled_by = ? WHERE id = ?');
    $stmt->bind_param('sii', $status, $user_id, $reportId);
    $result = $stmt->execute();
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Report status updated']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update report: ' . $db->error]);
    }
    
    return true;
}

/**
 * Handle admin deletion of a comment
 * 
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleAdminDeleteComment($db) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $stmt = $db->prepare('SELECT is_admin, is_moderator FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: User not found']);
        return true;
    }
    
    $user = $result->fetch_assoc();
    if (!$user['is_admin'] && !$user['is_moderator']) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Admin or moderator required']);
        return true;
    }
    
    $commentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (empty($commentId)) {
        echo json_encode(['success' => false, 'error' => 'Missing comment ID']);
        return true;
    }
    
    $stmt = $db->prepare('DELETE FROM review_comments WHERE id = ?');
    $stmt->bind_param('i', $commentId);
    $result = $stmt->execute();
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Comment successfully removed']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete comment: ' . $db->error]);
    }
    
    return true;
}

?>