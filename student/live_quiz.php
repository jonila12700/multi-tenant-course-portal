<?php
include "../includes/session.php";
include "../config/db.php";
include "../includes/realtime.php";

require_role('student');

$tenant_id = (int) $_SESSION['tenant_id'];
$user_id = (int) $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    verify_csrf_token();

    $quiz_id = (int) ($_POST['quiz_id'] ?? 0);
    $selected_option = $_POST['selected_option'] ?? '';
    $allowed_options = ['A', 'B', 'C', 'D'];

    if (!in_array($selected_option, $allowed_options, true)) {
        $error = 'Please select an answer.';
    } else {
        $stmt = $pdo->prepare("
            SELECT live_quizzes.id
            FROM live_quizzes
            INNER JOIN courses ON courses.id = live_quizzes.course_id
            INNER JOIN course_enrollments ON course_enrollments.course_id = courses.id
            WHERE live_quizzes.id = ?
            AND live_quizzes.tenant_id = ?
            AND course_enrollments.user_id = ?
            AND course_enrollments.status = 'active'
            AND live_quizzes.status = 'open'
            LIMIT 1
        ");
        $stmt->execute([$quiz_id, $tenant_id, $user_id]);
        $quiz = $stmt->fetch();

        if (!$quiz) {
            $error = 'This quiz is not available.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO live_quiz_responses (tenant_id, quiz_id, user_id, selected_option)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), created_at = NOW()
                ");
                $stmt->execute([$tenant_id, $quiz_id, $user_id, $selected_option]);

                $success = 'Answer submitted.';
                log_activity($pdo, 'quiz_answered', ($_SESSION['name'] ?? 'Student') . ' answered a live quiz.');
            } catch (PDOException $e) {
                $error = 'Could not submit answer. Please try again.';
            }
        }
    }
}

$stmt = $pdo->prepare("
    SELECT live_quizzes.*, courses.title AS course_title, live_quiz_responses.selected_option
    FROM live_quizzes
    INNER JOIN courses ON courses.id = live_quizzes.course_id
    INNER JOIN course_enrollments ON course_enrollments.course_id = courses.id
    LEFT JOIN live_quiz_responses
        ON live_quiz_responses.quiz_id = live_quizzes.id
        AND live_quiz_responses.user_id = ?
    WHERE live_quizzes.tenant_id = ?
    AND course_enrollments.tenant_id = ?
    AND course_enrollments.user_id = ?
    AND course_enrollments.status = 'active'
    ORDER BY live_quizzes.created_at DESC
    LIMIT 20
");
$stmt->execute([$user_id, $tenant_id, $tenant_id, $user_id]);
$quizzes = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Live Quiz</h2>
                <p class="text-muted mb-0">Answer active quizzes from your professors.</p>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">Back</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (count($quizzes) > 0): ?>
            <div class="row">
                <?php foreach ($quizzes as $quiz): ?>
                    <div class="col-lg-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between gap-2 mb-2">
                                    <strong><?php echo htmlspecialchars($quiz['question']); ?></strong>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($quiz['status']); ?></span>
                                </div>
                                <small class="text-muted"><?php echo htmlspecialchars($quiz['course_title']); ?></small>

                                <?php if ($quiz['status'] === 'open'): ?>
                                    <form method="POST" class="mt-3">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="submit_quiz" value="1">
                                        <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">

                                        <?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $label => $field): ?>
                                            <?php if (!empty($quiz[$field])): ?>
                                                <label class="quiz-option">
                                                    <input
                                                        type="radio"
                                                        name="selected_option"
                                                        value="<?php echo $label; ?>"
                                                        <?php echo $quiz['selected_option'] === $label ? 'checked' : ''; ?>
                                                    >
                                                    <span><?php echo $label; ?>. <?php echo htmlspecialchars($quiz[$field]); ?></span>
                                                </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>

                                        <button type="submit" class="btn btn-primary mt-3">Submit Answer</button>
                                    </form>
                                <?php else: ?>
                                    <p class="mt-3 mb-0 text-muted">
                                        Quiz closed. Your answer:
                                        <strong><?php echo htmlspecialchars($quiz['selected_option'] ?? 'No answer'); ?></strong>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-0">No live quizzes available yet.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
