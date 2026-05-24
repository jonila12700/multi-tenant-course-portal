# Multi-Tenant Course Portal

Production-ready PHP/MySQL course management portal for schools, training teams, and instructors. Each organization is a tenant with isolated users, courses, enrollments, resources, assignments, progress, notifications, and live class activity.

## Features

- Multi-tenant tenant/admin/instructor/student workflows
- Platform Super Admin overview
- Tenant-aware authentication, routing, dashboards, and CRUD
- Tenant-aware login, registration, join-by-organization-code, forgot/reset password
- Course CRUD, instructor assignment, publishing, archiving, and deletion
- Student enrollment management and progress tracking
- Resource uploads with per-tenant storage and guarded downloads
- Assignments, submissions, grading, announcements, live quizzes, notifications, and live chat
- Responsive Bootstrap-based UI for mobile, tablet, and desktop
- CSRF protection, password hashing, secure cookies, guarded uploads, and security headers

## Tech Stack

- PHP 8.x compatible procedural MVC-style structure
- MySQL or MariaDB with PDO
- Bootstrap 5
- Apache `.htaccess` routing/security rules
- XAMPP local development, InfinityFree-compatible deployment

## Installation

1. Copy the project into your web root, for example `C:\xampp\htdocs\MultiTentant`.
2. Create a MySQL database.
3. Import `courseportal_full_latest.sql`.
4. Run `courseportal_security_migration.sql`.
5. Copy `config/local.example.php` to `config/local.php`.
6. Update database credentials in `config/local.php`.
7. Open `http://localhost/MultiTentant`.

## Deployment

For InfinityFree:

1. Create a MySQL database in InfinityFree control panel.
2. Import the base SQL and security migration in phpMyAdmin.
3. Upload project files through FTP.
4. Keep `config/local.php` on the server only and never commit it.
5. Ensure `uploads/` is writable.
6. Verify `.htaccess` is uploaded and Apache rules are enabled.
7. Test login, registration, file downloads, password reset, dashboards, and tenant isolation.

## Environment

Use `.env.example` as a deployment checklist. The application reads real database values from `config/local.php` first, then from environment variables when `local.php` does not exist.

## Screenshots

Add screenshots after deployment:

- Login and register screen
- Tenant admin dashboard
- Instructor course/resource dashboard
- Student course view
- Super Admin tenant overview

## Production Notes

- Run the security migration before enabling password reset.
- Remove demo SQL dumps and archives from public hosting when no longer needed.
- Configure a real mail provider for password reset delivery. Localhost shows a reset preview only for development.
- Use HTTPS so secure session cookies are enforced by browsers.
- Keep `config/local.php`, `.env`, SQL exports, and ZIP archives out of GitHub.
- Back up the database before running migrations or deleting courses.

## Scalability Recommendations

- Add database indexes for every high-volume tenant query.
- Move uploads to object storage when storage grows.
- Add server-side pagination for users, courses, resources, submissions, and activity feeds.
- Replace polling realtime features with WebSockets or a queue-backed event stream.
- Add background jobs for email, notifications, and report generation.

## Security Recommendations

- Enforce HTTPS in production.
- Rotate database credentials before going live.
- Disable public access to SQL/ZIP exports.
- Use least-privilege database users.
- Add server-level rate limiting for login and password reset endpoints.
- Review uploaded file MIME handling and consider virus scanning for public deployments.
