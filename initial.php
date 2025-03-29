<?php
session_start();
require_once 'phpconfig.php';

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    echo "<div class='game'><h2>DB ERROR</h2><div class='review'>CHECK SERVER!</div></div>";
} else {
    $result = $db->query('SELECT r.*, u.username FROM reviews r LEFT JOIN users u ON r.user_id = u.id');
    if ($result->num_rows == 0) {
        echo "<div class='game'><h2>NO GAMES YET</h2><div class='review'>ADD ONE BELOW!</div></div>";
    } else {
        while ($r = $result->fetch_assoc()) {
            echo "<div class='game'>";
            echo "<h2>" . htmlspecialchars($r['gameName']) . "</h2>";
            $verifiedClass = $r['verified'] ? 'verified' : '';
            $username = $r['user_id'] ? htmlspecialchars($r['username']) : 'Anonymous';
            echo "<div class='review $verifiedClass'>[$username] " . htmlspecialchars($r['reviewText']) . " (VOTES: " . $r['votes'] . ")" . ($r['verified'] ? ' [VERIFIED]' : '') . "</div>";
            echo "<button onclick='upvoteReview(" . $r['id'] . ")'>UPVOTE</button>";
            echo "<button onclick='downvoteReview(" . $r['id'] . ")'>DOWNVOTE</button>";
            if (!$r['verified']) {
                echo "<button onclick='verifyReview(" . $r['id'] . ")'>VERIFY</button>";
            }
            echo "</div>";
        }
    }
    $db->close();
}
?>