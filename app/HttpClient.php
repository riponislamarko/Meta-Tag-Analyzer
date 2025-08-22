<?php
/**
 * HTTP Client Class
 * 
 * Provides secure HTTP client functionality with SSRF protection,
 * timeout handling, and content validation.
 */

class HttpClient
{
    private $connectTimeout;
    private $timeout;
    private $maxRedirects;
    private $maxBytes;
    private $userAgent;
    private $allowedPorts;
    private $verifySSL;
    
    public function __construct()
    {
        $this->connectTimeout = Config::get('HTTP.CONNECT_TIMEOUT', 5);
        $this->timeout = Config::get('HTTP.TIMEOUT', 12);
        $this->maxRedirects = Config::get('HTTP.MAX_REDIRECTS', 5);
        $this->maxBytes = Config::get('HTTP.MAX_BYTES', 2000000);
        $this->userAgent = Config::get('HTTP.USER_AGENT', 'MetaTagAnalyzer/1.0');
        $this->allowedPorts = Config::get('HTTP.ALLOW_PORTS', [80, 443]);
        $this->verifySSL = Config::get('HTTP.VERIFY_SSL', true);
    }
    
    /**
     * Fetch URL content with SSRF protection
     */
    public function fetch($url)
    {
        $startTime = microtime(true);
        
        // Validate URL for SSRF
        $validationErrors = Validators::validateUrlForSsrf($url);
        if (!empty($validationErrors)) {
            throw new Exception('SSRF validation failed: ' . implode(', ', $validationErrors));
        }
        
        // Normalize URL
        $normalizedUrl = Helpers::normalizeUrl($url);
        
        // Initialize cURL
        $ch = curl_init();
        
        try {
            // Set basic cURL options
            curl_setopt_array($ch, [
                CURLOPT_URL => $normalizedUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => $this->maxRedirects,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_USERAGENT => $this->userAgent,
                CURLOPT_HEADER => false,
                CURLOPT_NOBODY => false,
                CURLOPT_SSL_VERIFYPEER => $this->verifySSL,
                CURLOPT_SSL_VERIFYHOST => $this->verifySSL ? 2 : 0,
                CURLOPT_ENCODING => '', // Accept all supported encodings
                CURLOPT_AUTOREFERER => true,
            ]);
            
            // Set up header callback to get response headers
            $responseHeaders = [];
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                
                if (count($header) < 2) {
                    return $len;
                }
                
                $name = strtolower(trim($header[0]));
                $value = trim($header[1]);
                $responseHeaders[$name] = $value;
                
                return $len;
            });
            
