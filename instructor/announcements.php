<?php
include "../includes/session.php";
include "../config/db.php";
include "../includes/realtime.php";

require_role('instructor');

$tenant_id = (int) $_SESSION['tenant_id'];
$instructor_id = (int) $_SESSION['user_id'];
$error = '';
$success = '';

$stmt = $pdo->prepare("
    SELECT id, title
    FROM courses
    WHERE tenant_id = ?
    AND instructor_id = ?
    ORDER BY title ASC
");
$stmt->execute([$tenant_id, $instructor_id]);
$courses = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    verify_csrf_token();

    $course_id = (int) ($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($course_id <= 0 || $title === '' || $body === '') {
        $error = 'Course, title, and message are required.';
    } else {
        $stmt = $pdo->prepare("
            SELECT id
            FROM courses
            WHERE id = ?
            AND tenant_id = ?
            AND instructor_id = ?
            LIMIT 1
        ");
        $stmt->execute([$course_id, $tenant_id, $instructor_id]);
        $course = $stmt->fetch();

        if (!$course) {
            $error = 'Invalid course selected.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO course_announcements (tenant_id, course_id, created_by, title, body)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tenant_id, $course_id, $instructor_id, $title, $body]);

            $success = 'Announcement posted successfully.';
            log_activity($pdo, 'announcement_posted', 'Professor posted announcement: ' . $title);
            notify_enrolled_students($pdo, $course_id, 'New course announcement', $title, 'announcement');
        }
    }
}

$stmt = $pdo->prepare("
    SELECT course_announcements.*, courses.title AS course_title
    FROM course_announcements
    INNER JOIN courses
        ON courses.id = course_announcements.course_id
        AND courses.tenant_id = course_announcements.tenant_id
    WHERE course_announcements.tenant_id = ?
    AND courses.instructor_id = ?
    ORDER BY course_announcements.created_at DESC
    LIMIT 30
");
$stmt->execute([$tenant_id, $instructor_id]);
$announcements = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Course Announcements</h2>
                <p class="text-muted mb-0">Post updates that students can see live.</p>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">Back</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">New Announcement</div>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="create_announcement" value="1">

                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <select name="course_id" class="form-control" required>
                            <option value="">Select course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" placeholder="Quiz starts in 10 minutes" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="body" class="form-control" rows="4" placeholder="Write the update for students..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Post Announcement</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Recent Announcements</div>
            <div class="card-body">
                <?php if (count($announcements) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="list-group-item">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                                    <small class="text-muted"><?php echo htmlspecialchars($announcement['created_at']); ?></small>
                                </div>
                                <small class="text-muted"><?php echo htmlspecialchars($announcement['course_title']); ?></small>
                                <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($announcement['body'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No announcements posted yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
