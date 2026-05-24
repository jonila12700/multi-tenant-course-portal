<?php
include "../includes/session.php";
include "../config/db.php";
include "../includes/realtime.php";

if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'], $_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['role'] !== 'tenant_admin') {
    header("Location: ../login.php");
    exit();
}

$tenant_id = (int) $_SESSION['tenant_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_enrollment'])) {
    verify_csrf_token();
    $student_id = (int) ($_POST['student_id'] ?? 0);
    $course_id = (int) ($_POST['course_id'] ?? 0);

    if ($student_id <= 0 || $course_id <= 0) {
        $error = 'Please select both student and course.';
    } else {
        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE id = ?
            AND tenant_id = ?
            AND role = 'student'
            AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$student_id, $tenant_id]);
        $student = $stmt->fetch();

        $stmt = $pdo->prepare("
            SELECT id
            FROM courses
            WHERE id = ?
            AND tenant_id = ?
            LIMIT 1
        ");
        $stmt->execute([$course_id, $tenant_id]);
        $course = $stmt->fetch();

        if (!$student || !$course) {
            $error = 'Invalid student or course selected.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO course_enrollments (tenant_id, course_id, user_id, status)
                    VALUES (?, ?, ?, 'active')
                ");
                $stmt->execute([$tenant_id, $course_id, $student_id]);

                $success = 'Student enrolled successfully.';
                notify_user($pdo, $student_id, 'New course enrollment', 'You have been enrolled in a course.', 'enrollment');
                log_activity($pdo, 'student_enrolled', 'A student was enrolled in a course.');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'This student is already enrolled in that course.';
                } else {
                    $error = 'Enrollment failed. Please try again.';
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    verify_csrf_token();
    $enrollment_id = (int) ($_POST['enrollment_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $allowed_statuses = ['active', 'completed', 'dropped'];

    if (!in_array($status, $allowed_statuses, true)) {
        $error = 'Invalid enrollment status.';
    } else {
        $completed_at = $status === 'completed' ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("
            UPDATE course_enrollments
            SET status = ?, completed_at = ?
            WHERE id = ?
            AND tenant_id = ?
        ");
        $stmt->execute([$status, $completed_at, $enrollment_id, $tenant_id]);

        $success = 'Enrollment status updated.';
    }
}

$stmt = $pdo->prepare("
    SELECT id, name, email
    FROM users
    WHERE tenant_id = ?
    AND role = 'student'
    AND status = 'active'
    ORDER BY name ASC
");
$stmt->execute([$tenant_id]);
$students = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT id, title
    FROM courses
    WHERE tenant_id = ?
    AND visibility != 'archived'
    ORDER BY title ASC
");
$stmt->execute([$tenant_id]);
$courses = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT
        course_enrollments.id,
        course_enrollments.status,
        course_enrollments.enrolled_at,
        course_enrollments.completed_at,
        users.name AS student_name,
        users.email AS student_email,
        courses.title AS course_title
    FROM course_enrollments
    INNER JOIN users ON users.id = course_enrollments.user_id
    INNER JOIN courses ON courses.id = course_enrollments.course_id
    WHERE course_enrollments.tenant_id = ?
    ORDER BY course_enrollments.enrolled_at DESC
");
$stmt->execute([$tenant_id]);
$enrollments = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/sidebar_admin.php"; ?>

<div class="content">
    <div class="container-fluid">

        <h2 class="mb-4">Manage Enrollments</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">Enroll Student</div>

            <div class="card-body">
                <form method="POST">
                <?php echo csrf_field(); ?>
                    <input type="hidden" name="create_enrollment" value="1">

                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label>Student</label>
                            <select name="student_id" class="form-control" required>
                                <option value="">Select student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['name'] . ' (' . $student['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-5 mb-3">
                            <label>Course</label>
                            <select name="course_id" class="form-control" required>
                                <option value="">Select course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Enroll</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <input
            type="text"
            id="enrollmentSearch"
            class="form-control mb-3"
            placeholder="Search enrollments..."
        >

        <div class="card">
            <div class="card-header">Enrollments</div>

            <div class="card-body">
                <table class="table table-bordered table-striped" id="enrollmentsTable">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Enrolled</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($enrollments) > 0): ?>
                            <?php foreach ($enrollments as $enrollment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($enrollment['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($enrollment['student_email']); ?></td>
                                    <td><?php echo htmlspecialchars($enrollment['course_title']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($enrollment['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($enrollment['enrolled_at']); ?></td>
                                    <td>
                                        <form method="POST" class="d-inline enrollment-form">
                <?php echo csrf_field(); ?>
                                            <input type="hidden" name="change_status" value="1">
                                            <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                                            <select name="status" class="form-control form-control-sm d-inline w-auto">
                                                <option value="active" <?php echo $enrollment['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="completed" <?php echo $enrollment['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="dropped" <?php echo $enrollment['status'] === 'dropped' ? 'selected' : ''; ?>>Dropped</option>
                                            </select>
                                            <button type="submit" class="btn btn-dark btn-sm">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No enrollments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <br>

        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>

    </div>
</div>

<script>
document.getElementById('enrollmentSearch').addEventListener('keyup', function () {
    const value = this.value.toLowerCase();
    const rows = document.querySelectorAll('#enrollmentsTable tbody tr');

    rows.forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
    });
});

document.querySelectorAll('.enrollment-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!confirm('Update this enrollment status?')) {
            event.preventDefault();
        }
    });
});
</script>

<?php include "../includes/footer.php"; ?>



