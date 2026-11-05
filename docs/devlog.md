# Development Log

## 2026-08-04

### Project Initialization

- Created the GitHub repository.
- Set up the initial project structure.
- Decided on the technology stack:
  - PHP
  - MySQL
  - HTML
  - CSS
  - JavaScript

---

## 2026-08-07

### Landing Page

- Designed and implemented the landing page.
- Created Kumo branding and logo integration.
- Added hero section with application description.
- Added navigation buttons for Login and Sign Up.
- Implemented subtle UI animations and hover effects.

### Authentication System

- Redesigned the login page.
- Created the register page.
- Implemented the forgot password page.
- Created the reset link confirmation page.
- Unified all authentication pages using a shared `auth.css`.
- Refactored authentication styling into reusable components.
- Added a shared `auth.js` for reusable authentication functionality.
- Added navigation between all authentication pages.
- Added a home button to return to the landing page.
- Improved animations and overall UI consistency.

### Project Structure

- Reorganized project folders.
- Grouped authentication pages into a dedicated `auth` directory.
- Consolidated shared assets into reusable CSS and JavaScript files.
- Improved maintainability by reducing duplicated code.

### Version Milestone

**v0.1.0**

Completed the initial frontend prototype, including:

- Landing page
- Login
- Register
- Forgot Password
- Reset Link Sent
- Shared authentication design system

---

## 2026-08-07

**v0.1.1**

### Main Dashboard

- Designed and implemented the first Kumo dashboard prototype.
- Created the main dashboard layout and sidebar navigation.
- Added expandable cloud categories.
- Added support for creating new clouds.
- Added functionality for removing clouds.
- Added functionality for renaming clouds.
- Designed the initial cloud creation interface.
- Added empty dashboard states for new users.
- Designed the dashboard around the concept of users arranging their own "sky".
- Added responsive considerations for future mobile support.

### Kumo Organization Concept

- Defined clouds as the primary organizational system within Kumo.
- Established the concept of clouds containing different categories of information.
- Explored using Personal, Education, Projects, and Expenses as different cloud types.
- Designed semesters as sub-clouds within Education.
- Designed classes as categories within semesters.
- Planned for notes, reminders, and other content to exist inside classes.
- Planned Expenses as a dedicated cloud for tracking monthly expenses.
- Established the idea that Kumo should remain useful beyond university and adapt to work and personal life.

### Backend & Database

- Set up XAMPP for local PHP development.
- Configured Apache and MySQL/MariaDB.
- Created the initial MySQL database.
- Created the `userdata` table for user accounts.
- Created the PHP database connection.
- Connected the frontend registration form to PHP.
- Implemented server-side account registration.
- Added password hashing using `password_hash()`.
- Added duplicate email checking.
- Connected the login form to PHP.
- Implemented password verification using `password_verify()`.
- Implemented PHP sessions for authenticated users.
- Added session-based authentication protection through `auth.php`.
- Implemented logout functionality.
- Connected successful authentication to the main dashboard.
- Tested the complete local registration and login flow.

### Authentication Flow

The initial authentication flow is now:

```text
Register
   ↓
register.php
   ↓
MySQL
   ↓
userdata
   ↓
Login
   ↓
login.php
   ↓
password verification
   ↓
PHP session
   ↓
Kumo Dashboard
```

### 2026-08-29

**v0.1.2**

### Authentication System Enhancements

- Fixed backend validation logic in register.php to accurately enforce constraints and redirect errors.
- Fixed login logic for improved stability and secure authentication routing.
- Implemented frontend URL parameter parsing to bridge PHP server errors with the HTML UI.
- Added a dynamic password requirement box to explicitly guide users on complexity rules during account creation.

### UI/UX & Frontend Updates

- Overhauled and redesigned elements of the UI to strictly adhere to Kumo's soft, minimalist, and airy design language.
- Replaced plain text validation errors with animated, dynamically injected alert boxes for a smoother user experience.
- Refactored dashboard column layouts using CSS Grid (minmax) to ensure perfect 50/50 structural symmetry regardless of content size.
- Implemented the foundation for interactive, multi-step modals with sliding transitions (e.g., the onboarding flow and contextual popovers).
- Landing page re-design.

### 2026-09-10

**v0.1.3**

### Week & Task Management

- Implemented an automated class schedule system to generate deeply nested folders for semesters (Weeks 1-16, Midterms, and Finals).
- Built and integrated the backend tasks database table.
- Added task management functionality within the dashboard allowing users to add tasks, set due dates, and toggle completion statuses natively.
- Fixed MySQL recursive fetching behavior by sorting clouds via ORDER BY id ASC to maintain proper chronological order during bulk folder generation.

### Rich-Text Note System

- Created a dedicated, distraction-free writing environment (note_editor.php).
- Integrated Quill.js (v1.3.6) as the core rich-text editor engine.
- Implemented quill-image-resize-module for native, draggable image scaling within notes.
- Added multi-font support mapped to Kumo's aesthetic (Poppins, Lora, Roboto Mono).
- Built robust backend logic (save_note.php) to seamlessly check for existing notes, updating or inserting them smoothly without creating duplicate database rows.
- Updated the notes database table to track and display the last_updated timestamp in the editor UI.

### UI/UX & Code Refactoring

- Built a custom localStorage script for the sidebar to persist folder expansion states, decoupling the UI from page reloads.
- Refined the dashboard interface by adding a compact, elegantly styled "Distraction-Free Notebook" entry card.
- Extracted heavy editor CSS into a dedicated editor.css file to maintain clean architecture and modularity.
