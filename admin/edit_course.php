<?php
include "../includes/session.php";
include "../config/db.php";

require_role('tenant_admin');

$tenant_id = (int) $_SESSION['tenant_id'];
$course_id = (int) ($_GET['id'] ?? 0);
$error = '';
$success = '';

$stmt = $pdo->prepare("SELECT id, name FROM users WHERE tenant_id = ? AND role = 'instructor' AND status = 'active' ORDER BY name ASC");
$stmt->execute([$tenant_id]);
$instructors = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND tenant_id = ? LIMIT 1");
$stmt->execute([$course_id, $tenant_id]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: courses.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_course'])) {
    verify_csrf_token();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $instructor_id = ($_POST['instructor_id'] ?? '') !== '' ? (int) $_POST['instructor_id'] : null;
    $visibility = $_POST['visibility'] ?? 'draft';
    $start_date = ($_POST['start_date'] ?? '') !== '' ? $_POST['start_date'] : null;
    $end_date = ($_POST['end_date'] ?? '') !== '' ? $_POST['end_date'] : null;

    if ($title === '') {
        $error = 'Course title is required.';
    } elseif (!in_array($visibility, ['draft', 'published', 'archived'], true)) {
        $error = 'Invalid course status.';
    } else {
        if ($instructor_id !== null) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ? AND role = 'instructor' LIMIT 1");
            $stmt->execute([$instructor_id, $tenant_id]);
            if (!$stmt->fetch()) {
                $error = 'Invalid instructor selected.';
            }
        }

        if ($error === '') {
            $stmt = $pdo->prepare("UPDATE courses SET instructor_id = ?, title = ?, description = ?, visibility = ?, start_date = ?, end_date = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$instructor_id, $title, $description, $visibility, $start_date, $end_date, $course_id, $tenant_id]);
            $success = 'Course updated successfully.';

            $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmt->execute([$course_id, $tenant_id]);
            $course = $stmt->fetch();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    verify_csrf_token();

    $confirm = trim($_POST['confirm_delete'] ?? '');
    if ($confirm !== 'DELETE') {
        $error = 'Type DELETE to confirm course deletion.';
    } else {
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$course_id, $tenant_id]);
        header('Location: courses.php');
        exit;
    }
}
?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/sidebar_admin.php"; ?>

<div class="content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Edit Course</h2>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($course['title']); ?></p>
            </div>
            <a href="courses.php" class="btn btn-secondary">Back</a>
        </div>

        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">Course Details</div>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="update_course" value="1">
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Course Title</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($course['title']); ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Instructor</label>
                            <select name="instructor_id" class="form-control">
                                <option value="">No instructor</option>
                                <?php foreach ($instructors as $instructor): ?>
                                    <option value="<?php echo (int) $instructor['id']; ?>" <?php echo (int) $course['instructor_id'] === (int) $instructor['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($instructor['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Visibility</label>
                            <select name="visibility" class="form-control">
                                <?php foreach (['draft','published','archived'] as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo $course['visibility'] === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($course['start_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($course['end_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-10 mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <button class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>

        <div class="card border-danger">
            <div class="card-header bg-danger text-white">Delete Course</div>
            <div class="card-body">
                <p class="text-muted">This deletes the course and related records through database relationships.</p>
                <form method="POST" onsubmit="return confirm('Delete this course permanently?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="delete_course" value="1">
                    <label class="form-label">Type DELETE to confirm</label>
                    <input type="text" name="confirm_delete" class="form-control mb-3" placeholder="DELETE" required>
                    <button class="btn btn-danger">Delete Course</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include "../includes/footer.php"; ?>
