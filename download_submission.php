<?php
include 'includes/session.php';
include 'config/db.php';

if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'], $_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$submission_id = (int) ($_GET['id'] ?? 0);
$tenant_id = (int) $_SESSION['tenant_id'];
$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];

$stmt = $pdo->prepare("
    SELECT assignment_submissions.*, assignments.course_id, courses.instructor_id
    FROM assignment_submissions
    INNER JOIN assignments ON assignments.id = assignment_submissions.assignment_id
        AND assignments.tenant_id = assignment_submissions.tenant_id
    INNER JOIN courses ON courses.id = assignments.course_id
        AND courses.tenant_id = assignment_submissions.tenant_id
    WHERE assignment_submissions.id = ?
    AND assignment_submissions.tenant_id = ?
    LIMIT 1
");
$stmt->execute([$submission_id, $tenant_id]);
$submission = $stmt->fetch();

if (!$submission) {
    http_response_code(404);
    exit('Submission not found.');
}

$allowed = false;
if ($role === 'tenant_admin') {
    $allowed = true;
} elseif ($role === 'instructor' && (int) $submission['instructor_id'] === $user_id) {
    $allowed = true;
} elseif ($role === 'student' && (int) $submission['student_id'] === $user_id) {
    $allowed = true;
}

if (!$allowed) {
    http_response_code(403);
    exit('Access denied.');
}

$relative = str_replace('\\', '/', $submission['file_path']);
$full_path = realpath(__DIR__ . '/' . ltrim($relative, '/'));
$upload_root = realpath(__DIR__ . '/uploads/tenant_' . $tenant_id);

if (!$full_path || !$upload_root || strpos($full_path, $upload_root . DIRECTORY_SEPARATOR) !== 0 || !is_file($full_path)) {
    http_response_code(404);
    exit('File missing.');
}

header('Content-Type: ' . ($submission['mime_type'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . basename($submission['original_name']) . '"');
header('Content-Length: ' . filesize($full_path));
header('X-Content-Type-Options: nosniff');
readfile($full_path);
exit;
