#!/usr/bin/env bash
set -euo pipefail

# HandToGlobal Ubuntu 22.04 deployment script.
# Run from the project root on the VPS:
#   sudo DOMAIN=example.com DB_PASS='strong-password' bash deploy_ubuntu_22_04.sh

APP_NAME="${APP_NAME:-handtoglobal}"
DOMAIN="${DOMAIN:-example.com}"
APP_ROOT="${APP_ROOT:-/var/www/${APP_NAME}}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/${APP_NAME}}"
REQUIRE_EXISTING_DB_BACKUP="${REQUIRE_EXISTING_DB_BACKUP:-1}"
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
DEPLOY_COMMIT="$(git rev-parse HEAD 2>/dev/null || printf "unknown")"
CURRENT_DEPLOYED_COMMIT="unknown"

if [ -f "$APP_ROOT/.deployed_git_commit" ]; then
    CURRENT_DEPLOYED_COMMIT="$(cat "$APP_ROOT/.deployed_git_commit")"
elif [ -d "$APP_ROOT/.git" ]; then
    CURRENT_DEPLOYED_COMMIT="$(git -C "$APP_ROOT" rev-parse HEAD 2>/dev/null || printf "unknown")"
fi

if [ "$SOURCE_ROOT" = "$TARGET_ROOT" ]; then
    echo "Do not run this script from APP_ROOT itself. Run it from a separate release/checkout directory."
    exit 1
fi

mkdir -p "$BACKUP_ROOT"
RESTORE_ID="$(date +%Y%m%d_%H%M%S)"
APP_BACKUP_FILE="${BACKUP_ROOT}/${APP_NAME}_app_${RESTORE_ID}.tar.gz"
DB_BACKUP_FILE="${BACKUP_ROOT}/${APP_NAME}_db_${DB_NAME}_${RESTORE_ID}.sql.gz"
MANIFEST_FILE="${BACKUP_ROOT}/${APP_NAME}_restore_${RESTORE_ID}.txt"

if [ -e "$APP_BACKUP_FILE" ] || [ -e "$DB_BACKUP_FILE" ] || [ -e "$MANIFEST_FILE" ]; then
    echo "Backup target already exists for restore id ${RESTORE_ID}; aborting to avoid overwrite."
    exit 1
fi

echo "Creating restore point before deployment."
echo "Backup directory: $BACKUP_ROOT"
echo "Current deployed Git commit hash: $CURRENT_DEPLOYED_COMMIT"
echo "Incoming Git commit hash: $DEPLOY_COMMIT"

if [ -d "$APP_ROOT" ] && [ -n "$(find "$APP_ROOT" -mindepth 1 -maxdepth 1 -print -quit)" ]; then
    tar -czf "$APP_BACKUP_FILE" -C "$(dirname "$APP_ROOT")" "$(basename "$APP_ROOT")"
    test -s "$APP_BACKUP_FILE"
else
    echo "Application root $APP_ROOT is empty or missing; cannot create production app backup."
    exit 1
fi

DB_EXISTS="$(mysql -NBe "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}'" 2>/dev/null || true)"
if [ "$DB_EXISTS" = "$DB_NAME" ]; then
    mysqldump --single-transaction --routines --triggers --events "$DB_NAME" | gzip -c > "$DB_BACKUP_FILE"
    test -s "$DB_BACKUP_FILE"
elif [ "$REQUIRE_EXISTING_DB_BACKUP" = "1" ]; then
    echo "Database ${DB_NAME} does not exist; cannot create required production database backup."
    exit 1
else
    echo "Database ${DB_NAME} does not exist; skipping database backup because REQUIRE_EXISTING_DB_BACKUP=0."
fi

cat > "$MANIFEST_FILE" <<RESTORE
Restore point: ${RESTORE_ID}
Backup directory: ${BACKUP_ROOT}
Application backup: ${APP_BACKUP_FILE}
Database backup: ${DB_BACKUP_FILE}
Current deployed Git commit hash: ${CURRENT_DEPLOYED_COMMIT}
Incoming Git commit hash: ${DEPLOY_COMMIT}

Rollback commands:
  sudo systemctl stop php${PHP_VERSION}-fpm nginx
  sudo rm -rf ${APP_ROOT}
  sudo mkdir -p $(dirname "$APP_ROOT")
  sudo tar -xzf ${APP_BACKUP_FILE} -C $(dirname "$APP_ROOT")
  sudo mysql -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  zcat ${DB_BACKUP_FILE} | sudo mysql ${DB_NAME}
  sudo chown -R ${DEPLOY_USER}:${WEB_GROUP} ${APP_ROOT}
  sudo chown -R ${WEB_GROUP}:${WEB_GROUP} ${APP_ROOT}/uploads ${APP_ROOT}/tmp_sessions
  sudo systemctl start php${PHP_VERSION}-fpm nginx
RESTORE

echo "Restore point created successfully."
echo "Backup file locations:"
echo "  App: $APP_BACKUP_FILE"
echo "  Database: $DB_BACKUP_FILE"
echo "  Manifest: $MANIFEST_FILE"
echo "Backup filenames:"
echo "  $(basename "$APP_BACKUP_FILE")"
echo "  $(basename "$DB_BACKUP_FILE")"
echo "  $(basename "$MANIFEST_FILE")"
echo "Rollback commands:"
sed -n '/^Rollback commands:/,$p' "$MANIFEST_FILE"
echo "Backups succeeded; continuing deployment."

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
    --exclude "uploads/" \
    --exclude "tmp_sessions/" \
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
printf "%s\n" "$DEPLOY_COMMIT" > "$APP_ROOT/.deployed_git_commit"

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
