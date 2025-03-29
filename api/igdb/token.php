<?php
function getIgdbToken($client_id, $client_secret, $token_file, $force_refresh = false) {
    if (!$force_refresh && file_exists($token_file)) {
        $token_data = json_decode(file_get_contents($token_file), true);
        if ($token_data && isset($token_data['access_token']) && isset($token_data['expires_at']) && $token_data['expires_at'] > time()) {
            error_log("Using existing IGDB token: " . $token_data['access_token']);
            return $token_data['access_token'];
        } else {
            error_log("Existing token expired or invalid: " . json_encode($token_data));
        }
    } else {
        error_log("Token file does not exist or force refresh requested: $token_file");
    }

    error_log("Fetching new IGDB token...");
    $ch = curl_init('https://id.twitch.tv/oauth2/token');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "client_id=$client_id&client_secret=$client_secret&grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        error_log("cURL Error (Token Fetch): " . $error);
        curl_close($ch);
        return null;
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($response, true);
    if ($http_code !== 200 || !isset($data['access_token'])) {
        error_log("Token Fetch Failed: HTTP $http_code, Response: $response");
        return null;
    }
    $token_data = [
        'access_token' => $data['access_token'],
        'expires_at' => time() + $data['expires_in'] - 300
    ];
    file_put_contents($token_file, json_encode($token_data));
    error_log("New IGDB token fetched: " . $token_data['access_token']);
    return $token_data['access_token'];
}
?>