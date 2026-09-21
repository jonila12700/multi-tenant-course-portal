# Thesis Security and Multi-Tenancy Audit

## Scope and method

This hardening pass reviewed every PHP file in the repository. The review traced each SQL `SELECT`, `INSERT`, `UPDATE`, and `DELETE`, each identifier accepted from a request, every role-protected route, and both upload/download flows. It also verified prepared statements, CSRF checks on state-changing forms, password hashing and verification, session ID regeneration, cookie flags, throttling, and file validation. No database schema change was required.

### PHP files inspected

- Root: `ajax_search.php`, `demo.php`, `download.php`, `download_submission.php`, `forgot_password.php`, `index.php`, `join.php`, `login.php`, `logout.php`, `register.php`, `reset_password.php`.
- `admin/`: `courses.php`, `dashboard.php`, `edit_course.php`, `enrollments.php`, `index.php`, `reports.php`, `users.php`.
- `config/`: `db.php`, `local.example.php`.
- `includes/`: `footer.php`, `header.php`, `paths.php`, `realtime.php`, `session.php`, `sidebar_admin.php`.
- `instructor/`: `announcements.php`, `assignments.php`, `create_course.php`, `dashboard.php`, `index.php`, `live_quiz.php`, `progress.php`, `resources.php`, `submissions.php`, `upload_resource.php`.
- `realtime/`: `feed.php`, `ping.php`, `quiz_results.php`, `send_message.php`.
- `student/`: `announcements.php`, `assignments.php`, `dashboard.php`, `index.php`, `live_quiz.php`, `view_course.php`.
- `superadmin/`: `dashboard.php`.

## Security model

### Tenant isolation

After authentication, the application stores the authenticated user's tenant in `$_SESSION['tenant_id']`. Tenant-owned queries bind that server-side value rather than accepting a tenant ID from `GET` or `POST`. Child/parent joins use both the object relationship and matching tenant ownership (for example, `courses.id = resources.course_id` together with `courses.tenant_id = resources.tenant_id`). Identifiers supplied by a client are only selectors inside this tenant boundary.

The `super_admin` dashboard is the intentional exception: it is platform-wide and is protected by `require_role('super_admin')`. Public registration, organization join, login, and password recovery must identify a tenant before a session exists, so they resolve it from the unique organization code and then bind all account operations to the resulting tenant.

### Role authorization

`require_login()` verifies that the session contains a user, tenant, and role. `require_role()` calls it and returns HTTP 403 if the authenticated role is not allowed. Admin, instructor, student, super-admin, real-time, search, and download routes now consistently use these helpers. Authorization then narrows objects further: instructors must own the related course, students must have an active enrollment (or own their submission), tenant administrators remain within their tenant, and super administrators only access their intended platform dashboard.

### File isolation

Uploads retain the existing `uploads/tenant_<session tenant ID>/` layout, with submissions beneath a tenant-specific `submissions/` directory. Upload endpoints validate the authenticated role, course ownership/enrollment, PHP upload provenance, size, extension, and detected MIME type; stored filenames are generated rather than client-controlled. The upload tree denies direct HTTP serving, forcing access through `download.php` or `download_submission.php`. Those controllers tenant-scope the database object, enforce role/object authorization, resolve the canonical filesystem path, require it to remain inside the current tenant's upload root, and sanitize the response filename.

## Problems found and changes made

1. **Inconsistent route guards.** Several pages duplicated session/role conditionals and redirected unauthorized users to login. They now use `require_role()` so server-side denial is consistent and preserves the existing permission model. Directory index routes are protected before redirecting.
2. **Admin course creation trusted an instructor ID and status from POST.** The create path now allow-lists visibility and proves that a selected instructor is active, has the instructor role, and belongs to the session tenant before inserting the course.
3. **Course resource progress IDOR.** A student viewing one course could submit another resource ID from a different course in the same tenant if enrolled there. The mutation now also requires the resource to belong to the course in the current URL and explicitly repeats the enrollment tenant boundary.
4. **Direct upload-tree access.** The previous Apache policy blocked executable extensions but allowed uploaded documents to be requested directly, bypassing controller authorization. The upload tree now denies all direct HTTP access; authorized downloads continue through the controllers.
5. **Upload provenance and filename controls.** Both upload paths now require `is_uploaded_file()`. Submission display filenames have control characters removed, and download response filenames are reduced to a safe character set to prevent malformed response headers.
6. **Notification recipient defense in depth.** User notifications now use an `INSERT ... SELECT` constrained to a user in the session tenant. Enrollment notifications join courses and users on matching tenant IDs and target only active student accounts.
7. **Password-reset update scope.** Marking a reset token used now includes reset ID, tenant ID, user ID, and unused status rather than updating on the reset ID alone.
8. **Verified existing protections.** Tenant filters and tenant-equality join predicates were already present throughout course, enrollment, resource, assignment, submission, quiz, progress, announcement, search, reporting, and realtime reads. State-changing browser requests use CSRF tokens. PDO prepared statements are used for request-derived values. Authentication uses `password_hash()`/`password_verify()`, regenerates session IDs after login/signup, configures HttpOnly and SameSite cookies (and Secure under HTTPS), and throttles login/password-reset attempts.