            // Set up write callback to limit content size
            $downloadedBytes = 0;
            $content = '';
            
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$downloadedBytes, &$content) {
                $len = strlen($data);
                $downloadedBytes += $len;
                
                if ($downloadedBytes > $this->maxBytes) {
                    // Stop download if content is too large
                    return -1;
                }
                
                $content .= $data;
                return $len;
            });
            
            // Set up progress callback for additional monitoring
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch, $downloadTotal, $downloaded, $uploadTotal, $uploaded) {
                if ($downloadTotal > 0 && $downloadTotal > $this->maxBytes) {
                    return -1; // Stop download
                }
                return 0;
            });
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            
            // Execute request
            curl_exec($ch);
            
            // Check for cURL errors
            $curlError = curl_error($ch);
            if (!empty($curlError)) {
                throw new Exception("cURL error: {$curlError}");
            }
            
            // Get response information
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
            
            curl_close($ch);
            
            // Validate final URL for SSRF (in case of redirects)
            if ($finalUrl !== $normalizedUrl) {
                $finalValidationErrors = Validators::validateUrlForSsrf($finalUrl);
                if (!empty($finalValidationErrors)) {
                    throw new Exception('SSRF validation failed on redirect: ' . implode(', ', $finalValidationErrors));
                }
            }
            
            // Validate HTTP status
            $statusErrors = Validators::validateHttpStatus($httpCode);
            if (!empty($statusErrors)) {
                throw new Exception(implode(', ', $statusErrors));
            }
            
            // Validate content type
            $contentTypeErrors = Validators::validateContentType($contentType);
            if (!empty($contentTypeErrors)) {
                throw new Exception(implode(', ', $contentTypeErrors));
            }
            
            // Validate content length
            $contentLengthErrors = Validators::validateContentLength(strlen($content));
            if (!empty($contentLengthErrors)) {
                throw new Exception(implode(', ', $contentLengthErrors));
            }
            
            // Convert content to UTF-8
            $content = Helpers::convertToUtf8($content, $contentType);
            
            // Clean HTML content
            $content = Helpers::cleanHtml($content);
            
            $endTime = microtime(true);
            $fetchTime = round(($endTime - $startTime) * 1000); // Convert to milliseconds
            
            // Log successful fetch
            logMessage('INFO', 'URL fetched successfully', [
                'url' => $url,
                'final_url' => $finalUrl,
                'http_code' => $httpCode,
                'content_length' => strlen($content),
                'fetch_time_ms' => $fetchTime,
                'redirect_count' => $redirectCount
            ]);
            
            return [
                'content' => $content,
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'final_url' => $finalUrl,
                'content_length' => strlen($content),
                'fetch_time_ms' => $fetchTime,
                'redirect_count' => $redirectCount,
                'headers' => $responseHeaders
            ];
            
        } catch (Exception $e) {
            if (isset($ch)) {
                curl_close($ch);
            }
            
            // Log fetch error
            logMessage('ERROR', 'URL fetch failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'client_ip' => getClientIp()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Check if URL is reachable (HEAD request)
     */
    public function checkUrl($url)
    {
        // Validate URL for SSRF
        $validationErrors = Validators::validateUrlForSsrf($url);
        if (!empty($validationErrors)) {
            throw new Exception('SSRF validation failed: ' . implode(', ', $validationErrors));
        }
        
        $normalizedUrl = Helpers::normalizeUrl($url);
        
        $ch = curl_init();
        
        try {
            curl_setopt_array($ch, [
                CURLOPT_URL => $normalizedUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true, // HEAD request
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => $this->maxRedirects,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_USERAGENT => $this->userAgent,
                CURLOPT_SSL_VERIFYPEER => $this->verifySSL,
                CURLOPT_SSL_VERIFYHOST => $this->verifySSL ? 2 : 0,
            ]);
            
            curl_exec($ch);
            
            $curlError = curl_error($ch);
            if (!empty($curlError)) {
                throw new Exception("cURL error: {$curlError}");
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            
            curl_close($ch);
            
            return [
                'reachable' => ($httpCode >= 200 && $httpCode < 400),
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'final_url' => $finalUrl
            ];
            
        } catch (Exception $e) {
            if (isset($ch)) {
                curl_close($ch);
            }
            throw $e;
        }
    }
    
    /**
     * Fetch favicon from URL
     */
    public function fetchFavicon($baseUrl, $faviconUrl = null)
    {
        if (!Config::feature('ENABLE_FAVICON_DISCOVERY', true)) {
            return null;
        }
        
        $faviconUrls = [];
        
        if ($faviconUrl) {
            // Use provided favicon URL
            $faviconUrls[] = Helpers::resolveRelativeUrl($baseUrl, $faviconUrl);
        }
        
        // Add common favicon fallbacks
        $fallbacks = Config::get('ANALYSIS.FAVICON_FALLBACKS', ['/favicon.ico', '/favicon.png']);
        foreach ($fallbacks as $fallback) {
            $faviconUrls[] = Helpers::resolveRelativeUrl($baseUrl, $fallback);
        }
        
        // Try each favicon URL
        foreach ($faviconUrls as $url) {
            try {
                $result = $this->checkUrl($url);
                if ($result['reachable']) {
                    return $url;
                }
            } catch (Exception $e) {
                // Continue to next URL
                continue;
            }
        }
        
        return null;
    }
    
    /**
     * Validate image URL (for OG/Twitter images)
     */
    public function validateImageUrl($imageUrl, $baseUrl = null)
    {
        if (!Config::feature('ENABLE_OG_IMAGE_VALIDATION', false)) {
            return true; // Skip validation if disabled
        }
        
        try {
            if ($baseUrl) {
                $imageUrl = Helpers::resolveRelativeUrl($baseUrl, $imageUrl);
            }
            
            // Quick timeout for image validation
            $originalTimeout = $this->timeout;
            $this->timeout = Config::get('ANALYSIS.OG_IMAGE_TIMEOUT', 3);
            
            $result = $this->checkUrl($imageUrl);
            $isValid = $result['reachable'] && 
                      strpos($result['content_type'], 'image/') === 0;
            
            // Restore original timeout
            $this->timeout = $originalTimeout;
            
            return $isValid;
            
        } catch (Exception $e) {
            // Restore original timeout
            $this->timeout = $originalTimeout;
            return false;
        }
    }
    
    /**
     * Get timeout settings
     */
    public function getTimeouts()
    {
        return [
            'connect_timeout' => $this->connectTimeout,
            'timeout' => $this->timeout
        ];
    }
    
    /**
     * Get maximum content size
     */
    public function getMaxBytes()
    {
        return $this->maxBytes;
    }
    
    /**
     * Get user agent string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }
    
    /**
     * Set custom user agent
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }
    
    /**
     * Get allowed ports
     */
    public function getAllowedPorts()
    {
        return $this->allowedPorts;
    }
    
    /**
     * Check if SSL verification is enabled
     */
    public function isSSLVerificationEnabled()
    {
        return $this->verifySSL;
    }
}