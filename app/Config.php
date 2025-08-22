<?php
/**
 * Configuration Management Class
 * 
 * Provides centralized access to configuration values
 * and validation of configuration settings.
 */

class Config
{
    private static $config = [];
    private static $loaded = false;
    
    /**
     * Load configuration from file
     */
    public static function load($configFile = null)
    {
        if (self::$loaded) {
            return;
        }
        
        $configFile = $configFile ?: CONFIG_PATH;
        
        if (!file_exists($configFile)) {
            throw new Exception("Configuration file not found: {$configFile}");
        }
        
        $config = require $configFile;
        
        if (!is_array($config)) {
            throw new Exception('Configuration file must return an array');
        }
        
        self::$config = $config;
        self::$loaded = true;
        
        // Validate critical configuration
        self::validate();
    }
    
    /**
     * Get configuration value using dot notation
     */
    public static function get($key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Set configuration value
     */
    public static function set($key, $value)
    {
        if (!self::$loaded) {
            self::load();
        }
        
        $keys = explode('.', $key);
        $config = &self::$config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
    
    /**
     * Check if configuration key exists
     */
    public static function has($key)
    {
        if (!self::$loaded) {
            self::load();
        }
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return false;
            }
            $value = $value[$k];
        }
        
        return true;
    }
    
    /**
     * Get all configuration
     */
    public static function all()
    {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$config;
    }
    
    /**
     * Validate critical configuration settings
     */
    private static function validate()
    {
        $required = [
            'APP_ENV',
            'DB_DRIVER',
            'HTTP.CONNECT_TIMEOUT',
            'HTTP.TIMEOUT',
            'HTTP.MAX_BYTES',
            'HTTP.USER_AGENT',
            'RATE_LIMIT_PER_HOUR'
        ];
        
        foreach ($required as $key) {
            if (!self::has($key)) {
                throw new Exception("Required configuration key missing: {$key}");
            }
        }
        
        // Validate database configuration
        $dbDriver = self::get('DB_DRIVER');
        if (!in_array($dbDriver, ['sqlite', 'mysql'], true)) {
            throw new Exception("Invalid database driver: {$dbDriver}");
        }
        
        if ($dbDriver === 'sqlite') {
            if (!self::has('SQLITE_PATH')) {
                throw new Exception('SQLITE_PATH is required when using SQLite');
            }
        } elseif ($dbDriver === 'mysql') {
            $mysqlKeys = ['MYSQL.HOST', 'MYSQL.DB', 'MYSQL.USER', 'MYSQL.PASS'];
            foreach ($mysqlKeys as $key) {
                if (!self::has($key)) {
                    throw new Exception("Required MySQL configuration key missing: {$key}");
                }
            }
        }
        
        // Validate HTTP timeouts
        $connectTimeout = self::get('HTTP.CONNECT_TIMEOUT');
        $timeout = self::get('HTTP.TIMEOUT');
        
        if (!is_numeric($connectTimeout) || $connectTimeout <= 0) {
            throw new Exception('HTTP.CONNECT_TIMEOUT must be a positive number');
        }
        
        if (!is_numeric($timeout) || $timeout <= 0) {
            throw new Exception('HTTP.TIMEOUT must be a positive number');
        }
        
        if ($timeout < $connectTimeout) {
            throw new Exception('HTTP.TIMEOUT must be greater than or equal to HTTP.CONNECT_TIMEOUT');
        }
        
        // Validate rate limit
        $rateLimit = self::get('RATE_LIMIT_PER_HOUR');
        if (!is_numeric($rateLimit) || $rateLimit <= 0) {
            throw new Exception('RATE_LIMIT_PER_HOUR must be a positive number');
        }
        
        // Validate max bytes
        $maxBytes = self::get('HTTP.MAX_BYTES');
        if (!is_numeric($maxBytes) || $maxBytes <= 0) {
            throw new Exception('HTTP.MAX_BYTES must be a positive number');
        }
        
        // Validate cache TTL
        $cacheTtl = self::get('CACHE_TTL');
        if (!is_numeric($cacheTtl) || $cacheTtl <= 0) {
            throw new Exception('CACHE_TTL must be a positive number');
        }
        
        // Validate allowed ports
        $allowedPorts = self::get('HTTP.ALLOW_PORTS', []);
        if (!is_array($allowedPorts) || empty($allowedPorts)) {
            throw new Exception('HTTP.ALLOW_PORTS must be a non-empty array');
        }
        
        foreach ($allowedPorts as $port) {
            if (!is_numeric($port) || $port < 1 || $port > 65535) {
                throw new Exception("Invalid port number: {$port}");
            }
        }
        
        // Validate storage paths
        $paths = ['STORAGE', 'CACHE', 'LOGS'];
        foreach ($paths as $path) {
            $pathKey = "PATHS.{$path}";
            if (self::has($pathKey)) {
                $fullPath = APP_ROOT . '/' . self::get($pathKey);
                if (!is_dir($fullPath) && !mkdir($fullPath, 0755, true)) {
                    throw new Exception("Cannot create directory: {$fullPath}");
                }
                if (!is_writable($fullPath)) {
                    throw new Exception("Directory not writable: {$fullPath}");
                }
            }
        }
    }
    
    /**
     * Get environment-specific configuration
     */
    public static function env($key, $default = null)
    {
        $env = self::get('APP_ENV', 'prod');
        $envKey = strtoupper($env) . '.' . $key;
        
        if (self::has($envKey)) {
            return self::get($envKey);
        }
        
        return $default;
    }
    
    /**
     * Check if we're in development mode
     */
    public static function isDev()
    {
        return self::get('APP_ENV') === 'dev';
    }
    
    /**
     * Check if we're in production mode
     */
    public static function isProd()
    {
        return self::get('APP_ENV') === 'prod';
    }
    
    /**
     * Check if debugging is enabled
     */
    public static function isDebug()
    {
        return self::get('APP_DEBUG', false) || (self::isDev() && self::get('DEV.SHOW_ERRORS', false));
    }
    
    /**
     * Get feature flag value
     */
    public static function feature($feature, $default = false)
    {
        return self::get("FEATURES.{$feature}", $default);
    }
}