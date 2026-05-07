# HandToGlobal Environment Setup

## Required Server

- Ubuntu 22.04 VPS
- Nginx
- PHP 8.2-FPM
- MySQL 8
- Certbot for SSL

## Production Variables

The app reads database and runtime settings from server environment variables passed by Nginx to PHP-FPM:

```nginx
fastcgi_param APP_ENV "production";
fastcgi_param HTG_DEBUG "0";
fastcgi_param DB_HOST "localhost";
fastcgi_param DB_PORT "3306";
fastcgi_param DB_NAME "handtoglobal";
fastcgi_param DB_USER "handtoglobal_user";
fastcgi_param DB_PASS "CHANGE_ME_STRONG_PASSWORD";
```

Use `localhost` only for the local MySQL service on the VPS. Public URLs and redirects are generated from the current request and no longer require a hardcoded `localhost` or `/handtoglobal` base path.

Do not set `HTG_CREATE_DEFAULT_ADMIN` in production. Create the first admin account with a unique password after importing the schema.

Example first-admin setup on the VPS:

```bash
read -r -p "Admin email: " ADMIN_EMAIL
read -r -s -p "Admin password: " ADMIN_PASS
echo
ADMIN_HASH=$(ADMIN_PASS="$ADMIN_PASS" php8.2 -r 'echo password_hash(getenv("ADMIN_PASS"), PASSWORD_DEFAULT);')
mysql -u handtoglobal_user -p handtoglobal -e "INSERT INTO admins (name, email, password) VALUES ('Admin', '${ADMIN_EMAIL}', '${ADMIN_HASH}') ON DUPLICATE KEY UPDATE password='${ADMIN_HASH}'"
unset ADMIN_PASS ADMIN_HASH
```

## PHP-FPM Settings

Recommended `/etc/php/8.2/fpm/php.ini` values:

```ini
display_errors = Off
log_errors = On
upload_max_filesize = 20M
post_max_size = 24M
memory_limit = 256M
session.cookie_httponly = 1
session.use_strict_mode = 1
```

Restart PHP-FPM after changes:

```bash
sudo systemctl restart php8.2-fpm
```

## Writable Paths

Only these application paths should be writable by the web server:

```bash
sudo mkdir -p /var/www/handtoglobal/uploads/tasks /var/www/handtoglobal/uploads/settings /var/www/handtoglobal/tmp_sessions
sudo chown -R www-data:www-data /var/www/handtoglobal/uploads /var/www/handtoglobal/tmp_sessions
sudo chmod -R 775 /var/www/handtoglobal/uploads /var/www/handtoglobal/tmp_sessions
```

Application source files should not be writable by PHP-FPM:

```bash
sudo chown -R deploy:www-data /var/www/handtoglobal
sudo find /var/www/handtoglobal -type d -exec chmod 755 {} \;
sudo find /var/www/handtoglobal -type f -exec chmod 644 {} \;
```

Run the writable-path commands again after resetting permissions.

## SSL

Point DNS `A` records for `example.com` and `www.example.com` to the VPS IP, then run:

```bash
sudo certbot --nginx -d example.com -d www.example.com
```

Certbot will update the Nginx server block and install certificate renewal timers.
