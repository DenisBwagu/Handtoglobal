# HandToGlobal Deployment Checklist

## PHP Runtime
- [x] Run `php -l` across all PHP files.
- [x] Confirm changed PHP files pass `php -l`.
- [x] Disable public PHP error display in production.
- [x] Keep PHP errors logged through `log_errors`.
- [x] Gate active debug logging behind `HTG_DEBUG`.
- [x] Disable dashboard browser console debug output by default.

## Database Configuration
- [x] `config.php` supports `DB_HOST`.
- [x] `config.php` supports `DB_NAME`.
- [x] `config.php` supports `DB_USER`.
- [x] `config.php` supports `DB_PASS`.
- [x] `config.php` supports `DB_PORT`.
- [x] PDO connection uses the configured database constants.
- [ ] Set production environment values before upload:
  - `APP_ENV=production`
  - `DB_HOST`
  - `DB_PORT`
  - `DB_NAME`
  - `DB_USER`
  - `DB_PASS`

## Required Tables
- [x] `users`
- [x] `tasks`
- [x] `completed_tasks`
- [x] `deposits`
- [x] `withdrawals`
- [x] `invitation_codes`
- [x] `settings`
- [x] `testimonials`
- [x] `combos`
- [ ] `user_limits` was missing in the local database check.
- [x] `user_levels`
- [x] `admins`
- [x] `levels`
- [x] `balance_adjustments`

Run the existing safe repair/migration for `user_limits` before going live if that feature is required on production.

## Uploads
- [x] Confirm settings uploads use `uploads/settings/`.
- [x] Confirm task uploads use `uploads/tasks/`.
- [x] Keep uploaded database paths relative, not absolute URLs.
- [x] Add `index.php` protection to `uploads/settings/`.
- [x] Add `index.php` protection to `uploads/tasks/`.
- [x] Add `.htaccess` protection to prevent directory listing and PHP execution inside upload folders.

## Public Debug/Test Access
- [x] Block public access to diagnostic, setup, repair, backup, and test PHP scripts through root `.htaccess`.
- [x] Keep backup and disabled old file folders from being directly served when Apache rewrite rules are enabled.

## Route Smoke Test
- [x] Homepage loads without visible PHP warning markup.
- [x] Login page loads without visible PHP warning markup.
- [x] Register page loads without visible PHP warning markup.
- [x] User dashboard redirects unauthenticated visitors to login without visible PHP warning markup.
- [x] Request withdrawal redirects unauthenticated visitors to login without visible PHP warning markup.
- [x] Admin dashboard redirects unauthenticated visitors to login without visible PHP warning markup.
- [x] Admin settings redirects unauthenticated visitors to login without visible PHP warning markup.
- [x] Admin tasks redirects unauthenticated visitors to login without visible PHP warning markup.
- [x] Admin combos redirects unauthenticated visitors to login without visible PHP warning markup.
- [x] Admin users redirects unauthenticated visitors to login without visible PHP warning markup.
- [x] Admin finance analysis redirects unauthenticated visitors to login without visible PHP warning markup.

## Manual Live Verification
- [ ] Login as a real user.
- [ ] Register a new user.
- [ ] Login as admin.
- [ ] User dashboard loads after login.
- [ ] Task popup opens and task flow works.
- [ ] Withdrawal request works with valid wallet fields.
- [ ] Admin dashboard loads after login.
- [ ] Settings save successfully.
- [ ] Logo upload saves to `uploads/settings/` and displays from relative path.
- [ ] Tasks page loads and task image uploads save to `uploads/tasks/`.
- [ ] Combos page loads and combo actions work.
- [ ] Users page loads and user actions work.
- [ ] Finance Analysis page loads at full admin width.

## Server Notes
- [ ] Confirm Apache `mod_rewrite` and `.htaccess` overrides are enabled on the live host.
- [ ] Confirm PHP upload limits are large enough for logo/task images.
- [ ] Confirm `uploads/settings/` and `uploads/tasks/` are writable by PHP.
- [ ] Confirm HTTPS is enabled.
- [ ] Confirm production database backup is taken before first live run.
