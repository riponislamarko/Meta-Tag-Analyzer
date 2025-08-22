<?php
/**
 * Cache Class
 * 
 * Provides file-based caching with TTL support and automatic cleanup.
 * Uses both database and file storage for optimal performance.
 */

class Cache
{
    private $storage;
    private $cachePath;
    private $ttl;
    private $maxSize;
    
    public function __construct(Storage $storage = null)
    {
        $this->storage = $storage ?: new Storage();
        $this->cachePath = Config::get('CACHE_PATH', STORAGE_PATH . '/cache');
        $this->ttl = Config::get('CACHE_TTL', 21600); // 6 hours default
        $this->maxSize = Config::get('CACHE_MAX_SIZE', 524288); // 512 KB default
        
        // Ensure cache directory exists
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }
    
    /**
     * Get cached analysis result
     */
    public function get($cacheKey)
    {
        if (!Config::feature('ENABLE_CACHE', true)) {
            return null;
        }
        
        // Validate cache key
        $errors = Validators::validateCacheKey($cacheKey);
        if (!empty($errors)) {
            logMessage('WARN', 'Invalid cache key', ['cache_key' => $cacheKey, 'errors' => $errors]);
            return null;
        }
        
        try {
            // Get from database first
            $cached = $this->storage->getCache($cacheKey);
            
            if (!$cached) {
                return null;
            }
            
            // Check if cache has expired
            if (strtotime($cached['expires_at']) <= time()) {
                $this->delete($cacheKey);
                return null;
            }
            
            // Get file-based content if available
            $filePath = $this->getCacheFilePath($cacheKey);
            if (file_exists($filePath)) {
                $cached['raw_html'] = file_get_contents($filePath);
            }
            
            logMessage('DEBUG', 'Cache hit', [
                'cache_key' => $cacheKey,
                'url' => $cached['url'],
                'expires_at' => $cached['expires_at']
            ]);
            
            return $cached;
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Cache retrieval failed', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Store analysis result in cache
     */
    public function set($cacheKey, $url, $analysisData, $rawHtml = null, $fetchData = [])
    {
        if (!Config::feature('ENABLE_CACHE', true)) {
            return false;
        }
        
        // Validate cache key
        $errors = Validators::validateCacheKey($cacheKey);
        if (!empty($errors)) {
            logMessage('WARN', 'Invalid cache key for storage', ['cache_key' => $cacheKey, 'errors' => $errors]);
            return false;
        }
        
        try {
            // Limit raw HTML size for file storage
            $storeRawHtml = null;
            if ($rawHtml && strlen($rawHtml) <= $this->maxSize) {
                $storeRawHtml = $rawHtml;
            }
            
            // Store in database
            $success = $this->storage->storeCache(
                $cacheKey,
                $url,
                $analysisData,
                null, // Don't store raw HTML in database
                $fetchData['final_url'] ?? $url,
                $fetchData['http_code'] ?? null,
                $fetchData['content_type'] ?? null,
                $fetchData['content_length'] ?? null
            );
            
            if (!$success) {
                logMessage('ERROR', 'Failed to store cache in database', ['cache_key' => $cacheKey]);
                return false;
            }
            
            // Store raw HTML in file if provided and within size limit
            if ($storeRawHtml) {
                $filePath = $this->getCacheFilePath($cacheKey);
                $fileSuccess = file_put_contents($filePath, $storeRawHtml, LOCK_EX);
                
                if ($fileSuccess === false) {
                    logMessage('WARN', 'Failed to store raw HTML in cache file', [
                        'cache_key' => $cacheKey,
                        'file_path' => $filePath
                    ]);
                }
            }
            
            logMessage('DEBUG', 'Cache stored', [
                'cache_key' => $cacheKey,
                'url' => $url,
                'has_raw_html' => !empty($storeRawHtml),
                'ttl' => $this->ttl
            ]);
            
            return true;
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Cache storage failed', [
                'cache_key' => $cacheKey,
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Check if cache entry exists and is valid
     */
    public function has($cacheKey)
    {
        if (!Config::feature('ENABLE_CACHE', true)) {
            return false;
        }
        
        // Validate cache key
        $errors = Validators::validateCacheKey($cacheKey);
        if (!empty($errors)) {
            return false;
        }
        
        try {
            return $this->storage->hasValidCache($cacheKey);
        } catch (Exception $e) {
            logMessage('ERROR', 'Cache check failed', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Delete cache entry
     */
    public function delete($cacheKey)
    {
        if (!Config::feature('ENABLE_CACHE', true)) {
            return false;
        }
        
        try {
            // Delete from database
            $dbSuccess = $this->storage->deleteCache($cacheKey);
            
            // Delete file if exists
            $filePath = $this->getCacheFilePath($cacheKey);
            $fileSuccess = true;
            
            if (file_exists($filePath)) {
                $fileSuccess = unlink($filePath);
                
                if (!$fileSuccess) {
                    logMessage('WARN', 'Failed to delete cache file', [
                        'cache_key' => $cacheKey,
                        'file_path' => $filePath
                    ]);
                }
            }
            
            return $dbSuccess && $fileSuccess;
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Cache deletion failed', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Clean up expired cache entries
     */
    public function cleanup()
    {
        if (!Config::feature('ENABLE_CACHE', true)) {
            return;
        }
        
        try {
            $startTime = microtime(true);
            
            // Clean up database cache
            $this->storage->cleanupExpiredCache();
            
            // Clean up cache files
            $deletedFiles = $this->cleanupCacheFiles();
            
            $endTime = microtime(true);
            $cleanupTime = round(($endTime - $startTime) * 1000);
            
            logMessage('INFO', 'Cache cleanup completed', [
                'deleted_files' => $deletedFiles,
                'cleanup_time_ms' => $cleanupTime
            ]);
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Cache cleanup failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Clean up cache files that no longer have database entries
     */
    private function cleanupCacheFiles()
    {
        $deletedCount = 0;
        
        if (!is_dir($this->cachePath)) {
            return $deletedCount;
        }
        
        $files = glob($this->cachePath . '/*.cache');
        
        foreach ($files as $file) {
            $filename = basename($file, '.cache');
            
            // Check if this cache key exists in database
            if (!$this->storage->hasValidCache($filename)) {
                if (unlink($file)) {
                    $deletedCount++;
                } else {
                    logMessage('WARN', 'Failed to delete orphaned cache file', [
                        'file' => $file
                    ]);
                }
            }
        }
        
        return $deletedCount;
    }
    
    /**
     * Get cache file path for a cache key
     */
    private function getCacheFilePath($cacheKey)
    {
        return $this->cachePath . '/' . $cacheKey . '.cache';
    }
    
    /**
     * Generate cache key for URL
     */
    public function generateKey($url)
    {
        return Helpers::generateCacheKey($url);
    }
    
    /**
     * Get cache statistics
     */
    public function getStats()
    {
        $stats = [
            'enabled' => Config::feature('ENABLE_CACHE', true),
            'ttl_seconds' => $this->ttl,
            'max_file_size_bytes' => $this->maxSize,
            'cache_path' => $this->cachePath
        ];
        
        try {
            // Get database stats
            $dbStats = $this->storage->getStats();
            $stats['total_entries'] = $dbStats['total_cache_entries'] ?? 0;
            $stats['valid_entries'] = $dbStats['valid_cache_entries'] ?? 0;
            
            // Get file stats
            if (is_dir($this->cachePath)) {
                $files = glob($this->cachePath . '/*.cache');
                $stats['cache_files'] = count($files);
                
                $totalSize = 0;
                foreach ($files as $file) {
                    $totalSize += filesize($file);
                }
                $stats['total_file_size_bytes'] = $totalSize;
                $stats['total_file_size_formatted'] = Helpers::formatBytes($totalSize);
            } else {
                $stats['cache_files'] = 0;
                $stats['total_file_size_bytes'] = 0;
                $stats['total_file_size_formatted'] = '0 B';
            }
            
            // Calculate hit ratio (if we have request history)
            $stats['hit_ratio'] = $this->calculateHitRatio();
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Failed to get cache stats', [
                'error' => $e->getMessage()
            ]);
            
            $stats['error'] = 'Failed to retrieve statistics';
        }
        
        return $stats;
    }
    
    /**
     * Calculate cache hit ratio
     */
    private function calculateHitRatio()
    {
        try {
            // This is a simplified calculation
            // In a real implementation, you might want to track hits/misses more precisely
            $totalEntries = $this->storage->getStats()['total_cache_entries'] ?? 0;
            $validEntries = $this->storage->getStats()['valid_cache_entries'] ?? 0;
            
            if ($totalEntries === 0) {
                return 0;
            }
            
            return round(($validEntries / $totalEntries) * 100, 2);
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Invalidate cache for specific URL
     */
    public function invalidateUrl($url)
    {
        try {
            $cacheKey = $this->generateKey($url);
            return $this->delete($cacheKey);
        } catch (Exception $e) {
            logMessage('ERROR', 'Cache invalidation failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Clear all cache entries
     */
    public function clear()
    {
        try {
            $startTime = microtime(true);
            
            // Clear database cache
            $pdo = $this->storage->getPdo();
            $pdo->exec('DELETE FROM cache');
            
            // Clear cache files
            $deletedFiles = 0;
            if (is_dir($this->cachePath)) {
                $files = glob($this->cachePath . '/*.cache');
                foreach ($files as $file) {
                    if (unlink($file)) {
                        $deletedFiles++;
                    }
                }
            }
            
            $endTime = microtime(true);
            $clearTime = round(($endTime - $startTime) * 1000);
            
            logMessage('INFO', 'Cache cleared', [
                'deleted_files' => $deletedFiles,
                'clear_time_ms' => $clearTime
            ]);
            
            return true;
            
        } catch (Exception $e) {
            logMessage('ERROR', 'Cache clear failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}