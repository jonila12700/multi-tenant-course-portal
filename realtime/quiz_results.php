<?php
require_once "../includes/session.php";
require_once "../config/db.php";

require_login();

header('Content-Type: application/json');

$tenant_id = (int) $_SESSION['tenant_id'];
$quiz_id = (int) ($_GET['quiz_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT live_quizzes.*
    FROM live_quizzes
    INNER JOIN courses
        ON courses.id = live_quizzes.course_id
        AND courses.tenant_id = live_quizzes.tenant_id
    WHERE live_quizzes.id = ?
    AND live_quizzes.tenant_id = ?
    AND (
        live_quizzes.created_by = ?
        OR EXISTS (
            SELECT 1
            FROM course_enrollments
            WHERE course_enrollments.course_id = courses.id
            AND course_enrollments.tenant_id = live_quizzes.tenant_id
            AND course_enrollments.user_id = ?
            AND course_enrollments.status = 'active'
        )
    )
    LIMIT 1
");
$stmt->execute([$quiz_id, $tenant_id, (int) $_SESSION['user_id'], (int) $_SESSION['user_id']]);
$quiz = $stmt->fetch();

if (!$quiz) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT selected_option, COUNT(*) AS total
    FROM live_quiz_responses
    WHERE tenant_id = ?
    AND quiz_id = ?
    GROUP BY selected_option
");
$stmt->execute([$tenant_id, $quiz_id]);
$counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
$total = 0;

foreach ($stmt->fetchAll() as $row) {
    $counts[$row['selected_option']] = (int) $row['total'];
    $total += (int) $row['total'];
}

$options = [];
foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $label => $field) {
    if (!empty($quiz[$field])) {
        $count = $counts[$label];
        $options[] = [
            'label' => $label,
            'text' => $quiz[$field],
            'count' => $count,
            'percent' => $total > 0 ? round(($count / $total) * 100) : 0,
            'correct' => $quiz['correct_option'] === $label,
        ];
    }
}

echo json_encode([
    'ok' => true,
    'total' => $total,
    'options' => $options,
]);
