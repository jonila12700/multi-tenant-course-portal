# InfinityFree Deployment Checklist

## 1. Database

1. Create a MySQL database in the InfinityFree control panel.
2. Import `courseportal_full_latest.sql` using phpMyAdmin.
3. Import `courseportal_security_migration.sql` after the base import.
4. Confirm tables include `password_resets` and tenant-scoped unique indexes.

## 2. Configuration

1. Copy `config/local.example.php` to `config/local.php`.
2. Set InfinityFree values:
   - host: `sqlXXX.infinityfree.com`
   - dbname: `if0_XXXXXXX_courseportal`
   - username: `if0_XXXXXXX`
   - password: database password
3. Do not upload `.env` or local credentials to GitHub.

## 3. FTP Upload

Upload the project contents to the InfinityFree `htdocs` folder. Keep:

- `.htaccess`
- `uploads/.htaccess`
- `uploads/.gitkeep`
- `assets/`
- `admin/`, `instructor/`, `student/`, `superadmin/`, `realtime/`, `includes/`, `config/`

Do not publish old ZIP exports or unnecessary SQL dumps after setup.

## 4. Permissions

Set `uploads/` as writable. Uploaded files are stored under `uploads/tenant_{id}/` and guarded by PHP download checks plus `.htaccess`.

## 5. Verification

Test these flows online:

- Register tenant
- Login/logout with organization code
- Forgot/reset password
- Create instructor and student
- Create course
- Enroll student
- Upload resource
- Download as allowed user
- Try denied download as unrelated user
- Student progress toggle
- Assignment submit/review
- Responsive mobile layout

## 6. Production Notes

- Use HTTPS.
- Configure reliable mail delivery for password reset links.
- Rotate credentials after initial deployment.
- Remove demo accounts from imported data.
- Keep backups before schema migrations.
