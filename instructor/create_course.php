<?php
include "../includes/session.php";
include "../config/db.php";

require_role('instructor');

$tenant_id = (int) $_SESSION['tenant_id'];
$instructor_id = (int) $_SESSION['user_id'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $visibility = $_POST['visibility'] ?? 'draft';

    $allowed_visibility = ['draft', 'published'];

    if ($title === '') {
        $error = 'Course title is required.';
    } elseif (!in_array($visibility, $allowed_visibility, true)) {
        $error = 'Invalid visibility.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO courses
                    (tenant_id, instructor_id, title, description, visibility)
                VALUES
                    (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $tenant_id,
                $instructor_id,
                $title,
                $description,
                $visibility
            ]);

            $success = 'Course created successfully.';
        } catch (PDOException $e) {
            $error = 'Failed to create course. Please try again.';
        }
    }
}
?>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="container-fluid">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1">Create Course</h2>
            <p class="text-muted mb-0">Add a new course for your students.</p>
        </div>

        <a href="dashboard.php" class="btn btn-secondary">
            Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">Course Details</div>
        <div class="card-body">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label">Course Title</label>
                    <input
                        type="text"
                        name="title"
                        class="form-control"
                        placeholder="Introduction to Web Development"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea
                        name="description"
                        class="form-control"
                        rows="4"
                        placeholder="What students will learn in this course"
                    ></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Visibility</label>
                    <select name="visibility" class="form-control">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">
                    Create Course
                </button>
            </form>
        </div>
    </div>

    </div>
</div>

<?php include "../includes/footer.php"; ?>



