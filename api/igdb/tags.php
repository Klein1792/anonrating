<?php
require_once __DIR__ . '/token.php';

function fetchTagNames($client_id, $token, $tag_ids) {
    if (empty($tag_ids)) {
        error_log("No tag IDs provided for fetchTagNames");
        return [];
    }
    $tag_ids = array_map('intval', $tag_ids);
    $ch = curl_init('https://api.igdb.com/v4/tags');
    $query = 'fields name; where id = (' . implode(',', $tag_ids) . ');';
    error_log("Fetching tag names with query: $query");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = [
        "Client-ID: $client_id",
        "Authorization: Bearer $token",
        "Accept: application/json"
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        error_log("cURL Error (fetchTagNames): " . $error);
        curl_close($ch);
        return [];
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code !== 200) {
        error_log("fetchTagNames HTTP Error: $http_code, Response: $response");
        return [];
    }
    $tags = json_decode($response, true);
    if (!is_array($tags)) {
        error_log("Invalid or empty response from IGDB for tags: " . json_encode($tags));
        return [];
    }
    $tag_names = [];
    foreach ($tags as $tag) {
        if (isset($tag['name'])) {
            $tag_names[] = $tag['name'];
        }
    }
    error_log("Fetched tag names: " . json_encode($tag_names));
    return $tag_names;
}
?>