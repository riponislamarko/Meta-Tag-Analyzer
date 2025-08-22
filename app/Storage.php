<?php
/**
 * Storage Class
 * 
 * Handles database operations for both SQLite and MySQL,
 * providing a unified interface for data storage.
 */

class Storage
{
    private $pdo;
    private $driver;
    
    public function __construct()
    {
        $this->driver = Config::get('DB_DRIVER', 'sqlite');
        $this->connect();
        $this->initializeDatabase();
    }
    
    /**
     * Establish database connection
     */
    private function connect()
    {
        try {
            if ($this->driver === 'sqlite') {
                $this->connectSqlite();
            } elseif ($this->driver === 'mysql') {
                $this->connectMysql();
            } else {
                throw new Exception("Unsupported database driver: {$this->driver}");
            }
            
            // Set PDO attributes
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            if ($this->driver === 'sqlite') {
                // Enable foreign key constraints for SQLite
                $this->pdo->exec('PRAGMA foreign_keys = ON');
                $this->pdo->exec('PRAGMA journal_mode = WAL');
            }
            
        } catch (PDOException $e) {
            logMessage('ERROR', 'Database connection failed', [
                'driver' => $this->driver,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Connect to SQLite database
     */
    private function connectSqlite()
    {
        $dbPath = Config::get('SQLITE_PATH');
        
        // Ensure directory exists
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        $dsn = "sqlite:{$dbPath}";
        $this->pdo = new PDO($dsn);
    }
    
    /**
     * Connect to MySQL database
     */
    private function connectMysql()
    {
        $host = Config::get('MYSQL.HOST');
        $port = Config::get('MYSQL.PORT', 3306);
        $database = Config::get('MYSQL.DB');
        $username = Config::get('MYSQL.USER');
        $password = Config::get('MYSQL.PASS');
        $charset = Config::get('MYSQL.CHARSET', 'utf8mb4');
        
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        
        $options = [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 5,
        ];
        
        $this->pdo = new PDO($dsn, $username, $password, $options);
    }
    
    /**
     * Initialize database schema
     */
    private function initializeDatabase()
    {
        try {
            $schemaFile = APP_ROOT . "/database/schema.{$this->driver}.sql";
            
            if (!file_exists($schemaFile)) {
                throw new Exception("Schema file not found: {$schemaFile}");
            }
            
            $schema = file_get_contents($schemaFile);
            
            // For MySQL, we need to execute statements separately
            if ($this->driver === 'mysql') {
                $statements = explode(';', $schema);
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement) && !preg_match('/^\s*(--|\#)/', $statement)) {
                        $this->pdo->exec($statement);
                    }
                }
            } else {
                // SQLite can handle multiple statements
                $this->pdo->exec($schema);
            }
            
        } catch (PDOException $e) {
            logMessage('ERROR', 'Database initialization failed', [
                'driver' => $this->driver,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Database initialization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Record a request for rate limiting
     */
    public function recordRequest($ipAddress, $url, $userAgent = null)
    {
        $sql = "INSERT INTO requests (ip_address, url, user_agent) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$ipAddress, $url, $userAgent]);
    }
    
    /**
     * Get request count for IP address within time period
     */
    public function getRequestCount($ipAddress, $hours = 1)
    {
        if ($this->driver === 'sqlite') {
            $sql = "SELECT COUNT(*) FROM requests 
                    WHERE ip_address = ? 
                    AND created_at > datetime('now', '-{$hours} hours')";
        } else {
            $sql = "SELECT COUNT(*) FROM requests 
                    WHERE ip_address = ? 
                    AND created_at > DATE_SUB(NOW(), INTERVAL {$hours} HOUR)";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ipAddress]);
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Store cache entry
     */
    public function storeCache($cacheKey, $url, $data, $rawHtml = null, $finalUrl = null, $httpStatus = null, $contentType = null, $contentLength = null)
    {
        $ttl = Config::get('CACHE_TTL', 21600);
        
        // Calculate expires_at based on database type
        if ($this->driver === 'sqlite') {
            $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        } else {
            $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        }
        
        $sql = "INSERT OR REPLACE INTO cache 
                (cache_key, url, data_json, raw_html, final_url, http_status, content_type, content_length, ttl_seconds, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($this->driver === 'mysql') {
            $sql = str_replace('INSERT OR REPLACE', 'REPLACE', $sql);
        }
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $cacheKey,
            $url,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $rawHtml,
            $finalUrl,
            $httpStatus,
            $contentType,
            $contentLength,
            $ttl,
            $expiresAt
        ]);
    }
    
    /**
     * Retrieve cache entry
     */
    public function getCache($cacheKey)
    {
        $sql = "SELECT * FROM cache WHERE cache_key = ? AND expires_at > " . 
               ($this->driver === 'sqlite' ? "datetime('now')" : "NOW()");
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cacheKey]);
        $result = $stmt->fetch();
        
        if ($result) {
            $result['data_json'] = json_decode($result['data_json'], true);
        }
        
        return $result;
    }
    
    /**
     * Check if cache entry exists and is valid
     */
    public function hasValidCache($cacheKey)
    {
        $sql = "SELECT COUNT(*) FROM cache WHERE cache_key = ? AND expires_at > " . 
               ($this->driver === 'sqlite' ? "datetime('now')" : "NOW()");
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cacheKey]);
        return (bool) $stmt->fetchColumn();
    }
    
    /**
     * Delete cache entry
     */
    public function deleteCache($cacheKey)
    {
        $sql = "DELETE FROM cache WHERE cache_key = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$cacheKey]);
    }
    
    /**
     * Clean up expired cache entries
     */
    public function cleanupExpiredCache()
    {
        $sql = "DELETE FROM cache WHERE expires_at < " . 
               ($this->driver === 'sqlite' ? "datetime('now')" : "NOW()");
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute();
        
        $deletedCount = $stmt->rowCount();
        
        if ($deletedCount > 0) {
            logMessage('INFO', 'Cleaned up expired cache entries', [
                'deleted_count' => $deletedCount
            ]);
        }
        
        return $result;
    }
    
    /**
     * Log message to database
     */
    public function log($level, $message, $context = [], $ipAddress = null, $userAgent = null, $url = null)
    {
        $sql = "INSERT INTO logs (level, message, context_json, ip_address, user_agent, url) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            strtoupper($level),
            $message,
            !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $ipAddress,
            $userAgent,
            $url
        ]);
    }
    
