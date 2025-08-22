<?php
/**
 * Meta Tag Analyzer - Export Endpoint
 * 
 * Handles export requests for analysis results in JSON and CSV formats.
 * 
 * Usage: GET /export.php?url=https://example.com&format=json
 */

// Initialize application
define('META_TAG_ANALYZER', true);
require_once __DIR__ . '/../app/bootstrap.php';

// Initialize configuration
Config::load();

try {
    // Check request method
    $method = checkRequestMethod(['GET', 'POST']);
    
    // Initialize components
    $storage = new Storage();
    $rateLimiter = new RateLimiter($storage);
    $cache = new Cache($storage);
    
    // Get client information
    $clientIp = getClientIp();
    $userAgent = getUserAgent();
    
    // Check rate limit
    $rateLimitResult = $rateLimiter->checkLimit($clientIp);
    if (!$rateLimitResult['allowed']) {
        errorResponse('Rate limit exceeded. Please try again later.', 429);
    }
    
    // Get parameters
    $url = $_REQUEST['url'] ?? '';
    $format = strtolower($_REQUEST['format'] ?? 'json');
    $filename = $_REQUEST['filename'] ?? '';
    
    // Validate required parameters
    if (empty($url)) {
        errorResponse('URL parameter is required', 400);
    }
    
    // Validate format
    $formatErrors = Validators::validateExportFormat($format);
    if (!empty($formatErrors)) {
        errorResponse(implode(', ', $formatErrors), 400);
    }
    
    // Validate URL
    $urlErrors = Validators::validateUrl($url);
    if (!empty($urlErrors)) {
        errorResponse('Invalid URL: ' . implode(', ', $urlErrors), 400);
    }
    
    // Generate cache key and get analysis data
    $normalizedUrl = Helpers::normalizeUrl($url);
    $cacheKey = Helpers::generateCacheKey($normalizedUrl);
    
    // Try to get cached data first
    $cached = $cache->get($cacheKey);
    if (!$cached) {
        // If no cached data, suggest analyzing first
        errorResponse('No analysis data found for this URL. Please analyze the URL first.', 404, [
            'suggestion' => 'Visit the main page to analyze this URL before exporting',
            'analyze_url' => Config::get('BASE_URL', '') . '/analyze.php?url=' . urlencode($url)
        ]);
    }
    
    $analysisData = $cached['data_json'];
    $metadata = [
        'url' => $normalizedUrl,
        'final_url' => $cached['final_url'],
        'exported_at' => date('c'),
        'cache_created_at' => $cached['created_at'],
        'http_status' => $cached['http_status'],
        'content_type' => $cached['content_type']
    ];
    
    // Generate filename if not provided
    if (empty($filename)) {
        $domain = parse_url($normalizedUrl, PHP_URL_HOST);
        $safeDomain = preg_replace('/[^a-zA-Z0-9]/', '-', $domain);
        $timestamp = date('Y-m-d');
        $filename = "meta-analysis-{$safeDomain}-{$timestamp}";
    } else {
        // Validate custom filename
        $filenameErrors = Validators::validateExportFilename($filename);
        if (!empty($filenameErrors)) {
            errorResponse('Invalid filename: ' . implode(', ', $filenameErrors), 400);
        }
        
        // Remove extension if provided (we'll add it)
        $filename = pathinfo($filename, PATHINFO_FILENAME);
    }
    
    // Record the export request
    $rateLimiter->recordRequest($clientIp, $_SERVER['REQUEST_URI'] ?? '/', $userAgent);
    
    // Prepare export data
    $exportData = [
        'metadata' => $metadata,
        'analysis' => $analysisData
    ];
    
    // Generate export content based on format
    if ($format === 'json') {
        exportAsJSON($exportData, $filename);
    } elseif ($format === 'csv') {
        exportAsCSV($exportData, $filename);
    } else {
        errorResponse('Unsupported export format', 400);
    }
    
} catch (Exception $e) {
    // Log error
    logMessage('ERROR', 'Export error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'url' => $url ?? 'unknown',
        'format' => $format ?? 'unknown',
        'client_ip' => getClientIp()
    ]);
    
    errorResponse('Export failed: ' . $e->getMessage(), 500);
}

/**
 * Export data as JSON
 */
function exportAsJSON($data, $filename)
{
    $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if ($jsonContent === false) {
        errorResponse('Failed to encode data as JSON', 500);
    }
    
    // Set headers for download
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    header('Content-Length: ' . strlen($jsonContent));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output content
    echo $jsonContent;
    exit;
}

/**
 * Export data as CSV
 */
function exportAsCSV($data, $filename)
{
    // Flatten the data structure
    $flatData = flattenDataForCSV($data);
    
    // Create CSV content
    $csvContent = generateCSVContent($flatData);
    
    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Content-Length: ' . strlen($csvContent));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output content
    echo $csvContent;
    exit;
}

