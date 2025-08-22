-- Meta Tag Analyzer - MySQL Database Schema
-- This file contains the database schema for MySQL

SET sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';
SET default_storage_engine = InnoDB;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Table to track requests for rate limiting
CREATE TABLE IF NOT EXISTS `requests` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
    `url` TEXT NOT NULL,
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_requests_ip_created` (`ip_address`, `created_at`),
    KEY `idx_requests_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Request tracking for rate limiting';

-- Table to store cached analysis results
CREATE TABLE IF NOT EXISTS `cache` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cache_key` VARCHAR(40) UNIQUE NOT NULL COMMENT 'SHA1 hash of normalized URL',
    `url` TEXT NOT NULL,
    `data_json` LONGTEXT NOT NULL COMMENT 'JSON-encoded analysis results',
    `raw_html` MEDIUMTEXT COMMENT 'Raw HTML content (optional)',
    `final_url` TEXT COMMENT 'Final URL after redirects',
    `http_status` SMALLINT UNSIGNED,
    `content_type` VARCHAR(100),
    `content_length` INT UNSIGNED,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ttl_seconds` INT UNSIGNED NOT NULL DEFAULT 21600 COMMENT 'Time to live in seconds',
    `expires_at` TIMESTAMP NOT NULL,
    KEY `idx_cache_key` (`cache_key`),
    KEY `idx_cache_expires` (`expires_at`),
    KEY `idx_cache_url` (`url`(255)),
    KEY `idx_cache_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cached analysis results';

-- Table for application logs
CREATE TABLE IF NOT EXISTS `logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `level` ENUM('DEBUG', 'INFO', 'WARN', 'ERROR') NOT NULL,
    `message` TEXT NOT NULL,
    `context_json` JSON,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `url` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_logs_level` (`level`),
    KEY `idx_logs_created` (`created_at`),
    KEY `idx_logs_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Application logs';

-- Table to store analysis history (optional, for "nice-to-have" history feature)
CREATE TABLE IF NOT EXISTS `analysis_history` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `url` TEXT NOT NULL,
    `final_url` TEXT,
    `http_status` SMALLINT UNSIGNED,
    `title` VARCHAR(500),
    `meta_description` TEXT,
    `og_title` VARCHAR(500),
    `og_description` TEXT,
    `cache_hit` BOOLEAN DEFAULT FALSE,
    `analysis_time_ms` INT UNSIGNED COMMENT 'Analysis time in milliseconds',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_history_ip_created` (`ip_address`, `created_at`),
    KEY `idx_history_url` (`url`(255)),
    KEY `idx_history_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Analysis history for statistics';

-- Create stored procedure to clean up expired cache entries
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS CleanupExpiredCache()
BEGIN
    DELETE FROM `cache` WHERE `expires_at` < NOW();
END$$
DELIMITER ;

-- Create stored procedure to clean up old request records
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS CleanupOldRequests()
BEGIN
    DELETE FROM `requests` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 24 HOUR);
END$$
DELIMITER ;

-- Create stored procedure to clean up old logs
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS CleanupOldLogs()
BEGIN
    DELETE FROM `logs` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 30 DAY);
END$$
DELIMITER ;

-- Create event scheduler to run cleanup procedures (requires SUPER privilege)
-- Uncomment these if you have the necessary privileges and want automatic cleanup
/*
SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS cleanup_cache_event
ON SCHEDULE EVERY 1 HOUR
DO
    CALL CleanupExpiredCache();

CREATE EVENT IF NOT EXISTS cleanup_requests_event
ON SCHEDULE EVERY 6 HOUR
DO
    CALL CleanupOldRequests();

CREATE EVENT IF NOT EXISTS cleanup_logs_event
ON SCHEDULE EVERY 1 DAY
DO
    CALL CleanupOldLogs();
*/

-- Trigger to automatically set expires_at when inserting into cache
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS set_cache_expires_at
BEFORE INSERT ON `cache`
FOR EACH ROW
BEGIN
    SET NEW.expires_at = DATE_ADD(NEW.created_at, INTERVAL NEW.ttl_seconds SECOND);
END$$
DELIMITER ;