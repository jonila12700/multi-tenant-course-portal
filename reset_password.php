<?php
require_once 'includes/session.php';
require_once 'config/db.php';
require_once 'includes/paths.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = '';
$success = '';
$reset = null;

if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = $pdo->prepare("
        SELECT password_resets.*, users.email
        FROM password_resets
        INNER JOIN users ON users.id = password_resets.user_id
            AND users.tenant_id = password_resets.tenant_id
        WHERE password_resets.token_hash = ?
        AND password_resets.used_at IS NULL
        AND password_resets.expires_at > NOW()
        AND users.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([hash('sha256', $token)]);
    $reset = $stmt->fetch();
}

if (!$reset) {
    $error = 'This reset link is invalid or expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    verify_csrf_token();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif ($passwordError = password_policy_error($password)) {
        $error = $passwordError;
    } else {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['user_id'], (int) $reset['tenant_id']]);

        $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
        $stmt->execute([(int) $reset['id']]);

        $pdo->commit();
        $success = 'Password updated. You can now sign in.';
        $reset = null;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - Course Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo h(asset_path('assets/css/app.css')); ?>" rel="stylesheet">
</head>
<body class="auth-page">
<main class="auth-shell auth-shell-narrow">
    <section class="auth-panel">
        <div class="brand-mark"><span>CR</span>Course Resource Portal</div>
        <h1>Create a new password.</h1>
        <p class="mt-3">Use at least 8 characters. The reset link cannot be reused.</p>
    </section>
    <section class="auth-form">
        <h2>Reset password</h2>
        <?php if ($error): ?><div class="auth-error mb-3"><?php echo h($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo h($success); ?></div><?php endif; ?>
        <?php if ($reset): ?>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="token" value="<?php echo h($token); ?>">
                <div class="mb-3">
                    <label class="form-label">New password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100">Update password</button>
            </form>
        <?php endif; ?>
        <p class="mt-4 mb-0 text-muted"><a href="login.php">Back to login</a></p>
    </section>
</main>
</body>
</html>