## Manual isolation and authorization test plan

Use two active organizations, **Organization A** and **Organization B**, containing equivalent test users and data. Record HTTP status, response body, and database state without changing session values manually. Substitute real fixture IDs in the requests. These cases are a plan only; no Actual Result or PASS/FAIL is claimed here.

### TC-01: Organization A attempts to read Organization B data

**Preconditions**

- A tenant admin, instructor, and student exist in Organization A and are signed in one at a time.
- Organization B has a course, resource, assignment, submission, enrollment, quiz, and announcement; their IDs are known to the tester.

**Steps**

1. While authenticated to Organization A, replace IDs in relevant GET routes (course view/edit, submissions, quiz results, search-derived links) with Organization B IDs.
2. Repeat for each applicable Organization A role.
3. Inspect returned HTML/JSON and application logs; do not alter the session tenant ID.

**Expected result**

- No Organization B data is returned. Object pages respond with 403/404 or redirect to an authorized list/dashboard, and JSON endpoints return an error without tenant data.

### TC-02: Organization A attempts to update Organization B data

**Preconditions**

- The two organizations and known Organization B course, resource, assignment, submission, enrollment, quiz, and progress IDs exist.
- A valid Organization A CSRF token is available so the test isolates authorization from CSRF behavior.

**Steps**

1. Submit each relevant Organization A update form while replacing its object ID with the corresponding Organization B ID.
2. Include the valid Organization A CSRF token and otherwise valid field values.
3. Query Organization B records through its own authorized account or directly in the test database.

**Expected result**

- Zero Organization B rows change. The application rejects or safely ignores each out-of-tenant identifier.

### TC-03: Organization A attempts to delete Organization B data

**Preconditions**

- An Organization B course suitable for deletion testing exists and its ID is known.
- An Organization A tenant administrator has a valid session and CSRF token.

**Steps**

1. Submit the course deletion request from Organization A using the Organization B course ID, valid CSRF token, and required confirmation text.
2. Re-open the Organization B course using an Organization B authorized account or inspect the database.

**Expected result**

- The Organization B course and its dependent records remain unchanged because the delete predicate includes Organization A's session tenant ID.

### TC-04: Organization A attempts to download Organization B resource

**Preconditions**

- Organization B has a stored file resource and submission, with IDs and physical paths known to the tester.
- Organization A has authenticated admin, instructor, and student accounts.

**Steps**

1. Request `download.php?id=<organization-b-resource-id>` under each Organization A session.
2. Request `download_submission.php?id=<organization-b-submission-id>` under each Organization A session.
3. Attempt to request the known file path directly below `/uploads/tenant_<organization-b-id>/`.
4. Try encoded traversal components in the URL and, in a controlled fixture, a database file path containing traversal syntax.

**Expected result**

- Controller requests do not return the file (404/403), direct upload URLs are denied by the web server, and canonical-path containment rejects traversal or paths outside Organization A's upload root.

### TC-05: Student attempts to access Admin functionality

**Preconditions**

- A student is authenticated and has a valid student session.
- Admin route URLs and a valid student CSRF token are available.

**Steps**

1. Directly request every route beneath `/admin/`, including `/admin/index.php`.
2. Submit crafted POST requests for creating/changing users, courses, and enrollments using the student token.
3. Request `/superadmin/dashboard.php` and instructor mutation pages.

**Expected result**

- Every protected page returns HTTP 403 before reading or changing protected data; no user, course, enrollment, or other privileged record changes.
