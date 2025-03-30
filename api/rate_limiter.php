<?php
// filepath: e:\bakcup\xampp\htdocs\gamerating\api\rate_limiter.php

/**
 * Rate limiting functionality for the API
 * 
 * This file handles rate limiting for API requests to prevent abuse
 */

/**
 * Check if a request is within rate limits
 */
function checkRateLimit($db = null) {
    // If no database connection is provided, skip rate limiting
    if (!$db) {
        return true;
    }
    
    // Get client IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Create the rate_limits table if it doesn't exist
    $db->query("
        CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            endpoint VARCHAR(255) NOT NULL,
            requests INT NOT NULL DEFAULT 1,
            last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (ip, endpoint)
        )
    ");
    
    // Get current endpoint
    $endpoint = $_GET['action'] ?? 'unknown';
    
    // Clean up old entries (older than 1 hour)
    $db->query("DELETE FROM rate_limits WHERE last_request < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    
    // Check if this IP has recent requests
    $stmt = $db->prepare("SELECT id, requests, last_request FROM rate_limits WHERE ip = ? AND endpoint = ?");
    $stmt->bind_param("ss", $ip, $endpoint);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $id = $row['id'];
        $requests = $row['requests'];
        $lastRequest = strtotime($row['last_request']);
        
        // If last request is more than 1 minute ago, reset counter
        if (time() - $lastRequest > 60) {
            $db->query("UPDATE rate_limits SET requests = 1, last_request = NOW() WHERE id = $id");
            return true;
        }
        
        // Check if rate limit exceeded
        if ($requests >= 60) { // 60 requests per minute
            return false;
        }
        
        // Increment request count
        $db->query("UPDATE rate_limits SET requests = requests + 1, last_request = NOW() WHERE id = $id");
        return true;
    } else {
        // First request from this IP for this endpoint
        $stmt = $db->prepare("INSERT INTO rate_limits (ip, endpoint, requests) VALUES (?, ?, 1)");
        $stmt->bind_param("ss", $ip, $endpoint);
        $stmt->execute();
        return true;
    }
}

/**
 * Return a rate limit exceeded error
 */
function returnRateLimitError() {
    header('HTTP/1.1 429 Too Many Requests');
    echo json_encode([
        'success' => false,
        'error' => 'Rate limit exceeded',
        'message' => 'You have made too many requests in a short time period. Please try again later.'
    ]);
    exit;
}
?>