    /**
     * Get recent log entries
     */
    public function getLogs($level = null, $limit = 100, $offset = 0)
    {
        $sql = "SELECT * FROM logs";
        $params = [];
        
        if ($level) {
            $sql .= " WHERE level = ?";
            $params[] = strtoupper($level);
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Store analysis history
     */
    public function storeAnalysisHistory($ipAddress, $url, $finalUrl, $httpStatus, $title, $metaDescription, $ogTitle, $ogDescription, $cacheHit, $analysisTimeMs)
    {
        $sql = "INSERT INTO analysis_history 
                (ip_address, url, final_url, http_status, title, meta_description, og_title, og_description, cache_hit, analysis_time_ms) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $ipAddress,
            $url,
            $finalUrl,
            $httpStatus,
            Helpers::truncateText($title, 500),
            Helpers::truncateText($metaDescription, 1000),
            Helpers::truncateText($ogTitle, 500),
            Helpers::truncateText($ogDescription, 1000),
            $cacheHit ? 1 : 0,
            $analysisTimeMs
        ]);
    }
    
    /**
     * Get analysis history for IP
     */
    public function getAnalysisHistory($ipAddress, $limit = 50)
    {
        $sql = "SELECT * FROM analysis_history 
                WHERE ip_address = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ipAddress, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get database statistics
     */
    public function getStats()
    {
        $stats = [];
        
        // Request count
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM requests");
        $stats['total_requests'] = (int) $stmt->fetchColumn();
        
        // Cache count
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM cache");
        $stats['total_cache_entries'] = (int) $stmt->fetchColumn();
        
        // Valid cache count
        $sql = "SELECT COUNT(*) FROM cache WHERE expires_at > " . 
               ($this->driver === 'sqlite' ? "datetime('now')" : "NOW()");
        $stmt = $this->pdo->query($sql);
        $stats['valid_cache_entries'] = (int) $stmt->fetchColumn();
        
        // Log count
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM logs");
        $stats['total_logs'] = (int) $stmt->fetchColumn();
        
        return $stats;
    }
    
    /**
     * Clean up old data
     */
    public function cleanup()
    {
        $this->cleanupExpiredCache();
        
        // Clean up old request records (older than 24 hours)
        $sql = "DELETE FROM requests WHERE created_at < " . 
               ($this->driver === 'sqlite' ? "datetime('now', '-24 hours')" : "DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $this->pdo->exec($sql);
        
        // Clean up old logs (older than 30 days)
        $sql = "DELETE FROM logs WHERE created_at < " . 
               ($this->driver === 'sqlite' ? "datetime('now', '-30 days')" : "DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $this->pdo->exec($sql);
        
        logMessage('INFO', 'Database cleanup completed');
    }
    
    /**
     * Get PDO instance for custom queries
     */
    public function getPdo()
    {
        return $this->pdo;
    }
    
    /**
     * Get database driver
     */
    public function getDriver()
    {
        return $this->driver;
    }
}