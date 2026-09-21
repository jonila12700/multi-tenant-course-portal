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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $course_id = (int) ($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'file';
    $external_url = trim($_POST['external_url'] ?? '');
    $text_content = trim($_POST['text_content'] ?? '');

    $allowed_types = ['file', 'link', 'video', 'text'];
    $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'zip'];
    $allowed_mime_types = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/webm',
        'application/zip',
        'application/x-zip-compressed',
    ];
    $max_file_size = 10 * 1024 * 1024;

    if ($course_id <= 0 || $title === '') {
        $error = 'Course and title are required.';
    } elseif (!in_array($type, $allowed_types, true)) {
        $error = 'Invalid resource type.';
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
            $file_path = null;
            $mime_type = null;
            $file_size = null;

            if ($type === 'file') {
                if (!isset($_FILES['resource_file']) || $_FILES['resource_file']['error'] !== UPLOAD_ERR_OK
                    || !is_uploaded_file($_FILES['resource_file']['tmp_name'])) {
                    $error = 'Please upload a valid file.';
                } else {
                    $original_name = basename($_FILES['resource_file']['name']);
                    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    $file_size = (int) $_FILES['resource_file']['size'];
                    $detected_mime = mime_content_type($_FILES['resource_file']['tmp_name']);

                    if ($file_size > $max_file_size) {
                        $error = 'File is too large. Maximum allowed size is 10MB.';
                    } elseif (!in_array($extension, $allowed_extensions, true)) {
                        $error = 'This file type is not allowed.';
                    } elseif (!in_array($detected_mime, $allowed_mime_types, true)) {
                        $error = 'This file format is not allowed.';
                    } else {
                        $upload_dir = "../uploads/tenant_" . $tenant_id . "/";

                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        $safe_name = uniqid('resource_', true) . '.' . $extension;
                        $target_path = $upload_dir . $safe_name;

                        if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $target_path)) {
                            $file_path = "uploads/tenant_" . $tenant_id . "/" . $safe_name;
                            $mime_type = $detected_mime;
                        } else {
                            $error = 'Failed to upload file.';
                        }
                    }
                }
            }

            if (($type === 'link' || $type === 'video') && ($external_url === '' || !filter_var($external_url, FILTER_VALIDATE_URL))) {
                $error = 'A valid URL is required for link or video resources.';
            }

            if ($type === 'text' && $text_content === '') {
                $error = 'Text content is required.';
            }

            if ($type === 'text') {
                $external_url = $text_content;
            }

            if ($error === '') {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO resources
                            (
                                tenant_id,
                                course_id,
                                uploaded_by,
                                title,
                                description,
                                type,
                                file_path,
                                external_url,
                                mime_type,
                                file_size,
                                visibility
                            )
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'course')
                    ");

                    $stmt->execute([
                        $tenant_id,
                        $course_id,
                        $instructor_id,
                        $title,
                        $description,
                        $type,
                        $file_path,
                        $external_url,
                        $mime_type,
                        $file_size
                    ]);

                    $success = 'Resource uploaded successfully.';
                    log_activity($pdo, 'resource_uploaded', 'New resource added: ' . $title);
                    notify_enrolled_students($pdo, $course_id, 'New course resource', $title . ' was added to your course.', 'course');
                    notify_role($pdo, 'tenant_admin', 'Resource uploaded', ($_SESSION['name'] ?? 'Professor') . ' uploaded ' . $title, 'resource');
                } catch (PDOException $e) {
                    $error = 'Failed to save resource. Please try again.';
                }
            }
        }
    }
}
?>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="container-fluid">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1">Upload Resource</h2>
            <p class="text-muted mb-0">Attach files, links, videos, or text material to your courses.</p>
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
        <div class="card-header">Resource Details</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="resourceForm">
                <?php echo csrf_field(); ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
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

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" id="resourceType" class="form-control" required>
                            <option value="file">File</option>
                            <option value="link">Link</option>
                            <option value="video">Video</option>
                            <option value="text">Text Material</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Resource Title</label>
                    <input type="text" name="title" class="form-control" placeholder="Week 1 lecture notes" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Short context for students"></textarea>
                </div>

                <div class="mb-3" id="fileField">
                    <label class="form-label">Upload File</label>
                    <input type="file" name="resource_file" id="resourceFile" class="form-control">
                    <small id="fileName" class="text-muted"></small>
                </div>

                <div class="mb-3" id="urlField" style="display:none;">
                    <label class="form-label">External URL</label>
                    <input type="url" name="external_url" class="form-control" placeholder="https://example.com/resource">
                </div>

                <div class="mb-3" id="textField" style="display:none;">
                    <label class="form-label">Text Content</label>
                    <textarea name="text_content" class="form-control" rows="5" placeholder="Write the material here"></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    Upload Resource
                </button>
            </form>
        </div>
    </div>

    </div>
</div>

<script>
const typeSelect = document.getElementById('resourceType');
const fileField = document.getElementById('fileField');
const urlField = document.getElementById('urlField');
const textField = document.getElementById('textField');
const fileInput = document.getElementById('resourceFile');
const fileName = document.getElementById('fileName');
const form = document.getElementById('resourceForm');

function updateFields() {
    const type = typeSelect.value;

    fileField.style.display = type === 'file' ? 'block' : 'none';
    urlField.style.display = (type === 'link' || type === 'video') ? 'block' : 'none';
    textField.style.display = type === 'text' ? 'block' : 'none';
}

typeSelect.addEventListener('change', updateFields);

fileInput.addEventListener('change', function () {
    fileName.textContent = this.files.length ? 'Selected: ' + this.files[0].name : '';
});

form.addEventListener('submit', function (event) {
    if (!confirm('Upload this resource?')) {
        event.preventDefault();
    }
});

updateFields();
</script>

<?php include "../includes/footer.php"; ?>



