<?php
/**
 * Handle statistics-related actions
 * 
 * @param string $action The API action
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleStatsActions($action, $db) {
    if ($action === 'getStatistics') {
        try {
            $data = [
                'success' => true,
                'total_games' => 0,
                'total_reviews' => 0,
                'total_users' => 0
            ];
            
            // Check if tables exist before querying
            $tables = [];
            $tableResult = $db->query("SHOW TABLES");
            if ($tableResult) {
                while ($row = $tableResult->fetch_row()) {
                    $tables[] = strtolower($row[0]);
                }
            }
            
            // Get total games count (if table exists)
            if (in_array('games', $tables)) {
                $result = $db->query('SELECT COUNT(*) as total FROM games');
                if ($result) {
                    $data['total_games'] = (int)$result->fetch_assoc()['total'];
                }
            }
            
            // Get total reviews count (if table exists)
            if (in_array('reviews', $tables)) {
                $reviewsResult = $db->query('SELECT COUNT(*) as total FROM reviews');
                if ($reviewsResult && $row = $reviewsResult->fetch_assoc()) {
                    $data['total_reviews'] = (int)$row['total'];
                }
            }
            
            // Get total users count (if table exists)
            if (in_array('users', $tables)) {
                $usersResult = $db->query('SELECT COUNT(*) as total FROM users');
                if ($usersResult && $row = $usersResult->fetch_assoc()) {
                    $data['total_users'] = (int)$row['total'];
                }
            }
            
            echo json_encode($data);
            return true;
            
        } catch (Exception $e) {
            // Log the error for debugging
            error_log('Error in getStatistics: ' . $e->getMessage());
            
            echo json_encode([
                'success' => false,
                'error' => 'Database error occurred: ' . $e->getMessage()
            ]);
            return true;
        }
    }
    
    return false;
}