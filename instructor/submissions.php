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
$assignment_id = (int) ($_GET['assignment_id'] ?? 0);
$error = '';
$success = '';

$stmt = $pdo->prepare("SELECT assignments.*, courses.title AS course_title FROM assignments INNER JOIN courses ON courses.id = assignments.course_id AND courses.tenant_id = assignments.tenant_id WHERE assignments.id = ? AND assignments.tenant_id = ? AND courses.instructor_id = ? LIMIT 1");
$stmt->execute([$assignment_id, $tenant_id, $instructor_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    header('Location: assignments.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_submission'])) {
    verify_csrf_token();

    $submission_id = (int) ($_POST['submission_id'] ?? 0);
    $status = $_POST['status'] ?? 'reviewed';
    $grade = ($_POST['grade'] ?? '') !== '' ? (float) $_POST['grade'] : null;
    $feedback = trim($_POST['feedback'] ?? '');

    if (!in_array($status, ['submitted', 'reviewed', 'returned'], true)) {
        $error = 'Invalid submission status.';
    } elseif ($grade !== null && ($grade < 0 || $grade > (float) $assignment['max_points'])) {
        $error = 'Grade must be within assignment points.';
    } else {
        $stmt = $pdo->prepare("UPDATE assignment_submissions SET status = ?, grade = ?, feedback = ?, reviewed_at = NOW() WHERE id = ? AND tenant_id = ? AND assignment_id = ?");
        $stmt->execute([$status, $grade, $feedback, $submission_id, $tenant_id, $assignment_id]);
        $success = 'Submission reviewed successfully.';
    }
}

$stmt = $pdo->prepare("
    SELECT assignment_submissions.*, users.name AS student_name, users.email AS student_email
    FROM assignment_submissions
    INNER JOIN users
        ON users.id = assignment_submissions.student_id
        AND users.tenant_id = assignment_submissions.tenant_id
    WHERE assignment_submissions.tenant_id = ?
    AND assignment_submissions.assignment_id = ?
    ORDER BY assignment_submissions.submitted_at DESC
");
$stmt->execute([$tenant_id, $assignment_id]);
$submissions = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Review Submissions</h2>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($assignment['title']); ?> - <?php echo htmlspecialchars($assignment['course_title']); ?></p>
            </div>
            <a href="assignments.php" class="btn btn-secondary">Back</a>
        </div>

        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <input type="text" id="submissionSearch" class="form-control mb-3" placeholder="Search submissions...">

        <div class="card">
            <div class="card-header">Student Submissions</div>
            <div class="card-body">
                <table class="table table-bordered table-striped" id="submissionsTable">
                    <thead><tr><th>Student</th><th>File</th><th>Status</th><th>Grade</th><th>Submitted</th><th>Review</th></tr></thead>
                    <tbody>
                    <?php foreach ($submissions as $submission): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($submission['student_name']); ?></strong><br><small><?php echo htmlspecialchars($submission['student_email']); ?></small></td>
                            <td><a class="btn btn-primary btn-sm" href="../download_submission.php?id=<?php echo (int) $submission['id']; ?>">Download</a><br><small><?php echo htmlspecialchars($submission['original_name']); ?></small></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($submission['status']); ?></span></td>
                            <td><?php echo htmlspecialchars($submission['grade'] ?? ''); ?> / <?php echo htmlspecialchars($assignment['max_points']); ?></td>
                            <td><?php echo htmlspecialchars($submission['submitted_at']); ?></td>
                            <td>
                                <form method="POST" class="review-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="review_submission" value="1">
                                    <input type="hidden" name="submission_id" value="<?php echo (int) $submission['id']; ?>">
                                    <div class="row g-2">
                                        <div class="col-md-3"><input type="number" step="0.01" min="0" max="<?php echo htmlspecialchars($assignment['max_points']); ?>" name="grade" value="<?php echo htmlspecialchars($submission['grade'] ?? ''); ?>" class="form-control form-control-sm" placeholder="Grade"></div>
                                        <div class="col-md-3">
                                            <select name="status" class="form-control form-control-sm">
                                                <?php foreach (['submitted','reviewed','returned'] as $status): ?>
                                                    <option value="<?php echo $status; ?>" <?php echo $submission['status'] === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4"><input type="text" name="feedback" value="<?php echo htmlspecialchars($submission['feedback'] ?? ''); ?>" class="form-control form-control-sm" placeholder="Feedback"></div>
                                        <div class="col-md-2"><button class="btn btn-success btn-sm w-100">Save</button></div>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($submissions) === 0): ?><tr><td colspan="6" class="text-center">No submissions yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('submissionSearch').addEventListener('keyup', function () {
    const value = this.value.toLowerCase();
    document.querySelectorAll('#submissionsTable tbody tr').forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
    });
});
</script>
<?php include "../includes/footer.php"; ?>
