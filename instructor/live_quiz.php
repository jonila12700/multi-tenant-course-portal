<?php
include "../includes/session.php";
include "../config/db.php";
include "../includes/realtime.php";

require_role('instructor');

$tenant_id = (int) $_SESSION['tenant_id'];
$instructor_id = (int) $_SESSION['user_id'];
$error = '';
$success = '';

$stmt = $pdo->prepare("
    SELECT id, title
    FROM courses
    WHERE tenant_id = ?
    AND instructor_id = ?
    ORDER BY title ASC
");
$stmt->execute([$tenant_id, $instructor_id]);
$courses = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz'])) {
    verify_csrf_token();

    $course_id = (int) ($_POST['course_id'] ?? 0);
    $question = trim($_POST['question'] ?? '');
    $option_a = trim($_POST['option_a'] ?? '');
    $option_b = trim($_POST['option_b'] ?? '');
    $option_c = trim($_POST['option_c'] ?? '');
    $option_d = trim($_POST['option_d'] ?? '');
    $correct_option = ($_POST['correct_option'] ?? '') !== '' ? $_POST['correct_option'] : null;
    $allowed_options = ['A', 'B', 'C', 'D'];

    if ($course_id <= 0 || $question === '' || $option_a === '' || $option_b === '') {
        $error = 'Course, question, option A, and option B are required.';
    } elseif ($correct_option !== null && !in_array($correct_option, $allowed_options, true)) {
        $error = 'Invalid correct option.';
    } else {
        $stmt = $pdo->prepare("
            SELECT id
            FROM courses
            WHERE id = ?
            AND tenant_id = ?
            AND instructor_id = ?
            LIMIT 1
        ");
        $stmt->execute([$course_id, $tenant_id, $instructor_id]);
        $course = $stmt->fetch();

        if (!$course) {
            $error = 'Invalid course selected.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO live_quizzes
                    (tenant_id, course_id, created_by, question, option_a, option_b, option_c, option_d, correct_option, status)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')
            ");
            $stmt->execute([
                $tenant_id,
                $course_id,
                $instructor_id,
                $question,
                $option_a,
                $option_b,
                $option_c ?: null,
                $option_d ?: null,
                $correct_option,
            ]);

            $success = 'Live quiz opened.';
            log_activity($pdo, 'live_quiz_opened', 'Professor opened a live quiz.');
            notify_enrolled_students($pdo, $course_id, 'Live quiz is open', $question, 'quiz');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_quiz'])) {
    verify_csrf_token();

    $quiz_id = (int) ($_POST['quiz_id'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE live_quizzes
        INNER JOIN courses
            ON courses.id = live_quizzes.course_id
            AND courses.tenant_id = live_quizzes.tenant_id
        SET live_quizzes.status = 'closed'
        WHERE live_quizzes.id = ?
        AND live_quizzes.tenant_id = ?
        AND courses.instructor_id = ?
    ");
    $stmt->execute([$quiz_id, $tenant_id, $instructor_id]);

    $success = 'Live quiz closed.';
    log_activity($pdo, 'live_quiz_closed', 'Professor closed a live quiz.');
}

$stmt = $pdo->prepare("
    SELECT live_quizzes.*, courses.title AS course_title
    FROM live_quizzes
    INNER JOIN courses
        ON courses.id = live_quizzes.course_id
        AND courses.tenant_id = live_quizzes.tenant_id
    WHERE live_quizzes.tenant_id = ?
    AND courses.instructor_id = ?
    ORDER BY live_quizzes.created_at DESC
    LIMIT 20
");
$stmt->execute([$tenant_id, $instructor_id]);
$quizzes = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Live Quiz</h2>
                <p class="text-muted mb-0">Open a live poll and watch student results update.</p>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">Back</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">Create Live Quiz</div>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="create_quiz" value="1">

                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <select name="course_id" class="form-control" required>
                            <option value="">Select course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Question</label>
                        <input type="text" name="question" class="form-control" placeholder="Which cloud service stores files?" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Option A</label>
                            <input type="text" name="option_a" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Option B</label>
                            <input type="text" name="option_b" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Option C</label>
                            <input type="text" name="option_c" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Option D</label>
                            <input type="text" name="option_d" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Correct Option</label>
                        <select name="correct_option" class="form-control">
                            <option value="">No correct answer / poll only</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Open Live Quiz</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Live Results</div>
            <div class="card-body">
                <?php if (count($quizzes) > 0): ?>
                    <div class="row">
                        <?php foreach ($quizzes as $quiz): ?>
                            <div class="col-lg-6 mb-3">
                                <div class="quiz-card" data-quiz-results="<?php echo $quiz['id']; ?>">
                                    <div class="d-flex justify-content-between gap-2">
                                        <strong><?php echo htmlspecialchars($quiz['question']); ?></strong>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($quiz['status']); ?></span>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($quiz['course_title']); ?></small>

                                    <div class="quiz-results mt-3">Loading results...</div>

                                    <?php if ($quiz['status'] === 'open'): ?>
                                        <form method="POST" class="mt-3">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="close_quiz" value="1">
                                            <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                                            <button type="submit" class="btn btn-warning btn-sm">Close Quiz</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No live quizzes yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function loadQuizResults() {
    document.querySelectorAll('[data-quiz-results]').forEach(function (card) {
        const quizId = card.dataset.quizResults;

        fetch('../realtime/quiz_results.php?quiz_id=' + encodeURIComponent(quizId), { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.ok) {
                    return;
                }

                const results = card.querySelector('.quiz-results');
                results.innerHTML = data.options.map(function (option) {
                    return '<div class="quiz-result-row"><div><strong>' + option.label + '</strong> ' +
                        option.text + '</div><span>' + option.count + '</span></div>' +
                        '<div class="progress mb-2"><div class="progress-bar" style="width:' + option.percent + '%"></div></div>';
                }).join('');
            });
    });
}

loadQuizResults();
setInterval(loadQuizResults, 4000);
</script>

<?php include "../includes/footer.php"; ?>
