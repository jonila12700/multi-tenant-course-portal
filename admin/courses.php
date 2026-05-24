<?php
include "../includes/session.php";
include "../config/db.php";

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_visibility'])) {
    verify_csrf_token();
    $course_id = (int) ($_POST['course_id'] ?? 0);
    $visibility = $_POST['visibility'] ?? '';

    $allowed = ['draft', 'published', 'archived'];

    if (!in_array($visibility, $allowed, true)) {
        $error = 'Invalid course status.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE courses
            SET visibility = ?
            WHERE id = ?
            AND tenant_id = ?
        ");
        $stmt->execute([$visibility, $course_id, $tenant_id]);

        $success = 'Course status updated successfully.';
    }
}

$stmt = $pdo->prepare("
    SELECT id, name
    FROM users
    WHERE tenant_id = ?
    AND role = 'instructor'
    AND status = 'active'
    ORDER BY name ASC
");
$stmt->execute([$tenant_id]);
$instructors = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    verify_csrf_token();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $instructor_id = $_POST['instructor_id'] ?? null;
    $visibility = $_POST['visibility'] ?? 'draft';
    $start_date = $_POST['start_date'] ?: null;
    $end_date = $_POST['end_date'] ?: null;

    if ($title === '') {
        $error = 'Course title is required.';
    } else {
        $instructor_id = $instructor_id !== '' ? (int) $instructor_id : null;

        $stmt = $pdo->prepare("
            INSERT INTO courses
                (tenant_id, instructor_id, title, description, visibility, start_date, end_date)
            VALUES
                (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $tenant_id,
            $instructor_id,
            $title,
            $description,
            $visibility,
            $start_date,
            $end_date
        ]);

        $success = 'Course created successfully.';
    }
}

$stmt = $pdo->prepare("
    SELECT
        courses.id,
        courses.title,
        courses.description,
        courses.visibility,
        courses.start_date,
        courses.end_date,
        courses.created_at,
        users.name AS instructor_name
    FROM courses
    LEFT JOIN users ON users.id = courses.instructor_id
    WHERE courses.tenant_id = ?
    ORDER BY courses.created_at DESC
");
$stmt->execute([$tenant_id]);
$courses = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/sidebar_admin.php"; ?>

<div class="content">
    <div class="container-fluid">

        <h2 class="mb-4">Manage Courses</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">Add New Course</div>

            <div class="card-body">
                <form method="POST">
                <?php echo csrf_field(); ?>
                    <input type="hidden" name="create_course" value="1">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Course Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label>Professor</label>
                            <select name="instructor_id" class="form-control">
                                <option value="">No professor</option>
                                <?php foreach ($instructors as $instructor): ?>
                                    <option value="<?php echo $instructor['id']; ?>">
                                        <?php echo htmlspecialchars($instructor['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2 mb-3">
                            <label>Visibility</label>
                            <select name="visibility" class="form-control">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label>Description</label>
                            <input type="text" name="description" class="form-control">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label>Start Date</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label>End Date</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>

                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Add</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <input
            type="text"
            id="courseSearch"
            class="form-control mb-3"
            placeholder="Search courses..."
        >

        <div class="card">
            <div class="card-header">Courses</div>

            <div class="card-body">
                <table class="table table-bordered table-striped" id="coursesTable">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Professor</th>
                            <th>Status</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['title']); ?></td>
                                <td><?php echo htmlspecialchars($course['instructor_name'] ?? 'No professor'); ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($course['visibility']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($course['start_date'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($course['end_date'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($course['created_at']); ?></td>
                                <td>
                                    <a href="edit_course.php?id=<?php echo (int) $course['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                    <?php if ($course['visibility'] === 'draft'): ?>
                                        <form method="POST" class="d-inline status-form">
                <?php echo csrf_field(); ?>
                                            <input type="hidden" name="change_visibility" value="1">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <input type="hidden" name="visibility" value="published">
                                            <button class="btn btn-success btn-sm">Publish</button>
                                        </form>
                                    <?php elseif ($course['visibility'] === 'published'): ?>
                                        <form method="POST" class="d-inline status-form">
                <?php echo csrf_field(); ?>
                                            <input type="hidden" name="change_visibility" value="1">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <input type="hidden" name="visibility" value="archived">
                                            <button class="btn btn-warning btn-sm">Archive</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline status-form">
                <?php echo csrf_field(); ?>
                                            <input type="hidden" name="change_visibility" value="1">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <input type="hidden" name="visibility" value="draft">
                                            <button class="btn btn-secondary btn-sm">Restore Draft</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($courses) === 0): ?>
                            <tr>
                                <td colspan="7" class="text-center">No courses found.</td>
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
document.getElementById('courseSearch').addEventListener('keyup', function () {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll('#coursesTable tbody tr');

    rows.forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
    });
});

document.querySelectorAll('.status-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!confirm('Are you sure you want to change this course status?')) {
            event.preventDefault();
        }
    });
});
</script>

<?php include "../includes/footer.php"; ?>



