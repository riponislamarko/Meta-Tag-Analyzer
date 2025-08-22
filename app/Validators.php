<?php
/**
 * Validators Class
 * 
 * Provides input validation, URL validation, and security checks
 * to ensure safe processing of user input.
 */

class Validators
{
    /**
     * Validate URL input
     */
    public static function validateUrl($url)
    {
        $errors = [];
        
        // Basic validation
        if (empty($url)) {
            $errors[] = 'URL is required';
            return $errors;
        }
        
        $url = trim($url);
        
        // Length check
        if (strlen($url) > 2048) {
            $errors[] = 'URL is too long (maximum 2048 characters)';
        }
        
        // Normalize URL for validation
        try {
            $normalizedUrl = Helpers::normalizeUrl($url);
        } catch (Exception $e) {
            $errors[] = 'Invalid URL format: ' . $e->getMessage();
            return $errors;
        }
        
        // Parse URL
        $parts = parse_url($normalizedUrl);
        if (!$parts) {
            $errors[] = 'Invalid URL format';
            return $errors;
        }
        
        // Validate scheme
        $allowedSchemes = Config::get('SECURITY.ALLOWED_SCHEMES', ['http', 'https']);
        if (!isset($parts['scheme']) || !in_array($parts['scheme'], $allowedSchemes, true)) {
            $errors[] = 'Only HTTP and HTTPS URLs are allowed';
        }
        
        // Validate host
        if (!isset($parts['host']) || empty($parts['host'])) {
            $errors[] = 'URL must have a valid hostname';
        } else {
            // Check for valid domain format
            if (!filter_var($parts['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                $errors[] = 'Invalid hostname format';
            }
            
            // Check for localhost, IP addresses in hostname
            if (self::isLocalhost($parts['host']) || filter_var($parts['host'], FILTER_VALIDATE_IP)) {
                // Allow for development environment
                if (!Config::isDev()) {
                    $errors[] = 'Localhost and IP addresses are not allowed';
                }
            }
        }
        
        // Validate port
        if (isset($parts['port'])) {
            $allowedPorts = Config::get('HTTP.ALLOW_PORTS', [80, 443]);
            if (!in_array($parts['port'], $allowedPorts, true)) {
                $errors[] = "Port {$parts['port']} is not allowed";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate URL for SSRF protection
     */
    public static function validateUrlForSsrf($url)
    {
        $errors = [];
        
        // First do basic URL validation
        $basicErrors = self::validateUrl($url);
        if (!empty($basicErrors)) {
            return $basicErrors;
        }
        
        // Normalize URL
        try {
            $normalizedUrl = Helpers::normalizeUrl($url);
        } catch (Exception $e) {
            $errors[] = 'URL normalization failed: ' . $e->getMessage();
            return $errors;
        }
        
        $parts = parse_url($normalizedUrl);
        $host = $parts['host'];
        
        // Resolve hostname to IP address
        $ips = [];
        
        // Get IPv4 addresses
        $ipv4 = gethostbyname($host);
        if ($ipv4 !== $host) {
            $ips[] = $ipv4;
        }
        
        // Get all IP addresses (IPv4 and IPv6)
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records) {
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }
        
        // Remove duplicates
        $ips = array_unique($ips);
        
        if (empty($ips)) {
            $errors[] = 'Could not resolve hostname to IP address';
            return $errors;
        }
        
        // Check each resolved IP against blocked ranges
        foreach ($ips as $ip) {
            if (Helpers::isPrivateIp($ip)) {
                $errors[] = "Access to private IP address {$ip} is not allowed";
                
                // Log SSRF attempt
                logMessage('WARN', 'SSRF attempt blocked', [
                    'url' => $url,
                    'hostname' => $host,
                    'resolved_ip' => $ip,
                    'client_ip' => getClientIp(),
                    'user_agent' => getUserAgent()
                ]);
            }
        }
        
        return $errors;
    }
    
    /**
     * Check if hostname is localhost
     */
    private static function isLocalhost($hostname)
    {
        $localhostPatterns = [
            'localhost',
            '127.0.0.1',
            '::1',
            '0.0.0.0'
        ];
        
        return in_array(strtolower($hostname), $localhostPatterns, true);
    }
    
    /**
     * Validate export format
     */
    public static function validateExportFormat($format)
    {
        $allowedFormats = ['json', 'csv'];
        
        if (!in_array(strtolower($format), $allowedFormats, true)) {
            return ['Invalid export format. Allowed formats: ' . implode(', ', $allowedFormats)];
        }
        
        return [];
    }
    
    /**
     * Validate pagination parameters
     */
    public static function validatePagination($limit, $offset)
    {
        $errors = [];
        
        // Validate limit
        if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
            $errors[] = 'Limit must be a number between 1 and 100';
        }
        
        // Validate offset
        if (!is_numeric($offset) || $offset < 0) {
            $errors[] = 'Offset must be a non-negative number';
        }
        
        return $errors;
    }
    
    /**
     * Validate analysis options
     */
    public static function validateAnalysisOptions($options)
    {
        $errors = [];
        $allowedOptions = [
            'include_raw_html',
            'include_word_count',
            'include_schema',
            'include_hreflang',
            'include_headings'
        ];
        
        if (!is_array($options)) {
            return ['Analysis options must be an array'];
        }
        
        foreach ($options as $option => $value) {
            if (!in_array($option, $allowedOptions, true)) {
                $errors[] = "Unknown analysis option: {$option}";
            }
            
            if (!is_bool($value)) {
                $errors[] = "Analysis option {$option} must be a boolean value";
            }
        }
        
        return $errors;
    }
    
    /**
     * Sanitize user input
     */
    public static function sanitizeInput($input, $type = 'string')
    {
        if (is_array($input)) {
            return array_map(function ($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
                
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
                
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'string':
            default:
                return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate rate limit compliance
     */
    public static function validateRateLimit($ipAddress, Storage $storage)
    {
        if (!Config::feature('ENABLE_RATE_LIMITING', true)) {
            return [];
        }
        
        $rateLimit = Config::get('RATE_LIMIT_PER_HOUR', 30);
        $requestCount = $storage->getRequestCount($ipAddress, 1);
        
        if ($requestCount >= $rateLimit) {
            logMessage('WARN', 'Rate limit exceeded', [
                'ip_address' => $ipAddress,
                'request_count' => $requestCount,
                'rate_limit' => $rateLimit,
                'user_agent' => getUserAgent()
            ]);
            
            return ['Rate limit exceeded. Please try again later.'];
        }
        
        return [];
    }
    
    /**
     * Validate content type
     */
    public static function validateContentType($contentType)
    {
        if (empty($contentType)) {
            return [];
        }
        
        $allowedTypes = [
            'text/html',
            'application/xhtml+xml',
            'text/plain'
        ];
        
        // Extract main content type (before semicolon)
        $mainType = explode(';', $contentType)[0];
        $mainType = strtolower(trim($mainType));
        
        if (!in_array($mainType, $allowedTypes, true)) {
            return ["Content type '{$mainType}' is not supported for analysis"];
        }
        
        return [];
    }
    
    /**
     * Validate HTTP status code
     */
    public static function validateHttpStatus($statusCode)
    {
        $errors = [];
        
        if ($statusCode < 200 || $statusCode >= 400) {
            if ($statusCode >= 300 && $statusCode < 400) {
                // Redirect status codes are handled by cURL
                return [];
            }
            
            $errors[] = "HTTP status {$statusCode} indicates the page could not be retrieved successfully";
        }
        
        return $errors;
    }
    
    /**
     * Validate content length
     */
    public static function validateContentLength($contentLength)
    {
        $maxBytes = Config::get('HTTP.MAX_BYTES', 2000000);
        
        if ($contentLength > $maxBytes) {
            return ["Content too large ({$contentLength} bytes). Maximum allowed: {$maxBytes} bytes"];
        }
        
        return [];
    }
    
    /**
     * Validate filename for export
     */
    public static function validateExportFilename($filename)
    {
        $errors = [];
        
        if (empty($filename)) {
            return ['Filename cannot be empty'];
        }
        
        // Check length
        if (strlen($filename) > 255) {
            $errors[] = 'Filename too long (maximum 255 characters)';
        }
        
        // Check for dangerous characters
        $dangerousChars = ['/', '\\', ':', '*', '?', '"', '<', '>', '|'];
        foreach ($dangerousChars as $char) {
            if (strpos($filename, $char) !== false) {
                $errors[] = "Filename contains invalid character: {$char}";
            }
        }
        
        // Check for reserved names (Windows)
        $reservedNames = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        if (in_array(strtoupper($baseName), $reservedNames, true)) {
            $errors[] = "Filename '{$baseName}' is reserved and cannot be used";
        }
        
        return $errors;
    }
    
    /**
     * Validate cache key
     */
    public static function validateCacheKey($cacheKey)
    {
        if (empty($cacheKey)) {
            return ['Cache key cannot be empty'];
        }
        
        if (!preg_match('/^[a-f0-9]{40}$/', $cacheKey)) {
            return ['Invalid cache key format (must be 40-character SHA1 hash)'];
        }
        
        return [];
    }
    
    /**
     * Cross-Site Request Forgery (CSRF) protection
     */
    public static function validateCsrfToken($submittedToken, $sessionToken)
    {
        if (empty($submittedToken) || empty($sessionToken)) {
            return ['CSRF token is required'];
        }
        
        if (!hash_equals($sessionToken, $submittedToken)) {
            return ['Invalid CSRF token'];
        }
        
        return [];
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCsrfToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
}