/**
 * Flatten nested data structure for CSV export
 */
function flattenDataForCSV($data, $prefix = '')
{
    $flattened = [];
    
    foreach ($data as $key => $value) {
        $newKey = $prefix ? $prefix . '.' . $key : $key;
        
        if (is_array($value) && !empty($value)) {
            if (isAssociativeArray($value)) {
                // Recursive flatten for associative arrays
                $flattened = array_merge($flattened, flattenDataForCSV($value, $newKey));
            } else {
                // Join indexed arrays
                $flattened[$newKey] = implode('; ', array_map('strval', $value));
            }
        } elseif (is_object($value)) {
            $flattened = array_merge($flattened, flattenDataForCSV((array)$value, $newKey));
        } else {
            $flattened[$newKey] = $value;
        }
    }
    
    return $flattened;
}

/**
 * Check if array is associative
 */
function isAssociativeArray($array)
{
    if (!is_array($array) || empty($array)) {
        return false;
    }
    
    return array_keys($array) !== range(0, count($array) - 1);
}

/**
 * Generate CSV content from flattened data
 */
function generateCSVContent($data)
{
    $output = fopen('php://temp', 'r+');
    
    // Write headers
    fputcsv($output, array_keys($data));
    
    // Write data
    fputcsv($output, array_values($data));
    
    // Get content
    rewind($output);
    $csvContent = stream_get_contents($output);
    fclose($output);
    
    // Add BOM for better Excel compatibility
    return "\xEF\xBB\xBF" . $csvContent;
}

/**
 * Generate detailed CSV with multiple rows for complex data
 */
function generateDetailedCSV($analysisData, $metadata)
{
    $output = fopen('php://temp', 'r+');
    
    // Headers
    $headers = [
        'Section',
        'Property',
        'Value',
        'Notes'
    ];
    fputcsv($output, $headers);
    
    // Metadata section
    foreach ($metadata as $key => $value) {
        fputcsv($output, ['Metadata', $key, $value, '']);
    }
    
    // Meta tags section
    if (isset($analysisData['meta'])) {
        foreach ($analysisData['meta'] as $key => $value) {
            if (!empty($value)) {
                $notes = '';
                
                // Add validation notes for title and description
                if ($key === 'title' && strlen($value) > 60) {
                    $notes = 'Too long (recommended: 50-60 chars)';
                } elseif ($key === 'description' && strlen($value) > 160) {
                    $notes = 'Too long (recommended: 150-160 chars)';
                }
                
                fputcsv($output, ['Meta Tags', $key, $value, $notes]);
            }
        }
    }
    
    // Open Graph section
    if (isset($analysisData['open_graph'])) {
        foreach ($analysisData['open_graph'] as $key => $value) {
            if (!empty($value)) {
                fputcsv($output, ['Open Graph', "og:{$key}", $value, '']);
            }
        }
    }
    
    // Twitter Card section
    if (isset($analysisData['twitter_card'])) {
        foreach ($analysisData['twitter_card'] as $key => $value) {
            if (!empty($value)) {
                fputcsv($output, ['Twitter Card', "twitter:{$key}", $value, '']);
            }
        }
    }
    
    // Headings section
    if (isset($analysisData['headings'])) {
        foreach ($analysisData['headings'] as $level => $headings) {
            foreach ($headings as $index => $heading) {
                fputcsv($output, ['Headings', strtoupper($level), $heading, "Position: " . ($index + 1)]);
            }
        }
    }
    
    // Schema.org section
    if (isset($analysisData['schema_org']) && !empty($analysisData['schema_org'])) {
        foreach ($analysisData['schema_org'] as $index => $schema) {
            fputcsv($output, ['Schema.org', 'Type', $schema, "Index: " . ($index + 1)]);
        }
    }
    
    // Hreflang section
    if (isset($analysisData['hreflang']) && !empty($analysisData['hreflang'])) {
        foreach ($analysisData['hreflang'] as $hreflang) {
            fputcsv($output, ['Hreflang', $hreflang['lang'], $hreflang['url'], '']);
        }
    }
    
    // Additional properties
    $additionalProperties = [
        'canonical' => 'Canonical URL',
        'favicon' => 'Favicon URL',
        'word_count' => 'Word Count'
    ];
    
    foreach ($additionalProperties as $key => $label) {
        if (isset($analysisData[$key]) && !empty($analysisData[$key])) {
            fputcsv($output, ['Additional', $label, $analysisData[$key], '']);
        }
    }
    
    // Get content
    rewind($output);
    $csvContent = stream_get_contents($output);
    fclose($output);
    
    // Add BOM for better Excel compatibility
    return "\xEF\xBB\xBF" . $csvContent;
}