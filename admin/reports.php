<?php
include "../includes/session.php";
include "../config/db.php";

require_role('tenant_admin');

$tenant_id = (int) $_SESSION['tenant_id'];

$stmt = $pdo->prepare("
    SELECT role, COUNT(*) AS total
    FROM users
    WHERE tenant_id = ?
    GROUP BY role
");
$stmt->execute([$tenant_id]);
$role_counts = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT courses.title, COUNT(course_enrollments.id) AS total
    FROM courses
    LEFT JOIN course_enrollments ON course_enrollments.course_id = courses.id
    WHERE courses.tenant_id = ?
    GROUP BY courses.id, courses.title
    ORDER BY total DESC
    LIMIT 8
");
$stmt->execute([$tenant_id]);
$course_enrollments = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT resources.title, resources.type, courses.title AS course_title, users.name AS uploaded_by, resources.created_at
    FROM resources
    INNER JOIN courses ON courses.id = resources.course_id
    LEFT JOIN users ON users.id = resources.uploaded_by
    WHERE resources.tenant_id = ?
    ORDER BY resources.created_at DESC
    LIMIT 10
");
$stmt->execute([$tenant_id]);
$latest_resources = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT live_quizzes.question, courses.title AS course_title, COUNT(live_quiz_responses.id) AS responses
    FROM live_quizzes
    INNER JOIN courses ON courses.id = live_quizzes.course_id
    LEFT JOIN live_quiz_responses ON live_quiz_responses.quiz_id = live_quizzes.id
    WHERE live_quizzes.tenant_id = ?
    GROUP BY live_quizzes.id, live_quizzes.question, courses.title
    ORDER BY live_quizzes.created_at DESC
    LIMIT 8
");
$stmt->execute([$tenant_id]);
$quiz_reports = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/sidebar_admin.php"; ?>

<div class="content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Admin Reports</h2>
                <p class="text-muted mb-0">Overview of users, courses, resources, and quiz activity.</p>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">Back</a>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">Users by Role</div>
                    <div class="card-body">
                        <?php foreach ($role_counts as $row): ?>
                            <div class="report-row">
                                <span><?php echo htmlspecialchars($row['role']); ?></span>
                                <strong><?php echo (int) $row['total']; ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">Course Enrollments</div>
                    <div class="card-body">
                        <?php foreach ($course_enrollments as $row): ?>
                            <div class="report-row">
                                <span><?php echo htmlspecialchars($row['title']); ?></span>
                                <strong><?php echo (int) $row['total']; ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Latest Resources</div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Resource</th>
                            <th>Course</th>
                            <th>Type</th>
                            <th>Uploaded By</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latest_resources as $resource): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($resource['title']); ?></td>
                                <td><?php echo htmlspecialchars($resource['course_title']); ?></td>
                                <td><?php echo htmlspecialchars($resource['type']); ?></td>
                                <td><?php echo htmlspecialchars($resource['uploaded_by'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($resource['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Live Quiz Participation</div>
            <div class="card-body">
                <?php if (count($quiz_reports) > 0): ?>
                    <?php foreach ($quiz_reports as $quiz): ?>
                        <div class="report-row">
                            <span><?php echo htmlspecialchars($quiz['question'] . ' · ' . $quiz['course_title']); ?></span>
                            <strong><?php echo (int) $quiz['responses']; ?> responses</strong>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted mb-0">No quiz responses yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
