<?php
/**
 * Database schema setup for IGDB integration
 * This file should be run during installation only
 */

/**
 * Ensure all required IGDB tables exist
 * 
 * @param object $db Database connection
 */
function ensureIgdbTables($db) {
    // Create games table if it doesn't exist
    ensureGamesTable($db);
    
    // Create game_votes table if it doesn't exist  
    $db->query("
        CREATE TABLE IF NOT EXISTS game_votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            game_id INT NOT NULL,
            user_id INT NULL,
            anonymous_token VARCHAR(64) NULL,
            vote TINYINT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY user_game (game_id, user_id),
            UNIQUE KEY anon_game (game_id, anonymous_token)
        )
    ");
}

/**
 * Ensure games table exists
 * 
 * @param object $db Database connection
 */
function ensureGamesTable($db) {
    $db->query("
        CREATE TABLE IF NOT EXISTS games (
            id INT NOT NULL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            first_release_date INT NULL,
            cover_url VARCHAR(255) NULL,
            details TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            likes INT DEFAULT 0,
            dislikes INT DEFAULT 0,
            approval_percent FLOAT DEFAULT 0,
            avg_rating FLOAT DEFAULT NULL,
            review_count INT DEFAULT 0
        )
    ");
}

function ensureAnonymousUser($db) {
    // Check if the anonymous_users table exists
    $db->query("
        CREATE TABLE IF NOT EXISTS anonymous_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(64) NOT NULL UNIQUE,
            fingerprint VARCHAR(64) NULL,
            ip_address VARCHAR(45) NULL,
            first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            INDEX (token),
            INDEX (expires_at)
        )
    ");
?>