# XAMPP Installation Guide for Meta Tag Analyzer

This guide will walk you through setting up the Meta Tag Analyzer on XAMPP for local development and testing.

## Table of Contents
- [Prerequisites](#prerequisites)
- [XAMPP Installation](#xampp-installation)
- [Project Setup](#project-setup)
- [Database Configuration](#database-configuration)
- [Testing the Installation](#testing-the-installation)
- [Troubleshooting](#troubleshooting)
- [Development Workflow](#development-workflow)

## Prerequisites

- **Operating System**: Windows, macOS, or Linux
- **XAMPP Version**: 7.4.x or higher (recommended: 8.1.x or 8.2.x)
- **Available Disk Space**: At least 1GB for XAMPP + 50MB for the project
- **Administrator Rights**: Required for XAMPP installation

## XAMPP Installation

### Step 1: Download XAMPP

1. Visit the official XAMPP website: https://www.apachefriends.org/
2. Download the version that matches your operating system
3. Choose PHP 7.4 or higher (recommended: PHP 8.1 or 8.2)

### Step 2: Install XAMPP

#### On Windows:
1. Run the downloaded installer as Administrator
2. Choose installation directory (default: `C:\xampp`)
3. Select components to install:
   - ✅ Apache
   - ✅ MySQL
   - ✅ PHP
   - ✅ phpMyAdmin
   - ❌ Mercury (not needed)
   - ❌ Tomcat (not needed)
4. Complete the installation

#### On macOS:
1. Open the downloaded DMG file
2. Drag XAMPP to Applications folder
3. Open Terminal and run: `sudo /Applications/XAMPP/xamppfiles/xampp start`

#### On Linux:
1. Make the installer executable: `chmod +x xampp-linux-*-installer.run`
2. Run as root: `sudo ./xampp-linux-*-installer.run`
3. Follow the installation wizard

### Step 3: Start XAMPP Services

1. Open XAMPP Control Panel
2. Start the following services:
   - **Apache** (Web server)
   - **MySQL** (Database server - optional, only if using MySQL instead of SQLite)

## Project Setup

### Step 1: Download/Clone the Project

Place the Meta Tag Analyzer project in your XAMPP's web directory:

#### Windows:
```
C:\xampp\htdocs\meta-tag-analyzer\
```

#### macOS:
```
/Applications/XAMPP/xamppfiles/htdocs/meta-tag-analyzer/
```

#### Linux:
```
/opt/lampp/htdocs/meta-tag-analyzer/
```

### Step 2: Set Directory Permissions

#### On Windows:
No additional permissions needed if running as Administrator.

#### On macOS/Linux:
```bash
# Navigate to the project directory
cd /Applications/XAMPP/xamppfiles/htdocs/meta-tag-analyzer/

# Set proper permissions
sudo chmod -R 755 .
sudo chmod -R 777 storage/
sudo chmod -R 777 database/
sudo chown -R daemon:daemon storage/
sudo chown -R daemon:daemon database/
```

### Step 3: Verify PHP Version

1. Open your browser
2. Navigate to: `http://localhost/dashboard/`
3. Check that PHP version is 7.4 or higher
4. Verify that the following PHP extensions are enabled:
   - `curl`
   - `json`
   - `pdo`
   - `pdo_sqlite` (for SQLite)
   - `pdo_mysql` (for MySQL)
   - `dom`
   - `mbstring`

## Database Configuration

You have two options for the database: SQLite (recommended for XAMPP) or MySQL.

### Option 1: SQLite (Recommended)

SQLite is easier to set up and perfect for local development.

1. The project uses SQLite by default
2. The database file will be created automatically at: `database/app.db`
3. No additional configuration needed

### Option 2: MySQL (Advanced)

If you prefer MySQL:

1. **Start MySQL in XAMPP Control Panel**

2. **Create Database**:
   - Open phpMyAdmin: `http://localhost/phpmyadmin/`
   - Click "New" to create a database
   - Name it: `meta_tag_analyzer`
   - Set collation to: `utf8mb4_unicode_ci`

3. **Configure Database Connection**:
   Edit `.env.php` and update the database settings:
   ```php
   // Database Configuration
   'database' => [
       'type' => 'mysql',  // Change from 'sqlite' to 'mysql'
       'host' => 'localhost',
       'port' => 3306,
       'name' => 'meta_tag_analyzer',
       'username' => 'root',
       'password' => '',  // Default XAMPP MySQL password is empty
       'charset' => 'utf8mb4',
   ],
   ```

4. **Import Database Schema**:
   - In phpMyAdmin, select your database
   - Click "Import" tab
   - Choose file: `database/schema.mysql.sql`
   - Click "Go" to import

## Testing the Installation

### Step 1: Access the Application

1. Make sure Apache is running in XAMPP
2. Open your web browser
3. Navigate to: `http://localhost/meta-tag-analyzer/public/`

### Step 2: Test Basic Functionality

1. **Home Page Test**:
   - You should see the Meta Tag Analyzer interface
   - The page should load without errors

2. **URL Analysis Test**:
   - Enter a test URL (e.g., `https://example.com`)
   - Click "Analyze URL"
   - Wait for the analysis to complete
   - Verify that meta tag information is displayed

3. **API Test**:
   - Navigate to: `http://localhost/meta-tag-analyzer/public/api/analyze.php?url=https://example.com`
   - You should receive JSON response with meta tag data

### Step 3: Check Error Logs

If something doesn't work:

1. **PHP Errors**: Check `storage/logs/app.log`
2. **Apache Errors**: Check XAMPP Apache error logs
3. **Browser Console**: Check for JavaScript errors (F12)

## Troubleshooting

### Common Issues and Solutions

#### 1. "Page Not Found" Error

**Problem**: Accessing `http://localhost/meta-tag-analyzer/` shows 404 error.

**Solution**:
- Ensure the project is in the correct XAMPP directory
- Try accessing: `http://localhost/meta-tag-analyzer/public/`
- Check that Apache is running in XAMPP Control Panel

#### 2. PHP Extensions Missing

**Problem**: Error about missing PHP extensions.

**Solution**:
1. Open `C:\xampp\php\php.ini` (Windows) or equivalent
2. Find and uncomment these lines by removing the `;`:
   ```ini
   extension=curl
   extension=pdo_sqlite
   extension=pdo_mysql
   extension=mbstring
   ```
3. Restart Apache in XAMPP Control Panel

#### 3. Permission Denied Errors

**Problem**: Cannot write to database or cache files.

**Solution**:
```bash
# Make directories writable
chmod 777 storage/cache/
chmod 777 storage/logs/
chmod 777 database/
```

#### 4. Database Connection Failed

**Problem**: MySQL connection errors.

**Solution**:
1. Verify MySQL is running in XAMPP
2. Check database credentials in `.env.php`
3. Test connection in phpMyAdmin
4. Consider switching to SQLite for simplicity

#### 5. cURL SSL Certificate Issues

**Problem**: SSL certificate verification failures.

**Solution**:
1. Download `cacert.pem` from: https://curl.se/ca/cacert.pem
2. Place it in `C:\xampp\php\extras\ssl\`
3. Edit `php.ini`:
   ```ini
   curl.cainfo = "C:\xampp\php\extras\ssl\cacert.pem"
   ```
4. Restart Apache

#### 6. Memory Limit Issues

**Problem**: PHP memory limit exceeded.

**Solution**:
1. Edit `php.ini`
2. Increase memory limit:
   ```ini
   memory_limit = 256M
   ```
3. Restart Apache

### Getting Help

If you encounter issues not covered here:

1. **Check Logs**: Always check `storage/logs/app.log` first
2. **XAMPP Forums**: Visit XAMPP community forums
3. **PHP Documentation**: Check PHP.net for extension issues
4. **Project Issues**: Create an issue in the project repository

## Development Workflow

### Recommended Development Setup

1. **Use SQLite**: Easier for development, no MySQL setup required
2. **Enable Error Reporting**: Set `APP_DEBUG=true` in `.env.php`
3. **Monitor Logs**: Keep `storage/logs/app.log` open in a text editor
4. **Browser Dev Tools**: Use F12 to monitor network requests and errors

### Making Changes

1. **Edit Files**: Use your preferred code editor
2. **Test Changes**: Refresh browser to see changes
3. **Check Logs**: Monitor for any new errors
4. **Clear Cache**: Delete files in `storage/cache/` if needed

### Backup Your Work

Before making significant changes:

1. **Backup Database**:
   ```bash
   # For SQLite
   cp database/app.db database/app.db.backup
   
   # For MySQL - export via phpMyAdmin
   ```

2. **Backup Configuration**:
   ```bash
   cp .env.php .env.php.backup
   ```

## Performance Tips

### For Better Performance on XAMPP

1. **Increase PHP Limits** in `php.ini`:
   ```ini
   max_execution_time = 60
   max_input_time = 60
   memory_limit = 256M
   post_max_size = 8M
   upload_max_filesize = 2M
   ```

2. **Enable OPcache** in `php.ini`:
   ```ini
   opcache.enable=1
   opcache.memory_consumption=128
   opcache.max_accelerated_files=4000
   ```

3. **Optimize Apache** in `httpd.conf`:
   ```apache
   # Enable compression
   LoadModule deflate_module modules/mod_deflate.so
   
   # Enable expires headers
   LoadModule expires_module modules/mod_expires.so
   ```

## Security Considerations

When using XAMPP for development:

1. **Never use XAMPP in production**
2. **Change default MySQL password**:
   ```sql
   SET PASSWORD FOR 'root'@'localhost' = PASSWORD('your_password');
   ```
3. **Restrict network access** if not needed
4. **Keep XAMPP updated** to latest version
5. **Use firewall** to block unnecessary ports

## Network Access

To allow others to access your XAMPP installation:

1. **Configure Apache**:
   - Edit `httpd.conf`
   - Change `Listen 127.0.0.1:80` to `Listen 80`

2. **Configure Firewall**:
   - Allow port 80 through Windows Firewall
   - Or access via: `http://your-ip-address/meta-tag-analyzer/public/`

3. **Find Your IP Address**:
   ```bash
   # Windows
   ipconfig
   
   # macOS/Linux
   ifconfig
   ```

## Conclusion

You should now have a fully functional Meta Tag Analyzer running on XAMPP! The application provides a robust platform for analyzing website meta tags and SEO data.

For production deployment, consider using a proper web hosting service with PHP 7.4+ support and follow the main installation guide in `README.md`.

Happy analyzing! 🚀