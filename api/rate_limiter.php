
<?php
// Rate limiting configuration
$rateLimit = 10000; // Max requests per minute
$rateLimitWindow = 60; // Time window in seconds (1 minute)

// Get client IP address
$clientIp = $_SERVER['REMOTE_ADDR'];

// Clean up old rate limit entries (older than the rate limit window)
$db->query("DELETE FROM rate_limits WHERE last_request < NOW() - INTERVAL $rateLimitWindow SECOND");

// Check rate limit for the current IP and endpoint
$action = $_GET['action'] ?? '';
$endpoint = $action ?: $_SERVER['REQUEST_URI'];
$stmt = $db->prepare("SELECT request_count, last_request FROM rate_limits WHERE ip_address = ? AND endpoint = ?");
$stmt->bind_param('ss', $clientIp, $endpoint);
$stmt->execute();
$result = $stmt->get_result();
$rateLimitData = $result->fetch_assoc();
$stmt->close();

if ($rateLimitData) {
    $requestCount = $rateLimitData['request_count'];
    $lastRequest = strtotime($rateLimitData['last_request']);
    $currentTime = time();

    if ($currentTime - $lastRequest < $rateLimitWindow) {
        if ($requestCount >= $rateLimit) {
            http_response_code(429); // Too Many Requests
            echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
            exit;
        }
        // Increment request count
        $stmt = $db->prepare("UPDATE rate_limits SET request_count = request_count + 1, last_request = NOW() WHERE ip_address = ? AND endpoint = ?");
        $stmt->bind_param('ss', $clientIp, $endpoint);
        $stmt->execute();
        $stmt->close();
    } else {
        // Reset count if the time window has expired
        $stmt = $db->prepare("UPDATE rate_limits SET request_count = 1, last_request = NOW() WHERE ip_address = ? AND endpoint = ?");
        $stmt->bind_param('ss', $clientIp, $endpoint);
        $stmt->execute();
        $stmt->close();
    }
} else {
    // Insert new rate limit entry
    $stmt = $db->prepare("INSERT INTO rate_limits (ip_address, endpoint, request_count, last_request) VALUES (?, ?, 1, NOW())");
    $stmt->bind_param('ss', $clientIp, $endpoint);
    $stmt->execute();
    $stmt->close();
}
?>