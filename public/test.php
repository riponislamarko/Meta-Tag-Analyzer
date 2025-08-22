<?php
/**
 * Meta Tag Analyzer - Installation Test
 * 
 * Simple test script to verify the installation and basic functionality.
 * Access this file via: https://yourdomain.com/test.php
 * 
 * ⚠️ IMPORTANT: Remove this file after testing for security reasons.
 */

// Initialize application
define('META_TAG_ANALYZER', true);
require_once __DIR__ . '/../app/bootstrap.php';

// Set content type
header('Content-Type: text/html; charset=utf-8');

try {
    // Initialize configuration
    Config::load();
    
    echo '<h2>Meta Tag Analyzer - Installation Test</h2>';
    echo '<style>body{font-family:sans-serif;margin:2rem;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>';
    
    // Test 1: Configuration
    echo '<h3>1. Configuration Test</h3>';
    try {
        $appEnv = Config::get('APP_ENV');
        $baseUrl = Config::get('BASE_URL');
        echo "<p class='success'>✓ Configuration loaded successfully</p>";
        echo "<p class='info'>Environment: {$appEnv}</p>";
        echo "<p class='info'>Base URL: {$baseUrl}</p>";
    } catch (Exception $e) {
        echo "<p class='error'>✗ Configuration failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Test 2: Database
    echo '<h3>2. Database Test</h3>';
    try {
        $storage = new Storage();
        $stats = $storage->getStats();
        echo "<p class='success'>✓ Database connection successful</p>";
        echo "<p class='info'>Driver: " . $storage->getDriver() . "</p>";
        echo "<p class='info'>Total requests: {$stats['total_requests']}</p>";
    } catch (Exception $e) {
        echo "<p class='error'>✗ Database failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Test 3: Cache
    echo '<h3>3. Cache Test</h3>';
    try {
        $cache = new Cache();
        $testKey = 'test_' . time();
        $testData = ['test' => true, 'timestamp' => time()];
        
        $cache->set($testKey, 'https://example.com', $testData);
        $retrieved = $cache->get($testKey);
        
        if ($retrieved && $retrieved['data_json']['test'] === true) {
            echo "<p class='success'>✓ Cache system working</p>";
            $cache->delete($testKey); // Clean up
        } else {
            echo "<p class='error'>✗ Cache test failed</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Cache failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Test 4: HTTP Client
    echo '<h3>4. HTTP Client Test</h3>';
    try {
        $httpClient = new HttpClient();
        echo "<p class='success'>✓ HTTP Client initialized</p>";
        echo "<p class='info'>User Agent: " . $httpClient->getUserAgent() . "</p>";
        echo "<p class='info'>Max Bytes: " . number_format($httpClient->getMaxBytes()) . " bytes</p>";
        echo "<p class='info'>SSL Verification: " . ($httpClient->isSSLVerificationEnabled() ? 'Enabled' : 'Disabled') . "</p>";
    } catch (Exception $e) {
        echo "<p class='error'>✗ HTTP Client failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Test 5: URL Validation
    echo '<h3>5. URL Validation Test</h3>';
    try {
        $testUrls = [
            'https://example.com' => 'should pass',
            'http://127.0.0.1' => 'should be blocked (SSRF)',
            'ftp://example.com' => 'should be blocked (scheme)',
            'https://google.com' => 'should pass'
        ];
        
        foreach ($testUrls as $url => $expected) {
            $errors = Validators::validateUrlForSsrf($url);
            $status = empty($errors) ? 'PASS' : 'BLOCKED';
            $class = ($status === 'BLOCKED' && strpos($expected, 'blocked') !== false) || 
                     ($status === 'PASS' && strpos($expected, 'pass') !== false) ? 'success' : 'error';
            
            echo "<p class='{$class}'>URL: {$url} → {$status} ({$expected})</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ URL Validation failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Test 6: File Permissions
    echo '<h3>6. File Permissions Test</h3>';
    try {
        $paths = [
            'Storage directory' => __DIR__ . '/../storage',
            'Cache directory' => __DIR__ . '/../storage/cache',
            'Logs directory' => __DIR__ . '/../storage/logs',
            'Config file' => __DIR__ . '/../.env.php'
        ];
        
        foreach ($paths as $name => $path) {
            if (file_exists($path)) {
                $writable = is_writable($path);
                $permissions = substr(sprintf('%o', fileperms($path)), -4);
                $status = $writable ? '✓' : '✗';
                $class = $writable ? 'success' : 'error';
                
                echo "<p class='{$class}'>{$status} {$name}: {$permissions}</p>";
            } else {
                echo "<p class='error'>✗ {$name}: Not found</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Permission test failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Test 7: PHP Extensions
    echo '<h3>7. PHP Extensions Test</h3>';
    $requiredExtensions = ['curl', 'dom', 'mbstring', 'json', 'openssl'];
    if (Config::get('DB_DRIVER') === 'sqlite') {
        $requiredExtensions[] = 'sqlite3';
    } else {
        $requiredExtensions[] = 'pdo_mysql';
    }
    
    foreach ($requiredExtensions as $ext) {
        $loaded = extension_loaded($ext);
        $status = $loaded ? '✓' : '✗';
        $class = $loaded ? 'success' : 'error';
        echo "<p class='{$class}'>{$status} {$ext}</p>";
    }
    
    // Test 8: API Test
    echo '<h3>8. API Test</h3>';
    $apiUrl = Config::get('BASE_URL') . '/api/analyze?url=https://example.com';
    echo "<p class='info'>Test API endpoint: <a href='{$apiUrl}' target='_blank'>{$apiUrl}</a></p>";
    echo "<p class='info'>You can test this URL in a new tab to verify the API is working.</p>";
    
    // Summary
    echo '<h3>Test Summary</h3>';
    echo "<p class='success'>✓ If most tests above are green, your installation is working correctly!</p>";
    echo "<p class='info'>You can now:</p>";
    echo "<ul>";
    echo "<li>Visit the <a href='" . Config::get('BASE_URL', '/') . "'>home page</a> to start analyzing URLs</li>";
    echo "<li>Test the <a href='{$apiUrl}' target='_blank'>API endpoint</a></li>";
    echo "<li>Read the <a href='README.md'>documentation</a> for more information</li>";
    echo "</ul>";
    
    echo "<p class='error'><strong>SECURITY NOTICE:</strong> Delete this test.php file after testing!</p>";
    
} catch (Exception $e) {
    echo "<h3 class='error'>Critical Error</h3>";
    echo "<p class='error'>Installation test failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your configuration and file permissions.</p>";
}

?>

<style>
    body { 
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
        margin: 2rem; 
        line-height: 1.6; 
    }
    .success { color: #28a745; }
    .error { color: #dc3545; }
    .info { color: #6c757d; }
    h2 { color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 0.5rem; }
    h3 { color: #495057; margin-top: 2rem; }
    a { color: #007bff; text-decoration: none; }
    a:hover { text-decoration: underline; }
    ul { margin: 1rem 0; }
    li { margin: 0.5rem 0; }
</style>