<?php
include "../includes/session.php";
include "../config/db.php";

require_role('instructor');

$tenant_id = (int) $_SESSION['tenant_id'];
$instructor_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT id, title, description, visibility, start_date, end_date, created_at
    FROM courses
    WHERE tenant_id = ?
    AND instructor_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$tenant_id, $instructor_id]);
$courses = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="container-fluid">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Professor Dashboard</h2>
                <p class="text-muted mb-0">
                    Welcome back, <b><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></b>
                </p>
            </div>

            <a href="../logout.php" class="btn btn-danger">
                Logout
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-header">Quick Actions</div>
            <div class="card-body d-flex flex-wrap gap-2">
            <a href="create_course.php" class="btn btn-primary">
                Create Course
            </a>

            <a href="upload_resource.php" class="btn btn-success">
                Upload Resource
            </a>

            <a href="assignments.php" class="btn btn-warning">
                Assignments
            </a>

            <a href="announcements.php" class="btn btn-info">
                Announcements
            </a>

            <a href="live_quiz.php" class="btn btn-secondary">
                Live Quiz
            </a>

            <a href="progress.php" class="btn btn-outline-primary">
                Student Progress
            </a>

            <a href="resources.php" class="btn btn-dark">
                My Resources
            </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                My Courses
            </div>

            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Visibility</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($courses) > 0): ?>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                                    <td><?php echo htmlspecialchars($course['description'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($course['visibility']); ?></td>
                                    <td><?php echo htmlspecialchars($course['start_date'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($course['end_date'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($course['created_at']); ?></td>
                                    <td>
                                        <a href="upload_resource.php" class="btn btn-primary btn-sm">Add Resource</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">
                                    No courses assigned yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include "../includes/footer.php"; ?>

