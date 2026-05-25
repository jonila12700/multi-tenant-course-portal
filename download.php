<?php
include 'includes/session.php';
include "config/db.php";

if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'], $_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$resource_id = (int) ($_GET['id'] ?? 0);
$tenant_id = (int) $_SESSION['tenant_id'];
$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];

if ($resource_id <= 0) {
    http_response_code(404);
    exit('Resource not found.');
}

$stmt = $pdo->prepare("
    SELECT resources.*, courses.instructor_id
    FROM resources
    INNER JOIN courses
        ON courses.id = resources.course_id
        AND courses.tenant_id = resources.tenant_id
    WHERE resources.id = ?
    AND resources.tenant_id = ?
    LIMIT 1
");
$stmt->execute([$resource_id, $tenant_id]);
$resource = $stmt->fetch();

if (!$resource || $resource['type'] !== 'file' || empty($resource['file_path'])) {
    http_response_code(404);
    exit('File not found.');
}

$allowed = false;

if ($role === 'tenant_admin') {
    $allowed = true;
} elseif ($role === 'instructor' && (int) $resource['instructor_id'] === $user_id) {
    $allowed = true;
} elseif ($role === 'student' && in_array($resource['visibility'], ['course', 'public'], true)) {
    $stmt = $pdo->prepare("
        SELECT id
        FROM course_enrollments
        WHERE tenant_id = ?
        AND course_id = ?
        AND user_id = ?
        AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$tenant_id, (int) $resource['course_id'], $user_id]);
    $allowed = (bool) $stmt->fetch();
}

if (!$allowed) {
    http_response_code(403);
    exit('Access denied.');
}

$relative = str_replace('\\', '/', $resource['file_path']);
$full_path = realpath(__DIR__ . '/' . ltrim($relative, '/'));
$upload_root = realpath(__DIR__ . '/uploads/tenant_' . $tenant_id);

if (!$full_path || !$upload_root || strpos($full_path, $upload_root . DIRECTORY_SEPARATOR) !== 0 || !is_file($full_path)) {
    http_response_code(404);
    exit('File missing.');
}

$download_name = basename($full_path);
header('Content-Type: ' . ($resource['mime_type'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $download_name . '"');
header('Content-Length: ' . filesize($full_path));
header('X-Content-Type-Options: nosniff');
readfile($full_path);
exit;

