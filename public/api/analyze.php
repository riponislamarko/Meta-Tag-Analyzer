<?php
/**
 * Meta Tag Analyzer - REST API Endpoint
 * 
 * Provides programmatic access to meta tag analysis functionality.
 * 
 * Usage: GET /api/analyze?url=https://example.com
 */

// Initialize application
define('META_TAG_ANALYZER', true);
require_once __DIR__ . '/../../app/bootstrap.php';

// Set JSON content type
header('Content-Type: application/json; charset=utf-8');

// Initialize configuration
Config::load();

try {
    // Check request method
    $method = checkRequestMethod(['GET']);
    
    // Initialize components
    $storage = new Storage();
    $rateLimiter = new RateLimiter($storage);
    $cache = new Cache($storage);
    $httpClient = new HttpClient();
    
    // Get client information
    $clientIp = getClientIp();
    $userAgent = getUserAgent();
    
    // Check rate limit and record request
    $rateLimitResult = $rateLimiter->checkAndRecord($clientIp, $_SERVER['REQUEST_URI'] ?? '/', $userAgent);
    
    if (!$rateLimitResult['allowed']) {
        http_response_code(429);
        echo json_encode($rateLimiter->formatErrorResponse($clientIp), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Get parameters
    $url = $_GET['url'] ?? '';
    $bypassCache = !empty($_GET['bypass_cache']) || !empty($_GET['no_cache']);
    $includeRawHtml = !empty($_GET['include_raw_html']) || !empty($_GET['raw_html']);
    $format = strtolower($_GET['format'] ?? 'json');
    
    // Validate required parameters
    if (empty($url)) {
        errorResponse('URL parameter is required', 400, [
            'example' => Config::get('BASE_URL', '') . '/api/analyze?url=https://example.com',
            'parameters' => [
                'url' => 'Required. The URL to analyze',
                'bypass_cache' => 'Optional. Set to 1 to bypass cache',
                'include_raw_html' => 'Optional. Set to 1 to include raw HTML in response',
                'format' => 'Optional. Response format: json (default)'
            ]
        ]);
    }
    
    // Validate URL
    $urlErrors = Validators::validateUrl($url);
    if (!empty($urlErrors)) {
        errorResponse('Invalid URL: ' . implode(', ', $urlErrors), 400);
    }
    
    // Additional SSRF validation
    $ssrfErrors = Validators::validateUrlForSsrf($url);
    if (!empty($ssrfErrors)) {
        errorResponse('URL not allowed: ' . implode(', ', $ssrfErrors), 403, [
            'security_note' => 'This service blocks requests to private IP addresses and localhost for security reasons.'
        ]);
    }
    
    // Validate format
    $formatErrors = Validators::validateExportFormat($format);
    if (!empty($formatErrors)) {
        errorResponse(implode(', ', $formatErrors), 400);
    }
    
    // Normalize URL
    $normalizedUrl = Helpers::normalizeUrl($url);
    $cacheKey = Helpers::generateCacheKey($normalizedUrl);
    
    // Track analysis start time
    $analysisStartTime = microtime(true);
    
    // Check cache first (unless bypassed)
    $cacheHit = false;
    $analysisData = null;
    $fetchData = null;
    
    if (!$bypassCache && $cache->has($cacheKey)) {
        $cached = $cache->get($cacheKey);
        if ($cached) {
            $analysisData = $cached['data_json'];
            $fetchData = [
                'final_url' => $cached['final_url'],
                'http_code' => $cached['http_status'],
                'content_type' => $cached['content_type'],
                'content_length' => $cached['content_length'],
                'fetch_time_ms' => 0 // Cached, so no fetch time
            ];
            $cacheHit = true;
            
            // Include raw HTML if requested and available
            if ($includeRawHtml && !empty($cached['raw_html'])) {
                $analysisData['raw_html'] = $cached['raw_html'];
            }
            
            logMessage('INFO', 'API: Serving cached analysis result', [
                'url' => $normalizedUrl,
                'cache_key' => $cacheKey,
                'client_ip' => $clientIp,
                'user_agent' => $userAgent
            ]);
        }
    }
    
    // Perform fresh analysis if not cached
    if (!$analysisData) {
        // Fetch the URL
        logMessage('INFO', 'API: Fetching URL for analysis', [
            'url' => $normalizedUrl,
            'client_ip' => $clientIp,
            'bypass_cache' => $bypassCache
        ]);
        
        $fetchData = $httpClient->fetch($normalizedUrl);
        
        if (empty($fetchData['content'])) {
            errorResponse('Failed to retrieve content from the URL.', 400, [
                'url' => $normalizedUrl,
                'http_status' => $fetchData['http_code'] ?? 'unknown'
            ]);
        }
        
        // Analyze the content
        $analyzer = new Analyzer($fetchData['final_url']);
        $analysisData = $analyzer->analyze($fetchData['content'], $fetchData['final_url']);
        
        // Include raw HTML if requested
        if ($includeRawHtml) {
            $analysisData['raw_html'] = $fetchData['content'];
        }
        
        // Store in cache
        if (Config::feature('ENABLE_CACHE', true)) {
            $rawHtmlToCache = $includeRawHtml ? $fetchData['content'] : null;
            
            $cache->set(
                $cacheKey,
                $normalizedUrl,
                $analysisData,
                $rawHtmlToCache,
                $fetchData
            );
        }
        
        logMessage('INFO', 'API: URL analysis completed', [
            'url' => $normalizedUrl,
            'final_url' => $fetchData['final_url'],
            'analysis_time_ms' => $analysisData['analysis_meta']['analysis_time_ms'] ?? 0,
            'client_ip' => $clientIp
        ]);
    }
    
    // Calculate total processing time
    $totalProcessingTime = round((microtime(true) - $analysisStartTime) * 1000);
    
    // Store analysis in history (if enabled)
    if (Config::feature('ENABLE_ANALYSIS_HISTORY', false)) {
        $storage->storeAnalysisHistory(
            $clientIp,
            $normalizedUrl,
            $fetchData['final_url'] ?? $normalizedUrl,
            $fetchData['http_code'] ?? null,
            $analysisData['meta']['title'] ?? null,
            $analysisData['meta']['description'] ?? null,
            $analysisData['open_graph']['title'] ?? null,
            $analysisData['open_graph']['description'] ?? null,
            $cacheHit,
            $totalProcessingTime
        );
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'data' => $analysisData,
        'meta' => [
            'url' => $normalizedUrl,
            'final_url' => $fetchData['final_url'] ?? $normalizedUrl,
            'cache_hit' => $cacheHit,
            'processing_time_ms' => $totalProcessingTime,
            'fetched_at' => date('c'),
            'api_version' => Config::get('APP_VERSION', '1.0.0')
        ]
    ];
    
    // Add HTTP information if available
    if ($fetchData) {
        $response['meta']['http'] = [
            'status_code' => $fetchData['http_code'],
            'content_type' => $fetchData['content_type'],
            'content_length' => $fetchData['content_length'],
            'fetch_time_ms' => $fetchData['fetch_time_ms'],
            'redirect_count' => $fetchData['redirect_count'] ?? 0
        ];
    }
    
    // Add rate limit information
    $response['meta']['rate_limit'] = [
        'remaining' => $rateLimitResult['remaining'],
        'limit' => $rateLimitResult['limit'],
        'reset_at' => $rateLimitResult['reset_at'],
        'reset_at_formatted' => date('Y-m-d H:i:s', $rateLimitResult['reset_at'])
    ];
    
    // Send response
    http_response_code(200);
    
    if ($format === 'json') {
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        // This shouldn't happen due to validation, but just in case
        errorResponse('Unsupported format', 400);
    }
    
} catch (Exception $e) {
    // Log error
    logMessage('ERROR', 'API error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'url' => $url ?? 'unknown',
        'client_ip' => getClientIp(),
        'user_agent' => getUserAgent()
    ]);
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => Config::isProd() ? 'An internal error occurred' : $e->getMessage(),
            'code' => 500,
            'type' => 'internal_error'
        ],
        'meta' => [
            'timestamp' => date('c'),
            'api_version' => Config::get('APP_VERSION', '1.0.0')
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}