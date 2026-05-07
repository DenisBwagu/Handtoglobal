# HandToGlobal Deployment Checklist

## Local Preflight

- Run `php -v` and confirm PHP 8.2 is available on the VPS.
- Run PHP lint across the project before upload.
- Set production environment variables: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, and `APP_ENV=production`.
- Confirm `uploads/`, `uploads/tasks/`, `uploads/settings/`, and `tmp_sessions/` exist and are writable by the PHP-FPM user.
- Import `database_required_tables.sql` into the production MySQL database.
- Change the default admin password immediately after first login.

## Hostinger VPS

- Ubuntu server has Nginx, PHP 8.2-FPM, and MySQL installed.
- Nginx document root points to the project directory.
- Nginx blocks setup, repair, test, debug, backup, and diagnostic PHP scripts.
- PHP upload limits are large enough for admin image uploads.
- `APP_ENV=production` is set so PHP errors are logged, not displayed.
- File ownership is set to the deploy user and `www-data` group.

## Application Checks

- User login redirects to `dashboard.php`.
- Admin login redirects to `admin/dashboard.php`.
- User logout and admin logout clear the session and return to login.
- Admin sidebar links resolve under the live domain root.
- Task create/edit image upload writes to `uploads/tasks/`.
- Settings image upload writes to `uploads/settings/`.
- Withdrawals can be requested, approved, rejected, and listed.
- Combo create/edit pages can save the columns used by the active code.

## Post-Deploy

- Visit `/login.php` and test admin and user login.
- Visit `/admin/settings.php` and confirm uploads work.
- Visit `/admin/tasks.php`, create one test task, then remove it.
- Visit `/request_withdrawal.php` with a test user and verify admin review.
- Review `/var/log/nginx/handtoglobal_error.log` and PHP-FPM logs after testing.
