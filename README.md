# Meta Tag Analyzer

A lightweight, secure PHP web application for analyzing SEO meta data, Open Graph tags, Twitter cards, and more from any public URL. Built specifically for shared hosting compatibility with comprehensive security measures.

![Meta Tag Analyzer](public/assets/img/favicon.svg)

## 🚀 Features

### SEO Analysis
- **Meta Tags**: Extract title, description, keywords, robots directives, viewport settings
- **Open Graph**: Analyze OG properties for social media sharing optimization
- **Twitter Cards**: Extract Twitter Card meta data for rich media previews
- **Schema.org**: Detect structured data markup (JSON-LD and Microdata)
- **Content Structure**: Analyze heading hierarchy (H1-H3) and word count
- **Technical SEO**: Canonical URLs, hreflang links, favicon detection

### Security & Performance
- **SSRF Protection**: Comprehensive security against Server-Side Request Forgery
- **Rate Limiting**: IP-based request limiting with configurable thresholds
- **Caching System**: File-based caching with TTL support for improved performance
- **Input Validation**: Strict validation and sanitization of all user inputs
- **Security Headers**: Full set of security headers via .htaccess

### Export & API
- **Multiple Formats**: Export results in JSON and CSV formats
- **RESTful API**: Programmatic access with JSON responses
- **Data Portability**: Download analysis results for further processing

### User Experience
- **Responsive Design**: Mobile-first responsive interface using Bootstrap 5
- **Real-time Validation**: Client-side URL validation with user feedback
- **Dark Mode Support**: Optional dark mode toggle for better accessibility
- **Progressive Enhancement**: Works without JavaScript, enhanced with it

## 📋 Requirements

### Server Requirements
- **PHP**: 7.4 - 8.3 (8.1+ recommended)
- **Web Server**: Apache with mod_rewrite support
- **Database**: SQLite 3 (default) or MySQL 5.7+/MariaDB 10.3+

### PHP Extensions
- `curl` - For HTTP requests
- `dom` - For HTML parsing
- `mbstring` - For multi-byte string handling
- `sqlite3` - For SQLite database (if using SQLite)
- `pdo_mysql` - For MySQL database (if using MySQL)
- `json` - For JSON processing
- `openssl` - For secure HTTP requests

### Hosting Compatibility
✅ **Shared Hosting Ready**
- No root access required
- No Composer dependencies
- Standard cPanel/Plesk compatible
- Works with most hosting providers

## 🛠️ Installation

### Quick Installation

1. **Download and Extract**
   ```bash
   # Clone or download the repository
   git clone https://github.com/yourusername/meta-tag-analyzer.git
   cd meta-tag-analyzer
   ```

2. **Upload to Web Server**
   - Upload the `public/` directory contents to your web root (e.g., `public_html/`, `www/`, `htdocs/`)
   - Upload all other directories (`app/`, `storage/`, `database/`) to a location outside the web root for security

3. **Configure Application**
   ```bash
   # Copy and edit configuration
   cp .env.php.example .env.php
   nano .env.php  # Edit configuration settings
   ```

4. **Set Permissions**
   ```bash
   # Make storage directories writable
   chmod 755 storage/
   chmod 755 storage/cache/
   chmod 755 storage/logs/
   chmod 644 .env.php
   ```

