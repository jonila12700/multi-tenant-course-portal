<?php
require_once 'includes/session.php';
require_once "config/db.php";
require_once "includes/paths.php";
require_once "includes/realtime.php";

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $organization_code = normalize_slug($_POST['organization_code'] ?? '');
    $email = normalize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($organization_code === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Organization code and a valid email are required.';
    } elseif (too_many_attempts('login_' . $organization_code . '_' . $email, 10, 300)) {
        $error = 'Too many login attempts. Please wait a few minutes and try again.';
    } else {
        record_attempt('login_' . $organization_code . '_' . $email);

    $stmt = $pdo->prepare("
        SELECT
            users.*,
            tenants.status AS tenant_status
        FROM users
        INNER JOIN tenants ON tenants.id = users.tenant_id
        WHERE users.email = ?
        AND tenants.slug = ?
        AND users.status = 'active'
        AND tenants.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$email, $organization_code]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['tenant_id'] = $user['tenant_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];

        $stmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ? AND tenant_id = ?");
        $stmt->execute([(int) $user['id'], (int) $user['tenant_id']]);

        mark_user_online($pdo, 'login');
        log_activity($pdo, 'login', $user['name'] . ' signed in.');

        header("Location: " . redirect_for_role($user['role']));

        exit();
    } else {
        $error = 'Invalid email or password.';
    }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Course Portal</title>
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

        <h1>Manage courses, resources, and students in one cloud-ready portal.</h1>
        <p class="mt-3">
            Secure access for admins, instructors, and students with tenant-based data separation.
        </p>

        <div class="metric mt-4">
            <strong>Multi-tenant</strong>
            <small>Each organization sees only its own users, courses, and files.</small>
        </div>

        <div class="metric">
            <strong>Live class demo</strong>
            <small>Admins, professors, and students can sign in at the same time and see live updates.</small>
        </div>
    </section>

    <section class="auth-form">
        <h2>Welcome back</h2>
        <p class="text-muted mb-4">Sign in to continue to your dashboard.</p>

        <?php if ($error): ?>
            <div class="auth-error mb-3">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
                <?php echo csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">Organization Code</label>
                <input type="text" name="organization_code" class="form-control" placeholder="demo-academy" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="text" inputmode="email" autocomplete="email" name="email" class="form-control" placeholder="name@example.com" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                Login
            </button>
        </form>

        <p class="mt-4 mb-0 text-muted">
            Don't have an account?
            <a href="join.php">Join organization</a>
        </p>

        <p class="mt-2 mb-0 text-muted">
            Need a new organization?
            <a href="register.php">Register organization</a>
        </p>

        <p class="mt-2 mb-0 text-muted">
            Forgot your password?
            <a href="forgot_password.php">Reset password</a>
        </p>

        <p class="mt-2 mb-0 text-muted">
            Presenting the project?
            <a href="demo.php">Open demo guide</a>
        </p>
    </section>
</main>

</body>
</html>





