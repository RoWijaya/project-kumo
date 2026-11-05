# Kumo — Product Requirements Document (PRD)

**Document Version:** 2.1.0  
**Status:** Core application implemented; current redesign QA partially verified  
**Date:** October 29, 2026  
**Project Location:** `C:\xampp\htdocs\Kumo`  
**Target Environments:** Local Development (XAMPP / PHP 8.2+ / MariaDB) & Production (`https://kumo.ct.ws`)

---

## 1. Product Overview & Goals

**Kumo** is a tranquil, thoughtfully crafted personal productivity and academic organizer built for students, thinkers, and professionals. Drawing inspiration from calm cloud-like simplicity and Apple Human Interface Guidelines (HIG), Kumo provides a focused workspace where coursework, notes, schedules, and daily tasks can live together harmoniously without visual clutter or unnecessary cognitive load.

### Key Goals
- **Calm, Distraction-Free Organization:** Hierarchical workspace centered around "Clouds" (categories/classes) and nested sub-clouds.
- **Robust Authentication & Identity:** Full-featured native authentication supporting both standard email/password registration and Google OAuth 2.0.
- **Strict Verification Integrity:** Mandatory email verification ensuring account validity before user login access, with secure token lifecycles and cryptographic uniqueness.
- **Unified Aesthetic & Cross-Theme Cohesion:** A refined iOS-informed visual design with light and charcoal dark themes, accent customization (Sage, Forest, Ocean, Lavender), and fluid typography.
- **Resilient & Idempotent Migrations:** Safe database evolution supporting both fresh installations and legacy schema updates without data loss.

---

## 2. Technology Stack & Architecture

- **Backend Language:** Native PHP 8.2+ with strict typing (`declare(strict_types=1);`).
- **Database:** MySQL / MariaDB 10.4+ accessed via PHP `mysqli` with prepared statements and transactions.
- **Web Server:** Apache (via XAMPP) with `.htaccess` rewrites and strict directory access controls.
- **Frontend Architecture:** Semantic HTML5, Vanilla JavaScript (ES2022+), and native CSS3 using custom properties (CSS variables).
- **Typography:** DM Sans, Poppins, Inter, Google Material Symbols Outlined.
- **Security Primitives:** Argon2id / Bcrypt password hashing, SHA-256 token hashing, `random_bytes()` cryptographic entropy, strict CSRF token validation, HTTP-only SameSite cookies, and rate-limiting.
- **Email Transport:** Direct authenticated socket-level SMTP implementation with TLS (Port 587) and SSL (Port 465) support.
- **OAuth Integration:** Google Identity Services (OpenID Connect / OAuth 2.0).

---

## 3. User Roles & Permissions

Kumo implements a role-based access control (RBAC) model:

| Role | Access Level | Description & Available Views |
|---|---|---|
| `user` | Standard User | Default assigned role on registration. Full access to personal clouds, notes, class schedules, tasks, preferences, and personal profile (`Main/homepage.php`, `Main/note_editor.php`). |
| `admin` | Administrator | Extended permissions to manage community content, user moderation, and application oversight (`Main/admin_dashboard.php`). |
| `owner` | System Owner | Complete administrative authority, system diagnostics, demo seeding, and account role elevation (`Main/owner_dashboard.php`). |

---

## 4. Modules & Core User Flows

### 4.1 Atmospheric Clouds & Workspaces
- Users organize coursework and projects into top-level and nested "Clouds".
- Each cloud features custom icons, semantic color tags, class schedule pairings (midterm/final milestones), and rich notes.

### 4.2 Tasks & Time Tracking
- Cloud-associated tasks with due dates, completion states, and progress visualization.
- Filterable by date, completion status, and active cloud context.

### 4.3 Notes & Rich-Text Documentation
- In-context note taking powered by Quill-compatible rich-text formatting and persistent database storage (`notes` table).

### 4.4 Settings & Personalization
- Multi-theme switching (System, Light, Dark) persisted in `user_preferences`.
- Accent color palette switching (`sage`, `forest`, `ocean`, `lavender`).
- Motion preferences (`prefers-reduced-motion`) and layout density configuration.

---

## 5. UI/UX Requirements & Design Principles

