<?php
require_once 'config/db.php';
require_once 'includes/paths.php';

require_once 'includes/session.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $tenant_name = trim($_POST['tenant_name'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = normalize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($tenant_name === '' || $name === '' || $email === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($passwordError = password_policy_error($password)) {
        $error = $passwordError;
    } else {
        $slug = normalize_slug($tenant_name);

        if ($slug === '') {
            $error = 'Organization name must contain letters or numbers.';
        }

        if ($error === '') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO tenants (name, slug, status)
                VALUES (?, ?, 'active')
            ");
            $stmt->execute([$tenant_name, $slug]);

            $tenant_id = $pdo->lastInsertId();

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (tenant_id, name, email, password_hash, role, status)
                VALUES (?, ?, ?, ?, 'tenant_admin', 'active')
            ");
            $stmt->execute([
                $tenant_id,
                $name,
                $email,
                $password_hash
            ]);

            $user_id = $pdo->lastInsertId();

            $pdo->commit();

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user_id;
            $_SESSION['tenant_id'] = $tenant_id;
            $_SESSION['role'] = 'tenant_admin';
            $_SESSION['name'] = $name;

            header('Location: admin/dashboard.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();

            if ($e->getCode() === '23000') {
                $error = 'This organization or email already exists.';
            } else {
                $error = 'Registration failed. Please try again.';
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
    <title>Register - Course Portal</title>
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

            <h1>Start a private learning space for your organization.</h1>
            <p class="mt-3">
                Create the tenant and first admin account. After registration you can add instructors,
                students, courses, enrollments, and resources.
            </p>

            <div class="metric mt-4">
                <strong>Cloud-ready</strong>
                <small>Structured for deployment with isolated tenant data.</small>
            </div>

            <div class="metric">
                <strong>Fast setup</strong>
                <small>One form creates the organization and its admin user.</small>
            </div>
        </section>

        <section class="auth-form">
            <h2>Register organization</h2>
            <p class="text-muted mb-4">Create the first administrator account.</p>

            <?php if ($error): ?>
                <div class="auth-error mb-3">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label">Organization / School Name</label>
                    <input type="text" name="tenant_name" class="form-control" placeholder="Example Academy" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Admin Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Full name" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="text" inputmode="email" autocomplete="email" name="email" class="form-control" placeholder="admin@example.com" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Create a password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Register
                </button>
            </form>

            <p class="mt-4 mb-0 text-muted">
                Already have an account?
                <a href="login.php">Login</a>
            </p>

            <p class="mt-2 mb-0 text-muted">
                Joining as a student or professor?
                <a href="join.php">Join organization</a>
            </p>
        </section>
    </main>
</body>
</html>






