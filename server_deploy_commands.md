# Ubuntu 22.04 VPS Deployment Commands

Replace `example.com` and the database password before running.

## One-Command Deployment

From the project root on the VPS:

```bash
sudo DOMAIN=example.com \
  DB_NAME=handtoglobal \
  DB_USER=handtoglobal_user \
  DB_PASS='CHANGE_ME_STRONG_PASSWORD' \
  ADMIN_EMAIL=admin@example.com \
  bash deploy_ubuntu_22_04.sh
```

The script installs Nginx, PHP 8.2-FPM, MySQL, Certbot, creates the database, imports `database_required_tables.sql`, writes the Nginx config, sets optimized permissions, and requests SSL.

## Manual Package Install

```bash
sudo apt update
sudo apt install -y nginx mysql-server software-properties-common ca-certificates lsb-release apt-transport-https
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-intl unzip rsync certbot python3-certbot-nginx
```

## App Files And Permissions

```bash
sudo mkdir -p /var/www/handtoglobal
sudo rsync -a --delete ./ /var/www/handtoglobal/
sudo chown -R deploy:www-data /var/www/handtoglobal
sudo find /var/www/handtoglobal -type d -exec chmod 755 {} \;
sudo find /var/www/handtoglobal -type f -exec chmod 644 {} \;
sudo mkdir -p /var/www/handtoglobal/uploads/tasks /var/www/handtoglobal/uploads/settings /var/www/handtoglobal/tmp_sessions
sudo chown -R www-data:www-data /var/www/handtoglobal/uploads /var/www/handtoglobal/tmp_sessions
sudo chmod -R 775 /var/www/handtoglobal/uploads /var/www/handtoglobal/tmp_sessions
```

## MySQL

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

Create the first admin with a unique password:

```bash
read -r -p "Admin email: " ADMIN_EMAIL
read -r -s -p "Admin password: " ADMIN_PASS
echo
ADMIN_HASH=$(ADMIN_PASS="$ADMIN_PASS" php8.2 -r 'echo password_hash(getenv("ADMIN_PASS"), PASSWORD_DEFAULT);')
mysql -u handtoglobal_user -p handtoglobal -e "INSERT INTO admins (name, email, password) VALUES ('Admin', '${ADMIN_EMAIL}', '${ADMIN_HASH}') ON DUPLICATE KEY UPDATE password='${ADMIN_HASH}'"
unset ADMIN_PASS ADMIN_HASH
```

## Nginx

```bash
sudo cp /var/www/handtoglobal/deployment/nginx-handtoglobal.conf /etc/nginx/sites-available/handtoglobal
sudo sed -i 's/example.com/yourdomain.com/g' /etc/nginx/sites-available/handtoglobal
sudo sed -i 's/CHANGE_ME_STRONG_PASSWORD/your-db-password/g' /etc/nginx/sites-available/handtoglobal
sudo ln -sfn /etc/nginx/sites-available/handtoglobal /etc/nginx/sites-enabled/handtoglobal
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl restart php8.2-fpm
```

## SSL

```bash
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

## Verify

```bash
php8.2 -v
sudo nginx -t
sudo systemctl status nginx --no-pager
sudo systemctl status php8.2-fpm --no-pager
sudo systemctl status mysql --no-pager
```
