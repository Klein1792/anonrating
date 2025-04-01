<?php
/**
 * Handle batched API requests
 * 
 * @param string $action The API action
 * @param object $db Database connection
 * @return bool True if handled
 */
function handleBatchRequests($action, $db) {
    if ($action !== 'batch') {
        return false;
    }

    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success' => false,
            'error' => 'Batch requests must use POST method'
        ]);
        return true;
    }
    
    // Get the JSON request body
    $requestBody = file_get_contents('php://input');
    $requests = json_decode($requestBody, true);
    
    if (!isset($requests['batch']) || !is_array($requests['batch'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid batch request format'
        ]);
        return true;
    }
    
    // Process each request and collect responses
    $responses = [];
    
    foreach ($requests['batch'] as $index => $request) {
        $action = $request['action'] ?? '';
        $params = $request['params'] ?? [];
        
        // Store original GET and POST data
        $originalGet = $_GET;
        $originalPost = $_POST;
        
        // Set request parameters
        $_GET['action'] = $action;
        foreach ($params as $key => $value) {
            $_GET[$key] = $value;
        }
        
        // Start output buffering to capture the API response
        ob_start();
        
        // Execute the appropriate handler based on action
        $handled = false;
        
        // Try to handle with different handlers based on action
        if (function_exists('handleIgdbActions')) {
            $handled = handleIgdbActions($_GET['action'], $db, IGDB_CLIENT_ID, IGDB_CLIENT_SECRET);
        }
        
        if (!$handled && function_exists('handleReviewActions')) {
            $handled = handleReviewActions($_GET['action'], $db);
        }
        
        if (!$handled && function_exists('handleVotingActions')) {
            $handled = handleVotingActions($_GET['action'], $db);
        }
        
        if (!$handled && function_exists('handleStatsActions')) {
            $handled = handleStatsActions($_GET['action'], $db);
        }
        
        // Capture the output
        $responseJson = ob_get_clean();
        $response = json_decode($responseJson, true);
        
        // Add to responses array
        $responses[] = $response ?? [
            'success' => false,
            'error' => 'Failed to process request: ' . $action
        ];
        
        // Restore original GET and POST data
        $_GET = $originalGet;
        $_POST = $originalPost;
    }
    
    // Return all responses
    echo json_encode([
        'success' => true,
        'responses' => $responses
    ]);
    
    return true;
}