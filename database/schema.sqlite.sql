-- Meta Tag Analyzer - SQLite Database Schema
-- This file contains the database schema for SQLite

-- Enable foreign key constraints
PRAGMA foreign_keys = ON;

-- Table to track requests for rate limiting
CREATE TABLE IF NOT EXISTS requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address TEXT NOT NULL,
    url TEXT NOT NULL,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_requests_ip_created (ip_address, created_at),
    INDEX idx_requests_created (created_at)
);

-- Table to store cached analysis results
CREATE TABLE IF NOT EXISTS cache (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cache_key TEXT UNIQUE NOT NULL,
    url TEXT NOT NULL,
    data_json TEXT NOT NULL,
    raw_html TEXT,
    final_url TEXT,
    http_status INTEGER,
    content_type TEXT,
    content_length INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ttl_seconds INTEGER NOT NULL DEFAULT 21600,
    expires_at DATETIME NOT NULL,
    INDEX idx_cache_key (cache_key),
    INDEX idx_cache_expires (expires_at),
    INDEX idx_cache_url (url),
    INDEX idx_cache_created (created_at)
);

-- Table for application logs
CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    level TEXT NOT NULL CHECK (level IN ('DEBUG', 'INFO', 'WARN', 'ERROR')),
    message TEXT NOT NULL,
    context_json TEXT,
    ip_address TEXT,
    user_agent TEXT,
    url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_level (level),
    INDEX idx_logs_created (created_at),
    INDEX idx_logs_ip (ip_address)
);

-- Table to store analysis history (optional, for "nice-to-have" history feature)
CREATE TABLE IF NOT EXISTS analysis_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address TEXT NOT NULL,
    url TEXT NOT NULL,
    final_url TEXT,
    http_status INTEGER,
    title TEXT,
    meta_description TEXT,
    og_title TEXT,
    og_description TEXT,
    cache_hit BOOLEAN DEFAULT 0,
    analysis_time_ms INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_history_ip_created (ip_address, created_at),
    INDEX idx_history_url (url),
    INDEX idx_history_created (created_at)
);

-- Create trigger to automatically set expires_at when inserting into cache
CREATE TRIGGER IF NOT EXISTS set_cache_expires_at
AFTER INSERT ON cache
FOR EACH ROW
BEGIN
    UPDATE cache 
    SET expires_at = datetime(NEW.created_at, '+' || NEW.ttl_seconds || ' seconds')
    WHERE id = NEW.id;
END;

-- Create trigger to clean up old cache entries
CREATE TRIGGER IF NOT EXISTS cleanup_expired_cache
AFTER INSERT ON cache
FOR EACH ROW
BEGIN
    DELETE FROM cache WHERE expires_at < datetime('now');
END;

-- Create trigger to clean up old request records (keep only last 24 hours for rate limiting)
CREATE TRIGGER IF NOT EXISTS cleanup_old_requests
AFTER INSERT ON requests
FOR EACH ROW
BEGIN
    DELETE FROM requests WHERE created_at < datetime('now', '-24 hours');
END;

-- Create trigger to clean up old logs (keep only last 30 days)
CREATE TRIGGER IF NOT EXISTS cleanup_old_logs
AFTER INSERT ON logs
FOR EACH ROW
BEGIN
    DELETE FROM logs WHERE created_at < datetime('now', '-30 days');
END;