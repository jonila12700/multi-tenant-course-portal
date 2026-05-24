<?php
include "../includes/session.php";
include "../config/db.php";

require_role('student');

$tenant_id = (int) $_SESSION['tenant_id'];
$user_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT course_announcements.*, courses.title AS course_title, users.name AS professor_name
    FROM course_announcements
    INNER JOIN courses ON courses.id = course_announcements.course_id
    INNER JOIN course_enrollments ON course_enrollments.course_id = courses.id
    LEFT JOIN users ON users.id = course_announcements.created_by
    WHERE course_announcements.tenant_id = ?
    AND course_enrollments.tenant_id = ?
    AND course_enrollments.user_id = ?
    AND course_enrollments.status = 'active'
    ORDER BY course_announcements.created_at DESC
    LIMIT 50
");
$stmt->execute([$tenant_id, $tenant_id, $user_id]);
$announcements = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Course Announcements</h2>
                <p class="text-muted mb-0">Live updates from your professors.</p>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">Back</a>
        </div>

        <div class="card">
            <div class="card-header">Latest Updates</div>
            <div class="card-body">
                <?php if (count($announcements) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="list-group-item">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                                    <small class="text-muted"><?php echo htmlspecialchars($announcement['created_at']); ?></small>
                                </div>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($announcement['course_title']); ?>
                                    · <?php echo htmlspecialchars($announcement['professor_name'] ?? 'Professor'); ?>
                                </small>
                                <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($announcement['body'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No announcements yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
