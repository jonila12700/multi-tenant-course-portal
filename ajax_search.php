<?php
include 'includes/session.php';
include 'config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tenant_id = (int) $_SESSION['tenant_id'];
$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];
$type = $_GET['type'] ?? '';
$query = '%' . trim($_GET['q'] ?? '') . '%';

try {
    if ($type === 'courses') {
        if ($role === 'tenant_admin') {
            $stmt = $pdo->prepare("SELECT courses.id, courses.title, courses.visibility, users.name AS instructor FROM courses LEFT JOIN users ON users.id = courses.instructor_id WHERE courses.tenant_id = ? AND (courses.title LIKE ? OR courses.description LIKE ? OR users.name LIKE ?) ORDER BY courses.created_at DESC LIMIT 20");
            $stmt->execute([$tenant_id, $query, $query, $query]);
        } elseif ($role === 'instructor') {
            $stmt = $pdo->prepare("SELECT id, title, visibility, created_at FROM courses WHERE tenant_id = ? AND instructor_id = ? AND (title LIKE ? OR description LIKE ?) ORDER BY created_at DESC LIMIT 20");
            $stmt->execute([$tenant_id, $user_id, $query, $query]);
        } else {
            $stmt = $pdo->prepare("SELECT courses.id, courses.title, users.name AS instructor FROM courses INNER JOIN course_enrollments ON course_enrollments.course_id = courses.id LEFT JOIN users ON users.id = courses.instructor_id WHERE courses.tenant_id = ? AND course_enrollments.tenant_id = ? AND course_enrollments.user_id = ? AND course_enrollments.status = 'active' AND courses.visibility = 'published' AND (courses.title LIKE ? OR courses.description LIKE ? OR users.name LIKE ?) ORDER BY courses.created_at DESC LIMIT 20");
            $stmt->execute([$tenant_id, $tenant_id, $user_id, $query, $query, $query]);
        }
    } elseif ($type === 'students' && $role === 'tenant_admin') {
        $stmt = $pdo->prepare("SELECT id, name, email, status FROM users WHERE tenant_id = ? AND role = 'student' AND (name LIKE ? OR email LIKE ?) ORDER BY name ASC LIMIT 20");
        $stmt->execute([$tenant_id, $query, $query]);
    } elseif ($type === 'materials') {
        if ($role === 'instructor') {
            $stmt = $pdo->prepare("SELECT resources.id, resources.title, resources.type, courses.title AS course FROM resources INNER JOIN courses ON courses.id = resources.course_id WHERE resources.tenant_id = ? AND courses.instructor_id = ? AND (resources.title LIKE ? OR resources.description LIKE ? OR courses.title LIKE ?) ORDER BY resources.created_at DESC LIMIT 20");
            $stmt->execute([$tenant_id, $user_id, $query, $query, $query]);
        } else {
            $stmt = $pdo->prepare("SELECT resources.id, resources.title, resources.type, courses.title AS course FROM resources INNER JOIN courses ON courses.id = resources.course_id INNER JOIN course_enrollments ON course_enrollments.course_id = courses.id WHERE resources.tenant_id = ? AND course_enrollments.tenant_id = ? AND course_enrollments.user_id = ? AND course_enrollments.status = 'active' AND resources.visibility IN ('course', 'public') AND (resources.title LIKE ? OR resources.description LIKE ? OR courses.title LIKE ?) ORDER BY resources.created_at DESC LIMIT 20");
            $stmt->execute([$tenant_id, $tenant_id, $user_id, $query, $query, $query]);
        }
    } elseif ($type === 'assignments') {
        if ($role === 'instructor') {
            $stmt = $pdo->prepare("SELECT assignments.id, assignments.title, assignments.status, courses.title AS course FROM assignments INNER JOIN courses ON courses.id = assignments.course_id WHERE assignments.tenant_id = ? AND courses.instructor_id = ? AND (assignments.title LIKE ? OR assignments.description LIKE ? OR courses.title LIKE ?) ORDER BY assignments.created_at DESC LIMIT 20");
            $stmt->execute([$tenant_id, $user_id, $query, $query, $query]);
        } else {
            $stmt = $pdo->prepare("SELECT assignments.id, assignments.title, assignments.due_date, courses.title AS course FROM assignments INNER JOIN courses ON courses.id = assignments.course_id INNER JOIN course_enrollments ON course_enrollments.course_id = courses.id WHERE assignments.tenant_id = ? AND assignments.status = 'published' AND course_enrollments.tenant_id = ? AND course_enrollments.user_id = ? AND course_enrollments.status = 'active' AND (assignments.title LIKE ? OR assignments.description LIKE ? OR courses.title LIKE ?) ORDER BY assignments.due_date ASC LIMIT 20");
            $stmt->execute([$tenant_id, $tenant_id, $user_id, $query, $query, $query]);
        }
    } elseif ($type === 'submissions' && $role === 'instructor') {
        $stmt = $pdo->prepare("SELECT assignment_submissions.id, assignment_submissions.status, assignment_submissions.grade, users.name AS student, assignments.title AS assignment FROM assignment_submissions INNER JOIN assignments ON assignments.id = assignment_submissions.assignment_id INNER JOIN courses ON courses.id = assignments.course_id INNER JOIN users ON users.id = assignment_submissions.student_id WHERE assignment_submissions.tenant_id = ? AND courses.instructor_id = ? AND (users.name LIKE ? OR users.email LIKE ? OR assignments.title LIKE ?) ORDER BY assignment_submissions.submitted_at DESC LIMIT 20");
        $stmt->execute([$tenant_id, $user_id, $query, $query, $query]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid search type']);
        exit;
    }

    echo json_encode(['results' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}