Kumo adheres to core Apple Human Interface Guidelines:
1. **Clarity & Deference:** Interface elements step back to let notes and user thoughts take center stage.
2. **Harmonious Palette:**
   - **Light Theme:** Soft paper backdrops (`#edf2ee`), crisp card surfaces (`#ffffff`), charcoal ink (`#1e2d22`), muted sage accents (`#527e65`).
   - **Dark Theme:** Deep charcoal surfaces (`#101a12`, `#192019`), readable warm text (`#e2ece4`), luminous sage accents (`#8ec4a0`).
   - **Accents:** Muted Sage, Forest green, Ocean blue, Lavender purple.
3. **Purposeful Feedback & States:** Explicit alerts for registration status, pending verification, invalid credentials, rate limiting, and successful password resets.
4. **Accessible Inputs & Focus Rings:** Explicit contrast ratios, high-visibility focus states (`outline-offset: 3px`), aria labels, and password visibility toggles.
5. **Fluid Responsive Layouts:** Optimized for desktop (1440×900+), tablet (768×1024), and mobile viewports (390×844) with mobile-first media queries.

---

## 6. Registration & Authentication Requirements

### 6.1 Standard Email/Password Registration
- **Input Validation:**
  - Full Name: 1–100 characters, no control characters.
  - Username: 3–50 alphanumeric characters plus `.` `_` `-`.
  - Email: Valid RFC email address, max 254 chars, normalized to lowercase.
  - Password: 8–20 characters with uppercase, lowercase, digit, and special character.
  - Password Confirmation: Exact character match.
- **Account State on Creation:**
  - `role` defaults to `'user'`.
  - `setup_completed` defaults to `0`.
  - `email_verified_at` initialized to `NULL` (Pending Verification).
  - Password hashed using `PASSWORD_DEFAULT` (Argon2id or Bcrypt).
- **Failure Handling:**
  - Duplicate email or username returns 409 Conflict with precise field highlighting.
  - Client-side AJAX feedback preserves correctly entered fields without reload.

### 6.2 Login Security & Gatekeeping
- **Credential Validation:** Compares supplied identifier against `email` or `username` via prepared statement, then checks password hash via `password_verify()`.
- **Verification Gate:** If `email_verified_at` is `NULL` or empty, authentication is rejected with `email_unverified`, redirecting to `login.html?error=email_unverified` with an actionable link to resend verification.
- **Session Security:** `session_regenerate_id(true)` executed upon authentication; user ID, role, and auth version stored in session.
- **Remember Me Tokens:** Cryptographically separate 24-character selector and 64-character SHA-256 validator hash stored in `remember_tokens` table with strict expiration.

---

## 7. Email Verification Lifecycle

1. **Token Generation:**
   - 32 cryptographically secure bytes (`bin2hex(random_bytes(32))`), resulting in a 64-character hexadecimal token.
   - Stored in `email_verification_tokens` table as a SHA-256 hash (`token_hash`) with a 30-minute expiration: `DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)`.
   - Existing pending tokens for the user are removed before new token generation.
2. **Verification URL Structure:**
   - Canonical format: `{base_url}/assets/backend/verify_email.php?token={64_char_hex_token}`.
   - Base URL automatically resolves local development (`http://localhost/Kumo` or configured local) vs production (`https://kumo.ct.ws`).
3. **Endpoint Verification Workflow (`verify_email.php`):**
   - **GET Request:** Validates token syntax, checks expiration against `UTC_TIMESTAMP()`. Renders styled Kumo verification confirmation page with CSRF-protected form.
   - **POST Request:** Validates CSRF token, locks row (`FOR UPDATE`), verifies token validity, idempotently sets `email_verified_at = UTC_TIMESTAMP()`, purges token, and redirects to `login.html?verified=1`.
   - **Error Handling:** Expired, missing, malformed, or reused tokens redirect to `verify-email.html?error=invalid_token`.
4. **Resend Verification (`resend_verification.php`):**
   - Rate-limited to max 5 requests per 30 minutes per IP/email.
   - If account exists and is unverified, generates a fresh token and dispatches email.
   - Always returns a consistent status message to prevent email enumeration attacks.

---

## 8. Password Recovery & Reset Lifecycle

1. **Reset Request (`forgot_password.php`):**
   - Receives email address, checks existence in `userdata`.
   - Generates 64-char token, stores SHA-256 hash in `password_reset_tokens` with 30-minute UTC expiry: `DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)`.
   - Transports styled HTML email via unified SMTP mailer.
   - Always redirects to `reset-link-sent.html` regardless of account presence.
2. **Password Update (`resetpassword.php`):**
   - Validates token against `UTC_TIMESTAMP()`.
   - Enforces password complexity rules.
   - Updates password hash, increments `auth_version` (invalidating active sessions and remember tokens).
   - Cleans up used tokens and redirects to `login.html?reset=success`.

