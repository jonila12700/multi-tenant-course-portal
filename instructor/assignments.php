<?php
include "../includes/session.php";
include "../config/db.php";

if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'], $_SESSION['role'])) {
    header("Location: ../login.php");
    exit;
}

if ($_SESSION['role'] !== 'instructor') {
    header("Location: ../login.php");
    exit;
}

$tenant_id = (int) $_SESSION['tenant_id'];
$instructor_id = (int) $_SESSION['user_id'];
$error = '';
$success = '';

$stmt = $pdo->prepare("SELECT id, title FROM courses WHERE tenant_id = ? AND instructor_id = ? ORDER BY title ASC");
$stmt->execute([$tenant_id, $instructor_id]);
$courses = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
    verify_csrf_token();

    $course_id = (int) ($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = ($_POST['due_date'] ?? '') !== '' ? $_POST['due_date'] : null;
    $max_points = (float) ($_POST['max_points'] ?? 100);
    $status = $_POST['status'] ?? 'published';

    if ($title === '' || $course_id <= 0) {
        $error = 'Course and assignment title are required.';
    } elseif (!in_array($status, ['draft', 'published', 'archived'], true)) {
        $error = 'Invalid assignment status.';
    } elseif ($max_points <= 0) {
        $error = 'Max points must be greater than zero.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND tenant_id = ? AND instructor_id = ? LIMIT 1");
        $stmt->execute([$course_id, $tenant_id, $instructor_id]);

        if (!$stmt->fetch()) {
            $error = 'Invalid course selected.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO assignments (tenant_id, course_id, created_by, title, description, due_date, max_points, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tenant_id, $course_id, $instructor_id, $title, $description, $due_date, $max_points, $status]);
            $success = 'Assignment created successfully.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    verify_csrf_token();

    $assignment_id = (int) ($_POST['assignment_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!in_array($status, ['draft', 'published', 'archived'], true)) {
        $error = 'Invalid assignment status.';
    } else {
        $stmt = $pdo->prepare("UPDATE assignments INNER JOIN courses ON courses.id = assignments.course_id AND courses.tenant_id = assignments.tenant_id SET assignments.status = ? WHERE assignments.id = ? AND assignments.tenant_id = ? AND courses.instructor_id = ?");
        $stmt->execute([$status, $assignment_id, $tenant_id, $instructor_id]);
        $success = 'Assignment status updated.';
    }
}

$stmt = $pdo->prepare("
    SELECT assignments.*, courses.title AS course_title,
        (
            SELECT COUNT(*)
            FROM assignment_submissions
            WHERE assignment_submissions.assignment_id = assignments.id
            AND assignment_submissions.tenant_id = assignments.tenant_id
        ) AS submission_count
    FROM assignments
    INNER JOIN courses
        ON courses.id = assignments.course_id
        AND courses.tenant_id = assignments.tenant_id
    WHERE assignments.tenant_id = ?
    AND courses.instructor_id = ?
    ORDER BY assignments.created_at DESC
");
$stmt->execute([$tenant_id, $instructor_id]);
$assignments = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Assignments</h2>
                <p class="text-muted mb-0">Create assignments and review student submissions.</p>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">Back</a>
        </div>

        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">Create Assignment</div>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="create_assignment" value="1">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Course</label>
                            <select name="course_id" class="form-control" required>
                                <option value="">Select course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo (int) $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="datetime-local" name="due_date" class="form-control">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Max Points</label>
                            <input type="number" step="0.01" min="1" name="max_points" value="100" class="form-control">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Create</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-8"><input type="text" id="assignmentSearch" class="form-control" placeholder="Search assignments..."></div>
            <div class="col-md-4">
                <select id="statusFilter" class="form-control">
                    <option value="all">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Assignment List</div>
            <div class="card-body">
                <table class="table table-bordered table-striped" id="assignmentsTable">
                    <thead><tr><th>Title</th><th>Course</th><th>Due</th><th>Points</th><th>Status</th><th>Submissions</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                        <tr data-status="<?php echo htmlspecialchars($assignment['status']); ?>">
                            <td><?php echo htmlspecialchars($assignment['title']); ?><br><small class="text-muted"><?php echo htmlspecialchars($assignment['description'] ?? ''); ?></small></td>
                            <td><?php echo htmlspecialchars($assignment['course_title']); ?></td>
                            <td><?php echo htmlspecialchars($assignment['due_date'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($assignment['max_points']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($assignment['status']); ?></span></td>
                            <td><?php echo (int) $assignment['submission_count']; ?></td>
                            <td class="d-flex flex-wrap gap-1">
                                <a class="btn btn-primary btn-sm" href="submissions.php?assignment_id=<?php echo (int) $assignment['id']; ?>">Review</a>
                                <form method="POST" class="status-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="change_status" value="1">
                                    <input type="hidden" name="assignment_id" value="<?php echo (int) $assignment['id']; ?>">
                                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                        <?php foreach (['draft','published','archived'] as $status): ?>
                                            <option value="<?php echo $status; ?>" <?php echo $assignment['status'] === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($assignments) === 0): ?><tr><td colspan="7" class="text-center">No assignments created yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <p id="noResults" class="text-center text-muted" style="display:none;">No matching assignments found.</p>
            </div>
        </div>
    </div>
</div>

<script>
const assignmentSearch = document.getElementById('assignmentSearch');
const statusFilter = document.getElementById('statusFilter');
const rows = document.querySelectorAll('#assignmentsTable tbody tr[data-status]');
const noResults = document.getElementById('noResults');
function filterAssignments() {
    const search = assignmentSearch.value.toLowerCase();
    const status = statusFilter.value;
    let count = 0;
    rows.forEach(function (row) {
        const matches = row.textContent.toLowerCase().includes(search) && (status === 'all' || row.dataset.status === status);
        row.style.display = matches ? '' : 'none';
        if (matches) count++;
    });
    noResults.style.display = count === 0 ? 'block' : 'none';
}
assignmentSearch.addEventListener('keyup', filterAssignments);
statusFilter.addEventListener('change', filterAssignments);
</script>

<?php include "../includes/footer.php"; ?>