5. **Test Installation**
   - Visit your domain in a web browser
   - Try analyzing a URL (e.g., https://example.com)

### Manual Installation

If you need to set up the directory structure manually:

```
your-domain.com/
├── public/              # ← Web root (public_html/, www/, etc.)
│   ├── index.php
│   ├── analyze.php
│   ├── export.php
│   ├── api/
│   ├── assets/
│   ├── .htaccess
│   └── robots.txt
├── app/                 # ← Outside web root
├── storage/             # ← Outside web root
├── database/            # ← Outside web root
└── .env.php            # ← Outside web root
```

## ⚙️ Configuration

### Environment Configuration

Edit `.env.php` to configure your installation:

```php
<?php
return [
    // Application Environment
    'APP_ENV' => 'prod',           // 'dev' or 'prod'
    'APP_DEBUG' => false,          // Enable debug mode
    'BASE_URL' => 'https://yourdomain.com',
    
    // Database Configuration
    'DB_DRIVER' => 'sqlite',       // 'sqlite' or 'mysql'
    'SQLITE_PATH' => __DIR__ . '/storage/meta.sqlite',
    
    // MySQL Configuration (if using MySQL)
    'MYSQL' => [
        'HOST' => 'localhost',
        'DB' => 'metataganalyzer',
        'USER' => 'dbuser',
        'PASS' => 'dbpass',
    ],
    
    // Security & Performance
    'RATE_LIMIT_PER_HOUR' => 30,  // Requests per hour per IP
    'CACHE_TTL' => 21600,          // Cache TTL in seconds (6 hours)
    
    // HTTP Client Settings
    'HTTP' => [
        'TIMEOUT' => 12,           // Request timeout in seconds
        'MAX_BYTES' => 2000000,    // Max content size (2MB)
        'USER_AGENT' => 'MetaTagAnalyzer/1.0 (+https://yourdomain.com)',
    ],
];
```

### Database Setup

#### SQLite (Default - Recommended for Shared Hosting)
```bash
# SQLite database will be created automatically
# Ensure storage/ directory is writable
chmod 755 storage/
```

#### MySQL (Optional)
```sql
-- Create database and user
CREATE DATABASE metataganalyzer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'metauser'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON metataganalyzer.* TO 'metauser'@'localhost';
FLUSH PRIVILEGES;

-- Import schema
mysql -u metauser -p metataganalyzer < database/schema.mysql.sql
```

### Web Server Configuration

#### Apache (.htaccess included)
The included `.htaccess` file provides:
- Security headers
- Access controls
- Caching rules
- Compression
- URL rewriting

#### Nginx (if needed)
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/public;
    index index.php;
    
    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
    add_header X-XSS-Protection "1; mode=block";
    
    # Block sensitive directories
    location ~ ^/(app|storage|database)/ {
        deny all;
        return 404;
    }
    
    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    
    # Static files caching
    location ~* \.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

## 📚 Usage

### Web Interface

1. **Navigate to your domain**
2. **Enter a URL** in the analysis form
3. **Click "Analyze URL"** to start analysis
4. **View comprehensive results** including:
   - Meta tags and SEO data
   - Open Graph properties
   - Twitter Card information
   - Content structure analysis
   - Performance metrics

### API Usage

#### Analyze URL
```bash
# Basic analysis
curl "https://yourdomain.com/api/analyze?url=https://example.com"

# With options
curl "https://yourdomain.com/api/analyze?url=https://example.com&bypass_cache=1&include_raw_html=1"
```

#### Response Format
```json
{
  "success": true,
  "data": {
    "meta": {
      "title": "Example Domain",
      "description": "This domain is for use in illustrative examples",
      "robots": "index, follow"
    },
    "open_graph": {
      "title": "Example Domain",
      "type": "website",
      "url": "https://example.com"
    },
    "twitter_card": {
      "card": "summary",
      "title": "Example Domain"
    },
    "headings": {
      "h1": ["Example Domain"],
      "h2": [],
      "h3": []
    },
    "word_count": 123,
    "analysis_meta": {
      "analysis_time_ms": 45
    }
  },
  "meta": {
    "url": "https://example.com",
    "final_url": "https://example.com",
    "cache_hit": false,
    "processing_time_ms": 287,
    "fetched_at": "2024-01-01T12:00:00+00:00"
  }
}
```

### Export Data

#### Via Web Interface
- Click "JSON" or "CSV" buttons on results page
- Files download automatically with proper formatting

#### Via API
```bash
# Export as JSON
curl "https://yourdomain.com/export.php?url=https://example.com&format=json" -o analysis.json

# Export as CSV
curl "https://yourdomain.com/export.php?url=https://example.com&format=csv" -o analysis.csv
```

## 🔧 Advanced Configuration

### Security Settings

#### Rate Limiting
```php
// In .env.php
'RATE_LIMIT_PER_HOUR' => 30,        // Requests per hour
'RATE_LIMIT_WHITELIST' => [         // IP whitelist
    '192.168.1.100',
    '10.0.0.0/8'
],
```

#### SSRF Protection
```php
// In .env.php
'SECURITY' => [
    'BLOCKED_IP_RANGES' => [
        '127.0.0.0/8',      // Loopback
        '10.0.0.0/8',       // Private Class A
        '172.16.0.0/12',    // Private Class B
        '192.168.0.0/16',   // Private Class C
    ],
    'ALLOWED_SCHEMES' => ['http', 'https'],
],
```

#### Content Security Policy
```php
// In .env.php
'SECURITY' => [
    'CSP_HEADER' => "default-src 'self' https: data: 'unsafe-inline';",
],
```

### Caching Configuration

```php
// In .env.php
'CACHE_TTL' => 21600,              // 6 hours
'CACHE_MAX_SIZE' => 524288,        // 512 KB per file
'CACHE_PATH' => __DIR__ . '/storage/cache',
```

### Logging Configuration

```php
// In .env.php
'LOGGING' => [
    'ENABLED' => true,
    'LEVEL' => 'INFO',             // DEBUG, INFO, WARN, ERROR
    'PATH' => __DIR__ . '/storage/logs/app.log',
    'MAX_FILE_SIZE' => 2097152,    // 2 MB
    'MAX_FILES' => 5,              // Keep 5 rotated files
],
```

## 🚀 Performance Optimization

### Caching Strategy
- **File-based caching** for analysis results
- **HTTP caching headers** for static assets
- **Database query optimization** with proper indexing

### Shared Hosting Optimization
```php
// Optimize for shared hosting in .env.php
'HTTP' => [
    'TIMEOUT' => 8,                // Shorter timeout
    'MAX_BYTES' => 1000000,        // 1MB limit
],
'CACHE_TTL' => 43200,              // 12 hours
'RATE_LIMIT_PER_HOUR' => 20,       // Lower rate limit
```

### Memory Management
```apache
# In .htaccess (if supported by host)
php_value memory_limit 64M
php_value max_execution_time 30
php_value max_input_time 30
```

## 🔒 Security Best Practices

### File Permissions
```bash
# Recommended permissions
chmod 644 .env.php
chmod 644 public/.htaccess
chmod 755 storage/
chmod 755 storage/cache/
chmod 755 storage/logs/
chmod 644 app/*.php
```

### Security Checklist
- ✅ Configure `.env.php` outside web root
- ✅ Set proper file permissions
- ✅ Enable HTTPS (recommended)
- ✅ Regularly update PHP version
- ✅ Monitor logs for suspicious activity
- ✅ Use strong database passwords
- ✅ Enable rate limiting
- ✅ Keep backups of configuration

### Monitoring
```bash
# Monitor application logs
tail -f storage/logs/app.log

# Check for blocked requests
grep "SSRF\|Rate limit" storage/logs/app.log
```

## 🧪 Testing

### Test URLs
```bash
# Test with various URL types
curl "https://yourdomain.com/api/analyze?url=https://github.com"
curl "https://yourdomain.com/api/analyze?url=https://www.wikipedia.org"
curl "https://yourdomain.com/api/analyze?url=https://developer.mozilla.org"

# Test security (should be blocked)
curl "https://yourdomain.com/api/analyze?url=http://127.0.0.1"
curl "https://yourdomain.com/api/analyze?url=http://10.0.0.1"
```

### Performance Testing
```bash
# Test response times
time curl "https://yourdomain.com/api/analyze?url=https://example.com"

# Test rate limiting
for i in {1..35}; do
  curl "https://yourdomain.com/api/analyze?url=https://example.com"
done
```

## 🐛 Troubleshooting

### Common Issues

#### "Database connection failed"
```bash
# Check database file permissions
ls -la storage/meta.sqlite
chmod 644 storage/meta.sqlite

# Check directory permissions
chmod 755 storage/
```

#### "Configuration file not found"
```bash
# Ensure .env.php exists and is readable
ls -la .env.php
cp .env.php.example .env.php
```

#### "Rate limit exceeded"
```php
// Temporarily disable rate limiting in .env.php
'FEATURES' => [
    'ENABLE_RATE_LIMITING' => false,
],
```

#### "SSRF validation failed"
```bash
# Check if URL resolves to private IP
nslookup example.com

# Test with public domain
curl "https://yourdomain.com/api/analyze?url=https://www.google.com"
```

### Debug Mode
```php
// Enable debug mode in .env.php
'APP_ENV' => 'dev',
'APP_DEBUG' => true,
'DEV' => [
    'SHOW_ERRORS' => true,
    'LOG_QUERIES' => true,
],
```

### Log Analysis
```bash
# View recent errors
tail -n 50 storage/logs/app.log | grep ERROR

# Monitor real-time activity
tail -f storage/logs/app.log

# Check disk usage
du -sh storage/
```

## 📖 API Reference

### Endpoints

#### `GET /api/analyze`
Analyze a URL and return comprehensive meta data.

**Parameters:**
- `url` (required): The URL to analyze
- `bypass_cache` (optional): Set to `1` to bypass cache
- `include_raw_html` (optional): Set to `1` to include raw HTML
- `format` (optional): Response format (`json`)

**Response:**
```json
{
  "success": true,
  "data": { /* analysis results */ },
  "meta": { /* request metadata */ }
}
```

#### `GET /export.php`
Export analysis results in various formats.

**Parameters:**
- `url` (required): The URL to export data for
- `format` (required): Export format (`json`, `csv`)
- `filename` (optional): Custom filename

**Response:** File download

### Error Responses
```json
{
  "error": true,
  "message": "Error description",
  "code": 400
}
```

### Rate Limiting Headers
```
X-RateLimit-Remaining: 25
X-RateLimit-Reset: 1640995200
```

## 🤝 Contributing

### Development Setup
```bash
# Clone repository
git clone https://github.com/yourusername/meta-tag-analyzer.git
cd meta-tag-analyzer

# Set up development environment
cp .env.php.example .env.php
# Edit .env.php for development

# Set development mode
# In .env.php:
'APP_ENV' => 'dev',
'APP_DEBUG' => true,
```

### Code Style
- Follow PSR-12 coding standards
- Use meaningful variable names
- Add comments for complex logic
- Include PHPDoc blocks for functions

### Testing
```bash
# Test various scenarios
php -S localhost:8000 -t public/
# Visit http://localhost:8000

# Test API endpoints
curl "http://localhost:8000/api/analyze?url=https://example.com"
```

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- [Bootstrap](https://getbootstrap.com/) for the responsive UI framework
- [Bootstrap Icons](https://icons.getbootstrap.com/) for the icon set
- The PHP community for excellent documentation and libraries

## 📞 Support

- **Documentation**: This README file
- **Issues**: GitHub Issues (if applicable)
- **Security**: Report security issues privately

## 🔄 Updates

### Version 1.0.0 (Current)
- Initial release
- Complete SEO analysis suite
- SSRF protection
- Rate limiting
- Export functionality
- Shared hosting compatibility

### Roadmap
- [ ] Bulk URL analysis
- [ ] Historical analysis tracking
- [ ] Advanced performance metrics
- [ ] Custom analysis rules
- [ ] WordPress plugin version

---

**Built with ❤️ for the SEO and web development community**