<?php
include 'db_connect.php';

echo "<h1>Session Debug Information</h1>";
echo "<p>Session ID: " . session_id() . "</p>";

echo "<h2>Session Variables:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Cookies:</h2>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

if (isset($_SESSION['user_id'])) {
    echo "<h2>User Information:</h2>";
    $user_id = (int)$_SESSION['user_id'];
    $stmt = $db->prepare('SELECT id, username, is_admin, is_moderator FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "<pre>";
        print_r($user);
        echo "</pre>";
    } else {
        echo "<p>User not found in database.</p>";
    }
}

echo "<h2>PHP Session Settings:</h2>";
echo "<pre>";
echo "session.save_path: " . ini_get('session.save_path') . "\n";
echo "session.gc_maxlifetime: " . ini_get('session.gc_maxlifetime') . "\n";
echo "session.cookie_lifetime: " . ini_get('session.cookie_lifetime') . "\n";
echo "session.cookie_secure: " . ini_get('session.cookie_secure') . "\n";
echo "session.cookie_httponly: " . ini_get('session.cookie_httponly') . "\n";
echo "session.cookie_samesite: " . ini_get('session.cookie_samesite') . "\n";
echo "</pre>";

echo "<p><a href='index.php'>Back to Home</a></p>";
?>