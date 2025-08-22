<?php
/**
 * Rate Limiter Class
 * 
 * Provides IP-based rate limiting functionality using database storage
 * with configurable limits and time windows.
 */

class RateLimiter
{
    private $storage;
    private $rateLimit;
    private $timeWindow;
    
    public function __construct(Storage $storage = null)
    {
        $this->storage = $storage ?: new Storage();
        $this->rateLimit = Config::get('RATE_LIMIT_PER_HOUR', 30);
        $this->timeWindow = 3600; // 1 hour in seconds
    }
    
    /**
     * Check if IP address is within rate limit
     */
    public function checkLimit($ipAddress)
    {
        if (!Config::feature('ENABLE_RATE_LIMITING', true)) {
            return [
                'allowed' => true,
                'remaining' => $this->rateLimit,
                'reset_at' => time() + $this->timeWindow,
                'limit' => $this->rateLimit
            ];
        }
        
        try {
            // Get current request count for this IP
            $currentCount = $this->storage->getRequestCount($ipAddress, 1);
            
            $allowed = $currentCount < $this->rateLimit;
            $remaining = max(0, $this->rateLimit - $currentCount);
            
            // Calculate reset time (next hour boundary)
            $resetAt = $this->getNextResetTime();
            
            $result = [
                'allowed' => $allowed,
                'remaining' => $remaining,
                'reset_at' => $resetAt,
                'limit' => $this->rateLimit,
                'current_count' => $currentCount
            ];
            
            if (!$allowed) {
                logMessage('WARN', 'Rate limit exceeded', [
                    'ip_address' => $ipAddress,
                    'current_count' => $currentCount,
                    'limit' => $this->rateLimit,
                    'user_agent' => getUserAgent()
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Rate limit check failed', [
                'ip_address' => $ipAddress,
                'error' => $e->getMessage()
            ]);
            
            // On error, allow the request but log it
            return [
                'allowed' => true,
                'remaining' => $this->rateLimit,
                'reset_at' => time() + $this->timeWindow,
                'limit' => $this->rateLimit,
                'error' => 'Rate limit check failed'
            ];
        }
    }
    
    /**
     * Record a request for rate limiting
     */
    public function recordRequest($ipAddress, $url, $userAgent = null)
    {
        if (!Config::feature('ENABLE_RATE_LIMITING', true)) {
            return true;
        }
        
        try {
            $success = $this->storage->recordRequest($ipAddress, $url, $userAgent);
            
            if ($success) {
                logMessage('DEBUG', 'Request recorded for rate limiting', [
                    'ip_address' => $ipAddress,
                    'url' => $url
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Failed to record request for rate limiting', [
                'ip_address' => $ipAddress,
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            
            // Don't fail the request if we can't record it
            return false;
        }
    }
    
    /**
     * Check and record request in one operation
     */
    public function checkAndRecord($ipAddress, $url, $userAgent = null)
    {
        // First check if the request is allowed
        $limitCheck = $this->checkLimit($ipAddress);
        
        if (!$limitCheck['allowed']) {
            return $limitCheck;
        }
        
        // Record the request
        $recorded = $this->recordRequest($ipAddress, $url, $userAgent);
        
        if ($recorded) {
            // Update remaining count
            $limitCheck['remaining'] = max(0, $limitCheck['remaining'] - 1);
            $limitCheck['current_count'] = ($limitCheck['current_count'] ?? 0) + 1;
        }
        
        return $limitCheck;
    }
    
    /**
     * Get rate limit information for IP without recording
     */
    public function getInfo($ipAddress)
    {
        return $this->checkLimit($ipAddress);
    }
    
    /**
     * Reset rate limit for specific IP (admin function)
     */
    public function resetLimit($ipAddress)
    {
        try {
            $pdo = $this->storage->getPdo();
            $stmt = $pdo->prepare('DELETE FROM requests WHERE ip_address = ?');
            $success = $stmt->execute([$ipAddress]);
            
            if ($success) {
                logMessage('INFO', 'Rate limit reset for IP', [
                    'ip_address' => $ipAddress,
                    'reset_by' => 'admin' // You might want to track who did this
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Failed to reset rate limit', [
                'ip_address' => $ipAddress,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get statistics about rate limiting
     */
    public function getStats()
    {
        try {
            $stats = [
                'enabled' => Config::feature('ENABLE_RATE_LIMITING', true),
                'limit_per_hour' => $this->rateLimit,
                'time_window_seconds' => $this->timeWindow
            ];
            
            if (!$stats['enabled']) {
                return $stats;
            }
            
            $pdo = $this->storage->getPdo();
            
            // Total requests in the last hour
            if ($this->storage->getDriver() === 'sqlite') {
                $stmt = $pdo->query("SELECT COUNT(*) FROM requests WHERE created_at > datetime('now', '-1 hour')");
            } else {
                $stmt = $pdo->query("SELECT COUNT(*) FROM requests WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            }
            $stats['requests_last_hour'] = (int) $stmt->fetchColumn();
            
            // Unique IPs in the last hour
            if ($this->storage->getDriver() === 'sqlite') {
                $stmt = $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM requests WHERE created_at > datetime('now', '-1 hour')");
            } else {
                $stmt = $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM requests WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            }
            $stats['unique_ips_last_hour'] = (int) $stmt->fetchColumn();
            
            // Top requesting IPs in the last hour
            if ($this->storage->getDriver() === 'sqlite') {
                $stmt = $pdo->query("
                    SELECT ip_address, COUNT(*) as request_count 
                    FROM requests 
                    WHERE created_at > datetime('now', '-1 hour')
                    GROUP BY ip_address 
                    ORDER BY request_count DESC 
                    LIMIT 10
                ");
            } else {
                $stmt = $pdo->query("
                    SELECT ip_address, COUNT(*) as request_count 
                    FROM requests 
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    GROUP BY ip_address 
                    ORDER BY request_count DESC 
                    LIMIT 10
                ");
            }
            $stats['top_requesting_ips'] = $stmt->fetchAll();
            
            // Rate limited IPs (those that exceeded the limit)
            $rateLimitedIps = [];
            foreach ($stats['top_requesting_ips'] as $ipData) {
                if ($ipData['request_count'] >= $this->rateLimit) {
                    $rateLimitedIps[] = $ipData;
                }
            }
            $stats['rate_limited_ips'] = $rateLimitedIps;
            $stats['rate_limited_count'] = count($rateLimitedIps);
            
            return $stats;
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Failed to get rate limiter stats', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'enabled' => Config::feature('ENABLE_RATE_LIMITING', true),
                'limit_per_hour' => $this->rateLimit,
                'time_window_seconds' => $this->timeWindow,
                'error' => 'Failed to retrieve statistics'
            ];
        }
    }
    
    /**
     * Clean up old request records
     */
    public function cleanup()
    {
        try {
            $pdo = $this->storage->getPdo();
            
            // Delete records older than 24 hours
            if ($this->storage->getDriver() === 'sqlite') {
                $stmt = $pdo->prepare("DELETE FROM requests WHERE created_at < datetime('now', '-24 hours')");
            } else {
                $stmt = $pdo->prepare("DELETE FROM requests WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            }
            
            $stmt->execute();
            $deletedCount = $stmt->rowCount();
            
            if ($deletedCount > 0) {
                logMessage('INFO', 'Rate limiter cleanup completed', [
                    'deleted_records' => $deletedCount
                ]);
            }
            
            return $deletedCount;
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Rate limiter cleanup failed', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Get next reset time (next hour boundary)
     */
    private function getNextResetTime()
    {
        $currentTime = time();
        $currentHour = date('H', $currentTime);
        $nextHour = ($currentHour + 1) % 24;
        
        // Get the start of next hour
        $nextReset = mktime($nextHour, 0, 0, date('n'), date('j'), date('Y'));
        
        // If next hour is 0 (midnight), it's tomorrow
        if ($nextHour === 0) {
            $nextReset += 86400; // Add 24 hours
        }
        
        return $nextReset;
    }
    
    /**
     * Check if IP is whitelisted (admin IPs, etc.)
     */
    public function isWhitelisted($ipAddress)
    {
        $whitelist = Config::get('RATE_LIMIT_WHITELIST', []);
        
        if (empty($whitelist)) {
            return false;
        }
        
        foreach ($whitelist as $whitelistedIp) {
            if (Helpers::ipInRange($ipAddress, $whitelistedIp)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get time until rate limit reset for IP
     */
    public function getTimeUntilReset($ipAddress)
    {
        $nextReset = $this->getNextResetTime();
        $currentTime = time();
        
        return max(0, $nextReset - $currentTime);
    }
    
    /**
     * Format rate limit error response
     */
    public function formatErrorResponse($ipAddress)
    {
        $info = $this->getInfo($ipAddress);
        $timeUntilReset = $this->getTimeUntilReset($ipAddress);
        
        $hours = floor($timeUntilReset / 3600);
        $minutes = floor(($timeUntilReset % 3600) / 60);
        $seconds = $timeUntilReset % 60;
        
        $resetTimeFormatted = '';
        if ($hours > 0) {
            $resetTimeFormatted = "{$hours}h {$minutes}m";
        } elseif ($minutes > 0) {
            $resetTimeFormatted = "{$minutes}m {$seconds}s";
        } else {
            $resetTimeFormatted = "{$seconds}s";
        }
        
        return [
            'error' => true,
            'message' => 'Rate limit exceeded. Too many requests from your IP address.',
            'code' => 429,
            'rate_limit' => [
                'limit' => $info['limit'],
                'remaining' => $info['remaining'],
                'reset_at' => $info['reset_at'],
                'reset_at_formatted' => date('Y-m-d H:i:s', $info['reset_at']),
                'time_until_reset_seconds' => $timeUntilReset,
                'time_until_reset_formatted' => $resetTimeFormatted
            ]
        ];
    }
}