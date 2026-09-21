<?php
include "../includes/session.php";
include "../config/db.php";

require_role('tenant_admin');

$tenant_id = (int) $_SESSION['tenant_id'];

// STATISTIKA VETEM PER TENANT-IN AKTUAL
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM users WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
$user_count = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM courses WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
$course_count = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM resources WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
$file_count = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM course_enrollments WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
$enrollment_count = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT name, slug FROM tenants WHERE id = ? LIMIT 1");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch();
?>

<?php include "../includes/header.php"; ?>
<?php include "../includes/sidebar_admin.php"; ?>

<div class="content">

    <div class="container-fluid">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Admin Dashboard</h2>
                <p class="text-muted mb-0">
                    Welcome back, <b><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></b>
                </p>
            </div>

            <a href="../logout.php" class="btn btn-danger">
                Logout
            </a>
        </div>

        <div class="row">

            <div class="col-md-3">
                <div class="card text-white bg-primary mb-3 stat-card">
                    <div class="card-body">
                        <h5>Total Users</h5>
                        <h3 data-live-stat="users_count"><?php echo $user_count; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card text-white bg-success mb-3 stat-card">
                    <div class="card-body">
                        <h5>Total Courses</h5>
                        <h3 data-live-stat="courses_count"><?php echo $course_count; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card text-white bg-warning mb-3 stat-card">
                    <div class="card-body">
                        <h5>Total Files</h5>
                        <h3 data-live-stat="resources_count"><?php echo $file_count; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card text-white bg-info mb-3 stat-card">
                    <div class="card-body">
                        <h5>Enrollments</h5>
                        <h3 data-live-stat="active_enrollments"><?php echo $enrollment_count; ?></h3>
                    </div>
                </div>
            </div>

        </div>

        <div class="card mt-3">
            <div class="card-header">Quick Actions</div>
            <div class="card-body d-flex flex-wrap gap-2">

            <a href="users.php" class="btn btn-dark">
                Manage Users
            </a>

            <a href="courses.php" class="btn btn-dark">
                Manage Courses
            </a>

            <a href="enrollments.php" class="btn btn-dark">
                Manage Enrollments
            </a>

            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">Class Signup Code</div>
            <div class="card-body">
                <p class="mb-2 text-muted">
                    Share this code so students or professors can create their own account from the Join Organization page.
                </p>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <code class="join-code"><?php echo htmlspecialchars($tenant['slug'] ?? ''); ?></code>
                    <a href="../join.php" class="btn btn-primary btn-sm">Open Join Page</a>
                </div>
            </div>
        </div>

    </div>

</div>

<?php include "../includes/footer.php"; ?>
