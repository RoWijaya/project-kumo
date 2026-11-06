# ☁️ Project Kumo

Kumo is a personal workspace that keeps notes, tasks, and plans organized into **Clouds**. A Cloud can contain nested Clouds, notes, tasks, and (for class workspaces) an automatically generated week structure.

## ✨ What's New

- **Redesigned User Interface** — Kumo now features a redesigned interface with a more polished, modern, and user-friendly visual experience.
- **GSAP Animations** — Smooth animations powered by GSAP bring the interface to life and make interactions feel more engaging.
- **New Kumo Logo** — Updated branding with the new Fluffy Cloud Companion logo and visual identity.
- **Live Deployment** — Kumo is now deployed and accessible online.

## 🌐 Access Kumo Online

Kumo is deployed and available at:

**https://kumo.ct.ws**

Though there might still be some bugs and errors that needs fixing.

You can access the application directly through your browser without running a local XAMPP environment.

## 💻 Run Kumo Locally with XAMPP

1. Extract this project into `C:\xampp\htdocs\Kumo`.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open phpMyAdmin and select or create the database named `users`.
4. Import `assets/database/schema.sql` into that database for a fresh installation.
   - If you already have a Kumo `userdata` table with accounts you want to keep, **back up the database first**, then import `assets/database/upgrade_existing.sql` followed by `assets/database/schema.sql`. The migration adds the newer account columns and normalizes legacy `seller` roles; `schema.sql` creates missing feature tables without replacing the existing user table.
5. Copy `assets/backend/config.local.example.php` to `assets/backend/config.local.php`. Set the database host, username, password, and database name to match your local MySQL setup. The default example is for a standard local XAMPP install (`127.0.0.1`, `root`, empty password, database `users`). Keep the real `config.local.php` private; it is intentionally ignored by Git.
6. Open `http://localhost/Kumo/` in your browser and test registration, login, creating a Cloud, adding a task, and saving a note.

The same `config.local.php` can store Google OAuth settings, an email sender, and the canonical site URL. Leave Google values blank when you do not use Google sign-in. For local Google OAuth, the configured redirect URI must exactly match `http://localhost/Kumo/assets/backend/google_callback.php` in your Google Cloud OAuth client.

For hosting, use the database host, name, username, and password supplied by your hosting provider. Set `base_url` to your canonical **HTTPS** site URL.

## 🗄️ Existing-Database Migration Notes

- Do not import `upgrade_existing.sql` against an empty database; create the `userdata` table first with `schema.sql` if this is a new installation.
- If a foreign-key creation fails on an existing installation, check that `userdata.id` is an `INT` with the same signed/unsigned definition as the new tables' `user_id` columns. Back up before changing column types.
- The current environment used for the static audit did not have PHP's `mysqli` extension or a running MySQL server, so a real registration insert cannot be simulated here. PHP/JavaScript syntax and local file references were checked; complete the live checks after importing/configuring the database.

## 👥 Optional Demo Accounts

`assets/backend/seed_demo_accounts.php` is restricted to CLI use because it resets predictable demo-account passwords each time it runs. Run it from the project directory in a local terminal only, and remove it before deploying publicly. Never publish the demo passwords or use them on a public deployment.

## 🔐 Security Reminders

- The real database and Google OAuth credentials that were present in the uploaded project archive should be **rotated**. The fixed source uses environment variables or `assets/backend/config.local.php` instead of embedding those credentials in application code.
- Password-reset email requires `KUMO_MAIL_FROM` (or `mail_from` in the private config) and a working PHP mail transport. The app also needs `KUMO_BASE_URL` (or `base_url`) for reset links, except in local XAMPP development where it can derive the URL.
- Do not expose database credentials or `config.local.php` in version control or public source archives.

## ⚙️ Current Functionality

- Redesigned landing page and authentication interfaces
- GSAP-powered interface animations
- Updated Kumo branding and logo
- Landing page and login/register flows
- PHP session authentication with role checks, remember-me token rotation, rate-limited login attempts, and CSRF helpers
- Owner/admin dashboards
- Cloud creation and nested Cloud categories
- Onboarding that persists the selected education/workspace structure
- Notes with HTML sanitization and owner-checked save operations
- Task creation/completion and class-week generation
- Token-based password reset flow (requires working mail configuration)
- Google OAuth flow (requires correctly configured Google credentials and PHP cURL)

Some previously planned items, including a complete two-factor enrollment/settings UI and reminders, are not implemented end to end in this build and should not be treated as active features yet.

---

**Project Kumo** — Your personal space to organize everything, one Cloud at a time. ☁️
