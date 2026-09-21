<?php
include "../includes/session.php";
include "../config/db.php";

require_role('student');

$tenant_id = (int) $_SESSION['tenant_id'];
$student_id = (int) $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    verify_csrf_token();

    $assignment_id = (int) ($_POST['assignment_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    $stmt = $pdo->prepare("
        SELECT assignments.id, assignments.course_id
        FROM assignments
        INNER JOIN courses
            ON courses.id = assignments.course_id
            AND courses.tenant_id = assignments.tenant_id
        INNER JOIN course_enrollments
            ON course_enrollments.course_id = courses.id
            AND course_enrollments.tenant_id = courses.tenant_id
        WHERE assignments.id = ?
        AND assignments.tenant_id = ?
        AND assignments.status = 'published'
        AND course_enrollments.tenant_id = ?
        AND course_enrollments.user_id = ?
        AND course_enrollments.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$assignment_id, $tenant_id, $tenant_id, $student_id]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        $error = 'Invalid assignment selected.';
    } elseif (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== UPLOAD_ERR_OK
        || !is_uploaded_file($_FILES['submission_file']['tmp_name'])) {
        $error = 'Please upload a valid file.';
    } else {
        $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'zip'];
        $allowed_mime_types = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'application/zip',
            'application/x-zip-compressed',
        ];
        $max_file_size = 10 * 1024 * 1024;
        $original_name = preg_replace('/[\x00-\x1F\x7F]+/', '', basename($_FILES['submission_file']['name']));
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $file_size = (int) $_FILES['submission_file']['size'];
        $detected_mime = mime_content_type($_FILES['submission_file']['tmp_name']);

        if ($file_size > $max_file_size) {
            $error = 'File is too large. Maximum allowed size is 10MB.';
        } elseif (!in_array($extension, $allowed_extensions, true)) {
            $error = 'Only PDF, DOC, DOCX, TXT, and ZIP files are allowed.';
        } elseif (!in_array($detected_mime, $allowed_mime_types, true)) {
            $error = 'This file format is not allowed.';
        } else {
            $upload_dir = "../uploads/tenant_" . $tenant_id . "/submissions/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $safe_name = uniqid('submission_', true) . '.' . $extension;
            $target_path = $upload_dir . $safe_name;

            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $target_path)) {
                $file_path = "uploads/tenant_" . $tenant_id . "/submissions/" . $safe_name;
                $stmt = $pdo->prepare("
                    INSERT INTO assignment_submissions (tenant_id, assignment_id, student_id, file_path, original_name, mime_type, file_size, notes, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'submitted')
                    ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), original_name = VALUES(original_name), mime_type = VALUES(mime_type), file_size = VALUES(file_size), notes = VALUES(notes), status = 'submitted', grade = NULL, feedback = NULL, submitted_at = CURRENT_TIMESTAMP, reviewed_at = NULL
                ");
                $stmt->execute([$tenant_id, $assignment_id, $student_id, $file_path, $original_name, $detected_mime, $file_size, $notes]);
                $success = 'Assignment submitted successfully.';
            } else {
                $error = 'Failed to upload submission.';
            }
        }
    }
}

$stmt = $pdo->prepare("
    SELECT assignments.*, courses.title AS course_title, assignment_submissions.id AS submission_id, assignment_submissions.status AS submission_status, assignment_submissions.grade, assignment_submissions.feedback, assignment_submissions.submitted_at
    FROM assignments
    INNER JOIN courses
        ON courses.id = assignments.course_id
        AND courses.tenant_id = assignments.tenant_id
    INNER JOIN course_enrollments
        ON course_enrollments.course_id = courses.id
        AND course_enrollments.tenant_id = courses.tenant_id
    LEFT JOIN assignment_submissions
        ON assignment_submissions.assignment_id = assignments.id
        AND assignment_submissions.tenant_id = assignments.tenant_id
        AND assignment_submissions.student_id = ?
    WHERE assignments.tenant_id = ?
    AND assignments.status = 'published'
    AND course_enrollments.tenant_id = ?
    AND course_enrollments.user_id = ?
    AND course_enrollments.status = 'active'
    ORDER BY assignments.due_date ASC, assignments.created_at DESC
");
$stmt->execute([$student_id, $tenant_id, $tenant_id, $student_id]);
$assignments = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>
<div class="container mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1">My Assignments</h2>
            <p class="text-muted mb-0">Submit assignments for your enrolled courses.</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">Back</a>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <input type="text" id="assignmentSearch" class="form-control mb-3" placeholder="Search assignments...">

    <div class="card">
        <div class="card-header">Assignments</div>
        <div class="card-body">
            <table class="table table-bordered table-striped" id="assignmentsTable">
                <thead><tr><th>Assignment</th><th>Course</th><th>Due</th><th>Status</th><th>Submit</th></tr></thead>
                <tbody>
                <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($assignment['title']); ?></strong><br><small><?php echo htmlspecialchars($assignment['description'] ?? ''); ?></small></td>
                        <td><?php echo htmlspecialchars($assignment['course_title']); ?></td>
                        <td><?php echo htmlspecialchars($assignment['due_date'] ?? ''); ?></td>
                        <td>
                            <?php if ($assignment['submission_id']): ?>
                                <span class="badge bg-success"><?php echo htmlspecialchars($assignment['submission_status']); ?></span><br>
                                <small>Grade: <?php echo htmlspecialchars($assignment['grade'] ?? 'Pending'); ?></small><br>
                                <small><?php echo htmlspecialchars($assignment['feedback'] ?? ''); ?></small>
                            <?php else: ?>
                                <span class="badge bg-warning">Not submitted</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" enctype="multipart/form-data" class="submission-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="submit_assignment" value="1">
                                <input type="hidden" name="assignment_id" value="<?php echo (int) $assignment['id']; ?>">
                                <input type="file" name="submission_file" class="form-control form-control-sm mb-2" required>
                                <input type="text" name="notes" class="form-control form-control-sm mb-2" placeholder="Optional note">
                                <button class="btn btn-primary btn-sm">Submit</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($assignments) === 0): ?><tr><td colspan="5" class="text-center">No assignments available.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
document.getElementById('assignmentSearch').addEventListener('keyup', function () {
    const value = this.value.toLowerCase();
    document.querySelectorAll('#assignmentsTable tbody tr').forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
    });
});
document.querySelectorAll('.submission-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!confirm('Submit this assignment? You can resubmit later if needed.')) {
            event.preventDefault();
        }
    });
});
</script>
<?php include "../includes/footer.php"; ?>
