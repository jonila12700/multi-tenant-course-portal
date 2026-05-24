<?php
require_once 'includes/session.php';
require_once 'config/db.php';
require_once 'includes/paths.php';

$message = '';
$reset_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $organization_code = normalize_slug($_POST['organization_code'] ?? '');
    $email = normalize_email($_POST['email'] ?? '');

    if ($organization_code === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'If that email exists, a reset link will be generated.';
    } elseif (too_many_attempts('forgot_' . $organization_code . '_' . $email, 5, 600)) {
        $message = 'Please wait a few minutes before requesting another reset link.';
    } else {
        record_attempt('forgot_' . $organization_code . '_' . $email);

        $stmt = $pdo->prepare("
            SELECT users.id, users.tenant_id, users.email
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

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);

            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE tenant_id = ? AND user_id = ?");
            $stmt->execute([(int) $user['tenant_id'], (int) $user['id']]);

            $stmt = $pdo->prepare("
                INSERT INTO password_resets (tenant_id, user_id, token_hash, expires_at)
                VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
            ");
            $stmt->execute([(int) $user['tenant_id'], (int) $user['id'], $token_hash]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
            $reset_link = $scheme . '://' . $host . $base . '/reset_password.php?token=' . urlencode($token);

            $subject = 'Password reset - Course Portal';
            $body = "Use this link to reset your password within 1 hour:\n\n" . $reset_link;
            @mail($email, $subject, $body);
        }

        $message = 'If that email exists, a reset link has been sent.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - Course Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo h(asset_path('assets/css/app.css')); ?>" rel="stylesheet">
</head>
<body class="auth-page">
<main class="auth-shell auth-shell-narrow">
    <section class="auth-panel">
        <div class="brand-mark"><span>CR</span>Course Resource Portal</div>
        <h1>Reset your portal password securely.</h1>
        <p class="mt-3">Reset links expire after one hour and can be used once.</p>
    </section>
    <section class="auth-form">
        <h2>Forgot password</h2>
        <p class="text-muted mb-4">Enter your account email.</p>
        <?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
        <?php if ($reset_link && (($_SERVER['HTTP_HOST'] ?? '') === 'localhost')): ?>
            <div class="alert alert-warning small">Local mail preview: <a href="<?php echo h($reset_link); ?>"><?php echo h($reset_link); ?></a></div>
        <?php endif; ?>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">Organization Code</label>
                <input type="text" name="organization_code" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="text" inputmode="email" autocomplete="email" name="email" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100">Send reset link</button>
        </form>
        <p class="mt-4 mb-0 text-muted"><a href="login.php">Back to login</a></p>
    </section>
</main>
</body>
</html>
