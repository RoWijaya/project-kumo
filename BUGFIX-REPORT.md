# Kumo bug-fix and verification report

## Main fixes made

1. **Registration and database setup** — removed embedded production database credentials; added private config/environment-variable support and a safe local-XAMPP fallback. Registration now validates input, hashes passwords, checks duplicate email/name, handles uniqueness races, and returns readable form errors instead of an unhandled server error.
2. **Google OAuth fatal parse error** — fixed `google_config.php` so `declare(strict_types=1)` is the first PHP statement. Added OAuth initiation/state handling and corrected role-based redirects after Google sign-in.
3. **Authentication consistency** — repaired protected-page redirects, rechecks role/auth-version on protected pages (including admin/owner routes), supports email or username login, and fixes logout's destination. Logout still clears the browser session even if database token revocation cannot run.
4. **Password reset** — added the missing reset-request endpoint and a token-based, expiring, one-time password-reset flow. The former email-only password-change pattern is no longer used.
5. **Cloud and task handlers** — added Cloud creation, task creation/completion, content-category creation, and schedule generation handlers, with CSRF checks and ownership checks. The previous front-end-only Cloud/category actions now call their PHP endpoints.
6. **Onboarding / dashboard JavaScript** — added the modal elements the JavaScript expects, stopped the empty onboarding modal from crashing the dashboard, made onboarding choices persist server-side, and made dashboard search filter visible Clouds.
7. **Notes** — added note ownership validation and an HTML allowlist sanitizer before persistence and rendering.
8. **Role migration and demo seeder** — aligned Kumo roles to `user`, `admin`, and `owner`; the predictable demo-account seeder now refuses HTTP execution.
9. **Two-factor fail-closed behavior** — accounts flagged for 2FA no longer hit a missing page through Google sign-in or bypass verification through password/remember-me login. The full enrollment and verification UI remains incomplete, documented below.
10. **Install/migration support** — added a fresh schema, compatibility migration, private configuration example, and XAMPP setup instructions.

## Checks run

- All **29 PHP files** passed `php -l` syntax validation after the changes.
- All JavaScript files under `assets/JS` passed `node --check`.
- Local `href`, `src`, and form `action` references in the HTML/PHP files were checked; no missing static local target was found by the check.
- Searched the patched source for embedded database host/client secrets; the live values are no longer present in the fixed source.

## Not verified in this environment

- The current runtime has no `mysqli` extension and no live MySQL service, so registration/login inserts, SQL migration execution, task CRUD, and notes persistence could not be exercised against an actual database. Apply the schema/config in XAMPP and perform the manual checks before replacing a live deployment.
- Google OAuth and password-reset email require real deployment credentials, registered redirect URLs, PHP cURL, and a working outbound mail setup.
- Two-factor authentication is **not complete end to end** in this build: helper functions and a database flag exist, but there is no complete enrollment UI/secret-storage migration/challenge endpoint. Do not enable `two_factor_enabled` manually until that flow is implemented and tested.

## Important security action

The uploaded archive contained real database and Google OAuth secret material. Rotate/revoke those values in the relevant control panels, then configure the replacements through environment variables or `assets/backend/config.local.php`. Do not reuse the exposed values.