---

## 9. Database Architecture & Relationships

### Core Tables
1. **`userdata`:**
   - Primary user table storing credentials, display names, profile avatars, verification timestamps, and security flags.
   - Columns: `id` (PK), `username` (UQ), `full_name`, `email` (UQ), `email_verified_at`, `password`, `setup_completed`, `role`, `avatar_path`, `two_factor_enabled`, `two_factor_secret`, `auth_version`, `last_login_at`.
2. **`email_verification_tokens`:**
   - Tokens for unverified account verification.
   - Columns: `id` (PK), `user_id` (FK -> `userdata.id` ON DELETE CASCADE), `token_hash` (UQ CHAR(64)), `expires_at` (DATETIME), `created_at`.
3. **`password_reset_tokens`:**
   - Tokens for password recovery.
   - Columns: `id` (PK), `user_id` (FK -> `userdata.id` ON DELETE CASCADE), `token_hash` (UQ CHAR(64)), `expires_at` (DATETIME), `created_at`.
4. **`user_preferences`:**
   - User UI/UX customizations.
   - Columns: `id` (PK), `user_id` (FK -> `userdata.id` ON DELETE CASCADE), `theme`, `accent_color`, `density`, `reduce_motion`, `show_clouds`, `show_upcoming`, `show_recent_activity`, `show_progress`, `first_section`, `updated_at`.
5. **`clouds` & `class_schedules`:**
   - Workspace hierarchy and coursework schedule details.
6. **`notes` & `tasks`:**
   - Content and task tracking linked to specific clouds and users.
7. **`auth_rate_limits` & `remember_tokens`:**
   - Brute-force throttling and persistent device authentication.

### Database Migration Strategy
- **Fresh Installs:** `assets/database/schema.sql` creates all tables, primary keys, foreign key constraints, and default indexes.
- **Existing Databases:** `assets/database/upgrade_existing.sql` and `assets/database/upgrade_v2.sql` provide safe, additive, idempotent migrations using dynamic `INFORMATION_SCHEMA` checks to ensure zero data destruction.

---

## 10. Email Transport & Secrets Management

- **Shared Transport:** Socket-level authenticated SMTP client (`kumo_smtp_send`) in `email_verification.php`, unified via `kumo_send_email()` in `security.php`.
- **Supported Modes:** Port 587 with STARTTLS negotiation, Port 465 with implicit SSL.
- **Configuration Security:**
  - Stored in private uncommitted file `assets/backend/config.local.php`.
  - Credentials must never be logged, committed to version control, or rendered in client-side HTML/JS.
  - Separate environment dictionaries for `'local'` and `'production'` prevent accidental cross-talk between development and live infrastructure.

---

## 11. Functional Requirements & Acceptance Criteria

| Req ID | Description | Acceptance Criteria | Implementation Status |
|---|---|---|---|
| **REQ-AUTH-01** | Standard User Registration | Form requires Full Name, Username, Email, Password, Confirmation. Validates inputs, hashes password, saves to DB with `email_verified_at = NULL`. | **VERIFIED** |
| **REQ-AUTH-02** | Unverified Account Login Block | Unverified user attempting login receives `email_unverified` error and is presented a link to resend verification. | **VERIFIED** |
| **REQ-AUTH-03** | Email Verification Dispatch | After registration or resend, creates 64-char token in `email_verification_tokens` (expires in 30 mins) and sends HTML email. | **VERIFIED** |
| **REQ-AUTH-04** | Email Verification Execution | Clicking valid link confirms email, sets `email_verified_at = UTC_TIMESTAMP()`, deletes token, and permits login. | **VERIFIED** |
| **REQ-AUTH-05** | Token Reuse Prevention | Once a token has been verified, subsequent requests with the same token fail with `invalid_token`. | **VERIFIED** |
| **REQ-AUTH-06** | Expired Token Handling | Tokens accessed past 30-minute expiration window are rejected with `invalid_token`. | **VERIFIED** |
| **REQ-AUTH-07** | Resend Verification Flow | User can enter registered email on `verify-email.html` to receive a fresh verification link. Rate-limited to 5/30m. | **VERIFIED** |
| **REQ-AUTH-08** | Password Reset Flow | User requests reset link, receives email, accesses `new-password.php`, updates password; `auth_version` increments. | **VERIFIED** |
| **REQ-AUTH-09** | Dual Environment Config | `config.local.php` and `db.php` cleanly differentiate local XAMPP (`127.0.0.1`/`users`) from remote production (`kumo.ct.ws`). | **VERIFIED** |
| **REQ-AUTH-10** | UTF-8 Cleanliness | All PHP, HTML, CSS, and JS files free from UTF-8 BOM markers and mojibake characters. | **VERIFIED** |

