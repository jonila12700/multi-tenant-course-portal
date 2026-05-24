<?php
require_once "../includes/session.php";
require_once "../config/db.php";

require_role('super_admin');

$stats = [
    'tenants' => 0,
    'users' => 0,
    'courses' => 0,
    'resources' => 0,
];

foreach ($stats as $table => $value) {
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM " . $table);
    $stats[$table] = (int) $stmt->fetch()['total'];
}

$stmt = $pdo->query("
    SELECT tenants.id, tenants.name, tenants.slug, tenants.status, tenants.created_at,
        COUNT(DISTINCT users.id) AS users_count,
        COUNT(DISTINCT courses.id) AS courses_count
    FROM tenants
    LEFT JOIN users ON users.tenant_id = tenants.id
    LEFT JOIN courses ON courses.tenant_id = tenants.id
    GROUP BY tenants.id, tenants.name, tenants.slug, tenants.status, tenants.created_at
    ORDER BY tenants.created_at DESC
");
$tenants = $stmt->fetchAll();
?>
<?php include "../includes/header.php"; ?>
<div class="content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Super Admin</h2>
                <p class="text-muted mb-0">Platform-wide tenant health overview.</p>
            </div>
            <a href="../logout.php" class="btn btn-secondary">Logout</a>
        </div>

        <div class="row mb-4">
            <?php foreach ($stats as $label => $total): ?>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <span class="text-muted"><?php echo h(ucfirst($label)); ?></span>
                            <h3><?php echo (int) $total; ?></h3>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="card-header">Tenants</div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr><th>Name</th><th>Code</th><th>Status</th><th>Users</th><th>Courses</th><th>Created</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $tenant): ?>
                            <tr>
                                <td><?php echo h($tenant['name']); ?></td>
                                <td><span class="join-code"><?php echo h($tenant['slug']); ?></span></td>
                                <td><span class="badge bg-secondary"><?php echo h($tenant['status']); ?></span></td>
                                <td><?php echo (int) $tenant['users_count']; ?></td>
                                <td><?php echo (int) $tenant['courses_count']; ?></td>
                                <td><?php echo h($tenant['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$tenants): ?>
                            <tr><td colspan="6" class="text-center">No tenants found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include "../includes/footer.php"; ?>
