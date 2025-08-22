# Meta Tag Analyzer - XAMPP Installation Guide

This guide will walk you through setting up the Meta Tag Analyzer on XAMPP for local development and testing.

## 📋 Prerequisites

### XAMPP Requirements
- **XAMPP Version**: 7.4.x, 8.0.x, 8.1.x, or 8.2.x
- **Operating System**: Windows, macOS, or Linux
- **Available Space**: At least 100 MB free space

### Download XAMPP
If you don't have XAMPP installed:
1. Visit [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Download XAMPP for your operating system
3. Install XAMPP following the standard installation process

## 🚀 Quick Installation

### Step 1: Start XAMPP Services
1. Open **XAMPP Control Panel**
2. Start the following services:
   - ✅ **Apache** (Required)
   - ✅ **MySQL** (Optional - only if you want to use MySQL instead of SQLite)

![XAMPP Control Panel](https://via.placeholder.com/600x300/28a745/ffffff?text=Start+Apache+%26+MySQL)

### Step 2: Download the Project
1. **Option A: Git Clone**
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/yourusername/meta-tag-analyzer.git
   ```

2. **Option B: Manual Download**
   - Download the project files
   - Extract to `C:\xampp\htdocs\meta-tag-analyzer\`

### Step 3: Directory Setup
Your XAMPP directory structure should look like this:

```
C:\xampp\htdocs\meta-tag-analyzer\
├── public/              # ← Web-accessible files
├── app/                 # ← Application logic
├── storage/             # ← Data storage
├── database/            # ← Database schemas
├── tests/               # ← Test files
├── .env.php.example     # ← Configuration template
└── README.md
```

### Step 4: Configuration
1. **Copy configuration file:**
   ```bash
   cd C:\xampp\htdocs\meta-tag-analyzer
   copy .env.php.example .env.php
   ```

2. **Edit configuration** (open `.env.php` in your text editor):
   ```php
   <?php
   return [
       'APP_ENV' => 'dev',              // Set to 'dev' for development
       'APP_DEBUG' => true,             // Enable debug mode
       'BASE_URL' => 'http://localhost/meta-tag-analyzer/public',
       
       // Database (SQLite is recommended for XAMPP)
       'DB_DRIVER' => 'sqlite',
       'SQLITE_PATH' => __DIR__ . '/storage/meta.sqlite',
       
       // ... rest of configuration
   ];
   ```

### Step 5: Set Permissions (Windows)
1. Right-click on the `storage` folder
2. Select **Properties** → **Security**
3. Ensure **Full Control** for your user account
4. Apply to all subfolders

### Step 6: Test Installation
1. Open your web browser
2. Navigate to: `http://localhost/meta-tag-analyzer/public/test.php`
3. Verify all tests pass (should see green checkmarks ✅)
4. **Important:** Delete `test.php` after testing

### Step 7: Access the Application
Visit: `http://localhost/meta-tag-analyzer/public/`

🎉 **You're ready to start analyzing URLs!**

---

## 🔧 Detailed Installation Steps

### For Windows Users

#### 1. Install XAMPP
```powershell
# Download from https://www.apachefriends.org/
# Run the installer as Administrator
# Default installation path: C:\xampp\
```

#### 2. Configure XAMPP
1. **Open XAMPP Control Panel as Administrator**
2. **Start Apache:**
   - Click "Start" next to Apache
   - Default port: 80 (if port 80 is busy, change to 8080)
3. **Start MySQL (optional):**
   - Click "Start" next to MySQL
   - Default port: 3306

#### 3. Download Project
```cmd
# Open Command Prompt as Administrator
cd C:\xampp\htdocs
git clone https://github.com/yourusername/meta-tag-analyzer.git
# OR extract downloaded ZIP file here
```

#### 4. Configure Project
```cmd
cd meta-tag-analyzer
copy .env.php.example .env.php
notepad .env.php
```

**Edit these key settings:**
```php
'APP_ENV' => 'dev',
'APP_DEBUG' => true,
'BASE_URL' => 'http://localhost/meta-tag-analyzer/public',
'DB_DRIVER' => 'sqlite',  // Recommended for XAMPP
```

#### 5. Set Folder Permissions
```cmd
# Make storage directory writable
icacls storage /grant Users:F /T
icacls storage\cache /grant Users:F /T
icacls storage\logs /grant Users:F /T
```

### For macOS Users

#### 1. Install XAMPP
```bash
# Download XAMPP for macOS
# Install to /Applications/XAMPP/
```

#### 2. Start Services
```bash
sudo /Applications/XAMPP/xamppfiles/xampp start
# Or use XAMPP Control Panel
```

#### 3. Download Project
```bash
cd /Applications/XAMPP/xamppfiles/htdocs
git clone https://github.com/yourusername/meta-tag-analyzer.git
```

#### 4. Configure Project
```bash
cd meta-tag-analyzer
cp .env.php.example .env.php
nano .env.php
```

**Update configuration:**
```php
'BASE_URL' => 'http://localhost/meta-tag-analyzer/public',
```

#### 5. Set Permissions
```bash
chmod 755 storage/
chmod 755 storage/cache/
chmod 755 storage/logs/
chmod 644 .env.php
```

### For Linux Users

#### 1. Install XAMPP
```bash
# Download XAMPP for Linux
wget https://www.apachefriends.org/xampp-files/8.2.0/xampp-linux-x64-8.2.0-0-installer.run
chmod +x xampp-linux-x64-8.2.0-0-installer.run
sudo ./xampp-linux-x64-8.2.0-0-installer.run
```

#### 2. Start Services
```bash
sudo /opt/lampp/lampp start
```

#### 3. Setup Project
```bash
cd /opt/lampp/htdocs
sudo git clone https://github.com/yourusername/meta-tag-analyzer.git
sudo chown -R daemon:daemon meta-tag-analyzer/
```

#### 4. Configure
```bash
cd meta-tag-analyzer
sudo cp .env.php.example .env.php
sudo nano .env.php
```

#### 5. Set Permissions
```bash
sudo chmod 755 storage/
sudo chmod 755 storage/cache/
sudo chmod 755 storage/logs/
sudo chmod 644 .env.php
```

---

## 🗄️ Database Configuration

### Option 1: SQLite (Recommended for XAMPP)

**Advantages:**
- ✅ No additional setup required
- ✅ Perfect for development
- ✅ Automatically created
- ✅ No MySQL service needed

**Configuration:**
```php
// In .env.php
'DB_DRIVER' => 'sqlite',
'SQLITE_PATH' => __DIR__ . '/storage/meta.sqlite',
```

### Option 2: MySQL (Optional)

**When to use:**
- Testing MySQL compatibility
- Learning database administration
- Preparing for production deployment

**Setup Steps:**

1. **Start MySQL in XAMPP**
2. **Create Database:**
   ```sql
   -- Open http://localhost/phpmyadmin
   CREATE DATABASE metataganalyzer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Import Schema:**
   - In phpMyAdmin, select the database
   - Go to "Import" tab
   - Upload `database/schema.mysql.sql`

4. **Update Configuration:**
   ```php
   // In .env.php
   'DB_DRIVER' => 'mysql',
   'MYSQL' => [
       'HOST' => 'localhost',
       'DB' => 'metataganalyzer',
       'USER' => 'root',
       'PASS' => '',  // Empty for XAMPP default
   ],
   ```

---

## 🧪 Testing Your Installation

### 1. Run Installation Test
Visit: `http://localhost/meta-tag-analyzer/public/test.php`

**Expected Results:**
- ✅ Configuration loaded successfully
- ✅ Database connection successful
- ✅ Cache system working
- ✅ HTTP Client initialized
- ✅ URL Validation tests pass
- ✅ File permissions correct
- ✅ PHP extensions loaded

### 2. Test the Application
1. **Visit Home Page:**
   `http://localhost/meta-tag-analyzer/public/`

2. **Analyze a Test URL:**
   - Enter: `https://example.com`
   - Click "Analyze URL"
   - Verify results display correctly

3. **Test API Endpoint:**
   `http://localhost/meta-tag-analyzer/public/api/analyze?url=https://example.com`

4. **Test Export Functions:**
   - Analyze a URL
   - Click "JSON" and "CSV" export buttons
   - Verify files download

### 3. Test with Sample Page
Use the included test page:
`http://localhost/meta-tag-analyzer/tests/sample-pages/complete-test.html`

---

## 🔧 Troubleshooting

### Common Issues and Solutions

#### 🚨 "Apache failed to start"
**Cause:** Port 80 is already in use

**Solutions:**
1. **Change Apache port:**
   - XAMPP Control Panel → Apache → Config → httpd.conf
   - Change `Listen 80` to `Listen 8080`
   - Restart Apache
   - Access via: `http://localhost:8080/meta-tag-analyzer/public/`

2. **Stop conflicting services:**
   ```cmd
   # Windows: Stop IIS or other web servers
   net stop was /y
   net stop iisadmin /y
   ```

#### 🚨 "Configuration file not found"
**Cause:** `.env.php` file missing

**Solution:**
```bash
cd C:\xampp\htdocs\meta-tag-analyzer
copy .env.php.example .env.php
```

#### 🚨 "Database connection failed"
**Cause:** SQLite file permissions or MySQL not running

**Solutions:**
1. **For SQLite:**
   ```cmd
   # Windows
   icacls storage /grant Users:F /T
   ```

2. **For MySQL:**
   - Start MySQL in XAMPP Control Panel
   - Verify database exists in phpMyAdmin

#### 🚨 "Permission denied" errors
**Cause:** Storage directory not writable

**Solution:**
```bash
# Windows
icacls storage /grant Users:F /T

# macOS/Linux
chmod 755 storage/
chmod 755 storage/cache/
chmod 755 storage/logs/
```

#### 🚨 "Class not found" errors
**Cause:** File paths or autoloading issues

**Solution:**
1. Verify all files are in correct locations
2. Check that `app/bootstrap.php` exists
3. Ensure proper file permissions

#### 🚨 "cURL error" when analyzing URLs
**Cause:** cURL extension not enabled or firewall blocking

**Solutions:**
1. **Enable cURL in PHP:**
   - XAMPP Control Panel → Apache → Config → PHP (php.ini)
   - Uncomment: `extension=curl`
   - Restart Apache

2. **Check firewall settings:**
   - Allow XAMPP through Windows Firewall
   - Temporarily disable antivirus to test

### Performance Optimization for XAMPP

#### 1. PHP Configuration
Edit `C:\xampp\php\php.ini`:
```ini
# Increase memory limit
memory_limit = 128M

# Set timezone
date.timezone = "America/New_York"

# Enable error reporting for development
display_errors = On
error_reporting = E_ALL

# Optimize for development
max_execution_time = 60
max_input_time = 60
```

#### 2. Apache Configuration
Edit `C:\xampp\apache\conf\httpd.conf`:
```apache
# Enable mod_rewrite (should be enabled by default)
LoadModule rewrite_module modules/mod_rewrite.so

# Enable .htaccess
AllowOverride All
```

---

## 🌐 Accessing from Other Devices

### Local Network Access

To access from other devices on your network:

1. **Find your IP address:**
   ```cmd
   # Windows
   ipconfig
   
   # macOS/Linux
   ifconfig
   ```

2. **Update configuration:**
   ```php
   // In .env.php
   'BASE_URL' => 'http://192.168.1.100/meta-tag-analyzer/public',
   ```

3. **Configure Apache:**
   Edit `C:\xampp\apache\conf\extra\httpd-xampp.conf`
   ```apache
   # Allow access from network
   <LocationMatch "^/(?i:(?:xampp|security|licenses|phpmyadmin|webalizer|server-status|server-info))">
       Require local
       Require ip 192.168.1  # Allow your network
   </LocationMatch>
   ```

4. **Access from other devices:**
   `http://192.168.1.100/meta-tag-analyzer/public/`

---

## 🔄 Development Workflow

### Recommended Development Setup

1. **Enable Development Mode:**
   ```php
   // In .env.php
   'APP_ENV' => 'dev',
   'APP_DEBUG' => true,
   'DEV' => [
       'SHOW_ERRORS' => true,
       'LOG_QUERIES' => true,
       'DISABLE_CACHE' => false,
   ],
   ```

2. **Use Live Reload (Optional):**
   - Install browser extension for auto-refresh
   - Or use tools like BrowserSync

3. **Monitor Logs:**
   ```bash
   # Windows
   tail -f C:\xampp\htdocs\meta-tag-analyzer\storage\logs\app.log
   
   # Or use a log viewer application
   ```

4. **Test API with Postman/curl:**
   ```bash
   # Test API endpoint
   curl "http://localhost/meta-tag-analyzer/public/api/analyze?url=https://example.com"
   ```

### Version Control with Git

```bash
# Initialize git repository (if not already done)
cd C:\xampp\htdocs\meta-tag-analyzer
git init
git add .
git commit -m "Initial XAMPP setup"

# Create development branch
git checkout -b development

# Make changes and commit
git add .
git commit -m "Feature: Added new functionality"
```

---

## 📚 Additional Resources

### XAMPP Documentation
- [Official XAMPP Documentation](https://www.apachefriends.org/documentation.html)
- [XAMPP FAQ](https://www.apachefriends.org/faq.html)

### Project Documentation
- [Main README](README.md) - Complete project documentation
- [API Documentation](README.md#api-reference) - RESTful API details
- [Security Guide](README.md#security-best-practices) - Security best practices

### Helpful Tools for Development
- **Code Editor:** VS Code, PhpStorm, Sublime Text
- **API Testing:** Postman, Insomnia
- **Database Management:** phpMyAdmin (included with XAMPP)
- **Git Client:** GitHub Desktop, SourceTree

---

## 🎯 Next Steps

After successful installation:

1. **Explore the Application:**
   - Test with various URLs
   - Try different export formats
   - Experiment with the API

2. **Customize for Your Needs:**
   - Modify the UI in `app/Views/`
   - Adjust analysis parameters in `.env.php`
   - Add custom features in `app/`

3. **Deploy to Production:**
   - Follow the [main README](README.md) for production deployment
   - Use a proper hosting provider
   - Configure SSL certificates

4. **Contribute:**
   - Report bugs or suggest features
   - Submit pull requests
   - Share your customizations

---

**🎉 Congratulations! Your Meta Tag Analyzer is now running on XAMPP!**

For additional help, refer to the [main documentation](README.md) or create an issue in the project repository.