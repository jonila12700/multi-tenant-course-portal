<?php
include "../includes/session.php";
include "../config/db.php";
include "../includes/realtime.php";

require_role('student');

$tenant_id = (int) $_SESSION['tenant_id'];
$user_id = (int) $_SESSION['user_id'];
$course_id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_complete'])) {
    verify_csrf_token();

    $resource_id = (int) ($_POST['resource_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT resources.id
        FROM resources
        INNER JOIN courses
            ON courses.id = resources.course_id
            AND courses.tenant_id = resources.tenant_id
        INNER JOIN course_enrollments
            ON course_enrollments.course_id = courses.id
            AND course_enrollments.tenant_id = courses.tenant_id
        WHERE resources.id = ?
        AND resources.tenant_id = ?
        AND courses.id = ?
        AND course_enrollments.tenant_id = ?
        AND course_enrollments.user_id = ?
        AND course_enrollments.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$resource_id, $tenant_id, $course_id, $tenant_id, $user_id]);
    $resource = $stmt->fetch();

    if ($resource) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM resource_progress
            WHERE tenant_id = ?
            AND resource_id = ?
            AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$tenant_id, $resource_id, $user_id]);
        $progress = $stmt->fetch();

        if ($progress) {
            $stmt = $pdo->prepare("
                DELETE FROM resource_progress
                WHERE tenant_id = ?
                AND resource_id = ?
                AND user_id = ?
            ");
            $stmt->execute([$tenant_id, $resource_id, $user_id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO resource_progress (tenant_id, resource_id, user_id)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$tenant_id, $resource_id, $user_id]);
            log_activity($pdo, 'resource_completed', ($_SESSION['name'] ?? 'Student') . ' completed a resource.');
        }
    }
}

$stmt = $pdo->prepare("
    SELECT
        courses.id,
        courses.title,
        courses.description,
        users.name AS instructor_name
    FROM courses
    INNER JOIN course_enrollments
        ON course_enrollments.course_id = courses.id
        AND course_enrollments.tenant_id = courses.tenant_id
    LEFT JOIN users
        ON users.id = courses.instructor_id
        AND users.tenant_id = courses.tenant_id
    WHERE courses.id = ?
    AND courses.tenant_id = ?
    AND course_enrollments.tenant_id = ?
    AND course_enrollments.user_id = ?
    AND course_enrollments.status = 'active'
    AND courses.visibility = 'published'
    LIMIT 1
");
$stmt->execute([$course_id, $tenant_id, $tenant_id, $user_id]);
$course = $stmt->fetch();

if (!$course) {
    header("Location: dashboard.php");
    exit();
}

$stmt = $pdo->prepare("
    SELECT
        resources.id,
        resources.title,
        resources.description,
        resources.type,
        resources.file_path,
        resources.external_url,
        resources.created_at,
        resource_progress.id AS progress_id
    FROM resources
    LEFT JOIN resource_progress
        ON resource_progress.resource_id = resources.id
        AND resource_progress.tenant_id = resources.tenant_id
        AND resource_progress.user_id = ?
    WHERE resources.tenant_id = ?
    AND resources.course_id = ?
    AND resources.visibility IN ('course', 'public')
    ORDER BY resources.created_at DESC
");
$stmt->execute([$user_id, $tenant_id, $course_id]);
$resources = $stmt->fetchAll();
?>

<?php include "../includes/header.php"; ?>

<div class="container mt-4">

    <a href="dashboard.php" class="btn btn-secondary mb-3">
        Back
    </a>

    <h2><?php echo htmlspecialchars($course['title']); ?></h2>

    <p>
        <b>Professor:</b>
        <?php echo htmlspecialchars($course['instructor_name'] ?? ''); ?>
    </p>

    <p>
        <?php echo htmlspecialchars($course['description'] ?? ''); ?>
    </p>

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

    <div class="card mt-4">
        <div class="card-header">
            Course Resources
        </div>

        <div class="card-body">

            <table class="table table-bordered table-striped" id="resourcesTable">
                <thead>
                    <tr>
                        <th>Resource</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Uploaded</th>
                        <th>Open</th>
                        <th>Progress</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($resources) > 0): ?>
                        <?php foreach ($resources as $resource): ?>
                            <tr data-type="<?php echo htmlspecialchars($resource['type']); ?>">
                                <td><?php echo htmlspecialchars($resource['title']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($resource['description'] ?? ''); ?>

                                    <?php if ($resource['type'] === 'text' && $resource['external_url']): ?>
                                        <div class="alert alert-light mt-2 mb-0">
                                            <?php echo nl2br(htmlspecialchars($resource['external_url'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($resource['type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($resource['created_at']); ?></td>
                                <td>
                                    <?php if ($resource['type'] === 'file' && $resource['file_path']): ?>
                                        <a
                                            href="../download.php?id=<?php echo (int) $resource['id']; ?>"
                                            class="btn btn-primary btn-sm"
                                            
                                        >
                                            Download File
                                        </a>
                                    <?php elseif (($resource['type'] === 'link' || $resource['type'] === 'video') && $resource['external_url']): ?>
                                        <a
                                            href="<?php echo htmlspecialchars($resource['external_url']); ?>"
                                            class="btn btn-primary btn-sm"
                                            
                                        >
                                            Open Link
                                        </a>
                                    <?php elseif ($resource['type'] === 'text'): ?>
                                        <span class="text-muted">Shown here</span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="toggle_complete" value="1">
                                        <input type="hidden" name="resource_id" value="<?php echo (int) $resource['id']; ?>">
                                        <?php if ($resource['progress_id']): ?>
                                            <button type="submit" class="btn btn-success btn-sm">Completed</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-outline-success btn-sm">Mark Complete</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">
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

        if (matchesSearch && matchesType) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    noResults.style.display = visibleCount === 0 ? 'block' : 'none';
}

searchInput.addEventListener('keyup', filterResources);
typeFilter.addEventListener('change', filterResources);
</script>

<?php include "../includes/footer.php"; ?>

