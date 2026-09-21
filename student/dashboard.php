<?php
include "../includes/session.php";
include "../config/db.php";

require_role('student');

$tenant_id = (int) $_SESSION['tenant_id'];
$user_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT
        courses.id,
        courses.title,
        courses.description,
        courses.start_date,
        courses.end_date,
        users.name AS instructor_name
    FROM courses
    INNER JOIN course_enrollments
        ON course_enrollments.course_id = courses.id
        AND course_enrollments.tenant_id = courses.tenant_id
    LEFT JOIN users
        ON users.id = courses.instructor_id
        AND users.tenant_id = courses.tenant_id
    WHERE courses.tenant_id = ?
    AND course_enrollments.tenant_id = ?
    AND course_enrollments.user_id = ?
    AND course_enrollments.status = 'active'
    AND courses.visibility = 'published'
    ORDER BY courses.created_at DESC
");
$stmt->execute([$tenant_id, $tenant_id, $user_id]);
$courses = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>

<div class="container mt-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1">Student Dashboard</h2>
            <p class="text-muted mb-0">
                Welcome back, <b><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></b>
            </p>
        </div>

        <a href="../logout.php" class="btn btn-danger">
            Logout
        </a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="assignments.php" class="btn btn-warning">My Assignments</a>
        <a href="announcements.php" class="btn btn-info">Announcements</a>
        <a href="live_quiz.php" class="btn btn-secondary">Live Quiz</a>
    </div>

    <input
        type="text"
        id="courseSearch"
        class="form-control mb-3"
        placeholder="Search my courses..."
    >

    <div class="card">
        <div class="card-header">
            My Courses
        </div>

        <div class="card-body">

            <table class="table table-bordered table-striped" id="coursesTable">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Professor</th>
                        <th>Description</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($courses) > 0): ?>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['title']); ?></td>
                                <td><?php echo htmlspecialchars($course['instructor_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($course['description'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($course['start_date'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($course['end_date'] ?? ''); ?></td>
                                <td>
                                    <a
                                        href="view_course.php?id=<?php echo $course['id']; ?>"
                                        class="btn btn-primary btn-sm"
                                    >
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                No enrolled courses available.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p id="noResults" class="text-center text-muted" style="display:none;">
                No matching courses found.
            </p>

        </div>
    </div>

</div>

<script>
const courseSearch = document.getElementById('courseSearch');
const courseRows = document.querySelectorAll('#coursesTable tbody tr');
const noResults = document.getElementById('noResults');

courseSearch.addEventListener('keyup', function () {
    const value = this.value.toLowerCase();
    let visibleCount = 0;

    courseRows.forEach(function (row) {
        const isDataRow = row.querySelector('a[href^="view_course.php"]');
        const matches = row.textContent.toLowerCase().includes(value);

        if (!isDataRow) {
            return;
        }

        row.style.display = matches ? '' : 'none';

        if (matches) {
            visibleCount++;
        }
    });

    noResults.style.display = visibleCount === 0 && value !== '' ? 'block' : 'none';
});
</script>

<?php include "../includes/footer.php"; ?>

