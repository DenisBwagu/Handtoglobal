# Ubuntu VPS Deploy Commands

Replace `example.com`, paths, database names, and passwords before running.

```bash
sudo apt update
sudo apt install -y nginx mysql-server php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd unzip
```

```bash
sudo mkdir -p /var/www/handtoglobal
sudo rsync -av --delete ./ /var/www/handtoglobal/
sudo chown -R deploy:www-data /var/www/handtoglobal
sudo find /var/www/handtoglobal -type d -exec chmod 755 {} \;
sudo find /var/www/handtoglobal -type f -exec chmod 644 {} \;
sudo mkdir -p /var/www/handtoglobal/uploads/tasks /var/www/handtoglobal/uploads/settings /var/www/handtoglobal/tmp_sessions
sudo chown -R www-data:www-data /var/www/handtoglobal/uploads /var/www/handtoglobal/tmp_sessions
sudo chmod -R 775 /var/www/handtoglobal/uploads /var/www/handtoglobal/tmp_sessions
```

```bash
sudo mysql
CREATE DATABASE handtoglobal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'handtoglobal_user'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON handtoglobal.* TO 'handtoglobal_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
mysql -u handtoglobal_user -p handtoglobal < /var/www/handtoglobal/database_required_tables.sql
```

Create `/etc/nginx/sites-available/handtoglobal`:

```nginx
server {
    listen 80;
    server_name example.com www.example.com;
    root /var/www/handtoglobal;
    index index.php index.html;

    access_log /var/log/nginx/handtoglobal_access.log;
    error_log /var/log/nginx/handtoglobal_error.log;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~* /(backup_before_|setup_backup|disabled_old_files|vendor|node_modules|tmp_sessions|secrets|private)/ {
        deny all;
    }

    location ~* (^|/)(test_|debug_|check_|setup_|repair_|fix_|add_|create_|drop_|seed_|final_|quick_setup|complete_setup|database_repair|translation_audit|syntax_checker|auth_test|db_test|prepare_for_github).*\.php$ {
        deny all;
    }

    location ~ /\. {
        deny all;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param APP_ENV production;
        fastcgi_param DB_HOST localhost;
        fastcgi_param DB_PORT 3306;
        fastcgi_param DB_NAME handtoglobal;
        fastcgi_param DB_USER handtoglobal_user;
        fastcgi_param DB_PASS CHANGE_ME_STRONG_PASSWORD;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/handtoglobal /etc/nginx/sites-enabled/handtoglobal
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl restart php8.2-fpm
```

Optional HTTPS:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d example.com -d www.example.com
```
