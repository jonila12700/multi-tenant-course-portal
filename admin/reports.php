<?php
include "../includes/session.php";
include "../config/db.php";

require_role('tenant_admin');

$tenant_id = (int) $_SESSION['tenant_id'];
$report_error = '';

function fetch_report_rows(PDO $pdo, string $sql, array $params, string &$report_error): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Reports query failed: ' . $e->getMessage());
        $report_error = 'Some report data is unavailable. Import the latest database schema, then refresh this page.';
        return [];
    }
}

$role_counts = fetch_report_rows($pdo, "
    SELECT role, COUNT(*) AS total
    FROM users
    WHERE tenant_id = ?
    GROUP BY role
", [$tenant_id], $report_error);

$course_enrollments = fetch_report_rows($pdo, "
    SELECT courses.title, COUNT(course_enrollments.id) AS total
    FROM courses
    LEFT JOIN course_enrollments
        ON course_enrollments.course_id = courses.id
        AND course_enrollments.tenant_id = courses.tenant_id
    WHERE courses.tenant_id = ?
    GROUP BY courses.id, courses.title
    ORDER BY total DESC
    LIMIT 8
", [$tenant_id], $report_error);

$latest_resources = fetch_report_rows($pdo, "
    SELECT resources.title, resources.type, courses.title AS course_title, users.name AS uploaded_by, resources.created_at
    FROM resources
    INNER JOIN courses
        ON courses.id = resources.course_id
        AND courses.tenant_id = resources.tenant_id
    LEFT JOIN users
        ON users.id = resources.uploaded_by
        AND users.tenant_id = resources.tenant_id
    WHERE resources.tenant_id = ?
    ORDER BY resources.created_at DESC
    LIMIT 10
", [$tenant_id], $report_error);

$quiz_reports = fetch_report_rows($pdo, "
    SELECT live_quizzes.question, courses.title AS course_title, COUNT(live_quiz_responses.id) AS responses
    FROM live_quizzes
    INNER JOIN courses
        ON courses.id = live_quizzes.course_id
        AND courses.tenant_id = live_quizzes.tenant_id
    LEFT JOIN live_quiz_responses
        ON live_quiz_responses.quiz_id = live_quizzes.id
        AND live_quiz_responses.tenant_id = live_quizzes.tenant_id
    WHERE live_quizzes.tenant_id = ?
    GROUP BY live_quizzes.id, live_quizzes.question, courses.title
    ORDER BY live_quizzes.created_at DESC
    LIMIT 8
", [$tenant_id], $report_error);
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

        <?php if ($report_error): ?>
            <div class="alert alert-warning"><?php echo h($report_error); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">Users by Role</div>
                    <div class="card-body">
                        <?php foreach ($role_counts as $row): ?>
                            <div class="report-row">
                                <span><?php echo h($row['role']); ?></span>
                                <strong><?php echo (int) $row['total']; ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$role_counts): ?><p class="text-muted mb-0">No users found.</p><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">Course Enrollments</div>
                    <div class="card-body">
                        <?php foreach ($course_enrollments as $row): ?>
                            <div class="report-row">
                                <span><?php echo h($row['title']); ?></span>
                                <strong><?php echo (int) $row['total']; ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$course_enrollments): ?><p class="text-muted mb-0">No courses found.</p><?php endif; ?>
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
                                <td><?php echo h($resource['title']); ?></td>
                                <td><?php echo h($resource['course_title']); ?></td>
                                <td><?php echo h($resource['type']); ?></td>
                                <td><?php echo h($resource['uploaded_by'] ?? ''); ?></td>
                                <td><?php echo h($resource['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$latest_resources): ?><tr><td colspan="5" class="text-center">No resources found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Live Quiz Participation</div>
            <div class="card-body">
                <?php foreach ($quiz_reports as $quiz): ?>
                    <div class="report-row">
                        <span><?php echo h($quiz['question'] . ' - ' . $quiz['course_title']); ?></span>
                        <strong><?php echo (int) $quiz['responses']; ?> responses</strong>
                    </div>
                <?php endforeach; ?>
                <?php if (!$quiz_reports): ?><p class="text-muted mb-0">No quiz responses yet.</p><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