---

## 12. Non-Functional Requirements

- **Performance:** Database queries strictly indexed on foreign keys and unique constraints; auth requests complete within < 250ms.
- **Security:** Strict prepared statements preventing SQL injection; CSRF tokens on all state-mutating POST requests; SameSite cookies.
- **Reliability:** Database updates and token consumption executed inside atomic MySQL transactions with automatic rollback on error.
- **Maintainability:** Pure native PHP without bloated vendor dependencies; centralized helpers for security, database, and email.

---

## 13. Confirmed Issues & Root Cause Analysis

1. **Issue 1: Broken Base URL Helper**
   - *Root Cause:* Previous `config.local.php` set `base_url = 'kumo.ct.ws'` without `https://`. `parse_url()` rejected it, causing `kumo_project_base_url()` to return empty string and breaking verification URL generation.
   - *Fix:* Enhanced `kumo_project_base_url()` with auto-scheme prefixing, structured local vs production domain detection, and dynamic local fallback.
2. **Issue 2: Database Schema Mismatches**
   - *Root Cause:* Existing database `userdata` lacked `full_name` and `email_verified_at`, and lacked `email_verification_tokens` table. `register.php` failed on insert. Furthermore, `upgrade_v2.sql` attempted `UPDATE userdata SET email_verified_at = created_at`, but `created_at` did not exist in legacy `userdata`.
   - *Fix:* Replaced `created_at` with `NOW()`, unified `upgrade_existing.sql`, and applied safe additive migration to local database.
3. **Issue 3: Inconsistent Email Transports**
   - *Root Cause:* Password recovery used PHP `mail()` via `kumo_send_email()`, while verification used socket SMTP via `kumo_smtp_send()`.
   - *Fix:* Unified `kumo_send_email()` in `security.php` to delegate directly to the authenticated SMTP implementation.
4. **Issue 4: UTF-8 BOM & Mojibake Corruption**
   - *Root Cause:* Previous PowerShell `Set-Content -Encoding UTF8` inserted 3-byte UTF-8 BOMs into `config.local.php` (breaking headers) and corrupted UTF-8 symbols in `auth/login.html`.
   - *Fix:* Removed all BOMs and rewrote `auth/login.html` with clean entities.
5. **Issue 5: Timezone Discrepancy in Password Reset**
   - *Root Cause:* PHP `time() + 1800` generated UTC strings while MariaDB `NOW()` ran in system local time (UTC+7), prematurely expiring reset tokens.
   - *Fix:* Aligned all token expiration and validation queries to `UTC_TIMESTAMP()`.
6. **Issue 6: Legacy Missing `clouds.updated_at` Column & Multiple Include Guard**
   - *Root Cause:* Legacy database lacked `updated_at` on `clouds`, causing SQL exceptions on dashboard loads. Additionally, `kumo_initial_char()` in `homepage.php` lacked an include guard.
   - *Fix:* Added `clouds.updated_at` check to `upgrade_existing.sql`, applied it to the database, and added `if (!function_exists('kumo_initial_char'))` in `homepage.php`.

---

## 14. Verification & Testing Matrix

