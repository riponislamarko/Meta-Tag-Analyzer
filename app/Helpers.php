<?php
/**
 * Helper Functions Class
 * 
 * Provides utility functions for URL parsing, HTML processing,
 * and various data manipulation tasks.
 */

class Helpers
{
    /**
     * Normalize URL for consistent processing
     */
    public static function normalizeUrl($url)
    {
        $url = trim($url);
        
        // Add protocol if missing
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'http://' . $url;
        }
        
        // Parse and rebuild URL to normalize it
        $parts = parse_url($url);
        if (!$parts) {
            throw new InvalidArgumentException('Invalid URL format');
        }
        
        // Validate required parts
        if (empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('URL must have scheme and host');
        }
        
        // Normalize scheme
        $parts['scheme'] = strtolower($parts['scheme']);
        if (!in_array($parts['scheme'], ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only HTTP and HTTPS schemes are allowed');
        }
        
        // Normalize host
        $parts['host'] = strtolower($parts['host']);
        
        // Validate host format
        if (!filter_var($parts['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidArgumentException('Invalid hostname');
        }
        
        // Set default port
        if (empty($parts['port'])) {
            $parts['port'] = ($parts['scheme'] === 'https') ? 443 : 80;
        }
        
        // Validate port
        $allowedPorts = Config::get('HTTP.ALLOW_PORTS', [80, 443]);
        if (!in_array($parts['port'], $allowedPorts, true)) {
            throw new InvalidArgumentException("Port {$parts['port']} is not allowed");
        }
        
        // Normalize path
        if (empty($parts['path'])) {
            $parts['path'] = '/';
        }
        
        // Rebuild URL
        $normalized = $parts['scheme'] . '://' . $parts['host'];
        
        // Add port if not default
        if (($parts['scheme'] === 'http' && $parts['port'] !== 80) ||
            ($parts['scheme'] === 'https' && $parts['port'] !== 443)) {
            $normalized .= ':' . $parts['port'];
        }
        
        $normalized .= $parts['path'];
        
        if (!empty($parts['query'])) {
            $normalized .= '?' . $parts['query'];
        }
        
        if (!empty($parts['fragment'])) {
            $normalized .= '#' . $parts['fragment'];
        }
        
        return $normalized;
    }
    
    /**
     * Extract domain from URL
     */
    public static function extractDomain($url)
    {
        $parts = parse_url($url);
        return $parts['host'] ?? '';
    }
    
    /**
     * Generate cache key for URL
     */
    public static function generateCacheKey($url)
    {
        $normalized = self::normalizeUrl($url);
        return sha1($normalized);
    }
    
    /**
     * Clean HTML content for analysis
     */
    public static function cleanHtml($html)
    {
        // Remove script and style tags completely
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        
        // Remove comments
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        
        // Normalize whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        
        return trim($html);
    }
    
    /**
     * Extract text content from HTML
     */
    public static function extractTextContent($html, $stripTags = [])
    {
        // Remove specified tags
        $defaultStripTags = Config::get('ANALYSIS.STRIP_TAGS', ['script', 'style', 'nav', 'aside', 'footer', 'header']);
        $stripTags = array_merge($defaultStripTags, $stripTags);
        
        foreach ($stripTags as $tag) {
            $html = preg_replace("/<{$tag}\b[^<]*(?:(?!<\/{$tag}>)<[^<]*)*<\/{$tag}>/mi", '', $html);
        }
        
        // Strip all remaining HTML tags
        $text = strip_tags($html);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * Count words in text
     */
    public static function countWords($text)
    {
        $text = self::extractTextContent($text);
        
        if (empty($text)) {
            return 0;
        }
        
        $minLength = Config::get('ANALYSIS.WORD_COUNT_MIN_LENGTH', 3);
        
        // Split into words
        $words = preg_split('/\s+/', $text);
        
        // Filter words by minimum length
        $words = array_filter($words, function ($word) use ($minLength) {
            return mb_strlen(trim($word), 'UTF-8') >= $minLength;
        });
        
        return count($words);
    }
    
    /**
     * Truncate text to specified length
     */
    public static function truncateText($text, $length = 255, $suffix = '...')
    {
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
    }
    
    /**
     * Convert relative URL to absolute
     */
    public static function resolveRelativeUrl($baseUrl, $relativeUrl)
    {
        // If already absolute, return as-is
        if (preg_match('/^https?:\/\//', $relativeUrl)) {
            return $relativeUrl;
        }
        
        $base = parse_url($baseUrl);
        
        // Protocol-relative URL
        if (strpos($relativeUrl, '//') === 0) {
            return $base['scheme'] . ':' . $relativeUrl;
        }
        
        // Absolute path
        if (strpos($relativeUrl, '/') === 0) {
            return $base['scheme'] . '://' . $base['host'] . 
                   (isset($base['port']) ? ':' . $base['port'] : '') . $relativeUrl;
        }
        
        // Relative path
        $basePath = isset($base['path']) ? dirname($base['path']) : '';
        if ($basePath === '.') {
            $basePath = '';
        }
        
        $absoluteUrl = $base['scheme'] . '://' . $base['host'] . 
                       (isset($base['port']) ? ':' . $base['port'] : '') . 
                       rtrim($basePath, '/') . '/' . ltrim($relativeUrl, '/');
        
        return $absoluteUrl;
    }
    
    /**
     * Detect content encoding
     */
    public static function detectEncoding($content, $contentType = '')
    {
        // Check meta charset tag
        if (preg_match('/<meta[^>]+charset=["\']?([^"\'>\s]+)/i', $content, $matches)) {
            return strtoupper(trim($matches[1]));
        }
        
        // Check Content-Type header
        if ($contentType && preg_match('/charset=([^;\s]+)/i', $contentType, $matches)) {
            return strtoupper(trim($matches[1]));
        }
        
        // Use mbstring detection
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        
        return $encoding ?: 'UTF-8';
    }
    
    /**
     * Convert content to UTF-8
     */
    public static function convertToUtf8($content, $fromEncoding = null)
    {
        if (!$fromEncoding) {
            $fromEncoding = self::detectEncoding($content);
        }
        
        if (strtoupper($fromEncoding) === 'UTF-8') {
            return $content;
        }
        
        $converted = mb_convert_encoding($content, 'UTF-8', $fromEncoding);
        
        return $converted !== false ? $converted : $content;
    }
    
    /**
     * Format file size in human-readable format
     */
    public static function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Format duration in human-readable format
     */
    public static function formatDuration($milliseconds)
    {
        if ($milliseconds < 1000) {
            return round($milliseconds) . 'ms';
        }
        
        $seconds = $milliseconds / 1000;
        if ($seconds < 60) {
            return round($seconds, 2) . 's';
        }
        
        $minutes = $seconds / 60;
        return round($minutes, 2) . 'm';
    }
    
    /**
     * Sanitize filename for safe storage
     */
    public static function sanitizeFilename($filename)
    {
        // Remove or replace dangerous characters
        $filename = preg_replace('/[^\w\-_\.]/', '_', $filename);
        
        // Remove consecutive underscores
        $filename = preg_replace('/_+/', '_', $filename);
        
        // Trim underscores from start and end
        $filename = trim($filename, '_');
        
        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'unnamed';
        }
        
        return $filename;
    }
    
    /**
     * Generate random string
     */
    public static function generateRandomString($length = 32)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }
    
    /**
     * Check if IP address is in private range
     */
    public static function isPrivateIp($ip)
    {
        $blockedRanges = Config::get('SECURITY.BLOCKED_IP_RANGES', []);
        
        foreach ($blockedRanges as $range) {
            if (self::ipInRange($ip, $range)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP address is in CIDR range
     */
    public static function ipInRange($ip, $cidr)
    {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }
        
        list($subnet, $mask) = explode('/', $cidr);
        
        // IPv6
        if (strpos($ip, ':') !== false) {
            return self::ipv6InRange($ip, $subnet, $mask);
        }
        
        // IPv4
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        
        $mask = ~((1 << (32 - $mask)) - 1);
        
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
    
    /**
     * Check if IPv6 address is in range
     */
    private static function ipv6InRange($ip, $subnet, $mask)
    {
        $ip = inet_pton($ip);
        $subnet = inet_pton($subnet);
        
        if ($ip === false || $subnet === false) {
            return false;
        }
        
        $bytesToCheck = intval($mask / 8);
        $bitsToCheck = $mask % 8;
        
        for ($i = 0; $i < $bytesToCheck; $i++) {
            if ($ip[$i] !== $subnet[$i]) {
                return false;
            }
        }
        
        if ($bitsToCheck > 0) {
            $mask = 0xFF << (8 - $bitsToCheck);
            return (ord($ip[$bytesToCheck]) & $mask) === (ord($subnet[$bytesToCheck]) & $mask);
        }
        
        return true;
    }
}