<?php
/**
 * Meta Tag Analyzer - Analysis Handler
 * 
 * Handles form submissions, performs URL analysis, and displays results.
 */

// Initialize application
define('META_TAG_ANALYZER', true);
require_once __DIR__ . '/../app/bootstrap.php';

// Initialize configuration
Config::load();

// Start measuring page generation time
$startTime = microtime(true);

try {
    // Check request method
    $method = checkRequestMethod(['GET', 'POST']);
    
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
        errorResponse(
            'Rate limit exceeded. Please try again later.',
            429,
            $rateLimiter->formatErrorResponse($clientIp)['rate_limit']
        );
    }
    
    // Get URL from request
    $url = null;
    $bypassCache = false;
    $includeRawHtml = false;
    
    if ($method === 'POST') {
        // Validate CSRF token
        $submittedToken = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        $csrfErrors = Validators::validateCsrfToken($submittedToken, $sessionToken);
        if (!empty($csrfErrors)) {
            errorResponse('Invalid security token. Please refresh the page and try again.', 403);
        }
        
        $url = $_POST['url'] ?? '';
        $bypassCache = !empty($_POST['bypass_cache']);
        $includeRawHtml = !empty($_POST['include_raw_html']);
    } elseif ($method === 'GET') {
        $url = $_GET['url'] ?? '';
        $bypassCache = !empty($_GET['bypass_cache']);
        $includeRawHtml = !empty($_GET['include_raw_html']);
    }
    
    // Validate URL
    if (empty($url)) {
        // Redirect to home page if no URL provided
        header('Location: ' . Config::get('BASE_URL', '') . '/');
        exit;
    }
    
    $urlErrors = Validators::validateUrl($url);
    if (!empty($urlErrors)) {
        errorResponse('Invalid URL: ' . implode(', ', $urlErrors), 400);
    }
    
    // Additional SSRF validation
    $ssrfErrors = Validators::validateUrlForSsrf($url);
    if (!empty($ssrfErrors)) {
        errorResponse('URL not allowed: ' . implode(', ', $ssrfErrors), 403);
    }
    
    // Normalize URL
    $normalizedUrl = Helpers::normalizeUrl($url);
    $cacheKey = Helpers::generateCacheKey($normalizedUrl);
    
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
                'content_length' => $cached['content_length']
            ];
            $cacheHit = true;
            
            logMessage('INFO', 'Serving cached analysis result', [
                'url' => $normalizedUrl,
                'cache_key' => $cacheKey,
                'client_ip' => $clientIp
            ]);
        }
    }
    
    // Perform fresh analysis if not cached
    if (!$analysisData) {
        // Fetch the URL
        logMessage('INFO', 'Fetching URL for analysis', [
            'url' => $normalizedUrl,
            'client_ip' => $clientIp,
            'bypass_cache' => $bypassCache
        ]);
        
        $fetchData = $httpClient->fetch($normalizedUrl);
        
        if (empty($fetchData['content'])) {
            errorResponse('Failed to retrieve content from the URL.', 400);
        }
        
        // Analyze the content
        $analyzer = new Analyzer($fetchData['final_url']);
        $analysisData = $analyzer->analyze($fetchData['content'], $fetchData['final_url']);
        
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
        
        logMessage('INFO', 'URL analysis completed', [
            'url' => $normalizedUrl,
            'final_url' => $fetchData['final_url'],
            'analysis_time_ms' => $analysisData['analysis_meta']['analysis_time_ms'] ?? 0,
            'client_ip' => $clientIp
        ]);
    }
    
    // Store analysis in history
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
            ($analysisData['analysis_meta']['analysis_time_ms'] ?? 0) + ($fetchData['fetch_time_ms'] ?? 0)
        );
    }
    
    // Handle JSON response for AJAX requests
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        jsonResponse([
            'success' => true,
            'data' => $analysisData,
            'meta' => [
                'url' => $normalizedUrl,
                'final_url' => $fetchData['final_url'] ?? $normalizedUrl,
                'cache_hit' => $cacheHit,
                'fetched_at' => date('c'),
                'fetch_data' => $fetchData
            ]
        ]);
    }
    
    // Prepare data for template
    $originalUrl = $url;
    $finalUrl = $fetchData['final_url'] ?? $normalizedUrl;
    $csrfToken = Validators::generateCsrfToken();
    $baseUrl = Config::get('BASE_URL', '');
    $generationTime = microtime(true) - $startTime;
    
    // Include raw HTML if requested and available
    if ($includeRawHtml && isset($fetchData['content'])) {
        $analysisData['raw_html'] = $fetchData['content'];
    }
    
    // Include the results view
    include APP_PATH . '/Views/result.php';
    
} catch (Exception $e) {
    // Log error
    logMessage('ERROR', 'Analysis error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'url' => $url ?? 'unknown',
        'client_ip' => getClientIp()
    ]);
    
    // Check if this is an AJAX request
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        errorResponse('Analysis failed: ' . $e->getMessage(), 500);
    }
    
    // Show user-friendly error page
    http_response_code(500);
    
    $pageTitle = 'Analysis Error - Meta Tag Analyzer';
    $alertMessage = 'Failed to analyze the URL: ' . $e->getMessage();
    $alertType = 'danger';
    $baseUrl = Config::get('BASE_URL', '');
    $generationTime = microtime(true) - $startTime;
    
    // Start output buffering for error content
    ob_start();
    ?>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Analysis Failed
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            We encountered an error while analyzing the URL. This could be due to:
                        </p>
                        <ul class="mb-4">
                            <li>The website is not accessible or temporarily down</li>
                            <li>The URL is protected by security measures</li>
                            <li>Network connectivity issues</li>
                            <li>The content type is not supported</li>
                        </ul>
                        
                        <div class="d-flex gap-2">
                            <a href="<?= htmlspecialchars($baseUrl) ?>/" class="btn btn-primary">
                                <i class="bi bi-house me-2"></i>
                                Try Another URL
                            </a>
                            
                            <?php if (!empty($url)): ?>
                            <form method="POST" action="<?= htmlspecialchars($baseUrl) ?>/analyze.php" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Validators::generateCsrfToken()) ?>">
                                <input type="hidden" name="url" value="<?= htmlspecialchars($url) ?>">
                                <input type="hidden" name="bypass_cache" value="1">
                                <button type="submit" class="btn btn-outline-warning">
                                    <i class="bi bi-arrow-clockwise me-2"></i>
                                    Retry Analysis
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    $content = ob_get_clean();
    
    // Include layout for error display
    include APP_PATH . '/Views/layout.php';
}