| Test Case | Method | Expected Result | Actual Result |
|---|---|---|---|
| PHP Syntax Lint | `php -l` on all PHP files | Zero syntax errors | **PASSED (All files OK)** |
| JS Syntax Lint | `node -c` on all JS files | Zero syntax errors | **PASSED (All files OK)** |
| Database Connection | CLI & HTTP via `db.php` | Connects to MariaDB 10.4 | **PASSED** |
| Schema Additive Migration | `upgrade_existing.sql` | Adds required columns/tables | **PASSED** |
| Base URL Generation | Automated unit test | Returns absolute valid URL | **PASSED (`https://kumo.ct.ws` / `http://localhost/Kumo`)** |
| User Registration | Automated test runner | User created with `email_verified_at = NULL` | **PASSED** |
| Unverified Login Block | Automated test runner | Rejected with `email_unverified` | **PASSED** |
| Email Verification Token | Automated test runner | Token persisted and verified | **PASSED** |
| Verification Execution | Automated test runner | Updates `email_verified_at`, deletes token | **PASSED** |
| Token Single-Use | Automated test runner | Reused token rejected | **PASSED** |
| Expired Token Rejection | Automated test runner | Expired token rejected | **PASSED** |
| Verified Login Gate | Automated test runner | Verified user logs in successfully | **PASSED** |
| Password Reset Flow | Automated test runner | Token created, verified, password updated | **PASSED** |
| HTTP Status of Auth Pages | HTTP GET via port 8000 | Returns HTTP 200 | **PASSED** |
| CSRF Token Endpoint | HTTP GET via `csrf_token.php` | Returns 64-char hex token | **PASSED** |
| Dashboard Empty State Render | PHP simulated render (User 10) | Clean empty state with compact action | **PASSED (30.9 KB, 0 errors)** |
| Dashboard Populated State Render | PHP simulated render (User 7 & 8) | Auto-fill grid, stats, and task cards | **PASSED (35.2 KB, 0 errors)** |
| Asset Inventory Preservation | Filesystem & size audit | 22 assets completely preserved | **PASSED (0 modified/deleted)** |
| Typography Upgrades | CSS audit across auth & main | 30-34px headers, 16px inputs, 14-16px labels | **PASSED** |
| Browser Subagent Automated UI | Playwright Subagent | Inspect pages in headless browser | **BLOCKED (CDN 404 on Playwright driver)** |

---

## 15. Operational & Deployment Guide

### Applying Database Migration on Existing Production / Staging
1. Open phpMyAdmin or MySQL CLI connected to the target database.
2. Import `assets/database/upgrade_existing.sql`.
3. Verify that `userdata` has `full_name` and `email_verified_at`, and that `email_verification_tokens` table exists.
4. Legacy user accounts are automatically marked verified to maintain continuity.

### Environment Configuration (`assets/backend/config.local.php`)
Ensure `config.local.php` is created outside git with the following structure:
```php
<?php
declare(strict_types=1);

return [
    'environment' => 'auto',
    'base_url' => [
        'local' => 'http://localhost/Kumo',
        'production' => 'https://kumo.ct.ws',
    ],
    'database' => [
        'local' => [
            'host' => '127.0.0.1',
            'username' => 'root',
            'password' => '',
            'name' => 'users',
        ],
        'production' => [
            'host' => 'sql303.infinityfree.com',
            'username' => 'your_prod_user',
            'password' => 'your_prod_password',
            'name' => 'your_prod_db',
        ],
    ],
    'google' => [
        'client_id' => 'your_google_client_id',
        'client_secret' => 'your_google_client_secret',
        'redirect_uri' => 'https://kumo.ct.ws/assets/backend/google_callback.php',
    ],
    'mail_from' => 'cloudmail.kumo@gmail.com',
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'cloudmail.kumo@gmail.com',
        'password' => 'your_smtp_app_password',
        'encryption' => 'tls',
    ],
];
```

## 15. Guided Tour and Motion

- **Confirmed tour defect:** `KumoTour` was only a class definition; no dashboard code created an instance or exposed a launch control. Its markup was unstyled, it had no Back or Finish distinction, and closing it reopened onboarding. The tour therefore was not a usable product flow.
- The dashboard now creates one `KumoTour` instance and offers a replayable **Help & tour** section in Settings. The guide covers dashboard and navigation controls, filters unavailable targets, aligns its coachmark to the viewport, and restores focus and sidebar state. Back, Next, Skip, Finish, close, and Escape have distinct behavior. Only Finish saves `kumoTourComplete` in local storage; Skip and close do not.
- GSAP 3.15.0 is vendored at `assets/JS/vendor/gsap.min.js` with the upstream license notice. `assets/JS/gsap-animations.js` adds brief landing, dashboard, auth, and editor entrances plus landing feature-row reveals. Modal, help-popover, and tour transitions use the same runtime. No build system or plugin is required.
- Animations are progressive enhancements, use opacity and transforms, respect both the OS reduced-motion setting and Kumo's saved reduce-motion setting, and keep content visible if GSAP is unavailable. Existing theme tokens style the tour in light and dark modes.
- **Skill guidance used:** official Codex `greensock/gsap-skills` installation succeeded. The installed `gsap-core`, `gsap-timeline`, `gsap-performance`, and `gsap-scrolltrigger` instructions were inspected; core, timeline, and performance guidance informed this implementation. ScrollTrigger and other plugins were unnecessary.
- **Verification status:** the logged-in local dashboard was exercised in the browser. The complete 10-step tour advanced through its final step, Back returned to the prior step, and Finish closed the tour. Replay from Settings worked; Skip, the close button, and Escape each removed the overlay. The dashboard rail collapsed and expanded, and the main dashboard rendered without card overlap at the browser's current 1102 × 600 viewport. This was a focused live check, not a full viewport/theme regression matrix.

