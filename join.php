<?php
require_once 'includes/session.php';
require_once 'config/db.php';
require_once 'includes/paths.php';
require_once 'includes/realtime.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $organization_code = normalize_slug($_POST['organization_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = normalize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $allowed_roles = ['student', 'instructor'];

    if ($organization_code === '' || $name === '' || $email === '' || $password === '' || $role === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($role, $allowed_roles, true)) {
        $error = 'Please select Student or Professor.';
    } elseif ($passwordError = password_policy_error($password)) {
        $error = $passwordError;
    } else {
        $stmt = $pdo->prepare("
            SELECT id, name
            FROM tenants
            WHERE slug = ?
            AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$organization_code]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $error = 'Organization code was not found.';
        } else {
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO users (tenant_id, name, email, password_hash, role, status)
                    VALUES (?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([
                    (int) $tenant['id'],
                    $name,
                    $email,
                    $password_hash,
                    $role,
                ]);

                $user_id = (int) $pdo->lastInsertId();

                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_id;
                $_SESSION['tenant_id'] = (int) $tenant['id'];
                $_SESSION['role'] = $role;
                $_SESSION['name'] = $name;

                mark_user_online($pdo, 'join');
                log_activity($pdo, 'self_signup', $name . ' joined as ' . ($role === 'instructor' ? 'professor' : 'student') . '.');
                notify_role($pdo, 'tenant_admin', 'New user joined', $name . ' joined ' . $tenant['name'] . '.', 'user');

                if ($role === 'instructor') {
                    header('Location: instructor/dashboard.php');
                } else {
                    header('Location: student/dashboard.php');
                }
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'This email already exists in this organization.';
                } else {
                    $error = 'Signup failed. Please try again.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Join Organization - Course Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset_path('assets/css/app.css')); ?>" rel="stylesheet">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-panel">
            <div class="brand-mark">
                <span>CR</span>
                Course Resource Portal
            </div>

            <h1>Join your class portal with an organization code.</h1>
            <p class="mt-3">
                Students and professors can create their own accounts during a live class demo.
            </p>

            <div class="metric mt-4">
                <strong>For students</strong>
                <small>Join the organization, then access enrolled courses and resources.</small>
            </div>

            <div class="metric">
                <strong>For professors</strong>
                <small>Create courses, upload materials, and send live updates.</small>
            </div>
        </section>

        <section class="auth-form">
            <h2>Join organization</h2>
            <p class="text-muted mb-4">Ask the admin for the organization code.</p>

            <?php if ($error): ?>
                <div class="auth-error mb-3">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrf_field(); ?>

                <div class="mb-3">
                    <label class="form-label">Organization Code</label>
                    <input type="text" name="organization_code" class="form-control" placeholder="demo-academy" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Your name" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="text" inputmode="email" autocomplete="email" name="email" class="form-control" placeholder="name@example.com" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="At least 8 characters" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">I am joining as</label>
                    <select name="role" class="form-control" required>
                        <option value="">Select role</option>
                        <option value="student">Student</option>
                        <option value="instructor">Professor</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Create Account
                </button>
            </form>

            <p class="mt-4 mb-0 text-muted">
                Already have an account?
                <a href="login.php">Login</a>
            </p>

            <p class="mt-2 mb-0 text-muted">
                Starting a new organization?
                <a href="register.php">Register organization</a>
            </p>
        </section>
    </main>
</body>
</html>
