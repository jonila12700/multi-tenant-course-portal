<?php
include "../includes/session.php";
include "../config/db.php";

require_role('instructor');

$tenant_id = (int) $_SESSION['tenant_id'];
$instructor_id = (int) $_SESSION['user_id'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_visibility'])) {
    verify_csrf_token();
    $resource_id = (int) ($_POST['resource_id'] ?? 0);
    $visibility = $_POST['visibility'] ?? '';

    $allowed_visibility = ['course', 'private'];

    if (!in_array($visibility, $allowed_visibility, true)) {
        $error = 'Invalid resource visibility.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE resources
            INNER JOIN courses
                ON courses.id = resources.course_id
                AND courses.tenant_id = resources.tenant_id
            SET resources.visibility = ?
            WHERE resources.id = ?
            AND resources.tenant_id = ?
            AND courses.instructor_id = ?
        ");
        $stmt->execute([$visibility, $resource_id, $tenant_id, $instructor_id]);

        $success = 'Resource visibility updated successfully.';
    }
}

$stmt = $pdo->prepare("
    SELECT
        resources.id,
        resources.title,
        resources.description,
        resources.type,
        resources.file_path,
        resources.external_url,
        resources.visibility,
        resources.created_at,
        courses.title AS course_title
    FROM resources
    INNER JOIN courses
        ON courses.id = resources.course_id
        AND courses.tenant_id = resources.tenant_id
    WHERE resources.tenant_id = ?
    AND courses.instructor_id = ?
    ORDER BY resources.created_at DESC
");
$stmt->execute([$tenant_id, $instructor_id]);
$resources = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>

<div class="content">
    <div class="container-fluid">

        <h2 class="mb-4">My Resources</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-md-8">
                <input
                    type="text"
                    id="resourceSearch"
                    class="form-control"
                    placeholder="Search resources..."
                >
            </div>

            <div class="col-md-4">
                <select id="typeFilter" class="form-control">
                    <option value="all">All types</option>
                    <option value="file">File</option>
                    <option value="link">Link</option>
                    <option value="video">Video</option>
                    <option value="text">Text</option>
                </select>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                Uploaded Resources
            </div>

            <div class="card-body">
                <table class="table table-bordered table-striped" id="resourcesTable">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Course</th>
                            <th>Type</th>
                            <th>Visibility</th>
                            <th>Created</th>
                            <th>Open</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($resources) > 0): ?>
                            <?php foreach ($resources as $resource): ?>
                                <tr data-type="<?php echo htmlspecialchars($resource['type']); ?>">
                                    <td><?php echo htmlspecialchars($resource['title']); ?></td>
                                    <td><?php echo htmlspecialchars($resource['course_title']); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($resource['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($resource['visibility']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($resource['created_at']); ?></td>
                                    <td>
                                        <?php if ($resource['type'] === 'file' && $resource['file_path']): ?>
                                            <a
                                                href="../download.php?id=<?php echo (int) $resource['id']; ?>"
                                                class="btn btn-primary btn-sm"
                                                
                                            >
                                                Open
                                            </a>
                                        <?php elseif (($resource['type'] === 'link' || $resource['type'] === 'video') && $resource['external_url']): ?>
                                            <a
                                                href="<?php echo htmlspecialchars($resource['external_url']); ?>"
                                                class="btn btn-primary btn-sm"
                                                
                                            >
                                                Open
                                            </a>
                                        <?php elseif ($resource['type'] === 'text'): ?>
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-sm text-preview"
                                                data-text="<?php echo htmlspecialchars($resource['external_url'] ?? '', ENT_QUOTES); ?>"
                                            >
                                                Preview
                                            </button>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($resource['visibility'] === 'course'): ?>
                                            <form method="POST" class="d-inline visibility-form">
                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="change_visibility" value="1">
                                                <input type="hidden" name="resource_id" value="<?php echo $resource['id']; ?>">
                                                <input type="hidden" name="visibility" value="private">
                                                <button type="submit" class="btn btn-warning btn-sm">Hide</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline visibility-form">
                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="change_visibility" value="1">
                                                <input type="hidden" name="resource_id" value="<?php echo $resource['id']; ?>">
                                                <input type="hidden" name="visibility" value="course">
                                                <button type="submit" class="btn btn-success btn-sm">Show</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">
                                    No resources uploaded yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <p id="noResults" class="text-center text-muted" style="display:none;">
                    No matching resources found.
                </p>
            </div>
        </div>

        <br>

        <a href="upload_resource.php" class="btn btn-primary">Upload Resource</a>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>

    </div>
</div>

<div class="modal fade" id="textModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Text Resource</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre id="textModalBody" class="mb-0" style="white-space: pre-wrap;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
const searchInput = document.getElementById('resourceSearch');
const typeFilter = document.getElementById('typeFilter');
const rows = document.querySelectorAll('#resourcesTable tbody tr[data-type]');
const noResults = document.getElementById('noResults');

function filterResources() {
    const search = searchInput.value.toLowerCase();
    const selectedType = typeFilter.value;
    let visibleCount = 0;

    rows.forEach(function (row) {
        const matchesSearch = row.textContent.toLowerCase().includes(search);
        const matchesType = selectedType === 'all' || row.dataset.type === selectedType;

        row.style.display = matchesSearch && matchesType ? '' : 'none';

        if (matchesSearch && matchesType) {
            visibleCount++;
        }
    });

    noResults.style.display = visibleCount === 0 ? 'block' : 'none';
}

searchInput.addEventListener('keyup', filterResources);
typeFilter.addEventListener('change', filterResources);

document.querySelectorAll('.visibility-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!confirm('Change this resource visibility?')) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('.text-preview').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('textModalBody').textContent = this.dataset.text || '';
        new bootstrap.Modal(document.getElementById('textModal')).show();
    });
});
</script>

<?php include "../includes/footer.php"; ?>



