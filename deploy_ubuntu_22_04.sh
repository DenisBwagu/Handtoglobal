#!/usr/bin/env bash
set -euo pipefail

# HandToGlobal Ubuntu 22.04 deployment script.
# Run from the project root on the VPS:
#   sudo DOMAIN=example.com DB_PASS='strong-password' bash deploy_ubuntu_22_04.sh

APP_NAME="${APP_NAME:-handtoglobal}"
DOMAIN="${DOMAIN:-example.com}"
APP_ROOT="${APP_ROOT:-/var/www/${APP_NAME}}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
WEB_GROUP="${WEB_GROUP:-www-data}"
DB_NAME="${DB_NAME:-handtoglobal}"
DB_USER="${DB_USER:-handtoglobal_user}"
DB_PASS="${DB_PASS:-}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
PHP_VERSION="${PHP_VERSION:-8.2}"
ENABLE_SSL="${ENABLE_SSL:-1}"

if [ "$(id -u)" -ne 0 ]; then
    echo "Run this script as root or with sudo."
    exit 1
fi

if [ "$DOMAIN" = "example.com" ]; then
    echo "Set DOMAIN before running, for example: sudo DOMAIN=yourdomain.com DB_PASS='...' bash deploy_ubuntu_22_04.sh"
    exit 1
fi

if [ -z "$DB_PASS" ]; then
    echo "Set DB_PASS before running."
    exit 1
fi

if ! [[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ && "$DB_USER" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "DB_NAME and DB_USER may contain only letters, numbers, and underscores."
    exit 1
fi

DB_PASS_SQL="$(printf "%s" "$DB_PASS" | sed "s/'/''/g")"
DB_PASS_NGINX="$(printf "%s" "$DB_PASS" | sed 's/\\/\\\\/g; s/"/\\"/g; s/\$/\\$/g')"
SOURCE_ROOT="$(pwd -P)"
TARGET_ROOT="$(mkdir -p "$APP_ROOT" && cd "$APP_ROOT" && pwd -P)"

if [ "$SOURCE_ROOT" = "$TARGET_ROOT" ]; then
    echo "Do not run this script from APP_ROOT itself. Run it from a separate release/checkout directory."
    exit 1
fi

apt-get update
apt-get install -y nginx mysql-server software-properties-common ca-certificates lsb-release apt-transport-https

if ! apt-cache show "php${PHP_VERSION}-fpm" >/dev/null 2>&1; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update
fi

apt-get install -y \
    "php${PHP_VERSION}-fpm" \
    "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-zip" \
    "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-intl" \
    unzip rsync certbot python3-certbot-nginx

id "$DEPLOY_USER" >/dev/null 2>&1 || adduser --disabled-password --gecos "" "$DEPLOY_USER"
usermod -aG "$WEB_GROUP" "$DEPLOY_USER"

rsync -a --delete \
    --exclude ".git/" \
    --exclude ".env" \
    --exclude "config_BAD.php" \
    --exclude "backup_before_manual_layout/" \
    --exclude "backup_before_speed_fix/" \
    --exclude "setup_backup/" \
    --exclude "disabled_old_files/" \
    ./ "$APP_ROOT"/

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS_SQL}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS_SQL}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

if [ -f "$APP_ROOT/database_required_tables.sql" ]; then
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$APP_ROOT/database_required_tables.sql"
fi

mkdir -p "$APP_ROOT/uploads/tasks" "$APP_ROOT/uploads/settings" "$APP_ROOT/tmp_sessions"
chown -R "$DEPLOY_USER:$WEB_GROUP" "$APP_ROOT"
find "$APP_ROOT" -type d -exec chmod 755 {} \;
find "$APP_ROOT" -type f -exec chmod 644 {} \;
chown -R "$WEB_GROUP:$WEB_GROUP" "$APP_ROOT/uploads" "$APP_ROOT/tmp_sessions"
chmod -R 775 "$APP_ROOT/uploads" "$APP_ROOT/tmp_sessions"

PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
sed -i "s/^upload_max_filesize = .*/upload_max_filesize = 20M/" "$PHP_INI"
sed -i "s/^post_max_size = .*/post_max_size = 24M/" "$PHP_INI"
sed -i "s/^memory_limit = .*/memory_limit = 256M/" "$PHP_INI"
sed -i "s/^display_errors = .*/display_errors = Off/" "$PHP_INI"
sed -i "s/^log_errors = .*/log_errors = On/" "$PHP_INI"

cat > "/etc/nginx/sites-available/${APP_NAME}" <<NGINX
server {
    listen 80;
    server_name ${DOMAIN} www.${DOMAIN};
    root ${APP_ROOT};
    index index.php index.html;

    access_log /var/log/nginx/${APP_NAME}_access.log;
    error_log /var/log/nginx/${APP_NAME}_error.log;
    client_max_body_size 20M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~* ^/uploads/.*\.php$ {
        deny all;
    }

    location ^~ /uploads/ {
        try_files \$uri =404;
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
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param APP_ENV "production";
        fastcgi_param HTG_DEBUG "0";
        fastcgi_param DB_HOST "localhost";
        fastcgi_param DB_PORT "3306";
        fastcgi_param DB_NAME "${DB_NAME}";
        fastcgi_param DB_USER "${DB_USER}";
        fastcgi_param DB_PASS "${DB_PASS_NGINX}";
    }
}
NGINX

ln -sfn "/etc/nginx/sites-available/${APP_NAME}" "/etc/nginx/sites-enabled/${APP_NAME}"
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl enable nginx "php${PHP_VERSION}-fpm" mysql
systemctl restart "php${PHP_VERSION}-fpm"
systemctl reload nginx

if [ "$ENABLE_SSL" = "1" ]; then
    certbot --nginx -d "$DOMAIN" -d "www.${DOMAIN}" --non-interactive --agree-tos -m "$ADMIN_EMAIL" --redirect || {
        echo "Certbot failed. Confirm DNS points to this VPS, then run: sudo certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}"
    }
fi

echo "Deployment complete: https://${DOMAIN}"
