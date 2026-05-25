<?php
include "../includes/session.php";
include "../config/db.php";

require_role('instructor');

$tenant_id = (int) $_SESSION['tenant_id'];
$instructor_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT
        courses.id,
        courses.title,
        COUNT(DISTINCT resources.id) AS resource_count,
        COUNT(DISTINCT course_enrollments.user_id) AS student_count,
        COUNT(resource_progress.id) AS completed_count
    FROM courses
    LEFT JOIN resources
        ON resources.course_id = courses.id
        AND resources.tenant_id = courses.tenant_id
        AND resources.visibility IN ('course', 'public')
    LEFT JOIN course_enrollments
        ON course_enrollments.course_id = courses.id
        AND course_enrollments.tenant_id = courses.tenant_id
        AND course_enrollments.status = 'active'
    LEFT JOIN resource_progress
        ON resource_progress.resource_id = resources.id
        AND resource_progress.tenant_id = courses.tenant_id
        AND resource_progress.user_id = course_enrollments.user_id
    WHERE courses.tenant_id = ?
    AND courses.instructor_id = ?
    GROUP BY courses.id, courses.title
    ORDER BY courses.created_at DESC
");
$stmt->execute([$tenant_id, $instructor_id]);
$courses = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Student Progress</h2>
                <p class="text-muted mb-0">Track completed course resources.</p>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">Back</a>
        </div>

        <div class="card">
            <div class="card-header">Course Progress</div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Students</th>
                            <th>Resources</th>
                            <th>Completed Marks</th>
                            <th>Overall Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($courses) > 0): ?>
                            <?php foreach ($courses as $course): ?>
                                <?php
                                    $possible = (int) $course['student_count'] * (int) $course['resource_count'];
                                    $percent = $possible > 0 ? round(((int) $course['completed_count'] / $possible) * 100) : 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                                    <td><?php echo (int) $course['student_count']; ?></td>
                                    <td><?php echo (int) $course['resource_count']; ?></td>
                                    <td><?php echo (int) $course['completed_count']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1">
                                                <div class="progress-bar" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                            <strong><?php echo $percent; ?>%</strong>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No courses found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
