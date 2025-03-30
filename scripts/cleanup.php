<?php
/**
 * Manual token cleanup script
 * Run via cron job or manually when needed
 */

require_once __DIR__ . '/../db_connect.php';

// Delete expired JWT tokens
$stmt = $db->prepare('DELETE FROM jwt_tokens WHERE expires_at < NOW() - INTERVAL 1 DAY');
$stmt->execute();
$jwtTokensDeleted = $stmt->affected_rows;

// Delete old revoked tokens
$stmt = $db->prepare('DELETE FROM revoked_tokens WHERE revoked_at < NOW() - INTERVAL 30 DAY');
$stmt->execute();
$revokedTokensDeleted = $stmt->affected_rows;

// Delete expired refresh tokens
$stmt = $db->prepare('DELETE FROM refresh_tokens WHERE expires_at < NOW()');
$stmt->execute();
$refreshTokensDeleted = $stmt->affected_rows;

// Delete expired anonymous users
$stmt = $db->prepare('DELETE FROM anonymous_users WHERE expires_at < NOW()');
$stmt->execute();
$anonymousUsersDeleted = $stmt->affected_rows;

// Delete orphaned votes from non-existent anonymous tokens
$stmt = $db->prepare('
    DELETE gv FROM game_votes gv 
    LEFT JOIN anonymous_users au ON gv.anonymous_token = au.token
    WHERE gv.user_id IS NULL AND (au.token IS NULL OR au.expires_at < NOW())
');
$stmt->execute();
$orphanedVotesDeleted = $stmt->affected_rows;

// Output results
echo "Cleanup completed:\n";
echo "- JWT tokens deleted: $jwtTokensDeleted\n";
echo "- Revoked tokens deleted: $revokedTokensDeleted\n";
echo "- Refresh tokens deleted: $refreshTokensDeleted\n";
echo "- Anonymous users deleted: $anonymousUsersDeleted\n";
echo "- Orphaned votes deleted: $orphanedVotesDeleted\n";