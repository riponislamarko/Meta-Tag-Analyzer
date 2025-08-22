<?php
/**
 * Meta Tag Analyzer - Home Page
 * 
 * Main entry point that displays the URL input form and analysis features.
 */

// Initialize application
define('META_TAG_ANALYZER', true);
require_once __DIR__ . '/../app/bootstrap.php';

// Initialize configuration
Config::load();

// Start measuring page generation time
$startTime = microtime(true);

try {
    // Initialize storage for potential error logging
    $storage = new Storage();
    
    // Initialize rate limiter
    $rateLimiter = new RateLimiter($storage);
    
    // Get client information
    $clientIp = getClientIp();
    $userAgent = getUserAgent();
    
    // Check if we should show any status messages
    $alertMessage = null;
    $alertType = null;
    
    // Check for rate limiting info (non-blocking)
    $rateLimitInfo = $rateLimiter->getInfo($clientIp);
    
    if (!$rateLimitInfo['allowed']) {
        $alertMessage = 'You have reached the rate limit. Please try again later.';
        $alertType = 'warning';
    } elseif ($rateLimitInfo['remaining'] <= 5) {
        $alertMessage = "You have {$rateLimitInfo['remaining']} requests remaining this hour.";
        $alertType = 'info';
    }
    
    // Generate CSRF token
    $csrfToken = Validators::generateCsrfToken();
    
    // Set template variables
    $baseUrl = Config::get('BASE_URL', '');
    $generationTime = microtime(true) - $startTime;
    
    // Include the home view
    include APP_PATH . '/Views/home.php';
    
} catch (Exception $e) {
    // Log error
    logMessage('ERROR', 'Home page error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'client_ip' => getClientIp()
    ]);
    
    // Show user-friendly error
    http_response_code(500);
    
    $pageTitle = 'Error - Meta Tag Analyzer';
    $alertMessage = 'An error occurred while loading the page. Please try again later.';
    $alertType = 'danger';
    $baseUrl = Config::get('BASE_URL', '');
    $generationTime = microtime(true) - $startTime;
    
    // Start output buffering for error content
    ob_start();
    ?>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 4rem;"></i>
                <h2 class="mt-3 mb-3">Oops! Something went wrong</h2>
                <p class="text-muted mb-4">
                    We're experiencing technical difficulties. Please try again later or contact support if the problem persists.
                </p>
                <a href="<?= htmlspecialchars($baseUrl) ?>/" class="btn btn-primary">
                    <i class="bi bi-house me-2"></i>
                    Go to Home
                </a>
            </div>
        </div>
    </div>
    
    <?php
    $content = ob_get_clean();
    
    // Include layout for error display
    include APP_PATH . '/Views/layout.php';
}