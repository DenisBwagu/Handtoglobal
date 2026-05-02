# HandToGlobal Installation Guide

## 🚀 Quick Installation

### Prerequisites
- PHP 7.4+ with MySQLi/PDO extensions
- MySQL 5.7+ or MariaDB 10.2+
- Web server (Apache, Nginx, or XAMPP)
- Basic knowledge of web development

### Step 1: Database Setup

1. **Create Database**
   ```sql
   CREATE DATABASE handtoglobal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import Database Schema**
   - Option 1: Import `db.sql` file via phpMyAdmin
   - Option 2: Run `create_tables.php` in your browser
   - Option 3: Use `create_fresh_db.php` for a complete fresh setup

### Step 2: Configuration

1. **Copy Configuration File**
   ```bash
   cp config.example.php config.php
   ```

2. **Edit `config.php`**
   ```php
   // Database Configuration
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'handtoglobal');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   
   // Site Configuration
   define('SITE_NAME', 'HandToGlobal');
   define('SITE_URL', 'http://localhost/handtoglobal');
   ```

### Step 3: Web Server Setup

#### Apache Configuration
```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/handtoglobal"
    ServerName handtoglobal.local
    
    <Directory "C:/xampp/htdocs/handtoglobal">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name handtoglobal.local;
    root /path/to/handtoglobal;
    index index.php index.html;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Step 4: File Permissions

```bash
# Set proper permissions (Linux/Mac)
chmod 755 /path/to/handtoglobal
chmod 644 /path/to/handtoglobal/*.php
chmod 755 /path/to/handtoglobal/admin
```

### Step 5: Access the Platform

1. **User Interface**: `http://localhost/handtoglobal`
2. **Admin Panel**: `http://localhost/handtoglobal/admin`
3. **Default Admin Login**:
   - Email: `admin@handtoglobal.com`
   - Password: `password`

## 📋 Detailed Setup Instructions

### Database Setup Options

#### Option 1: Using phpMyAdmin
1. Open phpMyAdmin
2. Create new database named `handtoglobal`
3. Import the `db.sql` file
4. Verify all tables are created

#### Option 2: Using PHP Scripts
1. Navigate to `http://localhost/handtoglobal/create_tables.php`
2. Follow the on-screen instructions
3. Verify database creation

#### Option 3: Manual Setup
1. Run the SQL commands from `create_tables.php` manually
2. Insert initial data using the provided scripts

### Configuration Details

#### Required PHP Extensions
- `mysqli` or `pdo_mysql`
- `session`
- `json`
- `mbstring`
- `openssl` (for password hashing)

#### Important Config.php Settings
```php
// Database connection
define('DB_HOST', 'localhost');
define('DB_NAME', 'handtoglobal');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Application settings
define('SITE_NAME', 'HandToGlobal');
define('SITE_URL', 'http://yourdomain.com');
define('ADMIN_EMAIL', 'admin@handtoglobal.com');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);

// Level thresholds
define('BRONZE_THRESHOLD', 100);
define('SILVER_THRESHOLD', 150);
define('GOLD_THRESHOLD', 250);
define('PLATINUM_THRESHOLD', 500);
```

### Testing the Installation

#### 1. Database Connection Test
Create a test file `test_db.php`:
```php
<?php
require_once 'config.php';
try {
    $conn = getConnection();
    echo "Database connection successful!";
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage();
}
?>
```

#### 2. Admin Access Test
1. Navigate to `http://localhost/handtoglobal/admin_login.php`
2. Login with default credentials
3. Verify dashboard loads correctly

#### 3. User Registration Test
1. Navigate to `http://localhost/handtoglobal/login.php`
2. Click "Register"
3. Fill out registration form
4. Verify successful registration

## 🔧 Common Issues & Solutions

### Database Connection Issues
**Problem**: "Database connection failed"
**Solution**: 
- Verify database credentials in `config.php`
- Check if MySQL service is running
- Ensure database exists

### Permission Issues
**Problem**: "Permission denied" errors
**Solution**:
- Set proper file permissions (755 for directories, 644 for files)
- Check web server user permissions

### Session Issues
**Problem**: Login not working
**Solution**:
- Ensure session directory is writable
- Check PHP session settings
- Verify cookie settings

### Admin Access Issues
**Problem**: Cannot access admin panel
**Solution**:
- Verify admin credentials in database
- Check `isAdminLoggedIn()` function
- Ensure proper session management

## 🚀 Production Deployment

### Security Checklist
- [ ] Change default admin password
- [ ] Set up SSL/HTTPS
- [ ] Configure firewall rules
- [ ] Set up regular backups
- [ ] Monitor error logs
- [ ] Update dependencies

### Performance Optimization
- [ ] Enable database caching
- [ ] Optimize database queries
- [ ] Implement CDN for static assets
- [ ] Set up monitoring
- [ ] Configure gzip compression

### Backup Strategy
```bash
# Database backup
mysqldump -u username -p handtoglobal > backup.sql

# File backup
tar -czf handtoglobal_backup.tar.gz /path/to/handtoglobal/
```

## 📞 Support

If you encounter issues during installation:

1. **Check the logs**: Look for error messages in server logs
2. **Verify configuration**: Ensure all settings in `config.php` are correct
3. **Test database connection**: Use the provided test script
4. **Check permissions**: Ensure proper file and directory permissions
5. **Review requirements**: Verify all prerequisites are met

## 🔄 Updates and Maintenance

### Updating the Platform
1. Backup current installation
2. Download new version
3. Update database schema if needed
4. Replace files (except config.php)
5. Test functionality

### Regular Maintenance
- Update dependencies regularly
- Monitor database performance
- Check for security updates
- Backup data regularly
- Review error logs

---

**For additional support, check the documentation or create an issue on GitHub.**
