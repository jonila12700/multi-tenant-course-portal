<?php

if (session_status() === PHP_SESSION_NONE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function login_path()
{
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $script = trim($script, '/');

    if (in_array(basename($script), ['admin', 'instructor', 'student', 'superadmin', 'realtime'], true)) {
        return '../login.php';
    }

    return 'login.php';
}

function require_login()
{
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id']) || !isset($_SESSION['role'])) {
        header('Location: ' . login_path());
        exit;
    }
}

function require_role($role)
{
    require_login();

    $roles = is_array($role) ? $role : [$role];

    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function current_tenant_id()
{
    require_login();
    return (int) $_SESSION['tenant_id'];
}

function current_user_id()
{
    require_login();
    return (int) $_SESSION['user_id'];
}

function redirect_for_role($role)
{
    if ($role === 'super_admin') {
        return 'superadmin/dashboard.php';
    }

    if ($role === 'tenant_admin') {
        return 'admin/dashboard.php';
    }

    if ($role === 'instructor') {
        return 'instructor/dashboard.php';
    }

    if ($role === 'student') {
        return 'student/dashboard.php';
    }

    return 'login.php';
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token()
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Security check failed. Please go back and try again.');
    }
}

function password_policy_error($password)
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }

    return '';
}

function normalize_email($email)
{
    return strtolower(trim((string) $email));
}

function normalize_slug($value)
{
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) $value));
    return trim($slug, '-');
}

function throttle_key($name)
{
    return 'throttle_' . preg_replace('/[^a-z0-9_]+/i', '_', $name);
}

function too_many_attempts($name, $limit = 8, $seconds = 300)
{
    $key = throttle_key($name);
    $now = time();
    $_SESSION[$key] = array_values(array_filter($_SESSION[$key] ?? [], function ($timestamp) use ($now, $seconds) {
        return ($now - (int) $timestamp) < $seconds;
    }));

    return count($_SESSION[$key]) >= $limit;
}

function record_attempt($name)
{
    $key = throttle_key($name);
    $_SESSION[$key] = $_SESSION[$key] ?? [];
    $_SESSION[$key][] = time();
}
