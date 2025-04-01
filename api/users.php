<?php
function handleUserActions($action, $db, $user_id) {
    if ($action === 'getUsers') {
        // Check if the user is an admin - using passed user_id param instead of session
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $user_id);
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
        if (!$user['is_admin']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin']);
            return true;
        }
        // Pagination, search, and filter parameters
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
        $offset = ($page - 1) * $limit;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $filter_admin = isset($_GET['filter_admin']) ? (int)$_GET['filter_admin'] : 0; // 1 to filter admins, 0 to ignore
        $filter_moderator = isset($_GET['filter_moderator']) ? (int)$_GET['filter_moderator'] : 0; // 1 to filter moderators, 0 to ignore
    
        // Build the query with search and role filters
        $query = "SELECT id, username, is_admin, is_moderator, is_banned FROM users";
        $countQuery = "SELECT COUNT(*) as total FROM users";
        $params = [];
        $types = '';
        $conditions = [];
    
        if ($search !== '') {
            $conditions[] = "(username LIKE ? OR id = ?)";
            $searchLike = '%' . $search . '%';
            if (is_numeric($search)) {
                $params[] = $searchLike;
                $params[] = (int)$search;
                $types .= 'si';
            } else {
                $params[] = $searchLike;
                $params[] = 0;
                $types .= 'si';
            }
        }
    
        if ($filter_admin === 1) {
            $conditions[] = "is_admin = 1";
        }
    
        if ($filter_moderator === 1) {
            $conditions[] = "is_moderator = 1";
        }
    
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
            $countQuery .= " WHERE " . implode(' AND ', $conditions);
        }
    
        // Get total number of users (with filters)
        $stmt = $db->prepare($countQuery);
        if ($search !== '') {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $totalResult = $stmt->get_result();
        $totalRow = $totalResult->fetch_assoc();
        $totalUsers = $totalRow['total'];
        $totalPages = ceil($totalUsers / $limit);
        $stmt->close();
    
        // Fetch users for the current page (with filters)
        $query .= " LIMIT ? OFFSET ?";
        $stmt = $db->prepare($query);
        if ($search !== '') {
            $stmt->bind_param($types . 'ii', ...array_merge($params, [$limit, $offset]));
        } else {
            $stmt->bind_param('ii', $limit, $offset);
        }
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'is_admin' => (bool)$row['is_admin'],
                'is_moderator' => (bool)$row['is_moderator'],
                'is_banned' => (bool)$row['is_banned']
            ];
        }
        $stmt->close();
    
        echo json_encode([
            'users' => $users,
            'total_users' => $totalUsers,
            'total_pages' => $totalPages,
            'current_page' => $page
        ]);
        return true;
    }elseif ($action === 'getModerators') {
        // Check if the user is an admin - using passed user_id param
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $user_id);
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
        if (!$user['is_admin']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin']);
            return true;
        }
    
        // Fetch moderators
        $stmt = $db->prepare('SELECT id, username, is_admin, is_moderator, is_banned FROM users WHERE is_moderator = 1');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();
        $moderators = [];
        while ($row = $result->fetch_assoc()) {
            $moderators[] = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'is_admin' => (bool)$row['is_admin'],
                'is_moderator' => (bool)$row['is_moderator'],
                'is_banned' => (bool)$row['is_banned']
            ];
        }
        $stmt->close();
        echo json_encode($moderators);
        return true;
    } elseif ($action === 'getAdmins') {
        // Check if the user is an admin - using passed user_id param
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $user_id);
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
        if (!$user['is_admin']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin']);
            return true;
        }
    
        // Fetch admins
        $stmt = $db->prepare('SELECT id, username, is_admin, is_moderator, is_banned FROM users WHERE is_admin = 1');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $result = $stmt->get_result();
        $admins = [];
        while ($row = $result->fetch_assoc()) {
            $admins[] = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'is_admin' => (bool)$row['is_admin'],
                'is_moderator' => (bool)$row['is_moderator'],
                'is_banned' => (bool)$row['is_banned']
            ];
        }
        $stmt->close();
        echo json_encode($admins);
        return true;
    }elseif ($action === 'banUser' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if the user is an admin - using passed user_id param
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $user_id);
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
        if (!$user['is_admin']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin']);
            return true;
        }

        $target_user_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
        if ($target_user_id === $user_id) {
            echo json_encode(['error' => 'You cannot ban yourself']);
            return true;
        }

        $stmt = $db->prepare('UPDATE users SET is_banned = 1 WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $target_user_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();
        echo json_encode(['message' => 'User banned successfully']);
        return true;
    } elseif ($action === 'unbanUser' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if the user is an admin - using passed user_id param
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $user_id);
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
        if (!$user['is_admin']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin']);
            return true;
        }

        $target_user_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
        $stmt = $db->prepare('UPDATE users SET is_banned = 0 WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $target_user_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();
        echo json_encode(['message' => 'User unbanned successfully']);
        return true;
    } elseif ($action === 'setModerator' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if the user is an admin - using passed user_id param
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $user_id);
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
        if (!$user['is_admin']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin']);
            return true;
        }

        $target_user_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
        $stmt = $db->prepare('UPDATE users SET is_moderator = 1 WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $target_user_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();
        echo json_encode(['message' => 'User set as moderator successfully']);
        return true;
    } elseif ($action === 'removeModerator' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if the user is an admin - using passed user_id param
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $user_id);
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
        if (!$user['is_admin']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin']);
            return true;
        }

        $target_user_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
        $stmt = $db->prepare('UPDATE users SET is_moderator = 0 WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $target_user_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();
        echo json_encode(['message' => 'Moderator status removed successfully']);
        return true;
    } elseif ($action === 'setAdmin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if the user is an admin - using passed user_id param
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $user_id);
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
        if (!$user['is_admin']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin']);
            return true;
        }

        $target_user_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
        $stmt = $db->prepare('UPDATE users SET is_admin = 1 WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $target_user_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();
        echo json_encode(['message' => 'User set as admin successfully']);
        return true;
    } elseif ($action === 'removeAdmin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if the user is an admin - using passed user_id param
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $user_id);
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
        if (!$user['is_admin']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin']);
            return true;
        }

        $target_user_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
        if ($target_user_id === $user_id) {
            echo json_encode(['error' => 'You cannot remove your own admin status']);
            return true;
        }

        $stmt = $db->prepare('UPDATE users SET is_admin = 0 WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $target_user_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        $stmt->close();
        echo json_encode(['message' => 'Admin status removed successfully']);
        return true;
    } elseif ($action === 'getAnonymousUsers') {
        // Check if the user is an admin - using passed user_id param
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $user_id);
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
        if (!$user['is_admin']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin']);
            return true;
        }
        
        // Pagination parameters
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
        $offset = ($page - 1) * $limit;
        
        // Count total anonymous users
        $countStmt = $db->prepare('SELECT COUNT(*) as total FROM anonymous_users');
        $countStmt->execute();
        $totalResult = $countStmt->get_result();
        $totalAnon = $totalResult->fetch_assoc()['total'];
        $totalPages = ceil($totalAnon / $limit);
        
        // Fetch anonymous users with optional user mappings
        $stmt = $db->prepare("
            SELECT a.id, a.token, a.ip_address, a.first_seen, a.last_seen, a.is_banned,
                   u.id as user_id, u.username 
            FROM anonymous_users a
            LEFT JOIN user_anonymous_mapping m ON a.token = m.anonymous_token
            LEFT JOIN users u ON m.user_id = u.id
            ORDER BY a.last_seen DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $anonUsers = [];
        while ($row = $result->fetch_assoc()) {
            $anonUsers[] = [
                'id' => (int)$row['id'],
                'token' => $row['token'],
                'ip_address' => $row['ip_address'],
                'first_seen' => $row['first_seen'],
                'last_seen' => $row['last_seen'],
                'is_banned' => (bool)$row['is_banned'],
                'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
                'username' => $row['username']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'anonymous_users' => $anonUsers,
            'total_users' => $totalAnon,
            'total_pages' => $totalPages,
            'current_page' => $page
        ]);
        return true;
        
    } elseif ($action === 'banAnonymousUser' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if user is admin
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $user_id);
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
        if (!$user['is_admin']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin']);
            return true;
        }
        
        $token = isset($_GET['token']) ? $_GET['token'] : '';
        if (empty($token)) {
            echo json_encode(['error' => 'Missing token parameter']);
            return true;
        }
        
        $stmt = $db->prepare('UPDATE anonymous_users SET is_banned = 1 WHERE token = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('s', $token);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Anonymous user banned']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Anonymous user not found']);
        }
        $stmt->close();
        return true;
        
    } elseif ($action === 'unbanAnonymousUser' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if user is admin
        $stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('i', $user_id);
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
        if (!$user['is_admin']) {
            echo json_encode(['error' => 'Unauthorized: You must be an admin']);
            return true;
        }
        
        $token = isset($_GET['token']) ? $_GET['token'] : '';
        if (empty($token)) {
            echo json_encode(['error' => 'Missing token parameter']);
            return true;
        }
        
        $stmt = $db->prepare('UPDATE anonymous_users SET is_banned = 0 WHERE token = ?');
        if (!$stmt) {
            echo json_encode(['error' => 'Prepare failed: ' . $db->error]);
            return true;
        }
        $stmt->bind_param('s', $token);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
            return true;
        }
        
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Anonymous user unbanned']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Anonymous user not found']);
        }
        $stmt->close();
        return true;
    }
    return false;
}