## 16. Current Product Design and Theme Audit

- The design archive's project source files match the active project for the PRD, dashboard, theme styles, landing, and authentication files. Its mockups are older visual references and are not used as targets for this redesign.
- Product direction is a calm, editorial “field desk” organized around Clouds, tasks, notes, and schedules. The dashboard uses a persistent workspace rail, a welcoming accent wash, a compact metric strip, and clearer Cloud tiles. The design retains the current Kumo palette, native PHP routes, and existing product controls.
- Authenticated desktop navigation opens as a persistent, collapsible workspace rail so Cloud navigation and account tools remain discoverable. On narrow screens it starts closed and retains the existing overlay drawer interaction. The content canvas shifts to the available width on desktop and returns to full width on mobile.
- Theme initialization now uses the shared `assets/JS/theme.js` on the authenticated dashboard. Server-rendered preference meta tags take precedence there, while public pages use saved browser preferences and default to light. This removes the dashboard-only inline theme initializer and avoids a second theme implementation.
- Six required design skill commands completed successfully; GSAP was already installed for Codex and was reused. Instructions read include Uizze UI workflow, Image to UI, iOS Design, Ponytail, Caveman, Apple Design, and GSAP core, timeline, performance, and ScrollTrigger guidance. The Image to UI skill was invoked, but its old mockup target method was intentionally not used under the current task's no-mockup constraint.
- GSAP now owns the landing and authentication entrance effects; duplicate CSS keyframes were removed to avoid competing transforms. Cache-busting versions were incremented for the edited CSS files.
- **Verification:** changed JavaScript and PHP syntax passed. CSS brace and parenthesis balance passed. The archive asset-path audit checked 106 paths and found no archived asset paths missing from the active project. Public sign-in and landing pages rendered in the local browser. After login, the live dashboard, rail collapse/expand, Settings, and the full guided tour were checked at 1102 × 600. The Reduce motion setting's label and helper text were visually separated after that live check. The dashboard and Settings were checked in both light and dark; dark persisted through reload, then the original saved light preference was restored and confirmed after reload. The five specified viewport sizes, dark mode on other page types, browser console/network diagnostics, and a full CSS parser regression remain unverified.
- **Notebook and regular-user Cloud workspace continuation:** the note editor now identifies the active Cloud, gives the notebook a clear title and purpose, improves save-state and action readability, and gives the writing surface more comfortable type and spacing. The regular-user Cloud view now has a clearer hierarchy, parent navigation, a real child-space count, a more useful task empty state, and a focused notebook action. Existing Quill formatting, image resize, CSRF, ownership checks, task handlers, and note-save route remain in place. Owner/admin roles are outside the current redesign scope at the user's direction.
- **Notebook theme issue found and fixed:** Quill's Snow stylesheet loaded after `editor.css`, so Quill's default icon colors overrode Kumo's theme-aware toolbar colors. The stylesheet order now loads Quill first and Kumo's editor styles after it. The logged-in note editor was visually checked in light and dark at 1280 × 720; dark toolbar contrast improved after the order change. The existing light preference was restored and confirmed in the dashboard. No note content was submitted during the check.
- **Cloud grid issue found and fixed:** populated Cloud pages had grid rows compressed enough to crowd child-space cards. Explicit content-sized row tracks now keep cards separate. A 16-space Cloud was visually checked at 1280 × 720 after the fix; the task-level empty state and notebook action were also checked in a leaf Cloud. No task or note data was modified during these checks.
- **Remaining validation for this continuation:** the requested five-viewport matrix, note editor mobile rendering, browser console/network diagnostics, and full CSS parser validation remain unverified. Role-specific dashboards were left unchanged as requested.
- **Final regular-user pass:** homepage and notebook PHP syntax checks passed; `main.js`, `theme.js`, and the GSAP animation script parsed successfully. Structural CSS checks passed for main, editor, landing, and authentication stylesheets. The archive audit rechecked 106 paths with zero missing. The final live browser inspection covered a nested Cloud, a 16-space Cloud grid, and a task-level Cloud with its add-task and notebook controls.
