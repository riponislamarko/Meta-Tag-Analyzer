<?php
/**
 * Meta Tag Analyzer - Bootstrap
 * 
 * This file initializes the application, sets up error handling,
 * loads configuration, and provides autoloading for classes.
 */

// Prevent direct access
if (!defined('META_TAG_ANALYZER')) {
    define('META_TAG_ANALYZER', true);
}

// Define application constants
define('APP_ROOT', dirname(__DIR__));
define('APP_PATH', APP_ROOT . '/app');
define('PUBLIC_PATH', APP_ROOT . '/public');
define('STORAGE_PATH', APP_ROOT . '/storage');
define('CONFIG_PATH', APP_ROOT . '/.env.php');

// Set default timezone
date_default_timezone_set('UTC');

// Load configuration
if (!file_exists(CONFIG_PATH)) {
    throw new Exception('Configuration file not found. Please copy .env.php.example to .env.php and configure it.');
}

$config = require CONFIG_PATH;
if (!is_array($config)) {
    throw new Exception('Invalid configuration file format.');
}

// Store config globally for easy access
$GLOBALS['APP_CONFIG'] = $config;

/**
 * Get configuration value
 */
function config($key, $default = null)
{
    $keys = explode('.', $key);
    $value = $GLOBALS['APP_CONFIG'];
    
    foreach ($keys as $k) {
        if (!is_array($value) || !array_key_exists($k, $value)) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}

/**
 * Simple autoloader for application classes
 */
spl_autoload_register(function ($class) {
    // Only autoload our application classes
    $baseNamespace = '';
    
    // Remove namespace prefix if present
    if (strpos($class, $baseNamespace) === 0) {
        $class = substr($class, strlen($baseNamespace));
    }
    
    // Convert class name to file path
    $file = APP_PATH . '/' . str_replace('\\', '/', $class) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Error handler
 */
function handleError($severity, $message, $file, $line)
{
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    $errorType = 'Error';
    switch ($severity) {
        case E_WARNING:
        case E_USER_WARNING:
            $errorType = 'Warning';
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $errorType = 'Notice';
            break;
    }
    
    $errorMessage = sprintf(
        '%s: %s in %s on line %d',
        $errorType,
        $message,
        $file,
        $line
    );
    
    // Log error if logging is enabled
    if (config('LOGGING.ENABLED', false)) {
        logMessage('ERROR', $errorMessage, [
            'file' => $file,
            'line' => $line,
            'severity' => $severity
        ]);
    }
    
    // In production, don't show detailed errors to users
    if (config('APP_ENV') === 'prod' && !config('APP_DEBUG', false)) {
        return true; // Don't display the error
    }
    
    return false; // Use default error handler
}

/**
 * Exception handler
 */
function handleException($exception)
{
    $message = sprintf(
        'Uncaught exception: %s in %s on line %d',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    );
    
    // Log exception if logging is enabled
    if (config('LOGGING.ENABLED', false)) {
        logMessage('ERROR', $message, [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
    
    // In production, show user-friendly error
    if (config('APP_ENV') === 'prod' && !config('APP_DEBUG', false)) {
        http_response_code(500);
        echo json_encode([
            'error' => true,
            'message' => 'An internal server error occurred. Please try again later.',
            'code' => 500
        ]);
    } else {
        // Development: show detailed error
        http_response_code(500);
        echo json_encode([
            'error' => true,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace()
        ]);
    }
}

/**
 * Simple logging function
 */
function logMessage($level, $message, $context = [])
{
    if (!config('LOGGING.ENABLED', false)) {
        return;
    }
    
    $logPath = config('LOGGING.PATH');
    if (!$logPath) {
        return;
    }
    
    // Ensure log directory exists
    $logDir = dirname($logPath);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Rotate log if it's too large
    if (file_exists($logPath) && filesize($logPath) > config('LOGGING.MAX_FILE_SIZE', 2097152)) {
        $maxFiles = config('LOGGING.MAX_FILES', 5);
        
        // Rotate existing files
        for ($i = $maxFiles - 1; $i > 0; $i--) {
            $oldFile = $logPath . '.' . $i;
            $newFile = $logPath . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                if ($i === $maxFiles - 1) {
                    unlink($oldFile); // Delete oldest file
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }
        
        // Move current log to .1
        rename($logPath, $logPath . '.1');
    }
    
    // Format log entry
    $timestamp = date('Y-m-d H:i:s');
    $contextJson = !empty($context) ? ' ' . json_encode($context) : '';
    $logEntry = sprintf(
        "[%s] %s: %s%s\n",
        $timestamp,
        $level,
        $message,
        $contextJson
    );
    
    // Write to log file
    file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Get client IP address
 */
function getClientIp()
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            
            // Handle multiple IPs (take the first one)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            
            // Validate IP address
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    // Fallback to REMOTE_ADDR even if it's private/reserved
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Get user agent string
 */
function getUserAgent()
{
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send error response
 */
function errorResponse($message, $statusCode = 400, $additionalData = [])
{
    $response = array_merge([
        'error' => true,
        'message' => $message,
        'code' => $statusCode
    ], $additionalData);
    
    jsonResponse($response, $statusCode);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if request method is allowed
 */
function checkRequestMethod($allowedMethods)
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    if (!in_array($method, (array)$allowedMethods, true)) {
        errorResponse('Method not allowed', 405);
    }
    
    return $method;
}

// Set error handlers
set_error_handler('handleError');
set_exception_handler('handleException');

// Set error reporting based on environment
if (config('APP_ENV') === 'dev' || config('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
}

// Ensure required extensions are loaded
$requiredExtensions = ['curl', 'dom', 'mbstring'];
if (config('DB_DRIVER') === 'sqlite') {
    $requiredExtensions[] = 'sqlite3';
} elseif (config('DB_DRIVER') === 'mysql') {
    $requiredExtensions[] = 'pdo_mysql';
}

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        throw new Exception("Required PHP extension '$ext' is not loaded.");
    }
}

// Set memory limit if needed
if (config('APP_ENV') === 'prod') {
    ini_set('memory_limit', '64M');
}

// Initialize session if needed (for potential future features)
if (!session_id()) {
    session_start();
}

// Log application start
logMessage('INFO', 'Application initialized', [
    'php_version' => PHP_VERSION,
    'environment' => config('APP_ENV'),
    'ip' => getClientIp()
]);